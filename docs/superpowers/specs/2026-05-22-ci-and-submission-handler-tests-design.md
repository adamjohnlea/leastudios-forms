# Design: CI gate + Submission_Handler test slice

**Date:** 2026-05-22
**Status:** Approved

## Problem

`leastudios-forms` has clean PHPCS and PHPStan baselines and a passing PHPUnit
suite, but two gaps leave that state unprotected:

1. **No CI.** Nothing enforces the zero-warning lint baseline or the passing
   test suite on push or pull request — it relies on developers remembering to
   run `composer lint && composer test` locally, so it will drift.
2. **The critical path is untested.** Of ~6,300 lines in `src/`, only three test
   classes exist (field registry, form-builder JSON validator, a shared date
   util). `Submission_Handler` — the orchestrator that ingests untrusted form
   input (spam check → rate limit → sanitize → validate → store → notify) — has
   zero regression coverage.

This work adds a CI gate and the first slice of pipeline coverage. It is
purely additive: no existing source or test file changes.

## Scope

**In scope**
- A GitHub Actions workflow that runs lint and tests on push to `main` and on
  pull requests.
- One new test file covering every branch of `Submission_Handler::handle()`.

**Out of scope (intended follow-up slices)**
- Tests for `Submission_Controller` (REST surface), `Entry_Repository` /
  `Form_Repository` (data layer), `Email_Notifier` (merge tags), and the spam
  units (`Honeypot`, `Rate_Limiter`).
- Any change to `check-shared.sh` coverage — shared-file drift remains the
  suite-level job in `leastudios-dev-tools`.

## Component 1 — CI workflow

**File:** `.github/workflows/ci.yml` (new)

**Triggers:** `push` to `main`, and `pull_request`.

**Job `lint`** — runs once, no database:
- Steps: checkout → `shivammathur/setup-php@v2` (PHP 8.1) → `composer install`
  (dependency-cached) → `composer phpcs` → `composer phpstan`.
- PHP 8.1 is the project's minimum supported version (`composer.json`
  `"php": ">=8.1"`); lint results do not vary across PHP versions, so one run
  is sufficient.

**Job `test`** — matrix `php: ['8.1', '8.4']` (floor and current stable):
- A MySQL 8 service container provides the test database, with a health check
  gating the test step.
- Steps:
  1. Checkout this repository.
  2. Checkout `adamjohnlea/leastudios-dev-tools` into a sibling path. It is a
     public repo, so the default `GITHUB_TOKEN` clones it without extra
     secrets — the same cross-repo pattern `leastudios-dev-tools`'s own
     `check-shared.yml` already uses.
  3. `shivammathur/setup-php@v2` for the matrix PHP version.
  4. `composer install` (dependency-cached).
  5. `bash leastudios-dev-tools/bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest`
     — installs the WordPress test library to `/tmp/wordpress-tests-lib`, which
     `tests/bootstrap.php` already defaults to (no `WP_TESTS_DIR` override
     needed).
  6. `composer test`.

**Rationale for sibling checkout:** `install-wp-tests.sh` lives in
`leastudios-dev-tools/bin/`, not this repo. Checking out the sibling repo in CI
avoids vendoring a second copy of the script (which the suite's
shared-by-duplication rules would then have to police) and reuses an existing,
proven org pattern.

## Component 2 — Submission_Handler test slice

**File:** `tests/SubmissionHandlerTest.php` (new)

**Shape:** namespace `LEAStudios\Forms\Tests`, extends the existing
`LEAStudios\Tests\TestCase` (which extends `WP_UnitTestCase`). Real
collaborators are wired exactly as `Plugin::init()` wires them — consistent
with `FieldsValidatorTest`. No mocks; this matches the suite's
integration-test style.

**Fixtures:**
- `set_up()` builds a `Field_Registry` (with defaults), the repositories, spam
  classes, `Validator`, `Email_Notifier`, and the `Submission_Handler` under
  test.
- A private helper creates a `leastudios_form` post via the WordPress test
  factory and sets its `_leastudios_forms_fields` and
  `_leastudios_forms_settings` post meta (JSON), so each test can declare the
  form shape it needs.

**Tests** — one per branch of `Submission_Handler::handle()`:

| Test | Setup | Asserts |
|---|---|---|
| `test_rejects_honeypot_spam` | honeypot value is a non-empty string | result `success` is false; message is "Spam detected." |
| `test_rejects_unknown_form` | `form_id` points at no post (or a non-`leastudios_form` post) | `success` false; message "Form not found." |
| `test_rejects_over_rate_limit` | form configured with a low `rate_limit`; submit past the limit within the window | `success` false; "Too many submissions…" message |
| `test_returns_validation_errors` | form with a required field; that field submitted empty | `success` false; `errors` array keyed by the field name |
| `test_stores_entry_and_fires_hooks` | a valid submission against a well-formed form | `success` true; a row exists in the entries table for the new entry; the `leastudios_forms_submission_created` action fired |

## Behavioural notes / constraints

- **Entries table:** created once when the plugin's `Migration::maybe_migrate()`
  runs on `plugins_loaded` during the test bootstrap. `WP_UnitTestCase` wraps
  each test in a database transaction and rolls it back, so the row inserted by
  `test_stores_entry_and_fires_hooks` is cleaned up automatically; the table
  itself persists for the suite.
- **Email:** the happy-path test triggers `Email_Notifier`, which calls
  `wp_mail()`. The WordPress test suite intercepts `wp_mail()` with its mock
  mailer, so no real mail is sent and the test needs no SMTP configuration.
- **Honeypot semantics:** `Honeypot::is_spam()` treats `null` (field absent)
  and any non-empty string as spam; an empty string is clean. The spam test
  passes a non-empty string; all non-spam tests pass an empty string.
- **Rate limiter:** `Rate_Limiter::check()` counts submissions per
  hashed-IP + form in a transient. The rate-limit test drives the handler past
  the form's configured `rate_limit` within one window.

## Verification

- `ci.yml` parses as valid YAML and both jobs are green on the first push /
  pull request.
- `composer test` passes locally and in CI with the new test file present;
  the suite grows from 18 tests to 23.
- `composer phpcs` and `composer phpstan` remain at zero findings with the new
  test file included (PHPCS scans `tests/` too).

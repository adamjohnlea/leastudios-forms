# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Read first

Suite-wide conventions are **not repeated here**. Before working in this plugin, read:

- `../../../CLAUDE.md` — repository overview (this is one plugin in the leaStudios suite; the repo root is a WordPress install, not a git repo; each `leastudios-*` plugin is its own git repo).
- `../leastudios-dev-tools/CLAUDE.md` — the "mother" CLAUDE.md: coding standards, security rules (escape/sanitize/nonce/capability), database/REST/i18n conventions inherited by every plugin.

`docs/developer-handbook.md` is the authoritative reference for every action/filter this plugin exposes — consult it before adding or changing a hook.

## Commands

Run from this plugin directory:

```bash
composer install            # one-time
composer lint               # phpcs + phpstan
composer phpcs               # WordPress Coding Standards
composer phpcbf              # auto-fix WPCS issues
composer phpstan             # static analysis (level 6, scans src/)
composer test                # PHPUnit (needs the WP test library)
vendor/bin/phpunit --filter FieldRegistryTest   # single test class
```

PHPUnit needs the shared WP test library installed once (drops into `/tmp/wordpress-tests-lib/`):

```bash
bash ../leastudios-dev-tools/bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

`phpstan.neon` sets `treatPhpDocTypesAsCertain: false` on purpose — `apply_filters()` callbacks can return anything at runtime, so the runtime defensive checks after filters are intentional and must not be "simplified away" to satisfy the WordPress stubs' narrowed return types.

## Architecture

**`src/Plugin.php` is the single wiring point.** There is no DI container — `Plugin::init()` constructs every component by hand and passes dependencies through constructors. To add a feature, instantiate it there and register its hooks. Admin-only components are guarded by `is_admin()`; the `leastudios-mailer` integration only loads when that sibling plugin is active (`Suite_Detector::is_active()`).

**Data model:**
- A form is a custom post type `leastudios_form` (`CPT/Form_Post_Type.php`). Field definitions live in post meta `_leastudios_forms_fields` as a **JSON blob**; form settings in `_leastudios_forms_settings` as JSON (hydrated into a `Form_Settings` value object). All form data access goes through `Form/Form_Repository.php`.
- Submissions ("entries") are rows in the custom table `{prefix}leastudios_forms_entries`, accessed via `Entry/Entry_Repository.php`. Field values are stored as a JSON blob in the `field_data` column.

**Submission pipeline** — both entry points call the same `Submission/Submission_Handler::handle()`:
1. REST: `POST /wp-json/leastudios-forms/v1/submissions` (`REST/Submission_Controller.php`, extends `WP_REST_Controller`).
2. No-JS fallback: `admin-post.php` actions `leastudios_forms_submit` / `_nopriv` (`Plugin::handle_fallback_submission()`).

`handle()` runs: spam check (honeypot) → form lookup → IP rate limit → per-field-type sanitize → validate → store entry → send notifications. Each stage exposes a hook (see the developer handbook's "Hook Execution Order" section). When changing submission behavior, keep both entry points in sync — they must produce identical results.

**Field types** implement the `Field/Field_Type` interface (`get_type`, `get_label`, `sanitize`, `validate`, `render`) and are registered through `Field/Field_Registry`. The 11 built-ins live in `src/Field/Types/`. Third parties add custom types via the `leastudios_forms_field_types` action — do not hard-code type lists.

**Database migrations** (`Database/Migration.php`): schema version is tracked in the option `leastudios_forms_schema_version`; current target is `SCHEMA_VERSION = 3`. `maybe_migrate()` runs on activation and on every `init`. Note v2 added payment columns and v3 removes them — **payment/Stripe support was deliberately removed from this plugin**; do not reintroduce it.

## Shared-by-duplication classes

`src/Security/Nonce.php` and `src/Shared/Datetime_Util.php` are intentionally byte-identical copies of the same files in sibling plugins (verified by `leastudios-dev-tools/bin/check-shared.sh`, which must pass before release). When editing either, propagate the change to all sibling copies. This plugin does **not** carry `Options_Encryptor` — its unused copy was removed.

# CI Gate + Submission_Handler Test Slice Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a GitHub Actions CI gate (lint + tests) and the first slice of regression coverage for the form submission pipeline.

**Architecture:** One additive CI workflow with a `lint` job (PHPCS + PHPStan on PHP 8.1) and a `test` job (PHPUnit on a PHP 8.1/8.4 matrix, against a MySQL service container). The test job checks out `leastudios-dev-tools` as a sibling repo to reuse its `install-wp-tests.sh`. One new PHPUnit test class exercises every branch of `Submission_Handler::handle()` with real collaborators, matching the existing `WP_UnitTestCase` integration-test style. No existing source or test file is modified.

**Tech Stack:** GitHub Actions, `shivammathur/setup-php`, `ramsey/composer-install`, MySQL 8 service container, PHPUnit 9.6, WordPress test library.

> **Note on commits:** This repository's CLAUDE.md says never commit without explicit user approval. Each task ends with a commit step per plan convention — at execution time, confirm with the user before running it.

> **Note on TDD framing:** These tests characterize *already-shipped* code, so each new test is expected to **pass** on its first run. A failure means either the test is wrong or a real bug was found — investigate, don't paper over it.

---

### Task 1: CI workflow

**Files:**
- Create: `.github/workflows/ci.yml`

- [ ] **Step 1: Create the workflow file**

Create `.github/workflows/ci.yml` with exactly this content:

```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:

jobs:
  lint:
    name: Lint (PHPCS + PHPStan)
    runs-on: ubuntu-latest
    steps:
      - name: Check out repository
        uses: actions/checkout@v4

      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          coverage: none

      - name: Install Composer dependencies
        uses: ramsey/composer-install@v3

      - name: Run PHPCS
        run: composer phpcs

      - name: Run PHPStan
        run: composer phpstan

  test:
    name: Tests (PHP ${{ matrix.php }})
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      matrix:
        php: ['8.1', '8.4']
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping --silent"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=10
    steps:
      - name: Check out leastudios-forms
        uses: actions/checkout@v4
        with:
          path: leastudios-forms

      - name: Check out leastudios-dev-tools
        uses: actions/checkout@v4
        with:
          repository: adamjohnlea/leastudios-dev-tools
          path: leastudios-dev-tools

      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: mysqli
          coverage: none

      - name: Install Composer dependencies
        uses: ramsey/composer-install@v3
        with:
          working-directory: leastudios-forms

      - name: Install WordPress test library
        run: bash leastudios-dev-tools/bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest

      - name: Run PHPUnit
        run: composer test
        working-directory: leastudios-forms
```

- [ ] **Step 2: Verify the YAML parses**

Run: `python3 -c "import yaml,sys; yaml.safe_load(open('.github/workflows/ci.yml')); print('YAML OK')"`
Expected: prints `YAML OK` with no traceback.

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/ci.yml
git commit -m "Add GitHub Actions CI workflow for lint and tests"
```

- [ ] **Step 4: Verify CI runs (post-push)**

After this branch is pushed and a PR is opened, confirm in the GitHub Actions tab that both the `lint` job and both `test` matrix jobs (PHP 8.1 and 8.4) complete green. The `lint` job needs no database; the `test` jobs depend on the MySQL service. If the runner ever reports `mysqladmin: command not found`, add a step before "Install WordPress test library": `sudo apt-get update && sudo apt-get install -y mysql-client` — but `ubuntu-latest` ships the MySQL client, so this should not be needed.

---

### Task 2: Test scaffold + honeypot-spam case

**Files:**
- Create: `tests/SubmissionHandlerTest.php`

- [ ] **Step 1: Write the test file**

Create `tests/SubmissionHandlerTest.php` with exactly this content:

```php
<?php
/**
 * Tests for Submission_Handler.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Entry\Entry_Repository;
use LEAStudios\Forms\Field\Field_Registry;
use LEAStudios\Forms\Form\Form_Repository;
use LEAStudios\Forms\Form\Form_Settings;
use LEAStudios\Forms\Notification\Email_Notifier;
use LEAStudios\Forms\Spam\Honeypot;
use LEAStudios\Forms\Spam\Rate_Limiter;
use LEAStudios\Forms\Submission\Submission_Handler;
use LEAStudios\Forms\Submission\Validator;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Submission\Submission_Handler
 */
class SubmissionHandlerTest extends TestCase {

	private Submission_Handler $handler;

	private Form_Repository $form_repo;

	private Entry_Repository $entry_repo;

	public function set_up(): void {
		parent::set_up();

		$field_registry = new Field_Registry();
		$field_registry->register_defaults();

		$this->form_repo  = new Form_Repository();
		$this->entry_repo = new Entry_Repository();

		$this->handler = new Submission_Handler(
			new Validator( $field_registry ),
			$this->entry_repo,
			new Email_Notifier(),
			new Honeypot(),
			new Rate_Limiter(),
			$this->form_repo,
			$field_registry
		);
	}

	/**
	 * Create a leastudios_form post with the given fields and settings.
	 *
	 * @param array<int, array<string, mixed>> $fields   Field configurations.
	 * @param Form_Settings|null               $settings Optional form settings.
	 * @return int The new form post ID.
	 */
	private function create_form( array $fields, ?Form_Settings $settings = null ): int {
		$form_id = self::factory()->post->create(
			[
				'post_type'  => 'leastudios_form',
				'post_title' => 'Test Form',
			]
		);

		$this->form_repo->save_fields( $form_id, $fields );
		$this->form_repo->save_settings( $form_id, $settings ?? new Form_Settings() );

		return $form_id;
	}

	public function test_rejects_honeypot_spam(): void {
		$form_id = $this->create_form(
			[
				[
					'name' => 'message',
					'type' => 'text',
				],
			]
		);

		$result = $this->handler->handle(
			$form_id,
			[ 'message' => 'Hello' ],
			'i am a bot',
			'203.0.113.1',
			'PHPUnit',
			null
		);

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'Spam detected.', $result['message'] );
	}
}
```

Notes for the implementer:
- `TestCase` lives in `tests/TestCase.php`; it extends `WP_UnitTestCase`. `set_up()` is the WordPress-suite lifecycle hook (snake_case, via the Yoast polyfills) — the existing `FieldsValidatorTest` uses the same name.
- `Submission_Handler::handle()` signature is `handle( int $form_id, array $raw_data, ?string $honeypot_value, string $ip, string $user_agent, ?int $user_id ): array` and returns `array{success: bool, message: string, errors: array<string,string>}`.
- `Honeypot::is_spam()` treats a non-empty string as spam, so passing `'i am a bot'` triggers the spam branch. Spam is checked *before* the form is looked up, but a valid form is created here anyway for realism.
- PHPCS for `tests/` relaxes docblock and output-escaping rules but still enforces the rest of the WordPress standard. The project requires **short array syntax** (`[ ... ]`) — `array( ... )` is rejected by the `Generic.Arrays.DisallowLongArraySyntax` rule in `phpcs.xml.dist`. Also use Yoda conditions and spacing inside parentheses, matching `FieldsValidatorTest`.

- [ ] **Step 2: Run the test (expect PASS)**

Run: `vendor/bin/phpunit --filter test_rejects_honeypot_spam`
Expected: `OK (1 test, 2 assertions)`.

- [ ] **Step 3: Run PHPCS on the new file**

Run: `vendor/bin/phpcs tests/SubmissionHandlerTest.php`
Expected: no errors or warnings.

- [ ] **Step 4: Commit**

```bash
git add tests/SubmissionHandlerTest.php
git commit -m "Add Submission_Handler test scaffold and honeypot-spam case"
```

---

### Task 3: Unknown-form case

**Files:**
- Modify: `tests/SubmissionHandlerTest.php`

- [ ] **Step 1: Add the test method**

In `tests/SubmissionHandlerTest.php`, add this method immediately after `test_rejects_honeypot_spam()` (inside the class):

```php
	public function test_rejects_unknown_form(): void {
		$result = $this->handler->handle(
			999999,
			[ 'message' => 'Hello' ],
			'',
			'203.0.113.2',
			'PHPUnit',
			null
		);

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'Form not found.', $result['message'] );
	}
```

Notes for the implementer:
- `999999` is a post ID that does not exist; `Form_Repository::get_form()` returns `null` for a missing post or one whose `post_type` is not `leastudios_form`, which drives the "Form not found." branch.
- The honeypot value is `''` (empty string) — `Honeypot::is_spam()` treats an empty string as *not* spam, so the handler proceeds past the spam check to the form lookup.

- [ ] **Step 2: Run the test (expect PASS)**

Run: `vendor/bin/phpunit --filter test_rejects_unknown_form`
Expected: `OK (1 test, 2 assertions)`.

- [ ] **Step 3: Run PHPCS on the file**

Run: `vendor/bin/phpcs tests/SubmissionHandlerTest.php`
Expected: no errors or warnings.

- [ ] **Step 4: Commit**

```bash
git add tests/SubmissionHandlerTest.php
git commit -m "Add Submission_Handler test for unknown form"
```

---

### Task 4: Rate-limit case

**Files:**
- Modify: `tests/SubmissionHandlerTest.php`

- [ ] **Step 1: Add the test method**

In `tests/SubmissionHandlerTest.php`, add this method immediately after `test_rejects_unknown_form()` (inside the class):

```php
	public function test_rejects_over_rate_limit(): void {
		$form_id = $this->create_form(
			[
				[
					'name' => 'message',
					'type' => 'text',
				],
			],
			new Form_Settings( rate_limit: 1 )
		);

		$first = $this->handler->handle(
			$form_id,
			[ 'message' => 'Hello' ],
			'',
			'203.0.113.3',
			'PHPUnit',
			null
		);

		$second = $this->handler->handle(
			$form_id,
			[ 'message' => 'Hello' ],
			'',
			'203.0.113.3',
			'PHPUnit',
			null
		);

		$this->assertTrue( $first['success'] );
		$this->assertFalse( $second['success'] );
		$this->assertSame( 'Too many submissions. Please try again later.', $second['message'] );
	}
```

Notes for the implementer:
- `Form_Settings` is a value object with an all-named-defaults constructor; `new Form_Settings( rate_limit: 1 )` sets the per-window submission cap to 1 and leaves every other setting at its default.
- `Rate_Limiter::check()` counts submissions per hashed-IP + form in a transient. With `rate_limit: 1` and the **same IP** (`203.0.113.3`) for both calls: the first call is allowed (count `0 >= 1` is false) and bumps the counter to 1; the second is rejected (count `1 >= 1` is true).
- The first submission genuinely succeeds and stores an entry — that is expected and harmless; `WP_UnitTestCase` rolls back the database transaction after the test.

- [ ] **Step 2: Run the test (expect PASS)**

Run: `vendor/bin/phpunit --filter test_rejects_over_rate_limit`
Expected: `OK (1 test, 3 assertions)`.

- [ ] **Step 3: Run PHPCS on the file**

Run: `vendor/bin/phpcs tests/SubmissionHandlerTest.php`
Expected: no errors or warnings.

- [ ] **Step 4: Commit**

```bash
git add tests/SubmissionHandlerTest.php
git commit -m "Add Submission_Handler test for rate limiting"
```

---

### Task 5: Validation-errors case

**Files:**
- Modify: `tests/SubmissionHandlerTest.php`

- [ ] **Step 1: Add the test method**

In `tests/SubmissionHandlerTest.php`, add this method immediately after `test_rejects_over_rate_limit()` (inside the class):

```php
	public function test_returns_validation_errors(): void {
		$form_id = $this->create_form(
			[
				[
					'name'     => 'email',
					'type'     => 'email',
					'label'    => 'Email',
					'required' => true,
				],
			]
		);

		$result = $this->handler->handle(
			$form_id,
			[ 'email' => '' ],
			'',
			'203.0.113.4',
			'PHPUnit',
			null
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'email', $result['errors'] );
	}
```

Notes for the implementer:
- The form declares one required `email` field; submitting it empty makes `Validator::validate()` return an error keyed by the field `name` (`email`).
- When `Submission_Handler::handle()` gets a non-empty `errors` array it returns `success => false` with `message => 'Please correct the errors below.'` and the populated `errors` array — this test asserts on the `errors` key rather than the message.

- [ ] **Step 2: Run the test (expect PASS)**

Run: `vendor/bin/phpunit --filter test_returns_validation_errors`
Expected: `OK (1 test, 2 assertions)`.

- [ ] **Step 3: Run PHPCS on the file**

Run: `vendor/bin/phpcs tests/SubmissionHandlerTest.php`
Expected: no errors or warnings.

- [ ] **Step 4: Commit**

```bash
git add tests/SubmissionHandlerTest.php
git commit -m "Add Submission_Handler test for validation errors"
```

---

### Task 6: Entry-storage + hooks case

**Files:**
- Modify: `tests/SubmissionHandlerTest.php`

- [ ] **Step 1: Add the test method**

In `tests/SubmissionHandlerTest.php`, add this method immediately after `test_returns_validation_errors()` (inside the class):

```php
	public function test_stores_entry_and_fires_hooks(): void {
		$form_id = $this->create_form(
			[
				[
					'name' => 'message',
					'type' => 'text',
				],
			],
			new Form_Settings( success_message: 'Thanks!' )
		);

		$fired = false;

		add_action(
			'leastudios_forms_submission_created',
			function () use ( &$fired ): void {
				$fired = true;
			}
		);

		$result = $this->handler->handle(
			$form_id,
			[ 'message' => 'Hello there' ],
			'',
			'203.0.113.5',
			'PHPUnit',
			null
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'Thanks!', $result['message'] );
		$this->assertTrue( $fired );
		$this->assertSame( 1, $this->entry_repo->get_total_count( $form_id ) );
	}
```

Notes for the implementer:
- On a successful submission the handler returns `success => true` with `message` set to the form's `success_message` — here `'Thanks!'`, set via `new Form_Settings( success_message: 'Thanks!' )`.
- The handler fires the `leastudios_forms_submission_created` action after the entry is stored; the closure flips `$fired` to confirm it ran.
- `Entry_Repository::get_total_count( $form_id )` returns the row count for that form; it is `1` after exactly one stored submission.
- The entries table (`{prefix}leastudios_forms_entries`) is created once at test-suite bootstrap when the plugin's `Migration::maybe_migrate()` runs on `plugins_loaded`. The default `Form_Settings` has no `notifications`, so `Email_Notifier::send()` is a no-op and no mail is attempted.

- [ ] **Step 2: Run the test (expect PASS)**

Run: `vendor/bin/phpunit --filter test_stores_entry_and_fires_hooks`
Expected: `OK (1 test, 4 assertions)`.

- [ ] **Step 3: Run the full suite**

Run: `composer test`
Expected: `OK (23 tests, ...)` — the suite grows from 18 tests to 23.

- [ ] **Step 4: Run the full lint**

Run: `composer lint`
Expected: PHPCS reports no errors/warnings; PHPStan reports `[OK] No errors`.

- [ ] **Step 5: Commit**

```bash
git add tests/SubmissionHandlerTest.php
git commit -m "Add Submission_Handler test for entry storage and hooks"
```

---

## Self-Review

**Spec coverage:**
- CI workflow on push + PR, `lint` job (PHPCS + PHPStan, PHP 8.1) — Task 1.
- `test` job, matrix PHP 8.1/8.4, MySQL service, sibling `leastudios-dev-tools` checkout, `install-wp-tests.sh` — Task 1.
- `tests/SubmissionHandlerTest.php`, real collaborators wired as `Plugin::init()` does, `create_form` fixture helper — Task 2.
- Five branch tests: honeypot spam (Task 2), unknown form (Task 3), rate limit (Task 4), validation errors (Task 5), entry storage + hook (Task 6).
- Verification: YAML parses (Task 1), `composer test` reaches 23 tests, `composer lint` stays clean (Task 6). All spec items map to a task.

**Placeholder scan:** No TBD/TODO; every code step contains complete, runnable content.

**Type consistency:** `Submission_Handler::handle()` is called with the same 6-argument shape in every task. `Form_Settings` named arguments (`rate_limit`, `success_message`) match the constructor in `src/Form/Form_Settings.php`. `Entry_Repository::get_total_count()` and `Form_Repository::save_fields()/save_settings()` match the real signatures. The helper is named `create_form` consistently across Tasks 2–6.

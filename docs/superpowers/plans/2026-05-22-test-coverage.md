# leaStudios Forms — Test Coverage Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add PHPUnit coverage for the six untested high-risk areas of `leastudios-forms` — database migration, entry storage, REST submission endpoint, form storage, all 11 field types, and the two spam guards.

**Architecture:** These are *characterization tests* of already-shipped code, not TDD of new code. Each test is written against existing behavior and is expected to **pass on first run**. A failing test means either the test is wrong or a real bug was found — stop and investigate, do not "fix" the source to make a test green without confirming the behavior is actually wrong. Tests extend `LEAStudios\Tests\TestCase` (which extends `WP_UnitTestCase`), so each test runs inside a rolled-back DB transaction. One task = one test area = one branch = one PR.

**Tech Stack:** PHP 8.2, PHPUnit 9.6, the WordPress test library (`/tmp/wordpress-tests-lib/`), WordPress Coding Standards (PHPCS) + PHPStan level 6.

---

## Conventions every task must follow

These are established by the four existing test files (`tests/SubmissionHandlerTest.php`, `tests/FieldsValidatorTest.php`, `tests/FieldRegistryTest.php`, `tests/Datetime_UtilTest.php`). Do not deviate.

- **Namespace:** `LEAStudios\Forms\Tests`.
- **Base class:** extend `LEAStudios\Tests\TestCase`. Use `use LEAStudios\Tests\TestCase;`.
- **Setup method:** `public function set_up(): void` (WordPress convention — *not* `setUp`), and it must call `parent::set_up();` first.
- **`@covers` annotation:** every test class has a class-level `@covers \Fully\Qualified\ClassName` docblock.
- **File location:** flat in `tests/`, filename `<ClassName>Test.php` with no underscores (e.g. `EntryRepositoryTest.php`), matching `SubmissionHandlerTest.php` / `FieldRegistryTest.php`.
- **`declare(strict_types=1);`** at the top of every file.
- **Posts:** create forms with `self::factory()->post->create([ 'post_type' => 'leastudios_form', 'post_title' => '...' ])`.
- Every test must assert something — `phpunit.xml.dist` sets `beStrictAboutTestsThatDoNotTestAnything="true"`.
- After writing each file, run `composer phpcs` for that file and fix any WPCS issues before committing.

**Run commands** (from the `leastudios-forms/` plugin directory):
- Single class: `vendor/bin/phpunit --filter <ClassName>Test`
- Full suite: `composer test`
- Lint: `composer lint`

**Branch + PR per task.** Branch off `main`. Branch names: `test-coverage-migration`, `test-coverage-entry-repository`, `test-coverage-rest-controller`, `test-coverage-form-storage`, `test-coverage-field-types`, `test-coverage-spam-guards`. Open a PR after the task's tests and lint are green, and wait for CI before moving on.

## File Structure

All new files are test files — no `src/` changes. One test class per source class, matching the existing `@covers`-one-class convention.

| New test file | Covers | Task |
|---|---|---|
| `tests/MigrationTest.php` | `Database\Migration` | 1 |
| `tests/EntryRepositoryTest.php` | `Entry\Entry_Repository` | 2 |
| `tests/SubmissionControllerTest.php` | `REST\Submission_Controller` | 3 |
| `tests/FormRepositoryTest.php` | `Form\Form_Repository` | 4 |
| `tests/FormSettingsTest.php` | `Form\Form_Settings` | 4 |
| `tests/TextFieldTest.php` … `tests/AddressFieldTest.php` (11 files) | each `Field\Types\*` class | 5 |
| `tests/HoneypotTest.php` | `Spam\Honeypot` | 6 |
| `tests/RateLimiterTest.php` | `Spam\Rate_Limiter` | 6 |

---

## Task 1: Migration coverage

**Files:**
- Create: `tests/MigrationTest.php`
- Test target: `src/Database/Migration.php`

**Background the engineer needs:** The entries table (`{prefix}leastudios_forms_entries`) already exists in the test database — it is created during the test bootstrap when the plugin runs `Migration::maybe_migrate()`. `WP_UnitTestCase` wraps each test in a DB transaction and rolls it back, **but DDL statements (`CREATE`/`ALTER`/`DROP TABLE`) cause an implicit commit in MySQL and are NOT rolled back.** The tests below are designed around that: test 3 runs `dbDelta` against an already-identical table (a no-op), and test 4 is self-cleaning because the migration itself drops the columns it adds. The `tear_down()` defensively drops the payment columns in case test 4 fails partway. `get_option`/`update_option` writes *are* transactional and roll back normally.

- [ ] **Step 1: Write the test file**

```php
<?php
/**
 * Tests for Migration.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Database\Migration;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Database\Migration
 */
class MigrationTest extends TestCase {

	private const SCHEMA_OPTION = 'leastudios_forms_schema_version';

	public function tear_down(): void {
		// DDL is not rolled back by the test transaction. If test 4 failed
		// partway, defensively drop any payment columns it added.
		global $wpdb;
		$table = Migration::get_table_name();

		foreach ( [ 'payment_status', 'payment_amount', 'payment_currency', 'stripe_payment_id' ] as $column ) {
			$exists = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE '{$column}'" ); // phpcs:ignore WordPress.DB
			if ( ! empty( $exists ) ) {
				$wpdb->query( "ALTER TABLE {$table} DROP COLUMN {$column}" ); // phpcs:ignore WordPress.DB
			}
		}

		parent::tear_down();
	}

	public function test_get_table_name_uses_wpdb_prefix(): void {
		global $wpdb;

		$this->assertSame(
			$wpdb->prefix . 'leastudios_forms_entries',
			Migration::get_table_name()
		);
	}

	public function test_maybe_migrate_is_noop_when_schema_is_current(): void {
		update_option( self::SCHEMA_OPTION, 3 );

		( new Migration() )->maybe_migrate();

		$this->assertSame( 3, (int) get_option( self::SCHEMA_OPTION ) );
	}

	public function test_maybe_migrate_creates_table_and_records_version(): void {
		global $wpdb;

		delete_option( self::SCHEMA_OPTION );

		( new Migration() )->maybe_migrate();

		$this->assertSame( 3, (int) get_option( self::SCHEMA_OPTION ) );

		$table  = Migration::get_table_name();
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB
		$this->assertSame( $table, $exists );
	}

	public function test_maybe_migrate_removes_payment_columns_and_stripe_options(): void {
		global $wpdb;

		$table = Migration::get_table_name();

		// Simulate a v2 install: re-add the payment columns and Stripe options.
		$wpdb->query( // phpcs:ignore WordPress.DB
			"ALTER TABLE {$table}
				ADD COLUMN payment_status varchar(20) DEFAULT NULL,
				ADD COLUMN payment_amount bigint(20) DEFAULT NULL,
				ADD COLUMN payment_currency varchar(3) DEFAULT NULL,
				ADD COLUMN stripe_payment_id varchar(255) DEFAULT NULL"
		);

		update_option(
			'leastudios_forms_options',
			[
				'entry_retention_days'   => 90,
				'stripe_secret_key'      => 'sk_test_xxx',
				'stripe_publishable_key' => 'pk_test_xxx',
			]
		);
		update_option( self::SCHEMA_OPTION, 2 );

		( new Migration() )->maybe_migrate();

		$this->assertSame( 3, (int) get_option( self::SCHEMA_OPTION ) );

		foreach ( [ 'payment_status', 'payment_amount', 'payment_currency', 'stripe_payment_id' ] as $column ) {
			$found = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE '{$column}'" ); // phpcs:ignore WordPress.DB
			$this->assertEmpty( $found, "Column {$column} should have been dropped." );
		}

		$options = get_option( 'leastudios_forms_options' );
		$this->assertArrayNotHasKey( 'stripe_secret_key', $options );
		$this->assertArrayNotHasKey( 'stripe_publishable_key', $options );
		$this->assertSame( 90, $options['entry_retention_days'] );
	}
}
```

- [ ] **Step 2: Run the tests**

Run: `vendor/bin/phpunit --filter MigrationTest`
Expected: 4 tests, 4 passing. If any fail, investigate whether the test is wrong or a real bug exists before changing anything.

- [ ] **Step 3: Lint**

Run: `composer phpcs` — fix any WPCS issues in `tests/MigrationTest.php`.

- [ ] **Step 4: Commit, push, open PR**

```bash
git checkout -b test-coverage-migration
git add tests/MigrationTest.php
git commit -m "Add Migration test coverage"
git push -u origin test-coverage-migration
gh pr create --title "Add Migration test coverage" --body "Covers table-name resolution, idempotent re-runs, fresh table creation, and the v2 to v3 payment-column / Stripe-option removal."
```

---

## Task 2: Entry_Repository coverage

**Files:**
- Create: `tests/EntryRepositoryTest.php`
- Test target: `src/Entry/Entry_Repository.php`

**Background:** `Entry_Repository` does raw `$wpdb` CRUD against the entries table. `create()` always stamps `created_at` with the current UTC time, so the `delete_old_entries()` test back-dates a row with a direct `$wpdb->update` afterwards. All inserts are rolled back by the test transaction.

- [ ] **Step 1: Write the test file**

```php
<?php
/**
 * Tests for Entry_Repository.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Database\Migration;
use LEAStudios\Forms\Entry\Entry_Repository;
use LEAStudios\Forms\Entry\Entry_Status;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Entry\Entry_Repository
 */
class EntryRepositoryTest extends TestCase {

	private Entry_Repository $repo;

	public function set_up(): void {
		parent::set_up();
		$this->repo = new Entry_Repository();
	}

	public function test_create_persists_row_and_returns_insert_id(): void {
		$id = $this->repo->create( 101, [ 'name' => 'Ada' ], '203.0.113.1', 'PHPUnit', 7 );

		$this->assertGreaterThan( 0, $id );

		$row = $this->repo->get_entry( $id );
		$this->assertNotNull( $row );
		$this->assertSame( 101, (int) $row->form_id );
		$this->assertSame( '203.0.113.1', $row->ip_address );
		$this->assertSame( 7, (int) $row->user_id );
		$this->assertSame( Entry_Status::Unread->value, $row->status );
		$this->assertSame( [ 'name' => 'Ada' ], json_decode( $row->field_data, true ) );
	}

	public function test_create_truncates_user_agent_to_255_chars(): void {
		$id  = $this->repo->create( 1, [], null, str_repeat( 'x', 400 ), null );
		$row = $this->repo->get_entry( $id );

		$this->assertSame( 255, mb_strlen( $row->user_agent ) );
	}

	public function test_get_entry_returns_null_for_missing_id(): void {
		$this->assertNull( $this->repo->get_entry( 99999999 ) );
	}

	public function test_get_entries_filters_by_form_id(): void {
		$this->repo->create( 10, [], null, null, null );
		$this->repo->create( 10, [], null, null, null );
		$this->repo->create( 20, [], null, null, null );

		$this->assertCount( 2, $this->repo->get_entries( 1, 50, 10 ) );
		$this->assertCount( 1, $this->repo->get_entries( 1, 50, 20 ) );
	}

	public function test_get_entries_filters_by_status(): void {
		$read = $this->repo->create( 30, [], null, null, null );
		$this->repo->create( 30, [], null, null, null );
		$this->repo->update_status( $read, Entry_Status::Read->value );

		$this->assertCount( 1, $this->repo->get_entries( 1, 50, 30, Entry_Status::Read->value ) );
		$this->assertCount( 1, $this->repo->get_entries( 1, 50, 30, Entry_Status::Unread->value ) );
	}

	public function test_get_entries_paginates_newest_first(): void {
		global $wpdb;
		$table = Migration::get_table_name();

		$old = $this->repo->create( 40, [ 'n' => 'old' ], null, null, null );
		$mid = $this->repo->create( 40, [ 'n' => 'mid' ], null, null, null );
		$new = $this->repo->create( 40, [ 'n' => 'new' ], null, null, null );

		$wpdb->update( $table, [ 'created_at' => '2020-01-01 00:00:00' ], [ 'id' => $old ] );
		$wpdb->update( $table, [ 'created_at' => '2021-01-01 00:00:00' ], [ 'id' => $mid ] );
		$wpdb->update( $table, [ 'created_at' => '2022-01-01 00:00:00' ], [ 'id' => $new ] );

		$page1 = $this->repo->get_entries( 1, 2, 40 );
		$page2 = $this->repo->get_entries( 2, 2, 40 );

		$this->assertCount( 2, $page1 );
		$this->assertCount( 1, $page2 );
		$this->assertSame( $new, (int) $page1[0]->id );
		$this->assertSame( $mid, (int) $page1[1]->id );
		$this->assertSame( $old, (int) $page2[0]->id );
	}

	public function test_get_total_count_with_and_without_filters(): void {
		$this->repo->create( 50, [], null, null, null );
		$this->repo->create( 50, [], null, null, null );
		$this->repo->create( 60, [], null, null, null );

		$this->assertSame( 3, $this->repo->get_total_count() );
		$this->assertSame( 2, $this->repo->get_total_count( 50 ) );
		$this->assertSame( 1, $this->repo->get_total_count( 60 ) );
	}

	public function test_update_status_changes_status(): void {
		$id = $this->repo->create( 1, [], null, null, null );

		$this->assertTrue( $this->repo->update_status( $id, Entry_Status::Trashed->value ) );
		$this->assertSame( Entry_Status::Trashed->value, $this->repo->get_entry( $id )->status );
	}

	public function test_update_message_ids_stores_json(): void {
		$id = $this->repo->create( 1, [], null, null, null );

		$this->assertTrue( $this->repo->update_message_ids( $id, [ 'ses-1', 'ses-2' ] ) );
		$this->assertSame(
			[ 'ses-1', 'ses-2' ],
			json_decode( $this->repo->get_entry( $id )->notification_message_ids, true )
		);
	}

	public function test_delete_entry_removes_row(): void {
		$id = $this->repo->create( 1, [], null, null, null );

		$this->assertTrue( $this->repo->delete_entry( $id ) );
		$this->assertNull( $this->repo->get_entry( $id ) );
	}

	public function test_delete_old_entries_removes_only_aged_rows(): void {
		global $wpdb;
		$table = Migration::get_table_name();

		$old   = $this->repo->create( 70, [], null, null, null );
		$fresh = $this->repo->create( 70, [], null, null, null );

		$wpdb->update( $table, [ 'created_at' => '2000-01-01 00:00:00' ], [ 'id' => $old ] );

		$deleted = $this->repo->delete_old_entries( 30 );

		$this->assertSame( 1, $deleted );
		$this->assertNull( $this->repo->get_entry( $old ) );
		$this->assertNotNull( $this->repo->get_entry( $fresh ) );
	}

	public function test_get_entries_for_export_returns_form_rows_newest_first(): void {
		global $wpdb;
		$table = Migration::get_table_name();

		$a = $this->repo->create( 80, [ 'n' => 'a' ], null, null, null );
		$b = $this->repo->create( 80, [ 'n' => 'b' ], null, null, null );
		$this->repo->create( 90, [ 'n' => 'other' ], null, null, null );

		$wpdb->update( $table, [ 'created_at' => '2020-01-01 00:00:00' ], [ 'id' => $a ] );
		$wpdb->update( $table, [ 'created_at' => '2021-01-01 00:00:00' ], [ 'id' => $b ] );

		$rows = $this->repo->get_entries_for_export( 80 );

		$this->assertCount( 2, $rows );
		$this->assertSame( $b, (int) $rows[0]->id );
		$this->assertSame( $a, (int) $rows[1]->id );
	}
}
```

- [ ] **Step 2: Run the tests**

Run: `vendor/bin/phpunit --filter EntryRepositoryTest`
Expected: 12 tests, all passing.

- [ ] **Step 3: Lint** — `composer phpcs`, fix issues in the new file.

- [ ] **Step 4: Commit, push, open PR**

```bash
git checkout -b test-coverage-entry-repository
git add tests/EntryRepositoryTest.php
git commit -m "Add Entry_Repository test coverage"
git push -u origin test-coverage-entry-repository
gh pr create --title "Add Entry_Repository test coverage" --body "Covers create/read, user-agent truncation, form and status filtering, pagination ordering, counts, status and message-id updates, deletion, and retention cleanup."
```

---

## Task 3: Submission_Controller (REST) coverage

**Files:**
- Create: `tests/SubmissionControllerTest.php`
- Test target: `src/REST/Submission_Controller.php`

**Background:** Test the controller directly — construct `Submission_Controller` with a real `Submission_Handler` (built exactly as `SubmissionHandlerTest::set_up()` does), build `WP_REST_Request` objects, and call `create_item()` / `create_item_permissions_check()` directly. This avoids depending on the plugin's `rest_api_init` wiring. The controller reads params via `$request->get_param()` and `$request->get_body_params()`; `set_body_params()` feeds both. The honeypot is a *presence* check: an absent `_leastudios_forms_hp` key means the controller passes `null` to the handler, which treats `null` as spam. A nonce is created with `wp_create_nonce( 'leastudios_forms_submit_' . $form_id )`.

- [ ] **Step 1: Write the test file**

```php
<?php
/**
 * Tests for Submission_Controller.
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
use LEAStudios\Forms\REST\Submission_Controller;
use LEAStudios\Forms\Spam\Honeypot;
use LEAStudios\Forms\Spam\Rate_Limiter;
use LEAStudios\Forms\Submission\Submission_Handler;
use LEAStudios\Forms\Submission\Validator;
use LEAStudios\Tests\TestCase;
use WP_REST_Request;

/**
 * @covers \LEAStudios\Forms\REST\Submission_Controller
 */
class SubmissionControllerTest extends TestCase {

	private Submission_Controller $controller;

	private Form_Repository $form_repo;

	public function set_up(): void {
		parent::set_up();

		$field_registry = new Field_Registry();
		$field_registry->register_defaults();

		$this->form_repo = new Form_Repository();

		$handler = new Submission_Handler(
			new Validator( $field_registry ),
			new Entry_Repository(),
			new Email_Notifier(),
			new Honeypot(),
			new Rate_Limiter(),
			$this->form_repo,
			$field_registry
		);

		$this->controller = new Submission_Controller( $handler );
	}

	/**
	 * Create a leastudios_form post with the given fields.
	 *
	 * @param array<int, array<string, mixed>> $fields Field configurations.
	 * @return int The new form post ID.
	 */
	private function create_form( array $fields ): int {
		$form_id = self::factory()->post->create(
			[
				'post_type'  => 'leastudios_form',
				'post_title' => 'REST Test Form',
			]
		);

		$this->form_repo->save_fields( $form_id, $fields );
		$this->form_repo->save_settings( $form_id, new Form_Settings() );

		return $form_id;
	}

	/**
	 * Build a POST request for the submissions endpoint.
	 *
	 * @param array<string, mixed> $params Body params.
	 * @return WP_REST_Request
	 */
	private function make_request( array $params ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/leastudios-forms/v1/submissions' );
		$request->set_body_params( $params );

		return $request;
	}

	public function test_permissions_check_is_public(): void {
		$this->assertTrue(
			$this->controller->create_item_permissions_check( $this->make_request( [] ) )
		);
	}

	public function test_rejects_missing_nonce_with_403(): void {
		$form_id = $this->create_form( [ [ 'name' => 'message', 'type' => 'text' ] ] );

		$response = $this->controller->create_item(
			$this->make_request(
				[
					'form_id'              => $form_id,
					'fields'               => [ 'message' => 'Hi' ],
					'_leastudios_forms_hp' => '',
				]
			)
		);

		$this->assertSame( 403, $response->get_status() );
		$this->assertFalse( $response->get_data()['success'] );
	}

	public function test_rejects_invalid_nonce_with_403(): void {
		$form_id = $this->create_form( [ [ 'name' => 'message', 'type' => 'text' ] ] );

		$response = $this->controller->create_item(
			$this->make_request(
				[
					'form_id'              => $form_id,
					'fields'               => [ 'message' => 'Hi' ],
					'_leastudios_forms_hp' => '',
					'_wpnonce'             => 'not-a-real-nonce',
				]
			)
		);

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_returns_200_on_successful_submission(): void {
		$form_id = $this->create_form( [ [ 'name' => 'message', 'type' => 'text' ] ] );

		$response = $this->controller->create_item(
			$this->make_request(
				[
					'form_id'              => $form_id,
					'fields'               => [ 'message' => 'Hello' ],
					'_leastudios_forms_hp' => '',
					'_wpnonce'             => wp_create_nonce( 'leastudios_forms_submit_' . $form_id ),
				]
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
	}

	public function test_returns_422_on_validation_failure(): void {
		$form_id = $this->create_form(
			[ [ 'name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true ] ]
		);

		$response = $this->controller->create_item(
			$this->make_request(
				[
					'form_id'              => $form_id,
					'fields'               => [ 'email' => '' ],
					'_leastudios_forms_hp' => '',
					'_wpnonce'             => wp_create_nonce( 'leastudios_forms_submit_' . $form_id ),
				]
			)
		);

		$this->assertSame( 422, $response->get_status() );
		$this->assertArrayHasKey( 'email', $response->get_data()['errors'] );
	}

	public function test_absent_honeypot_field_is_treated_as_spam(): void {
		$form_id = $this->create_form( [ [ 'name' => 'message', 'type' => 'text' ] ] );

		// Note: no _leastudios_forms_hp key in the body at all.
		$response = $this->controller->create_item(
			$this->make_request(
				[
					'form_id'  => $form_id,
					'fields'   => [ 'message' => 'Hello' ],
					'_wpnonce' => wp_create_nonce( 'leastudios_forms_submit_' . $form_id ),
				]
			)
		);

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'Spam detected.', $response->get_data()['message'] );
	}
}
```

> Note the deliberate honeypot test: the success and validation tests *include* `_leastudios_forms_hp => ''` (a present-but-empty field, i.e. a real browser), while `test_absent_honeypot_field_is_treated_as_spam` omits the key entirely.

- [ ] **Step 2: Run the tests**

Run: `vendor/bin/phpunit --filter SubmissionControllerTest`
Expected: 6 tests, all passing.

- [ ] **Step 3: Lint** — `composer phpcs`, fix issues.

- [ ] **Step 4: Commit, push, open PR**

```bash
git checkout -b test-coverage-rest-controller
git add tests/SubmissionControllerTest.php
git commit -m "Add Submission_Controller REST endpoint test coverage"
git push -u origin test-coverage-rest-controller
gh pr create --title "Add Submission_Controller REST endpoint test coverage" --body "Covers the public permission callback, nonce rejection (403), success (200), validation failure (422), and absent-honeypot spam detection."
```

---

## Task 4: Form storage coverage (Form_Repository + Form_Settings)

**Files:**
- Create: `tests/FormRepositoryTest.php`
- Create: `tests/FormSettingsTest.php`
- Test targets: `src/Form/Form_Repository.php`, `src/Form/Form_Settings.php`

**Background:** `Form_Repository` reads/writes the `leastudios_form` CPT and two JSON post-meta blobs. `Form_Settings` is an immutable value object with `from_json()` / `to_array()`; note `from_json` nests rate-limit settings under a `spam_protection` key and drops notification rows that have no `to` address.

- [ ] **Step 1: Write `tests/FormRepositoryTest.php`**

```php
<?php
/**
 * Tests for Form_Repository.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\CPT\Form_Post_Type;
use LEAStudios\Forms\Form\Form_Repository;
use LEAStudios\Forms\Form\Form_Settings;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Form\Form_Repository
 */
class FormRepositoryTest extends TestCase {

	private Form_Repository $repo;

	public function set_up(): void {
		parent::set_up();
		$this->repo = new Form_Repository();
	}

	private function make_form(): int {
		return self::factory()->post->create(
			[
				'post_type'  => 'leastudios_form',
				'post_title' => 'A Form',
			]
		);
	}

	public function test_get_form_returns_post_for_form_cpt(): void {
		$id   = $this->make_form();
		$form = $this->repo->get_form( $id );

		$this->assertNotNull( $form );
		$this->assertSame( $id, $form->ID );
	}

	public function test_get_form_returns_null_for_non_form_post(): void {
		$post_id = self::factory()->post->create( [ 'post_type' => 'post' ] );

		$this->assertNull( $this->repo->get_form( $post_id ) );
	}

	public function test_get_form_returns_null_for_missing_id(): void {
		$this->assertNull( $this->repo->get_form( 99999999 ) );
	}

	public function test_save_and_get_fields_round_trip(): void {
		$id     = $this->make_form();
		$fields = [
			[ 'id' => 'name', 'type' => 'text' ],
			[ 'id' => 'email', 'type' => 'email' ],
		];

		$this->assertTrue( $this->repo->save_fields( $id, $fields ) );
		$this->assertSame( $fields, $this->repo->get_fields( $id ) );
	}

	public function test_get_fields_returns_empty_array_when_unset(): void {
		$this->assertSame( [], $this->repo->get_fields( $this->make_form() ) );
	}

	public function test_get_fields_returns_empty_array_for_non_json_meta(): void {
		$id = $this->make_form();
		update_post_meta( $id, Form_Post_Type::FIELDS_META_KEY, 'not-json' );

		$this->assertSame( [], $this->repo->get_fields( $id ) );
	}

	public function test_save_and_get_settings_round_trip(): void {
		$id       = $this->make_form();
		$settings = new Form_Settings( success_message: 'Done', rate_limit: 9 );

		$this->assertTrue( $this->repo->save_settings( $id, $settings ) );

		$loaded = $this->repo->get_settings( $id );
		$this->assertSame( 'Done', $loaded->success_message );
		$this->assertSame( 9, $loaded->rate_limit );
	}

	public function test_get_settings_returns_defaults_when_unset(): void {
		$loaded = $this->repo->get_settings( $this->make_form() );

		$this->assertSame( 'Thank you for your submission.', $loaded->success_message );
		$this->assertSame( 5, $loaded->rate_limit );
	}

	public function test_get_all_forms_returns_forms_sorted_by_title(): void {
		self::factory()->post->create( [ 'post_type' => 'leastudios_form', 'post_title' => 'Zebra', 'post_status' => 'publish' ] );
		self::factory()->post->create( [ 'post_type' => 'leastudios_form', 'post_title' => 'Apple', 'post_status' => 'draft' ] );

		$forms  = $this->repo->get_all_forms();
		$titles = wp_list_pluck( $forms, 'post_title' );

		$this->assertContains( 'Apple', $titles );
		$this->assertContains( 'Zebra', $titles );
		$this->assertLessThan(
			array_search( 'Zebra', $titles, true ),
			array_search( 'Apple', $titles, true ),
			'Forms should be ordered by title ascending.'
		);
	}
}
```

- [ ] **Step 2: Run** — `vendor/bin/phpunit --filter FormRepositoryTest` — expect 9 passing.

- [ ] **Step 3: Write `tests/FormSettingsTest.php`**

```php
<?php
/**
 * Tests for Form_Settings.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Form\Form_Settings;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Form\Form_Settings
 */
class FormSettingsTest extends TestCase {

	public function test_defaults(): void {
		$settings = new Form_Settings();

		$this->assertSame( 'Thank you for your submission.', $settings->success_message );
		$this->assertSame( '', $settings->redirect_url );
		$this->assertSame( [], $settings->notifications );
		$this->assertTrue( $settings->honeypot_enabled );
		$this->assertSame( 5, $settings->rate_limit );
		$this->assertSame( 60, $settings->rate_limit_window );
		$this->assertSame( 'Submit', $settings->submit_button_text );
	}

	public function test_from_json_returns_defaults_for_invalid_json(): void {
		$settings = Form_Settings::from_json( 'not-json' );

		$this->assertSame( 'Thank you for your submission.', $settings->success_message );
		$this->assertSame( 5, $settings->rate_limit );
	}

	public function test_from_json_parses_all_fields(): void {
		$json = wp_json_encode(
			[
				'success_message'    => 'Cheers',
				'redirect_url'       => 'https://example.com/thanks',
				'submit_button_text' => 'Send',
				'spam_protection'    => [
					'honeypot'          => false,
					'rate_limit'        => 12,
					'rate_limit_window' => 120,
				],
			]
		);

		$settings = Form_Settings::from_json( $json );

		$this->assertSame( 'Cheers', $settings->success_message );
		$this->assertSame( 'https://example.com/thanks', $settings->redirect_url );
		$this->assertSame( 'Send', $settings->submit_button_text );
		$this->assertFalse( $settings->honeypot_enabled );
		$this->assertSame( 12, $settings->rate_limit );
		$this->assertSame( 120, $settings->rate_limit_window );
	}

	public function test_from_json_drops_notifications_without_a_to_address(): void {
		$json = wp_json_encode(
			[
				'notifications' => [
					[ 'to' => 'admin@example.com', 'subject' => 'New', 'message' => 'Body', 'reply_to' => '' ],
					[ 'to' => '', 'subject' => 'Skip me', 'message' => '', 'reply_to' => '' ],
				],
			]
		);

		$settings = Form_Settings::from_json( $json );

		$this->assertCount( 1, $settings->notifications );
		$this->assertSame( 'admin@example.com', $settings->notifications[0]['to'] );
	}

	public function test_to_array_nests_spam_protection(): void {
		$array = ( new Form_Settings( rate_limit: 8, rate_limit_window: 90, honeypot_enabled: false ) )->to_array();

		$this->assertSame( 8, $array['spam_protection']['rate_limit'] );
		$this->assertSame( 90, $array['spam_protection']['rate_limit_window'] );
		$this->assertFalse( $array['spam_protection']['honeypot'] );
	}

	public function test_to_array_round_trips_through_from_json(): void {
		$original = new Form_Settings(
			success_message: 'Round trip',
			rate_limit: 7,
			submit_button_text: 'Go',
		);

		$restored = Form_Settings::from_json( (string) wp_json_encode( $original->to_array() ) );

		$this->assertSame( 'Round trip', $restored->success_message );
		$this->assertSame( 7, $restored->rate_limit );
		$this->assertSame( 'Go', $restored->submit_button_text );
	}
}
```

- [ ] **Step 4: Run** — `vendor/bin/phpunit --filter FormSettingsTest` — expect 6 passing. Then run both: `vendor/bin/phpunit --filter 'FormRepositoryTest|FormSettingsTest'`.

- [ ] **Step 5: Lint** — `composer phpcs`, fix issues.

- [ ] **Step 6: Commit, push, open PR**

```bash
git checkout -b test-coverage-form-storage
git add tests/FormRepositoryTest.php tests/FormSettingsTest.php
git commit -m "Add Form_Repository and Form_Settings test coverage"
git push -u origin test-coverage-form-storage
gh pr create --title "Add Form_Repository and Form_Settings test coverage" --body "Covers CPT lookup guarding, fields/settings JSON round-trips, malformed-meta fallbacks, and Form_Settings parsing/serialization including notification filtering."
```

---

## Task 5: Field type coverage (all 11 types)

**Files:**
- Create 11 files in `tests/`: `TextFieldTest.php`, `EmailFieldTest.php`, `TextareaFieldTest.php`, `SelectFieldTest.php`, `CheckboxFieldTest.php`, `RadioFieldTest.php`, `HiddenFieldTest.php`, `NumberFieldTest.php`, `PhoneFieldTest.php`, `UrlFieldTest.php`, `AddressFieldTest.php`
- Test targets: the 11 classes in `src/Field/Types/`

**Background:** Every field type implements `Field_Type` (`get_type`, `get_label`, `sanitize`, `validate`, `render`). All field types are instantiated with no constructor args (e.g. `new Email_Field()`). `validate()` returns `true` on success or an error *string*. `render()` returns an HTML string (it does not echo). Each test class covers exactly one type. The structure is uniform — the reference files below are complete; the spec table that follows gives the exact per-type variations for the remaining files.

### 5a. Reference file — `tests/EmailFieldTest.php` (complete)

```php
<?php
/**
 * Tests for Email_Field.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Field\Types\Email_Field;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Field\Types\Email_Field
 */
class EmailFieldTest extends TestCase {

	private Email_Field $field;

	public function set_up(): void {
		parent::set_up();
		$this->field = new Email_Field();
	}

	public function test_get_type(): void {
		$this->assertSame( 'email', $this->field->get_type() );
	}

	public function test_get_label_is_not_empty(): void {
		$this->assertNotEmpty( $this->field->get_label() );
	}

	public function test_sanitize_strips_invalid_email_characters(): void {
		$this->assertSame( 'user@example.com', $this->field->sanitize( 'user@exa mple.com' ) );
	}

	public function test_validate_passes_for_valid_email(): void {
		$this->assertTrue( $this->field->validate( 'user@example.com', [] ) );
	}

	public function test_validate_rejects_malformed_email(): void {
		$this->assertIsString( $this->field->validate( 'not-an-email', [] ) );
	}

	public function test_validate_required_rejects_empty(): void {
		$this->assertIsString(
			$this->field->validate( '', [ 'required' => true, 'label' => 'Email' ] )
		);
	}

	public function test_validate_optional_allows_empty(): void {
		$this->assertTrue( $this->field->validate( '', [] ) );
	}

	public function test_render_outputs_email_input(): void {
		$html = $this->field->render( [ 'id' => 'e1', 'name' => 'email', 'label' => 'Email' ] );

		$this->assertStringContainsString( 'type="email"', $html );
		$this->assertStringContainsString( 'name="email"', $html );
	}
}
```

### 5b. Reference file — `tests/NumberFieldTest.php` (complete)

`Number_Field::sanitize()` has real logic: empty stays `''`, non-numeric strings pass through unchanged, whole numbers become `int`, fractional become `float`.

```php
<?php
/**
 * Tests for Number_Field.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Field\Types\Number_Field;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Field\Types\Number_Field
 */
class NumberFieldTest extends TestCase {

	private Number_Field $field;

	public function set_up(): void {
		parent::set_up();
		$this->field = new Number_Field();
	}

	public function test_get_type(): void {
		$this->assertSame( 'number', $this->field->get_type() );
	}

	public function test_get_label_is_not_empty(): void {
		$this->assertNotEmpty( $this->field->get_label() );
	}

	public function test_sanitize_keeps_empty_string_empty(): void {
		$this->assertSame( '', $this->field->sanitize( '' ) );
	}

	public function test_sanitize_normalizes_whole_number_to_int(): void {
		$this->assertSame( 42, $this->field->sanitize( '42' ) );
	}

	public function test_sanitize_normalizes_fraction_to_float(): void {
		$this->assertSame( 3.5, $this->field->sanitize( '3.5' ) );
	}

	public function test_sanitize_passes_non_numeric_through(): void {
		$this->assertSame( 'abc', $this->field->sanitize( 'abc' ) );
	}

	public function test_validate_rejects_non_numeric(): void {
		$this->assertIsString( $this->field->validate( 'abc', [] ) );
	}

	public function test_validate_passes_for_numeric(): void {
		$this->assertTrue( $this->field->validate( 10, [] ) );
	}

	public function test_validate_required_rejects_empty(): void {
		$this->assertIsString( $this->field->validate( '', [ 'required' => true ] ) );
	}

	public function test_render_outputs_number_input(): void {
		$html = $this->field->render( [ 'id' => 'n1', 'name' => 'qty', 'label' => 'Qty' ] );
		$this->assertStringContainsString( 'type="number"', $html );
	}
}
```

### 5c. Reference file — `tests/AddressFieldTest.php` (complete)

`Address_Field` sanitizes to a fixed 6-key array and, when required, validates that `line1/city/state/zip/country` are all non-empty (`line2` is optional).

```php
<?php
/**
 * Tests for Address_Field.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Field\Types\Address_Field;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Field\Types\Address_Field
 */
class AddressFieldTest extends TestCase {

	private Address_Field $field;

	public function set_up(): void {
		parent::set_up();
		$this->field = new Address_Field();
	}

	public function test_get_type(): void {
		$this->assertSame( 'address', $this->field->get_type() );
	}

	public function test_get_label_is_not_empty(): void {
		$this->assertNotEmpty( $this->field->get_label() );
	}

	public function test_sanitize_returns_six_key_array_for_non_array_input(): void {
		$result = $this->field->sanitize( 'garbage' );

		$this->assertSame(
			[ 'line1', 'line2', 'city', 'state', 'zip', 'country' ],
			array_keys( $result )
		);
	}

	public function test_sanitize_cleans_known_keys(): void {
		$result = $this->field->sanitize(
			[ 'line1' => '10 Main St', 'city' => 'Springfield', 'extra' => 'dropped' ]
		);

		$this->assertSame( '10 Main St', $result['line1'] );
		$this->assertSame( 'Springfield', $result['city'] );
		$this->assertArrayNotHasKey( 'extra', $result );
	}

	public function test_validate_optional_always_passes(): void {
		$this->assertTrue( $this->field->validate( 'anything', [] ) );
	}

	public function test_validate_required_rejects_incomplete_address(): void {
		$value = [ 'line1' => '10 Main St', 'city' => '', 'state' => 'CA', 'zip' => '90210', 'country' => 'US' ];

		$this->assertIsString( $this->field->validate( $value, [ 'required' => true ] ) );
	}

	public function test_validate_required_passes_for_complete_address(): void {
		$value = [ 'line1' => '10 Main St', 'city' => 'LA', 'state' => 'CA', 'zip' => '90210', 'country' => 'US' ];

		$this->assertTrue( $this->field->validate( $value, [ 'required' => true ] ) );
	}

	public function test_render_outputs_address_fieldset(): void {
		$html = $this->field->render( [ 'id' => 'a1', 'name' => 'addr', 'label' => 'Address' ] );

		$this->assertStringContainsString( 'name="addr[line1]"', $html );
		$this->assertStringContainsString( 'name="addr[country]"', $html );
	}
}
```

### 5d. Remaining 8 files — spec table

Each file follows the **exact structure of `EmailFieldTest`**: `declare(strict_types=1)`, namespace `LEAStudios\Forms\Tests`, `@covers` the class, a typed property + `set_up()` instantiating the field, then the test methods. Build each file from the reference template, substituting per this table. Every file includes `test_get_type`, `test_get_label_is_not_empty`, and a `test_render_outputs_*` that asserts the rendered HTML contains the expected `type="..."` (or structural marker), plus the rows below.

| File / class | `get_type()` | `sanitize` test | `validate` tests | `render` asserts contains |
|---|---|---|---|---|
| `TextFieldTest` / `Text_Field` | `text` | `sanitize( ' hi ' )` trims to `hi` | required-empty → string; optional-empty → `true`; with `validation.pattern` `^\d+$`, value `abc` → string, value `123` → `true` | `type="text"` |
| `TextareaFieldTest` / `Textarea_Field` | `textarea` | `sanitize` of multi-line input keeps newlines (use `sanitize_textarea_field` expectation) | required-empty → string; optional-empty → `true` | `<textarea` |
| `HiddenFieldTest` / `Hidden_Field` | `hidden` | `sanitize( '<b>x</b>' )` strips tags | required-empty → string; optional-empty → `true` | `type="hidden"` |
| `PhoneFieldTest` / `Phone_Field` | `phone` | `sanitize` of `'  555-1234 '` trims | required-empty → string; with `validation.pattern` `^\+?[0-9]+$`, value `12a` → string, value `1234` → `true` | `type="tel"` |
| `UrlFieldTest` / `Url_Field` | `url` | `sanitize( 'https://example.com' )` keeps the URL | required-empty → string; optional-empty → `true`; value `not a url` → string; value `https://example.com` → `true` | `type="url"` |
| `SelectFieldTest` / `Select_Field` | `select` | `sanitize` trims text | required-empty → string; value not in `options` → string; value in `options` → `true`. Use `field_config` `[ 'options' => [ [ 'value' => 'a', 'label' => 'A' ] ] ]` | `<select` and the option value |
| `RadioFieldTest` / `Radio_Field` | `radio` | `sanitize` trims text | same as Select: invalid option → string, valid option → `true` | `type="radio"` |
| `CheckboxFieldTest` / `Checkbox_Field` | `checkbox` | `sanitize( [ 'a', 'b' ] )` returns an array; `sanitize( 'a' )` returns a string | required with empty array → string; required with non-empty valid array → `true`; array containing a value not in `options` → string | `type="checkbox"` and `name="...[]"` |

For the `validate` option tests, the field config shape is `[ 'options' => [ [ 'value' => 'red', 'label' => 'Red' ], [ 'value' => 'blue', 'label' => 'Blue' ] ] ]`.

- [ ] **Step 1: Write the three reference files** — `EmailFieldTest.php`, `NumberFieldTest.php`, `AddressFieldTest.php` (code above).

- [ ] **Step 2: Run them** — `vendor/bin/phpunit --filter 'EmailFieldTest|NumberFieldTest|AddressFieldTest'` — expect all passing.

- [ ] **Step 3: Write the 8 remaining files** from the spec table, each modeled on `EmailFieldTest`.

- [ ] **Step 4: Run all field type tests**

Run: `vendor/bin/phpunit --filter 'FieldTest'`
Expected: ~40 tests across 11 classes, all passing. Investigate any failure (test error vs. real bug) before proceeding.

- [ ] **Step 5: Lint** — `composer phpcs`, fix all issues across the 11 new files.

- [ ] **Step 6: Commit, push, open PR**

```bash
git checkout -b test-coverage-field-types
git add tests/*FieldTest.php
git commit -m "Add test coverage for all 11 field types"
git push -u origin test-coverage-field-types
gh pr create --title "Add test coverage for all 11 field types" --body "One test class per Field_Type implementation covering get_type, sanitize, validate (required, type-specific, and option/pattern rules), and render output."
```

---

## Task 6: Spam guard coverage (Honeypot + Rate_Limiter)

**Files:**
- Create: `tests/HoneypotTest.php`
- Create: `tests/RateLimiterTest.php`
- Test targets: `src/Spam/Honeypot.php`, `src/Spam/Rate_Limiter.php`

**Background:** `Honeypot::is_spam()` treats `null` (field absent) and any non-empty string as spam; an empty string is a legitimate human. `Rate_Limiter::check()` stores a per-IP, per-form counter in a WordPress transient keyed by `sha256(ip)` and the form ID, returning `false` once `max_submissions` is reached.

- [ ] **Step 1: Write `tests/HoneypotTest.php`**

```php
<?php
/**
 * Tests for Honeypot.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Spam\Honeypot;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Spam\Honeypot
 */
class HoneypotTest extends TestCase {

	private Honeypot $honeypot;

	public function set_up(): void {
		parent::set_up();
		$this->honeypot = new Honeypot();
	}

	public function test_is_spam_when_field_is_absent(): void {
		$this->assertTrue( $this->honeypot->is_spam( null ) );
	}

	public function test_is_spam_when_field_has_a_value(): void {
		$this->assertTrue( $this->honeypot->is_spam( 'i am a bot' ) );
	}

	public function test_not_spam_when_field_is_empty_string(): void {
		$this->assertFalse( $this->honeypot->is_spam( '' ) );
	}

	public function test_render_outputs_hidden_input(): void {
		$html = $this->honeypot->render();

		$this->assertStringContainsString( 'name="_leastudios_forms_hp"', $html );
		$this->assertStringContainsString( 'tabindex="-1"', $html );
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
	}
}
```

- [ ] **Step 2: Write `tests/RateLimiterTest.php`**

```php
<?php
/**
 * Tests for Rate_Limiter.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Spam\Rate_Limiter;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Spam\Rate_Limiter
 */
class RateLimiterTest extends TestCase {

	private Rate_Limiter $limiter;

	public function set_up(): void {
		parent::set_up();
		$this->limiter = new Rate_Limiter();
	}

	public function test_allows_submissions_under_the_limit(): void {
		$this->assertTrue( $this->limiter->check( '203.0.113.1', 1, 3, 60 ) );
		$this->assertTrue( $this->limiter->check( '203.0.113.1', 1, 3, 60 ) );
	}

	public function test_blocks_once_the_limit_is_reached(): void {
		$this->limiter->check( '203.0.113.2', 1, 2, 60 );
		$this->limiter->check( '203.0.113.2', 1, 2, 60 );

		$this->assertFalse( $this->limiter->check( '203.0.113.2', 1, 2, 60 ) );
	}

	public function test_counter_is_isolated_per_ip(): void {
		$this->limiter->check( '203.0.113.3', 1, 1, 60 );

		$this->assertFalse( $this->limiter->check( '203.0.113.3', 1, 1, 60 ) );
		$this->assertTrue( $this->limiter->check( '203.0.113.4', 1, 1, 60 ) );
	}

	public function test_counter_is_isolated_per_form(): void {
		$this->limiter->check( '203.0.113.5', 1, 1, 60 );

		$this->assertFalse( $this->limiter->check( '203.0.113.5', 1, 1, 60 ) );
		$this->assertTrue( $this->limiter->check( '203.0.113.5', 2, 1, 60 ) );
	}
}
```

- [ ] **Step 3: Run** — `vendor/bin/phpunit --filter 'HoneypotTest|RateLimiterTest'` — expect 8 passing.

- [ ] **Step 4: Lint** — `composer phpcs`, fix issues.

- [ ] **Step 5: Commit, push, open PR**

```bash
git checkout -b test-coverage-spam-guards
git add tests/HoneypotTest.php tests/RateLimiterTest.php
git commit -m "Add Honeypot and Rate_Limiter test coverage"
git push -u origin test-coverage-spam-guards
gh pr create --title "Add Honeypot and Rate_Limiter test coverage" --body "Covers the honeypot presence-check (absent/filled/empty) and rendered markup, plus rate-limiter under/at-limit behavior and per-IP / per-form isolation."
```

---

## Final verification (after all six PRs merge)

- [ ] On `main` with all PRs merged, run the full suite: `composer test` — expect ~104 tests, all passing.
- [ ] Run `composer lint` — PHPCS and PHPStan clean.
- [ ] Confirm CI is green on `main`.

## Self-review notes

- **Spec coverage:** all six areas the user listed have a task — Migration (1), Entry_Repository (2), Submission_Controller/REST (3), Form_Repository (4), field types (5), Honeypot/Rate_Limiter (6). `Form_Settings` is covered alongside `Form_Repository` in task 4 because the repository hydrates it.
- **Out of scope (deliberately):** `Email_Notifier`, the `Admin/*` UI classes, `Render/*`, `Suite_Detector`, and `Mailer_Integration` are not in the six requested areas. `Rate_Limiter` window-expiry is not tested — transient TTL is WordPress behavior, not plugin logic.
- **Known risk:** Task 1 test 4 and the `delete_old_entries` setup mutate the shared entries table via DDL / direct updates; the transaction rollback does not undo DDL. Mitigations are built in (dbDelta no-op on an identical table; the migration self-drops the columns it adds; `MigrationTest::tear_down()` defensively drops leftovers).

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
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			"ALTER TABLE {$table}
				ADD COLUMN payment_status varchar(20) DEFAULT NULL,
				ADD COLUMN payment_amount bigint(20) DEFAULT NULL,
				ADD COLUMN payment_currency varchar(3) DEFAULT NULL,
				ADD COLUMN stripe_payment_id varchar(255) DEFAULT NULL"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		update_option(
			'leastudios_forms_options',
			[
				'entry_retention_days'    => 90,
				'stripe_test_mode'        => true,
				'stripe_publishable_key'  => 'pk_test_xxx',
				'stripe_secret_key'       => 'sk_test_xxx',
				'stripe_webhook_secret'   => 'whsec_xxx',
				'stripe_default_currency' => 'usd',
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

		foreach ( [ 'stripe_test_mode', 'stripe_publishable_key', 'stripe_secret_key', 'stripe_webhook_secret', 'stripe_default_currency' ] as $stripe_key ) {
			$this->assertArrayNotHasKey( $stripe_key, $options, "Stripe key {$stripe_key} should have been removed." );
		}

		$this->assertSame( 90, $options['entry_retention_days'] );
	}
}

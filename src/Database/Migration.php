<?php
/**
 * Database migration handler.
 *
 * @package LEAStudios\Forms\Database
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Database;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Handles entries table creation and migration.
 */
class Migration {

	/**
	 * Schema version option key.
	 */
	private const SCHEMA_VERSION_KEY = 'leastudios_forms_schema_version';

	/**
	 * Target schema version.
	 */
	private const SCHEMA_VERSION = 3;

	/**
	 * Get the entries table name.
	 *
	 * @return string Full table name with prefix.
	 */
	public static function get_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'leastudios_forms_entries';
	}

	/**
	 * Run migrations if needed.
	 *
	 * @return void
	 */
	public function maybe_migrate(): void {
		$current = (int) get_option( self::SCHEMA_VERSION_KEY, 0 );

		if ( $current >= self::SCHEMA_VERSION ) {
			return;
		}

		$this->migrate( $current );
		update_option( self::SCHEMA_VERSION_KEY, self::SCHEMA_VERSION );
	}

	/**
	 * Run the migration sequence.
	 *
	 * @param int $from_version Current schema version.
	 * @return void
	 */
	private function migrate( int $from_version ): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		if ( $from_version < 1 ) {
			$this->create_entries_table( $wpdb );
		}

		// Version 2 previously added payment columns. Version 3 removes them.
		if ( $from_version >= 2 && $from_version < 3 ) {
			$this->remove_payment_columns( $wpdb );
			$this->cleanup_stripe_options();
		}
	}

	/**
	 * Create the entries table.
	 *
	 * @param \wpdb $wpdb WordPress database abstraction.
	 * @return void
	 */
	private function create_entries_table( \wpdb $wpdb ): void {
		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = self::get_table_name();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_id bigint(20) unsigned NOT NULL,
			field_data longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'unread',
			ip_address varchar(45) DEFAULT NULL,
			user_agent varchar(255) DEFAULT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			notification_message_ids text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY form_id (form_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Remove payment-related columns from the entries table.
	 *
	 * @param \wpdb $wpdb WordPress database abstraction.
	 * @return void
	 */
	private function remove_payment_columns( \wpdb $wpdb ): void {
		$table_name = self::get_table_name();

		$columns = [ 'payment_status', 'payment_amount', 'payment_currency', 'stripe_payment_id' ];

		foreach ( $columns as $column ) {
			// Check if the column exists before attempting to drop it.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$column_exists = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table_name, $column ) );

			if ( ! empty( $column_exists ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP COLUMN %i', $table_name, $column ) );
			}
		}
	}

	/**
	 * Remove Stripe-related keys from the plugin options.
	 *
	 * @return void
	 */
	private function cleanup_stripe_options(): void {
		$options = get_option( 'leastudios_forms_options', [] );

		if ( ! is_array( $options ) ) {
			return;
		}

		$stripe_keys = [
			'stripe_test_mode',
			'stripe_publishable_key',
			'stripe_secret_key',
			'stripe_webhook_secret',
			'stripe_default_currency',
		];

		$changed = false;

		foreach ( $stripe_keys as $key ) {
			if ( array_key_exists( $key, $options ) ) {
				unset( $options[ $key ] );
				$changed = true;
			}
		}

		if ( $changed ) {
			update_option( 'leastudios_forms_options', $options );
		}
	}

	/**
	 * Drop all plugin tables. Use on uninstall only.
	 *
	 * @return void
	 */
	public static function drop_tables(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::get_table_name() ) );

		delete_option( self::SCHEMA_VERSION_KEY );
	}
}

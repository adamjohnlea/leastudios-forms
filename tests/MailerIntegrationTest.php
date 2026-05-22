<?php
/**
 * Tests for Mailer_Integration.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Integration\Mailer_Integration;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Integration\Mailer_Integration
 */
class MailerIntegrationTest extends TestCase {

	private Mailer_Integration $integration;

	private string $log_table;

	public function set_up(): void {
		parent::set_up();

		global $wpdb;
		$this->log_table = $wpdb->prefix . 'leastudios_mailer_log';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$this->log_table}" );
		$wpdb->query(
			"CREATE TABLE {$this->log_table} (
				message_id VARCHAR(255) NOT NULL,
				status VARCHAR(50) NOT NULL,
				error_message TEXT NULL
			)"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$this->integration = new Mailer_Integration();
	}

	public function tear_down(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$this->log_table}" );

		unset( $GLOBALS['current_screen'] );

		parent::tear_down();
	}

	public function test_init_does_nothing_outside_admin(): void {
		unset( $GLOBALS['current_screen'] );
		$fired = false;
		add_action(
			'leastudios_forms_mailer_integration_init',
			static function () use ( &$fired ): void {
				$fired = true;
			}
		);

		$this->integration->init();

		$this->assertFalse( $fired );
	}

	public function test_init_fires_action_in_admin_with_self_as_argument(): void {
		set_current_screen( 'dashboard' );
		$received = null;
		add_action(
			'leastudios_forms_mailer_integration_init',
			static function ( $instance ) use ( &$received ): void {
				$received = $instance;
			}
		);

		$this->integration->init();

		$this->assertSame( $this->integration, $received );
	}

	public function test_get_delivery_statuses_returns_empty_for_empty_input(): void {
		$this->assertSame( [], $this->integration->get_delivery_statuses( [] ) );
	}

	public function test_get_delivery_statuses_returns_normalized_rows(): void {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			$this->log_table,
			[
				'message_id'    => 'ses-aaa',
				'status'        => 'delivered',
				'error_message' => '',
			]
		);
		$wpdb->insert(
			$this->log_table,
			[
				'message_id'    => 'ses-bbb',
				'status'        => 'failed',
				'error_message' => 'recipient invalid',
			]
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$rows = $this->integration->get_delivery_statuses( [ 'ses-aaa', 'ses-bbb' ] );

		$this->assertCount( 2, $rows );

		$by_id = [];
		foreach ( $rows as $row ) {
			$by_id[ $row['message_id'] ] = $row;
		}

		$this->assertSame( 'delivered', $by_id['ses-aaa']['status'] );
		$this->assertSame( '', $by_id['ses-aaa']['error_message'] );
		$this->assertSame( 'failed', $by_id['ses-bbb']['status'] );
		$this->assertSame( 'recipient invalid', $by_id['ses-bbb']['error_message'] );
	}

	public function test_get_delivery_statuses_filters_by_supplied_message_ids(): void {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			$this->log_table,
			[
				'message_id'    => 'ses-known',
				'status'        => 'sent',
				'error_message' => '',
			]
		);
		$wpdb->insert(
			$this->log_table,
			[
				'message_id'    => 'ses-other',
				'status'        => 'delivered',
				'error_message' => '',
			]
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$rows = $this->integration->get_delivery_statuses( [ 'ses-known', 'ses-missing' ] );

		$this->assertCount( 1, $rows );
		$this->assertSame( 'ses-known', $rows[0]['message_id'] );
	}

	public function test_render_status_badge_uses_status_specific_colour(): void {
		$html = Mailer_Integration::render_status_badge( 'delivered' );

		$this->assertStringContainsString( '#00a32a', $html );
		$this->assertStringContainsString( '>Delivered<', $html );
	}

	public function test_render_status_badge_falls_back_to_grey_for_unknown_status(): void {
		$html = Mailer_Integration::render_status_badge( 'mystery' );

		$this->assertStringContainsString( '#888888', $html );
		$this->assertStringContainsString( '>Mystery<', $html );
	}

	public function test_render_status_badge_escapes_the_status_label(): void {
		$html = Mailer_Integration::render_status_badge( '<script>x</script>' );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}
}

<?php
/**
 * Integration with the leaStudios Mailer plugin.
 *
 * @package LEAStudios\Forms\Integration
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Integration;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Provides delivery status lookups and display for the leaStudios Mailer plugin.
 */
class Mailer_Integration {

	/**
	 * Status badge colour map.
	 *
	 * @var array<string, string>
	 */
	private const STATUS_COLOURS = [
		'sent'       => '#2271b1',
		'delivered'  => '#00a32a',
		'failed'     => '#d63638',
		'bounced'    => '#d63638',
		'complained' => '#dba617',
	];

	/**
	 * Initialise hooks (admin only).
	 *
	 * @return void
	 */
	public function init(): void {
		if ( ! is_admin() ) {
			return;
		}

		/**
		 * Fires when the mailer integration is initialised.
		 *
		 * @param Mailer_Integration $integration The integration instance.
		 */
		do_action( 'leastudios_forms_mailer_integration_init', $this );
	}

	/**
	 * Get delivery statuses for a set of SES message IDs.
	 *
	 * @param array<int, string> $message_ids List of SES message IDs to look up.
	 * @return array<int, array{message_id: string, status: string, error_message: string}> Status rows from the mailer log.
	 */
	public function get_delivery_statuses( array $message_ids ): array {
		if ( empty( $message_ids ) ) {
			return [];
		}

		global $wpdb;

		$table        = $wpdb->prefix . 'leastudios_mailer_log';
		$placeholders = implode( ', ', array_fill( 0, count( $message_ids ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders contains N '%s' tokens matching count($message_ids).
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT message_id, status, error_message FROM {$table} WHERE message_id IN ( {$placeholders} )",
				...$message_ids
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		if ( ! is_array( $results ) ) {
			return [];
		}

		return array_map(
			static function ( array $row ): array {
				return [
					'message_id'    => $row['message_id'] ?? '',
					'status'        => $row['status'] ?? '',
					'error_message' => $row['error_message'] ?? '',
				];
			},
			$results
		);
	}

	/**
	 * Render a coloured status badge for a delivery status.
	 *
	 * @param string $status The delivery status.
	 * @return string The badge HTML.
	 */
	public static function render_status_badge( string $status ): string {
		$colour = self::STATUS_COLOURS[ $status ] ?? '#888888';
		$label  = ucfirst( $status );

		return sprintf(
			'<span class="leastudios-forms-status-badge" style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:12px;font-weight:600;color:#fff;background-color:%s;">%s</span>',
			esc_attr( $colour ),
			esc_html( $label )
		);
	}
}

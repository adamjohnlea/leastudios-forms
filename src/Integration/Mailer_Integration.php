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
 *
 * The actual delivery data lives in the mailer's own log table. This class
 * never touches that table directly — it asks the mailer for a message's
 * status through the public `leastudios_mailer_delivery_status` filter and
 * renders the answer as a coloured badge in the forms entry detail view.
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

		add_filter( 'leastudios_forms_delivery_status', [ $this, 'filter_delivery_status' ], 10, 2 );

		/**
		 * Fires when the mailer integration is initialised.
		 *
		 * @param Mailer_Integration $integration The integration instance.
		 */
		do_action( 'leastudios_forms_mailer_integration_init', $this );
	}

	/**
	 * Answer the forms delivery-status filter using the mailer's public lookup.
	 *
	 * Looks the message up via the mailer's `leastudios_mailer_delivery_status`
	 * filter (the only supported cross-plugin read path into the mailer log)
	 * and, when a status is known, returns a coloured badge. When the message
	 * is unknown the unchanged default string is returned so the entry view
	 * still shows something sensible.
	 *
	 * @param string $status     The default status display string.
	 * @param string $message_id The notification (SES) message ID.
	 * @return string The status display string, as badge HTML when resolvable.
	 */
	public function filter_delivery_status( string $status, string $message_id ): string {
		/**
		 * Ask the mailer for this message's delivery status.
		 *
		 * The mailer answers with an array `['status' => string, 'error_message' => string]`
		 * when the message ID is known, or returns the passed-through default
		 * (here `null`) when it is not.
		 *
		 * @param array{status: string, error_message: string}|null $result     Status row, or null default.
		 * @param string                                             $message_id The SES message ID to look up.
		 */
		$result = apply_filters( 'leastudios_mailer_delivery_status', null, $message_id );

		if ( is_array( $result ) && ! empty( $result['status'] ) ) {
			return self::render_status_badge( (string) $result['status'] );
		}

		return $status;
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

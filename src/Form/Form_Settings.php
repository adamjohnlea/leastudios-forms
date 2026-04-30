<?php
/**
 * Form settings value object.
 *
 * @package LEAStudios\Forms\Form
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Form;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Represents a form's configuration settings.
 */
class Form_Settings {

	/**
	 * Constructor.
	 *
	 * @param string                                                                            $success_message    Message shown on successful submission.
	 * @param string                                                                            $redirect_url       URL to redirect to after submission.
	 * @param array<int, array{to: string, subject: string, message: string, reply_to: string}> $notifications      Email notification configurations.
	 * @param bool                                                                              $honeypot_enabled   Whether honeypot spam protection is enabled.
	 * @param int                                                                               $rate_limit         Max submissions per window.
	 * @param int                                                                               $rate_limit_window  Rate limit window in seconds.
	 * @param string                                                                            $submit_button_text Submit button label.
	 */
	public function __construct(
		public readonly string $success_message = 'Thank you for your submission.',
		public readonly string $redirect_url = '',
		public readonly array $notifications = [],
		public readonly bool $honeypot_enabled = true,
		public readonly int $rate_limit = 5,
		public readonly int $rate_limit_window = 60,
		public readonly string $submit_button_text = 'Submit',
	) {}

	/**
	 * Create from a JSON string stored in post meta.
	 *
	 * @param string $json The JSON settings string.
	 * @return self
	 */
	public static function from_json( string $json ): self {
		$data = json_decode( $json, true );

		if ( ! is_array( $data ) ) {
			return new self();
		}

		$notifications = [];

		foreach ( ( $data['notifications'] ?? [] ) as $notification ) {
			if ( is_array( $notification ) && ! empty( $notification['to'] ) ) {
				$notifications[] = [
					'to'       => sanitize_text_field( $notification['to'] ?? '' ),
					'subject'  => sanitize_text_field( $notification['subject'] ?? '' ),
					'message'  => wp_kses_post( $notification['message'] ?? '' ),
					'reply_to' => sanitize_text_field( $notification['reply_to'] ?? '' ),
				];
			}
		}

		return new self(
			success_message: sanitize_text_field( $data['success_message'] ?? 'Thank you for your submission.' ),
			redirect_url: esc_url_raw( $data['redirect_url'] ?? '' ),
			notifications: $notifications,
			honeypot_enabled: (bool) ( $data['spam_protection']['honeypot'] ?? true ),
			rate_limit: absint( $data['spam_protection']['rate_limit'] ?? 5 ),
			rate_limit_window: absint( $data['spam_protection']['rate_limit_window'] ?? 60 ),
			submit_button_text: sanitize_text_field( $data['submit_button_text'] ?? 'Submit' ),
		);
	}

	/**
	 * Convert to array for JSON storage.
	 *
	 * @return array<string, mixed> The settings as an array suitable for `wp_json_encode()`.
	 */
	public function to_array(): array {
		return [
			'success_message'    => $this->success_message,
			'redirect_url'       => $this->redirect_url,
			'notifications'      => $this->notifications,
			'spam_protection'    => [
				'honeypot'          => $this->honeypot_enabled,
				'rate_limit'        => $this->rate_limit,
				'rate_limit_window' => $this->rate_limit_window,
			],
			'submit_button_text' => $this->submit_button_text,
		];
	}
}

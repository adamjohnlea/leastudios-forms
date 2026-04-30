<?php
/**
 * Email notification sender.
 *
 * @package LEAStudios\Forms\Notification
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Notification;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Sends email notifications for form submissions.
 */
class Email_Notifier {

	/**
	 * Send notifications for a form submission.
	 *
	 * @param int                                                                                $form_id       The form post ID.
	 * @param int                                                                                $entry_id      The entry ID.
	 * @param array<string, mixed>                                                               $field_data    The submitted field data, keyed by field name.
	 * @param array<int, array{to: string, subject: string, message: string, reply_to?: string}> $notifications Notification configurations to dispatch.
	 * @return array<int, string> SES message IDs captured from sent emails.
	 */
	public function send( int $form_id, int $entry_id, array $field_data, array $notifications ): array {
		$message_ids = [];

		foreach ( $notifications as $notification ) {
			// Each merge-tag context picks the right escape: HTML body
			// runs values through esc_html; the email/subject paths strip
			// CR/LF instead so a malicious value cannot smuggle headers.
			$to      = $this->replace_merge_tags( $notification['to'] ?? '', $form_id, $field_data, $entry_id, 'email' );
			$subject = $this->replace_merge_tags( $notification['subject'] ?? '', $form_id, $field_data, $entry_id, 'subject' );
			$message = $this->replace_merge_tags( $notification['message'] ?? '', $form_id, $field_data, $entry_id, 'html' );

			/**
			 * Filter the notification message after merge tag replacement.
			 *
			 * @param string $message    The notification message.
			 * @param int    $form_id    The form post ID.
			 * @param array  $field_data The submitted field data.
			 * @return string Filtered message.
			 */
			$message = apply_filters( 'leastudios_forms_notification_message', $message, $form_id, $field_data );

			if ( empty( $to ) ) {
				continue;
			}

			$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

			if ( ! empty( $notification['reply_to'] ) ) {
				$reply_to = $this->replace_merge_tags( $notification['reply_to'], $form_id, $field_data, $entry_id, 'email' );

				// Only add Reply-To if the merge tag resolved to a valid email.
				if ( is_email( $reply_to ) ) {
					$headers[] = 'Reply-To: ' . sanitize_email( $reply_to );
				}
			}

			$captured_id = null;

			$listener = function ( array $args ) use ( &$captured_id ): void {
				if ( ! empty( $args['message_id'] ) ) {
					$captured_id = $args['message_id'];
				}
			};

			/**
			 * Filter each notification's email args before wp_mail().
			 *
			 * @param array $args       Array with to, subject, message, and headers keys.
			 * @param int   $form_id    The form post ID.
			 * @param int   $entry_id   The entry ID.
			 * @param array $field_data The submitted field data.
			 * @return array Filtered email args.
			 */
			$mail_args = apply_filters(
				'leastudios_forms_notification_args',
				[
					'to'      => $to,
					'subject' => $subject,
					'message' => $message,
					'headers' => $headers,
				],
				$form_id,
				$entry_id,
				$field_data
			);

			add_action( 'leastudios_mailer_email_sent', $listener, 10, 1 );

			wp_mail( $mail_args['to'], $mail_args['subject'], $mail_args['message'], $mail_args['headers'] );

			remove_action( 'leastudios_mailer_email_sent', $listener, 10 );

			if ( null !== $captured_id ) {
				$message_ids[] = $captured_id;
			}
		}

		return $message_ids;
	}

	/**
	 * Replace merge tags in a string.
	 *
	 * @param string               $text       The text containing merge tags.
	 * @param int                  $form_id    The form post ID.
	 * @param array<string, mixed> $field_data The submitted field data, keyed by field name.
	 * @param int                  $entry_id   Optional entry ID.
	 * @param string               $context    Merge context: 'html', 'subject', or 'email'.
	 *                                         Drives per-value escaping and whether the
	 *                                         HTML-only `{all_fields}` table is emitted.
	 * @return string The text with merge tags replaced.
	 */
	private function replace_merge_tags( string $text, int $form_id, array $field_data, int $entry_id = 0, string $context = 'html' ): string {
		$form  = get_post( $form_id );
		$title = $form ? $form->post_title : '';

		$escape = self::escape_for_context( $context );

		$text = str_replace( '{form_title}', $escape( $title ), $text );
		$text = str_replace( '{admin_email}', $escape( (string) get_option( 'admin_email' ) ), $text );
		$text = str_replace( '{site_name}', $escape( (string) get_option( 'blogname' ) ), $text );

		// Resolve `{field:<name>}` tags via a regex callback rather than
		// per-field str_replace. The earlier loop was vulnerable to
		// substring collisions when one field name was a prefix of another
		// (e.g. `email` and `email_alt`); the regex matches the full tag
		// boundary so each substitution is unambiguous.
		$text = preg_replace_callback(
			'/\{field:([a-zA-Z0-9_\-]+)\}/',
			static function ( array $matches ) use ( $field_data, $escape ): string {
				$field_name = $matches[1];

				if ( ! array_key_exists( $field_name, $field_data ) ) {
					return $matches[0];
				}

				$value         = $field_data[ $field_name ];
				$display_value = is_array( $value ) ? implode( ', ', $value ) : (string) $value;

				return $escape( $display_value );
			},
			$text
		) ?? $text;

		// `{all_fields}` is an HTML table; in non-HTML contexts (subject,
		// to/reply-to) the tag is replaced with an empty string so a stray
		// `{all_fields}` in a subject doesn't dump a markup table into the
		// header line.
		if ( 'html' === $context ) {
			$text = str_replace( '{all_fields}', $this->render_all_fields_html( $field_data ), $text );
		} else {
			$text = str_replace( '{all_fields}', '', $text );
		}

		return $text;
	}

	/**
	 * Build the HTML `{all_fields}` table for the message body.
	 *
	 * @param array<string, mixed> $field_data The submitted field data, keyed by field name.
	 * @return string
	 */
	private function render_all_fields_html( array $field_data ): string {
		$html = '<table style="width:100%;border-collapse:collapse;">';

		foreach ( $field_data as $field_name => $value ) {
			$label = ucwords( str_replace( [ '_', '-' ], ' ', $field_name ) );

			if ( is_array( $value ) && isset( $value['line1'] ) ) {
				$parts         = array_filter( array_map( 'trim', $value ) );
				$display_value = implode( ', ', $parts );
			} elseif ( is_array( $value ) ) {
				$display_value = implode( ', ', $value );
			} else {
				$display_value = (string) $value;
			}

			$html .= sprintf(
				'<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">%s</td><td style="padding:8px;border:1px solid #ddd;">%s</td></tr>',
				esc_html( $label ),
				esc_html( $display_value )
			);
		}

		$html .= '</table>';

		return $html;
	}

	/**
	 * Pick the value-escape strategy for a merge-tag context.
	 *
	 * - `html` runs values through `esc_html` so attacker-controlled field
	 *   values cannot inject markup into the email body.
	 * - `subject` and `email` strip CR/LF (and embedded NUL) so a value
	 *   cannot smuggle additional headers when concatenated into the
	 *   subject line or used as a recipient.
	 *
	 * @param string $context The context name.
	 * @return callable(string):string
	 */
	private static function escape_for_context( string $context ): callable {
		if ( 'html' === $context ) {
			return static fn( string $value ): string => esc_html( $value );
		}

		return static fn( string $value ): string => (string) preg_replace( '/[\r\n\x00]+/', ' ', $value );
	}
}

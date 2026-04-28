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
	 * @param int   $form_id       The form post ID.
	 * @param int   $entry_id      The entry ID.
	 * @param array $field_data    The submitted field data.
	 * @param array $notifications Array of notification configurations.
	 * @return array Array of SES message IDs captured from sent emails.
	 */
	public function send( int $form_id, int $entry_id, array $field_data, array $notifications ): array {
		$message_ids = [];

		foreach ( $notifications as $notification ) {
			$to      = $this->replace_merge_tags( $notification['to'] ?? '', $form_id, $field_data, $entry_id );
			$subject = $this->replace_merge_tags( $notification['subject'] ?? '', $form_id, $field_data, $entry_id );
			$message = $this->replace_merge_tags( $notification['message'] ?? '', $form_id, $field_data, $entry_id );

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
				$reply_to = $this->replace_merge_tags( $notification['reply_to'], $form_id, $field_data, $entry_id );

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
	 * @param string $text       The text containing merge tags.
	 * @param int    $form_id    The form post ID.
	 * @param array  $field_data The submitted field data.
	 * @param int    $entry_id   Optional entry ID.
	 * @return string The text with merge tags replaced.
	 */
	private function replace_merge_tags( string $text, int $form_id, array $field_data, int $entry_id = 0 ): string {
		$form  = get_post( $form_id );
		$title = $form ? $form->post_title : '';

		$text = str_replace( '{form_title}', $title, $text );
		$text = str_replace( '{admin_email}', get_option( 'admin_email' ), $text );
		$text = str_replace( '{site_name}', get_option( 'blogname' ), $text );

		// Replace individual field tags.
		foreach ( $field_data as $field_name => $value ) {
			$display_value = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
			$text          = str_replace( "{field:{$field_name}}", esc_html( $display_value ), $text );
		}

		// Replace {all_fields} with a formatted list.
		$all_fields_html = '<table style="width:100%;border-collapse:collapse;">';

		foreach ( $field_data as $field_name => $value ) {
			$label = ucwords( str_replace( [ '_', '-' ], ' ', $field_name ) );

			// Format address fields — filter empty parts and join cleanly.
			if ( is_array( $value ) && isset( $value['line1'] ) ) {
				$parts         = array_filter( array_map( 'trim', $value ) );
				$display_value = implode( ', ', $parts );
			} elseif ( is_array( $value ) ) {
				$display_value = implode( ', ', $value );
			} else {
				$display_value = (string) $value;
			}

			$cell_value = esc_html( $display_value );

			$all_fields_html .= sprintf(
				'<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">%s</td><td style="padding:8px;border:1px solid #ddd;">%s</td></tr>',
				esc_html( $label ),
				$cell_value
			);
		}

		$all_fields_html .= '</table>';

		$text = str_replace( '{all_fields}', $all_fields_html, $text );

		return $text;
	}
}

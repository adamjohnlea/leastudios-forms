<?php
/**
 * Form submission handler.
 *
 * @package LEAStudios\Forms\Submission
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Submission;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

use LEAStudios\Forms\Entry\Entry_Repository;
use LEAStudios\Forms\Field\Field_Registry;
use LEAStudios\Forms\Form\Form_Repository;
use LEAStudios\Forms\Notification\Email_Notifier;
use LEAStudios\Forms\Spam\Honeypot;
use LEAStudios\Forms\Spam\Rate_Limiter;

/**
 * Orchestrates the full form submission flow.
 */
class Submission_Handler {

	/**
	 * Constructor.
	 *
	 * @param Validator        $validator        The submission validator.
	 * @param Entry_Repository $entry_repository The entry repository.
	 * @param Email_Notifier   $email_notifier   The email notifier.
	 * @param Honeypot         $honeypot         The honeypot checker.
	 * @param Rate_Limiter     $rate_limiter     The rate limiter.
	 * @param Form_Repository  $form_repository  The form repository.
	 * @param Field_Registry   $field_registry   The field type registry.
	 */
	public function __construct(
		private readonly Validator $validator,
		private readonly Entry_Repository $entry_repository,
		private readonly Email_Notifier $email_notifier,
		private readonly Honeypot $honeypot,
		private readonly Rate_Limiter $rate_limiter,
		private readonly Form_Repository $form_repository,
		private readonly Field_Registry $field_registry,
	) {}

	/**
	 * Handle a form submission.
	 *
	 * @param int                  $form_id        The form post ID.
	 * @param array<string, mixed> $raw_data       The raw submitted data, keyed by field name.
	 * @param string|null          $honeypot_value The honeypot field value (null if the field was absent from the request).
	 * @param string               $ip             The submitter's IP address.
	 * @param string               $user_agent     The submitter's user agent.
	 * @param int|null             $user_id        The submitter's user ID.
	 * @return array{success: bool, message: string, errors: array<string, string>}
	 */
	public function handle( int $form_id, array $raw_data, ?string $honeypot_value, string $ip, string $user_agent, ?int $user_id ): array {
		/**
		 * Fires at the start of submission handling, before any validation.
		 *
		 * @param int   $form_id  The form post ID.
		 * @param array $raw_data The raw submitted data.
		 */
		do_action( 'leastudios_forms_before_submission', $form_id, $raw_data );

		/**
		 * Filter spam detection result.
		 *
		 * @param bool  $is_spam  Whether the submission is spam.
		 * @param int   $form_id  The form post ID.
		 * @param array $raw_data The raw submitted data.
		 * @return bool Filtered spam detection result.
		 */
		$is_spam = apply_filters( 'leastudios_forms_spam_detected', $this->honeypot->is_spam( $honeypot_value ), $form_id, $raw_data );

		// Check honeypot.
		if ( $is_spam ) {
			return [
				'success' => false,
				'message' => __( 'Spam detected.', 'leastudios-forms' ),
				'errors'  => [],
			];
		}

		// Get form and settings.
		$form = $this->form_repository->get_form( $form_id );

		if ( null === $form ) {
			return [
				'success' => false,
				'message' => __( 'Form not found.', 'leastudios-forms' ),
				'errors'  => [],
			];
		}

		$settings = $this->form_repository->get_settings( $form_id );

		// Check rate limit.
		$allowed = $this->rate_limiter->check( $ip, $form_id, $settings->rate_limit, $settings->rate_limit_window );

		if ( ! $allowed ) {
			return [
				'success' => false,
				'message' => __( 'Too many submissions. Please try again later.', 'leastudios-forms' ),
				'errors'  => [],
			];
		}

		// Get form fields configuration.
		$fields_config = $this->form_repository->get_fields( $form_id );

		// Sanitize submitted data via field registry.
		$sanitized_data = [];

		foreach ( $fields_config as $field_config ) {
			$name       = sanitize_key( $field_config['name'] ?? '' );
			$type       = $field_config['type'] ?? '';
			$field_type = $this->field_registry->get( $type );

			if ( '' === $name ) {
				continue;
			}

			$raw_value = $raw_data[ $name ] ?? '';

			if ( null !== $field_type ) {
				$sanitized_data[ $name ] = $field_type->sanitize( $raw_value );
			} else {
				$sanitized_data[ $name ] = sanitize_text_field( (string) $raw_value );
			}
		}

		/**
		 * Filter sanitized data before validation.
		 *
		 * @param array $sanitized_data The sanitized submitted data.
		 * @param int   $form_id        The form post ID.
		 * @param array $fields_config  The form fields configuration.
		 * @return array Filtered sanitized data.
		 */
		$sanitized_data = apply_filters( 'leastudios_forms_sanitized_data', $sanitized_data, $form_id, $fields_config );

		// Validate.
		$errors = $this->validator->validate( $fields_config, $sanitized_data );

		/**
		 * Filter validation errors.
		 *
		 * @param array $errors         The validation errors keyed by field name.
		 * @param int   $form_id        The form post ID.
		 * @param array $sanitized_data The sanitized submitted data.
		 * @return array Filtered errors array.
		 */
		$errors = apply_filters( 'leastudios_forms_validation_errors', $errors, $form_id, $sanitized_data );

		if ( ! empty( $errors ) ) {
			return [
				'success' => false,
				'message' => __( 'Please correct the errors below.', 'leastudios-forms' ),
				'errors'  => $errors,
			];
		}

		/**
		 * Filter data before storage.
		 *
		 * @param array $data    The data to be stored.
		 * @param int   $form_id The form post ID.
		 * @return array Filtered data.
		 */
		$sanitized_data = apply_filters( 'leastudios_forms_entry_data', $sanitized_data, $form_id );

		// Store entry.
		$entry_id = $this->entry_repository->create( $form_id, $sanitized_data, $ip, $user_agent, $user_id );

		if ( 0 === $entry_id ) {
			return [
				'success' => false,
				'message' => __( 'An error occurred saving your submission. Please try again.', 'leastudios-forms' ),
				'errors'  => [],
			];
		}

		/**
		 * Fires after a form submission is created.
		 *
		 * @param int   $entry_id       The entry ID.
		 * @param int   $form_id        The form post ID.
		 * @param array $sanitized_data The sanitized field data.
		 */
		do_action( 'leastudios_forms_submission_created', $entry_id, $form_id, $sanitized_data );

		// Send notifications.
		$message_ids = $this->email_notifier->send( $form_id, $entry_id, $sanitized_data, $settings->notifications );

		// Update entry with message IDs.
		if ( ! empty( $message_ids ) ) {
			$this->entry_repository->update_message_ids( $entry_id, $message_ids );

			/**
			 * Fires after notification emails are sent.
			 *
			 * @param int   $entry_id    The entry ID.
			 * @param int   $form_id     The form post ID.
			 * @param array $message_ids Array of SES message IDs.
			 */
			do_action( 'leastudios_forms_notification_sent', $entry_id, $form_id, $message_ids );
		}

		/**
		 * Filter the submission response array before returning.
		 *
		 * @param array $response An array with success, message, and errors keys.
		 * @param int   $form_id  The form post ID.
		 * @param int   $entry_id The entry ID.
		 * @return array Filtered response.
		 */
		return apply_filters(
			'leastudios_forms_submission_response',
			[
				'success' => true,
				'message' => $settings->success_message,
				'errors'  => [],
			],
			$form_id,
			$entry_id
		);
	}
}

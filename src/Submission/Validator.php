<?php
/**
 * Form submission validator.
 *
 * @package LEAStudios\Forms\Submission
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Submission;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

use LEAStudios\Forms\Field\Field_Registry;

/**
 * Validates submitted form data against field configurations.
 */
class Validator {

	/**
	 * Constructor.
	 *
	 * @param Field_Registry $field_registry The field type registry.
	 */
	public function __construct(
		private readonly Field_Registry $field_registry,
	) {}

	/**
	 * Validate submitted data against field configurations.
	 *
	 * @param array $fields_config  Array of field configuration arrays.
	 * @param array $submitted_data Submitted data keyed by field name.
	 * @return array Array of errors keyed by field name. Empty if valid.
	 */
	public function validate( array $fields_config, array $submitted_data ): array {
		$errors = [];

		foreach ( $fields_config as $field_config ) {
			$name  = $field_config['name'] ?? '';
			$type  = $field_config['type'] ?? '';
			$value = $submitted_data[ $name ] ?? '';

			if ( '' === $name ) {
				continue;
			}

			$required = ! empty( $field_config['required'] );

			if ( $required && ( '' === $value || null === $value || ( is_array( $value ) && empty( $value ) ) ) ) {
				$label           = $field_config['label'] ?? $name;
				$errors[ $name ] = sprintf(
					/* translators: %s: field label */
					__( '%s is required.', 'leastudios-forms' ),
					$label
				);
				continue;
			}

			$field_type = $this->field_registry->get( $type );

			if ( null === $field_type ) {
				continue;
			}

			$result = $field_type->validate( $value, $field_config );

			if ( true !== $result ) {
				$errors[ $name ] = $result;
			}
		}

		return $errors;
	}
}

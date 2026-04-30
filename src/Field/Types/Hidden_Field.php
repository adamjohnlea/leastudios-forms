<?php
/**
 * Hidden field type.
 *
 * @package LEAStudios\Forms\Field\Types
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Field\Types;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

use LEAStudios\Forms\Field\Field_Type;

/**
 * Hidden input field (no visible label).
 */
final class Hidden_Field implements Field_Type {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'hidden';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Hidden', 'leastudios-forms' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $value The value to sanitize.
	 * @return mixed
	 */
	public function sanitize( mixed $value ): mixed {
		return sanitize_text_field( (string) $value );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed                $value        The value to validate.
	 * @param array<string, mixed> $field_config The field configuration.
	 * @return true|string
	 */
	public function validate( mixed $value, array $field_config ): true|string {
		if ( ! empty( $field_config['required'] ) && '' === (string) $value ) {
			return sprintf(
				/* translators: %s: field label */
				__( '%s is required.', 'leastudios-forms' ),
				$field_config['label'] ?? $field_config['name'] ?? ''
			);
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $field_config The field configuration.
	 * @param mixed                $value        The current value.
	 * @return string
	 */
	public function render( array $field_config, mixed $value = null ): string {
		$id   = esc_attr( $field_config['id'] ?? '' );
		$name = esc_attr( $field_config['name'] ?? '' );
		$val  = esc_attr( (string) ( $value ?? '' ) );

		return sprintf(
			'<input type="hidden" id="%1$s" name="%2$s" value="%3$s" />',
			$id,
			$name,
			$val
		);
	}
}

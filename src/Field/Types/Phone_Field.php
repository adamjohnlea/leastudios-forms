<?php
/**
 * Phone field type.
 *
 * @package LEAStudios\Forms\Field\Types
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Field\Types;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

use LEAStudios\Forms\Field\Field_Type;

/**
 * Telephone number input.
 */
final class Phone_Field implements Field_Type {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'phone';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Phone', 'leastudios-forms' );
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
	 * @param mixed $value        The value to validate.
	 * @param array $field_config The field configuration.
	 * @return true|string
	 */
	public function validate( mixed $value, array $field_config ): true|string {
		$label = $field_config['label'] ?? $field_config['name'] ?? '';

		if ( ! empty( $field_config['required'] ) && '' === (string) $value ) {
			return sprintf(
				/* translators: %s: field label */
				__( '%s is required.', 'leastudios-forms' ),
				$label
			);
		}

		if ( ! empty( $field_config['validation']['pattern'] ) && '' !== (string) $value ) {
			$pattern = '/' . $field_config['validation']['pattern'] . '/';
			if ( ! preg_match( $pattern, (string) $value ) ) {
				return sprintf(
					/* translators: %s: field label */
					__( '%s is not a valid phone number.', 'leastudios-forms' ),
					$label
				);
			}
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $field_config The field configuration.
	 * @param mixed $value        The current value.
	 * @return string
	 */
	public function render( array $field_config, mixed $value = null ): string {
		$id          = esc_attr( $field_config['id'] ?? '' );
		$name        = esc_attr( $field_config['name'] ?? '' );
		$label       = esc_html( $field_config['label'] ?? '' );
		$placeholder = esc_attr( $field_config['placeholder'] ?? '' );
		$required    = ! empty( $field_config['required'] );
		$val         = esc_attr( (string) ( $value ?? '' ) );

		$required_attrs = $required ? ' required aria-required="true"' : '';

		return sprintf(
			'<div class="leastudios-forms-field leastudios-forms-field--phone">'
			. '<label for="%1$s">%2$s</label>'
			. '<input type="tel" id="%1$s" name="%3$s" value="%4$s" placeholder="%5$s"%6$s />'
			. '</div>',
			$id,
			$label,
			$name,
			$val,
			$placeholder,
			$required_attrs
		);
	}
}

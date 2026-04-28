<?php
/**
 * Textarea field type.
 *
 * @package LEAStudios\Forms\Field\Types
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Field\Types;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

use LEAStudios\Forms\Field\Field_Type;

/**
 * Multi-line textarea input.
 */
final class Textarea_Field implements Field_Type {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'textarea';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Textarea', 'leastudios-forms' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $value The value to sanitize.
	 * @return mixed
	 */
	public function sanitize( mixed $value ): mixed {
		return sanitize_textarea_field( (string) $value );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $value        The value to validate.
	 * @param array $field_config The field configuration.
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
		$val         = esc_textarea( (string) ( $value ?? '' ) );

		$required_attrs = $required ? ' required aria-required="true"' : '';

		return sprintf(
			'<div class="leastudios-forms-field leastudios-forms-field--textarea">'
			. '<label for="%1$s">%2$s</label>'
			. '<textarea id="%1$s" name="%3$s" placeholder="%4$s"%5$s>%6$s</textarea>'
			. '</div>',
			$id,
			$label,
			$name,
			$placeholder,
			$required_attrs,
			$val
		);
	}
}

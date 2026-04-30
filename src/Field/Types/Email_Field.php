<?php
/**
 * Email field type.
 *
 * @package LEAStudios\Forms\Field\Types
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Field\Types;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

use LEAStudios\Forms\Field\Field_Type;

/**
 * Email address input.
 */
final class Email_Field implements Field_Type {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'email';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Email', 'leastudios-forms' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $value The value to sanitize.
	 * @return mixed
	 */
	public function sanitize( mixed $value ): mixed {
		return sanitize_email( (string) $value );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed                $value        The value to validate.
	 * @param array<string, mixed> $field_config The field configuration.
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

		if ( '' !== (string) $value && ! is_email( (string) $value ) ) {
			return sprintf(
				/* translators: %s: field label */
				__( '%s must be a valid email address.', 'leastudios-forms' ),
				$label
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
		$id          = esc_attr( $field_config['id'] ?? '' );
		$name        = esc_attr( $field_config['name'] ?? '' );
		$label       = esc_html( $field_config['label'] ?? '' );
		$placeholder = esc_attr( $field_config['placeholder'] ?? '' );
		$required    = ! empty( $field_config['required'] );
		$val         = esc_attr( (string) ( $value ?? '' ) );

		$required_attrs = $required ? ' required aria-required="true"' : '';

		return sprintf(
			'<div class="leastudios-forms-field leastudios-forms-field--email">'
			. '<label for="%1$s">%2$s</label>'
			. '<input type="email" id="%1$s" name="%3$s" value="%4$s" placeholder="%5$s"%6$s />'
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

<?php
/**
 * Number field type.
 *
 * @package LEAStudios\Forms\Field\Types
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Field\Types;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

use LEAStudios\Forms\Field\Field_Type;

/**
 * Numeric input field.
 */
final class Number_Field implements Field_Type {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'number';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Number', 'leastudios-forms' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $value The value to sanitize.
	 * @return mixed
	 */
	public function sanitize( mixed $value ): mixed {
		$text = sanitize_text_field( (string) $value );

		// An empty submission stays empty so `validate()` can flag it as
		// missing for required fields. A non-empty numeric value is
		// normalised — int when it's whole, float otherwise — so consumers
		// (entry storage, merge tags, exports) get a real number rather
		// than the raw input string with its locale-specific separators.
		if ( '' === $text ) {
			return '';
		}

		if ( ! is_numeric( $text ) ) {
			return $text;
		}

		// Strip any thousands grouping the input may have, then coerce.
		$normalised = (float) $text;

		return floor( $normalised ) === $normalised && abs( $normalised ) < PHP_INT_MAX
			? (int) $normalised
			: $normalised;
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

		if ( '' !== (string) $value && ! is_numeric( $value ) ) {
			return sprintf(
				/* translators: %s: field label */
				__( '%s must be a valid number.', 'leastudios-forms' ),
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
			'<div class="leastudios-forms-field leastudios-forms-field--number">'
			. '<label for="%1$s">%2$s</label>'
			. '<input type="number" id="%1$s" name="%3$s" value="%4$s" placeholder="%5$s"%6$s />'
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

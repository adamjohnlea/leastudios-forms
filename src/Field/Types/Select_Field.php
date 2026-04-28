<?php
/**
 * Select field type.
 *
 * @package LEAStudios\Forms\Field\Types
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Field\Types;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

use LEAStudios\Forms\Field\Field_Type;

/**
 * Dropdown select input.
 */
final class Select_Field implements Field_Type {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'select';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Select', 'leastudios-forms' );
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

		if ( '' !== (string) $value && ! empty( $field_config['options'] ) ) {
			$valid_values = array_column( $field_config['options'], 'value' );
			if ( ! in_array( (string) $value, $valid_values, true ) ) {
				return sprintf(
					/* translators: %s: field label */
					__( '%s contains an invalid selection.', 'leastudios-forms' ),
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
		$placeholder = $field_config['placeholder'] ?? '';
		$required    = ! empty( $field_config['required'] );
		$options     = $field_config['options'] ?? [];
		$val         = (string) ( $value ?? '' );

		$required_attrs = $required ? ' required aria-required="true"' : '';

		$html = sprintf(
			'<div class="leastudios-forms-field leastudios-forms-field--select">'
			. '<label for="%1$s">%2$s</label>'
			. '<select id="%1$s" name="%3$s"%4$s>',
			$id,
			$label,
			$name,
			$required_attrs
		);

		if ( '' !== $placeholder ) {
			$html .= sprintf(
				'<option value="">%s</option>',
				esc_html( $placeholder )
			);
		}

		foreach ( $options as $option ) {
			$option_value = esc_attr( $option['value'] ?? '' );
			$option_label = esc_html( $option['label'] ?? '' );
			$selected     = selected( $val, $option['value'] ?? '', false );

			$html .= sprintf(
				'<option value="%1$s"%2$s>%3$s</option>',
				$option_value,
				$selected,
				$option_label
			);
		}

		$html .= '</select></div>';

		return $html;
	}
}

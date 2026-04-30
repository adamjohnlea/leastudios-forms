<?php
/**
 * Radio field type.
 *
 * @package LEAStudios\Forms\Field\Types
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Field\Types;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

use LEAStudios\Forms\Field\Field_Type;

/**
 * Radio button group input.
 */
final class Radio_Field implements Field_Type {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'radio';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Radio', 'leastudios-forms' );
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
	 * @param array<string, mixed> $field_config The field configuration.
	 * @param mixed                $value        The current value.
	 * @return string
	 */
	public function render( array $field_config, mixed $value = null ): string {
		$id       = esc_attr( $field_config['id'] ?? '' );
		$name     = esc_attr( $field_config['name'] ?? '' );
		$label    = esc_html( $field_config['label'] ?? '' );
		$required = ! empty( $field_config['required'] );
		$options  = $field_config['options'] ?? [];
		$val      = (string) ( $value ?? '' );

		$required_attrs = $required ? ' required aria-required="true"' : '';

		$html = sprintf(
			'<div class="leastudios-forms-field leastudios-forms-field--radio">'
			. '<fieldset id="%1$s">'
			. '<legend>%2$s</legend>',
			$id,
			$label
		);

		foreach ( $options as $index => $option ) {
			$option_value = esc_attr( $option['value'] ?? '' );
			$option_label = esc_html( $option['label'] ?? '' );
			$option_id    = $id . '-' . $index;
			$checked      = checked( $val, $option['value'] ?? '', false );

			$html .= sprintf(
				'<label for="%1$s">'
				. '<input type="radio" id="%1$s" name="%2$s" value="%3$s"%4$s%5$s />'
				. ' %6$s'
				. '</label>',
				$option_id,
				$name,
				$option_value,
				$checked,
				$required_attrs,
				$option_label
			);
		}

		$html .= '</fieldset></div>';

		return $html;
	}
}

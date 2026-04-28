<?php
/**
 * Address field type.
 *
 * @package LEAStudios\Forms\Field\Types
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Field\Types;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

use LEAStudios\Forms\Field\Field_Type;

/**
 * Structured address field rendered as a group of inputs.
 */
final class Address_Field implements Field_Type {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'address';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Address', 'leastudios-forms' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $value The value to sanitize.
	 * @return mixed
	 */
	public function sanitize( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return [
				'line1'   => '',
				'line2'   => '',
				'city'    => '',
				'state'   => '',
				'zip'     => '',
				'country' => '',
			];
		}

		return [
			'line1'   => sanitize_text_field( $value['line1'] ?? '' ),
			'line2'   => sanitize_text_field( $value['line2'] ?? '' ),
			'city'    => sanitize_text_field( $value['city'] ?? '' ),
			'state'   => sanitize_text_field( $value['state'] ?? '' ),
			'zip'     => sanitize_text_field( $value['zip'] ?? '' ),
			'country' => sanitize_text_field( $value['country'] ?? '' ),
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $value        The value to validate.
	 * @param array $field_config The field configuration.
	 * @return true|string
	 */
	public function validate( mixed $value, array $field_config ): true|string {
		if ( empty( $field_config['required'] ) ) {
			return true;
		}

		$label = $field_config['label'] ?? 'Address';

		if ( ! is_array( $value ) ) {
			return sprintf(
				/* translators: %s: field label. */
				__( '%s is required.', 'leastudios-forms' ),
				$label
			);
		}

		$required_parts = [ 'line1', 'city', 'state', 'zip', 'country' ];

		foreach ( $required_parts as $part ) {
			if ( empty( $value[ $part ] ) ) {
				$part_labels = [
					'line1'   => __( 'Street Address', 'leastudios-forms' ),
					'city'    => __( 'City', 'leastudios-forms' ),
					'state'   => __( 'State/Province', 'leastudios-forms' ),
					'zip'     => __( 'Zip/Postal Code', 'leastudios-forms' ),
					'country' => __( 'Country', 'leastudios-forms' ),
				];

				return sprintf(
					/* translators: %s: sub-field label. */
					__( '%s is required.', 'leastudios-forms' ),
					$part_labels[ $part ] ?? $part
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
		$id              = esc_attr( $field_config['id'] ?? '' );
		$name            = esc_attr( $field_config['name'] ?? '' );
		$label           = esc_html( $field_config['label'] ?? 'Address' );
		$required        = ! empty( $field_config['required'] );
		$show_line2      = ! isset( $field_config['show_line2'] ) || ! empty( $field_config['show_line2'] );
		$default_country = esc_attr( $field_config['default_country'] ?? 'US' );

		$vals = is_array( $value ) ? $value : [];

		$req_attrs = $required ? ' required aria-required="true"' : '';

		$html  = '<fieldset class="leastudios-forms-field leastudios-forms-field--address" id="' . $id . '">';
		$html .= '<legend>' . $label;
		if ( $required ) {
			$html .= ' <span class="required-indicator">*</span>';
		}
		$html .= '</legend>';

		$html .= '<div class="leastudios-address-row">';
		$html .= '<label for="' . $id . '-line1">' . esc_html__( 'Street Address', 'leastudios-forms' ) . '</label>';
		$html .= '<input type="text" id="' . $id . '-line1" name="' . $name . '[line1]" value="' . esc_attr( $vals['line1'] ?? '' ) . '"' . $req_attrs . ' />';
		$html .= '</div>';

		if ( $show_line2 ) {
			$html .= '<div class="leastudios-address-row">';
			$html .= '<label for="' . $id . '-line2">' . esc_html__( 'Address Line 2', 'leastudios-forms' ) . '</label>';
			$html .= '<input type="text" id="' . $id . '-line2" name="' . $name . '[line2]" value="' . esc_attr( $vals['line2'] ?? '' ) . '" />';
			$html .= '</div>';
		}

		$html .= '<div class="leastudios-address-row leastudios-address-row--split">';

		$html .= '<div class="leastudios-address-col">';
		$html .= '<label for="' . $id . '-city">' . esc_html__( 'City', 'leastudios-forms' ) . '</label>';
		$html .= '<input type="text" id="' . $id . '-city" name="' . $name . '[city]" value="' . esc_attr( $vals['city'] ?? '' ) . '"' . $req_attrs . ' />';
		$html .= '</div>';

		$html .= '<div class="leastudios-address-col">';
		$html .= '<label for="' . $id . '-state">' . esc_html__( 'State / Province', 'leastudios-forms' ) . '</label>';
		$html .= '<input type="text" id="' . $id . '-state" name="' . $name . '[state]" value="' . esc_attr( $vals['state'] ?? '' ) . '"' . $req_attrs . ' />';
		$html .= '</div>';

		$html .= '</div>';

		$html .= '<div class="leastudios-address-row leastudios-address-row--split">';

		$html .= '<div class="leastudios-address-col">';
		$html .= '<label for="' . $id . '-zip">' . esc_html__( 'Zip / Postal Code', 'leastudios-forms' ) . '</label>';
		$html .= '<input type="text" id="' . $id . '-zip" name="' . $name . '[zip]" value="' . esc_attr( $vals['zip'] ?? '' ) . '"' . $req_attrs . ' />';
		$html .= '</div>';

		$html .= '<div class="leastudios-address-col">';
		$html .= '<label for="' . $id . '-country">' . esc_html__( 'Country', 'leastudios-forms' ) . '</label>';
		$html .= '<select id="' . $id . '-country" name="' . $name . '[country]"' . $req_attrs . '>';
		$html .= $this->render_country_options( $vals['country'] ?? $default_country );
		$html .= '</select>';
		$html .= '</div>';

		$html .= '</div>';

		$html .= '</fieldset>';

		return $html;
	}

	/**
	 * Render country select options.
	 *
	 * @param string $selected The selected country code.
	 * @return string HTML option elements.
	 */
	private function render_country_options( string $selected ): string {
		$countries = $this->get_countries();
		$html      = '<option value="">' . esc_html__( 'Select Country', 'leastudios-forms' ) . '</option>';

		foreach ( $countries as $code => $name ) {
			$html .= '<option value="' . esc_attr( $code ) . '"' . selected( $selected, $code, false ) . '>' . esc_html( $name ) . '</option>';
		}

		return $html;
	}

	/**
	 * Get the list of countries.
	 *
	 * @return array<string, string> Country code => name.
	 */
	private function get_countries(): array {
		$countries = [
			'US' => 'United States',
			'CA' => 'Canada',
			'GB' => 'United Kingdom',
			'AU' => 'Australia',
			'NZ' => 'New Zealand',
			'IE' => 'Ireland',
			'DE' => 'Germany',
			'FR' => 'France',
			'ES' => 'Spain',
			'IT' => 'Italy',
			'NL' => 'Netherlands',
			'BE' => 'Belgium',
			'AT' => 'Austria',
			'CH' => 'Switzerland',
			'SE' => 'Sweden',
			'NO' => 'Norway',
			'DK' => 'Denmark',
			'FI' => 'Finland',
			'PT' => 'Portugal',
			'JP' => 'Japan',
			'SG' => 'Singapore',
			'HK' => 'Hong Kong',
			'IN' => 'India',
			'BR' => 'Brazil',
			'MX' => 'Mexico',
			'ZA' => 'South Africa',
			'AE' => 'United Arab Emirates',
			'IL' => 'Israel',
		];

		/**
		 * Filters the available countries in the address field.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, string> $countries Country code => name pairs.
		 * @return array<string, string> Filtered countries.
		 */
		return (array) apply_filters( 'leastudios_forms_address_countries', $countries );
	}
}

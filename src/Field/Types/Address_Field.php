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
	 * @param mixed                $value        The value to validate.
	 * @param array<string, mixed> $field_config The field configuration.
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
	 * @param array<string, mixed> $field_config The field configuration.
	 * @param mixed                $value        The current value.
	 * @return string
	 */
	public function render( array $field_config, mixed $value = null ): string {
		$id              = esc_attr( $field_config['id'] ?? '' );
		$name            = esc_attr( $field_config['name'] ?? '' );
		$label           = esc_html( $field_config['label'] ?? 'Address' );
		$required        = ! empty( $field_config['required'] );
		$show_line2      = ! isset( $field_config['show_line2'] ) || ! empty( $field_config['show_line2'] );
		$default_country = esc_attr( $field_config['default_country'] ?? 'US' );

		$vals      = is_array( $value ) ? $value : [];
		$req_attrs = $required ? ' required aria-required="true"' : '';
		$req_mark  = $required ? ' <span class="required-indicator">*</span>' : '';

		$line2_html = '';
		if ( $show_line2 ) {
			$line2_html = sprintf(
				'<div class="leastudios-address-row"><label for="%1$s-line2">%2$s</label><input type="text" id="%1$s-line2" name="%3$s[line2]" value="%4$s" /></div>',
				$id,
				esc_html__( 'Address Line 2', 'leastudios-forms' ),
				$name,
				esc_attr( $vals['line2'] ?? '' )
			);
		}

		return sprintf(
			'<fieldset class="leastudios-forms-field leastudios-forms-field--address" id="%1$s">'
				. '<legend>%2$s%3$s</legend>'
				. '<div class="leastudios-address-row">'
					. '<label for="%1$s-line1">%4$s</label>'
					. '<input type="text" id="%1$s-line1" name="%5$s[line1]" value="%6$s"%7$s />'
				. '</div>'
				. '%8$s'
				. '<div class="leastudios-address-row leastudios-address-row--split">'
					. '<div class="leastudios-address-col">'
						. '<label for="%1$s-city">%9$s</label>'
						. '<input type="text" id="%1$s-city" name="%5$s[city]" value="%10$s"%7$s />'
					. '</div>'
					. '<div class="leastudios-address-col">'
						. '<label for="%1$s-state">%11$s</label>'
						. '<input type="text" id="%1$s-state" name="%5$s[state]" value="%12$s"%7$s />'
					. '</div>'
				. '</div>'
				. '<div class="leastudios-address-row leastudios-address-row--split">'
					. '<div class="leastudios-address-col">'
						. '<label for="%1$s-zip">%13$s</label>'
						. '<input type="text" id="%1$s-zip" name="%5$s[zip]" value="%14$s"%7$s />'
					. '</div>'
					. '<div class="leastudios-address-col">'
						. '<label for="%1$s-country">%15$s</label>'
						. '<select id="%1$s-country" name="%5$s[country]"%7$s>%16$s</select>'
					. '</div>'
				. '</div>'
			. '</fieldset>',
			$id,
			$label,
			$req_mark,
			esc_html__( 'Street Address', 'leastudios-forms' ),
			$name,
			esc_attr( $vals['line1'] ?? '' ),
			$req_attrs,
			$line2_html,
			esc_html__( 'City', 'leastudios-forms' ),
			esc_attr( $vals['city'] ?? '' ),
			esc_html__( 'State / Province', 'leastudios-forms' ),
			esc_attr( $vals['state'] ?? '' ),
			esc_html__( 'Zip / Postal Code', 'leastudios-forms' ),
			esc_attr( $vals['zip'] ?? '' ),
			esc_html__( 'Country', 'leastudios-forms' ),
			$this->render_country_options( $vals['country'] ?? $default_country )
		);
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

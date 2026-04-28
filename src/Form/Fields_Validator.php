<?php
/**
 * Validates and normalizes form field configurations.
 *
 * @package LEAStudios\Forms\Form
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Form;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

use LEAStudios\Forms\Field\Field_Registry;

/**
 * Sanity-checks the field-builder JSON before it's persisted.
 *
 * Drops fields that lack a registered type or an id; coerces well-known
 * keys to their expected scalar/array shapes; preserves unknown keys so
 * third-party field types can extend the schema without losing data.
 */
final class Fields_Validator {

	/**
	 * Constructor.
	 *
	 * @param Field_Registry $field_registry Used to verify that each field's type is registered.
	 */
	public function __construct(
		private readonly Field_Registry $field_registry,
	) {}

	/**
	 * Validate and normalize an array of field configurations.
	 *
	 * @param array<int|string, mixed> $fields Raw field configs (typically decoded from JSON).
	 * @return array<int, array<string, mixed>> The cleaned, normalized field list.
	 */
	public function validate( array $fields ): array {
		$valid = [];

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$type = is_string( $field['type'] ?? null ) ? $field['type'] : '';

			if ( '' === $type || null === $this->field_registry->get( $type ) ) {
				continue;
			}

			$id = is_string( $field['id'] ?? null ) ? $field['id'] : '';

			if ( '' === $id ) {
				continue;
			}

			$valid[] = $this->normalize_field( $field );
		}

		return $valid;
	}

	/**
	 * Normalize the well-known keys of a single field config.
	 *
	 * @param array<string, mixed> $field The field config.
	 * @return array<string, mixed> The normalized field config.
	 */
	private function normalize_field( array $field ): array {
		$field['id']          = (string) $field['id'];
		$field['type']        = (string) $field['type'];
		$field['name']        = (string) ( $field['name'] ?? $field['id'] );
		$field['label']       = (string) ( $field['label'] ?? '' );
		$field['placeholder'] = (string) ( $field['placeholder'] ?? '' );
		$field['required']    = ! empty( $field['required'] );

		if ( isset( $field['options'] ) ) {
			$field['options'] = $this->normalize_options( $field['options'] );
		}

		return $field;
	}

	/**
	 * Normalize the `options` array used by select / radio / checkbox fields.
	 *
	 * @param mixed $options Whatever was supplied on the wire.
	 * @return array<int, array{value: string, label: string}> Normalized options.
	 */
	private function normalize_options( mixed $options ): array {
		if ( ! is_array( $options ) ) {
			return [];
		}

		$normalized = [];

		foreach ( $options as $option ) {
			if ( ! is_array( $option ) ) {
				continue;
			}

			$normalized[] = [
				'value' => (string) ( $option['value'] ?? '' ),
				'label' => (string) ( $option['label'] ?? '' ),
			];
		}

		return $normalized;
	}
}

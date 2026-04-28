<?php
/**
 * Field type interface.
 *
 * @package LEAStudios\Forms\Field
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Field;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Contract for form field types.
 */
interface Field_Type {

	/**
	 * Get the field type slug.
	 *
	 * @return string
	 */
	public function get_type(): string;

	/**
	 * Get the display label for this field type.
	 *
	 * @return string
	 */
	public function get_label(): string;

	/**
	 * Sanitize a submitted value.
	 *
	 * @param mixed $value The raw submitted value.
	 * @return mixed The sanitized value.
	 */
	public function sanitize( mixed $value ): mixed;

	/**
	 * Validate a submitted value.
	 *
	 * @param mixed $value        The sanitized value.
	 * @param array $field_config The field configuration.
	 * @return true|string True if valid, or an error message string.
	 */
	public function validate( mixed $value, array $field_config ): true|string;

	/**
	 * Render the field HTML.
	 *
	 * @param array $field_config The field configuration.
	 * @param mixed $value        Current value (for repopulation).
	 * @return string The field HTML.
	 */
	public function render( array $field_config, mixed $value = null ): string;
}

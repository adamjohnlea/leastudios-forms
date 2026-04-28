<?php
/**
 * Field type registry.
 *
 * @package LEAStudios\Forms\Field
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Field;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

use LEAStudios\Forms\Field\Types\Checkbox_Field;
use LEAStudios\Forms\Field\Types\Email_Field;
use LEAStudios\Forms\Field\Types\Hidden_Field;
use LEAStudios\Forms\Field\Types\Number_Field;
use LEAStudios\Forms\Field\Types\Phone_Field;
use LEAStudios\Forms\Field\Types\Radio_Field;
use LEAStudios\Forms\Field\Types\Select_Field;
use LEAStudios\Forms\Field\Types\Text_Field;
use LEAStudios\Forms\Field\Types\Textarea_Field;
use LEAStudios\Forms\Field\Types\Address_Field;
use LEAStudios\Forms\Field\Types\Url_Field;

/**
 * Manages registered field types.
 */
class Field_Registry {

	/**
	 * Registered field types.
	 *
	 * @var array<string, Field_Type>
	 */
	private array $types = [];

	/**
	 * Register all default field types.
	 *
	 * @return void
	 */
	public function register_defaults(): void {
		$defaults = [
			new Text_Field(),
			new Email_Field(),
			new Textarea_Field(),
			new Select_Field(),
			new Checkbox_Field(),
			new Radio_Field(),
			new Hidden_Field(),
			new Number_Field(),
			new Phone_Field(),
			new Url_Field(),
			new Address_Field(),
		];

		foreach ( $defaults as $type ) {
			$this->register( $type );
		}

		/**
		 * Allow other plugins to register custom field types.
		 *
		 * @param Field_Registry $registry The field registry instance.
		 */
		do_action( 'leastudios_forms_field_types', $this );
	}

	/**
	 * Register a field type.
	 *
	 * @param Field_Type $type The field type to register.
	 * @return void
	 */
	public function register( Field_Type $type ): void {
		$this->types[ $type->get_type() ] = $type;
	}

	/**
	 * Get a field type by slug.
	 *
	 * @param string $type The type slug.
	 * @return Field_Type|null
	 */
	public function get( string $type ): ?Field_Type {
		return $this->types[ $type ] ?? null;
	}

	/**
	 * Get all registered field types.
	 *
	 * @return array<string, Field_Type>
	 */
	public function get_all(): array {
		return $this->types;
	}
}

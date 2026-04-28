<?php
/**
 * Tests for Field_Registry.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Field\Field_Registry;
use LEAStudios\Forms\Field\Field_Type;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Field\Field_Registry
 */
class FieldRegistryTest extends TestCase {

	private Field_Registry $registry;

	public function set_up(): void {
		parent::set_up();
		$this->registry = new Field_Registry();
	}

	public function test_get_returns_null_for_unregistered_type(): void {
		$this->assertNull( $this->registry->get( 'nonexistent' ) );
	}

	public function test_get_all_returns_empty_array_initially(): void {
		$this->assertSame( [], $this->registry->get_all() );
	}

	public function test_register_and_get(): void {
		$mock_type = $this->createMock( Field_Type::class );
		$mock_type->method( 'get_type' )->willReturn( 'custom_field' );

		$this->registry->register( $mock_type );

		$this->assertSame( $mock_type, $this->registry->get( 'custom_field' ) );
	}

	public function test_register_defaults_loads_all_field_types(): void {
		$this->registry->register_defaults();

		$expected_types = [
			'text',
			'email',
			'textarea',
			'select',
			'checkbox',
			'radio',
			'hidden',
			'number',
			'phone',
			'url',
			'address',
		];

		$all = $this->registry->get_all();

		foreach ( $expected_types as $type ) {
			$this->assertArrayHasKey( $type, $all, "Missing default field type: {$type}" );
			$this->assertInstanceOf( Field_Type::class, $all[ $type ] );
		}

		$this->assertCount( count( $expected_types ), $all );
	}

	public function test_register_defaults_fires_action(): void {
		$fired = false;

		add_action(
			'leastudios_forms_field_types',
			function ( Field_Registry $registry ) use ( &$fired ) {
				$fired = true;
				$this->assertInstanceOf( Field_Registry::class, $registry );
			}
		);

		$this->registry->register_defaults();

		$this->assertTrue( $fired );
	}

	public function test_register_overwrites_existing_type(): void {
		$first = $this->createMock( Field_Type::class );
		$first->method( 'get_type' )->willReturn( 'text' );

		$second = $this->createMock( Field_Type::class );
		$second->method( 'get_type' )->willReturn( 'text' );

		$this->registry->register( $first );
		$this->registry->register( $second );

		$this->assertSame( $second, $this->registry->get( 'text' ) );
	}
}

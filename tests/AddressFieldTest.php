<?php
/**
 * Tests for Address_Field.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Field\Types\Address_Field;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Field\Types\Address_Field
 */
class AddressFieldTest extends TestCase {

	private Address_Field $field;

	public function set_up(): void {
		parent::set_up();
		$this->field = new Address_Field();
	}

	public function test_get_type(): void {
		$this->assertSame( 'address', $this->field->get_type() );
	}

	public function test_get_label_is_not_empty(): void {
		$this->assertNotEmpty( $this->field->get_label() );
	}

	public function test_sanitize_returns_six_key_array_for_non_array_input(): void {
		$result = $this->field->sanitize( 'garbage' );

		$this->assertSame(
			[ 'line1', 'line2', 'city', 'state', 'zip', 'country' ],
			array_keys( $result )
		);
	}

	public function test_sanitize_cleans_known_keys(): void {
		$result = $this->field->sanitize(
			[
				'line1' => '10 Main St',
				'city'  => 'Springfield',
				'extra' => 'dropped',
			]
		);

		$this->assertSame( '10 Main St', $result['line1'] );
		$this->assertSame( 'Springfield', $result['city'] );
		$this->assertArrayNotHasKey( 'extra', $result );
	}

	public function test_validate_optional_always_passes(): void {
		$this->assertTrue( $this->field->validate( 'anything', [] ) );
	}

	public function test_validate_required_rejects_incomplete_address(): void {
		$value = [
			'line1'   => '10 Main St',
			'city'    => '',
			'state'   => 'CA',
			'zip'     => '90210',
			'country' => 'US',
		];

		$this->assertIsString( $this->field->validate( $value, [ 'required' => true ] ) );
	}

	public function test_validate_required_passes_for_complete_address(): void {
		$value = [
			'line1'   => '10 Main St',
			'city'    => 'LA',
			'state'   => 'CA',
			'zip'     => '90210',
			'country' => 'US',
		];

		$this->assertTrue( $this->field->validate( $value, [ 'required' => true ] ) );
	}

	public function test_render_outputs_address_fieldset(): void {
		$html = $this->field->render(
			[
				'id'    => 'a1',
				'name'  => 'addr',
				'label' => 'Address',
			]
		);

		$this->assertStringContainsString( 'name="addr[line1]"', $html );
		$this->assertStringContainsString( 'name="addr[country]"', $html );
	}
}

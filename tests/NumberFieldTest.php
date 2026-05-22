<?php
/**
 * Tests for Number_Field.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Field\Types\Number_Field;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Field\Types\Number_Field
 */
class NumberFieldTest extends TestCase {

	private Number_Field $field;

	public function set_up(): void {
		parent::set_up();
		$this->field = new Number_Field();
	}

	public function test_get_type(): void {
		$this->assertSame( 'number', $this->field->get_type() );
	}

	public function test_get_label_is_not_empty(): void {
		$this->assertNotEmpty( $this->field->get_label() );
	}

	public function test_sanitize_keeps_empty_string_empty(): void {
		$this->assertSame( '', $this->field->sanitize( '' ) );
	}

	public function test_sanitize_normalizes_whole_number_to_int(): void {
		$this->assertSame( 42, $this->field->sanitize( '42' ) );
	}

	public function test_sanitize_normalizes_fraction_to_float(): void {
		$this->assertSame( 3.5, $this->field->sanitize( '3.5' ) );
	}

	public function test_sanitize_passes_non_numeric_through(): void {
		$this->assertSame( 'abc', $this->field->sanitize( 'abc' ) );
	}

	public function test_validate_rejects_non_numeric(): void {
		$this->assertIsString( $this->field->validate( 'abc', [] ) );
	}

	public function test_validate_passes_for_numeric(): void {
		$this->assertTrue( $this->field->validate( 10, [] ) );
	}

	public function test_validate_required_rejects_empty(): void {
		$this->assertIsString( $this->field->validate( '', [ 'required' => true ] ) );
	}

	public function test_render_outputs_number_input(): void {
		$html = $this->field->render(
			[
				'id'    => 'n1',
				'name'  => 'qty',
				'label' => 'Qty',
			]
		);
		$this->assertStringContainsString( 'type="number"', $html );
		$this->assertStringContainsString( 'name="qty"', $html );
	}
}

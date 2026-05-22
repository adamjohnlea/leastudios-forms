<?php
/**
 * Tests for Phone_Field.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Field\Types\Phone_Field;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Field\Types\Phone_Field
 */
class PhoneFieldTest extends TestCase {

	private Phone_Field $field;

	public function set_up(): void {
		parent::set_up();
		$this->field = new Phone_Field();
	}

	public function test_get_type(): void {
		$this->assertSame( 'phone', $this->field->get_type() );
	}

	public function test_get_label_is_not_empty(): void {
		$this->assertNotEmpty( $this->field->get_label() );
	}

	public function test_sanitize_preserves_valid_phone_characters(): void {
		$this->assertSame( '+1 (555) 123-4567', $this->field->sanitize( '+1 (555) 123-4567' ) );
	}

	public function test_validate_required_rejects_empty(): void {
		$this->assertIsString(
			$this->field->validate(
				'',
				[
					'required' => true,
					'label'    => 'Phone',
				]
			)
		);
	}

	public function test_validate_pattern_rejects_non_matching_value(): void {
		$this->assertIsString(
			$this->field->validate(
				'12a',
				[ 'validation' => [ 'pattern' => '^\+?[0-9]+$' ] ]
			)
		);
	}

	public function test_validate_pattern_passes_matching_value(): void {
		$this->assertTrue(
			$this->field->validate(
				'1234',
				[ 'validation' => [ 'pattern' => '^\+?[0-9]+$' ] ]
			)
		);
	}

	public function test_render_outputs_tel_input(): void {
		$html = $this->field->render(
			[
				'id'    => 'x1',
				'name'  => 'fieldname',
				'label' => 'A Label',
			]
		);

		$this->assertStringContainsString( 'type="tel"', $html );
		$this->assertStringContainsString( 'name="fieldname"', $html );
	}
}

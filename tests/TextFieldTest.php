<?php
/**
 * Tests for Text_Field.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Field\Types\Text_Field;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Field\Types\Text_Field
 */
class TextFieldTest extends TestCase {

	private Text_Field $field;

	public function set_up(): void {
		parent::set_up();
		$this->field = new Text_Field();
	}

	public function test_get_type(): void {
		$this->assertSame( 'text', $this->field->get_type() );
	}

	public function test_get_label_is_not_empty(): void {
		$this->assertNotEmpty( $this->field->get_label() );
	}

	public function test_sanitize_trims_whitespace(): void {
		$this->assertSame( 'hi', $this->field->sanitize( ' hi ' ) );
	}

	public function test_validate_required_rejects_empty(): void {
		$this->assertIsString(
			$this->field->validate(
				'',
				[
					'required' => true,
					'label'    => 'Name',
				]
			)
		);
	}

	public function test_validate_optional_allows_empty(): void {
		$this->assertTrue( $this->field->validate( '', [] ) );
	}

	public function test_validate_pattern_rejects_non_matching_value(): void {
		$this->assertIsString(
			$this->field->validate(
				'abc',
				[ 'validation' => [ 'pattern' => '^\d+$' ] ]
			)
		);
	}

	public function test_validate_pattern_passes_matching_value(): void {
		$this->assertTrue(
			$this->field->validate(
				'123',
				[ 'validation' => [ 'pattern' => '^\d+$' ] ]
			)
		);
	}

	public function test_render_outputs_text_input(): void {
		$html = $this->field->render(
			[
				'id'    => 'x1',
				'name'  => 'fieldname',
				'label' => 'A Label',
			]
		);

		$this->assertStringContainsString( 'type="text"', $html );
	}
}

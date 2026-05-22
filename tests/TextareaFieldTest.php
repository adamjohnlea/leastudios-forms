<?php
/**
 * Tests for Textarea_Field.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Field\Types\Textarea_Field;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Field\Types\Textarea_Field
 */
class TextareaFieldTest extends TestCase {

	private Textarea_Field $field;

	public function set_up(): void {
		parent::set_up();
		$this->field = new Textarea_Field();
	}

	public function test_get_type(): void {
		$this->assertSame( 'textarea', $this->field->get_type() );
	}

	public function test_get_label_is_not_empty(): void {
		$this->assertNotEmpty( $this->field->get_label() );
	}

	public function test_sanitize_trims_whitespace(): void {
		$this->assertSame( 'hello', $this->field->sanitize( ' hello ' ) );
	}

	public function test_validate_required_rejects_empty(): void {
		$this->assertIsString(
			$this->field->validate(
				'',
				[
					'required' => true,
					'label'    => 'Message',
				]
			)
		);
	}

	public function test_validate_optional_allows_empty(): void {
		$this->assertTrue( $this->field->validate( '', [] ) );
	}

	public function test_render_outputs_textarea_element(): void {
		$html = $this->field->render(
			[
				'id'    => 'x1',
				'name'  => 'fieldname',
				'label' => 'A Label',
			]
		);

		$this->assertStringContainsString( '<textarea', $html );
	}
}

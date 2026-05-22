<?php
/**
 * Tests for Hidden_Field.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Field\Types\Hidden_Field;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Field\Types\Hidden_Field
 */
class HiddenFieldTest extends TestCase {

	private Hidden_Field $field;

	public function set_up(): void {
		parent::set_up();
		$this->field = new Hidden_Field();
	}

	public function test_get_type(): void {
		$this->assertSame( 'hidden', $this->field->get_type() );
	}

	public function test_get_label_is_not_empty(): void {
		$this->assertNotEmpty( $this->field->get_label() );
	}

	public function test_sanitize_strips_html_tags(): void {
		$this->assertSame( 'x', $this->field->sanitize( '<b>x</b>' ) );
	}

	public function test_validate_required_rejects_empty(): void {
		$this->assertIsString(
			$this->field->validate(
				'',
				[
					'required' => true,
					'label'    => 'Token',
				]
			)
		);
	}

	public function test_validate_optional_allows_empty(): void {
		$this->assertTrue( $this->field->validate( '', [] ) );
	}

	public function test_render_outputs_hidden_input(): void {
		$html = $this->field->render(
			[
				'id'    => 'x1',
				'name'  => 'fieldname',
				'label' => 'A Label',
			]
		);

		$this->assertStringContainsString( 'type="hidden"', $html );
		$this->assertStringContainsString( 'name="fieldname"', $html );
	}
}

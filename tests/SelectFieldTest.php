<?php
/**
 * Tests for Select_Field.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Field\Types\Select_Field;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Field\Types\Select_Field
 */
class SelectFieldTest extends TestCase {

	private Select_Field $field;

	/** @var array<array{value: string, label: string}> */
	private array $options;

	public function set_up(): void {
		parent::set_up();
		$this->field   = new Select_Field();
		$this->options = [
			[
				'value' => 'red',
				'label' => 'Red',
			],
			[
				'value' => 'blue',
				'label' => 'Blue',
			],
		];
	}

	public function test_get_type(): void {
		$this->assertSame( 'select', $this->field->get_type() );
	}

	public function test_get_label_is_not_empty(): void {
		$this->assertNotEmpty( $this->field->get_label() );
	}

	public function test_sanitize_trims_whitespace(): void {
		$this->assertSame( 'a', $this->field->sanitize( ' a ' ) );
	}

	public function test_validate_required_rejects_empty(): void {
		$this->assertIsString(
			$this->field->validate(
				'',
				[
					'required' => true,
					'label'    => 'Color',
					'options'  => $this->options,
				]
			)
		);
	}

	public function test_validate_rejects_value_not_in_options(): void {
		$this->assertIsString(
			$this->field->validate( 'green', [ 'options' => $this->options ] )
		);
	}

	public function test_validate_passes_for_value_in_options(): void {
		$this->assertTrue(
			$this->field->validate( 'red', [ 'options' => $this->options ] )
		);
	}

	public function test_render_outputs_select_element(): void {
		$html = $this->field->render(
			[
				'id'      => 'x1',
				'name'    => 'fieldname',
				'label'   => 'A Label',
				'options' => $this->options,
			]
		);

		$this->assertStringContainsString( '<select', $html );
		$this->assertStringContainsString( 'name="fieldname"', $html );
	}
}

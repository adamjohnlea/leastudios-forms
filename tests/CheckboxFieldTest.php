<?php
/**
 * Tests for Checkbox_Field.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Field\Types\Checkbox_Field;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Field\Types\Checkbox_Field
 */
class CheckboxFieldTest extends TestCase {

	private Checkbox_Field $field;

	/** @var array<array{value: string, label: string}> */
	private array $options;

	public function set_up(): void {
		parent::set_up();
		$this->field   = new Checkbox_Field();
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
		$this->assertSame( 'checkbox', $this->field->get_type() );
	}

	public function test_get_label_is_not_empty(): void {
		$this->assertNotEmpty( $this->field->get_label() );
	}

	public function test_sanitize_array_returns_array(): void {
		$this->assertSame( [ 'a', 'b' ], $this->field->sanitize( [ '<b>a</b>', 'b' ] ) );
	}

	public function test_sanitize_string_returns_string(): void {
		$this->assertIsString( $this->field->sanitize( 'a' ) );
	}

	public function test_validate_required_rejects_empty_array(): void {
		$this->assertIsString(
			$this->field->validate(
				[],
				[
					'required' => true,
					'label'    => 'Colors',
					'options'  => $this->options,
				]
			)
		);
	}

	public function test_validate_required_passes_for_valid_non_empty_array(): void {
		$this->assertTrue(
			$this->field->validate(
				[ 'red' ],
				[
					'required' => true,
					'options'  => $this->options,
				]
			)
		);
	}

	public function test_validate_rejects_array_containing_value_not_in_options(): void {
		$this->assertIsString(
			$this->field->validate(
				[ 'green' ],
				[ 'options' => $this->options ]
			)
		);
	}

	public function test_render_outputs_checkbox_inputs_with_array_name(): void {
		$html = $this->field->render(
			[
				'id'      => 'x1',
				'name'    => 'fieldname',
				'label'   => 'A Label',
				'options' => $this->options,
			]
		);

		$this->assertStringContainsString( 'type="checkbox"', $html );
		$this->assertStringContainsString( '[]', $html );
	}
}

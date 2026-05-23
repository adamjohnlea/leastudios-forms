<?php
/**
 * Tests for Fields_Validator.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Field\Field_Registry;
use LEAStudios\Forms\Form\Fields_Validator;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Form\Fields_Validator
 */
class FieldsValidatorTest extends TestCase {

	private Fields_Validator $validator;

	public function set_up(): void {
		parent::set_up();

		$registry = new Field_Registry();
		$registry->register_defaults();

		$this->validator = new Fields_Validator( $registry );
	}

	public function test_drops_field_with_unknown_type(): void {
		$result = $this->validator->validate(
			[
				[
					'id'   => 'foo',
					'type' => 'totally_made_up',
				],
			]
		);

		$this->assertSame( [], $result );
	}

	public function test_drops_field_missing_type(): void {
		$result = $this->validator->validate(
			[
				[ 'id' => 'foo' ],
			]
		);

		$this->assertSame( [], $result );
	}

	public function test_drops_field_missing_id(): void {
		$result = $this->validator->validate(
			[
				[ 'type' => 'text' ],
			]
		);

		$this->assertSame( [], $result );
	}

	public function test_drops_non_array_entries(): void {
		$result = $this->validator->validate( [ 'not-an-array', 42, null ] );

		$this->assertSame( [], $result );
	}

	public function test_keeps_valid_field_and_normalizes_known_keys(): void {
		$result = $this->validator->validate(
			[
				[
					'id'       => 'email',
					'type'     => 'email',
					'label'    => 'Your email',
					'required' => 1,
				],
			]
		);

		$this->assertCount( 1, $result );
		$this->assertSame( 'email', $result[0]['id'] );
		$this->assertSame( 'email', $result[0]['type'] );
		$this->assertSame( 'email', $result[0]['name'] );
		$this->assertSame( 'Your email', $result[0]['label'] );
		$this->assertTrue( $result[0]['required'] );
		$this->assertSame( '', $result[0]['placeholder'] );
	}

	public function test_normalizes_options_and_drops_non_array_options(): void {
		$result = $this->validator->validate(
			[
				[
					'id'      => 'colour',
					'type'    => 'select',
					'options' => [
						[
							'value' => 'red',
							'label' => 'Red',
						],
						'not-an-array',
						[ 'value' => 'blue' ],
					],
				],
			]
		);

		$this->assertCount( 2, $result[0]['options'] );
		$this->assertSame(
			[
				'value' => 'red',
				'label' => 'Red',
			],
			$result[0]['options'][0]
		);
		$this->assertSame(
			[
				'value' => 'blue',
				'label' => '',
			],
			$result[0]['options'][1]
		);
	}

	public function test_preserves_unknown_keys_for_extension(): void {
		$result = $this->validator->validate(
			[
				[
					'id'             => 'street',
					'type'           => 'address',
					'show_line2'     => false,
					'custom_setting' => 'leave-me-alone',
				],
			]
		);

		$this->assertSame( false, $result[0]['show_line2'] );
		$this->assertSame( 'leave-me-alone', $result[0]['custom_setting'] );
	}

	public function test_uses_id_as_default_name(): void {
		$result = $this->validator->validate(
			[
				[
					'id'   => 'phone_number',
					'type' => 'phone',
				],
			]
		);

		$this->assertSame( 'phone_number', $result[0]['name'] );
	}

	public function test_normalizes_name_so_lookup_keys_match_downstream(): void {
		// Names round-trip through sanitize_key() in Submission_Handler when
		// building the sanitized payload, but Validator looks up the raw
		// $field_config['name']. If a stored name contains uppercase or
		// other characters that sanitize_key alters, the two paths key the
		// data differently and every required field reports as empty.
		// Normalizing here guarantees the stored name is already a safe key.
		$result = $this->validator->validate(
			[
				[
					'id'   => 'f1',
					'type' => 'text',
					'name' => 'First Name',
				],
				[
					'id'   => 'f2',
					'type' => 'email',
					'name' => 'EMAIL',
				],
			]
		);

		// sanitize_key() lowercases and strips anything outside [a-z0-9_-];
		// the inner space in "First Name" is dropped.
		$this->assertSame( 'firstname', $result[0]['name'] );
		$this->assertSame( 'email', $result[1]['name'] );
	}
}

<?php
/**
 * Tests for Email_Field.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Field\Types\Email_Field;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Field\Types\Email_Field
 */
class EmailFieldTest extends TestCase {

	private Email_Field $field;

	public function set_up(): void {
		parent::set_up();
		$this->field = new Email_Field();
	}

	public function test_get_type(): void {
		$this->assertSame( 'email', $this->field->get_type() );
	}

	public function test_get_label_is_not_empty(): void {
		$this->assertNotEmpty( $this->field->get_label() );
	}

	public function test_sanitize_strips_invalid_email_characters(): void {
		$this->assertSame( 'user@example.com', $this->field->sanitize( 'user@exa mple.com' ) );
	}

	public function test_validate_passes_for_valid_email(): void {
		$this->assertTrue( $this->field->validate( 'user@example.com', [] ) );
	}

	public function test_validate_rejects_malformed_email(): void {
		$this->assertIsString( $this->field->validate( 'not-an-email', [] ) );
	}

	public function test_validate_required_rejects_empty(): void {
		$this->assertIsString(
			$this->field->validate(
				'',
				[
					'required' => true,
					'label'    => 'Email',
				]
			)
		);
	}

	public function test_validate_optional_allows_empty(): void {
		$this->assertTrue( $this->field->validate( '', [] ) );
	}

	public function test_render_outputs_email_input(): void {
		$html = $this->field->render(
			[
				'id'    => 'e1',
				'name'  => 'email',
				'label' => 'Email',
			]
		);

		$this->assertStringContainsString( 'type="email"', $html );
		$this->assertStringContainsString( 'name="email"', $html );
	}
}

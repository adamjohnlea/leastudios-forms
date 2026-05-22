<?php
/**
 * Tests for Url_Field.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Field\Types\Url_Field;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Field\Types\Url_Field
 */
class UrlFieldTest extends TestCase {

	private Url_Field $field;

	public function set_up(): void {
		parent::set_up();
		$this->field = new Url_Field();
	}

	public function test_get_type(): void {
		$this->assertSame( 'url', $this->field->get_type() );
	}

	public function test_get_label_is_not_empty(): void {
		$this->assertNotEmpty( $this->field->get_label() );
	}

	public function test_sanitize_returns_clean_url(): void {
		$this->assertSame( 'https://example.com', $this->field->sanitize( 'https://example.com' ) );
	}

	public function test_validate_required_rejects_empty(): void {
		$this->assertIsString(
			$this->field->validate(
				'',
				[
					'required' => true,
					'label'    => 'Website',
				]
			)
		);
	}

	public function test_validate_optional_allows_empty(): void {
		$this->assertTrue( $this->field->validate( '', [] ) );
	}

	public function test_validate_rejects_non_url(): void {
		$this->assertIsString( $this->field->validate( 'not a url', [] ) );
	}

	public function test_validate_passes_for_valid_url(): void {
		$this->assertTrue( $this->field->validate( 'https://example.com', [] ) );
	}

	public function test_render_outputs_url_input(): void {
		$html = $this->field->render(
			[
				'id'    => 'x1',
				'name'  => 'fieldname',
				'label' => 'A Label',
			]
		);

		$this->assertStringContainsString( 'type="url"', $html );
	}
}

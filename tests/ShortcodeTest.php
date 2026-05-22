<?php
/**
 * Tests for Shortcode.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Field\Field_Registry;
use LEAStudios\Forms\Form\Form_Repository;
use LEAStudios\Forms\Render\Form_Renderer;
use LEAStudios\Forms\Render\Shortcode;
use LEAStudios\Forms\Spam\Honeypot;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Render\Shortcode
 */
class ShortcodeTest extends TestCase {

	private Form_Repository $form_repository;

	private Shortcode $shortcode;

	public function set_up(): void {
		parent::set_up();

		$registry = new Field_Registry();
		$registry->register_defaults();

		$this->form_repository = new Form_Repository();
		$renderer              = new Form_Renderer(
			$this->form_repository,
			$registry,
			new Honeypot()
		);
		$this->shortcode       = new Shortcode( $renderer );

		// The plugin bootstrap registers the shortcode on init; clear so
		// register tests prove the class did the registration itself.
		remove_shortcode( 'leastudios_form' );
	}

	public function tear_down(): void {
		remove_shortcode( 'leastudios_form' );
		unset( $_GET['leastudios_forms_errors'] );
		parent::tear_down();
	}

	/**
	 * Build a form with a single 'name' text field.
	 *
	 * @return int
	 */
	private function make_form_with_name_field(): int {
		$id = self::factory()->post->create(
			[
				'post_type'  => 'leastudios_form',
				'post_title' => 'Contact',
			]
		);

		$this->form_repository->save_fields(
			$id,
			[
				[
					'id'    => 'name',
					'name'  => 'name',
					'type'  => 'text',
					'label' => 'Your Name',
				],
			]
		);

		return $id;
	}

	public function test_register_adds_the_shortcode(): void {
		$this->shortcode->register();

		$this->assertTrue( shortcode_exists( 'leastudios_form' ) );
	}

	public function test_handle_returns_empty_string_for_missing_id(): void {
		$this->assertSame( '', $this->shortcode->handle( [] ) );
	}

	public function test_handle_returns_empty_string_for_non_numeric_id(): void {
		$this->assertSame( '', $this->shortcode->handle( [ 'id' => 'not-a-number' ] ) );
	}

	public function test_handle_renders_the_form_for_a_valid_id(): void {
		$id = $this->make_form_with_name_field();

		$html = $this->shortcode->handle( [ 'id' => (string) $id ] );

		$this->assertStringContainsString( '<form', $html );
		$this->assertStringContainsString( 'data-form-id="' . $id . '"', $html );
	}

	public function test_shortcode_output_filter_is_applied(): void {
		$id = $this->make_form_with_name_field();

		add_filter(
			'leastudios_forms_shortcode_output',
			static fn(): string => 'FILTERED OUTPUT'
		);

		$this->assertSame( 'FILTERED OUTPUT', $this->shortcode->handle( [ 'id' => (string) $id ] ) );
	}

	public function test_handle_consumes_and_deletes_no_js_error_transient(): void {
		$id    = $this->make_form_with_name_field();
		$token = 'tok123';

		set_transient( 'leastudios_forms_errors_' . $token, [ 'name' => 'Required field' ], 60 );
		$_GET['leastudios_forms_errors'] = $token;

		$html = $this->shortcode->handle( [ 'id' => (string) $id ] );

		$this->assertStringContainsString( 'Required field', $html );
		$this->assertStringContainsString( 'field-error visible', $html );
		$this->assertFalse( get_transient( 'leastudios_forms_errors_' . $token ) );
	}

	public function test_handle_renders_without_errors_when_token_absent(): void {
		$id = $this->make_form_with_name_field();

		$html = $this->shortcode->handle( [ 'id' => (string) $id ] );

		$this->assertStringContainsString( '<form', $html );
		$this->assertStringNotContainsString( 'field-error visible', $html );
	}
}

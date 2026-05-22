<?php
/**
 * Tests for Block.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Field\Field_Registry;
use LEAStudios\Forms\Form\Form_Repository;
use LEAStudios\Forms\Render\Block;
use LEAStudios\Forms\Render\Form_Renderer;
use LEAStudios\Forms\Spam\Honeypot;
use LEAStudios\Tests\TestCase;
use WP_Block_Type_Registry;

/**
 * @covers \LEAStudios\Forms\Render\Block
 */
class BlockTest extends TestCase {

	private const BLOCK_NAME = 'leastudios-forms/form';

	private Form_Repository $form_repository;

	private Block $block;

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
		$this->block           = new Block( $renderer, $this->form_repository );

		// The plugin bootstrap registers the block on init; tests need a
		// clean registry so calls to register() are not "already registered".
		if ( WP_Block_Type_Registry::get_instance()->is_registered( self::BLOCK_NAME ) ) {
			unregister_block_type( self::BLOCK_NAME );
		}
	}

	public function tear_down(): void {
		if ( WP_Block_Type_Registry::get_instance()->is_registered( self::BLOCK_NAME ) ) {
			unregister_block_type( self::BLOCK_NAME );
		}
		parent::tear_down();
	}

	/**
	 * Create a leastudios_form with a single text field.
	 *
	 * @param string $title Post title.
	 * @return int
	 */
	private function make_form( string $title = 'Block Form' ): int {
		$id = self::factory()->post->create(
			[
				'post_type'   => 'leastudios_form',
				'post_title'  => $title,
				'post_status' => 'publish',
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

	public function test_register_registers_the_block_type(): void {
		$this->block->register();

		$this->assertTrue(
			WP_Block_Type_Registry::get_instance()->is_registered( self::BLOCK_NAME )
		);
	}

	public function test_render_block_returns_empty_when_form_id_is_missing(): void {
		$this->assertSame( '', $this->block->render_block( [] ) );
	}

	public function test_render_block_returns_empty_when_form_id_is_zero(): void {
		$this->assertSame( '', $this->block->render_block( [ 'formId' => 0 ] ) );
	}

	public function test_render_block_renders_the_form_for_a_valid_id(): void {
		$id   = $this->make_form();
		$html = $this->block->render_block( [ 'formId' => $id ] );

		$this->assertStringContainsString( '<form', $html );
		$this->assertStringContainsString( 'data-form-id="' . $id . '"', $html );
	}

	public function test_render_block_coerces_non_numeric_form_id_to_empty(): void {
		$this->assertSame( '', $this->block->render_block( [ 'formId' => 'not-a-number' ] ) );
	}

	public function test_localize_block_data_exposes_forms_to_the_editor_script(): void {
		$this->make_form( 'Localized Form Title' );
		$this->block->register();

		$this->block->localize_block_data();

		$data = wp_scripts()->get_data( 'leastudios-forms-block-editor', 'data' );

		$this->assertIsString( $data );
		$this->assertStringContainsString( 'leastudiosFormsBlock', $data );
		$this->assertStringContainsString( 'Localized Form Title', $data );
	}
}

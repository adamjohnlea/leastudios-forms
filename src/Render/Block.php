<?php
/**
 * Gutenberg block registration.
 *
 * @package LEAStudios\Forms\Render
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Render;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

use LEAStudios\Forms\Form\Form_Repository;

/**
 * Registers the leastudios-forms/form block for the block editor.
 */
class Block {

	/**
	 * Constructor.
	 *
	 * @param Form_Renderer   $renderer        The form renderer.
	 * @param Form_Repository $form_repository The form repository.
	 */
	public function __construct(
		private readonly Form_Renderer $renderer,
		private readonly Form_Repository $form_repository,
	) {}

	/**
	 * Register the block type.
	 *
	 * @return void
	 */
	public function register(): void {
		wp_register_script(
			'leastudios-forms-block-editor',
			LEASTUDIOS_FORMS_URL . 'assets/js/block-editor.js',
			[ 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ],
			LEASTUDIOS_FORMS_VERSION,
			true
		);

		wp_register_style(
			'leastudios-forms-frontend',
			LEASTUDIOS_FORMS_URL . 'assets/css/frontend.css',
			[],
			LEASTUDIOS_FORMS_VERSION
		);

		register_block_type(
			'leastudios-forms/form',
			[
				'attributes'      => [
					'formId' => [
						'type'    => 'number',
						'default' => 0,
					],
				],
				'editor_script'   => 'leastudios-forms-block-editor',
				'editor_style'    => 'leastudios-forms-frontend',
				'style'           => 'leastudios-forms-frontend',
				'render_callback' => [ $this, 'render_block' ],
			]
		);

		add_action( 'enqueue_block_editor_assets', [ $this, 'localize_block_data' ] );
	}

	/**
	 * Pass forms list to the block editor script.
	 *
	 * @return void
	 */
	public function localize_block_data(): void {
		$forms      = $this->form_repository->get_all_forms();
		$forms_data = [];

		foreach ( $forms as $form ) {
			$forms_data[] = [
				'id'    => $form->ID,
				'title' => $form->post_title,
			];
		}

		wp_localize_script(
			'leastudios-forms-block-editor',
			'leastudiosFormsBlock',
			[ 'forms' => $forms_data ]
		);
	}

	/**
	 * Render the block on the server side.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string The rendered form HTML.
	 */
	public function render_block( array $attributes ): string {
		$form_id = absint( $attributes['formId'] ?? 0 );

		if ( 0 === $form_id ) {
			return '';
		}

		$this->enqueue_assets();

		return $this->renderer->render( $form_id );
	}

	/**
	 * Enqueue frontend CSS and JS assets.
	 *
	 * @return void
	 */
	private function enqueue_assets(): void {
		wp_enqueue_style(
			'leastudios-forms-frontend',
			LEASTUDIOS_FORMS_URL . 'assets/css/frontend.css',
			[],
			LEASTUDIOS_FORMS_VERSION
		);

		wp_enqueue_script(
			'leastudios-forms-frontend',
			LEASTUDIOS_FORMS_URL . 'assets/js/frontend-form.js',
			[],
			LEASTUDIOS_FORMS_VERSION,
			true
		);

		wp_localize_script(
			'leastudios-forms-frontend',
			'leastudiosForms',
			[
				'restUrl'   => rest_url( 'leastudios-forms/v1/submissions' ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
			]
		);
	}
}

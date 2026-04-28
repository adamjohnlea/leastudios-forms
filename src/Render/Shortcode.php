<?php
/**
 * Form shortcode handler.
 *
 * @package LEAStudios\Forms\Render
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Render;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Registers and handles the [leastudios_form] shortcode.
 */
class Shortcode {

	/**
	 * Constructor.
	 *
	 * @param Form_Renderer $renderer The form renderer.
	 */
	public function __construct(
		private readonly Form_Renderer $renderer,
	) {}

	/**
	 * Register the shortcode.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'leastudios_form', [ $this, 'handle' ] );
	}

	/**
	 * Handle the shortcode output.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string The rendered form HTML.
	 */
	public function handle( array $atts ): string {
		$atts = shortcode_atts(
			[ 'id' => 0 ],
			$atts,
			'leastudios_form'
		);

		$form_id = absint( $atts['id'] );

		if ( 0 === $form_id ) {
			return '';
		}

		$this->enqueue_assets( $form_id );

		$html = $this->renderer->render( $form_id );

		/**
		 * Filter final shortcode output HTML.
		 *
		 * @param string $html    The rendered form HTML.
		 * @param int    $form_id The form post ID.
		 * @return string Filtered HTML.
		 */
		return apply_filters( 'leastudios_forms_shortcode_output', $html, $form_id );
	}

	/**
	 * Enqueue frontend CSS and JS assets.
	 *
	 * @param int $form_id The form post ID.
	 * @return void
	 */
	private function enqueue_assets( int $form_id ): void {
		wp_enqueue_style(
			'leastudios-forms-frontend',
			LEASTUDIOS_FORMS_URL . 'assets/css/frontend.css',
			[],
			LEASTUDIOS_FORMS_VERSION
		);

		wp_enqueue_script(
			'leastudios-forms-frontend',
			LEASTUDIOS_FORMS_URL . 'assets/js/frontend.js',
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

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
	 * @param array<string, mixed> $atts Shortcode attributes.
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

		$this->enqueue_assets();

		// Pick up errors stashed by the no-JS fallback handler in
		// Plugin::handle_fallback_submission(). The transient is a
		// single-use error bag keyed by a token in the redirect URL.
		$errors = $this->consume_no_js_errors();

		$html = $this->renderer->render( $form_id, $errors );

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
	 * Read and delete any transient-stashed validation errors left behind
	 * by the no-JS fallback redirect.
	 *
	 * @return array<string, string>
	 */
	private function consume_no_js_errors(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only token consumption; nonce was checked at the original POST.
		$token = isset( $_GET['leastudios_forms_errors'] ) ? sanitize_key( wp_unslash( (string) $_GET['leastudios_forms_errors'] ) ) : '';

		if ( '' === $token ) {
			return [];
		}

		$key    = 'leastudios_forms_errors_' . $token;
		$errors = get_transient( $key );
		delete_transient( $key );

		if ( ! is_array( $errors ) ) {
			return [];
		}

		// Coerce to (string => string) map: caller renders error text per field name.
		$out = [];
		foreach ( $errors as $field_name => $error ) {
			$out[ (string) $field_name ] = is_string( $error ) ? $error : (string) ( $error[0] ?? '' );
		}

		return $out;
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

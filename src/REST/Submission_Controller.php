<?php
/**
 * REST API controller for form submissions.
 *
 * @package LEAStudios\Forms\REST
 */

declare(strict_types=1);

namespace LEAStudios\Forms\REST;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

use LEAStudios\Forms\Submission\Submission_Handler;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Handles POST requests for form submissions via the REST API.
 */
class Submission_Controller extends WP_REST_Controller {

	/**
	 * The REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'leastudios-forms/v1';

	/**
	 * The REST base.
	 *
	 * @var string
	 */
	protected $rest_base = 'submissions';

	/**
	 * Constructor.
	 *
	 * @param Submission_Handler $submission_handler The submission handler.
	 */
	public function __construct(
		private readonly Submission_Handler $submission_handler,
	) {}

	/**
	 * Register the REST route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'create_item_permissions_check' ],
					'args'                => $this->get_endpoint_args(),
				],
			]
		);
	}

	/**
	 * Check permissions for creating a submission.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return bool Always true (public forms).
	 */
	public function create_item_permissions_check( $request ): bool {
		return true;
	}

	/**
	 * Handle the submission request.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response.
	 */
	public function create_item( $request ): WP_REST_Response {
		$form_id = (int) $request->get_param( 'form_id' );

		// Validate nonce.
		$nonce = $request->get_param( '_wpnonce' );

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'leastudios_forms_submit_' . $form_id ) ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Security check failed. Please refresh the page and try again.', 'leastudios-forms' ),
					'errors'  => [],
				],
				403
			);
		}

		$fields         = $request->get_param( 'fields' );
		$honeypot_value = $request->get_param( '_leastudios_forms_hp' ) ?? '';
		$ip             = $this->get_client_ip();
		$user_agent     = $request->get_header( 'user-agent' ) ?? '';
		$user_id        = get_current_user_id() ? get_current_user_id() : null;

		$result = $this->submission_handler->handle(
			$form_id,
			is_array( $fields ) ? $fields : [],
			(string) $honeypot_value,
			$ip,
			$user_agent,
			$user_id
		);

		$status_code = $result['success'] ? 200 : 422;

		return new WP_REST_Response( $result, $status_code );
	}

	/**
	 * Get the endpoint arguments definition.
	 *
	 * @return array The args schema.
	 */
	private function get_endpoint_args(): array {
		return [
			'form_id'              => [
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'validate_callback' => static function ( $value ): bool {
					return is_numeric( $value ) && (int) $value > 0;
				},
			],
			'fields'               => [
				'required' => true,
				'type'     => 'object',
			],
			'_leastudios_forms_hp' => [
				'required'          => false,
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'_wpnonce'             => [
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}

	/**
	 * Get the client IP address.
	 *
	 * @return string The sanitized IP address.
	 */
	private function get_client_ip(): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}

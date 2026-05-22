<?php
/**
 * Tests for Submission_Controller.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Entry\Entry_Repository;
use LEAStudios\Forms\Field\Field_Registry;
use LEAStudios\Forms\Form\Form_Repository;
use LEAStudios\Forms\Form\Form_Settings;
use LEAStudios\Forms\Notification\Email_Notifier;
use LEAStudios\Forms\REST\Submission_Controller;
use LEAStudios\Forms\Spam\Honeypot;
use LEAStudios\Forms\Spam\Rate_Limiter;
use LEAStudios\Forms\Submission\Submission_Handler;
use LEAStudios\Forms\Submission\Validator;
use LEAStudios\Tests\TestCase;
use WP_REST_Request;

/**
 * @covers \LEAStudios\Forms\REST\Submission_Controller
 */
class SubmissionControllerTest extends TestCase {

	private Submission_Controller $controller;

	private Form_Repository $form_repo;

	public function set_up(): void {
		parent::set_up();

		$field_registry = new Field_Registry();
		$field_registry->register_defaults();

		$this->form_repo = new Form_Repository();

		$handler = new Submission_Handler(
			new Validator( $field_registry ),
			new Entry_Repository(),
			new Email_Notifier(),
			new Honeypot(),
			new Rate_Limiter(),
			$this->form_repo,
			$field_registry
		);

		$this->controller = new Submission_Controller( $handler );
	}

	/**
	 * Create a leastudios_form post with the given fields.
	 *
	 * @param array<int, array<string, mixed>> $fields Field configurations.
	 * @return int The new form post ID.
	 */
	private function create_form( array $fields ): int {
		$form_id = self::factory()->post->create(
			[
				'post_type'  => 'leastudios_form',
				'post_title' => 'REST Test Form',
			]
		);

		$this->form_repo->save_fields( $form_id, $fields );
		$this->form_repo->save_settings( $form_id, new Form_Settings() );

		return $form_id;
	}

	/**
	 * Build a POST request for the submissions endpoint.
	 *
	 * @param array<string, mixed> $params Body params.
	 * @return WP_REST_Request
	 */
	private function make_request( array $params ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/leastudios-forms/v1/submissions' );
		$request->set_body_params( $params );

		return $request;
	}

	public function test_permissions_check_is_public(): void {
		$this->assertTrue(
			$this->controller->create_item_permissions_check( $this->make_request( [] ) )
		);
	}

	public function test_rejects_missing_nonce_with_403(): void {
		$form_id = $this->create_form(
			[
				[
					'name' => 'message',
					'type' => 'text',
				],
			]
		);

		$response = $this->controller->create_item(
			$this->make_request(
				[
					'form_id'              => $form_id,
					'fields'               => [ 'message' => 'Hi' ],
					'_leastudios_forms_hp' => '',
				]
			)
		);

		$this->assertSame( 403, $response->get_status() );
		$this->assertFalse( $response->get_data()['success'] );
	}

	public function test_rejects_invalid_nonce_with_403(): void {
		$form_id = $this->create_form(
			[
				[
					'name' => 'message',
					'type' => 'text',
				],
			]
		);

		$response = $this->controller->create_item(
			$this->make_request(
				[
					'form_id'              => $form_id,
					'fields'               => [ 'message' => 'Hi' ],
					'_leastudios_forms_hp' => '',
					'_wpnonce'             => 'not-a-real-nonce',
				]
			)
		);

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_returns_200_on_successful_submission(): void {
		$form_id = $this->create_form(
			[
				[
					'name' => 'message',
					'type' => 'text',
				],
			]
		);

		$response = $this->controller->create_item(
			$this->make_request(
				[
					'form_id'              => $form_id,
					'fields'               => [ 'message' => 'Hello' ],
					'_leastudios_forms_hp' => '',
					'_wpnonce'             => wp_create_nonce( 'leastudios_forms_submit_' . $form_id ),
				]
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
	}

	public function test_returns_422_on_validation_failure(): void {
		$form_id = $this->create_form(
			[
				[
					'name'     => 'email',
					'type'     => 'email',
					'label'    => 'Email',
					'required' => true,
				],
			]
		);

		$response = $this->controller->create_item(
			$this->make_request(
				[
					'form_id'              => $form_id,
					'fields'               => [ 'email' => '' ],
					'_leastudios_forms_hp' => '',
					'_wpnonce'             => wp_create_nonce( 'leastudios_forms_submit_' . $form_id ),
				]
			)
		);

		$this->assertSame( 422, $response->get_status() );
		$this->assertArrayHasKey( 'email', $response->get_data()['errors'] );
	}

	public function test_absent_honeypot_field_is_treated_as_spam(): void {
		$form_id = $this->create_form(
			[
				[
					'name' => 'message',
					'type' => 'text',
				],
			]
		);

		// Note: no _leastudios_forms_hp key in the body at all.
		$response = $this->controller->create_item(
			$this->make_request(
				[
					'form_id'  => $form_id,
					'fields'   => [ 'message' => 'Hello' ],
					'_wpnonce' => wp_create_nonce( 'leastudios_forms_submit_' . $form_id ),
				]
			)
		);

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'Spam detected.', $response->get_data()['message'] );
	}
}

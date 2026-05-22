<?php
/**
 * Tests for Form_Editor::save_post.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Admin\Form_Editor;
use LEAStudios\Forms\Field\Field_Registry;
use LEAStudios\Forms\Form\Fields_Validator;
use LEAStudios\Forms\Form\Form_Repository;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Admin\Form_Editor
 */
class FormEditorTest extends TestCase {

	private const NONCE_FIELD  = '_leastudios_forms_nonce';
	private const NONCE_ACTION = 'leastudios_forms_save_form';

	private Form_Repository $form_repository;

	private Form_Editor $editor;

	private int $form_id;

	public function set_up(): void {
		parent::set_up();

		$registry = new Field_Registry();
		$registry->register_defaults();

		$this->form_repository = new Form_Repository();
		$this->editor          = new Form_Editor(
			$this->form_repository,
			$registry,
			new Fields_Validator( $registry )
		);

		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin );

		$this->form_id = self::factory()->post->create(
			[
				'post_type'  => 'leastudios_form',
				'post_title' => 'Edit Me',
			]
		);
	}

	public function tear_down(): void {
		unset(
			$_POST[ self::NONCE_FIELD ],
			$_POST['leastudios_forms_fields_data'],
			$_POST['leastudios_forms_settings_data']
		);
		parent::tear_down();
	}

	/**
	 * Stash a valid nonce in $_POST so save_post() passes its first guard.
	 */
	private function set_valid_nonce(): void {
		$_POST[ self::NONCE_FIELD ] = wp_create_nonce( self::NONCE_ACTION );
	}

	public function test_save_post_does_nothing_without_a_nonce(): void {
		$_POST['leastudios_forms_fields_data'] = wp_json_encode(
			[
				[
					'id'   => 'name',
					'name' => 'name',
					'type' => 'text',
				],
			]
		);

		$this->editor->save_post( $this->form_id );

		$this->assertSame( [], $this->form_repository->get_fields( $this->form_id ) );
	}

	public function test_save_post_does_nothing_with_an_invalid_nonce(): void {
		$_POST[ self::NONCE_FIELD ]            = 'not-a-real-nonce';
		$_POST['leastudios_forms_fields_data'] = wp_json_encode(
			[
				[
					'id'   => 'name',
					'name' => 'name',
					'type' => 'text',
				],
			]
		);

		$this->editor->save_post( $this->form_id );

		$this->assertSame( [], $this->form_repository->get_fields( $this->form_id ) );
	}

	public function test_save_post_does_nothing_when_user_lacks_capability(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$this->set_valid_nonce();
		$_POST['leastudios_forms_fields_data'] = wp_json_encode(
			[
				[
					'id'   => 'name',
					'name' => 'name',
					'type' => 'text',
				],
			]
		);

		$this->editor->save_post( $this->form_id );

		$this->assertSame( [], $this->form_repository->get_fields( $this->form_id ) );
	}

	public function test_save_post_persists_validated_fields(): void {
		$this->set_valid_nonce();
		$_POST['leastudios_forms_fields_data'] = wp_json_encode(
			[
				[
					'id'    => 'name',
					'name'  => 'name',
					'type'  => 'text',
					'label' => 'Your Name',
				],
				[
					'id'    => 'email',
					'name'  => 'email',
					'type'  => 'email',
					'label' => 'Your Email',
				],
			]
		);

		$this->editor->save_post( $this->form_id );

		$stored = $this->form_repository->get_fields( $this->form_id );
		$this->assertCount( 2, $stored );
		$this->assertSame( 'name', $stored[0]['id'] );
		$this->assertSame( 'email', $stored[1]['id'] );
	}

	public function test_save_post_drops_fields_with_unknown_type(): void {
		$this->set_valid_nonce();
		$_POST['leastudios_forms_fields_data'] = wp_json_encode(
			[
				[
					'id'   => 'good',
					'name' => 'good',
					'type' => 'text',
				],
				[
					'id'   => 'bad',
					'name' => 'bad',
					'type' => 'not-a-real-type',
				],
			]
		);

		$this->editor->save_post( $this->form_id );

		$stored = $this->form_repository->get_fields( $this->form_id );
		$this->assertCount( 1, $stored );
		$this->assertSame( 'good', $stored[0]['id'] );
	}

	public function test_save_post_ignores_non_array_fields_payload(): void {
		$this->set_valid_nonce();
		$_POST['leastudios_forms_fields_data'] = 'this is not valid JSON {';

		$this->editor->save_post( $this->form_id );

		$this->assertSame( [], $this->form_repository->get_fields( $this->form_id ) );
	}

	public function test_save_post_persists_settings_from_json(): void {
		$this->set_valid_nonce();
		$_POST['leastudios_forms_settings_data'] = wp_json_encode(
			[
				'success_message'    => 'Got it, thanks',
				'submit_button_text' => 'Send Inquiry',
				'spam_protection'    => [
					'honeypot'          => false,
					'rate_limit'        => 12,
					'rate_limit_window' => 90,
				],
			]
		);

		$this->editor->save_post( $this->form_id );

		$settings = $this->form_repository->get_settings( $this->form_id );
		$this->assertSame( 'Got it, thanks', $settings->success_message );
		$this->assertSame( 'Send Inquiry', $settings->submit_button_text );
		$this->assertFalse( $settings->honeypot_enabled );
		$this->assertSame( 12, $settings->rate_limit );
		$this->assertSame( 90, $settings->rate_limit_window );
	}
}

<?php
/**
 * Tests for Form_Renderer.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Field\Field_Registry;
use LEAStudios\Forms\Form\Form_Repository;
use LEAStudios\Forms\Form\Form_Settings;
use LEAStudios\Forms\Render\Form_Renderer;
use LEAStudios\Forms\Spam\Honeypot;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Render\Form_Renderer
 */
class FormRendererTest extends TestCase {

	private Form_Repository $form_repository;

	private Form_Renderer $renderer;

	public function set_up(): void {
		parent::set_up();

		$registry = new Field_Registry();
		$registry->register_defaults();

		$this->form_repository = new Form_Repository();
		$this->renderer        = new Form_Renderer(
			$this->form_repository,
			$registry,
			new Honeypot()
		);
	}

	/**
	 * Create a leastudios_form post with fields and (optionally) settings.
	 *
	 * @param array<int, array<string, mixed>>|null $fields   Field configurations.
	 * @param Form_Settings|null                    $settings Form settings to save.
	 * @return int
	 */
	private function make_form( ?array $fields = null, ?Form_Settings $settings = null ): int {
		$id = self::factory()->post->create(
			[
				'post_type'  => 'leastudios_form',
				'post_title' => 'Test Form',
			]
		);

		$this->form_repository->save_fields(
			$id,
			$fields ?? [
				[
					'id'    => 'name',
					'name'  => 'name',
					'type'  => 'text',
					'label' => 'Your Name',
				],
			]
		);

		if ( null !== $settings ) {
			$this->form_repository->save_settings( $id, $settings );
		}

		return $id;
	}

	public function test_render_returns_comment_when_form_not_found(): void {
		$this->assertStringContainsString( 'form not found', $this->renderer->render( 999999 ) );
	}

	public function test_render_outputs_form_tag_with_id_attribute(): void {
		$id   = $this->make_form();
		$html = $this->renderer->render( $id );

		$this->assertStringContainsString( '<form', $html );
		$this->assertStringContainsString( 'id="leastudios-form-' . $id . '"', $html );
		$this->assertStringContainsString( 'data-form-id="' . $id . '"', $html );
	}

	public function test_render_includes_hidden_form_id_input(): void {
		$id   = $this->make_form();
		$html = $this->renderer->render( $id );

		$this->assertMatchesRegularExpression(
			'/<input[^>]+name="form_id"[^>]+value="' . $id . '"/',
			$html
		);
	}

	public function test_render_includes_nonce_field(): void {
		$html = $this->renderer->render( $this->make_form() );

		$this->assertStringContainsString( 'name="_wpnonce"', $html );
	}

	public function test_render_outputs_each_configured_field(): void {
		$id = $this->make_form(
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

		$html = $this->renderer->render( $id );

		$this->assertStringContainsString( 'data-field-name="name"', $html );
		$this->assertStringContainsString( 'data-field-name="email"', $html );
	}

	public function test_render_skips_field_with_unknown_type(): void {
		$id = $this->make_form(
			[
				[
					'name' => 'mystery',
					'type' => 'totally-bogus-type',
				],
			]
		);

		$html = $this->renderer->render( $id );

		$this->assertStringNotContainsString( 'data-field-name="mystery"', $html );
	}

	public function test_render_skips_field_with_empty_name(): void {
		$id = $this->make_form(
			[
				[
					'name' => '',
					'type' => 'text',
				],
			]
		);

		$html = $this->renderer->render( $id );

		$this->assertStringNotContainsString( 'data-field-name=""', $html );
	}

	public function test_render_includes_honeypot_when_enabled(): void {
		$html = $this->renderer->render( $this->make_form() );

		$this->assertStringContainsString( '_leastudios_forms_hp', $html );
	}

	public function test_render_omits_honeypot_when_disabled(): void {
		$id = $this->make_form( null, new Form_Settings( honeypot_enabled: false ) );

		$html = $this->renderer->render( $id );

		$this->assertStringNotContainsString( '_leastudios_forms_hp', $html );
	}

	public function test_render_marks_field_error_visible_when_error_present(): void {
		$id   = $this->make_form();
		$html = $this->renderer->render( $id, [ 'name' => 'This field is required' ] );

		$this->assertStringContainsString( 'field-error visible', $html );
		$this->assertStringContainsString( 'This field is required', $html );
	}

	public function test_render_error_span_not_visible_without_error(): void {
		$html = $this->renderer->render( $this->make_form() );

		$this->assertStringNotContainsString( 'field-error visible', $html );
		$this->assertStringContainsString( 'class="field-error', $html );
	}

	public function test_render_repopulates_old_values_into_fields(): void {
		$id   = $this->make_form();
		$html = $this->renderer->render( $id, [], [ 'name' => 'Ada Lovelace' ] );

		$this->assertStringContainsString( 'value="Ada Lovelace"', $html );
	}

	public function test_render_uses_submit_button_text_from_settings(): void {
		$id = $this->make_form( null, new Form_Settings( submit_button_text: 'Send Inquiry' ) );

		$html = $this->renderer->render( $id );

		$this->assertStringContainsString( '>Send Inquiry<', $html );
	}

	public function test_render_rejects_invalid_form_attribute_name(): void {
		add_filter(
			'leastudios_forms_form_attributes',
			static function ( array $attrs ): array {
				$attrs['onclick=alert(1) data-x'] = 'pwn';
				return $attrs;
			}
		);

		$html = $this->renderer->render( $this->make_form() );

		$this->assertStringNotContainsString( 'onclick', $html );
		$this->assertStringNotContainsString( 'pwn', $html );
	}

	public function test_form_attributes_filter_can_add_valid_attribute(): void {
		add_filter(
			'leastudios_forms_form_attributes',
			static function ( array $attrs ): array {
				$attrs['data-test'] = 'yes';
				return $attrs;
			}
		);

		$html = $this->renderer->render( $this->make_form() );

		$this->assertStringContainsString( 'data-test="yes"', $html );
	}

	public function test_render_field_filter_can_replace_field_html(): void {
		add_filter(
			'leastudios_forms_render_field',
			static fn(): string => '<div data-field-name="replaced">FILTERED</div>'
		);

		$html = $this->renderer->render( $this->make_form() );

		$this->assertStringContainsString( 'FILTERED', $html );
		$this->assertStringContainsString( 'data-field-name="replaced"', $html );
	}

	public function test_before_render_action_fires(): void {
		$fired = false;
		add_action(
			'leastudios_forms_before_render',
			static function () use ( &$fired ): void {
				$fired = true;
			}
		);

		$this->renderer->render( $this->make_form() );

		$this->assertTrue( $fired );
	}

	public function test_after_render_action_fires(): void {
		$fired = false;
		add_action(
			'leastudios_forms_after_render',
			static function () use ( &$fired ): void {
				$fired = true;
			}
		);

		$this->renderer->render( $this->make_form() );

		$this->assertTrue( $fired );
	}
}

<?php
/**
 * Tests for Submission_Handler.
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
use LEAStudios\Forms\Spam\Honeypot;
use LEAStudios\Forms\Spam\Rate_Limiter;
use LEAStudios\Forms\Submission\Submission_Handler;
use LEAStudios\Forms\Submission\Validator;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Submission\Submission_Handler
 */
class SubmissionHandlerTest extends TestCase {

	private Submission_Handler $handler;

	private Form_Repository $form_repo;

	private Entry_Repository $entry_repo;

	public function set_up(): void {
		parent::set_up();

		$field_registry = new Field_Registry();
		$field_registry->register_defaults();

		$this->form_repo  = new Form_Repository();
		$this->entry_repo = new Entry_Repository();

		$this->handler = new Submission_Handler(
			new Validator( $field_registry ),
			$this->entry_repo,
			new Email_Notifier(),
			new Honeypot(),
			new Rate_Limiter(),
			$this->form_repo,
			$field_registry
		);
	}

	/**
	 * Create a leastudios_form post with the given fields and settings.
	 *
	 * @param array<int, array<string, mixed>> $fields   Field configurations.
	 * @param Form_Settings|null               $settings Optional form settings.
	 * @return int The new form post ID.
	 */
	private function create_form( array $fields, ?Form_Settings $settings = null ): int {
		$form_id = self::factory()->post->create(
			[
				'post_type'  => 'leastudios_form',
				'post_title' => 'Test Form',
			]
		);

		$this->form_repo->save_fields( $form_id, $fields );
		$this->form_repo->save_settings( $form_id, $settings ?? new Form_Settings() );

		return $form_id;
	}

	public function test_rejects_honeypot_spam(): void {
		$form_id = $this->create_form(
			[
				[
					'name' => 'message',
					'type' => 'text',
				],
			]
		);

		$result = $this->handler->handle(
			$form_id,
			[ 'message' => 'Hello' ],
			'i am a bot',
			'203.0.113.1',
			'PHPUnit',
			null
		);

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'Spam detected.', $result['message'] );
	}

	public function test_rejects_unknown_form(): void {
		$result = $this->handler->handle(
			999999,
			[ 'message' => 'Hello' ],
			'',
			'203.0.113.2',
			'PHPUnit',
			null
		);

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'Form not found.', $result['message'] );
	}
}

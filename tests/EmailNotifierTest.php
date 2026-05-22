<?php
/**
 * Tests for Email_Notifier.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Notification\Email_Notifier;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Notification\Email_Notifier
 */
class EmailNotifierTest extends TestCase {

	private Email_Notifier $notifier;

	private int $form_id;

	public function set_up(): void {
		parent::set_up();
		reset_phpmailer_instance();
		$this->notifier = new Email_Notifier();
		$this->form_id  = self::factory()->post->create( [ 'post_title' => 'Contact Form' ] );
	}

	/**
	 * Build a notification config with sensible defaults.
	 *
	 * @param array<string, string> $overrides Keys to override.
	 * @return array<string, string>
	 */
	private function notification( array $overrides = [] ): array {
		return array_merge(
			[
				'to'      => 'admin@example.org',
				'subject' => 'New submission',
				'message' => 'Hello',
			],
			$overrides
		);
	}

	/**
	 * The MockPHPMailer instance the WP test suite swaps in for wp_mail().
	 *
	 * @return \MockPHPMailer
	 */
	private function mailer() {
		return tests_retrieve_phpmailer_instance();
	}

	public function test_send_dispatches_one_email_per_notification(): void {
		$this->notifier->send(
			$this->form_id,
			1,
			[],
			[
				$this->notification(),
				$this->notification( [ 'to' => 'second@example.org' ] ),
			]
		);

		$this->assertCount( 2, $this->mailer()->mock_sent );
	}

	public function test_send_skips_notification_with_empty_recipient(): void {
		$this->notifier->send( $this->form_id, 1, [], [ $this->notification( [ 'to' => '' ] ) ] );

		$this->assertCount( 0, $this->mailer()->mock_sent );
	}

	public function test_send_uses_html_content_type(): void {
		$this->notifier->send( $this->form_id, 1, [], [ $this->notification() ] );

		$this->assertStringContainsString( 'text/html', $this->mailer()->get_sent()->header );
	}

	public function test_form_title_merge_tag_is_resolved(): void {
		$this->notifier->send(
			$this->form_id,
			1,
			[],
			[ $this->notification( [ 'message' => 'Submitted via {form_title}' ] ) ]
		);

		$this->assertStringContainsString( 'Submitted via Contact Form', $this->mailer()->get_sent()->body );
	}

	public function test_admin_email_and_site_name_merge_tags_are_resolved(): void {
		$expected = get_option( 'blogname' ) . ' / ' . get_option( 'admin_email' );

		$this->notifier->send(
			$this->form_id,
			1,
			[],
			[ $this->notification( [ 'message' => '{site_name} / {admin_email}' ] ) ]
		);

		$this->assertStringContainsString( $expected, $this->mailer()->get_sent()->body );
	}

	public function test_field_merge_tag_is_resolved(): void {
		$this->notifier->send(
			$this->form_id,
			1,
			[ 'name' => 'Ada Lovelace' ],
			[ $this->notification( [ 'message' => 'From {field:name}' ] ) ]
		);

		$this->assertStringContainsString( 'From Ada Lovelace', $this->mailer()->get_sent()->body );
	}

	public function test_unknown_field_merge_tag_is_left_intact(): void {
		$this->notifier->send(
			$this->form_id,
			1,
			[],
			[ $this->notification( [ 'message' => 'Value: {field:missing}' ] ) ]
		);

		$this->assertStringContainsString( 'Value: {field:missing}', $this->mailer()->get_sent()->body );
	}

	public function test_prefixed_field_names_resolve_independently(): void {
		$this->notifier->send(
			$this->form_id,
			1,
			[
				'email'     => 'primary@example.org',
				'email_alt' => 'backup@example.org',
			],
			[ $this->notification( [ 'message' => '{field:email}|{field:email_alt}' ] ) ]
		);

		$this->assertStringContainsString( 'primary@example.org|backup@example.org', $this->mailer()->get_sent()->body );
	}

	public function test_array_field_value_is_imploded(): void {
		$this->notifier->send(
			$this->form_id,
			1,
			[ 'colors' => [ 'red', 'blue' ] ],
			[ $this->notification( [ 'message' => 'Picked {field:colors}' ] ) ]
		);

		$this->assertStringContainsString( 'Picked red, blue', $this->mailer()->get_sent()->body );
	}

	public function test_all_fields_renders_an_html_table_in_the_body(): void {
		$this->notifier->send(
			$this->form_id,
			1,
			[
				'full_name' => 'Ada',
				'email'     => 'ada@example.org',
			],
			[ $this->notification( [ 'message' => '{all_fields}' ] ) ]
		);

		$body = $this->mailer()->get_sent()->body;
		$this->assertStringContainsString( '<table', $body );
		$this->assertStringContainsString( 'Full Name', $body );
		$this->assertStringContainsString( 'ada@example.org', $body );
	}

	public function test_all_fields_is_blank_in_the_subject_context(): void {
		$captured = [];
		add_filter(
			'leastudios_forms_notification_args',
			static function ( array $args ) use ( &$captured ): array {
				$captured = $args;
				return $args;
			}
		);

		$this->notifier->send(
			$this->form_id,
			1,
			[ 'name' => 'Ada' ],
			[ $this->notification( [ 'subject' => 'Re: {all_fields}' ] ) ]
		);

		$this->assertSame( 'Re: ', $captured['subject'] );
	}

	public function test_html_context_escapes_field_values(): void {
		$this->notifier->send(
			$this->form_id,
			1,
			[ 'name' => '<script>alert(1)</script>' ],
			[ $this->notification( [ 'message' => '{field:name}' ] ) ]
		);

		$body = $this->mailer()->get_sent()->body;
		$this->assertStringContainsString( '&lt;script&gt;', $body );
		$this->assertStringNotContainsString( '<script>', $body );
	}

	public function test_subject_strips_crlf_from_field_values(): void {
		$captured = [];
		add_filter(
			'leastudios_forms_notification_args',
			static function ( array $args ) use ( &$captured ): array {
				$captured = $args;
				return $args;
			}
		);

		$this->notifier->send(
			$this->form_id,
			1,
			[ 'name' => "Ada\r\nBcc: evil@example.org" ],
			[ $this->notification( [ 'subject' => '{field:name}' ] ) ]
		);

		$this->assertStringNotContainsString( "\r", $captured['subject'] );
		$this->assertStringNotContainsString( "\n", $captured['subject'] );
	}

	public function test_recipient_strips_crlf_from_field_values(): void {
		$captured = [];
		add_filter(
			'leastudios_forms_notification_args',
			static function ( array $args ) use ( &$captured ): array {
				$captured = $args;
				return $args;
			}
		);

		$this->notifier->send(
			$this->form_id,
			1,
			[ 'reply' => "visitor@example.org\r\nBcc: evil@example.org" ],
			[ $this->notification( [ 'to' => '{field:reply}' ] ) ]
		);

		$this->assertStringNotContainsString( "\r", $captured['to'] );
		$this->assertStringNotContainsString( "\n", $captured['to'] );
	}

	public function test_reply_to_header_added_when_value_is_a_valid_email(): void {
		$this->notifier->send(
			$this->form_id,
			1,
			[ 'email' => 'visitor@example.org' ],
			[ $this->notification( [ 'reply_to' => '{field:email}' ] ) ]
		);

		$this->assertStringContainsString( 'visitor@example.org', $this->mailer()->get_sent()->header );
	}

	public function test_reply_to_header_omitted_when_value_is_not_an_email(): void {
		$this->notifier->send(
			$this->form_id,
			1,
			[ 'email' => 'not-an-email' ],
			[ $this->notification( [ 'reply_to' => '{field:email}' ] ) ]
		);

		$this->assertStringNotContainsString( 'Reply-To:', $this->mailer()->get_sent()->header );
	}

	public function test_notification_message_filter_is_applied(): void {
		add_filter( 'leastudios_forms_notification_message', static fn() => 'filtered body content' );

		$this->notifier->send( $this->form_id, 1, [], [ $this->notification() ] );

		$this->assertStringContainsString( 'filtered body content', $this->mailer()->get_sent()->body );
	}

	public function test_notification_args_filter_can_override_the_recipient(): void {
		add_filter(
			'leastudios_forms_notification_args',
			static function ( array $args ): array {
				$args['to'] = 'override@example.org';
				return $args;
			}
		);

		$this->notifier->send( $this->form_id, 1, [], [ $this->notification() ] );

		$this->assertSame( 'override@example.org', $this->mailer()->get_sent()->to[0][0] );
	}

	public function test_ses_message_id_is_captured_and_returned(): void {
		add_filter(
			'wp_mail',
			static function ( array $args ): array {
				do_action( 'leastudios_mailer_email_sent', [ 'message_id' => 'ses-message-123' ] );
				return $args;
			}
		);

		$ids = $this->notifier->send( $this->form_id, 1, [], [ $this->notification() ] );

		$this->assertSame( [ 'ses-message-123' ], $ids );
	}

	public function test_send_returns_empty_array_when_no_message_id_is_emitted(): void {
		$ids = $this->notifier->send( $this->form_id, 1, [], [ $this->notification() ] );

		$this->assertSame( [], $ids );
	}

	public function test_missing_form_yields_an_empty_form_title(): void {
		$this->notifier->send(
			999999,
			1,
			[],
			[ $this->notification( [ 'message' => 'Title:[{form_title}]' ] ) ]
		);

		$this->assertStringContainsString( 'Title:[]', $this->mailer()->get_sent()->body );
	}

	public function test_all_fields_table_collapses_an_address_array(): void {
		$this->notifier->send(
			$this->form_id,
			1,
			[
				'address' => [
					'line1' => '1 Main St',
					'line2' => '',
					'city'  => 'Springfield',
				],
			],
			[ $this->notification( [ 'message' => '{all_fields}' ] ) ]
		);

		$this->assertStringContainsString( '1 Main St, Springfield', $this->mailer()->get_sent()->body );
	}
}

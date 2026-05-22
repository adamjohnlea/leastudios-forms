<?php
/**
 * Tests for Form_Settings.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Form\Form_Settings;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Form\Form_Settings
 */
class FormSettingsTest extends TestCase {

	public function test_defaults(): void {
		$settings = new Form_Settings();

		$this->assertSame( 'Thank you for your submission.', $settings->success_message );
		$this->assertSame( '', $settings->redirect_url );
		$this->assertSame( [], $settings->notifications );
		$this->assertTrue( $settings->honeypot_enabled );
		$this->assertSame( 5, $settings->rate_limit );
		$this->assertSame( 60, $settings->rate_limit_window );
		$this->assertSame( 'Submit', $settings->submit_button_text );
	}

	public function test_from_json_returns_defaults_for_invalid_json(): void {
		$settings = Form_Settings::from_json( 'not-json' );

		$this->assertSame( 'Thank you for your submission.', $settings->success_message );
		$this->assertSame( 5, $settings->rate_limit );
	}

	public function test_from_json_parses_all_fields(): void {
		$json = wp_json_encode(
			[
				'success_message'    => 'Cheers',
				'redirect_url'       => 'https://example.com/thanks',
				'submit_button_text' => 'Send',
				'spam_protection'    => [
					'honeypot'          => false,
					'rate_limit'        => 12,
					'rate_limit_window' => 120,
				],
			]
		);

		$settings = Form_Settings::from_json( $json );

		$this->assertSame( 'Cheers', $settings->success_message );
		$this->assertSame( 'https://example.com/thanks', $settings->redirect_url );
		$this->assertSame( 'Send', $settings->submit_button_text );
		$this->assertFalse( $settings->honeypot_enabled );
		$this->assertSame( 12, $settings->rate_limit );
		$this->assertSame( 120, $settings->rate_limit_window );
	}

	public function test_from_json_drops_notifications_without_a_to_address(): void {
		$json = wp_json_encode(
			[
				'notifications' => [
					[
						'to'       => 'admin@example.com',
						'subject'  => 'New',
						'message'  => 'Body',
						'reply_to' => '',
					],
					[
						'to'       => '',
						'subject'  => 'Skip me',
						'message'  => '',
						'reply_to' => '',
					],
				],
			]
		);

		$settings = Form_Settings::from_json( $json );

		$this->assertCount( 1, $settings->notifications );
		$this->assertSame( 'admin@example.com', $settings->notifications[0]['to'] );
	}

	public function test_to_array_nests_spam_protection(): void {
		$array = ( new Form_Settings( rate_limit: 8, rate_limit_window: 90, honeypot_enabled: false ) )->to_array();

		$this->assertSame( 8, $array['spam_protection']['rate_limit'] );
		$this->assertSame( 90, $array['spam_protection']['rate_limit_window'] );
		$this->assertFalse( $array['spam_protection']['honeypot'] );
	}

	public function test_to_array_round_trips_through_from_json(): void {
		$original = new Form_Settings(
			success_message: 'Round trip',
			rate_limit: 7,
			submit_button_text: 'Go',
		);

		$restored = Form_Settings::from_json( (string) wp_json_encode( $original->to_array() ) );

		$this->assertSame( 'Round trip', $restored->success_message );
		$this->assertSame( 7, $restored->rate_limit );
		$this->assertSame( 'Go', $restored->submit_button_text );
	}
}

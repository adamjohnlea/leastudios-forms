<?php
/**
 * Tests for Settings_Page.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Admin\Settings_Page;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Admin\Settings_Page
 */
class SettingsPageTest extends TestCase {

	private Settings_Page $settings;

	public function set_up(): void {
		parent::set_up();
		$this->settings = new Settings_Page();
	}

	public function test_sanitize_email_normalises_input(): void {
		$out = $this->settings->sanitize_options(
			[ 'notification_email' => '  Owner@Example.ORG  ' ]
		);

		$this->assertSame( 'Owner@Example.ORG', $out['notification_email'] );
	}

	public function test_sanitize_strips_invalid_email(): void {
		$out = $this->settings->sanitize_options(
			[ 'notification_email' => 'definitely not an email' ]
		);

		$this->assertSame( '', $out['notification_email'] );
	}

	public function test_sanitize_coerces_retention_days_to_non_negative_int(): void {
		$negative    = $this->settings->sanitize_options( [ 'entry_retention_days' => '-15' ] );
		$non_numeric = $this->settings->sanitize_options( [ 'entry_retention_days' => 'twelve' ] );

		$this->assertSame( 15, $negative['entry_retention_days'] );
		$this->assertSame( 0, $non_numeric['entry_retention_days'] );
	}

	public function test_sanitize_honeypot_is_true_when_present(): void {
		$out = $this->settings->sanitize_options( [ 'honeypot_enabled' => '1' ] );

		$this->assertTrue( $out['honeypot_enabled'] );
	}

	public function test_sanitize_honeypot_is_false_when_absent(): void {
		$out = $this->settings->sanitize_options( [] );

		$this->assertFalse( $out['honeypot_enabled'] );
	}

	public function test_sanitize_rate_limit_clamps_to_minimum_one(): void {
		$out = $this->settings->sanitize_options( [ 'rate_limit' => '0' ] );

		$this->assertSame( 1, $out['rate_limit'] );
	}

	public function test_sanitize_rate_limit_window_clamps_to_minimum_one(): void {
		$out = $this->settings->sanitize_options( [ 'rate_limit_window' => '0' ] );

		$this->assertSame( 1, $out['rate_limit_window'] );
	}

	public function test_sanitize_falls_back_to_defaults_when_input_missing(): void {
		$out = $this->settings->sanitize_options( [] );

		$this->assertSame( get_option( 'admin_email' ), $out['notification_email'] );
		$this->assertSame( 90, $out['entry_retention_days'] );
		$this->assertSame( 5, $out['rate_limit'] );
		$this->assertSame( 60, $out['rate_limit_window'] );
	}

	public function test_register_settings_registers_the_option(): void {
		$this->settings->register_settings();

		$registered = get_registered_settings();
		$this->assertArrayHasKey( Settings_Page::OPTION_NAME, $registered );
		$this->assertSame( 'array', $registered[ Settings_Page::OPTION_NAME ]['type'] );
	}
}

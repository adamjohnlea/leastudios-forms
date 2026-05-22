<?php
/**
 * Tests for Suite_Detector.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Suite\Suite_Detector;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Suite\Suite_Detector
 */
class SuiteDetectorTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		update_option( 'active_plugins', [] );
	}

	public function tear_down(): void {
		update_option( 'active_plugins', [] );
		parent::tear_down();
	}

	public function test_is_active_returns_false_for_unknown_slug(): void {
		$this->assertFalse( Suite_Detector::is_active( 'not-a-suite-plugin' ) );
	}

	public function test_is_active_returns_false_when_plugin_is_inactive(): void {
		$this->assertFalse( Suite_Detector::is_active( 'leastudios-mailer' ) );
	}

	public function test_is_active_returns_true_when_plugin_is_active(): void {
		update_option( 'active_plugins', [ 'leastudios-mailer/leastudios-mailer.php' ] );

		$this->assertTrue( Suite_Detector::is_active( 'leastudios-mailer' ) );
	}

	public function test_get_active_suite_plugins_returns_empty_when_none_are_active(): void {
		$this->assertSame( [], Suite_Detector::get_active_suite_plugins() );
	}

	public function test_get_active_suite_plugins_returns_active_subset(): void {
		update_option(
			'active_plugins',
			[
				'leastudios-mailer/leastudios-mailer.php',
				'some-unrelated-plugin/some-unrelated-plugin.php',
			]
		);

		$this->assertSame( [ 'leastudios-mailer' ], Suite_Detector::get_active_suite_plugins() );
	}

	public function test_get_active_suite_plugins_returns_all_when_all_active(): void {
		update_option(
			'active_plugins',
			[
				'leastudios-mailer/leastudios-mailer.php',
				'leastudios-forms/leastudios-forms.php',
			]
		);

		$this->assertSame(
			[ 'leastudios-mailer', 'leastudios-forms' ],
			Suite_Detector::get_active_suite_plugins()
		);
	}
}

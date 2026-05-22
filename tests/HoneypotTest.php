<?php
/**
 * Tests for Honeypot.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Spam\Honeypot;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Spam\Honeypot
 */
class HoneypotTest extends TestCase {

	private Honeypot $honeypot;

	public function set_up(): void {
		parent::set_up();
		$this->honeypot = new Honeypot();
	}

	public function test_is_spam_when_field_is_absent(): void {
		$this->assertTrue( $this->honeypot->is_spam( null ) );
	}

	public function test_is_spam_when_field_has_a_value(): void {
		$this->assertTrue( $this->honeypot->is_spam( 'i am a bot' ) );
	}

	public function test_not_spam_when_field_is_empty_string(): void {
		$this->assertFalse( $this->honeypot->is_spam( '' ) );
	}

	public function test_render_outputs_honeypot_field(): void {
		$html = $this->honeypot->render();

		$this->assertStringContainsString( 'name="_leastudios_forms_hp"', $html );
		$this->assertStringContainsString( 'type="text"', $html );
		$this->assertStringContainsString( 'tabindex="-1"', $html );
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
		$this->assertStringContainsString( 'autocomplete="off"', $html );
	}
}

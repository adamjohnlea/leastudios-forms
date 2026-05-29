<?php
/**
 * Tests for Mailer_Integration.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Integration\Mailer_Integration;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Integration\Mailer_Integration
 */
class MailerIntegrationTest extends TestCase {

	private Mailer_Integration $integration;

	public function set_up(): void {
		parent::set_up();

		$this->integration = new Mailer_Integration();
	}

	public function tear_down(): void {
		remove_all_filters( 'leastudios_mailer_delivery_status' );

		unset( $GLOBALS['current_screen'] );

		parent::tear_down();
	}

	public function test_init_does_nothing_outside_admin(): void {
		unset( $GLOBALS['current_screen'] );
		$fired = false;
		add_action(
			'leastudios_forms_mailer_integration_init',
			static function () use ( &$fired ): void {
				$fired = true;
			}
		);

		$this->integration->init();

		$this->assertFalse( $fired );
		$this->assertFalse( has_filter( 'leastudios_forms_delivery_status' ) );
	}

	public function test_init_fires_action_in_admin_with_self_as_argument(): void {
		set_current_screen( 'dashboard' );
		$received = null;
		add_action(
			'leastudios_forms_mailer_integration_init',
			static function ( $instance ) use ( &$received ): void {
				$received = $instance;
			}
		);

		$this->integration->init();

		$this->assertSame( $this->integration, $received );
	}

	public function test_init_registers_the_forms_delivery_status_filter_in_admin(): void {
		set_current_screen( 'dashboard' );

		$this->integration->init();

		$this->assertNotFalse( has_filter( 'leastudios_forms_delivery_status', [ $this->integration, 'filter_delivery_status' ] ) );
	}

	public function test_filter_delivery_status_renders_badge_from_mailer_lookup(): void {
		// Stand in for the mailer answering its own public lookup filter.
		add_filter(
			'leastudios_mailer_delivery_status',
			static function ( $status, string $message_id ) {
				if ( 'ses-aaa' === $message_id ) {
					return [
						'status'        => 'delivered',
						'error_message' => '',
					];
				}

				return $status;
			},
			10,
			2
		);

		$html = $this->integration->filter_delivery_status( 'Sent', 'ses-aaa' );

		$this->assertStringContainsString( '#00a32a', $html );
		$this->assertStringContainsString( '>Delivered<', $html );
	}

	public function test_filter_delivery_status_returns_default_when_message_unknown(): void {
		// Mailer answers but does not recognise this message ID.
		add_filter(
			'leastudios_mailer_delivery_status',
			static fn( $status ) => $status,
			10,
			2
		);

		$html = $this->integration->filter_delivery_status( 'Sent', 'ses-missing' );

		$this->assertSame( 'Sent', $html );
	}

	public function test_filter_delivery_status_returns_default_when_mailer_absent(): void {
		// No mailer filter registered at all (sibling plugin inactive path).
		$html = $this->integration->filter_delivery_status( 'Sent', 'ses-anything' );

		$this->assertSame( 'Sent', $html );
	}

	public function test_render_status_badge_uses_status_specific_colour(): void {
		$html = Mailer_Integration::render_status_badge( 'delivered' );

		$this->assertStringContainsString( '#00a32a', $html );
		$this->assertStringContainsString( '>Delivered<', $html );
	}

	public function test_render_status_badge_falls_back_to_grey_for_unknown_status(): void {
		$html = Mailer_Integration::render_status_badge( 'mystery' );

		$this->assertStringContainsString( '#888888', $html );
		$this->assertStringContainsString( '>Mystery<', $html );
	}

	public function test_render_status_badge_escapes_the_status_label(): void {
		$html = Mailer_Integration::render_status_badge( '<script>x</script>' );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}
}

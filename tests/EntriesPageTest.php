<?php
/**
 * Tests for Entries_Page::handle_actions.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Admin\Entries_Page;
use LEAStudios\Forms\Entry\Entry_Repository;
use LEAStudios\Forms\Entry\Entry_Status;
use LEAStudios\Forms\Form\Form_Repository;
use LEAStudios\Tests\TestCase;
use RuntimeException;
use WPDieException;

/**
 * @covers \LEAStudios\Forms\Admin\Entries_Page
 */
class EntriesPageTest extends TestCase {

	private Entry_Repository $entry_repository;

	private Entries_Page $page;

	public function set_up(): void {
		parent::set_up();

		$this->entry_repository = new Entry_Repository();
		$this->page             = new Entries_Page( $this->entry_repository, new Form_Repository() );
	}

	public function tear_down(): void {
		unset(
			$_GET['page'],
			$_GET['action'],
			$_GET['entry_id'],
			$_REQUEST['_wpnonce'],
			$_POST['_wpnonce'],
			$_POST['action'],
			$_POST['action2'],
			$_POST['entry_ids']
		);
		parent::tear_down();
	}

	/**
	 * Replace wp_redirect with a throw so we can inspect state after a
	 * handler that would otherwise wp_safe_redirect + exit.
	 */
	private function intercept_redirect(): void {
		add_filter(
			'wp_redirect',
			static function ( string $location ): string {
				throw new RuntimeException( esc_html( 'REDIRECTED:' . $location ) );
			}
		);
	}

	/**
	 * Become an administrator.
	 */
	private function as_admin(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	/**
	 * Insert an entry and return its id.
	 */
	private function make_entry(): int {
		return $this->entry_repository->create( 1, [ 'name' => 'Ada' ], null, null, null );
	}

	public function test_returns_silently_when_not_on_entries_page(): void {
		$_GET['page']     = 'some-other-page';
		$_GET['action']   = 'delete';
		$entry_id         = $this->make_entry();
		$_GET['entry_id'] = $entry_id;

		$this->page->handle_actions();

		$this->assertNotNull( $this->entry_repository->get_entry( $entry_id ) );
	}

	public function test_dies_when_user_lacks_capability_for_single_action(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$_GET['page']     = 'leastudios-forms-entries';
		$_GET['action']   = 'delete';
		$_GET['entry_id'] = $this->make_entry();

		$this->expectException( WPDieException::class );
		$this->page->handle_actions();
	}

	public function test_delete_action_removes_the_entry(): void {
		$this->as_admin();
		$entry_id             = $this->make_entry();
		$_GET['page']         = 'leastudios-forms-entries';
		$_GET['action']       = 'delete';
		$_GET['entry_id']     = $entry_id;
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'leastudios_forms_entry_action' );

		$this->intercept_redirect();

		try {
			$this->page->handle_actions();
			$this->fail( 'Expected a redirect.' );
		} catch ( RuntimeException $e ) {
			$this->assertStringStartsWith( 'REDIRECTED:', $e->getMessage() );
		}

		$this->assertNull( $this->entry_repository->get_entry( $entry_id ) );
	}

	public function test_mark_read_action_updates_status(): void {
		$this->as_admin();
		$entry_id             = $this->make_entry();
		$_GET['page']         = 'leastudios-forms-entries';
		$_GET['action']       = 'mark_read';
		$_GET['entry_id']     = $entry_id;
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'leastudios_forms_entry_action' );

		$this->intercept_redirect();

		try {
			$this->page->handle_actions();
			$this->fail( 'Expected a redirect.' );
		} catch ( RuntimeException $e ) {
			$this->assertStringStartsWith( 'REDIRECTED:', $e->getMessage() );
		}

		$row = $this->entry_repository->get_entry( $entry_id );
		$this->assertNotNull( $row );
		$this->assertSame( Entry_Status::Read->value, $row->status );
	}

	public function test_bulk_action_does_nothing_without_a_nonce(): void {
		$this->as_admin();
		$entry_id           = $this->make_entry();
		$_GET['page']       = 'leastudios-forms-entries';
		$_POST['action']    = 'delete';
		$_POST['entry_ids'] = [ $entry_id ];

		$this->page->handle_actions();

		$this->assertNotNull( $this->entry_repository->get_entry( $entry_id ) );
	}

	public function test_bulk_action_does_nothing_without_capability(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$entry_id           = $this->make_entry();
		$_GET['page']       = 'leastudios-forms-entries';
		$_POST['_wpnonce']  = wp_create_nonce( 'bulk-entries' );
		$_POST['action']    = 'delete';
		$_POST['entry_ids'] = [ $entry_id ];

		$this->page->handle_actions();

		$this->assertNotNull( $this->entry_repository->get_entry( $entry_id ) );
	}

	public function test_bulk_delete_removes_selected_entries(): void {
		$this->as_admin();
		$entry_a = $this->make_entry();
		$entry_b = $this->make_entry();
		$entry_c = $this->make_entry();

		$_GET['page']       = 'leastudios-forms-entries';
		$_POST['_wpnonce']  = wp_create_nonce( 'bulk-entries' );
		$_POST['action']    = 'delete';
		$_POST['entry_ids'] = [ $entry_a, $entry_c ];

		$this->intercept_redirect();

		try {
			$this->page->handle_actions();
			$this->fail( 'Expected a redirect.' );
		} catch ( RuntimeException $e ) {
			$this->assertStringStartsWith( 'REDIRECTED:', $e->getMessage() );
		}

		$this->assertNull( $this->entry_repository->get_entry( $entry_a ) );
		$this->assertNotNull( $this->entry_repository->get_entry( $entry_b ) );
		$this->assertNull( $this->entry_repository->get_entry( $entry_c ) );
	}

	public function test_bulk_action_uses_action2_when_action_is_minus_one(): void {
		$this->as_admin();
		$entry_id = $this->make_entry();

		$_GET['page']       = 'leastudios-forms-entries';
		$_POST['_wpnonce']  = wp_create_nonce( 'bulk-entries' );
		$_POST['action']    = '-1';
		$_POST['action2']   = 'mark_read';
		$_POST['entry_ids'] = [ $entry_id ];

		$this->intercept_redirect();

		try {
			$this->page->handle_actions();
			$this->fail( 'Expected a redirect.' );
		} catch ( RuntimeException $e ) {
			$this->assertStringStartsWith( 'REDIRECTED:', $e->getMessage() );
		}

		$row = $this->entry_repository->get_entry( $entry_id );
		$this->assertNotNull( $row );
		$this->assertSame( Entry_Status::Read->value, $row->status );
	}
}

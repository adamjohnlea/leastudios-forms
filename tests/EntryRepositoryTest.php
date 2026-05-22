<?php
/**
 * Tests for Entry_Repository.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Database\Migration;
use LEAStudios\Forms\Entry\Entry_Repository;
use LEAStudios\Forms\Entry\Entry_Status;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Entry\Entry_Repository
 */
class EntryRepositoryTest extends TestCase {

	private Entry_Repository $repo;

	public function set_up(): void {
		parent::set_up();
		$this->repo = new Entry_Repository();
	}

	public function test_create_persists_row_and_returns_insert_id(): void {
		$id = $this->repo->create( 101, [ 'name' => 'Ada' ], '203.0.113.1', 'PHPUnit', 7 );

		$this->assertGreaterThan( 0, $id );

		$row = $this->repo->get_entry( $id );
		$this->assertNotNull( $row );
		$this->assertSame( 101, (int) $row->form_id );
		$this->assertSame( '203.0.113.1', $row->ip_address );
		$this->assertSame( 7, (int) $row->user_id );
		$this->assertSame( Entry_Status::Unread->value, $row->status );
		$this->assertSame( [ 'name' => 'Ada' ], json_decode( $row->field_data, true ) );
	}

	public function test_create_truncates_user_agent_to_255_chars(): void {
		$id  = $this->repo->create( 1, [], null, str_repeat( 'x', 400 ), null );
		$row = $this->repo->get_entry( $id );

		$this->assertSame( 255, mb_strlen( $row->user_agent ) );
	}

	public function test_get_entry_returns_null_for_missing_id(): void {
		$this->assertNull( $this->repo->get_entry( 99999999 ) );
	}

	public function test_get_entries_filters_by_form_id(): void {
		$this->repo->create( 10, [], null, null, null );
		$this->repo->create( 10, [], null, null, null );
		$this->repo->create( 20, [], null, null, null );

		$this->assertCount( 2, $this->repo->get_entries( 1, 50, 10 ) );
		$this->assertCount( 1, $this->repo->get_entries( 1, 50, 20 ) );
	}

	public function test_get_entries_filters_by_status(): void {
		$read = $this->repo->create( 30, [], null, null, null );
		$this->repo->create( 30, [], null, null, null );
		$this->repo->update_status( $read, Entry_Status::Read->value );

		$this->assertCount( 1, $this->repo->get_entries( 1, 50, 30, Entry_Status::Read->value ) );
		$this->assertCount( 1, $this->repo->get_entries( 1, 50, 30, Entry_Status::Unread->value ) );
	}

	public function test_get_entries_paginates_newest_first(): void {
		global $wpdb;
		$table = Migration::get_table_name();

		$old = $this->repo->create( 40, [ 'n' => 'old' ], null, null, null );
		$mid = $this->repo->create( 40, [ 'n' => 'mid' ], null, null, null );
		$new = $this->repo->create( 40, [ 'n' => 'new' ], null, null, null );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $table, [ 'created_at' => '2020-01-01 00:00:00' ], [ 'id' => $old ] );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $table, [ 'created_at' => '2021-01-01 00:00:00' ], [ 'id' => $mid ] );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $table, [ 'created_at' => '2022-01-01 00:00:00' ], [ 'id' => $new ] );

		$page1 = $this->repo->get_entries( 1, 2, 40 );
		$page2 = $this->repo->get_entries( 2, 2, 40 );

		$this->assertCount( 2, $page1 );
		$this->assertCount( 1, $page2 );
		$this->assertSame( $new, (int) $page1[0]->id );
		$this->assertSame( $mid, (int) $page1[1]->id );
		$this->assertSame( $old, (int) $page2[0]->id );
	}

	public function test_get_total_count_with_and_without_filters(): void {
		$this->repo->create( 50, [], null, null, null );
		$this->repo->create( 50, [], null, null, null );
		$this->repo->create( 60, [], null, null, null );

		$this->assertSame( 3, $this->repo->get_total_count() );
		$this->assertSame( 2, $this->repo->get_total_count( 50 ) );
		$this->assertSame( 1, $this->repo->get_total_count( 60 ) );
	}

	public function test_update_status_changes_status(): void {
		$id = $this->repo->create( 1, [], null, null, null );

		$this->assertTrue( $this->repo->update_status( $id, Entry_Status::Trashed->value ) );
		$this->assertSame( Entry_Status::Trashed->value, $this->repo->get_entry( $id )->status );
	}

	public function test_update_message_ids_stores_json(): void {
		$id = $this->repo->create( 1, [], null, null, null );

		$this->assertTrue( $this->repo->update_message_ids( $id, [ 'ses-1', 'ses-2' ] ) );
		$this->assertSame(
			[ 'ses-1', 'ses-2' ],
			json_decode( $this->repo->get_entry( $id )->notification_message_ids, true )
		);
	}

	public function test_delete_entry_removes_row(): void {
		$id = $this->repo->create( 1, [], null, null, null );

		$this->assertTrue( $this->repo->delete_entry( $id ) );
		$this->assertNull( $this->repo->get_entry( $id ) );
	}

	public function test_delete_old_entries_removes_only_aged_rows(): void {
		global $wpdb;
		$table = Migration::get_table_name();

		$old   = $this->repo->create( 70, [], null, null, null );
		$fresh = $this->repo->create( 70, [], null, null, null );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $table, [ 'created_at' => '2000-01-01 00:00:00' ], [ 'id' => $old ] );

		$deleted = $this->repo->delete_old_entries( 30 );

		$this->assertSame( 1, $deleted );
		$this->assertNull( $this->repo->get_entry( $old ) );
		$this->assertNotNull( $this->repo->get_entry( $fresh ) );
	}

	public function test_get_entries_for_export_returns_form_rows_newest_first(): void {
		global $wpdb;
		$table = Migration::get_table_name();

		$a = $this->repo->create( 80, [ 'n' => 'a' ], null, null, null );
		$b = $this->repo->create( 80, [ 'n' => 'b' ], null, null, null );
		$this->repo->create( 90, [ 'n' => 'other' ], null, null, null );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $table, [ 'created_at' => '2020-01-01 00:00:00' ], [ 'id' => $a ] );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $table, [ 'created_at' => '2021-01-01 00:00:00' ], [ 'id' => $b ] );

		$rows = $this->repo->get_entries_for_export( 80 );

		$this->assertCount( 2, $rows );
		$this->assertSame( $b, (int) $rows[0]->id );
		$this->assertSame( $a, (int) $rows[1]->id );
	}
}

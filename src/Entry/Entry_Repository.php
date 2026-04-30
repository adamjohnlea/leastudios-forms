<?php
/**
 * Entry data access.
 *
 * @package LEAStudios\Forms\Entry
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Entry;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

use LEAStudios\Forms\Database\Migration;

/**
 * CRUD operations for form entries.
 */
class Entry_Repository {

	/**
	 * Create a new entry.
	 *
	 * @param int                  $form_id    The form post ID.
	 * @param array<string, mixed> $field_data The submitted field data, keyed by field name.
	 * @param string|null          $ip         The submitter's IP address.
	 * @param string|null          $user_agent The submitter's user agent.
	 * @param int|null             $user_id    The submitter's user ID.
	 * @return int The new entry ID.
	 */
	public function create( int $form_id, array $field_data, ?string $ip, ?string $user_agent, ?int $user_id ): int {
		global $wpdb;

		$table = Migration::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			[
				'form_id'    => $form_id,
				'field_data' => wp_json_encode( $field_data ),
				'status'     => Entry_Status::Unread->value,
				'ip_address' => $ip,
				'user_agent' => $user_agent ? mb_substr( $user_agent, 0, 255 ) : null,
				'user_id'    => $user_id,
				'created_at' => current_time( 'mysql', true ),
				'updated_at' => current_time( 'mysql', true ),
			],
			[ '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get a single entry by ID.
	 *
	 * @param int $id The entry ID.
	 * @return object|null The entry row or null.
	 */
	public function get_entry( int $id ): ?object {
		global $wpdb;

		$table = Migration::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE id = %d",
				$id
			)
		);

		return $row ? $row : null;
	}

	/**
	 * Get paginated entries.
	 *
	 * @param int         $page     Current page number.
	 * @param int         $per_page Number of entries per page.
	 * @param int|null    $form_id  Optional form ID filter.
	 * @param string|null $status   Optional status filter.
	 * @return array<int, \stdClass> Wpdb-hydrated entry objects.
	 */
	public function get_entries( int $page, int $per_page, ?int $form_id = null, ?string $status = null ): array {
		global $wpdb;

		$table  = Migration::get_table_name();
		$where  = [];
		$values = [];

		if ( null !== $form_id ) {
			$where[]  = 'form_id = %d';
			$values[] = $form_id;
		}

		if ( null !== $status ) {
			$where[]  = 'status = %s';
			$values[] = $status;
		}

		$where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$offset       = ( $page - 1 ) * $per_page;
		$values[]     = $per_page;
		$values[]     = $offset;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare --$where_clause carries 0/1/2 dynamic placeholders, $values matches.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				...$values
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	}

	/**
	 * Get total entry count.
	 *
	 * @param int|null    $form_id Optional form ID filter.
	 * @param string|null $status  Optional status filter.
	 * @return int Total count.
	 */
	public function get_total_count( ?int $form_id = null, ?string $status = null ): int {
		global $wpdb;

		$table  = Migration::get_table_name();
		$where  = [];
		$values = [];

		if ( null !== $form_id ) {
			$where[]  = 'form_id = %d';
			$values[] = $form_id;
		}

		if ( null !== $status ) {
			$where[]  = 'status = %s';
			$values[] = $status;
		}

		$where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		if ( ! empty( $values ) ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare --$where_clause carries 1+ dynamic placeholders, $values matches.
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} {$where_clause}",
					...$values
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE 1 = %d", 1 ) );
	}

	/**
	 * Update an entry's status.
	 *
	 * @param int    $id     The entry ID.
	 * @param string $status The new status.
	 * @return bool Whether the update succeeded.
	 */
	public function update_status( int $id, string $status ): bool {
		global $wpdb;

		$table = Migration::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			[ 'status' => $status ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Update an entry's notification message IDs.
	 *
	 * @param int                $id          The entry ID.
	 * @param array<int, string> $message_ids List of SES message IDs persisted as JSON.
	 * @return bool Whether the update succeeded.
	 */
	public function update_message_ids( int $id, array $message_ids ): bool {
		global $wpdb;

		$table = Migration::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			[ 'notification_message_ids' => wp_json_encode( $message_ids ) ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Delete an entry.
	 *
	 * @param int $id The entry ID.
	 * @return bool Whether the deletion succeeded.
	 */
	public function delete_entry( int $id ): bool {
		global $wpdb;

		$table = Migration::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$table,
			[ 'id' => $id ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Delete entries older than a given number of days.
	 *
	 * @param int $days Number of days to retain.
	 * @return int Number of entries deleted.
	 */
	public function delete_old_entries( int $days ): int {
		global $wpdb;

		$table  = Migration::get_table_name();
		$cutoff = ( new \DateTimeImmutable( "-{$days} days", new \DateTimeZone( 'UTC' ) ) )->format( 'Y-m-d H:i:s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE FROM {$table} WHERE created_at < %s",
				$cutoff
			)
		);

		return (int) $result;
	}

	/**
	 * Get all entries for a form (for export).
	 *
	 * @param int $form_id The form post ID.
	 * @return array<int, \stdClass> Wpdb-hydrated entry objects.
	 */
	public function get_entries_for_export( int $form_id ): array {
		global $wpdb;

		$table = Migration::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE form_id = %d ORDER BY created_at DESC",
				$form_id
			)
		);
	}
}

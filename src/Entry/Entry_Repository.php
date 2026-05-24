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
				'SELECT * FROM %i WHERE id = %d',
				$table,
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
		$offset = ( $page - 1 ) * $per_page;

		// Each filter combination is enumerated as a fully-static prepare() format string so the
		// WHERE fragment is never interpolated, satisfying Plugin Check's stricter DB-interpolation sniff.
		if ( null !== $form_id && null !== $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE form_id = %d AND status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d',
					$table,
					$form_id,
					$status,
					$per_page,
					$offset
				)
			);
		}

		if ( null !== $form_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE form_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d',
					$table,
					$form_id,
					$per_page,
					$offset
				)
			);
		}

		if ( null !== $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d',
					$table,
					$status,
					$per_page,
					$offset
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY created_at DESC LIMIT %d OFFSET %d',
				$table,
				$per_page,
				$offset
			)
		);
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

		$table = Migration::get_table_name();

		// Each filter combination is enumerated as a fully-static prepare() format string so the
		// WHERE fragment is never interpolated, satisfying Plugin Check's stricter DB-interpolation sniff.
		if ( null !== $form_id && null !== $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE form_id = %d AND status = %s',
					$table,
					$form_id,
					$status
				)
			);
		}

		if ( null !== $form_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE form_id = %d',
					$table,
					$form_id
				)
			);
		}

		if ( null !== $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE status = %s',
					$table,
					$status
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
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
				'DELETE FROM %i WHERE created_at < %s',
				$table,
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
				'SELECT * FROM %i WHERE form_id = %d ORDER BY created_at DESC',
				$table,
				$form_id
			)
		);
	}
}

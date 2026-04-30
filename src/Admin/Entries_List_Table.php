<?php
/**
 * Entries list table.
 *
 * @package LEAStudios\Forms\Admin
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Admin;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

use LEAStudios\Forms\Entry\Entry_Repository;
use LEAStudios\Forms\Entry\Entry_Status;
use LEAStudios\Forms\Form\Form_Repository;
use LEAStudios\Forms\Shared\Datetime_Util;
use WP_List_Table;

// Load WP_List_Table if not already available.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table for form entries.
 */
class Entries_List_Table extends WP_List_Table {

	/**
	 * The entry repository.
	 *
	 * @var Entry_Repository
	 */
	private Entry_Repository $entry_repository;

	/**
	 * The form repository.
	 *
	 * @var Form_Repository
	 */
	private Form_Repository $form_repository;

	/**
	 * Constructor.
	 *
	 * @param Entry_Repository $entry_repository The entry repository.
	 * @param Form_Repository  $form_repository  The form repository.
	 */
	public function __construct( Entry_Repository $entry_repository, Form_Repository $form_repository ) {
		$this->entry_repository = $entry_repository;
		$this->form_repository  = $form_repository;

		parent::__construct(
			[
				'singular' => 'entry',
				'plural'   => 'entries',
				'ajax'     => false,
			]
		);
	}

	/**
	 * Get the list of columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return [
			'cb'         => '<input type="checkbox" />',
			'form_name'  => __( 'Form', 'leastudios-forms' ),
			'summary'    => __( 'Summary', 'leastudios-forms' ),
			'status'     => __( 'Status', 'leastudios-forms' ),
			'created_at' => __( 'Date', 'leastudios-forms' ),
			'actions'    => __( 'Actions', 'leastudios-forms' ),
		];
	}

	/**
	 * Get the sortable columns.
	 *
	 * @return array<string, array<int, string|bool>>
	 */
	protected function get_sortable_columns(): array {
		return [
			'created_at' => [ 'created_at', true ],
		];
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions(): array {
		return [
			'mark_read'   => __( 'Mark as Read', 'leastudios-forms' ),
			'mark_unread' => __( 'Mark as Unread', 'leastudios-forms' ),
			'delete'      => __( 'Delete', 'leastudios-forms' ),
		];
	}

	/**
	 * Get views (status filter links).
	 *
	 * @return array<string, string>
	 */
	protected function get_views(): array {
		$base_url = admin_url( 'admin.php?page=leastudios-forms-entries' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;

		if ( $form_id > 0 ) {
			$base_url = add_query_arg( 'form_id', $form_id, $base_url );
		}

		$filter_form_id = $form_id > 0 ? $form_id : null;

		$total_count  = $this->entry_repository->get_total_count( $filter_form_id );
		$unread_count = $this->entry_repository->get_total_count( $filter_form_id, Entry_Status::Unread->value );
		$read_count   = $this->entry_repository->get_total_count( $filter_form_id, Entry_Status::Read->value );

		$views = [];

		$views['all'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
			esc_url( $base_url ),
			'' === $current_status ? 'current' : '',
			esc_html__( 'All', 'leastudios-forms' ),
			$total_count
		);

		$views['unread'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
			esc_url( add_query_arg( 'status', 'unread', $base_url ) ),
			'unread' === $current_status ? 'current' : '',
			esc_html__( 'Unread', 'leastudios-forms' ),
			$unread_count
		);

		$views['read'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
			esc_url( add_query_arg( 'status', 'read', $base_url ) ),
			'read' === $current_status ? 'current' : '',
			esc_html__( 'Read', 'leastudios-forms' ),
			$read_count
		);

		return $views;
	}

	/**
	 * Prepare items for display.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$per_page = 20;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_page = $this->get_pagenum();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';

		$filter_form_id = $form_id > 0 ? $form_id : null;
		$filter_status  = '' !== $status ? $status : null;

		$this->items = $this->entry_repository->get_entries(
			$current_page,
			$per_page,
			$filter_form_id,
			$filter_status
		);

		$total_items = $this->entry_repository->get_total_count( $filter_form_id, $filter_status );

		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			]
		);

		$this->_column_headers = [
			$this->get_columns(),
			[],
			$this->get_sortable_columns(),
		];
	}

	/**
	 * Render the checkbox column.
	 *
	 * @param object $item The current entry.
	 * @return string
	 */
	protected function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="entry_ids[]" value="%d" />',
			absint( $item->id )
		);
	}

	/**
	 * Render the form name column.
	 *
	 * @param object $item The current entry.
	 * @return string
	 */
	protected function column_form_name( object $item ): string {
		$form = $this->form_repository->get_form( (int) $item->form_id );

		return esc_html( $form ? $form->post_title : __( 'Unknown', 'leastudios-forms' ) );
	}

	/**
	 * Render the summary column with first 2-3 field values truncated.
	 *
	 * @param object $item The current entry.
	 * @return string
	 */
	protected function column_summary( object $item ): string {
		$field_data = json_decode( $item->field_data, true );

		if ( ! is_array( $field_data ) || empty( $field_data ) ) {
			return '<em>' . esc_html__( 'No data', 'leastudios-forms' ) . '</em>';
		}

		$parts = [];
		$count = 0;
		$max   = 3;

		foreach ( $field_data as $value ) {
			if ( $count >= $max ) {
				break;
			}

			$display = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
			$display = wp_trim_words( $display, 5, '...' );

			if ( '' !== $display ) {
				$parts[] = esc_html( $display );
				++$count;
			}
		}

		return implode( ' &mdash; ', $parts );
	}

	/**
	 * Render the status column with a colored badge.
	 *
	 * @param object $item The current entry.
	 * @return string
	 */
	protected function column_status( object $item ): string {
		$status = Entry_Status::tryFrom( $item->status ?? '' );
		$label  = $status ? $status->label() : esc_html( $item->status );

		$colors = [
			'unread'  => '#2271b1',
			'read'    => '#787c82',
			'trashed' => '#d63638',
		];

		$color = $colors[ $item->status ] ?? '#787c82';

		return sprintf(
			'<span class="leastudios-forms-status-badge" style="background-color: %s; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 12px;">%s</span>',
			esc_attr( $color ),
			esc_html( $label )
		);
	}

	/**
	 * Render the created_at column.
	 *
	 * @param object $item The current entry.
	 * @return string
	 */
	protected function column_created_at( object $item ): string {
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		return esc_html( Datetime_Util::format_for_display( $item->created_at ?? null, $format ) );
	}

	/**
	 * Render the actions column.
	 *
	 * @param object $item The current entry.
	 * @return string
	 */
	protected function column_actions( object $item ): string {
		// Nonce-protect the view link so the "auto mark as read on view"
		// side-effect cannot be triggered by link-prefetchers, security
		// scanners, or third-party crawlers that visit admin URLs without
		// an authenticated user click. Without the nonce, render_entry_detail
		// still shows the entry but skips the write.
		$view_url = wp_nonce_url(
			admin_url(
				sprintf(
					'admin.php?page=leastudios-forms-entries&action=view&entry_id=%d',
					absint( $item->id )
				)
			),
			'leastudios_forms_view_entry'
		);

		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( $view_url ),
			esc_html__( 'View', 'leastudios-forms' )
		);
	}

	/**
	 * Default column rendering fallback.
	 *
	 * @param object $item        The current entry.
	 * @param string $column_name The column name.
	 * @return string
	 */
	protected function column_default( $item, $column_name ): string {
		return isset( $item->$column_name ) ? esc_html( (string) $item->$column_name ) : '';
	}

	/**
	 * Message displayed when there are no entries.
	 *
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No entries found.', 'leastudios-forms' );
	}
}

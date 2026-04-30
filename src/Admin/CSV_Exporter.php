<?php
/**
 * CSV export handler.
 *
 * @package LEAStudios\Forms\Admin
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Admin;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

use LEAStudios\Forms\Entry\Entry_Repository;
use LEAStudios\Forms\Form\Form_Repository;
use LEAStudios\Forms\Shared\Datetime_Util;

/**
 * Handles CSV export of form entries.
 */
class CSV_Exporter {

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
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_post_leastudios_forms_export_csv', [ $this, 'handle_export' ] );
	}

	/**
	 * Handle the CSV export request.
	 *
	 * Verifies nonce and capability, retrieves entries for the given form,
	 * and streams a CSV file to the browser.
	 *
	 * @return void
	 */
	public function handle_export(): void {
		check_admin_referer( 'leastudios_forms_export_csv' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to export entries.', 'leastudios-forms' ) );
		}

		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;

		if ( 0 === $form_id ) {
			wp_die( esc_html__( 'No form specified.', 'leastudios-forms' ) );
		}

		$form = $this->form_repository->get_form( $form_id );

		if ( ! $form ) {
			wp_die( esc_html__( 'Form not found.', 'leastudios-forms' ) );
		}

		$fields  = $this->form_repository->get_fields( $form_id );
		$entries = $this->entry_repository->get_entries_for_export( $form_id );

		// Build column headers from field labels.
		$field_columns = [];
		foreach ( $fields as $field ) {
			if ( isset( $field['name'], $field['label'] ) ) {
				$field_columns[ $field['name'] ] = $field['label'];
			}
		}

		$form_name = sanitize_file_name( $form->post_title );
		$date      = wp_date( 'Y-m-d' );
		$filename  = sprintf( '%s-%s.csv', $form_name, $date );

		// Set headers for CSV download.
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$output = fopen( 'php://output', 'w' );

		if ( false === $output ) {
			wp_die( esc_html__( 'Unable to create export file.', 'leastudios-forms' ) );
		}

		// Write header row.
		$header_row   = array_values( $field_columns );
		$header_row[] = __( 'Date', 'leastudios-forms' );
		$header_row[] = __( 'Status', 'leastudios-forms' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
		fputcsv( $output, $header_row );

		// Write data rows.
		foreach ( $entries as $entry ) {
			$field_data = json_decode( $entry->field_data, true );

			if ( ! is_array( $field_data ) ) {
				$field_data = [];
			}

			$row = [];

			foreach ( array_keys( $field_columns ) as $field_name ) {
				$value = $field_data[ $field_name ] ?? '';

				if ( is_array( $value ) ) {
					$value = implode( ', ', $value );
				}

				$row[] = (string) $value;
			}

			$row[] = Datetime_Util::format_for_display( $entry->created_at, 'Y-m-d H:i:s' );
			$row[] = $entry->status;

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
			fputcsv( $output, $row );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $output );
		exit;
	}
}

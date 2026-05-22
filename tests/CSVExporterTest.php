<?php
/**
 * Tests for CSV_Exporter::handle_export precondition guards.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Admin\CSV_Exporter;
use LEAStudios\Forms\Entry\Entry_Repository;
use LEAStudios\Forms\Form\Form_Repository;
use LEAStudios\Tests\TestCase;
use WPDieException;

/**
 * @covers \LEAStudios\Forms\Admin\CSV_Exporter
 */
class CSVExporterTest extends TestCase {

	private const NONCE_ACTION = 'leastudios_forms_export_csv';

	private CSV_Exporter $exporter;

	public function set_up(): void {
		parent::set_up();
		$this->exporter = new CSV_Exporter( new Entry_Repository(), new Form_Repository() );
	}

	public function tear_down(): void {
		unset( $_REQUEST['_wpnonce'], $_GET['_wpnonce'], $_GET['form_id'] );
		parent::tear_down();
	}

	/**
	 * Become an administrator with a valid nonce stashed in $_REQUEST.
	 */
	private function authorise_admin(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$_REQUEST['_wpnonce'] = wp_create_nonce( self::NONCE_ACTION );
	}

	public function test_handle_export_dies_when_nonce_is_missing(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$this->expectException( WPDieException::class );
		$this->exporter->handle_export();
	}

	public function test_handle_export_dies_when_user_lacks_capability(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$_REQUEST['_wpnonce'] = wp_create_nonce( self::NONCE_ACTION );

		$this->expectException( WPDieException::class );
		$this->expectExceptionMessageMatches( '/sufficient permissions/' );
		$this->exporter->handle_export();
	}

	public function test_handle_export_dies_when_form_id_is_missing(): void {
		$this->authorise_admin();

		$this->expectException( WPDieException::class );
		$this->expectExceptionMessageMatches( '/No form specified/' );
		$this->exporter->handle_export();
	}

	public function test_handle_export_dies_when_form_does_not_exist(): void {
		$this->authorise_admin();
		$_GET['form_id'] = 999999;

		$this->expectException( WPDieException::class );
		$this->expectExceptionMessageMatches( '/Form not found/' );
		$this->exporter->handle_export();
	}
}

<?php
/**
 * Top-level admin menu registration.
 *
 * @package LEAStudios\Forms\Admin
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Admin;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Registers the top-level "leaStudios Forms" admin menu.
 *
 * The CPT list table is auto-nested via `show_in_menu => 'leastudios-forms'`.
 */
class Forms_Page {

	/**
	 * The menu slug.
	 */
	public const MENU_SLUG = 'leastudios-forms';

	/**
	 * The required capability.
	 */
	private const CAPABILITY = 'manage_options';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
	}

	/**
	 * Add the top-level admin menu page.
	 *
	 * WordPress handles the CPT screens automatically because the CPT
	 * is registered with `show_in_menu => 'leastudios-forms'`.
	 *
	 * @return string The hook suffix for the page.
	 */
	public function add_menu_page(): string {
		$hook_suffix = add_menu_page(
			__( 'leaStudios Forms', 'leastudios-forms' ),
			__( 'Forms', 'leastudios-forms' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			'__return_empty_string',
			'dashicons-feedback',
			30
		);

		return (string) $hook_suffix;
	}
}

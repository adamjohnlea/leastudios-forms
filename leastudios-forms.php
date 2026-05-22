<?php
/**
 * Plugin Name:       leaStudios Forms
 * Plugin URI:        https://leastudios.com/plugins/leastudios-forms
 * Description:       Lightweight form builder for WordPress. Create contact forms, feedback forms, and more with an intuitive drag-and-drop builder.
 * Version:           1.0.2
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Author:            leaStudios
 * Author URI:        https://leastudios.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       leastudios-forms
 * Domain Path:       /languages
 *
 * @package LEAStudios\Forms
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'LEASTUDIOS_FORMS_VERSION', '1.0.2' );
define( 'LEASTUDIOS_FORMS_FILE', __FILE__ );
define( 'LEASTUDIOS_FORMS_DIR', plugin_dir_path( __FILE__ ) );
define( 'LEASTUDIOS_FORMS_URL', plugin_dir_url( __FILE__ ) );

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		function () {
			printf(
				'<div class="notice notice-error"><p><strong>%s</strong>: %s</p></div>',
				esc_html__( 'leaStudios Forms', 'leastudios-forms' ),
				esc_html__( 'Plugin dependencies are missing. Run "composer install" in the plugin directory.', 'leastudios-forms' )
			);
		}
	);
	return;
}

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Initialize the plugin.
 *
 * @return void
 */
function leastudios_forms_init(): void {
	if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
		add_action( 'admin_notices', 'leastudios_forms_php_version_notice' );
		return;
	}

	$plugin = new LEAStudios\Forms\Plugin();
	$plugin->init();
}
add_action( 'plugins_loaded', 'leastudios_forms_init' );

/**
 * Display PHP version notice.
 *
 * @return void
 */
function leastudios_forms_php_version_notice(): void {
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__( 'leaStudios Forms requires PHP 8.2 or higher.', 'leastudios-forms' )
	);
}

/**
 * Run on plugin activation.
 *
 * @return void
 */
function leastudios_forms_activate(): void {
	$migration = new LEAStudios\Forms\Database\Migration();
	$migration->maybe_migrate();

	if ( false === get_option( 'leastudios_forms_options' ) ) {
		update_option(
			'leastudios_forms_options',
			[
				'notification_email'   => get_option( 'admin_email' ),
				'entry_retention_days' => 90,
				'honeypot_enabled'     => true,
				'rate_limit'           => 5,
				'rate_limit_window'    => 60,
			]
		);
	}

	// Register CPT before flushing so rewrite rules are created.
	LEAStudios\Forms\CPT\Form_Post_Type::register();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'leastudios_forms_activate' );

/**
 * Run on plugin deactivation.
 *
 * @return void
 */
function leastudios_forms_deactivate(): void {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'leastudios_forms_deactivate' );

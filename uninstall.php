<?php
/**
 * Uninstall handler — runs when the plugin is deleted via WP admin.
 *
 * @package LEAStudios\Forms
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Delete all form posts.
$leastudios_forms_posts = get_posts(
	[
		'post_type'   => 'leastudios_form',
		'numberposts' => -1,
		'post_status' => 'any',
		'fields'      => 'ids',
	]
);

foreach ( $leastudios_forms_posts as $leastudios_forms_post_id ) {
	wp_delete_post( $leastudios_forms_post_id, true );
}

// Drop entries table.
LEAStudios\Forms\Database\Migration::drop_tables();

// Delete options.
delete_option( 'leastudios_forms_options' );
delete_option( 'leastudios_forms_schema_version' );

flush_rewrite_rules();

<?php
/**
 * Form custom post type registration.
 *
 * @package LEAStudios\Forms\CPT
 */

declare(strict_types=1);

namespace LEAStudios\Forms\CPT;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Registers the leastudios_form custom post type.
 */
class Form_Post_Type {

	/**
	 * The post type slug.
	 */
	public const POST_TYPE = 'leastudios_form';

	/**
	 * Meta key for field definitions.
	 */
	public const FIELDS_META_KEY = '_leastudios_forms_fields';

	/**
	 * Meta key for form settings.
	 */
	public const SETTINGS_META_KEY = '_leastudios_forms_settings';

	/**
	 * Register the post type.
	 *
	 * @return void
	 */
	public static function register(): void {
		$args = [
			'labels'          => [
				'name'               => __( 'Forms', 'leastudios-forms' ),
				'singular_name'      => __( 'Form', 'leastudios-forms' ),
				'add_new'            => __( 'Add New', 'leastudios-forms' ),
				'add_new_item'       => __( 'Add New Form', 'leastudios-forms' ),
				'edit_item'          => __( 'Edit Form', 'leastudios-forms' ),
				'new_item'           => __( 'New Form', 'leastudios-forms' ),
				'view_item'          => __( 'View Form', 'leastudios-forms' ),
				'search_items'       => __( 'Search Forms', 'leastudios-forms' ),
				'not_found'          => __( 'No forms found.', 'leastudios-forms' ),
				'not_found_in_trash' => __( 'No forms found in Trash.', 'leastudios-forms' ),
				'all_items'          => __( 'All Forms', 'leastudios-forms' ),
			],
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'leastudios-forms',
			'show_in_rest'    => true,
			'supports'        => [ 'title' ],
			'capability_type' => 'post',
			'map_meta_cap'    => true,
			'menu_icon'       => 'dashicons-feedback',
			'has_archive'     => false,
			'rewrite'         => false,
		];

		/**
		 * Filter the CPT registration args.
		 *
		 * @param array $args The post type registration arguments.
		 * @return array Filtered arguments.
		 */
		$args = apply_filters( 'leastudios_forms_post_type_args', $args );

		register_post_type( self::POST_TYPE, $args );

		register_post_meta(
			self::POST_TYPE,
			self::FIELDS_META_KEY,
			[
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
				'default'      => '[]',
			]
		);

		register_post_meta(
			self::POST_TYPE,
			self::SETTINGS_META_KEY,
			[
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
				'default'      => '{}',
			]
		);
	}
}

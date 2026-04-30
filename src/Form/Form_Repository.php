<?php
/**
 * Form data access.
 *
 * @package LEAStudios\Forms\Form
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Form;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

use LEAStudios\Forms\CPT\Form_Post_Type;

/**
 * CRUD operations for form custom post type.
 */
class Form_Repository {

	/**
	 * Get a form post by ID.
	 *
	 * @param int $form_id The form post ID.
	 * @return \WP_Post|null The form post or null.
	 */
	public function get_form( int $form_id ): ?\WP_Post {
		$post = get_post( $form_id );

		if ( ! $post || Form_Post_Type::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	/**
	 * Get the field definitions for a form.
	 *
	 * @param int $form_id The form post ID.
	 * @return array<int, array<string, mixed>> Field configurations as decoded from the FIELDS_META_KEY JSON blob.
	 */
	public function get_fields( int $form_id ): array {
		$json = get_post_meta( $form_id, Form_Post_Type::FIELDS_META_KEY, true );

		if ( empty( $json ) ) {
			return [];
		}

		$fields = json_decode( $json, true );

		return is_array( $fields ) ? $fields : [];
	}

	/**
	 * Save field definitions for a form.
	 *
	 * @param int                              $form_id The form post ID.
	 * @param array<int, array<string, mixed>> $fields  Field configurations to encode as JSON.
	 * @return bool Whether the update succeeded.
	 */
	public function save_fields( int $form_id, array $fields ): bool {
		$json = wp_json_encode( $fields );

		return (bool) update_post_meta( $form_id, Form_Post_Type::FIELDS_META_KEY, $json );
	}

	/**
	 * Get form settings.
	 *
	 * @param int $form_id The form post ID.
	 * @return Form_Settings The form settings.
	 */
	public function get_settings( int $form_id ): Form_Settings {
		$json = get_post_meta( $form_id, Form_Post_Type::SETTINGS_META_KEY, true );

		return Form_Settings::from_json( is_string( $json ) ? $json : '{}' );
	}

	/**
	 * Save form settings.
	 *
	 * @param int           $form_id  The form post ID.
	 * @param Form_Settings $settings The settings to save.
	 * @return bool Whether the update succeeded.
	 */
	public function save_settings( int $form_id, Form_Settings $settings ): bool {
		$json = wp_json_encode( $settings->to_array() );

		return (bool) update_post_meta( $form_id, Form_Post_Type::SETTINGS_META_KEY, $json );
	}

	/**
	 * Get all published forms.
	 *
	 * @return \WP_Post[] Array of form posts.
	 */
	public function get_all_forms(): array {
		return get_posts(
			[
				'post_type'   => Form_Post_Type::POST_TYPE,
				'post_status' => [ 'publish', 'draft' ],
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			]
		);
	}
}

<?php
/**
 * Form editor metaboxes and save handler.
 *
 * @package LEAStudios\Forms\Admin
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Admin;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

use LEAStudios\Forms\CPT\Form_Post_Type;
use LEAStudios\Forms\Field\Field_Registry;
use LEAStudios\Forms\Form\Form_Repository;
use LEAStudios\Forms\Form\Form_Settings;
use WP_Post;

/**
 * Manages metaboxes and persistence for the form editor screen.
 */
class Form_Editor {

	/**
	 * Nonce action for the form editor.
	 */
	private const NONCE_ACTION = 'leastudios_forms_save_form';

	/**
	 * Nonce field name.
	 */
	private const NONCE_FIELD = '_leastudios_forms_nonce';

	/**
	 * The form repository.
	 *
	 * @var Form_Repository
	 */
	private Form_Repository $form_repository;

	/**
	 * The field registry.
	 *
	 * @var Field_Registry
	 */
	private Field_Registry $field_registry;

	/**
	 * Constructor.
	 *
	 * @param Form_Repository $form_repository The form repository.
	 * @param Field_Registry  $field_registry  The field registry.
	 */
	public function __construct( Form_Repository $form_repository, Field_Registry $field_registry ) {
		$this->form_repository = $form_repository;
		$this->field_registry  = $field_registry;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'add_meta_boxes_' . Form_Post_Type::POST_TYPE, [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post_' . Form_Post_Type::POST_TYPE, [ $this, 'save_post' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Register metaboxes for the form editor.
	 *
	 * @return void
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'leastudios-forms-fields',
			__( 'Fields', 'leastudios-forms' ),
			[ $this, 'render_fields_metabox' ],
			Form_Post_Type::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'leastudios-forms-embed',
			__( 'Embed', 'leastudios-forms' ),
			[ $this, 'render_embed_metabox' ],
			Form_Post_Type::POST_TYPE,
			'side',
			'high'
		);

		add_meta_box(
			'leastudios-forms-settings',
			__( 'Form Settings', 'leastudios-forms' ),
			[ $this, 'render_settings_metabox' ],
			Form_Post_Type::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Render the Fields metabox.
	 *
	 * Outputs a container for the JS form builder, a hidden textarea with
	 * the current fields JSON, a nonce field, and a field palette of
	 * available field types.
	 *
	 * @param WP_Post $post The current post.
	 * @return void
	 */
	public function render_fields_metabox( WP_Post $post ): void {
		$fields      = $this->form_repository->get_fields( $post->ID );
		$fields_json = wp_json_encode( $fields );
		$field_types = $this->field_registry->get_all();

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<div class="leastudios-forms-field-palette">
			<span class="leastudios-forms-field-palette__label"><?php esc_html_e( 'Add Field', 'leastudios-forms' ); ?></span>
			<?php foreach ( $field_types as $type_slug => $type ) : ?>
				<button
					type="button"
					class="button leastudios-forms-add-field"
					data-field-type="<?php echo esc_attr( $type_slug ); ?>"
				>
					<?php echo esc_html( $type->get_label() ); ?>
				</button>
			<?php endforeach; ?>
		</div>

		<div id="leastudios-forms-fields-list" class="leastudios-forms-fields-list">
			<!-- JS form builder renders field rows here -->
		</div>

		<textarea
			id="leastudios-forms-fields-data"
			name="leastudios_forms_fields_data"
			class="hidden"
			aria-hidden="true"
		><?php echo esc_textarea( (string) $fields_json ); ?></textarea>
		<?php
	}

	/**
	 * Render the Embed metabox showing how to add the form to a page.
	 *
	 * @param WP_Post $post The current post.
	 * @return void
	 */
	public function render_embed_metabox( WP_Post $post ): void {
		$is_new = 'auto-draft' === $post->post_status;

		if ( $is_new ) {
			?>
			<p class="description"><?php esc_html_e( 'Publish the form first to get your embed code.', 'leastudios-forms' ); ?></p>
			<?php
			return;
		}

		$shortcode = '[leastudios_form id="' . $post->ID . '"]';
		?>
		<p class="description" style="margin-bottom: 8px;">
			<?php esc_html_e( 'Copy this shortcode and paste it into any page or post:', 'leastudios-forms' ); ?>
		</p>
		<code
			class="leastudios-forms-shortcode"
			style="display: block; padding: 10px 12px; background: #f0f6fc; border: 1px solid #c3c4c7; border-radius: 4px; font-size: 13px; cursor: pointer; text-align: center; user-select: all;"
			title="<?php esc_attr_e( 'Click to copy', 'leastudios-forms' ); ?>"
		><?php echo esc_html( $shortcode ); ?></code>
		<p class="description" style="margin-top: 8px;">
			<?php esc_html_e( 'Or use the leaStudios Forms block in the block editor.', 'leastudios-forms' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the Form Settings metabox.
	 *
	 * Outputs form setting fields and a hidden textarea for JSON storage.
	 *
	 * @param WP_Post $post The current post.
	 * @return void
	 */
	public function render_settings_metabox( WP_Post $post ): void {
		$settings      = $this->form_repository->get_settings( $post->ID );
		$settings_json = wp_json_encode( $settings->to_array() );
		?>
		<div class="leastudios-forms-settings">
			<p>
				<label for="leastudios-forms-success-message">
					<?php esc_html_e( 'Success Message', 'leastudios-forms' ); ?>
				</label>
				<input
					type="text"
					id="leastudios-forms-success-message"
					class="widefat leastudios-forms-setting"
					data-setting="success_message"
					value="<?php echo esc_attr( $settings->success_message ); ?>"
				/>
			</p>

			<p>
				<label for="leastudios-forms-redirect-url">
					<?php esc_html_e( 'Redirect URL', 'leastudios-forms' ); ?>
				</label>
				<input
					type="url"
					id="leastudios-forms-redirect-url"
					class="widefat leastudios-forms-setting"
					data-setting="redirect_url"
					value="<?php echo esc_url( $settings->redirect_url ); ?>"
				/>
			</p>

			<p>
				<label for="leastudios-forms-submit-text">
					<?php esc_html_e( 'Submit Button Text', 'leastudios-forms' ); ?>
				</label>
				<input
					type="text"
					id="leastudios-forms-submit-text"
					class="widefat leastudios-forms-setting"
					data-setting="submit_button_text"
					value="<?php echo esc_attr( $settings->submit_button_text ); ?>"
				/>
			</p>

			<p>
				<label>
					<input
						type="checkbox"
						id="leastudios-forms-honeypot"
						class="leastudios-forms-setting"
						data-setting="honeypot_enabled"
						<?php checked( $settings->honeypot_enabled ); ?>
					/>
					<?php esc_html_e( 'Enable honeypot spam protection', 'leastudios-forms' ); ?>
				</label>
			</p>

			<p>
				<label for="leastudios-forms-rate-limit">
					<?php esc_html_e( 'Rate Limit (submissions per window)', 'leastudios-forms' ); ?>
				</label>
				<input
					type="number"
					id="leastudios-forms-rate-limit"
					class="widefat leastudios-forms-setting"
					data-setting="rate_limit"
					value="<?php echo esc_attr( (string) $settings->rate_limit ); ?>"
					min="1"
					step="1"
				/>
			</p>

			<p>
				<label for="leastudios-forms-rate-window">
					<?php esc_html_e( 'Rate Limit Window (seconds)', 'leastudios-forms' ); ?>
				</label>
				<input
					type="number"
					id="leastudios-forms-rate-window"
					class="widefat leastudios-forms-setting"
					data-setting="rate_limit_window"
					value="<?php echo esc_attr( (string) $settings->rate_limit_window ); ?>"
					min="10"
					step="1"
				/>
			</p>

			<hr />

			<h4><?php esc_html_e( 'Notifications', 'leastudios-forms' ); ?></h4>

			<div id="leastudios-forms-notifications" class="leastudios-forms-notifications">
				<?php foreach ( $settings->notifications as $index => $notification ) : ?>
					<div class="leastudios-forms-notification" data-index="<?php echo esc_attr( (string) $index ); ?>">
						<p>
							<label><?php esc_html_e( 'To', 'leastudios-forms' ); ?></label>
							<input
								type="text"
								class="widefat notification-to"
								value="<?php echo esc_attr( $notification['to'] ?? '' ); ?>"
							/>
						</p>
						<p>
							<label><?php esc_html_e( 'Subject', 'leastudios-forms' ); ?></label>
							<input
								type="text"
								class="widefat notification-subject"
								value="<?php echo esc_attr( $notification['subject'] ?? '' ); ?>"
							/>
						</p>
						<p>
							<label><?php esc_html_e( 'Message', 'leastudios-forms' ); ?></label>
							<textarea
								class="widefat notification-message"
								rows="3"
							><?php echo esc_textarea( $notification['message'] ?? '' ); ?></textarea>
						</p>
						<p>
							<label><?php esc_html_e( 'Reply-To', 'leastudios-forms' ); ?></label>
							<input
								type="text"
								class="widefat notification-reply-to"
								value="<?php echo esc_attr( $notification['reply_to'] ?? '' ); ?>"
							/>
						</p>
						<p>
							<button type="button" class="button notification-remove">
								<?php esc_html_e( 'Remove Notification', 'leastudios-forms' ); ?>
							</button>
						</p>
						<hr />
					</div>
				<?php endforeach; ?>
			</div>

			<p>
				<button type="button" class="button" id="leastudios-forms-add-notification">
					<?php esc_html_e( 'Add Notification', 'leastudios-forms' ); ?>
				</button>
			</p>
		</div>

		<textarea
			id="leastudios-forms-settings-data"
			name="leastudios_forms_settings_data"
			class="hidden"
			aria-hidden="true"
		><?php echo esc_textarea( (string) $settings_json ); ?></textarea>
		<?php
	}

	/**
	 * Save form data on post save.
	 *
	 * @param int $post_id The post ID being saved.
	 * @return void
	 */
	public function save_post( int $post_id ): void {
		// Verify nonce.
		if (
			! isset( $_POST[ self::NONCE_FIELD ] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ),
				self::NONCE_ACTION
			)
		) {
			return;
		}

		// Skip autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check capabilities.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save fields JSON.
		if ( isset( $_POST['leastudios_forms_fields_data'] ) ) {
			$fields_raw = wp_unslash( $_POST['leastudios_forms_fields_data'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$fields     = json_decode( $fields_raw, true );

			if ( is_array( $fields ) ) {
				$this->form_repository->save_fields( $post_id, $fields );
			}
		}

		// Save settings JSON.
		if ( isset( $_POST['leastudios_forms_settings_data'] ) ) {
			$settings_raw = wp_unslash( $_POST['leastudios_forms_settings_data'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$settings     = Form_Settings::from_json( $settings_raw );

			$this->form_repository->save_settings( $post_id, $settings );
		}
	}

	/**
	 * Enqueue admin assets on the form edit screen.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		$screen = get_current_screen();

		if ( ! $screen || Form_Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}

		if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		wp_enqueue_style(
			'leastudios-forms-admin',
			LEASTUDIOS_FORMS_URL . 'assets/css/admin.css',
			[],
			LEASTUDIOS_FORMS_VERSION
		);

		wp_enqueue_script(
			'leastudios-forms-admin-builder',
			LEASTUDIOS_FORMS_URL . 'assets/js/admin-form-builder.js',
			[ 'jquery', 'jquery-ui-sortable' ],
			LEASTUDIOS_FORMS_VERSION,
			true
		);

		// Prepare field types data for JS.
		$field_types_data = [];

		foreach ( $this->field_registry->get_all() as $type_slug => $type ) {
			$field_types_data[ $type_slug ] = [
				'type'  => $type_slug,
				'label' => $type->get_label(),
			];
		}

		wp_localize_script(
			'leastudios-forms-admin-builder',
			'leastudiosFormsAdmin',
			[
				'fieldTypes' => $field_types_data,
				'restUrl'    => rest_url( 'leastudios-forms/v1' ),
				'restNonce'  => wp_create_nonce( 'wp_rest' ),
			]
		);
	}
}

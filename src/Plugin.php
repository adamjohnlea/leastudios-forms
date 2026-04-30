<?php
/**
 * Main plugin bootstrap class.
 *
 * @package LEAStudios\Forms
 */

declare(strict_types=1);

namespace LEAStudios\Forms;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

use LEAStudios\Forms\Admin\CSV_Exporter;
use LEAStudios\Forms\Admin\Entries_Page;
use LEAStudios\Forms\Admin\Form_Editor;
use LEAStudios\Forms\Admin\Forms_Page;
use LEAStudios\Forms\Admin\Settings_Page;
use LEAStudios\Forms\CPT\Form_Post_Type;
use LEAStudios\Forms\Database\Migration;
use LEAStudios\Forms\Entry\Entry_Repository;
use LEAStudios\Forms\Field\Field_Registry;
use LEAStudios\Forms\Form\Fields_Validator;
use LEAStudios\Forms\Form\Form_Repository;
use LEAStudios\Forms\Integration\Mailer_Integration;
use LEAStudios\Forms\Notification\Email_Notifier;
use LEAStudios\Forms\Render\Block;
use LEAStudios\Forms\Render\Form_Renderer;
use LEAStudios\Forms\Render\Shortcode;
use LEAStudios\Forms\REST\Submission_Controller;
use LEAStudios\Forms\Spam\Honeypot;
use LEAStudios\Forms\Spam\Rate_Limiter;
use LEAStudios\Forms\Submission\Submission_Handler;
use LEAStudios\Forms\Submission\Validator;
use LEAStudios\Forms\Suite\Suite_Detector;

/**
 * Wires all plugin components together.
 */
final class Plugin {

	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		// Run migrations.
		$migration = new Migration();
		$migration->maybe_migrate();

		// Translations: load on `init` so other plugins/themes can hook into
		// the translated strings as soon as they're available.
		add_action( 'init', [ $this, 'load_textdomain' ] );

		// Register CPT.
		add_action( 'init', [ Form_Post_Type::class, 'register' ] );

		// Field registry.
		$field_registry = new Field_Registry();
		$field_registry->register_defaults();

		// Repositories.
		$form_repo  = new Form_Repository();
		$entry_repo = new Entry_Repository();

		// Spam protection.
		$honeypot     = new Honeypot();
		$rate_limiter = new Rate_Limiter();

		// Submission pipeline.
		$validator = new Validator( $field_registry );
		$notifier  = new Email_Notifier();
		$handler   = new Submission_Handler(
			$validator,
			$entry_repo,
			$notifier,
			$honeypot,
			$rate_limiter,
			$form_repo,
			$field_registry
		);

		// Frontend rendering.
		$renderer  = new Form_Renderer( $form_repo, $field_registry, $honeypot );
		$shortcode = new Shortcode( $renderer );
		$block     = new Block( $renderer, $form_repo );

		add_action( 'init', [ $shortcode, 'register' ] );
		add_action( 'init', [ $block, 'register' ] );

		// REST API.
		$submission_controller = new Submission_Controller( $handler );
		add_action( 'rest_api_init', [ $submission_controller, 'register_routes' ] );

		// No-JS form submission fallback.
		add_action(
			'admin_post_nopriv_leastudios_forms_submit',
			function () use ( $handler, $form_repo ) {
				$this->handle_fallback_submission( $handler, $form_repo );
			}
		);
		add_action(
			'admin_post_leastudios_forms_submit',
			function () use ( $handler, $form_repo ) {
				$this->handle_fallback_submission( $handler, $form_repo );
			}
		);

		// Admin.
		if ( is_admin() ) {
			$forms_page = new Forms_Page();
			$forms_page->init();

			$fields_validator = new Fields_Validator( $field_registry );
			$form_editor      = new Form_Editor( $form_repo, $field_registry, $fields_validator );
			$form_editor->init();

			$entries_page = new Entries_Page( $entry_repo, $form_repo );
			$entries_page->init();

			$settings_page = new Settings_Page();
			$settings_page->init();

			$csv_exporter = new CSV_Exporter( $entry_repo, $form_repo );
			$csv_exporter->init();

			// Mailer integration.
			if ( Suite_Detector::is_active( 'leastudios-mailer' ) ) {
				$mailer_integration = new Mailer_Integration();
				$mailer_integration->init();
			}
		}

		// Scheduled entry cleanup.
		add_action( 'leastudios_forms_cleanup_entries', [ $this, 'cleanup_entries' ] );

		if ( ! wp_next_scheduled( 'leastudios_forms_cleanup_entries' ) ) {
			wp_schedule_event( time(), 'daily', 'leastudios_forms_cleanup_entries' );
		}
	}

	/**
	 * Clean up old entries based on retention setting.
	 *
	 * @return void
	 */
	public function cleanup_entries(): void {
		$options = get_option( 'leastudios_forms_options', [] );
		$days    = (int) ( $options['entry_retention_days'] ?? 90 );

		$entry_repo = new Entry_Repository();
		$entry_repo->delete_old_entries( $days );
	}

	/**
	 * Load plugin text domain for translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'leastudios-forms',
			false,
			dirname( plugin_basename( LEASTUDIOS_FORMS_FILE ) ) . '/languages'
		);
	}

	/**
	 * Handle no-JS form submission fallback via admin-post.php.
	 *
	 * @param Submission_Handler $handler   The submission handler.
	 * @param Form_Repository    $form_repo The form repository.
	 * @return void
	 */
	private function handle_fallback_submission( Submission_Handler $handler, Form_Repository $form_repo ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified below.
		$form_id = absint( $_POST['form_id'] ?? 0 );

		if ( 0 === $form_id ) {
			wp_die(
				esc_html__( 'Form submission is missing the form_id field.', 'leastudios-forms' ),
				esc_html__( 'Form submission failed', 'leastudios-forms' ),
				[ 'response' => 400 ]
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );

		if ( ! wp_verify_nonce( $nonce, 'leastudios_forms_submit_' . $form_id ) ) {
			wp_die(
				esc_html__( 'Security check failed. Please reload the page and resubmit.', 'leastudios-forms' ),
				esc_html__( 'Form submission failed', 'leastudios-forms' ),
				[ 'response' => 403 ]
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize_fields_recursively walks the array and applies sanitize_text_field per leaf value.
		$fields = self::sanitize_fields_recursively( (array) wp_unslash( $_POST['fields'] ?? [] ) );
		// Pass null when the honeypot field is absent from the post body
		// so Honeypot::is_spam can flag a missing field as a bot signal.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$honeypot_value = array_key_exists( '_leastudios_forms_hp', $_POST )
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			? sanitize_text_field( (string) wp_unslash( $_POST['_leastudios_forms_hp'] ) )
			: null;
		$ip         = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
		$user_id    = get_current_user_id();

		$result = $handler->handle(
			$form_id,
			$fields,
			$honeypot_value,
			$ip,
			$user_agent,
			$user_id > 0 ? $user_id : null
		);

		$referer = wp_get_referer();

		if ( ! $referer ) {
			$referer = home_url();
		}

		if ( $result['success'] ) {
			$settings = $form_repo->get_settings( $form_id );

			if ( '' !== $settings->redirect_url ) {
				wp_safe_redirect( $settings->redirect_url );
				exit;
			}

			wp_safe_redirect( add_query_arg( 'leastudios_forms_success', '1', $referer ) );
			exit;
		}

		// Store errors in transient for display after redirect.
		$token = wp_generate_password( 12, false );
		set_transient( 'leastudios_forms_errors_' . $token, $result['errors'], 300 );

		wp_safe_redirect( add_query_arg( 'leastudios_forms_errors', $token, $referer ) );
		exit;
	}

	/**
	 * Recursively sanitise the no-JS submission `fields` array. Field types
	 * such as Address use nested arrays (`fields[address][line1]`); a flat
	 * `array_map( 'sanitize_text_field', … )` collapses each nested array to
	 * the literal string "Array". This walker preserves shape and applies
	 * `sanitize_text_field` to every leaf, leaving the per-field sanitiser
	 * registered on each Field_Type to do final type-aware sanitisation
	 * inside Submission_Handler.
	 *
	 * @param array<int|string, mixed> $input The raw fields payload.
	 * @return array<int|string, mixed>
	 */
	private static function sanitize_fields_recursively( array $input ): array {
		$out = [];

		foreach ( $input as $key => $value ) {
			if ( is_array( $value ) ) {
				$out[ $key ] = self::sanitize_fields_recursively( $value );
			} else {
				$out[ $key ] = sanitize_text_field( (string) $value );
			}
		}

		return $out;
	}
}

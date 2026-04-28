<?php
/**
 * Global settings page.
 *
 * @package LEAStudios\Forms\Admin
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Admin;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the leaStudios Forms global settings page.
 */
class Settings_Page {

	/**
	 * The option group name.
	 */
	private const OPTION_GROUP = 'leastudios_forms_settings';

	/**
	 * The option name in the database.
	 */
	public const OPTION_NAME = 'leastudios_forms_options';

	/**
	 * The settings page slug.
	 */
	private const PAGE_SLUG = 'leastudios-forms-settings';

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
		add_action( 'admin_menu', [ $this, 'add_submenu_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Add the Settings submenu page under the Forms menu.
	 *
	 * @return void
	 */
	public function add_submenu_page(): void {
		add_submenu_page(
			Forms_Page::MENU_SLUG,
			__( 'leaStudios Forms Settings', 'leastudios-forms' ),
			__( 'Settings', 'leastudios-forms' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Register settings using the Settings API.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_options' ],
				'default'           => $this->get_defaults(),
			]
		);

		add_settings_section(
			'leastudios_forms_general',
			__( 'General Settings', 'leastudios-forms' ),
			'__return_empty_string',
			self::PAGE_SLUG
		);

		add_settings_field(
			'notification_email',
			__( 'Default Notification Email', 'leastudios-forms' ),
			[ $this, 'render_notification_email_field' ],
			self::PAGE_SLUG,
			'leastudios_forms_general'
		);

		add_settings_field(
			'entry_retention_days',
			__( 'Entry Retention (days)', 'leastudios-forms' ),
			[ $this, 'render_entry_retention_field' ],
			self::PAGE_SLUG,
			'leastudios_forms_general'
		);

		add_settings_section(
			'leastudios_forms_spam',
			__( 'Spam Protection', 'leastudios-forms' ),
			'__return_empty_string',
			self::PAGE_SLUG
		);

		add_settings_field(
			'honeypot_enabled',
			__( 'Enable Honeypot', 'leastudios-forms' ),
			[ $this, 'render_honeypot_field' ],
			self::PAGE_SLUG,
			'leastudios_forms_spam'
		);

		add_settings_field(
			'rate_limit',
			__( 'Rate Limit', 'leastudios-forms' ),
			[ $this, 'render_rate_limit_field' ],
			self::PAGE_SLUG,
			'leastudios_forms_spam'
		);

		add_settings_field(
			'rate_limit_window',
			__( 'Rate Limit Window (seconds)', 'leastudios-forms' ),
			[ $this, 'render_rate_limit_window_field' ],
			self::PAGE_SLUG,
			'leastudios_forms_spam'
		);
	}

	/**
	 * Sanitize options before saving.
	 *
	 * @param array<string, mixed> $input Raw input values.
	 * @return array<string, mixed> Sanitized values.
	 */
	public function sanitize_options( array $input ): array {
		$defaults  = $this->get_defaults();
		$sanitized = [];

		$sanitized['notification_email'] = isset( $input['notification_email'] )
			? sanitize_email( $input['notification_email'] )
			: $defaults['notification_email'];

		$sanitized['entry_retention_days'] = isset( $input['entry_retention_days'] )
			? absint( $input['entry_retention_days'] )
			: $defaults['entry_retention_days'];

		$sanitized['honeypot_enabled'] = ! empty( $input['honeypot_enabled'] );

		$sanitized['rate_limit'] = isset( $input['rate_limit'] )
			? max( 1, absint( $input['rate_limit'] ) )
			: $defaults['rate_limit'];

		$sanitized['rate_limit_window'] = isset( $input['rate_limit_window'] )
			? max( 1, absint( $input['rate_limit_window'] ) )
			: $defaults['rate_limit_window'];

		return $sanitized;
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the notification email field.
	 *
	 * @return void
	 */
	public function render_notification_email_field(): void {
		$options = get_option( self::OPTION_NAME, $this->get_defaults() );
		$value   = $options['notification_email'] ?? $this->get_defaults()['notification_email'];
		?>
		<input
			type="email"
			id="notification_email"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[notification_email]"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
		/>
		<p class="description">
			<?php esc_html_e( 'Default email address for form notifications.', 'leastudios-forms' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the entry retention field.
	 *
	 * @return void
	 */
	public function render_entry_retention_field(): void {
		$options = get_option( self::OPTION_NAME, $this->get_defaults() );
		$value   = $options['entry_retention_days'] ?? $this->get_defaults()['entry_retention_days'];
		?>
		<input
			type="number"
			id="entry_retention_days"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[entry_retention_days]"
			value="<?php echo esc_attr( (string) $value ); ?>"
			class="small-text"
			min="0"
			step="1"
		/>
		<p class="description">
			<?php esc_html_e( 'Number of days to keep entries. Set to 0 to keep indefinitely.', 'leastudios-forms' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the honeypot enabled field.
	 *
	 * @return void
	 */
	public function render_honeypot_field(): void {
		$options = get_option( self::OPTION_NAME, $this->get_defaults() );
		$value   = $options['honeypot_enabled'] ?? $this->get_defaults()['honeypot_enabled'];
		?>
		<label>
			<input
				type="checkbox"
				id="honeypot_enabled"
				name="<?php echo esc_attr( self::OPTION_NAME ); ?>[honeypot_enabled]"
				value="1"
				<?php checked( $value ); ?>
			/>
			<?php esc_html_e( 'Enable honeypot fields on all forms by default.', 'leastudios-forms' ); ?>
		</label>
		<?php
	}

	/**
	 * Render the rate limit field.
	 *
	 * @return void
	 */
	public function render_rate_limit_field(): void {
		$options = get_option( self::OPTION_NAME, $this->get_defaults() );
		$value   = $options['rate_limit'] ?? $this->get_defaults()['rate_limit'];
		?>
		<input
			type="number"
			id="rate_limit"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[rate_limit]"
			value="<?php echo esc_attr( (string) $value ); ?>"
			class="small-text"
			min="1"
			step="1"
		/>
		<p class="description">
			<?php esc_html_e( 'Maximum number of submissions per IP within the rate limit window.', 'leastudios-forms' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the rate limit window field.
	 *
	 * @return void
	 */
	public function render_rate_limit_window_field(): void {
		$options = get_option( self::OPTION_NAME, $this->get_defaults() );
		$value   = $options['rate_limit_window'] ?? $this->get_defaults()['rate_limit_window'];
		?>
		<input
			type="number"
			id="rate_limit_window"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[rate_limit_window]"
			value="<?php echo esc_attr( (string) $value ); ?>"
			class="small-text"
			min="1"
			step="1"
		/>
		<p class="description">
			<?php esc_html_e( 'Time window in seconds for the rate limit.', 'leastudios-forms' ); ?>
		</p>
		<?php
	}

	/**
	 * Get default option values.
	 *
	 * @return array<string, mixed>
	 */
	private function get_defaults(): array {
		return [
			'notification_email'   => get_option( 'admin_email' ),
			'entry_retention_days' => 90,
			'honeypot_enabled'     => true,
			'rate_limit'           => 5,
			'rate_limit_window'    => 60,
		];
	}
}

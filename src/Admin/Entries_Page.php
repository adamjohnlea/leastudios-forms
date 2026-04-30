<?php
/**
 * Entries admin page.
 *
 * @package LEAStudios\Forms\Admin
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Admin;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

use LEAStudios\Forms\Entry\Entry_Repository;
use LEAStudios\Forms\Entry\Entry_Status;
use LEAStudios\Forms\Form\Form_Repository;
use LEAStudios\Forms\Shared\Datetime_Util;

/**
 * Manages the Entries submenu page and entry detail view.
 */
class Entries_Page {

	/**
	 * The page hook suffix.
	 *
	 * @var string
	 */
	private string $hook_suffix = '';

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
		add_action( 'admin_menu', [ $this, 'add_submenu_page' ] );
		add_action( 'admin_init', [ $this, 'handle_actions' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Add the Entries submenu page.
	 *
	 * @return void
	 */
	public function add_submenu_page(): void {
		$this->hook_suffix = (string) add_submenu_page(
			Forms_Page::MENU_SLUG,
			__( 'Form Entries', 'leastudios-forms' ),
			__( 'Entries', 'leastudios-forms' ),
			'manage_options',
			'leastudios-forms-entries',
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Render the entries page.
	 *
	 * Delegates to detail view when viewing a single entry, otherwise
	 * renders the list table.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'leastudios-forms' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$entry_id = isset( $_GET['entry_id'] ) ? absint( $_GET['entry_id'] ) : 0;

		if ( 'view' === $action && $entry_id > 0 ) {
			$this->render_entry_detail( $entry_id );
		} else {
			$this->render_list();
		}
	}

	/**
	 * Render the entries list table.
	 *
	 * @return void
	 */
	private function render_list(): void {
		$list_table = new Entries_List_Table( $this->entry_repository, $this->form_repository );
		$list_table->prepare_items();

		$forms = $this->form_repository->get_all_forms();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Form Entries', 'leastudios-forms' ); ?></h1>

			<?php if ( $current_form_id > 0 ) : ?>
				<a
					href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=leastudios_forms_export_csv&form_id=' . $current_form_id ), 'leastudios_forms_export_csv' ) ); ?>"
					class="page-title-action"
				>
					<?php esc_html_e( 'Export CSV', 'leastudios-forms' ); ?>
				</a>
			<?php endif; ?>

			<hr class="wp-header-end" />

			<div class="leastudios-forms-entries-filter">
				<form method="get">
					<input type="hidden" name="page" value="leastudios-forms-entries" />
					<label for="leastudios-forms-filter-form" class="screen-reader-text">
						<?php esc_html_e( 'Filter by form', 'leastudios-forms' ); ?>
					</label>
					<select id="leastudios-forms-filter-form" name="form_id">
						<option value=""><?php esc_html_e( 'All Forms', 'leastudios-forms' ); ?></option>
						<?php foreach ( $forms as $form ) : ?>
							<option
								value="<?php echo esc_attr( (string) $form->ID ); ?>"
								<?php selected( $current_form_id, $form->ID ); ?>
							>
								<?php echo esc_html( $form->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php submit_button( __( 'Filter', 'leastudios-forms' ), 'secondary', 'filter_action', false ); ?>
				</form>
			</div>

			<form method="post">
				<?php
				$list_table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render a single entry's detail view.
	 *
	 * @param int $entry_id The entry ID.
	 * @return void
	 */
	private function render_entry_detail( int $entry_id ): void {
		$entry = $this->entry_repository->get_entry( $entry_id );

		if ( ! $entry ) {
			wp_die( esc_html__( 'Entry not found.', 'leastudios-forms' ) );
		}

		$form   = $this->form_repository->get_form( (int) $entry->form_id );
		$fields = $this->form_repository->get_fields( (int) $entry->form_id );

		$field_data = json_decode( $entry->field_data, true );

		if ( ! is_array( $field_data ) ) {
			$field_data = [];
		}

		// Build a label map from field configs.
		$label_map = [];
		foreach ( $fields as $field ) {
			if ( isset( $field['name'], $field['label'] ) ) {
				$label_map[ $field['name'] ] = $field['label'];
			}
		}

		$status       = Entry_Status::tryFrom( $entry->status ?? '' );
		$status_label = $status ? $status->label() : $entry->status;

		$status_colors = [
			'unread'  => '#2271b1',
			'read'    => '#787c82',
			'trashed' => '#d63638',
		];
		$badge_color   = $status_colors[ $entry->status ] ?? '#787c82';

		$back_url = admin_url( 'admin.php?page=leastudios-forms-entries' );

		// Mark as read action URL.
		$mark_read_url = wp_nonce_url(
			add_query_arg(
				[
					'page'     => 'leastudios-forms-entries',
					'action'   => 'mark_read',
					'entry_id' => $entry_id,
				],
				admin_url( 'admin.php' )
			),
			'leastudios_forms_entry_action'
		);

		// Mark as unread action URL.
		$mark_unread_url = wp_nonce_url(
			add_query_arg(
				[
					'page'     => 'leastudios-forms-entries',
					'action'   => 'mark_unread',
					'entry_id' => $entry_id,
				],
				admin_url( 'admin.php' )
			),
			'leastudios_forms_entry_action'
		);

		// Delete action URL.
		$delete_url = wp_nonce_url(
			add_query_arg(
				[
					'page'     => 'leastudios-forms-entries',
					'action'   => 'delete',
					'entry_id' => $entry_id,
				],
				admin_url( 'admin.php' )
			),
			'leastudios_forms_entry_action'
		);

		// Auto-mark as read when viewing — but only if the request carries
		// the view-entry nonce. This keeps the friendly UX (clicking a row
		// in the list table marks it read) while ensuring link-prefetchers
		// or admin-area scanners that hit the URL without the nonce do not
		// silently mutate state.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$has_view_nonce = isset( $_GET['_wpnonce'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			&& false !== wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'leastudios_forms_view_entry' );

		if ( $has_view_nonce && Entry_Status::Unread === $status ) {
			$this->entry_repository->update_status( $entry_id, Entry_Status::Read->value );
		}

		?>
		<div class="wrap">
			<h1>
				<a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action">
					&larr; <?php esc_html_e( 'Back to Entries', 'leastudios-forms' ); ?>
				</a>
				<?php esc_html_e( 'Entry Detail', 'leastudios-forms' ); ?>
			</h1>

			<div class="leastudios-forms-entry-detail">
				<table class="widefat fixed striped">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Form', 'leastudios-forms' ); ?></th>
							<td><?php echo esc_html( $form ? $form->post_title : __( 'Unknown', 'leastudios-forms' ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Date', 'leastudios-forms' ); ?></th>
							<td><?php echo esc_html( Datetime_Util::format_for_display( $entry->created_at ?? null, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'IP Address', 'leastudios-forms' ); ?></th>
							<td><?php echo esc_html( $entry->ip_address ?? '' ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'User Agent', 'leastudios-forms' ); ?></th>
							<td><?php echo esc_html( $entry->user_agent ?? '' ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Status', 'leastudios-forms' ); ?></th>
							<td>
								<span
									class="leastudios-forms-status-badge"
									style="background-color: <?php echo esc_attr( $badge_color ); ?>; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 12px;"
								>
									<?php echo esc_html( $status_label ); ?>
								</span>
							</td>
						</tr>
					</tbody>
				</table>

				<h3><?php esc_html_e( 'Submitted Data', 'leastudios-forms' ); ?></h3>

				<dl class="leastudios-forms-entry-fields">
					<?php foreach ( $field_data as $field_name => $value ) : ?>
						<dt><?php echo esc_html( $label_map[ $field_name ] ?? $field_name ); ?></dt>
						<dd>
							<?php
							if ( is_array( $value ) ) {
								echo esc_html( implode( ', ', $value ) );
							} else {
								echo esc_html( (string) $value );
							}
							?>
						</dd>
					<?php endforeach; ?>
				</dl>

				<?php $this->maybe_render_delivery_status( $entry ); ?>

				<?php
				$entry_actions = [];

				if ( Entry_Status::Read->value === $entry->status ) {
					$entry_actions['mark_unread'] = [
						'url'   => $mark_unread_url,
						'label' => __( 'Mark as Unread', 'leastudios-forms' ),
						'class' => 'button',
					];
				} else {
					$entry_actions['mark_read'] = [
						'url'   => $mark_read_url,
						'label' => __( 'Mark as Read', 'leastudios-forms' ),
						'class' => 'button',
					];
				}

				$entry_actions['delete'] = [
					'url'     => $delete_url,
					'label'   => __( 'Delete', 'leastudios-forms' ),
					'class'   => 'button button-link-delete',
					'onclick' => "return confirm('" . esc_js( __( 'Are you sure you want to delete this entry?', 'leastudios-forms' ) ) . "');",
				];

				/**
				 * Filter available actions on entry detail.
				 *
				 * @param array  $actions Array of actions keyed by slug with url, label, class, and optional onclick.
				 * @param object $entry   The entry object.
				 * @return array Filtered actions.
				 */
				$entry_actions = apply_filters( 'leastudios_forms_entry_actions', $entry_actions, $entry );
				?>

				<div class="leastudios-forms-entry-actions">
					<?php foreach ( $entry_actions as $action_config ) : ?>
						<a
							href="<?php echo esc_url( $action_config['url'] ); ?>"
							class="<?php echo esc_attr( $action_config['class'] ?? 'button' ); ?>"
							<?php if ( ! empty( $action_config['onclick'] ) ) : ?>
								onclick="<?php echo esc_attr( $action_config['onclick'] ); ?>"
							<?php endif; ?>
						>
							<?php echo esc_html( $action_config['label'] ); ?>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render delivery status section if mailer integration is active.
	 *
	 * @param object $entry The entry object.
	 * @return void
	 */
	private function maybe_render_delivery_status( object $entry ): void {
		if ( ! class_exists( '\LEAStudios\Suite\Suite_Detector' ) ) {
			return;
		}

		if ( empty( $entry->notification_message_ids ) ) {
			return;
		}

		$message_ids = json_decode( $entry->notification_message_ids, true );

		if ( ! is_array( $message_ids ) || empty( $message_ids ) ) {
			return;
		}

		?>
		<h3><?php esc_html_e( 'Notification Delivery Status', 'leastudios-forms' ); ?></h3>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Message ID', 'leastudios-forms' ); ?></th>
					<th><?php esc_html_e( 'Status', 'leastudios-forms' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $message_ids as $message_id ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $message_id ); ?></td>
						<td>
							<?php
							/**
							 * Filter the delivery status display for a message ID.
							 *
							 * @param string $status     The status display string.
							 * @param string $message_id The notification message ID.
							 */
							echo esc_html(
								(string) apply_filters(
									'leastudios_forms_delivery_status',
									__( 'Sent', 'leastudios-forms' ),
									$message_id
								)
							);
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Handle entry actions (mark read, mark unread, delete).
	 *
	 * @return void
	 */
	public function handle_actions(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'leastudios-forms-entries' !== $page ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$entry_id = isset( $_GET['entry_id'] ) ? absint( $_GET['entry_id'] ) : 0;

		if ( ! in_array( $action, [ 'mark_read', 'mark_unread', 'delete' ], true ) || 0 === $entry_id ) {
			// Handle bulk actions from POST.
			$this->handle_bulk_actions();
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'leastudios-forms' ) );
		}

		check_admin_referer( 'leastudios_forms_entry_action' );

		switch ( $action ) {
			case 'mark_read':
				$this->entry_repository->update_status( $entry_id, Entry_Status::Read->value );
				break;

			case 'mark_unread':
				$this->entry_repository->update_status( $entry_id, Entry_Status::Unread->value );
				break;

			case 'delete':
				$this->entry_repository->delete_entry( $entry_id );
				break;
		}

		$redirect_url = admin_url( 'admin.php?page=leastudios-forms-entries' );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle bulk actions from the list table.
	 *
	 * @return void
	 */
	private function handle_bulk_actions(): void {
		if ( ! isset( $_POST['_wpnonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'bulk-entries' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = '';
		if ( isset( $_POST['action'] ) && '-1' !== $_POST['action'] ) {
			$action = sanitize_text_field( wp_unslash( $_POST['action'] ) );
		} elseif ( isset( $_POST['action2'] ) && '-1' !== $_POST['action2'] ) {
			$action = sanitize_text_field( wp_unslash( $_POST['action2'] ) );
		}

		if ( empty( $action ) || ! isset( $_POST['entry_ids'] ) ) {
			return;
		}

		$entry_ids = array_map( 'absint', (array) $_POST['entry_ids'] );

		foreach ( $entry_ids as $entry_id ) {
			if ( 0 === $entry_id ) {
				continue;
			}

			switch ( $action ) {
				case 'mark_read':
					$this->entry_repository->update_status( $entry_id, Entry_Status::Read->value );
					break;

				case 'mark_unread':
					$this->entry_repository->update_status( $entry_id, Entry_Status::Unread->value );
					break;

				case 'delete':
					$this->entry_repository->delete_entry( $entry_id );
					break;
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=leastudios-forms-entries' ) );
		exit;
	}

	/**
	 * Enqueue admin assets on the entries page.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'leastudios-forms-admin',
			LEASTUDIOS_FORMS_URL . 'assets/css/admin.css',
			[],
			LEASTUDIOS_FORMS_VERSION
		);
	}
}

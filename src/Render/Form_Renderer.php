<?php
/**
 * Form rendering.
 *
 * @package LEAStudios\Forms\Render
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Render;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

use LEAStudios\Forms\Field\Field_Registry;
use LEAStudios\Forms\Form\Form_Repository;
use LEAStudios\Forms\Spam\Honeypot;

/**
 * Renders a complete form as HTML.
 */
class Form_Renderer {

	/**
	 * Allowed HTML tags and attributes for field rendering.
	 */
	private const ALLOWED_FIELD_HTML = [
		'div'      => [
			'class'         => true,
			'id'            => true,
			'data-field-id' => true,
		],
		'label'    => [
			'for'   => true,
			'class' => true,
		],
		'input'    => [
			'type'          => true,
			'id'            => true,
			'name'          => true,
			'value'         => true,
			'placeholder'   => true,
			'required'      => true,
			'aria-required' => true,
			'aria-invalid'  => true,
			'aria-hidden'   => true,
			'class'         => true,
			'min'           => true,
			'max'           => true,
			'step'          => true,
			'checked'       => true,
			'tabindex'      => true,
		],
		'select'   => [
			'id'            => true,
			'name'          => true,
			'required'      => true,
			'aria-required' => true,
			'class'         => true,
		],
		'option'   => [
			'value'    => true,
			'selected' => true,
		],
		'textarea' => [
			'id'            => true,
			'name'          => true,
			'placeholder'   => true,
			'required'      => true,
			'aria-required' => true,
			'rows'          => true,
			'class'         => true,
		],
		'fieldset' => [
			'class' => true,
		],
		'legend'   => [
			'class' => true,
		],
		'span'     => [
			'class' => true,
			'style' => true,
		],
	];

	/**
	 * Constructor.
	 *
	 * @param Form_Repository $form_repository The form repository.
	 * @param Field_Registry  $field_registry  The field type registry.
	 * @param Honeypot        $honeypot        The honeypot spam protection.
	 */
	public function __construct(
		private readonly Form_Repository $form_repository,
		private readonly Field_Registry $field_registry,
		private readonly Honeypot $honeypot,
	) {}

	/**
	 * Render a form by its post ID.
	 *
	 * @param int   $form_id    The form post ID.
	 * @param array $errors     Validation errors keyed by field name.
	 * @param array $old_values Previously submitted values for repopulation.
	 * @return string The complete form HTML.
	 */
	public function render( int $form_id, array $errors = [], array $old_values = [] ): string {
		$form = $this->form_repository->get_form( $form_id );

		if ( null === $form ) {
			return '<!-- leastudios-forms: form not found -->';
		}

		$fields   = $this->form_repository->get_fields( $form_id );
		$settings = $this->form_repository->get_settings( $form_id );

		/**
		 * Fires before form HTML output.
		 *
		 * @param int   $form_id  The form post ID.
		 * @param array $fields   The form field configurations.
		 * @param object $settings The form settings.
		 */
		do_action( 'leastudios_forms_before_render', $form_id, $fields, $settings );

		$attributes = [
			'class'        => 'leastudios-form',
			'id'           => 'leastudios-form-' . $form_id,
			'data-form-id' => (string) $form_id,
			'method'       => 'post',
			'novalidate'   => 'novalidate',
		];

		/**
		 * Filter the form tag attributes.
		 *
		 * @param array $attributes Key-value pairs of form tag attributes.
		 * @param int   $form_id    The form post ID.
		 * @return array Filtered attributes.
		 */
		$form_attributes = apply_filters(
			'leastudios_forms_form_attributes',
			$attributes,
			$form_id
		);

		// Reject attribute names that don't match the HTML attribute-name
		// grammar. esc_attr does not prevent a malicious filter from
		// returning `onclick=alert(1) data-foo` as a single key — restrict
		// to alphanumerics, dashes, underscores, and colons (for namespaced
		// attributes like `data-*` and `xml:lang`).
		$form_attrs_html = '';
		foreach ( $form_attributes as $attr_name => $attr_value ) {
			if ( ! is_string( $attr_name ) || 1 !== preg_match( '/^[A-Za-z_:][A-Za-z0-9_:.\-]*$/', $attr_name ) ) {
				continue;
			}
			$form_attrs_html .= ' ' . esc_attr( $attr_name ) . '="' . esc_attr( (string) $attr_value ) . '"';
		}

		ob_start();
		?>
		<form<?php echo $form_attrs_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>>
			<input type="hidden" name="form_id" value="<?php echo esc_attr( (string) $form_id ); ?>" />
			<?php wp_nonce_field( 'leastudios_forms_submit_' . $form_id ); ?>

			<?php
			foreach ( $fields as $field_config ) :
				$type_slug  = $field_config['type'] ?? '';
				$field_name = $field_config['name'] ?? '';
				$field_type = $this->field_registry->get( $type_slug );

				if ( null === $field_type || '' === $field_name ) {
					continue;
				}

				$value     = $old_values[ $field_name ] ?? null;
				$has_error = isset( $errors[ $field_name ] );
				?>
				<?php
				$field_html = sprintf(
					'<div class="leastudios-form-field leastudios-form-field--%s" data-field-name="%s">%s<span class="field-error%s" role="alert">%s</span></div>',
					esc_attr( $type_slug ),
					esc_attr( $field_name ),
					wp_kses( $field_type->render( $field_config, $value ), self::ALLOWED_FIELD_HTML ),
					$has_error ? ' visible' : '',
					$has_error ? esc_html( $errors[ $field_name ] ) : ''
				);

				/**
				 * Filter individual field HTML output.
				 *
				 * @param string $html         The field HTML.
				 * @param array  $field_config The field configuration.
				 * @param mixed  $value        The current field value.
				 * @param int    $form_id      The form post ID.
				 * @return string Filtered HTML.
				 */
				$field_html = apply_filters( 'leastudios_forms_render_field', $field_html, $field_config, $value, $form_id );

				echo $field_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in field rendering.
				?>
			<?php endforeach; ?>

			<?php if ( $settings->honeypot_enabled ) : ?>
				<?php echo wp_kses( $this->honeypot->render(), self::ALLOWED_FIELD_HTML ); ?>
			<?php endif; ?>

			<div class="leastudios-form-submit">
				<button type="submit" class="leastudios-form-button" data-original-text="<?php echo esc_attr( $settings->submit_button_text ); ?>"><?php echo esc_html( $settings->submit_button_text ); ?></button>
			</div>
		</form>
		<?php

		/**
		 * Fires after form HTML output.
		 *
		 * @param int $form_id The form post ID.
		 */
		do_action( 'leastudios_forms_after_render', $form_id );

		return (string) ob_get_clean();
	}
}

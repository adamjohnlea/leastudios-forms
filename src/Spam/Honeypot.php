<?php
/**
 * Honeypot spam protection.
 *
 * @package LEAStudios\Forms\Spam
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Spam;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Renders and checks a honeypot field for spam detection.
 */
class Honeypot {

	/**
	 * Render the honeypot field HTML.
	 *
	 * @return string The hidden honeypot input HTML.
	 */
	public function render(): string {
		return '<div class="leastudios-form-hp"><input type="text" name="_leastudios_forms_hp" value="" aria-hidden="true" tabindex="-1" autocomplete="off" /></div>';
	}

	/**
	 * Check if the honeypot value indicates spam.
	 *
	 * Treats the field as a presence check: a real browser-rendered form
	 * always posts the input (with an empty value), so a missing field
	 * (`null`) is itself a spam signal — typical of bots that POST
	 * directly without scraping the rendered HTML. Any non-empty value
	 * means a bot wrote into the hidden field.
	 *
	 * @param string|null $value The submitted honeypot value, or null if
	 *                           the field was absent from the request.
	 * @return bool True if the submission is spam.
	 */
	public function is_spam( ?string $value ): bool {
		if ( null === $value ) {
			return true;
		}

		return '' !== $value;
	}
}

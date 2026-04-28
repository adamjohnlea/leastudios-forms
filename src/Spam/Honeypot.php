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
	 * @param string $value The submitted honeypot value.
	 * @return bool True if the submission is spam.
	 */
	public function is_spam( string $value ): bool {
		return '' !== $value;
	}
}

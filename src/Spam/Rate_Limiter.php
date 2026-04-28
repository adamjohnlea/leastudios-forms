<?php
/**
 * Rate limiter for form submissions.
 *
 * @package LEAStudios\Forms\Spam
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Spam;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Limits form submission frequency using WordPress transients.
 */
class Rate_Limiter {

	/**
	 * Check if a submission is allowed within rate limits.
	 *
	 * @param string $ip              The submitter's IP address.
	 * @param int    $form_id         The form post ID.
	 * @param int    $max_submissions Maximum submissions allowed per window.
	 * @param int    $window_seconds  The time window in seconds.
	 * @return bool True if allowed, false if rate exceeded.
	 */
	public function check( string $ip, int $form_id, int $max_submissions, int $window_seconds ): bool {
		$hashed_ip     = hash( 'sha256', $ip );
		$transient_key = "leastudios_forms_rate_{$hashed_ip}_{$form_id}";
		$count         = (int) get_transient( $transient_key );

		if ( $count >= $max_submissions ) {
			return false;
		}

		set_transient( $transient_key, $count + 1, $window_seconds );

		return true;
	}
}

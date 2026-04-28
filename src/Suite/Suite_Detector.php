<?php
/**
 * Suite plugin detection utility.
 *
 * @package LEAStudios\Forms\Suite
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Suite;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Detects active leaStudios suite plugins.
 */
class Suite_Detector {

	/**
	 * Mapping of suite plugin slugs to their main file paths.
	 *
	 * @var array<string, string>
	 */
	public const PLUGINS = [
		'leastudios-mailer' => 'leastudios-mailer/leastudios-mailer.php',
		'leastudios-forms'  => 'leastudios-forms/leastudios-forms.php',
	];

	/**
	 * Check if a suite plugin is active.
	 *
	 * @param string $slug The plugin slug.
	 * @return bool Whether the plugin is active.
	 */
	public static function is_active( string $slug ): bool {
		if ( ! isset( self::PLUGINS[ $slug ] ) ) {
			return false;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( self::PLUGINS[ $slug ] );
	}

	/**
	 * Get all active suite plugin slugs.
	 *
	 * @return string[] Array of active plugin slugs.
	 */
	public static function get_active_suite_plugins(): array {
		$active = [];

		foreach ( array_keys( self::PLUGINS ) as $slug ) {
			if ( self::is_active( $slug ) ) {
				$active[] = $slug;
			}
		}

		return $active;
	}
}

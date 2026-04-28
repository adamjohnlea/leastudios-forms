<?php
/**
 * Entry status enumeration.
 *
 * @package LEAStudios\Forms\Entry
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Entry;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Represents the status of a form entry.
 */
enum Entry_Status: string {

	case Unread  = 'unread';
	case Read    = 'read';
	case Trashed = 'trashed';

	/**
	 * Get the translatable label for this status.
	 *
	 * @return string
	 */
	public function label(): string {
		// phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- valid in PHP 8.1+ enums.
		return match ( $this ) {
			self::Unread  => __( 'Unread', 'leastudios-forms' ),
			self::Read    => __( 'Read', 'leastudios-forms' ),
			self::Trashed => __( 'Trashed', 'leastudios-forms' ),
		};
	}
}

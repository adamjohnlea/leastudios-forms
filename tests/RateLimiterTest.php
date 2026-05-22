<?php
/**
 * Tests for Rate_Limiter.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\Spam\Rate_Limiter;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Spam\Rate_Limiter
 */
class RateLimiterTest extends TestCase {

	private Rate_Limiter $limiter;

	public function set_up(): void {
		parent::set_up();
		$this->limiter = new Rate_Limiter();
	}

	public function test_allows_submissions_under_the_limit(): void {
		$this->assertTrue( $this->limiter->check( '203.0.113.1', 1, 2, 60 ) );
		$this->assertTrue( $this->limiter->check( '203.0.113.1', 1, 2, 60 ) );
		$this->assertFalse( $this->limiter->check( '203.0.113.1', 1, 2, 60 ) );
	}

	public function test_blocks_once_the_limit_is_reached(): void {
		$this->limiter->check( '203.0.113.2', 1, 2, 60 );
		$this->limiter->check( '203.0.113.2', 1, 2, 60 );

		$this->assertFalse( $this->limiter->check( '203.0.113.2', 1, 2, 60 ) );
	}

	public function test_counter_is_isolated_per_ip(): void {
		$this->limiter->check( '203.0.113.3', 1, 1, 60 );

		$this->assertFalse( $this->limiter->check( '203.0.113.3', 1, 1, 60 ) );
		$this->assertTrue( $this->limiter->check( '203.0.113.4', 1, 1, 60 ) );
	}

	public function test_counter_is_isolated_per_form(): void {
		$this->limiter->check( '203.0.113.5', 1, 1, 60 );

		$this->assertFalse( $this->limiter->check( '203.0.113.5', 1, 1, 60 ) );
		$this->assertTrue( $this->limiter->check( '203.0.113.5', 2, 1, 60 ) );
	}
}

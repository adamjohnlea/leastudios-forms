<?php
/**
 * Tests for Form_Repository.
 *
 * @package LEAStudios\Forms\Tests
 */

declare(strict_types=1);

namespace LEAStudios\Forms\Tests;

use LEAStudios\Forms\CPT\Form_Post_Type;
use LEAStudios\Forms\Form\Form_Repository;
use LEAStudios\Forms\Form\Form_Settings;
use LEAStudios\Tests\TestCase;

/**
 * @covers \LEAStudios\Forms\Form\Form_Repository
 */
class FormRepositoryTest extends TestCase {

	private Form_Repository $repo;

	public function set_up(): void {
		parent::set_up();
		$this->repo = new Form_Repository();
	}

	private function make_form(): int {
		return self::factory()->post->create(
			[
				'post_type'  => 'leastudios_form',
				'post_title' => 'A Form',
			]
		);
	}

	public function test_get_form_returns_post_for_form_cpt(): void {
		$id   = $this->make_form();
		$form = $this->repo->get_form( $id );

		$this->assertNotNull( $form );
		$this->assertSame( $id, $form->ID );
	}

	public function test_get_form_returns_null_for_non_form_post(): void {
		$post_id = self::factory()->post->create( [ 'post_type' => 'post' ] );

		$this->assertNull( $this->repo->get_form( $post_id ) );
	}

	public function test_get_form_returns_null_for_missing_id(): void {
		$this->assertNull( $this->repo->get_form( 99999999 ) );
	}

	public function test_save_and_get_fields_round_trip(): void {
		$id     = $this->make_form();
		$fields = [
			[
				'id'   => 'name',
				'type' => 'text',
			],
			[
				'id'   => 'email',
				'type' => 'email',
			],
		];

		$this->assertTrue( $this->repo->save_fields( $id, $fields ) );
		$this->assertSame( $fields, $this->repo->get_fields( $id ) );
	}

	public function test_get_fields_returns_empty_array_when_unset(): void {
		$this->assertSame( [], $this->repo->get_fields( $this->make_form() ) );
	}

	public function test_get_fields_returns_empty_array_for_non_json_meta(): void {
		$id = $this->make_form();
		update_post_meta( $id, Form_Post_Type::FIELDS_META_KEY, 'not-json' );

		$this->assertSame( [], $this->repo->get_fields( $id ) );
	}

	public function test_save_and_get_settings_round_trip(): void {
		$id       = $this->make_form();
		$settings = new Form_Settings( success_message: 'Done', rate_limit: 9 );

		$this->assertTrue( $this->repo->save_settings( $id, $settings ) );

		$loaded = $this->repo->get_settings( $id );
		$this->assertSame( 'Done', $loaded->success_message );
		$this->assertSame( 9, $loaded->rate_limit );
	}

	public function test_get_settings_returns_defaults_when_unset(): void {
		$loaded = $this->repo->get_settings( $this->make_form() );

		$this->assertSame( 'Thank you for your submission.', $loaded->success_message );
		$this->assertSame( 5, $loaded->rate_limit );
	}

	public function test_get_all_forms_returns_forms_sorted_by_title(): void {
		self::factory()->post->create(
			[
				'post_type'   => 'leastudios_form',
				'post_title'  => 'Zebra',
				'post_status' => 'publish',
			]
		);
		self::factory()->post->create(
			[
				'post_type'   => 'leastudios_form',
				'post_title'  => 'Apple',
				'post_status' => 'draft',
			]
		);

		$forms  = $this->repo->get_all_forms();
		$titles = wp_list_pluck( $forms, 'post_title' );

		$this->assertContains( 'Apple', $titles );
		$this->assertContains( 'Zebra', $titles );
		$this->assertLessThan(
			array_search( 'Zebra', $titles, true ),
			array_search( 'Apple', $titles, true ),
			'Forms should be ordered by title ascending.'
		);
	}
}

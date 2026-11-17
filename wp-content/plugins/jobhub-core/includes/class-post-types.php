<?php
namespace JobHub;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `job` and `job_application` post types, the job taxonomies
 * and the custom `expired` post status.
 */
class Post_Types {

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register' ] );
	}

	public static function register(): void {
		register_post_type( 'job', [
			'labels'          => [
				'name'          => __( 'Jobs', 'jobhub' ),
				'singular_name' => __( 'Job', 'jobhub' ),
				'add_new_item'  => __( 'Add New Job', 'jobhub' ),
				'edit_item'     => __( 'Edit Job', 'jobhub' ),
				'all_items'     => __( 'All Jobs', 'jobhub' ),
				'search_items'  => __( 'Search Jobs', 'jobhub' ),
				'not_found'     => __( 'No jobs found', 'jobhub' ),
			],
			'public'          => true,
			'has_archive'     => 'jobs',
			'rewrite'         => [ 'slug' => 'jobs', 'with_front' => false ],
			'menu_icon'       => 'dashicons-businessman',
			'menu_position'   => 5,
			'supports'        => [ 'title', 'editor', 'excerpt', 'author', 'thumbnail', 'custom-fields' ],
			'show_in_rest'    => true,
			'capability_type' => [ 'job', 'jobs' ],
			'map_meta_cap'    => true,
		] );

		register_post_type( 'job_application', [
			'labels'          => [
				'name'          => __( 'Applications', 'jobhub' ),
				'singular_name' => __( 'Application', 'jobhub' ),
				'edit_item'     => __( 'View Application', 'jobhub' ),
				'all_items'     => __( 'Applications', 'jobhub' ),
			],
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'edit.php?post_type=job',
			'supports'        => [ 'title', 'editor' ],
			'capability_type' => 'post',
			'capabilities'    => [ 'create_posts' => 'do_not_allow' ],
			'map_meta_cap'    => true,
		] );

		$taxonomies = [
			'job_category' => [ __( 'Categories', 'jobhub' ), __( 'Category', 'jobhub' ), 'job-category', true ],
			'job_type'     => [ __( 'Job Types', 'jobhub' ), __( 'Job Type', 'jobhub' ), 'job-type', true ],
			'job_location' => [ __( 'Locations', 'jobhub' ), __( 'Location', 'jobhub' ), 'job-location', true ],
		];

		foreach ( $taxonomies as $tax => [ $plural, $singular, $slug, $hierarchical ] ) {
			register_taxonomy( $tax, 'job', [
				'labels'            => [ 'name' => $plural, 'singular_name' => $singular ],
				'hierarchical'      => $hierarchical,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => [ 'slug' => $slug ],
				'capabilities'      => [
					'manage_terms' => 'manage_categories',
					'edit_terms'   => 'manage_categories',
					'delete_terms' => 'manage_categories',
					'assign_terms' => 'edit_jobs',
				],
			] );
		}

		register_post_status( 'expired', [
			'label'                     => _x( 'Expired', 'post status', 'jobhub' ),
			'public'                    => false,
			'protected'                 => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: number of expired jobs */
			'label_count'               => _n_noop( 'Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'jobhub' ),
		] );
	}
}

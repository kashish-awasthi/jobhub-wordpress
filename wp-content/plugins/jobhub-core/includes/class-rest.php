<?php
namespace JobHub;

defined( 'ABSPATH' ) || exit;

/**
 * Custom REST API, ready for a headless / mobile frontend.
 *
 *   GET /wp-json/jobhub/v1/jobs              list + filters (public)
 *   GET /wp-json/jobhub/v1/jobs/{id}         single job (public)
 *   GET /wp-json/jobhub/v1/stats             counts per category / type (public)
 *   GET /wp-json/jobhub/v1/me/applications   applications to your jobs (employer auth)
 */
class Rest {

	const NS = 'jobhub/v1';

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
	}

	public static function routes(): void {
		$filter_args = [];
		foreach ( Query::PARAMS as $p ) {
			$filter_args[ $p ] = [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ];
		}
		$filter_args['sort']['enum'] = [ '', 'newest', 'oldest', 'salary' ];
		$filter_args['page']         = [ 'type' => 'integer', 'default' => 1, 'minimum' => 1 ];
		$filter_args['per_page']     = [ 'type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 50 ];

		register_rest_route( self::NS, '/jobs', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'list_jobs' ],
			'permission_callback' => '__return_true',
			'args'                => $filter_args,
		] );

		register_rest_route( self::NS, '/jobs/(?P<id>\d+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'get_job' ],
			'permission_callback' => '__return_true',
			'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
		] );

		register_rest_route( self::NS, '/stats', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'stats' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NS, '/me/applications', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'my_applications' ],
			'permission_callback' => static fn() => current_user_can( 'edit_jobs' ),
		] );
	}

	public static function list_jobs( \WP_REST_Request $req ): \WP_REST_Response {
		$params = [];
		foreach ( Query::PARAMS as $p ) {
			$params[ $p ] = (string) $req->get_param( $p );
		}
		$q = new \WP_Query( Query::args( $params, (int) $req['page'], (int) $req['per_page'] ) );

		$res = rest_ensure_response( array_map( [ __CLASS__, 'prepare' ], $q->posts ) );
		$res->header( 'X-WP-Total', (string) $q->found_posts );
		$res->header( 'X-WP-TotalPages', (string) $q->max_num_pages );
		return $res;
	}

	public static function get_job( \WP_REST_Request $req ) {
		$post = get_post( (int) $req['id'] );
		if ( ! $post || 'job' !== $post->post_type || 'publish' !== $post->post_status ) {
			return new \WP_Error( 'jobhub_not_found', __( 'Job not found.', 'jobhub' ), [ 'status' => 404 ] );
		}
		$data                = self::prepare( $post );
		$data['description'] = apply_filters( 'the_content', $post->post_content );
		return rest_ensure_response( $data );
	}

	public static function prepare( \WP_Post $post ): array {
		$terms = static function ( string $tax ) use ( $post ): array {
			$t = get_the_terms( $post, $tax );
			return $t && ! is_wp_error( $t )
				? array_map( static fn( $term ) => [ 'id' => $term->term_id, 'name' => $term->name, 'slug' => $term->slug ], $t )
				: [];
		};

		return [
			'id'        => $post->ID,
			'title'     => get_the_title( $post ),
			'slug'      => $post->post_name,
			'link'      => get_permalink( $post ),
			'date'      => mysql_to_rfc3339( $post->post_date_gmt ),
			'excerpt'   => wp_strip_all_tags( get_the_excerpt( $post ) ),
			'company'   => [
				'name'    => (string) jobhub_get( $post->ID, 'company' ),
				'website' => (string) jobhub_get( $post->ID, 'company_url' ),
			],
			'salary'    => [
				'min'   => (int) jobhub_get( $post->ID, 'salary_min' ) ?: null,
				'max'   => (int) jobhub_get( $post->ID, 'salary_max' ) ?: null,
				'label' => jobhub_salary( $post->ID ),
			],
			'remote'    => (bool) jobhub_get( $post->ID, 'remote' ),
			'featured'  => (bool) jobhub_get( $post->ID, 'featured' ),
			'deadline'  => jobhub_get( $post->ID, 'deadline' ) ?: null,
			'category'  => $terms( 'job_category' ),
			'type'      => $terms( 'job_type' ),
			'location'  => $terms( 'job_location' ),
		];
	}

	public static function stats(): \WP_REST_Response {
		$cached = get_transient( 'jobhub_stats' );
		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		$out = [ 'total_jobs' => (int) ( wp_count_posts( 'job' )->publish ?? 0 ) ];
		foreach ( [ 'job_category', 'job_type', 'job_location' ] as $tax ) {
			$terms       = get_terms( [ 'taxonomy' => $tax, 'hide_empty' => true ] );
			$out[ $tax ] = is_wp_error( $terms ) ? [] : array_map(
				static fn( $t ) => [ 'name' => $t->name, 'slug' => $t->slug, 'count' => (int) $t->count ],
				$terms
			);
		}

		set_transient( 'jobhub_stats', $out, HOUR_IN_SECONDS );
		return rest_ensure_response( $out );
	}

	public static function my_applications(): \WP_REST_Response {
		$job_ids = get_posts( [
			'post_type'      => 'job',
			'post_status'    => 'any',
			'author'         => get_current_user_id(),
			'fields'         => 'ids',
			'posts_per_page' => -1,
		] );

		if ( ! $job_ids ) {
			return rest_ensure_response( [] );
		}

		$apps = get_posts( [
			'post_type'      => 'job_application',
			'post_status'    => 'any',
			'posts_per_page' => 100,
			'meta_query'     => [ [ 'key' => '_jobhub_job_id', 'value' => $job_ids, 'compare' => 'IN' ] ], // phpcs:ignore WordPress.DB.SlowDBQuery
		] );

		return rest_ensure_response( array_map( static function ( \WP_Post $a ) {
			$m = static fn( string $k ) => get_post_meta( $a->ID, '_jobhub_' . $k, true );
			return [
				'id'      => $a->ID,
				'job_id'  => (int) $m( 'job_id' ),
				'name'    => $m( 'name' ),
				'email'   => $m( 'email' ),
				'status'  => $m( 'status' ) ?: 'new',
				'date'    => mysql_to_rfc3339( $a->post_date_gmt ),
				'cv_url'  => Applications::cv_url( $a->ID ),
			];
		}, $apps ) );
	}
}

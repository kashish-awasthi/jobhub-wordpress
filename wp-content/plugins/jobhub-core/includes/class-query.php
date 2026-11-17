<?php
namespace JobHub;

defined( 'ABSPATH' ) || exit;

/**
 * One place that turns filter parameters into WP_Query args. Used by the job
 * archive (no-JS GET form), the AJAX filter and the REST API so they always
 * return the same results.
 */
class Query {

	const PARAMS = [ 'search', 'category', 'type', 'location', 'remote', 'salary', 'sort' ];

	public static function init(): void {
		add_action( 'pre_get_posts', [ __CLASS__, 'filter_archive' ] );
	}

	/** Reads and sanitises filter params from an array such as $_GET. */
	public static function params_from( array $source ): array {
		$p = [];
		foreach ( self::PARAMS as $key ) {
			$p[ $key ] = isset( $source[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $source[ $key ] ) ) : '';
		}
		return $p;
	}

	public static function args( array $p, int $paged = 1, ?int $per_page = null ): array {
		$args = [
			'post_type'      => 'job',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page ?? (int) jobhub_option( 'per_page', 10 ),
			'paged'          => max( 1, $paged ),
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery
				'relation' => 'AND',
				[
					'relation' => 'OR',
					[ 'key' => '_jobhub_filled', 'compare' => 'NOT EXISTS' ],
					[ 'key' => '_jobhub_filled', 'value' => '1', 'compare' => '!=' ],
				],
			],
			'tax_query'      => [ 'relation' => 'AND' ], // phpcs:ignore WordPress.DB.SlowDBQuery
		];

		if ( ! empty( $p['search'] ) ) {
			$args['s'] = $p['search'];
		}

		foreach ( [ 'category' => 'job_category', 'type' => 'job_type', 'location' => 'job_location' ] as $param => $tax ) {
			if ( ! empty( $p[ $param ] ) ) {
				$args['tax_query'][] = [
					'taxonomy' => $tax,
					'field'    => 'slug',
					'terms'    => array_map( 'sanitize_title', explode( ',', $p[ $param ] ) ),
				];
			}
		}

		if ( ! empty( $p['remote'] ) ) {
			$args['meta_query'][] = [ 'key' => '_jobhub_remote', 'value' => '1' ];
		}

		if ( ! empty( $p['salary'] ) ) {
			$args['meta_query'][] = [
				'key'     => '_jobhub_salary_max',
				'value'   => absint( $p['salary'] ),
				'compare' => '>=',
				'type'    => 'NUMERIC',
			];
		}

		switch ( $p['sort'] ?? '' ) {
			case 'salary':
				$args['meta_key'] = '_jobhub_salary_max'; // phpcs:ignore WordPress.DB.SlowDBQuery
				$args['orderby']  = [ 'meta_value_num' => 'DESC', 'date' => 'DESC' ];
				break;
			case 'oldest':
				$args['orderby'] = 'date';
				$args['order']   = 'ASC';
				break;
			default:
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
		}

		return apply_filters( 'jobhub_query_args', $args, $p );
	}

	/** Applies the filters to the main /jobs/ archive so it works without JavaScript. */
	public static function filter_archive( \WP_Query $q ): void {
		if ( is_admin() || ! $q->is_main_query() ) {
			return;
		}
		if ( ! $q->is_post_type_archive( 'job' ) && ! $q->is_tax( [ 'job_category', 'job_type', 'job_location' ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public filters.
		$p    = self::params_from( $_GET );
		$args = self::args( $p );

		$q->set( 'posts_per_page', $args['posts_per_page'] );
		$q->set( 'meta_query', $args['meta_query'] );
		$q->set( 'orderby', $args['orderby'] );
		if ( isset( $args['order'] ) ) {
			$q->set( 'order', $args['order'] );
		}
		if ( isset( $args['meta_key'] ) ) {
			$q->set( 'meta_key', $args['meta_key'] );
		}
		if ( count( $args['tax_query'] ) > 1 ) {
			$existing = (array) $q->get( 'tax_query' );
			$q->set( 'tax_query', array_merge( $existing, $args['tax_query'] ) );
		}
		if ( ! empty( $args['s'] ) ) {
			$q->set( 's', $args['s'] );
		}
	}
}

<?php
namespace JobHub;

defined( 'ABSPATH' ) || exit;

/**
 * Live job filtering. Returns rendered job cards so the markup is identical
 * to the server-rendered archive (one template, no duplication in JS).
 */
class Ajax {

	public static function init(): void {
		add_action( 'wp_ajax_jobhub_filter_jobs', [ __CLASS__, 'filter' ] );
		add_action( 'wp_ajax_nopriv_jobhub_filter_jobs', [ __CLASS__, 'filter' ] );
	}

	public static function filter(): void {
		check_ajax_referer( 'jobhub_filter', 'nonce' );

		$params = Query::params_from( $_POST );
		$paged  = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$query  = new \WP_Query( Query::args( $params, $paged ) );

		ob_start();
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				jobhub_render_card();
			}
		} elseif ( 1 === $paged ) {
			jobhub_template( 'no-results' );
		}
		wp_reset_postdata();

		wp_send_json_success( [
			'html'     => ob_get_clean(),
			'found'    => (int) $query->found_posts,
			'page'     => $paged,
			'maxPages' => (int) $query->max_num_pages,
		] );
	}
}

<?php
namespace JobHub;

defined( 'ABSPATH' ) || exit;

/** Registers the dynamic `jobhub/latest-jobs` block and the [jobhub_jobs] shortcode. */
class Blocks {

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register' ] );
		add_shortcode( 'jobhub_jobs', [ __CLASS__, 'jobs_shortcode' ] );
	}

	public static function register(): void {
		register_block_type( JOBHUB_DIR . 'blocks/latest-jobs' );
	}

	/** Server render for the block (also used by the shortcode). */
	public static function render_latest( array $attrs ): string {
		$args = [
			'post_type'      => 'job',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, min( 12, (int) ( $attrs['count'] ?? 4 ) ) ),
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery
				'relation' => 'OR',
				[ 'key' => '_jobhub_filled', 'compare' => 'NOT EXISTS' ],
				[ 'key' => '_jobhub_filled', 'value' => '1', 'compare' => '!=' ],
			],
		];

		if ( ! empty( $attrs['category'] ) ) {
			$args['tax_query'] = [ [ 'taxonomy' => 'job_category', 'field' => 'slug', 'terms' => sanitize_title( $attrs['category'] ) ] ]; // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		if ( ! empty( $attrs['featuredOnly'] ) ) {
			$args['meta_query'] = [ [ 'key' => '_jobhub_featured', 'value' => '1' ] ]; // phpcs:ignore WordPress.DB.SlowDBQuery
		}

		$q = new \WP_Query( $args );
		ob_start();
		if ( $q->have_posts() ) {
			echo '<div class="jh-list">';
			while ( $q->have_posts() ) {
				$q->the_post();
				jobhub_render_card();
			}
			echo '</div>';
		} else {
			jobhub_template( 'no-results' );
		}
		wp_reset_postdata();
		return (string) ob_get_clean();
	}

	public static function jobs_shortcode( $atts ): string {
		$atts = shortcode_atts( [ 'count' => 4, 'category' => '', 'featured' => '' ], $atts, 'jobhub_jobs' );
		return self::render_latest( [
			'count'        => (int) $atts['count'],
			'category'     => $atts['category'],
			'featuredOnly' => (bool) $atts['featured'],
		] );
	}
}

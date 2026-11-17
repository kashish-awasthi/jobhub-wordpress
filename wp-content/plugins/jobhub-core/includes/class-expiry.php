<?php
namespace JobHub;

defined( 'ABSPATH' ) || exit;

/** Daily WP-Cron task that moves old or past-deadline jobs to `expired`. */
class Expiry {

	const HOOK = 'jobhub_expire_jobs';

	public static function init(): void {
		add_action( self::HOOK, [ __CLASS__, 'run' ] );
		add_action( 'init', [ __CLASS__, 'schedule' ] );
		add_action( 'save_post_job', [ __CLASS__, 'clear_stats_cache' ] );
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public static function clear_stats_cache(): void {
		delete_transient( 'jobhub_stats' );
	}

	/** @return int Number of jobs expired. */
	public static function run(): int {
		$ids = get_posts( [
			'post_type'      => 'job',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => 200,
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery
				[ 'key' => '_jobhub_deadline', 'value' => current_time( 'Y-m-d' ), 'compare' => '<', 'type' => 'DATE' ],
				[ 'key' => '_jobhub_deadline', 'value' => '', 'compare' => '!=' ],
			],
		] );

		$days = (int) jobhub_option( 'expire_days', 60 );
		if ( $days > 0 ) {
			$ids = array_merge( $ids, get_posts( [
				'post_type'      => 'job',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => 200,
				'date_query'     => [ [ 'before' => $days . ' days ago' ] ],
			] ) );
		}

		$ids = array_unique( $ids );
		foreach ( $ids as $id ) {
			wp_update_post( [ 'ID' => $id, 'post_status' => 'expired' ] );
		}
		if ( $ids ) {
			self::clear_stats_cache();
		}
		return count( $ids );
	}
}

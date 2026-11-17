<?php
namespace JobHub;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the `employer` role and gives administrators the job capabilities.
 * Employers manage their jobs from the front-end dashboard, not wp-admin.
 */
class Roles {

	const EMPLOYER_CAPS = [
		'read'                   => true,
		'edit_jobs'              => true,
		'edit_published_jobs'    => true,
		'delete_jobs'            => true,
		'delete_published_jobs'  => true,
	];

	const ADMIN_CAPS = [
		'edit_jobs', 'edit_others_jobs', 'edit_published_jobs', 'edit_private_jobs',
		'publish_jobs', 'read_private_jobs', 'delete_jobs', 'delete_others_jobs',
		'delete_published_jobs', 'delete_private_jobs',
	];

	public static function init(): void {
		add_action( 'admin_init', [ __CLASS__, 'block_admin_for_employers' ] );
		add_filter( 'show_admin_bar', [ __CLASS__, 'hide_admin_bar' ] );
		add_filter( 'login_redirect', [ __CLASS__, 'login_redirect' ], 10, 3 );
	}

	public static function add_roles(): void {
		add_role( 'employer', __( 'Employer', 'jobhub' ), self::EMPLOYER_CAPS );

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::ADMIN_CAPS as $cap ) {
				$admin->add_cap( $cap );
			}
		}
	}

	public static function is_employer( ?\WP_User $user = null ): bool {
		$user = $user ?: wp_get_current_user();
		return $user->exists() && in_array( 'employer', (array) $user->roles, true );
	}

	public static function block_admin_for_employers(): void {
		if ( wp_doing_ajax() || 'admin-post.php' === ( $GLOBALS['pagenow'] ?? '' ) || ! self::is_employer() ) {
			return;
		}
		wp_safe_redirect( jobhub_page_url( 'dashboard' ) );
		exit;
	}

	public static function hide_admin_bar( bool $show ): bool {
		return self::is_employer() ? false : $show;
	}

	public static function login_redirect( $redirect_to, $requested, $user ) {
		if ( $user instanceof \WP_User && self::is_employer( $user ) ) {
			return jobhub_page_url( 'dashboard' );
		}
		return $redirect_to;
	}
}

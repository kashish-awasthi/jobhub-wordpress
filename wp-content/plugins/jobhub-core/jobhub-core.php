<?php
/**
 * Plugin Name:       JobHub Core
 * Description:       Job board engine: job listings, employer accounts, applications with CV upload, AJAX filtering, REST API and a Gutenberg block.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            JobHub
 * License:           GPL-2.0-or-later
 * Text Domain:       jobhub
 */

defined( 'ABSPATH' ) || exit;

define( 'JOBHUB_VERSION', '1.0.0' );
define( 'JOBHUB_FILE', __FILE__ );
define( 'JOBHUB_DIR', plugin_dir_path( __FILE__ ) );
define( 'JOBHUB_URL', plugin_dir_url( __FILE__ ) );

require_once JOBHUB_DIR . 'includes/helpers.php';
require_once JOBHUB_DIR . 'includes/class-post-types.php';
require_once JOBHUB_DIR . 'includes/class-job-meta.php';
require_once JOBHUB_DIR . 'includes/class-roles.php';
require_once JOBHUB_DIR . 'includes/class-query.php';
require_once JOBHUB_DIR . 'includes/class-applications.php';
require_once JOBHUB_DIR . 'includes/class-employer.php';
require_once JOBHUB_DIR . 'includes/class-ajax.php';
require_once JOBHUB_DIR . 'includes/class-rest.php';
require_once JOBHUB_DIR . 'includes/class-settings.php';
require_once JOBHUB_DIR . 'includes/class-expiry.php';
require_once JOBHUB_DIR . 'includes/class-blocks.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once JOBHUB_DIR . 'includes/class-cli.php';
}

add_action( 'plugins_loaded', static function () {
	JobHub\Post_Types::init();
	JobHub\Job_Meta::init();
	JobHub\Roles::init();
	JobHub\Query::init();
	JobHub\Applications::init();
	JobHub\Employer::init();
	JobHub\Ajax::init();
	JobHub\Rest::init();
	JobHub\Settings::init();
	JobHub\Expiry::init();
	JobHub\Blocks::init();
} );

add_action( 'wp_enqueue_scripts', static function () {
	// File modification time as version = automatic cache busting after every change.
	$ver = static fn( string $path ) => (string) ( filemtime( JOBHUB_DIR . $path ) ?: JOBHUB_VERSION );
	wp_enqueue_style( 'jobhub-core', JOBHUB_URL . 'assets/css/jobhub.css', [], $ver( 'assets/css/jobhub.css' ) );
	wp_register_script( 'jobhub-filters', JOBHUB_URL . 'assets/js/filters.js', [], $ver( 'assets/js/filters.js' ), [ 'strategy' => 'defer' ] );
	wp_localize_script( 'jobhub-filters', 'JobHubFilters', [
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'jobhub_filter' ),
	] );
} );

register_activation_hook( __FILE__, static function () {
	JobHub\Post_Types::register();
	JobHub\Roles::add_roles();
	JobHub\Expiry::schedule();
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, static function () {
	JobHub\Expiry::unschedule();
	flush_rewrite_rules();
} );

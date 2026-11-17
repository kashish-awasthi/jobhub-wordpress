<?php
/**
 * Plugin Name: Local Mailpit (dev only)
 * Description: Sends all WordPress email to the Mailpit container so you can read it at http://localhost:8025.
 */

defined( 'ABSPATH' ) || exit;

if ( 'local' === wp_get_environment_type() || getenv( 'WORDPRESS_DEBUG' ) ) {
	add_action( 'phpmailer_init', static function ( $mailer ) {
		$mailer->isSMTP();
		$mailer->Host     = 'mailpit';
		$mailer->Port     = 1025;
		$mailer->SMTPAuth = false;
	} );
	add_filter( 'wp_mail_from', static fn() => 'no-reply@jobhub.test' );
	add_filter( 'wp_mail_from_name', static fn() => 'JobHub' );
}

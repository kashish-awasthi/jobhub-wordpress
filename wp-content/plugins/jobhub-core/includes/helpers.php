<?php
/**
 * Template helpers available to themes. All output is escaped here so themes
 * can call them directly.
 */

defined( 'ABSPATH' ) || exit;

/** Meta keys stored on each job, with their sanitisation type. */
function jobhub_meta_fields(): array {
	return [
		'company'     => 'text',
		'company_url' => 'url',
		'salary_min'  => 'int',
		'salary_max'  => 'int',
		'remote'      => 'bool',
		'deadline'    => 'date',
		'apply_email' => 'email',
		'filled'      => 'bool',
		'featured'    => 'bool',
	];
}

function jobhub_get( int $job_id, string $field ) {
	return get_post_meta( $job_id, '_jobhub_' . $field, true );
}

function jobhub_option( string $key, $default = null ) {
	$options = get_option( 'jobhub_settings', [] );
	return $options[ $key ] ?? $default;
}

/** "£40k – £55k" style salary string, or empty string if unknown. */
function jobhub_salary( int $job_id ): string {
	$min = (int) jobhub_get( $job_id, 'salary_min' );
	$max = (int) jobhub_get( $job_id, 'salary_max' );
	$cur = jobhub_option( 'currency', '$' );

	$fmt = static fn( int $n ) => $cur . ( $n >= 1000 ? round( $n / 1000 ) . 'k' : number_format_i18n( $n ) );

	if ( $min && $max ) {
		return $fmt( $min ) . ' – ' . $fmt( $max );
	}
	if ( $min ) {
		/* translators: %s: minimum salary */
		return sprintf( __( 'From %s', 'jobhub' ), $fmt( $min ) );
	}
	if ( $max ) {
		/* translators: %s: maximum salary */
		return sprintf( __( 'Up to %s', 'jobhub' ), $fmt( $max ) );
	}
	return '';
}

function jobhub_terms( int $job_id, string $taxonomy ): string {
	$terms = get_the_terms( $job_id, $taxonomy );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return '';
	}
	return implode( ', ', wp_list_pluck( $terms, 'name' ) );
}

function jobhub_company_initials( string $company ): string {
	$words = preg_split( '/\s+/', trim( $company ) ) ?: [];
	$init  = '';
	foreach ( array_slice( $words, 0, 2 ) as $w ) {
		$init .= mb_strtoupper( mb_substr( $w, 0, 1 ) );
	}
	return $init ?: 'J';
}

/** Loads a template part, letting the theme override it in /jobhub/. */
function jobhub_template( string $name, array $args = [] ): void {
	$theme_file = locate_template( 'jobhub/' . $name . '.php' );
	$file       = $theme_file ?: JOBHUB_DIR . 'templates/' . $name . '.php';
	if ( file_exists( $file ) ) {
		load_template( $file, false, $args );
	}
}

function jobhub_render_card( ?WP_Post $post = null ): void {
	$post = $post ?: get_post();
	jobhub_template( 'job-card', [ 'job' => $post ] );
}

function jobhub_render_filters(): void {
	wp_enqueue_script( 'jobhub-filters' );
	jobhub_template( 'filters' );
}

function jobhub_render_apply_form( ?WP_Post $post = null ): void {
	jobhub_template( 'apply-form', [ 'job' => $post ?: get_post() ] );
}

function jobhub_application_count( int $job_id ): int {
	$q = new WP_Query( [
		'post_type'      => 'job_application',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => '_jobhub_job_id', // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_value'     => $job_id,          // phpcs:ignore WordPress.DB.SlowDBQuery
	] );
	return (int) $q->found_posts;
}

function jobhub_page_url( string $key ): string {
	$page_id = (int) jobhub_option( $key . '_page' );
	return $page_id ? (string) get_permalink( $page_id ) : home_url( '/' );
}

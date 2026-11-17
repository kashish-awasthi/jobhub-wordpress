<?php
/**
 * JobHub theme setup.
 */

defined( 'ABSPATH' ) || exit;

define( 'JOBHUB_THEME_VERSION', '1.0.0' );

add_action( 'after_setup_theme', static function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', [ 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ] );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'custom-logo', [ 'height' => 40, 'width' => 160, 'flex-width' => true ] );
	add_theme_support( 'editor-color-palette', [
		[ 'name' => 'Primary', 'slug' => 'primary', 'color' => '#0f766e' ],
		[ 'name' => 'Ink', 'slug' => 'ink', 'color' => '#0f172a' ],
		[ 'name' => 'Accent', 'slug' => 'accent', 'color' => '#f59e0b' ],
		[ 'name' => 'Soft', 'slug' => 'soft', 'color' => '#f8fafc' ],
	] );

	register_nav_menus( [
		'primary' => __( 'Primary menu', 'jobhub-theme' ),
		'footer'  => __( 'Footer menu', 'jobhub-theme' ),
	] );
} );

add_action( 'wp_enqueue_scripts', static function () {
	wp_enqueue_style( 'jobhub-font', 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap', [], null );
	$ver = static fn( string $path ) => (string) ( filemtime( get_theme_file_path( $path ) ) ?: JOBHUB_THEME_VERSION );
	wp_enqueue_style( 'jobhub-theme', get_theme_file_uri( 'assets/css/theme.css' ), [ 'jobhub-font' ], $ver( 'assets/css/theme.css' ) );
	wp_enqueue_script( 'jobhub-theme', get_theme_file_uri( 'assets/js/theme.js' ), [], $ver( 'assets/js/theme.js' ), [ 'strategy' => 'defer', 'in_footer' => true ] );
} );

add_filter( 'wp_resource_hints', static function ( array $urls, string $type ) {
	if ( 'preconnect' === $type ) {
		$urls[] = [ 'href' => 'https://fonts.gstatic.com', 'crossorigin' ];
	}
	return $urls;
}, 10, 2 );

/** The theme depends on JobHub Core; show a friendly admin notice if it's missing. */
add_action( 'admin_notices', static function () {
	if ( ! function_exists( 'jobhub_get' ) ) {
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'The JobHub theme needs the JobHub Core plugin. Please activate it.', 'jobhub-theme' ) . '</p></div>';
	}
} );

/** Fallback menu when none is assigned yet. */
function jobhub_theme_fallback_menu(): void {
	echo '<ul class="nav__list">';
	printf( '<li><a href="%s">%s</a></li>', esc_url( (string) get_post_type_archive_link( 'job' ) ), esc_html__( 'Find Jobs', 'jobhub-theme' ) );
	echo '</ul>';
}

/** Live job count for the hero. */
function jobhub_theme_job_count(): int {
	$counts = wp_count_posts( 'job' );
	return (int) ( $counts->publish ?? 0 );
}

/** Builds a "BreadcrumbList"-free, simple JSON-LD JobPosting for SEO on single jobs. */
add_action( 'wp_head', static function () {
	if ( ! is_singular( 'job' ) || ! function_exists( 'jobhub_get' ) ) {
		return;
	}
	$id     = get_the_ID();
	$schema = [
		'@context'           => 'https://schema.org/',
		'@type'              => 'JobPosting',
		'title'              => get_the_title(),
		'description'        => wp_strip_all_tags( get_post_field( 'post_content', $id ) ),
		'datePosted'         => get_the_date( 'c' ),
		'hiringOrganization' => [ '@type' => 'Organization', 'name' => jobhub_get( $id, 'company' ), 'sameAs' => jobhub_get( $id, 'company_url' ) ],
		'jobLocation'        => [ '@type' => 'Place', 'address' => [ '@type' => 'PostalAddress', 'addressLocality' => jobhub_terms( $id, 'job_location' ) ] ],
		'employmentType'     => strtoupper( str_replace( '-', '_', sanitize_title( jobhub_terms( $id, 'job_type' ) ) ) ),
	];
	if ( jobhub_get( $id, 'deadline' ) ) {
		$schema['validThrough'] = jobhub_get( $id, 'deadline' );
	}
	if ( jobhub_get( $id, 'remote' ) ) {
		$schema['jobLocationType'] = 'TELECOMMUTE';
	}
	if ( jobhub_get( $id, 'salary_min' ) ) {
		$schema['baseSalary'] = [
			'@type'    => 'MonetaryAmount',
			'currency' => 'USD',
			'value'    => [ '@type' => 'QuantitativeValue', 'minValue' => (int) jobhub_get( $id, 'salary_min' ), 'maxValue' => (int) jobhub_get( $id, 'salary_max' ), 'unitText' => 'YEAR' ],
		];
	}
	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
} );

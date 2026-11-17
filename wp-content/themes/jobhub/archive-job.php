<?php
/**
 * Job listings archive (/jobs/) and job taxonomy archives.
 */
get_header();

global $wp_query;
$title = is_tax() ? single_term_title( '', false ) . ' ' . __( 'jobs', 'jobhub-theme' ) : __( 'Find your next job', 'jobhub-theme' );
?>

<section class="page-head">
	<div class="container">
		<h1><?php echo esc_html( $title ); ?></h1>
		<p class="page-head__sub" data-jobhub-count aria-live="polite"><?php
			/* translators: %d: number of jobs */
			printf( esc_html( _n( '%d job', '%d jobs', (int) $wp_query->found_posts, 'jobhub-theme' ) ), (int) $wp_query->found_posts );
		?></p>
	</div>
</section>

<div class="container listings">
	<?php
	if ( function_exists( 'jobhub_render_filters' ) ) {
		jobhub_render_filters();
	}
	?>

	<div class="jh-list" data-jobhub-results data-max-pages="<?php echo (int) $wp_query->max_num_pages; ?>">
		<?php
		if ( have_posts() ) {
			while ( have_posts() ) {
				the_post();
				jobhub_render_card();
			}
		} else {
			jobhub_template( 'no-results' );
		}
		?>
	</div>

	<div class="listings__more">
		<button type="button" class="jh-btn jh-btn--ghost" data-jobhub-more hidden><?php esc_html_e( 'Load more jobs', 'jobhub-theme' ); ?></button>
	</div>

	<nav class="pagination" data-jobhub-pagination aria-label="<?php esc_attr_e( 'Job pages', 'jobhub-theme' ); ?>">
		<?php echo paginate_links( [ 'prev_text' => '←', 'next_text' => '→' ] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</nav>
</div>

<?php
get_footer();

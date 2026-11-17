<?php get_header(); ?>
<section class="section">
	<div class="container container--narrow notfound">
		<p class="eyebrow">404</p>
		<h1><?php esc_html_e( 'This page has moved on to a new job', 'jobhub-theme' ); ?></h1>
		<p><?php esc_html_e( 'The page you were looking for could not be found.', 'jobhub-theme' ); ?></p>
		<a class="jh-btn" href="<?php echo esc_url( (string) get_post_type_archive_link( 'job' ) ); ?>"><?php esc_html_e( 'Browse jobs', 'jobhub-theme' ); ?></a>
	</div>
</section>
<?php get_footer(); ?>

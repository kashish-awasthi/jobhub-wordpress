<?php
/**
 * Fallback template (blog, search, archives).
 */
get_header();
?>
<section class="page-head">
	<div class="container"><h1><?php echo esc_html( is_search() ? sprintf( /* translators: %s: query */ __( 'Results for “%s”', 'jobhub-theme' ), get_search_query() ) : wp_strip_all_tags( get_the_archive_title() ?: __( 'Blog', 'jobhub-theme' ) ) ); ?></h1></div>
</section>
<div class="container container--narrow">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<article <?php post_class( 'post-item' ); ?>>
				<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
				<p class="jh-muted"><?php echo esc_html( get_the_date() ); ?></p>
				<?php the_excerpt(); ?>
			</article>
		<?php endwhile; ?>
		<nav class="pagination"><?php echo paginate_links(); // phpcs:ignore WordPress.Security.EscapeOutput ?></nav>
	<?php else : ?>
		<div class="jh-empty"><?php esc_html_e( 'Nothing found.', 'jobhub-theme' ); ?></div>
	<?php endif; ?>
</div>
<?php
get_footer();

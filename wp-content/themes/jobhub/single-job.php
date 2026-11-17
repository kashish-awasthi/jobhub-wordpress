<?php
/**
 * Single job page.
 */
get_header();

while ( have_posts() ) :
	the_post();
	$id       = get_the_ID();
	$company  = (string) jobhub_get( $id, 'company' );
	$site     = (string) jobhub_get( $id, 'company_url' );
	$salary   = jobhub_salary( $id );
	$location = jobhub_terms( $id, 'job_location' );
	$type     = jobhub_terms( $id, 'job_type' );
	$category = jobhub_terms( $id, 'job_category' );
	$remote   = (bool) jobhub_get( $id, 'remote' );
	$deadline = (string) jobhub_get( $id, 'deadline' );
	$filled   = (bool) jobhub_get( $id, 'filled' );
	$hue      = abs( crc32( $company ) ) % 360;
	?>

	<section class="job-hero">
		<div class="container">
			<a class="back-link" href="<?php echo esc_url( (string) get_post_type_archive_link( 'job' ) ); ?>">&larr; <?php esc_html_e( 'All jobs', 'jobhub-theme' ); ?></a>
			<div class="job-hero__row">
				<div class="jh-job__logo jh-job__logo--lg" style="--hue: <?php echo (int) $hue; ?>" aria-hidden="true"><?php echo esc_html( jobhub_company_initials( $company ) ); ?></div>
				<div>
					<h1 class="job-hero__title"><?php the_title(); ?></h1>
					<p class="job-hero__company">
						<?php if ( $site ) : ?>
							<a href="<?php echo esc_url( $site ); ?>" rel="noopener nofollow" target="_blank"><?php echo esc_html( $company ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $company ); ?>
						<?php endif; ?>
						<?php if ( $location ) : ?> · <?php echo esc_html( $location ); ?><?php endif; ?>
					</p>
					<ul class="jh-tags">
						<?php if ( $filled ) : ?><li class="jh-tag jh-tag--closed"><?php esc_html_e( 'Position filled', 'jobhub-theme' ); ?></li><?php endif; ?>
						<?php if ( $type ) : ?><li class="jh-tag"><?php echo esc_html( $type ); ?></li><?php endif; ?>
						<?php if ( $category ) : ?><li class="jh-tag"><?php echo esc_html( $category ); ?></li><?php endif; ?>
						<?php if ( $remote ) : ?><li class="jh-tag jh-tag--remote"><?php esc_html_e( 'Remote', 'jobhub-theme' ); ?></li><?php endif; ?>
					</ul>
				</div>
				<?php if ( ! $filled ) : ?>
					<a class="jh-btn job-hero__apply" href="#apply"><?php esc_html_e( 'Apply now', 'jobhub-theme' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<div class="container job-layout">
		<article class="job-content entry">
			<?php the_content(); ?>
		</article>

		<aside class="job-side">
			<div class="jh-card job-facts">
				<h2 class="job-facts__title"><?php esc_html_e( 'Job overview', 'jobhub-theme' ); ?></h2>
				<dl>
					<?php if ( $salary ) : ?><div><dt><?php esc_html_e( 'Salary', 'jobhub-theme' ); ?></dt><dd><?php echo esc_html( $salary ); ?> / <?php esc_html_e( 'year', 'jobhub-theme' ); ?></dd></div><?php endif; ?>
					<?php if ( $type ) : ?><div><dt><?php esc_html_e( 'Job type', 'jobhub-theme' ); ?></dt><dd><?php echo esc_html( $type ); ?></dd></div><?php endif; ?>
					<?php if ( $location ) : ?><div><dt><?php esc_html_e( 'Location', 'jobhub-theme' ); ?></dt><dd><?php echo esc_html( $location . ( $remote ? ' / ' . __( 'Remote', 'jobhub-theme' ) : '' ) ); ?></dd></div><?php endif; ?>
					<div><dt><?php esc_html_e( 'Posted', 'jobhub-theme' ); ?></dt><dd><?php echo esc_html( get_the_date() ); ?></dd></div>
					<?php if ( $deadline ) : ?><div><dt><?php esc_html_e( 'Apply by', 'jobhub-theme' ); ?></dt><dd><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $deadline ) ) ); ?></dd></div><?php endif; ?>
				</dl>
			</div>
			<?php jobhub_render_apply_form(); ?>
		</aside>
	</div>

	<?php
	$related = new WP_Query( [
		'post_type'      => 'job',
		'posts_per_page' => 3,
		'post__not_in'   => [ $id ],
		'tax_query'      => [ [ 'taxonomy' => 'job_category', 'field' => 'name', 'terms' => explode( ', ', $category ) ] ], // phpcs:ignore WordPress.DB.SlowDBQuery
	] );
	if ( $category && $related->have_posts() ) :
		?>
		<section class="section section--soft">
			<div class="container">
				<div class="section__head"><h2><?php esc_html_e( 'Similar jobs', 'jobhub-theme' ); ?></h2></div>
				<div class="jh-list">
					<?php
					while ( $related->have_posts() ) {
						$related->the_post();
						jobhub_render_card();
					}
					wp_reset_postdata();
					?>
				</div>
			</div>
		</section>
	<?php endif; ?>

<?php endwhile; ?>

<?php
get_footer();

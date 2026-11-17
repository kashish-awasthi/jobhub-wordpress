<?php
/**
 * Homepage: hero search, categories, latest jobs and employer CTA.
 */
get_header();

$archive   = (string) get_post_type_archive_link( 'job' );
$has_core  = function_exists( 'jobhub_render_card' );
$locations = $has_core ? get_terms( [ 'taxonomy' => 'job_location', 'hide_empty' => true ] ) : [];
$cats      = $has_core ? get_terms( [ 'taxonomy' => 'job_category', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 6 ] ) : [];
$icons     = [ 'engineering' => '⌘', 'design' => '✎', 'marketing' => '◎', 'data-ai' => '◆', 'sales' => '↗', 'customer-support' => '☺' ];
?>

<section class="hero">
	<div class="container hero__inner">
		<p class="eyebrow"><?php
			/* translators: %d: number of jobs */
			printf( esc_html__( '%d open roles this week', 'jobhub-theme' ), (int) jobhub_theme_job_count() );
		?></p>
		<h1 class="hero__title"><?php esc_html_e( 'Find work you will', 'jobhub-theme' ); ?> <span class="hl"><?php esc_html_e( 'actually love', 'jobhub-theme' ); ?></span></h1>
		<p class="hero__lead"><?php esc_html_e( 'Hand-picked jobs from teams that care about their people. Search by skill, location or salary and apply in under two minutes.', 'jobhub-theme' ); ?></p>

		<form class="hero-search" method="get" action="<?php echo esc_url( $archive ); ?>" role="search">
			<label class="screen-reader-text" for="hero-q"><?php esc_html_e( 'Job title or keyword', 'jobhub-theme' ); ?></label>
			<input id="hero-q" type="search" name="search" placeholder="<?php esc_attr_e( 'Job title, skill or company', 'jobhub-theme' ); ?>">
			<?php if ( $locations && ! is_wp_error( $locations ) ) : ?>
				<label class="screen-reader-text" for="hero-loc"><?php esc_html_e( 'Location', 'jobhub-theme' ); ?></label>
				<select id="hero-loc" name="location">
					<option value=""><?php esc_html_e( 'Any location', 'jobhub-theme' ); ?></option>
					<?php foreach ( $locations as $loc ) : ?>
						<option value="<?php echo esc_attr( $loc->slug ); ?>"><?php echo esc_html( $loc->name ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
			<button class="jh-btn" type="submit"><?php esc_html_e( 'Search jobs', 'jobhub-theme' ); ?></button>
		</form>

		<ul class="hero__popular">
			<li><?php esc_html_e( 'Popular:', 'jobhub-theme' ); ?></li>
			<?php foreach ( [ 'WordPress', 'React', 'Designer', 'Remote' ] as $term ) : ?>
				<li><a href="<?php echo esc_url( 'Remote' === $term ? add_query_arg( 'remote', '1', $archive ) : add_query_arg( 'search', rawurlencode( $term ), $archive ) ); ?>"><?php echo esc_html( $term ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>

<?php if ( $cats && ! is_wp_error( $cats ) ) : ?>
<section class="section">
	<div class="container">
		<div class="section__head">
			<h2><?php esc_html_e( 'Browse by category', 'jobhub-theme' ); ?></h2>
		</div>
		<ul class="cats">
			<?php foreach ( $cats as $cat ) : ?>
				<li>
					<a class="cat" href="<?php echo esc_url( (string) get_term_link( $cat ) ); ?>">
						<span class="cat__icon" aria-hidden="true"><?php echo esc_html( $icons[ $cat->slug ] ?? '•' ); ?></span>
						<span class="cat__name"><?php echo esc_html( $cat->name ); ?></span>
						<span class="cat__count"><?php
							/* translators: %d: number of jobs */
							printf( esc_html( _n( '%d job', '%d jobs', (int) $cat->count, 'jobhub-theme' ) ), (int) $cat->count );
						?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
<?php endif; ?>

<?php if ( $has_core ) : ?>
<section class="section section--soft">
	<div class="container">
		<div class="section__head">
			<h2><?php esc_html_e( 'Latest jobs', 'jobhub-theme' ); ?></h2>
			<a href="<?php echo esc_url( $archive ); ?>"><?php esc_html_e( 'View all jobs →', 'jobhub-theme' ); ?></a>
		</div>
		<?php echo JobHub\Blocks::render_latest( [ 'count' => 6 ] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in template. ?>
	</div>
</section>
<?php endif; ?>

<section class="section">
	<div class="container steps">
		<?php
		$steps = [
			[ '1', __( 'Search', 'jobhub-theme' ), __( 'Filter by skill, location, salary and remote options. Results update as you type.', 'jobhub-theme' ) ],
			[ '2', __( 'Apply', 'jobhub-theme' ), __( 'Upload your CV and a short note. No account needed.', 'jobhub-theme' ) ],
			[ '3', __( 'Get hired', 'jobhub-theme' ), __( 'Employers review applicants in their dashboard and reach out directly.', 'jobhub-theme' ) ],
		];
		foreach ( $steps as [ $n, $title, $text ] ) :
			?>
			<div class="step">
				<span class="step__n"><?php echo esc_html( $n ); ?></span>
				<h3><?php echo esc_html( $title ); ?></h3>
				<p><?php echo esc_html( $text ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<section class="section">
	<div class="container">
		<div class="cta">
			<div>
				<h2><?php esc_html_e( 'Hiring? Reach great candidates today.', 'jobhub-theme' ); ?></h2>
				<p><?php esc_html_e( 'Post a job in minutes and manage every applicant from one simple dashboard.', 'jobhub-theme' ); ?></p>
			</div>
			<?php if ( function_exists( 'jobhub_page_url' ) ) : ?>
				<a class="jh-btn jh-btn--light" href="<?php echo esc_url( jobhub_page_url( 'submit' ) ); ?>"><?php esc_html_e( 'Post a job — it’s free', 'jobhub-theme' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</section>

<?php
get_footer();

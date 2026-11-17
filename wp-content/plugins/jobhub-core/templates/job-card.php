<?php
/**
 * Job card. Override by copying to your-theme/jobhub/job-card.php
 *
 * @var array $args { @type WP_Post $job }
 */
defined( 'ABSPATH' ) || exit;

$job      = $args['job'];
$id       = $job->ID;
$company  = (string) jobhub_get( $id, 'company' );
$salary   = jobhub_salary( $id );
$location = jobhub_terms( $id, 'job_location' );
$type     = jobhub_terms( $id, 'job_type' );
$remote   = (bool) jobhub_get( $id, 'remote' );
$featured = (bool) jobhub_get( $id, 'featured' );
$hue      = abs( crc32( $company ) ) % 360;
?>
<article class="jh-job<?php echo $featured ? ' jh-job--featured' : ''; ?>">
	<div class="jh-job__logo" style="--hue: <?php echo (int) $hue; ?>" aria-hidden="true"><?php echo esc_html( jobhub_company_initials( $company ) ); ?></div>
	<div class="jh-job__body">
		<h3 class="jh-job__title"><a href="<?php echo esc_url( (string) get_permalink( $job ) ); ?>"><?php echo esc_html( get_the_title( $job ) ); ?></a></h3>
		<p class="jh-job__company"><?php echo esc_html( $company ); ?><?php if ( $location ) : ?> · <?php echo esc_html( $location ); ?><?php endif; ?></p>
		<ul class="jh-tags">
			<?php if ( $featured ) : ?><li class="jh-tag jh-tag--featured"><?php esc_html_e( 'Featured', 'jobhub' ); ?></li><?php endif; ?>
			<?php if ( $type ) : ?><li class="jh-tag"><?php echo esc_html( $type ); ?></li><?php endif; ?>
			<?php if ( $remote ) : ?><li class="jh-tag jh-tag--remote"><?php esc_html_e( 'Remote', 'jobhub' ); ?></li><?php endif; ?>
		</ul>
	</div>
	<div class="jh-job__meta">
		<?php if ( $salary ) : ?><span class="jh-job__salary"><?php echo esc_html( $salary ); ?></span><?php endif; ?>
		<time class="jh-job__date" datetime="<?php echo esc_attr( get_the_date( 'c', $job ) ); ?>">
			<?php
			/* translators: %s: human time diff */
			printf( esc_html__( '%s ago', 'jobhub' ), esc_html( human_time_diff( get_post_time( 'U', true, $job ) ) ) );
			?>
		</time>
	</div>
</article>

<?php
/**
 * Application form shown on single job pages.
 *
 * @var array $args { @type WP_Post $job }
 */
defined( 'ABSPATH' ) || exit;

$job    = $args['job'];
$errors = JobHub\Applications::error_messages();
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
$error   = isset( $_GET['apply_error'] ) ? sanitize_key( $_GET['apply_error'] ) : '';
$applied = isset( $_GET['applied'] );
// phpcs:enable
$max_mb = (int) jobhub_option( 'max_cv_mb', 2 );
?>
<section id="apply" class="jh-card jh-apply">
	<?php if ( $applied ) : ?>
		<div class="jh-apply__done" role="status">
			<span class="jh-apply__icon" aria-hidden="true">✓</span>
			<h2><?php esc_html_e( 'Application sent!', 'jobhub' ); ?></h2>
			<p><?php esc_html_e( 'We emailed you a confirmation. Good luck!', 'jobhub' ); ?></p>
		</div>
	<?php elseif ( jobhub_get( $job->ID, 'filled' ) || 'publish' !== $job->post_status ) : ?>
		<h2><?php esc_html_e( 'This position is closed', 'jobhub' ); ?></h2>
		<p><a href="<?php echo esc_url( (string) get_post_type_archive_link( 'job' ) ); ?>"><?php esc_html_e( 'Browse open jobs', 'jobhub' ); ?></a></p>
	<?php else : ?>
		<h2><?php esc_html_e( 'Apply for this job', 'jobhub' ); ?></h2>
		<?php if ( isset( $errors[ $error ] ) ) : ?>
			<div class="jh-notice jh-notice--error" role="alert"><?php echo esc_html( $errors[ $error ] ); ?></div>
		<?php endif; ?>
		<form class="jh-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="jobhub_apply">
			<input type="hidden" name="job_id" value="<?php echo (int) $job->ID; ?>">
			<?php wp_nonce_field( 'jobhub_apply_' . $job->ID, '_jobhub_nonce' ); ?>
			<div class="jh-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

			<label><?php esc_html_e( 'Full name', 'jobhub' ); ?> *
				<input type="text" name="applicant_name" autocomplete="name" required></label>
			<div class="jh-form__row">
				<label><?php esc_html_e( 'Email', 'jobhub' ); ?> *
					<input type="email" name="applicant_email" autocomplete="email" required></label>
				<label><?php esc_html_e( 'Phone', 'jobhub' ); ?>
					<input type="tel" name="applicant_phone" autocomplete="tel"></label>
			</div>
			<label><?php esc_html_e( 'Why are you a great fit?', 'jobhub' ); ?> *
				<textarea name="cover_letter" rows="6" minlength="20" required></textarea></label>
			<label class="jh-file"><?php
				/* translators: %d: max size in MB */
				printf( esc_html__( 'CV (PDF, DOC or DOCX, max %d MB)', 'jobhub' ), (int) $max_mb );
				?> *
				<input type="file" name="cv" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required data-max-mb="<?php echo (int) $max_mb; ?>"></label>
			<button class="jh-btn jh-btn--block" type="submit"><?php esc_html_e( 'Submit application', 'jobhub' ); ?></button>
			<p class="jh-muted jh-small"><?php esc_html_e( 'Your CV is stored privately and only shared with this employer.', 'jobhub' ); ?></p>
		</form>
	<?php endif; ?>
</section>

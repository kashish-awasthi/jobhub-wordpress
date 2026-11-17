<?php
namespace JobHub;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end employer features:
 *  [jobhub_submit_job]  – register / log in, then post or edit a job
 *  [jobhub_dashboard]   – manage your jobs and review applicants
 */
class Employer {

	public static function init(): void {
		add_shortcode( 'jobhub_submit_job', [ __CLASS__, 'submit_shortcode' ] );
		add_shortcode( 'jobhub_dashboard', [ __CLASS__, 'dashboard_shortcode' ] );

		add_action( 'admin_post_nopriv_jobhub_register', [ __CLASS__, 'handle_register' ] );
		add_action( 'admin_post_jobhub_submit_job', [ __CLASS__, 'handle_submit' ] );
		add_action( 'admin_post_jobhub_job_action', [ __CLASS__, 'handle_job_action' ] );
	}

	private static function notice(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		$map = [
			'registered' => [ 'success', __( 'Welcome! Your employer account is ready. Post your first job below.', 'jobhub' ) ],
			'submitted'  => [ 'success', __( 'Your job is live.', 'jobhub' ) ],
			'pending'    => [ 'info', __( 'Thanks! Your job will go live once an admin approves it.', 'jobhub' ) ],
			'updated'    => [ 'success', __( 'Job updated.', 'jobhub' ) ],
			'filled'     => [ 'success', __( 'Job marked as filled.', 'jobhub' ) ],
			'reopened'   => [ 'success', __( 'Job reopened.', 'jobhub' ) ],
			'deleted'    => [ 'success', __( 'Job moved to trash.', 'jobhub' ) ],
		];
		$errors = [
			'nonce'    => __( 'Your session expired. Please try again.', 'jobhub' ),
			'fields'   => __( 'Please fill in all required fields.', 'jobhub' ),
			'exists'   => __( 'That username or email is already registered.', 'jobhub' ),
			'password' => __( 'Password must be at least 8 characters.', 'jobhub' ),
			'denied'   => __( 'You are not allowed to do that.', 'jobhub' ),
			'closed'   => __( 'Registration is currently closed.', 'jobhub' ),
		];
		$msg  = isset( $_GET['jobhub_msg'] ) ? sanitize_key( $_GET['jobhub_msg'] ) : '';
		$err  = isset( $_GET['jobhub_err'] ) ? sanitize_key( $_GET['jobhub_err'] ) : '';
		// phpcs:enable

		if ( isset( $map[ $msg ] ) ) {
			return sprintf( '<div class="jh-notice jh-notice--%s" role="status">%s</div>', esc_attr( $map[ $msg ][0] ), esc_html( $map[ $msg ][1] ) );
		}
		if ( isset( $errors[ $err ] ) ) {
			return sprintf( '<div class="jh-notice jh-notice--error" role="alert">%s</div>', esc_html( $errors[ $err ] ) );
		}
		return '';
	}

	private static function redirect( string $url, array $args ): void {
		wp_safe_redirect( add_query_arg( $args, $url ) );
		exit;
	}

	/* ----- Registration -------------------------------------------------- */

	public static function handle_register(): void {
		$back = wp_get_referer() ?: jobhub_page_url( 'submit' );

		if ( ! isset( $_POST['_jobhub_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_jobhub_nonce'] ), 'jobhub_register' ) ) {
			self::redirect( $back, [ 'jobhub_err' => 'nonce' ] );
		}
		if ( ! get_option( 'users_can_register' ) ) {
			self::redirect( $back, [ 'jobhub_err' => 'closed' ] );
		}

		$username = sanitize_user( wp_unslash( $_POST['username'] ?? '' ), true );
		$email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$company  = sanitize_text_field( wp_unslash( $_POST['company'] ?? '' ) );
		$password = (string) ( $_POST['password'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- passwords are not sanitised.

		if ( ! $username || ! is_email( $email ) || ! $company ) {
			self::redirect( $back, [ 'jobhub_err' => 'fields' ] );
		}
		if ( strlen( $password ) < 8 ) {
			self::redirect( $back, [ 'jobhub_err' => 'password' ] );
		}
		if ( username_exists( $username ) || email_exists( $email ) ) {
			self::redirect( $back, [ 'jobhub_err' => 'exists' ] );
		}

		$user_id = wp_insert_user( [
			'user_login'   => $username,
			'user_email'   => $email,
			'user_pass'    => $password,
			'display_name' => $company,
			'role'         => 'employer',
		] );
		if ( is_wp_error( $user_id ) ) {
			self::redirect( $back, [ 'jobhub_err' => 'fields' ] );
		}
		update_user_meta( $user_id, 'jobhub_company', $company );

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );

		self::redirect( jobhub_page_url( 'submit' ), [ 'jobhub_msg' => 'registered' ] );
	}

	private static function auth_forms(): string {
		ob_start();
		?>
		<div class="jh-auth">
			<div class="jh-card">
				<h2><?php esc_html_e( 'Employer log in', 'jobhub' ); ?></h2>
				<?php wp_login_form( [ 'redirect' => jobhub_page_url( 'submit' ) ] ); ?>
			</div>
			<?php if ( get_option( 'users_can_register' ) ) : ?>
			<div class="jh-card">
				<h2><?php esc_html_e( 'Create an employer account', 'jobhub' ); ?></h2>
				<form class="jh-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="jobhub_register">
					<?php wp_nonce_field( 'jobhub_register', '_jobhub_nonce' ); ?>
					<label><?php esc_html_e( 'Company name', 'jobhub' ); ?> <input type="text" name="company" required></label>
					<label><?php esc_html_e( 'Username', 'jobhub' ); ?> <input type="text" name="username" autocomplete="username" required></label>
					<label><?php esc_html_e( 'Work email', 'jobhub' ); ?> <input type="email" name="email" autocomplete="email" required></label>
					<label><?php esc_html_e( 'Password (8+ characters)', 'jobhub' ); ?> <input type="password" name="password" minlength="8" autocomplete="new-password" required></label>
					<button class="jh-btn" type="submit"><?php esc_html_e( 'Create account', 'jobhub' ); ?></button>
				</form>
			</div>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/* ----- Submit / edit job ------------------------------------------- */

	public static function submit_shortcode(): string {
		if ( ! is_user_logged_in() ) {
			return self::notice() . self::auth_forms();
		}
		if ( ! current_user_can( 'edit_jobs' ) ) {
			return '<div class="jh-notice jh-notice--error">' . esc_html__( 'Only employer accounts can post jobs.', 'jobhub' ) . '</div>';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$job_id = isset( $_GET['job_id'] ) ? absint( $_GET['job_id'] ) : 0;
		$job    = $job_id ? get_post( $job_id ) : null;
		if ( $job && ( 'job' !== $job->post_type || ! current_user_can( 'edit_post', $job_id ) ) ) {
			return '<div class="jh-notice jh-notice--error">' . esc_html__( 'You cannot edit this job.', 'jobhub' ) . '</div>';
		}

		$v       = static fn( string $k ) => $job ? jobhub_get( $job->ID, $k ) : '';
		$term    = static function ( string $tax ) use ( $job ) {
			if ( ! $job ) {
				return 0;
			}
			$t = wp_get_object_terms( $job->ID, $tax, [ 'fields' => 'ids' ] );
			return is_wp_error( $t ) || ! $t ? 0 : (int) $t[0];
		};
		$company = $v( 'company' ) ?: get_user_meta( get_current_user_id(), 'jobhub_company', true );

		ob_start();
		echo self::notice(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in notice().
		?>
		<form class="jh-form jh-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="jobhub_submit_job">
			<input type="hidden" name="job_id" value="<?php echo esc_attr( (string) $job_id ); ?>">
			<?php wp_nonce_field( 'jobhub_submit_job', '_jobhub_nonce' ); ?>

			<h2><?php echo $job ? esc_html__( 'Edit job', 'jobhub' ) : esc_html__( 'Post a job', 'jobhub' ); ?></h2>

			<label><?php esc_html_e( 'Job title', 'jobhub' ); ?> *
				<input type="text" name="title" value="<?php echo esc_attr( $job ? $job->post_title : '' ); ?>" required></label>

			<div class="jh-form__row">
				<?php
				foreach ( [ 'job_category' => __( 'Category', 'jobhub' ), 'job_type' => __( 'Job type', 'jobhub' ), 'job_location' => __( 'Location', 'jobhub' ) ] as $tax => $label ) :
					?>
					<label><?php echo esc_html( $label ); ?> *
						<?php
						wp_dropdown_categories( [
							'taxonomy'         => $tax,
							'name'             => $tax,
							'hide_empty'       => false,
							'selected'         => $term( $tax ),
							'show_option_none' => __( 'Select…', 'jobhub' ),
							'option_none_value' => '',
							'required'         => true,
						] );
						?>
					</label>
				<?php endforeach; ?>
			</div>

			<label><?php esc_html_e( 'Description', 'jobhub' ); ?> *
				<textarea name="description" rows="10" required><?php echo esc_textarea( $job ? $job->post_content : '' ); ?></textarea></label>

			<div class="jh-form__row">
				<label><?php esc_html_e( 'Company', 'jobhub' ); ?> *
					<input type="text" name="jobhub[company]" value="<?php echo esc_attr( $company ); ?>" required></label>
				<label><?php esc_html_e( 'Company website', 'jobhub' ); ?>
					<input type="url" name="jobhub[company_url]" value="<?php echo esc_attr( $v( 'company_url' ) ); ?>"></label>
			</div>
			<div class="jh-form__row">
				<label><?php esc_html_e( 'Salary min (yearly)', 'jobhub' ); ?>
					<input type="number" min="0" step="1000" name="jobhub[salary_min]" value="<?php echo esc_attr( $v( 'salary_min' ) ); ?>"></label>
				<label><?php esc_html_e( 'Salary max (yearly)', 'jobhub' ); ?>
					<input type="number" min="0" step="1000" name="jobhub[salary_max]" value="<?php echo esc_attr( $v( 'salary_max' ) ); ?>"></label>
				<label><?php esc_html_e( 'Deadline', 'jobhub' ); ?>
					<input type="date" name="jobhub[deadline]" value="<?php echo esc_attr( $v( 'deadline' ) ); ?>"></label>
			</div>
			<div class="jh-form__row">
				<label><?php esc_html_e( 'Send applications to (email)', 'jobhub' ); ?>
					<input type="email" name="jobhub[apply_email]" value="<?php echo esc_attr( $v( 'apply_email' ) ?: wp_get_current_user()->user_email ); ?>"></label>
				<label class="jh-check"><input type="checkbox" name="jobhub[remote]" value="1" <?php checked( (bool) $v( 'remote' ) ); ?>> <?php esc_html_e( 'Remote friendly', 'jobhub' ); ?></label>
			</div>

			<button class="jh-btn" type="submit"><?php echo $job ? esc_html__( 'Save changes', 'jobhub' ) : esc_html__( 'Publish job', 'jobhub' ); ?></button>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	public static function handle_submit(): void {
		$back = wp_get_referer() ?: jobhub_page_url( 'submit' );

		if ( ! isset( $_POST['_jobhub_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_jobhub_nonce'] ), 'jobhub_submit_job' ) ) {
			self::redirect( $back, [ 'jobhub_err' => 'nonce' ] );
		}
		if ( ! current_user_can( 'edit_jobs' ) ) {
			self::redirect( $back, [ 'jobhub_err' => 'denied' ] );
		}

		$job_id = absint( $_POST['job_id'] ?? 0 );
		if ( $job_id && ! current_user_can( 'edit_post', $job_id ) ) {
			self::redirect( $back, [ 'jobhub_err' => 'denied' ] );
		}

		$title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$desc  = wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) );
		$terms = [];
		foreach ( [ 'job_category', 'job_type', 'job_location' ] as $tax ) {
			$terms[ $tax ] = absint( $_POST[ $tax ] ?? 0 );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per-field in save_from_array().
		$meta = isset( $_POST['jobhub'] ) && is_array( $_POST['jobhub'] ) ? $_POST['jobhub'] : [];

		if ( ! $title || ! $desc || in_array( 0, $terms, true ) || empty( $meta['company'] ) ) {
			self::redirect( $back, [ 'jobhub_err' => 'fields' ] );
		}

		$needs_review = jobhub_option( 'require_approval', false ) && ! current_user_can( 'publish_jobs' );
		$data         = [
			'post_type'    => 'job',
			'post_title'   => $title,
			'post_content' => wpautop( $desc ),
			'post_excerpt' => wp_trim_words( wp_strip_all_tags( $desc ), 30 ),
		];

		if ( $job_id ) {
			$data['ID'] = $job_id;
			$result     = wp_update_post( $data, true );
			$msg        = 'updated';
		} else {
			$data['post_status'] = $needs_review ? 'pending' : 'publish';
			$data['post_author'] = get_current_user_id();
			$result              = wp_insert_post( $data, true );
			$msg                 = $needs_review ? 'pending' : 'submitted';
		}

		if ( is_wp_error( $result ) ) {
			self::redirect( $back, [ 'jobhub_err' => 'fields' ] );
		}
		$job_id = (int) $result;

		foreach ( $terms as $tax => $term_id ) {
			wp_set_object_terms( $job_id, [ $term_id ], $tax );
		}
		Job_Meta::save_from_array( $job_id, $meta );

		if ( 'pending' === $msg ) {
			wp_mail(
				get_option( 'admin_email' ),
				/* translators: %s: job title */
				sprintf( __( 'Job awaiting approval: %s', 'jobhub' ), $title ),
				admin_url( 'post.php?action=edit&post=' . $job_id )
			);
		}

		self::redirect( jobhub_page_url( 'dashboard' ), [ 'jobhub_msg' => $msg ] );
	}

	/* ----- Dashboard ---------------------------------------------------- */

	public static function dashboard_shortcode(): string {
		if ( ! is_user_logged_in() ) {
			return self::auth_forms();
		}
		if ( ! current_user_can( 'edit_jobs' ) ) {
			return '<div class="jh-notice jh-notice--error">' . esc_html__( 'The dashboard is for employer accounts.', 'jobhub' ) . '</div>';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$view_job = isset( $_GET['applications'] ) ? absint( $_GET['applications'] ) : 0;
		if ( $view_job && current_user_can( 'edit_post', $view_job ) ) {
			return self::notice() . self::applications_view( $view_job );
		}

		$jobs = get_posts( [
			'post_type'      => 'job',
			'post_status'    => [ 'publish', 'pending', 'draft', 'expired' ],
			'author'         => current_user_can( 'edit_others_jobs' ) ? '' : get_current_user_id(),
			'posts_per_page' => 50,
		] );

		$total_apps = 0;
		$live       = 0;
		foreach ( $jobs as $j ) {
			$total_apps += jobhub_application_count( $j->ID );
			$live       += ( 'publish' === $j->post_status && ! jobhub_get( $j->ID, 'filled' ) ) ? 1 : 0;
		}

		ob_start();
		echo self::notice(); // phpcs:ignore WordPress.Security.EscapeOutput
		?>
		<div class="jh-dash">
			<div class="jh-dash__head">
				<div>
					<h2><?php esc_html_e( 'Your jobs', 'jobhub' ); ?></h2>
					<p class="jh-muted"><?php echo esc_html( wp_get_current_user()->display_name ); ?></p>
				</div>
				<a class="jh-btn" href="<?php echo esc_url( jobhub_page_url( 'submit' ) ); ?>">+ <?php esc_html_e( 'Post a job', 'jobhub' ); ?></a>
			</div>

			<div class="jh-stats">
				<div class="jh-stat"><span class="jh-stat__n"><?php echo (int) count( $jobs ); ?></span><?php esc_html_e( 'Jobs posted', 'jobhub' ); ?></div>
				<div class="jh-stat"><span class="jh-stat__n"><?php echo (int) $live; ?></span><?php esc_html_e( 'Live now', 'jobhub' ); ?></div>
				<div class="jh-stat"><span class="jh-stat__n"><?php echo (int) $total_apps; ?></span><?php esc_html_e( 'Applications', 'jobhub' ); ?></div>
			</div>

			<?php if ( ! $jobs ) : ?>
				<div class="jh-empty"><?php esc_html_e( 'You have not posted any jobs yet.', 'jobhub' ); ?></div>
			<?php else : ?>
			<div class="jh-table-wrap">
			<table class="jh-table">
				<thead><tr>
					<th><?php esc_html_e( 'Job', 'jobhub' ); ?></th>
					<th><?php esc_html_e( 'Status', 'jobhub' ); ?></th>
					<th><?php esc_html_e( 'Applicants', 'jobhub' ); ?></th>
					<th><?php esc_html_e( 'Posted', 'jobhub' ); ?></th>
					<th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'jobhub' ); ?></span></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $jobs as $j ) : ?>
					<?php
					$filled = (bool) jobhub_get( $j->ID, 'filled' );
					$status = $filled ? 'filled' : $j->post_status;
					$count  = jobhub_application_count( $j->ID );
					?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Job', 'jobhub' ); ?>"><a href="<?php echo esc_url( (string) get_permalink( $j ) ); ?>"><strong><?php echo esc_html( $j->post_title ); ?></strong></a></td>
						<td data-label="<?php esc_attr_e( 'Status', 'jobhub' ); ?>"><span class="jh-pill jh-pill--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( [ 'publish' => __( 'Live', 'jobhub' ), 'pending' => __( 'In review', 'jobhub' ) ][ $status ] ?? ucfirst( $status ) ); ?></span></td>
						<td data-label="<?php esc_attr_e( 'Applicants', 'jobhub' ); ?>"><a href="<?php echo esc_url( add_query_arg( 'applications', $j->ID ) ); ?>"><?php echo (int) $count; ?> <?php esc_html_e( 'view', 'jobhub' ); ?></a></td>
						<td data-label="<?php esc_attr_e( 'Posted', 'jobhub' ); ?>"><?php echo esc_html( get_the_date( '', $j ) ); ?></td>
						<td class="jh-actions">
							<a href="<?php echo esc_url( add_query_arg( 'job_id', $j->ID, jobhub_page_url( 'submit' ) ) ); ?>"><?php esc_html_e( 'Edit', 'jobhub' ); ?></a>
							<?php echo self::action_link( $j->ID, $filled ? 'reopen' : 'fill', $filled ? __( 'Reopen', 'jobhub' ) : __( 'Mark filled', 'jobhub' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<?php echo self::action_link( $j->ID, 'delete', __( 'Delete', 'jobhub' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function action_link( int $job_id, string $do, string $label, bool $confirm = false ): string {
		return sprintf(
			'<form method="post" action="%1$s" class="jh-inline"%5$s><input type="hidden" name="action" value="jobhub_job_action"><input type="hidden" name="job" value="%2$d"><input type="hidden" name="do" value="%3$s">%4$s<button type="submit" class="jh-link%6$s">%7$s</button></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			$job_id,
			esc_attr( $do ),
			wp_nonce_field( 'jobhub_job_' . $do . '_' . $job_id, '_jobhub_nonce', true, false ),
			$confirm ? ' onsubmit="return confirm(\'' . esc_js( __( 'Delete this job?', 'jobhub' ) ) . '\')"' : '',
			'delete' === $do ? ' jh-link--danger' : '',
			esc_html( $label )
		);
	}

	public static function handle_job_action(): void {
		$job_id = absint( $_POST['job'] ?? 0 );
		$do     = sanitize_key( $_POST['do'] ?? '' );
		$back   = jobhub_page_url( 'dashboard' );

		if ( ! isset( $_POST['_jobhub_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_jobhub_nonce'] ), 'jobhub_job_' . $do . '_' . $job_id ) ) {
			self::redirect( $back, [ 'jobhub_err' => 'nonce' ] );
		}
		if ( 'job' !== get_post_type( $job_id ) ) {
			self::redirect( $back, [ 'jobhub_err' => 'denied' ] );
		}

		switch ( $do ) {
			case 'fill':
			case 'reopen':
				if ( ! current_user_can( 'edit_post', $job_id ) ) {
					self::redirect( $back, [ 'jobhub_err' => 'denied' ] );
				}
				update_post_meta( $job_id, '_jobhub_filled', 'fill' === $do );
				self::redirect( $back, [ 'jobhub_msg' => 'fill' === $do ? 'filled' : 'reopened' ] );
				break;
			case 'delete':
				if ( ! current_user_can( 'delete_post', $job_id ) ) {
					self::redirect( $back, [ 'jobhub_err' => 'denied' ] );
				}
				wp_trash_post( $job_id );
				self::redirect( $back, [ 'jobhub_msg' => 'deleted' ] );
				break;
		}
		self::redirect( $back, [ 'jobhub_err' => 'denied' ] );
	}

	private static function applications_view( int $job_id ): string {
		$apps = get_posts( [
			'post_type'      => 'job_application',
			'post_status'    => 'any',
			'posts_per_page' => 200,
			'meta_key'       => '_jobhub_job_id', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $job_id,          // phpcs:ignore WordPress.DB.SlowDBQuery
		] );

		ob_start();
		?>
		<div class="jh-dash">
			<p><a href="<?php echo esc_url( remove_query_arg( 'applications' ) ); ?>">&larr; <?php esc_html_e( 'Back to your jobs', 'jobhub' ); ?></a></p>
			<h2><?php
				/* translators: %s: job title */
				printf( esc_html__( 'Applicants for %s', 'jobhub' ), esc_html( get_the_title( $job_id ) ) );
			?></h2>

			<?php if ( ! $apps ) : ?>
				<div class="jh-empty"><?php esc_html_e( 'No applications yet. Share the job link to get more candidates.', 'jobhub' ); ?></div>
			<?php endif; ?>

			<div class="jh-applicants">
			<?php foreach ( $apps as $app ) : ?>
				<?php
				$m      = static fn( string $k ) => (string) get_post_meta( $app->ID, '_jobhub_' . $k, true );
				$status = $m( 'status' ) ?: 'new';
				?>
				<article class="jh-card jh-applicant">
					<header class="jh-applicant__head">
						<div>
							<h3><?php echo esc_html( $m( 'name' ) ); ?></h3>
							<p class="jh-muted">
								<a href="mailto:<?php echo esc_attr( $m( 'email' ) ); ?>"><?php echo esc_html( $m( 'email' ) ); ?></a>
								<?php if ( $m( 'phone' ) ) : ?> · <?php echo esc_html( $m( 'phone' ) ); ?><?php endif; ?>
								· <?php echo esc_html( human_time_diff( get_post_time( 'U', true, $app ) ) . ' ' . __( 'ago', 'jobhub' ) ); ?>
							</p>
						</div>
						<span class="jh-pill jh-pill--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span>
					</header>
					<div class="jh-applicant__letter"><?php echo wp_kses_post( wpautop( $app->post_content ) ); ?></div>
					<footer class="jh-applicant__foot">
						<a class="jh-btn jh-btn--ghost" href="<?php echo esc_url( Applications::cv_url( $app->ID ) ); ?>"><?php esc_html_e( 'Download CV', 'jobhub' ); ?></a>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jh-inline">
							<input type="hidden" name="action" value="jobhub_app_status">
							<input type="hidden" name="app" value="<?php echo (int) $app->ID; ?>">
							<?php wp_nonce_field( 'jobhub_app_status_' . $app->ID ); ?>
							<label class="screen-reader-text" for="jh-status-<?php echo (int) $app->ID; ?>"><?php esc_html_e( 'Status', 'jobhub' ); ?></label>
							<select id="jh-status-<?php echo (int) $app->ID; ?>" name="status" onchange="this.form.submit()">
								<?php foreach ( Applications::STATUSES as $s ) : ?>
									<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $status, $s ); ?>><?php echo esc_html( ucfirst( $s ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<noscript><button type="submit"><?php esc_html_e( 'Update', 'jobhub' ); ?></button></noscript>
						</form>
					</footer>
				</article>
			<?php endforeach; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}

<?php
namespace JobHub;

defined( 'ABSPATH' ) || exit;

/**
 * Candidate applications: the apply form handler, private CV storage,
 * secure CV downloads, status changes and email notifications.
 */
class Applications {

	const STATUSES = [ 'new', 'reviewed', 'shortlisted', 'rejected' ];
	const CV_DIR   = 'jobhub-cvs';
	const CV_TYPES = [
		'pdf'  => 'application/pdf',
		'doc'  => 'application/msword',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	];

	public static function init(): void {
		add_action( 'admin_post_jobhub_apply', [ __CLASS__, 'handle_apply' ] );
		add_action( 'admin_post_nopriv_jobhub_apply', [ __CLASS__, 'handle_apply' ] );
		add_action( 'admin_post_jobhub_cv', [ __CLASS__, 'download_cv' ] );
		add_action( 'admin_post_jobhub_app_status', [ __CLASS__, 'handle_status' ] );

		add_filter( 'manage_job_application_posts_columns', [ __CLASS__, 'columns' ] );
		add_action( 'manage_job_application_posts_custom_column', [ __CLASS__, 'column_content' ], 10, 2 );
		add_action( 'pre_get_posts', [ __CLASS__, 'admin_filter_by_job' ] );
		add_action( 'add_meta_boxes_job_application', [ __CLASS__, 'add_box' ] );
	}

	public static function error_messages(): array {
		return [
			'nonce'   => __( 'Your session expired. Please try again.', 'jobhub' ),
			'job'     => __( 'This job is no longer accepting applications.', 'jobhub' ),
			'fields'  => __( 'Please fill in your name, a valid email and a short cover note.', 'jobhub' ),
			'cv'      => __( 'Please attach your CV as a PDF, DOC or DOCX file.', 'jobhub' ),
			'cv_size' => __( 'Your CV is too large.', 'jobhub' ),
			'upload'  => __( 'We could not save your CV. Please try again.', 'jobhub' ),
			'dup'     => __( 'You have already applied for this job with that email.', 'jobhub' ),
		];
	}

	public static function handle_apply(): void {
		$job_id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
		$back   = $job_id ? get_permalink( $job_id ) : home_url( '/' );
		$fail   = static function ( string $code ) use ( $back ) {
			wp_safe_redirect( add_query_arg( 'apply_error', $code, $back ) . '#apply' );
			exit;
		};

		if ( ! isset( $_POST['_jobhub_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_jobhub_nonce'] ), 'jobhub_apply_' . $job_id ) ) {
			$fail( 'nonce' );
		}

		// Honeypot: real users never see or fill this field.
		if ( ! empty( $_POST['website'] ) ) {
			wp_safe_redirect( add_query_arg( 'applied', '1', $back ) );
			exit;
		}

		$job = get_post( $job_id );
		if ( ! $job || 'job' !== $job->post_type || 'publish' !== $job->post_status || jobhub_get( $job_id, 'filled' ) ) {
			$fail( 'job' );
		}

		$name   = sanitize_text_field( wp_unslash( $_POST['applicant_name'] ?? '' ) );
		$email  = sanitize_email( wp_unslash( $_POST['applicant_email'] ?? '' ) );
		$phone  = sanitize_text_field( wp_unslash( $_POST['applicant_phone'] ?? '' ) );
		$letter = sanitize_textarea_field( wp_unslash( $_POST['cover_letter'] ?? '' ) );

		if ( '' === $name || ! is_email( $email ) || mb_strlen( $letter ) < 20 ) {
			$fail( 'fields' );
		}

		if ( self::already_applied( $job_id, $email ) ) {
			$fail( 'dup' );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated in store_cv().
		$file = $_FILES['cv'] ?? null;
		$cv   = self::store_cv( is_array( $file ) ? $file : [] );
		if ( is_string( $cv ) ) {
			$fail( $cv );
		}

		$app_id = wp_insert_post( [
			'post_type'    => 'job_application',
			'post_status'  => 'publish',
			/* translators: 1: applicant name 2: job title */
			'post_title'   => sprintf( __( '%1$s — %2$s', 'jobhub' ), $name, $job->post_title ),
			'post_content' => $letter,
			'meta_input'   => [
				'_jobhub_job_id'   => $job_id,
				'_jobhub_name'     => $name,
				'_jobhub_email'    => $email,
				'_jobhub_phone'    => $phone,
				'_jobhub_cv_file'  => $cv['file'],
				'_jobhub_cv_name'  => $cv['name'],
				'_jobhub_status'   => 'new',
			],
		], true );

		if ( is_wp_error( $app_id ) ) {
			wp_delete_file( $cv['path'] );
			$fail( 'upload' );
		}

		self::notify( (int) $app_id, $job );
		do_action( 'jobhub_application_submitted', $app_id, $job_id );

		wp_safe_redirect( add_query_arg( 'applied', '1', $back ) . '#apply' );
		exit;
	}

	private static function already_applied( int $job_id, string $email ): bool {
		$q = new \WP_Query( [
			'post_type'      => 'job_application',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery
				[ 'key' => '_jobhub_job_id', 'value' => $job_id ],
				[ 'key' => '_jobhub_email', 'value' => $email ],
			],
		] );
		return $q->have_posts();
	}

	/**
	 * Validates and moves an uploaded CV into a private folder.
	 *
	 * @return array{file:string,name:string,path:string}|string Stored file info, or an error code.
	 */
	private static function store_cv( array $file ) {
		if ( empty( $file['tmp_name'] ) || UPLOAD_ERR_OK !== ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			return 'cv';
		}

		$max_mb = (int) jobhub_option( 'max_cv_mb', 2 );
		if ( (int) $file['size'] > $max_mb * MB_IN_BYTES ) {
			return 'cv_size';
		}

		$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], self::CV_TYPES );
		if ( empty( $check['ext'] ) || ! isset( self::CV_TYPES[ $check['ext'] ] ) ) {
			return 'cv';
		}

		$dir = self::cv_dir();
		if ( ! $dir ) {
			return 'upload';
		}

		// Random name so files can never be guessed, even if the folder were exposed.
		$stored = wp_generate_password( 32, false ) . '.' . $check['ext'];
		$path   = trailingslashit( $dir ) . $stored;

		if ( ! @move_uploaded_file( $file['tmp_name'], $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return 'upload';
		}
		chmod( $path, 0640 );

		return [
			'file' => $stored,
			'name' => sanitize_file_name( $file['name'] ),
			'path' => $path,
		];
	}

	/** Returns the private CV directory, creating it with deny rules on first use. */
	public static function cv_dir(): ?string {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . self::CV_DIR;

		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return null;
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			file_put_contents( $dir . '/index.php', "<?php // Silence.\n" );                 // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		return $dir;
	}

	/** Admins, or the employer who owns the job, may view an application. */
	public static function can_view( int $app_id, ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		if ( user_can( $user_id, 'edit_others_jobs' ) ) {
			return true;
		}
		$job = get_post( (int) get_post_meta( $app_id, '_jobhub_job_id', true ) );
		return $job && (int) $job->post_author === $user_id;
	}

	public static function cv_url( int $app_id ): string {
		return wp_nonce_url(
			add_query_arg( [ 'action' => 'jobhub_cv', 'app' => $app_id ], admin_url( 'admin-post.php' ) ),
			'jobhub_cv_' . $app_id
		);
	}

	public static function download_cv(): void {
		$app_id = isset( $_GET['app'] ) ? absint( $_GET['app'] ) : 0;
		check_admin_referer( 'jobhub_cv_' . $app_id );

		if ( ! self::can_view( $app_id ) ) {
			wp_die( esc_html__( 'You are not allowed to view this CV.', 'jobhub' ), 403 );
		}

		$stored = basename( (string) get_post_meta( $app_id, '_jobhub_cv_file', true ) );
		$path   = trailingslashit( (string) self::cv_dir() ) . $stored;
		if ( ! $stored || ! is_readable( $path ) ) {
			wp_die( esc_html__( 'CV not found.', 'jobhub' ), 404 );
		}

		$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$name = (string) get_post_meta( $app_id, '_jobhub_cv_name', true ) ?: 'cv.' . $ext;

		nocache_headers();
		header( 'Content-Type: ' . ( self::CV_TYPES[ $ext ] ?? 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $name ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	public static function handle_status(): void {
		$app_id = isset( $_POST['app'] ) ? absint( $_POST['app'] ) : 0;
		check_admin_referer( 'jobhub_app_status_' . $app_id );

		if ( ! self::can_view( $app_id ) ) {
			wp_die( esc_html__( 'Not allowed.', 'jobhub' ), 403 );
		}

		$status = sanitize_key( $_POST['status'] ?? '' );
		if ( in_array( $status, self::STATUSES, true ) ) {
			update_post_meta( $app_id, '_jobhub_status', $status );
		}

		wp_safe_redirect( wp_get_referer() ?: jobhub_page_url( 'dashboard' ) );
		exit;
	}

	private static function notify( int $app_id, \WP_Post $job ): void {
		$name  = get_post_meta( $app_id, '_jobhub_name', true );
		$email = get_post_meta( $app_id, '_jobhub_email', true );
		$site  = get_bloginfo( 'name' );

		$to = (string) jobhub_get( $job->ID, 'apply_email' );
		if ( ! is_email( $to ) ) {
			$author = get_userdata( (int) $job->post_author );
			$to     = $author ? $author->user_email : get_option( 'admin_email' );
		}

		/* translators: 1: applicant name 2: job title */
		$subject = sprintf( __( 'New application: %1$s for %2$s', 'jobhub' ), $name, $job->post_title );
		$body    = sprintf(
			/* translators: 1: applicant name 2: job title 3: dashboard url */
			__( "%1\$s has applied for \"%2\$s\".\n\nReview the application and CV in your dashboard:\n%3\$s", 'jobhub' ),
			$name,
			$job->post_title,
			jobhub_page_url( 'dashboard' )
		);
		wp_mail( $to, $subject, $body, [ 'Reply-To: ' . $name . ' <' . $email . '>' ] );

		$cc = (string) jobhub_option( 'notify_email', '' );
		if ( is_email( $cc ) && $cc !== $to ) {
			wp_mail( $cc, $subject, $body );
		}

		wp_mail(
			$email,
			/* translators: %s: job title */
			sprintf( __( 'We received your application for %s', 'jobhub' ), $job->post_title ),
			sprintf(
				/* translators: 1: applicant name 2: job title 3: company 4: site name */
				__( "Hi %1\$s,\n\nThanks for applying for \"%2\$s\" at %3\$s. The employer will be in touch if your profile is a match.\n\n— %4\$s", 'jobhub' ),
				$name,
				$job->post_title,
				jobhub_get( $job->ID, 'company' ),
				$site
			)
		);
	}

	/* ----- wp-admin list table ------------------------------------------- */

	public static function columns( array $cols ): array {
		return [
			'cb'     => $cols['cb'] ?? '',
			'title'  => __( 'Applicant', 'jobhub' ),
			'job'    => __( 'Job', 'jobhub' ),
			'email'  => __( 'Email', 'jobhub' ),
			'status' => __( 'Status', 'jobhub' ),
			'cv'     => __( 'CV', 'jobhub' ),
			'date'   => $cols['date'] ?? __( 'Date', 'jobhub' ),
		];
	}

	public static function column_content( string $col, int $app_id ): void {
		switch ( $col ) {
			case 'job':
				$job_id = (int) get_post_meta( $app_id, '_jobhub_job_id', true );
				printf( '<a href="%s">%s</a>', esc_url( (string) get_edit_post_link( $job_id ) ), esc_html( get_the_title( $job_id ) ) );
				break;
			case 'email':
				$email = (string) get_post_meta( $app_id, '_jobhub_email', true );
				printf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $email ) );
				break;
			case 'status':
				$s = (string) get_post_meta( $app_id, '_jobhub_status', true ) ?: 'new';
				printf( '<span class="jobhub-status jobhub-status--%1$s">%2$s</span>', esc_attr( $s ), esc_html( ucfirst( $s ) ) );
				break;
			case 'cv':
				printf( '<a href="%s">%s</a>', esc_url( self::cv_url( $app_id ) ), esc_html__( 'Download', 'jobhub' ) );
				break;
		}
	}

	public static function admin_filter_by_job( \WP_Query $q ): void {
		if ( ! is_admin() || ! $q->is_main_query() || 'job_application' !== $q->get( 'post_type' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$job_id = isset( $_GET['job_id'] ) ? absint( $_GET['job_id'] ) : 0;
		if ( $job_id ) {
			$q->set( 'meta_key', '_jobhub_job_id' );
			$q->set( 'meta_value', $job_id );
		}
	}

	public static function add_box(): void {
		remove_post_type_support( 'job_application', 'editor' );
		add_meta_box( 'jobhub_app', __( 'Application', 'jobhub' ), [ __CLASS__, 'render_box' ], 'job_application', 'normal', 'high' );
	}

	public static function render_box( \WP_Post $post ): void {
		$m = static fn( string $k ) => (string) get_post_meta( $post->ID, '_jobhub_' . $k, true );
		?>
		<table class="form-table" role="presentation">
			<tr><th><?php esc_html_e( 'Name', 'jobhub' ); ?></th><td><?php echo esc_html( $m( 'name' ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Email', 'jobhub' ); ?></th><td><a href="mailto:<?php echo esc_attr( $m( 'email' ) ); ?>"><?php echo esc_html( $m( 'email' ) ); ?></a></td></tr>
			<tr><th><?php esc_html_e( 'Phone', 'jobhub' ); ?></th><td><?php echo esc_html( $m( 'phone' ) ?: '—' ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Job', 'jobhub' ); ?></th><td><?php echo esc_html( get_the_title( (int) $m( 'job_id' ) ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Status', 'jobhub' ); ?></th><td><?php echo esc_html( ucfirst( $m( 'status' ) ?: 'new' ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'CV', 'jobhub' ); ?></th><td><a class="button" href="<?php echo esc_url( self::cv_url( $post->ID ) ); ?>"><?php echo esc_html( $m( 'cv_name' ) ?: __( 'Download', 'jobhub' ) ); ?></a></td></tr>
			<tr><th><?php esc_html_e( 'Cover letter', 'jobhub' ); ?></th><td><?php echo wp_kses_post( wpautop( $post->post_content ) ); ?></td></tr>
		</table>
		<?php
	}
}

<?php
namespace JobHub;

defined( 'ABSPATH' ) || exit;

/**
 * Job details (company, salary, remote, deadline…): registered meta, admin
 * meta box and the shared sanitiser used by the admin and front-end forms.
 */
class Job_Meta {

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register_meta' ] );
		add_action( 'add_meta_boxes_job', [ __CLASS__, 'add_box' ] );
		add_action( 'save_post_job', [ __CLASS__, 'save_box' ], 10, 2 );
		add_filter( 'manage_job_posts_columns', [ __CLASS__, 'columns' ] );
		add_action( 'manage_job_posts_custom_column', [ __CLASS__, 'column_content' ], 10, 2 );
	}

	public static function register_meta(): void {
		$types = [ 'text' => 'string', 'url' => 'string', 'email' => 'string', 'date' => 'string', 'int' => 'integer', 'bool' => 'boolean' ];

		foreach ( jobhub_meta_fields() as $key => $type ) {
			register_post_meta( 'job', '_jobhub_' . $key, [
				'type'              => $types[ $type ],
				'single'            => true,
				'show_in_rest'      => 'apply_email' !== $key, // never expose contact email publicly
				'sanitize_callback' => static fn( $v ) => self::sanitize( $v, $type ),
				'auth_callback'     => static fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', $post_id ),
			] );
		}
	}

	public static function sanitize( $value, string $type ) {
		return match ( $type ) {
			'int'   => absint( $value ),
			'bool'  => (bool) $value,
			'url'   => esc_url_raw( (string) $value ),
			'email' => sanitize_email( (string) $value ),
			'date'  => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $value ) ? (string) $value : '',
			default => sanitize_text_field( (string) $value ),
		};
	}

	/** Saves all known fields from an input array (e.g. $_POST['jobhub']). */
	public static function save_from_array( int $job_id, array $input ): void {
		foreach ( jobhub_meta_fields() as $key => $type ) {
			if ( 'featured' === $key && ! current_user_can( 'manage_options' ) ) {
				continue; // only admins can feature jobs
			}
			$raw = $input[ $key ] ?? ( 'bool' === $type ? false : '' );
			update_post_meta( $job_id, '_jobhub_' . $key, self::sanitize( wp_unslash( $raw ), $type ) );
		}
	}

	public static function add_box(): void {
		add_meta_box( 'jobhub_details', __( 'Job Details', 'jobhub' ), [ __CLASS__, 'render_box' ], 'job', 'normal', 'high' );
	}

	public static function render_box( \WP_Post $post ): void {
		wp_nonce_field( 'jobhub_save_job', 'jobhub_job_nonce' );
		$v = static fn( string $k ) => jobhub_get( $post->ID, $k );
		?>
		<style>.jobhub-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px 20px}.jobhub-grid label{display:block;font-weight:600;margin-bottom:4px}.jobhub-grid input[type=text],.jobhub-grid input[type=url],.jobhub-grid input[type=email],.jobhub-grid input[type=number],.jobhub-grid input[type=date]{width:100%}</style>
		<div class="jobhub-grid">
			<p><label for="jh-company"><?php esc_html_e( 'Company name', 'jobhub' ); ?></label>
				<input id="jh-company" type="text" name="jobhub[company]" value="<?php echo esc_attr( $v( 'company' ) ); ?>" required></p>
			<p><label for="jh-company-url"><?php esc_html_e( 'Company website', 'jobhub' ); ?></label>
				<input id="jh-company-url" type="url" name="jobhub[company_url]" value="<?php echo esc_attr( $v( 'company_url' ) ); ?>"></p>
			<p><label for="jh-min"><?php esc_html_e( 'Salary min (yearly)', 'jobhub' ); ?></label>
				<input id="jh-min" type="number" min="0" step="1000" name="jobhub[salary_min]" value="<?php echo esc_attr( $v( 'salary_min' ) ); ?>"></p>
			<p><label for="jh-max"><?php esc_html_e( 'Salary max (yearly)', 'jobhub' ); ?></label>
				<input id="jh-max" type="number" min="0" step="1000" name="jobhub[salary_max]" value="<?php echo esc_attr( $v( 'salary_max' ) ); ?>"></p>
			<p><label for="jh-deadline"><?php esc_html_e( 'Application deadline', 'jobhub' ); ?></label>
				<input id="jh-deadline" type="date" name="jobhub[deadline]" value="<?php echo esc_attr( $v( 'deadline' ) ); ?>"></p>
			<p><label for="jh-email"><?php esc_html_e( 'Send applications to', 'jobhub' ); ?></label>
				<input id="jh-email" type="email" name="jobhub[apply_email]" value="<?php echo esc_attr( $v( 'apply_email' ) ); ?>"></p>
			<p><label><input type="checkbox" name="jobhub[remote]" value="1" <?php checked( $v( 'remote' ) ); ?>> <?php esc_html_e( 'Remote friendly', 'jobhub' ); ?></label>
				<label><input type="checkbox" name="jobhub[filled]" value="1" <?php checked( $v( 'filled' ) ); ?>> <?php esc_html_e( 'Position filled', 'jobhub' ); ?></label>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<label><input type="checkbox" name="jobhub[featured]" value="1" <?php checked( $v( 'featured' ) ); ?>> <?php esc_html_e( 'Featured', 'jobhub' ); ?></label>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	public static function save_box( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['jobhub_job_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['jobhub_job_nonce'] ), 'jobhub_save_job' ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per-field in save_from_array().
		$input = isset( $_POST['jobhub'] ) && is_array( $_POST['jobhub'] ) ? $_POST['jobhub'] : [];
		self::save_from_array( $post_id, $input );
	}

	public static function columns( array $cols ): array {
		$new = [];
		foreach ( $cols as $k => $label ) {
			$new[ $k ] = $label;
			if ( 'title' === $k ) {
				$new['company']      = __( 'Company', 'jobhub' );
				$new['salary']       = __( 'Salary', 'jobhub' );
				$new['applications'] = __( 'Applications', 'jobhub' );
			}
		}
		return $new;
	}

	public static function column_content( string $col, int $post_id ): void {
		switch ( $col ) {
			case 'company':
				echo esc_html( jobhub_get( $post_id, 'company' ) );
				if ( jobhub_get( $post_id, 'featured' ) ) {
					echo ' <span class="dashicons dashicons-star-filled" title="' . esc_attr__( 'Featured', 'jobhub' ) . '"></span>';
				}
				break;
			case 'salary':
				echo esc_html( jobhub_salary( $post_id ) ?: '—' );
				break;
			case 'applications':
				$count = jobhub_application_count( $post_id );
				$url   = add_query_arg( [ 'post_type' => 'job_application', 'job_id' => $post_id ], admin_url( 'edit.php' ) );
				printf( '<a href="%s">%d</a>', esc_url( $url ), (int) $count );
				break;
		}
	}
}

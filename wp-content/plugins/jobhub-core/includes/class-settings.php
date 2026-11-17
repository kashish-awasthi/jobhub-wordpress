<?php
namespace JobHub;

defined( 'ABSPATH' ) || exit;

/** Jobs → Settings page built with the WordPress Settings API. */
class Settings {

	const OPTION = 'jobhub_settings';

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
		add_action( 'admin_init', [ __CLASS__, 'register' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( JOBHUB_FILE ), [ __CLASS__, 'action_link' ] );
	}

	public static function fields(): array {
		return [
			'per_page'         => [ __( 'Jobs per page', 'jobhub' ), 'number', 10 ],
			'currency'         => [ __( 'Currency symbol', 'jobhub' ), 'text', '$' ],
			'require_approval' => [ __( 'Admins must approve new jobs', 'jobhub' ), 'checkbox', 0 ],
			'expire_days'      => [ __( 'Expire jobs after (days, 0 = never)', 'jobhub' ), 'number', 60 ],
			'max_cv_mb'        => [ __( 'Max CV size (MB)', 'jobhub' ), 'number', 2 ],
			'notify_email'     => [ __( 'Also notify this email of new applications', 'jobhub' ), 'email', '' ],
			'submit_page'      => [ __( '"Post a job" page', 'jobhub' ), 'page', 0 ],
			'dashboard_page'   => [ __( 'Employer dashboard page', 'jobhub' ), 'page', 0 ],
		];
	}

	public static function menu(): void {
		add_submenu_page( 'edit.php?post_type=job', __( 'JobHub Settings', 'jobhub' ), __( 'Settings', 'jobhub' ), 'manage_options', 'jobhub-settings', [ __CLASS__, 'render' ] );
	}

	public static function action_link( array $links ): array {
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'edit.php?post_type=job&page=jobhub-settings' ) ), esc_html__( 'Settings', 'jobhub' ) ) );
		return $links;
	}

	public static function register(): void {
		register_setting( 'jobhub', self::OPTION, [ 'sanitize_callback' => [ __CLASS__, 'sanitize' ], 'default' => [] ] );
		add_settings_section( 'jobhub_main', '', '__return_false', 'jobhub-settings' );

		foreach ( self::fields() as $key => [ $label, $type ] ) {
			add_settings_field( $key, $label, [ __CLASS__, 'field' ], 'jobhub-settings', 'jobhub_main', [ 'key' => $key, 'type' => $type, 'label_for' => 'jobhub_' . $key ] );
		}
	}

	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : [];
		$out   = [];
		foreach ( self::fields() as $key => [ , $type, $default ] ) {
			$v           = $input[ $key ] ?? '';
			$out[ $key ] = match ( $type ) {
				'number', 'page' => absint( $v ),
				'checkbox'       => empty( $v ) ? 0 : 1,
				'email'          => sanitize_email( $v ),
				default          => sanitize_text_field( $v ) ?: $default,
			};
		}
		$out['per_page']  = max( 1, min( 50, $out['per_page'] ?: 10 ) );
		$out['max_cv_mb'] = max( 1, min( 10, $out['max_cv_mb'] ?: 2 ) );
		return $out;
	}

	public static function field( array $args ): void {
		$key   = $args['key'];
		$name  = self::OPTION . '[' . $key . ']';
		$id    = 'jobhub_' . $key;
		$value = jobhub_option( $key, self::fields()[ $key ][2] );

		switch ( $args['type'] ) {
			case 'checkbox':
				printf( '<input type="checkbox" id="%s" name="%s" value="1" %s>', esc_attr( $id ), esc_attr( $name ), checked( $value, 1, false ) );
				break;
			case 'page':
				wp_dropdown_pages( [ 'id' => $id, 'name' => $name, 'selected' => (int) $value, 'show_option_none' => esc_html__( '— Select —', 'jobhub' ) ] );
				break;
			default:
				printf( '<input type="%s" id="%s" name="%s" value="%s" class="regular-text">', esc_attr( $args['type'] ), esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
		}
	}

	public static function render(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'JobHub Settings', 'jobhub' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'jobhub' );
				do_settings_sections( 'jobhub-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}

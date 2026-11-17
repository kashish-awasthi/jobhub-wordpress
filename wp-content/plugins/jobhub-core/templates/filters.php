<?php
/**
 * Filter form. Works as a normal GET form without JavaScript; filters.js
 * upgrades it to live AJAX filtering.
 */
defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters.
$p = JobHub\Query::params_from( $_GET );

$dropdown = static function ( string $tax, string $name, string $label, string $current ) {
	$terms = get_terms( [ 'taxonomy' => $tax, 'hide_empty' => true ] );
	if ( is_wp_error( $terms ) ) {
		return;
	}
	printf( '<label class="screen-reader-text" for="jh-f-%1$s">%2$s</label>', esc_attr( $name ), esc_html( $label ) );
	printf( '<select id="jh-f-%1$s" name="%1$s"><option value="">%2$s</option>', esc_attr( $name ), esc_html( $label ) );
	foreach ( $terms as $t ) {
		printf( '<option value="%1$s" %2$s>%3$s (%4$d)</option>', esc_attr( $t->slug ), selected( $current, $t->slug, false ), esc_html( $t->name ), (int) $t->count );
	}
	echo '</select>';
};
$currency = jobhub_option( 'currency', '$' );
?>
<form class="jh-filters" method="get" action="<?php echo esc_url( (string) get_post_type_archive_link( 'job' ) ); ?>" data-jobhub-filters role="search">
	<div class="jh-filters__search">
		<label class="screen-reader-text" for="jh-f-search"><?php esc_html_e( 'Search jobs', 'jobhub' ); ?></label>
		<input id="jh-f-search" type="search" name="search" value="<?php echo esc_attr( $p['search'] ); ?>" placeholder="<?php esc_attr_e( 'Job title, skill or company', 'jobhub' ); ?>">
	</div>
	<?php
	$dropdown( 'job_category', 'category', __( 'All categories', 'jobhub' ), $p['category'] );
	$dropdown( 'job_location', 'location', __( 'All locations', 'jobhub' ), $p['location'] );
	$dropdown( 'job_type', 'type', __( 'All job types', 'jobhub' ), $p['type'] );
	?>
	<label class="screen-reader-text" for="jh-f-salary"><?php esc_html_e( 'Minimum salary', 'jobhub' ); ?></label>
	<select id="jh-f-salary" name="salary">
		<option value=""><?php esc_html_e( 'Any salary', 'jobhub' ); ?></option>
		<?php foreach ( [ 40000, 60000, 80000, 100000, 120000 ] as $s ) : ?>
			<option value="<?php echo (int) $s; ?>" <?php selected( $p['salary'], (string) $s ); ?>><?php echo esc_html( $currency . ( $s / 1000 ) . 'k+' ); ?></option>
		<?php endforeach; ?>
	</select>
	<label class="jh-check"><input type="checkbox" name="remote" value="1" <?php checked( $p['remote'], '1' ); ?>> <?php esc_html_e( 'Remote only', 'jobhub' ); ?></label>
	<label class="screen-reader-text" for="jh-f-sort"><?php esc_html_e( 'Sort by', 'jobhub' ); ?></label>
	<select id="jh-f-sort" name="sort">
		<option value=""><?php esc_html_e( 'Newest first', 'jobhub' ); ?></option>
		<option value="salary" <?php selected( $p['sort'], 'salary' ); ?>><?php esc_html_e( 'Highest salary', 'jobhub' ); ?></option>
		<option value="oldest" <?php selected( $p['sort'], 'oldest' ); ?>><?php esc_html_e( 'Oldest first', 'jobhub' ); ?></option>
	</select>
	<button class="jh-btn" type="submit"><?php esc_html_e( 'Search', 'jobhub' ); ?></button>
	<a class="jh-filters__reset" href="<?php echo esc_url( (string) get_post_type_archive_link( 'job' ) ); ?>" data-jobhub-reset><?php esc_html_e( 'Clear', 'jobhub' ); ?></a>
</form>

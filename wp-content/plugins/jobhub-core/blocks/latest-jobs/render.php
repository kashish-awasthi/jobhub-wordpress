<?php
/**
 * Server-side render for jobhub/latest-jobs.
 *
 * @var array $attributes Block attributes.
 */
defined( 'ABSPATH' ) || exit;
?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'jh-block-latest' ] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php echo JobHub\Blocks::render_latest( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in template. ?>
</div>

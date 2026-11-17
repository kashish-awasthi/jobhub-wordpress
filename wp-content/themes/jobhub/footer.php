</main>

<footer class="site-footer">
	<div class="container site-footer__inner">
		<div>
			<a class="brand brand--light" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<span class="brand__mark" aria-hidden="true">J</span>
				<span class="brand__name"><?php bloginfo( 'name' ); ?></span>
			</a>
			<p class="site-footer__tag"><?php bloginfo( 'description' ); ?></p>
		</div>
		<?php
		wp_nav_menu( [
			'theme_location' => 'footer',
			'container'      => false,
			'menu_class'     => 'site-footer__menu',
			'fallback_cb'    => false,
			'depth'          => 1,
		] );
		?>
		<p class="site-footer__copy">&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'Built with a custom WordPress theme and plugin.', 'jobhub-theme' ); ?></p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>

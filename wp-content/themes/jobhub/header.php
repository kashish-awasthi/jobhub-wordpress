<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e( 'Skip to content', 'jobhub-theme' ); ?></a>

<header class="site-header">
	<div class="container site-header__inner">
		<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php if ( has_custom_logo() ) : ?>
				<?php echo wp_get_attachment_image( get_theme_mod( 'custom_logo' ), 'full', false, [ 'class' => 'brand__logo' ] ); ?>
			<?php else : ?>
				<span class="brand__mark" aria-hidden="true">J</span>
				<span class="brand__name"><?php bloginfo( 'name' ); ?></span>
			<?php endif; ?>
		</a>

		<button class="nav-toggle" aria-expanded="false" aria-controls="site-nav">
			<span class="nav-toggle__bar"></span><span class="screen-reader-text"><?php esc_html_e( 'Menu', 'jobhub-theme' ); ?></span>
		</button>

		<nav id="site-nav" class="nav" aria-label="<?php esc_attr_e( 'Primary', 'jobhub-theme' ); ?>">
			<?php
			wp_nav_menu( [
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'nav__list',
				'fallback_cb'    => 'jobhub_theme_fallback_menu',
				'depth'          => 1,
			] );
			?>
			<div class="nav__cta">
				<?php if ( is_user_logged_in() ) : ?>
					<a class="nav__link" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Log out', 'jobhub-theme' ); ?></a>
				<?php endif; ?>
				<?php if ( function_exists( 'jobhub_page_url' ) ) : ?>
					<a class="jh-btn" href="<?php echo esc_url( jobhub_page_url( 'submit' ) ); ?>"><?php esc_html_e( 'Post a job', 'jobhub-theme' ); ?></a>
				<?php endif; ?>
			</div>
		</nav>
	</div>
</header>

<main id="main" class="site-main">

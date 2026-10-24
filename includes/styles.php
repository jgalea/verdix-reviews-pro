<?php
defined( 'ABSPATH' ) || exit;

function verdix_pro_register_styles( $styles ) {
	$styles['card']    = array( 'label' => __( 'Card', 'verdix-pro' ) );
	$styles['hero']    = array( 'label' => __( 'Hero', 'verdix-pro' ) );
	$styles['split']   = array( 'label' => __( 'Split', 'verdix-pro' ) );
	$styles['minimal'] = array( 'label' => __( 'Minimal', 'verdix-pro' ) );
	return $styles;
}
add_filter( 'verdix_styles', 'verdix_pro_register_styles' );

function verdix_pro_enqueue_styles() {
	if ( ! is_singular() ) {
		return;
	}

	$post_id = get_the_ID();
	$review  = verdix_get_review( $post_id );
	if ( ! $review || empty( $review['rating'] ) ) {
		return;
	}

	$options      = get_option( 'verdix_settings', array() );
	$global_style = isset( $options['default_style'] ) ? $options['default_style'] : 'default';
	$box_style    = ! empty( $review['style'] ) ? $review['style'] : $global_style;

	$pro_styles = array( 'card', 'hero', 'split', 'minimal' );
	if ( ! in_array( $box_style, $pro_styles, true ) ) {
		return;
	}

	wp_enqueue_style(
		'verdix-pro-styles',
		VERDIX_PRO_URL . 'assets/css/pro-styles.css',
		array( 'verdix-frontend' ),
		VERDIX_PRO_VERSION
	);

	// Google Fonts.
	$font_families = array();
	if ( in_array( $box_style, array( 'card', 'hero', 'split' ), true ) ) {
		$font_families[] = 'DM+Sans:wght@400;500;600;700';
	}
	if ( 'minimal' === $box_style ) {
		$font_families[] = 'DM+Sans:wght@400;500;600;700';
		$font_families[] = 'Instrument+Serif:ital@0;1';
	}

	if ( ! empty( $font_families ) ) {
		$font_url = 'https://fonts.googleapis.com/css2?' . implode( '&', array_map( function( $f ) {
			return 'family=' . $f;
		}, $font_families ) ) . '&display=swap';
		wp_enqueue_style( 'verdix-pro-google-fonts', $font_url, array(), null );
	}
}
add_action( 'wp_enqueue_scripts', 'verdix_pro_enqueue_styles' );

/**
 * Load pro styles + fonts on the Verdix Reviews settings page so the Appearance
 * preview renders correctly when a pro style is selected.
 */
function verdix_pro_admin_enqueue_styles( $hook_suffix ) {
	if ( 'verdix_page_verdix-settings' !== $hook_suffix ) {
		return;
	}

	wp_enqueue_style(
		'verdix-pro-styles',
		VERDIX_PRO_URL . 'assets/css/pro-styles.css',
		array( 'verdix-frontend' ),
		VERDIX_PRO_VERSION
	);

	wp_enqueue_style(
		'verdix-pro-criteria',
		VERDIX_PRO_URL . 'assets/css/criteria.css',
		array( 'verdix-frontend' ),
		VERDIX_PRO_VERSION
	);

	wp_enqueue_style(
		'verdix-pro-visitor-ratings',
		VERDIX_PRO_URL . 'assets/css/visitor-ratings.css',
		array( 'verdix-frontend' ),
		VERDIX_PRO_VERSION
	);

	wp_enqueue_style(
		'verdix-pro-google-fonts',
		'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Instrument+Serif:ital@0;1&display=swap',
		array(),
		null
	);
}
add_action( 'admin_enqueue_scripts', 'verdix_pro_admin_enqueue_styles' );

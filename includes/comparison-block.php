<?php
defined( 'ABSPATH' ) || exit;

/**
 * Register the verdix-pro/compare block.
 */
function verdix_pro_register_compare_block() {
	wp_register_script(
		'verdix-pro-compare-editor',
		VERDIX_PRO_URL . 'assets/js/comparison-editor.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data' ),
		VERDIX_PRO_VERSION,
		true
	);

	register_block_type( 'verdix-pro/compare', array(
		'editor_script'   => 'verdix-pro-compare-editor',
		'render_callback' => 'verdix_pro_compare_block_render',
		'attributes'      => array(
			'ids'       => array( 'type' => 'string', 'default' => '' ),
			'fields'    => array( 'type' => 'string', 'default' => 'rating,summary,pros,cons,cta' ),
			'highlight' => array( 'type' => 'boolean', 'default' => true ),
		),
	) );
}
add_action( 'init', 'verdix_pro_register_compare_block' );

/**
 * Server-side render callback for the compare block.
 *
 * @param array $attributes Block attributes.
 * @return string HTML.
 */
function verdix_pro_compare_block_render( $attributes ) {
	return verdix_pro_compare_handler( array(
		'ids'       => $attributes['ids'],
		'fields'    => $attributes['fields'],
		'highlight' => $attributes['highlight'] ? 'true' : 'false',
	) );
}

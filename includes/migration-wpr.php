<?php
defined( 'ABSPATH' ) || exit;

function verdix_pro_register_wpr_migration( $sources ) {
	$sources['wp_product_review'] = array(
		'label'    => __( 'WP Product Review', 'verdix-pro' ),
		'detect'   => 'verdix_pro_get_wpr_migratable_posts',
		'migrate'  => 'verdix_pro_migrate_post_wpr',
		'columns'  => array( 'product', 'criteria', 'pros', 'cons' ),
		'row_data' => 'verdix_pro_migration_row_wpr',
	);
	return $sources;
}
add_filter( 'verdix_migration_sources', 'verdix_pro_register_wpr_migration' );

function verdix_pro_migrate_post_wpr( $post_id ) {
	$enabled = get_post_meta( $post_id, 'cwp_meta_box_check', true );
	if ( 'Yes' !== $enabled ) {
		return new WP_Error( 'no_review', __( 'No WP Product Review data found.', 'verdix-pro' ) );
	}

	$existing = get_post_meta( $post_id, '_verdix_review', true );
	if ( ! empty( $existing ) ) {
		$existing_data = json_decode( $existing, true );
		if ( is_array( $existing_data ) && ! empty( $existing_data['rating'] ) ) {
			return new WP_Error( 'already_migrated', __( 'Already has Verdix Reviews data.', 'verdix-pro' ) );
		}
	}

	$product_name = get_post_meta( $post_id, 'cwp_rev_product_name', true );
	$price        = get_post_meta( $post_id, 'cwp_rev_price', true );
	$affiliate    = get_post_meta( $post_id, 'cwp_rev_affiliate_link', true );

	$pros_raw = get_post_meta( $post_id, 'cwp_rev_product_pros', true );
	$cons_raw = get_post_meta( $post_id, 'cwp_rev_product_cons', true );
	$pros = verdix_pro_parse_wpr_list( $pros_raw );
	$cons = verdix_pro_parse_wpr_list( $cons_raw );

	$criteria    = array();
	$score_sum   = 0;
	$score_count = 0;
	for ( $i = 1; $i <= 9; $i++ ) {
		$label = get_post_meta( $post_id, 'cwp_rev_option_' . $i, true );
		$score = get_post_meta( $post_id, 'cwp_rev_option_' . $i . '_score', true );
		if ( empty( $label ) ) {
			continue;
		}
		$score_value = floatval( $score );
		$normalized  = round( ( $score_value / 20 ) * 2 ) / 2;
		$normalized  = max( 0, min( 5, $normalized ) );

		$criteria[] = array(
			'label'  => sanitize_text_field( $label ),
			'rating' => $normalized,
		);
		$score_sum += $normalized;
		$score_count++;
	}

	$overall_rating = 0;
	if ( $score_count > 0 ) {
		$overall_rating = round( ( $score_sum / $score_count ) * 2 ) / 2;
	}

	$data = array(
		'rating'       => $overall_rating,
		'rating_type'  => 'star',
		'position'     => '',
		'heading'      => '',
		'summary'      => '',
		'pros'         => $pros,
		'cons'         => $cons,
		'schema_type'  => 'Product',
		'product_name' => $product_name ?: '',
		'price'        => $price ?: '',
		'currency'     => 'USD',
		'cta_text'     => $affiliate ? __( 'Buy Now', 'verdix-pro' ) : '',
		'cta_url'      => $affiliate ?: '',
		'author_name'  => '',
		'use_criteria' => ! empty( $criteria ),
		'criteria'     => $criteria,
	);

	verdix_save_review( $post_id, $data );
	return true;
}

function verdix_pro_parse_wpr_list( $raw ) {
	if ( empty( $raw ) ) {
		return array();
	}
	$unserialized = is_string( $raw ) && is_serialized( $raw ) ? unserialize( $raw, array( 'allowed_classes' => false ) ) : $raw; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
	if ( is_array( $unserialized ) ) {
		$items = array();
		foreach ( $unserialized as $item ) {
			$text = sanitize_text_field( $item );
			if ( '' !== $text ) {
				$items[] = $text;
			}
		}
		return $items;
	}
	return verdix_parse_list_html( $raw );
}

function verdix_pro_get_wpr_migratable_posts() {
	global $wpdb;
	$post_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT pm.post_id
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = %s
			AND pm.meta_value = %s
			AND p.post_status IN (%s, %s, %s, %s)
			ORDER BY p.post_date DESC",
			'cwp_meta_box_check',
			'Yes',
			'publish',
			'draft',
			'pending',
			'private'
		)
	);

	if ( empty( $post_ids ) ) {
		return array();
	}

	$posts = array();
	foreach ( $post_ids as $pid ) {
		$post = get_post( absint( $pid ) );
		if ( $post ) {
			$posts[] = $post;
		}
	}
	return $posts;
}

function verdix_pro_migration_row_wpr( $post ) {
	$product_name   = get_post_meta( $post->ID, 'cwp_rev_product_name', true );
	$criteria_count = 0;
	for ( $i = 1; $i <= 9; $i++ ) {
		if ( ! empty( get_post_meta( $post->ID, 'cwp_rev_option_' . $i, true ) ) ) {
			$criteria_count++;
		}
	}
	$pros_raw   = get_post_meta( $post->ID, 'cwp_rev_product_pros', true );
	$cons_raw   = get_post_meta( $post->ID, 'cwp_rev_product_cons', true );
	$pros_count = count( verdix_pro_parse_wpr_list( $pros_raw ) );
	$cons_count = count( verdix_pro_parse_wpr_list( $cons_raw ) );

	return array(
		'product'  => $product_name ?: '-',
		'criteria' => $criteria_count,
		'pros'     => $pros_count,
		'cons'     => $cons_count,
	);
}

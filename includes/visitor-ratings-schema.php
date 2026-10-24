<?php
defined( 'ABSPATH' ) || exit;

/**
 * Supply the aggregateRating for the review schema from approved visitor ratings.
 *
 * @param array|null $aggregate Aggregate rating from earlier filters, or null.
 * @param int        $post_id   Post ID.
 * @param array      $review    Review data.
 * @return array|null
 */
function verdix_pro_schema_aggregate_rating( $aggregate, $post_id, $review ) {
	if ( ! verdix_pro_visitor_ratings_enabled( $post_id ) ) {
		return $aggregate;
	}

	$data    = verdix_pro_get_visitor_ratings( $post_id );
	$options = get_option( 'verdix_settings', array() );
	$min     = isset( $options['visitor_min_ratings'] ) ? max( 1, (int) $options['visitor_min_ratings'] ) : 1;

	if ( $data['count'] < $min ) {
		return $aggregate;
	}

	return array(
		'@type'       => 'AggregateRating',
		'ratingValue' => (float) $data['avg'],
		'ratingCount' => (int) $data['count'],
		'bestRating'  => 5,
		'worstRating' => 1,
	);
}
add_filter( 'verdix_schema_aggregate_rating', 'verdix_pro_schema_aggregate_rating', 10, 3 );

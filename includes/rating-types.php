<?php
defined( 'ABSPATH' ) || exit;

function verdix_pro_register_rating_types( $types ) {
	$types['percentage'] = array(
		'label'  => __( 'Percentage (0-100%)', 'verdix-pro' ),
		'max'    => 100,
		'step'   => 1,
		'render' => 'verdix_pro_render_percentage',
		'format' => '%s%%',
	);
	$types['point'] = array(
		'label'  => __( 'Points (0-10)', 'verdix-pro' ),
		'max'    => 10,
		'step'   => 0.5,
		'render' => 'verdix_pro_render_points',
		'format' => '%s/10',
	);
	return $types;
}
add_filter( 'verdix_rating_types', 'verdix_pro_register_rating_types' );

function verdix_pro_render_percentage( $rating ) {
	$percentage    = max( 0, min( 100, round( $rating ) ) );
	$circumference = 2 * 3.14159 * 36;
	$offset        = $circumference - ( $circumference * $percentage / 100 );

	$html  = '<span class="verdix-box__score-box">' . esc_html( $percentage ) . '%</span>';
	$html .= '<div class="verdix-box__percentage" aria-label="' . esc_attr( sprintf( __( 'Rating: %s%%', 'verdix-pro' ), $percentage ) ) . '">';
	$html .= '<svg class="verdix-box__percentage-circle" width="80" height="80" viewBox="0 0 80 80">';
	$html .= '<circle cx="40" cy="40" r="36" fill="none" stroke="#e0e0e0" stroke-width="6"/>';
	$html .= '<circle cx="40" cy="40" r="36" fill="none" stroke="var(--sb-accent, #1e73be)" stroke-width="6" '
		. 'stroke-dasharray="' . esc_attr( $circumference ) . '" '
		. 'stroke-dashoffset="' . esc_attr( $offset ) . '" '
		. 'stroke-linecap="round" transform="rotate(-90 40 40)"/>';
	$html .= '<text x="40" y="40" text-anchor="middle" dominant-baseline="central" '
		. 'font-size="18" font-weight="700" fill="#333">' . esc_html( $percentage ) . '%</text>';
	$html .= '</svg>';
	$html .= '</div>';

	return $html;
}

function verdix_pro_render_points( $rating ) {
	$points  = max( 0, min( 10, $rating ) );
	$display = ( floor( $points ) == $points ) ? number_format( $points, 0 ) : number_format( $points, 1 );

	$html  = '<span class="verdix-box__score-box">' . esc_html( $display ) . '</span>';
	$html .= '<div class="verdix-box__points" aria-label="' . esc_attr( sprintf( __( 'Rating: %s out of 10', 'verdix-pro' ), $display ) ) . '">';
	$html .= '<span class="verdix-box__points-value">' . esc_html( $display ) . '</span>';
	$html .= '<span class="verdix-box__points-max">/10</span>';
	$html .= '</div>';

	return $html;
}

/**
 * Render the circular score graphic used in the split layout.
 *
 * Normalises the rating to a 0-100 percentage regardless of type, then draws
 * a circular SVG progress indicator with the formatted score value in the centre.
 *
 * @param float  $rating      Raw rating value.
 * @param string $rating_type Rating type slug (star|percentage|point).
 * @return string HTML string.
 */
function verdix_pro_render_score_circle( $rating, $rating_type ) {
	switch ( $rating_type ) {
		case 'percentage':
			$percentage   = max( 0, min( 100, round( $rating ) ) );
			$center_label = $percentage . '%';
			break;
		case 'point':
			$points       = max( 0, min( 10, $rating ) );
			$percentage   = round( ( $points / 10 ) * 100 );
			$center_label = ( floor( $points ) == $points )
				? number_format( $points, 0 ) . '/10'
				: number_format( $points, 1 ) . '/10';
			break;
		default: // star
			$stars        = max( 0, min( 5, $rating ) );
			$percentage   = round( ( $stars / 5 ) * 100 );
			$center_label = $stars . '/5';
			break;
	}

	$circumference = 2 * 3.14159 * 36;
	$offset        = $circumference - ( $circumference * $percentage / 100 );

	$html  = '<div class="verdix-box__score-circle" aria-label="' . esc_attr( $center_label ) . '">';
	$html .= '<svg width="90" height="90" viewBox="0 0 80 80">';
	$html .= '<circle cx="40" cy="40" r="36" fill="none" stroke="#e0e0e0" stroke-width="6"/>';
	$html .= '<circle cx="40" cy="40" r="36" fill="none" stroke="var(--sb-accent, #1e73be)" stroke-width="6" '
		. 'stroke-dasharray="' . esc_attr( $circumference ) . '" '
		. 'stroke-dashoffset="' . esc_attr( $offset ) . '" '
		. 'stroke-linecap="round" transform="rotate(-90 40 40)"/>';
	$html .= '<text x="40" y="40" text-anchor="middle" dominant-baseline="central" '
		. 'font-size="14" font-weight="700" fill="#333">' . esc_html( $center_label ) . '</text>';
	$html .= '</svg>';
	$html .= '</div>';

	return $html;
}

add_action( 'verdix_render_split_score', 'verdix_pro_render_split_score', 10, 2 );
function verdix_pro_render_split_score( $review, $post_id ) {
	$rating_type = ! empty( $review['rating_type'] ) ? $review['rating_type'] : 'star';
	echo verdix_pro_render_score_circle( $review['rating'], $rating_type ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

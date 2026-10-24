<?php
defined( 'ABSPATH' ) || exit;

/**
 * Register the [verdix_compare] shortcode.
 */
function verdix_pro_register_compare_shortcode() {
	add_shortcode( 'verdix_compare', 'verdix_pro_compare_handler' );
}
add_action( 'init', 'verdix_pro_register_compare_shortcode' );

/**
 * Default fields for comparison table.
 *
 * @return array Field definitions.
 */
function verdix_pro_compare_default_fields() {
	$fields = array(
		'rating'  => array(
			'label'  => __( 'Rating', 'verdix-pro' ),
			'render' => 'verdix_pro_compare_field_rating',
		),
		'summary' => array(
			'label'  => __( 'Summary', 'verdix-pro' ),
			'render' => 'verdix_pro_compare_field_summary',
		),
		'pros'    => array(
			'label'  => __( 'Pros', 'verdix-pro' ),
			'render' => 'verdix_pro_compare_field_pros',
		),
		'cons'    => array(
			'label'  => __( 'Cons', 'verdix-pro' ),
			'render' => 'verdix_pro_compare_field_cons',
		),
		'cta'     => array(
			'label'  => __( 'Link', 'verdix-pro' ),
			'render' => 'verdix_pro_compare_field_cta',
		),
		'price'   => array(
			'label'  => __( 'Price', 'verdix-pro' ),
			'render' => 'verdix_pro_compare_field_price',
		),
	);

	return apply_filters( 'verdix_compare_fields', $fields );
}

/**
 * Handle the [verdix_compare] shortcode.
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML output.
 */
function verdix_pro_compare_handler( $atts ) {
	$atts = shortcode_atts(
		array(
			'ids'       => '',
			'fields'    => 'rating,summary,pros,cons,cta',
			'highlight' => 'true',
		),
		$atts,
		'verdix_compare'
	);

	if ( empty( $atts['ids'] ) ) {
		return '';
	}

	$post_ids = array_map( 'absint', explode( ',', $atts['ids'] ) );
	$post_ids = array_filter( $post_ids );

	if ( count( $post_ids ) < 2 ) {
		return '';
	}

	$field_keys     = array_map( 'trim', explode( ',', $atts['fields'] ) );
	$all_fields     = verdix_pro_compare_default_fields();
	$highlight      = 'true' === $atts['highlight'] || '1' === $atts['highlight'];

	// Fetch review data for each post.
	$items = array();
	foreach ( $post_ids as $pid ) {
		if ( 'publish' !== get_post_status( $pid ) || post_password_required( $pid ) ) {
			continue;
		}
		$review = verdix_get_review( $pid );
		if ( ! $review || empty( $review['rating'] ) ) {
			continue;
		}
		$items[] = array(
			'post_id' => $pid,
			'title'   => get_the_title( $pid ),
			'image'   => get_the_post_thumbnail_url( $pid, 'medium' ),
			'url'     => get_permalink( $pid ),
			'review'  => $review,
		);
	}

	if ( count( $items ) < 2 ) {
		return '';
	}

	// Find highest rating for highlight.
	$best_id    = 0;
	$best_score = -1;
	if ( $highlight ) {
		foreach ( $items as $item ) {
			$rating_type = ! empty( $item['review']['rating_type'] ) ? $item['review']['rating_type'] : 'star';
			$normalized  = verdix_normalize_to_star_scale( $item['review']['rating'], $rating_type );
			if ( $normalized > $best_score ) {
				$best_score = $normalized;
				$best_id    = $item['post_id'];
			}
		}
	}

	// Enqueue styles.
	wp_enqueue_style(
		'verdix-pro-comparison',
		VERDIX_PRO_URL . 'assets/css/comparison.css',
		array(),
		VERDIX_PRO_VERSION
	);

	return verdix_pro_render_compare_table( $items, $field_keys, $all_fields, $best_id );
}

/**
 * Render the comparison table HTML.
 *
 * @param array  $items      Array of item data.
 * @param array  $field_keys Fields to display.
 * @param array  $all_fields Field definitions.
 * @param int    $best_id    Post ID with highest rating (0 to disable highlight).
 * @return string HTML.
 */
function verdix_pro_render_compare_table( $items, $field_keys, $all_fields, $best_id = 0 ) {
	$options      = get_option( 'verdix_settings', array() );
	$accent_color = ! empty( $options['accent_color'] ) ? $options['accent_color'] : '#1e73be';

	ob_start();
	?>
	<div class="verdix-compare" style="--sb-accent: <?php echo esc_attr( $accent_color ); ?>;">
		<div class="verdix-compare__scroll">
			<table class="verdix-compare__table">
				<thead>
					<tr>
						<th class="verdix-compare__corner"></th>
						<?php foreach ( $items as $item ) : ?>
							<th class="verdix-compare__header <?php echo $best_id === $item['post_id'] ? 'verdix-compare__header--best' : ''; ?>">
								<?php if ( $best_id === $item['post_id'] ) : ?>
									<span class="verdix-compare__badge"><?php esc_html_e( 'Best Pick', 'verdix-pro' ); ?></span>
								<?php endif; ?>
								<?php if ( $item['image'] ) : ?>
									<img src="<?php echo esc_url( $item['image'] ); ?>" alt="" class="verdix-compare__thumb">
								<?php endif; ?>
								<a href="<?php echo esc_url( $item['url'] ); ?>" class="verdix-compare__title">
									<?php echo esc_html( $item['title'] ); ?>
								</a>
							</th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $field_keys as $key ) : ?>
						<?php if ( ! isset( $all_fields[ $key ] ) ) continue; ?>
						<tr>
							<td class="verdix-compare__label"><?php echo esc_html( $all_fields[ $key ]['label'] ); ?></td>
							<?php foreach ( $items as $item ) : ?>
								<td class="verdix-compare__cell <?php echo $best_id === $item['post_id'] ? 'verdix-compare__cell--best' : ''; ?>">
									<?php
									if ( is_callable( $all_fields[ $key ]['render'] ) ) {
										echo call_user_func( $all_fields[ $key ]['render'], $item['review'], $item['post_id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									}
									?>
								</td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php
	$html = ob_get_clean();

	do_action( 'verdix_compare_render', $items, $field_keys );

	return $html;
}

// =========================================================================
// Field render callbacks
// =========================================================================

function verdix_pro_compare_field_rating( $review, $post_id ) {
	$rating      = $review['rating'];
	$rating_type = ! empty( $review['rating_type'] ) ? $review['rating_type'] : 'star';
	$types       = verdix_get_rating_types();

	$html = '<div class="verdix-compare__rating">';

	// Score.
	if ( isset( $types[ $rating_type ] ) ) {
		$html .= '<strong class="verdix-compare__score">' . esc_html( sprintf( $types[ $rating_type ]['format'], $rating ) ) . '</strong>';
	} else {
		$html .= '<strong class="verdix-compare__score">' . esc_html( $rating ) . '</strong>';
	}

	// Stars for star type.
	if ( 'star' === $rating_type ) {
		$percentage = ( $rating / 5 ) * 100;
		$star_svg   = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
		$five       = str_repeat( $star_svg, 5 );
		$html .= '<div class="verdix-compare__stars">';
		$html .= '<span class="verdix-compare__stars-bg">' . $five . '</span>';
		$html .= '<span class="verdix-compare__stars-fg" style="width:' . esc_attr( $percentage ) . '%;">' . $five . '</span>';
		$html .= '</div>';
	}

	$html .= '</div>';
	return $html;
}

function verdix_pro_compare_field_summary( $review, $post_id ) {
	if ( empty( $review['summary'] ) ) {
		return '<span class="verdix-compare__empty">&mdash;</span>';
	}
	$text = $review['summary'];
	if ( strlen( $text ) > 150 ) {
		$text = substr( $text, 0, 147 ) . '...';
	}
	return '<p class="verdix-compare__summary">' . esc_html( $text ) . '</p>';
}

function verdix_pro_compare_field_pros( $review, $post_id ) {
	if ( empty( $review['pros'] ) ) {
		return '<span class="verdix-compare__empty">&mdash;</span>';
	}
	$html = '<ul class="verdix-compare__pros">';
	foreach ( $review['pros'] as $pro ) {
		$html .= '<li>' . esc_html( $pro ) . '</li>';
	}
	$html .= '</ul>';
	return $html;
}

function verdix_pro_compare_field_cons( $review, $post_id ) {
	if ( empty( $review['cons'] ) ) {
		return '<span class="verdix-compare__empty">&mdash;</span>';
	}
	$html = '<ul class="verdix-compare__cons">';
	foreach ( $review['cons'] as $con ) {
		$html .= '<li>' . esc_html( $con ) . '</li>';
	}
	$html .= '</ul>';
	return $html;
}

// The first offer stands in for the product; verdix_get_offers() also maps the legacy price and button fields.
function verdix_pro_compare_offer( $review ) {
	if ( ! function_exists( 'verdix_get_offers' ) ) {
		return array(
			'price'    => isset( $review['price'] ) ? $review['price'] : '',
			'currency' => ! empty( $review['currency'] ) ? $review['currency'] : 'USD',
			'href'     => isset( $review['cta_url'] ) ? $review['cta_url'] : '',
			'cta_text' => isset( $review['cta_text'] ) ? $review['cta_text'] : '',
		);
	}
	$offers = verdix_get_offers( $review );
	return $offers ? $offers[0] : array();
}

function verdix_pro_compare_field_cta( $review, $post_id ) {
	$offer = verdix_pro_compare_offer( $review );
	if ( empty( $offer['href'] ) ) {
		return '<span class="verdix-compare__empty">&mdash;</span>';
	}
	$text = ! empty( $offer['cta_text'] ) ? $offer['cta_text'] : __( 'Visit', 'verdix-pro' );
	$rel  = function_exists( 'verdix_offer_rel' ) ? verdix_offer_rel( $offer, $review ) : 'noopener noreferrer nofollow';
	return '<a href="' . esc_url( $offer['href'] ) . '" class="verdix-compare__cta" target="_blank" rel="' . esc_attr( $rel ) . '">'
		. esc_html( $text ) . '</a>';
}

function verdix_pro_compare_field_price( $review, $post_id ) {
	$offer = verdix_pro_compare_offer( $review );
	if ( empty( $offer['price'] ) ) {
		return '<span class="verdix-compare__empty">&mdash;</span>';
	}
	$currency = ! empty( $offer['currency'] ) ? $offer['currency'] : 'USD';
	$symbols  = array( 'USD' => '$', 'EUR' => "\xe2\x82\xac", 'GBP' => "\xc2\xa3" );
	$symbol   = isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : $currency . ' ';
	return '<span class="verdix-compare__price">' . esc_html( $symbol . $offer['price'] ) . '</span>';
}

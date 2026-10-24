<?php
defined( 'ABSPATH' ) || exit;

// =========================================================================
// Review box display
// =========================================================================

/**
 * Render visitor rating aggregate inside the review box.
 *
 * @param array $review  Review data.
 * @param int   $post_id Post ID.
 */
function verdix_pro_render_visitor_rating( $review, $post_id ) {
	if ( ! verdix_pro_visitor_ratings_enabled( $post_id ) ) {
		return;
	}

	$data    = verdix_pro_get_visitor_ratings( $post_id );
	$options = get_option( 'verdix_settings', array() );
	$min     = isset( $options['visitor_min_ratings'] ) ? max( 1, (int) $options['visitor_min_ratings'] ) : 1;

	if ( $data['count'] < $min ) {
		return;
	}

	$show_count = ! isset( $options['visitor_show_count'] ) || ! empty( $options['visitor_show_count'] );
	$avg        = $data['avg'];
	$count      = $data['count'];
	$percentage = ( $avg / 5 ) * 100;

	$star_svg   = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
	$five_stars = str_repeat( $star_svg, 5 );
	?>
	<div class="verdix-visitor-rating">
		<span class="verdix-visitor-rating__label"><?php esc_html_e( 'Reader Rating', 'verdix-pro' ); ?></span>
		<span class="verdix-visitor-rating__score"><?php echo esc_html( $avg ); ?>/5</span>
		<div class="verdix-visitor-rating__stars">
			<div class="verdix-visitor-rating__stars-bg"><?php echo $five_stars; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="verdix-visitor-rating__stars-fg" style="width:<?php echo esc_attr( $percentage ); ?>%;"><?php echo $five_stars; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		</div>
		<?php if ( $show_count ) : ?>
			<span class="verdix-visitor-rating__count">
				<?php
				printf(
					/* translators: %d: number of votes */
					esc_html( _n( '(%d vote)', '(%d votes)', $count, 'verdix-pro' ) ),
					$count
				);
				?>
			</span>
		<?php endif; ?>
	</div>
	<?php
}
add_action( 'verdix_render_after_rating', 'verdix_pro_render_visitor_rating', 10, 2 );

/**
 * Render a mocked visitor-rating widget on the Appearance preview, where no
 * real post exists. Recognised by $post_id === 0, which is how the preview
 * passes the sample review into verdix_render_box().
 */
function verdix_pro_render_visitor_rating_preview( $review, $post_id ) {
	if ( 0 !== (int) $post_id ) {
		return;
	}
	if ( ! is_admin() ) {
		return;
	}

	$avg        = 4.3;
	$count      = 27;
	$percentage = ( $avg / 5 ) * 100;
	$star_svg   = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
	$five_stars = str_repeat( $star_svg, 5 );
	?>
	<div class="verdix-visitor-rating">
		<span class="verdix-visitor-rating__label"><?php esc_html_e( 'Reader Rating', 'verdix-pro' ); ?></span>
		<span class="verdix-visitor-rating__score"><?php echo esc_html( $avg ); ?>/5</span>
		<div class="verdix-visitor-rating__stars">
			<div class="verdix-visitor-rating__stars-bg"><?php echo $five_stars; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="verdix-visitor-rating__stars-fg" style="width:<?php echo esc_attr( $percentage ); ?>%;"><?php echo $five_stars; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		</div>
		<span class="verdix-visitor-rating__count">
			<?php
			printf(
				/* translators: %d: number of votes */
				esc_html( _n( '(%d vote)', '(%d votes)', $count, 'verdix-pro' ) ),
				(int) $count
			);
			?>
		</span>
	</div>
	<?php
}
add_action( 'verdix_render_after_rating', 'verdix_pro_render_visitor_rating_preview', 20, 2 );

// =========================================================================
// Comment form stars
// =========================================================================

/**
 * Inject rating stars into the comment form.
 */
function verdix_pro_comment_form_rating() {
	if ( ! is_singular() ) {
		return;
	}

	$post_id = get_the_ID();
	if ( ! verdix_pro_visitor_ratings_enabled( $post_id ) ) {
		return;
	}

	$existing_rating = verdix_pro_visitor_already_rated( $post_id );
	$review          = verdix_get_review( $post_id );
	$has_criteria    = ! empty( $review['use_criteria'] ) && ! empty( $review['criteria'] );
	?>
	<div class="verdix-comment-rating" id="verdix-comment-rating">
		<?php if ( false !== $existing_rating ) : ?>
			<p class="verdix-comment-rating__already">
				<?php
				printf(
					/* translators: %s: rating value */
					esc_html__( 'You rated this %s/5', 'verdix-pro' ),
					esc_html( $existing_rating )
				);
				?>
			</p>
		<?php else : ?>
			<p class="verdix-comment-rating__heading">
				<strong><?php esc_html_e( 'Your Rating', 'verdix-pro' ); ?></strong>
			</p>
			<div class="verdix-comment-rating__stars" data-name="verdix_visitor_rating">
				<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
					<span class="verdix-comment-star" data-value="<?php echo esc_attr( $i ); ?>"
						role="button" tabindex="0"
						aria-label="<?php echo esc_attr( sprintf( __( '%d star', 'verdix-pro' ), $i ) ); ?>">
						<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
							<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="currentColor"/>
						</svg>
					</span>
				<?php endfor; ?>
				<input type="hidden" name="verdix_visitor_rating" value="0">
			</div>
			<?php wp_nonce_field( 'verdix_visitor_rating', 'verdix_visitor_rating_nonce' ); ?>

			<?php if ( $has_criteria ) : ?>
				<div class="verdix-comment-rating__criteria">
					<?php foreach ( $review['criteria'] as $criterion ) : ?>
						<div class="verdix-comment-criterion">
							<span class="verdix-comment-criterion__label"><?php echo esc_html( $criterion['label'] ); ?></span>
							<div class="verdix-comment-rating__stars" data-name="verdix_visitor_criteria[<?php echo esc_attr( $criterion['label'] ); ?>]">
								<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
									<span class="verdix-comment-star" data-value="<?php echo esc_attr( $i ); ?>"
										role="button" tabindex="0">
										<svg width="18" height="18" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
											<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="currentColor"/>
										</svg>
									</span>
								<?php endfor; ?>
								<input type="hidden" name="verdix_visitor_criteria[<?php echo esc_attr( $criterion['label'] ); ?>]" value="0">
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}
add_action( 'comment_form_after_fields', 'verdix_pro_comment_form_rating' );
add_action( 'comment_form_logged_in_after', 'verdix_pro_comment_form_rating' );

// =========================================================================
// Enqueue assets
// =========================================================================

/**
 * Enqueue visitor rating frontend assets.
 */
function verdix_pro_enqueue_visitor_assets() {
	if ( ! is_singular() ) {
		return;
	}

	$post_id = get_the_ID();
	if ( ! verdix_pro_visitor_ratings_enabled( $post_id ) ) {
		return;
	}

	wp_enqueue_style(
		'verdix-pro-visitor-ratings',
		VERDIX_PRO_URL . 'assets/css/visitor-ratings.css',
		array( 'verdix-frontend' ),
		VERDIX_PRO_VERSION
	);

	wp_enqueue_script(
		'verdix-pro-visitor-ratings',
		VERDIX_PRO_URL . 'assets/js/visitor-ratings.js',
		array(),
		VERDIX_PRO_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'verdix_pro_enqueue_visitor_assets' );

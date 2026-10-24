<?php
defined( 'ABSPATH' ) || exit;

// =========================================================================
// Settings
// =========================================================================

/**
 * Register visitor rating settings fields in the General tab.
 */
function verdix_pro_visitor_settings() {
	$options = get_option( 'verdix_settings', array() );
	?>
	<tr>
		<th scope="row"><?php esc_html_e( 'Visitor Ratings', 'verdix-pro' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="verdix_settings[enable_visitor_ratings]" value="1"
					<?php checked( ! empty( $options['enable_visitor_ratings'] ) ); ?>>
				<?php esc_html_e( 'Allow visitors to rate reviewed products', 'verdix-pro' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Show Vote Count', 'verdix-pro' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="verdix_settings[visitor_show_count]" value="1"
					<?php checked( ! empty( $options['visitor_show_count'] ) || ! isset( $options['visitor_show_count'] ) ); ?>>
				<?php esc_html_e( 'Display number of visitor ratings', 'verdix-pro' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row">
			<label for="verdix_visitor_min_ratings"><?php esc_html_e( 'Minimum Ratings', 'verdix-pro' ); ?></label>
		</th>
		<td>
			<input type="number" id="verdix_visitor_min_ratings" name="verdix_settings[visitor_min_ratings]"
				value="<?php echo esc_attr( isset( $options['visitor_min_ratings'] ) ? $options['visitor_min_ratings'] : 1 ); ?>"
				min="1" max="100" style="width: 70px;">
			<p class="description"><?php esc_html_e( 'Minimum number of ratings before showing the aggregate.', 'verdix-pro' ); ?></p>
		</td>
	</tr>
	<?php
}

/**
 * Add settings fields after the existing General tab fields.
 * Hooks into a new action we'll fire from the settings page.
 */
function verdix_pro_visitor_settings_section() {
	add_action( 'verdix_settings_after_general', 'verdix_pro_visitor_settings' );
}
add_action( 'admin_init', 'verdix_pro_visitor_settings_section' );

/**
 * Sanitize visitor rating settings.
 *
 * @param array $output Settings being saved.
 * @return array Modified settings.
 */
function verdix_pro_sanitize_visitor_settings( $output ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by Settings API.
	$post_data = isset( $_POST['verdix_settings'] ) ? wp_unslash( $_POST['verdix_settings'] ) : array();
	if ( ! empty( $post_data['enable_visitor_ratings'] ) ) {
		$output['enable_visitor_ratings'] = 1;
	}
	if ( ! empty( $post_data['visitor_show_count'] ) ) {
		$output['visitor_show_count'] = 1;
	}
	if ( isset( $post_data['visitor_min_ratings'] ) ) {
		$output['visitor_min_ratings'] = absint( $post_data['visitor_min_ratings'] );
	}
	return $output;
}
add_filter( 'pre_update_option_verdix_settings', 'verdix_pro_sanitize_visitor_settings' );

// =========================================================================
// Save rating from comment
// =========================================================================

/**
 * Save visitor rating when a comment is posted.
 *
 * @param int        $comment_id Comment ID.
 * @param int|string $approved   1 if approved, 0 if pending, 'spam' if spam.
 */
function verdix_pro_save_visitor_rating( $comment_id, $approved ) {
	if ( ! isset( $_POST['verdix_visitor_rating'] ) ) {
		return;
	}

	// Verify our nonce.
	if ( ! isset( $_POST['verdix_visitor_rating_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['verdix_visitor_rating_nonce'] ) ), 'verdix_visitor_rating' ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- floatval is the sanitizer.
	$rating = floatval( wp_unslash( $_POST['verdix_visitor_rating'] ) );
	$rating = round( $rating * 2 ) / 2; // Snap to half-star.
	$rating = max( 0, min( 5, $rating ) );

	if ( $rating <= 0 ) {
		return;
	}

	$comment = get_comment( $comment_id );
	if ( ! $comment || ! verdix_pro_visitor_ratings_enabled( $comment->comment_post_ID ) || verdix_pro_has_rated( $comment ) ) {
		return;
	}

	update_comment_meta( $comment_id, '_verdix_visitor_rating', $rating );

	// Save per-criteria ratings — validate labels against the post's actual criteria.
	$review = verdix_get_review( $comment->comment_post_ID );

	if ( isset( $_POST['verdix_visitor_criteria'] ) && is_array( $_POST['verdix_visitor_criteria'] ) && $review ) {
		$valid_labels = array();
		if ( ! empty( $review['criteria'] ) ) {
			foreach ( $review['criteria'] as $c ) {
				$valid_labels[] = $c['label'];
			}
		}

		$raw_criteria = wp_unslash( $_POST['verdix_visitor_criteria'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$criteria = array();
		foreach ( $raw_criteria as $label => $value ) {
			$label = sanitize_text_field( $label );
			if ( ! in_array( $label, $valid_labels, true ) ) {
				continue; // Skip criteria not in the review.
			}
			$criteria[] = array(
				'label'  => $label,
				'rating' => max( 0, min( 5, round( floatval( $value ) * 2 ) / 2 ) ),
			);
		}
		if ( ! empty( $criteria ) ) {
			update_comment_meta( $comment_id, '_verdix_visitor_criteria', wp_json_encode( $criteria ) );
		}
	}

	// Refresh cache if comment is approved.
	if ( 1 === (int) $approved ) {
		$comment = get_comment( $comment_id );
		if ( $comment ) {
			verdix_pro_refresh_visitor_cache( $comment->comment_post_ID );
		}
	}
}
add_action( 'comment_post', 'verdix_pro_save_visitor_rating', 10, 2 );

// One rating per visitor and post, matched on user ID when logged in, otherwise on email.
function verdix_pro_has_rated( $comment ) {
	$args = array(
		'post_id'      => $comment->comment_post_ID,
		'status'       => 'all',
		'meta_key'     => '_verdix_visitor_rating',
		'meta_compare' => 'EXISTS',
		'count'        => true,
	);

	if ( $comment->user_id ) {
		$args['user_id'] = (int) $comment->user_id;
	} elseif ( '' !== $comment->comment_author_email ) {
		$args['author_email'] = $comment->comment_author_email;
	} else {
		return false;
	}

	return get_comments( $args ) > 0;
}

// =========================================================================
// Cache management
// =========================================================================

/**
 * Recalculate and cache visitor rating aggregates for a post.
 *
 * @param int $post_id Post ID.
 */
function verdix_pro_refresh_visitor_cache( $post_id ) {
	$comments = get_comments( array(
		'post_id'      => $post_id,
		'status'       => 'approve',
		'meta_key'     => '_verdix_visitor_rating',
		'meta_compare' => 'EXISTS',
	) );

	$total           = 0;
	$count           = 0;
	$criteria_sums   = array();
	$criteria_counts = array();

	foreach ( $comments as $comment ) {
		$rating = (float) get_comment_meta( $comment->comment_ID, '_verdix_visitor_rating', true );
		if ( $rating > 0 ) {
			$total += $rating;
			$count++;
		}

		$criteria_raw = get_comment_meta( $comment->comment_ID, '_verdix_visitor_criteria', true );
		if ( $criteria_raw ) {
			$criteria = json_decode( $criteria_raw, true );
			if ( is_array( $criteria ) ) {
				foreach ( $criteria as $c ) {
					$label = $c['label'];
					if ( ! isset( $criteria_sums[ $label ] ) ) {
						$criteria_sums[ $label ]   = 0;
						$criteria_counts[ $label ] = 0;
					}
					$criteria_sums[ $label ]   += $c['rating'];
					$criteria_counts[ $label ]++;
				}
			}
		}
	}

	$avg = $count > 0 ? round( $total / $count, 1 ) : 0;
	update_post_meta( $post_id, '_verdix_visitor_avg', $avg );
	update_post_meta( $post_id, '_verdix_visitor_count', $count );

	// Per-criteria averages.
	$criteria_avg = array();
	foreach ( $criteria_sums as $label => $sum ) {
		$criteria_avg[] = array(
			'label'  => $label,
			'rating' => round( $sum / $criteria_counts[ $label ], 1 ),
			'count'  => $criteria_counts[ $label ],
		);
	}
	update_post_meta( $post_id, '_verdix_visitor_criteria_avg', wp_json_encode( $criteria_avg ) );
}

/**
 * Invalidate cache when comment status changes.
 *
 * @param string $new_status New status.
 * @param string $old_status Old status.
 * @param object $comment    Comment object.
 */
function verdix_pro_comment_status_change( $new_status, $old_status, $comment ) {
	$has_rating = get_comment_meta( $comment->comment_ID, '_verdix_visitor_rating', true );
	if ( $has_rating ) {
		verdix_pro_refresh_visitor_cache( $comment->comment_post_ID );
	}
}
add_action( 'transition_comment_status', 'verdix_pro_comment_status_change', 10, 3 );

/**
 * Invalidate cache when a comment is deleted.
 *
 * @param int $comment_id Comment ID.
 */
function verdix_pro_comment_deleted( $comment_id ) {
	$comment = get_comment( $comment_id );
	if ( ! $comment ) {
		return;
	}
	$has_rating = get_comment_meta( $comment_id, '_verdix_visitor_rating', true );
	if ( $has_rating ) {
		verdix_pro_refresh_visitor_cache( $comment->comment_post_ID );
	}
}
add_action( 'delete_comment', 'verdix_pro_comment_deleted' );

/**
 * Get cached visitor rating data for a post.
 *
 * @param int $post_id Post ID.
 * @return array { avg: float, count: int, criteria: array }
 */
function verdix_pro_get_visitor_ratings( $post_id ) {
	return array(
		'avg'      => (float) get_post_meta( $post_id, '_verdix_visitor_avg', true ),
		'count'    => (int) get_post_meta( $post_id, '_verdix_visitor_count', true ),
		'criteria' => json_decode( get_post_meta( $post_id, '_verdix_visitor_criteria_avg', true ) ?: '[]', true ),
	);
}

/**
 * Check if visitor already rated this post.
 *
 * @param int $post_id Post ID.
 * @return float|false Rating value or false.
 */
function verdix_pro_visitor_already_rated( $post_id ) {
	if ( is_user_logged_in() ) {
		$user_email = wp_get_current_user()->user_email;
	} else {
		$user_email = isset( $_COOKIE['comment_author_email_' . COOKIEHASH] )
			? sanitize_email( $_COOKIE['comment_author_email_' . COOKIEHASH] )
			: '';
	}

	if ( empty( $user_email ) ) {
		return false;
	}

	$comments = get_comments( array(
		'post_id'      => $post_id,
		'author_email' => $user_email,
		'meta_key'     => '_verdix_visitor_rating',
		'meta_compare' => 'EXISTS',
		'number'       => 1,
	) );

	if ( ! empty( $comments ) ) {
		return (float) get_comment_meta( $comments[0]->comment_ID, '_verdix_visitor_rating', true );
	}

	return false;
}

/**
 * Check if visitor ratings are enabled for a post.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function verdix_pro_visitor_ratings_enabled( $post_id ) {
	$options = get_option( 'verdix_settings', array() );
	if ( empty( $options['enable_visitor_ratings'] ) ) {
		return false;
	}

	// Per-post override.
	$review = verdix_get_review( $post_id );
	if ( $review && isset( $review['disable_visitor_ratings'] ) && $review['disable_visitor_ratings'] ) {
		return false;
	}

	// Must have review data.
	if ( ! $review || empty( $review['rating'] ) ) {
		return false;
	}

	return true;
}

// =========================================================================
// Per-post toggle
// =========================================================================

/**
 * Add per-post visitor ratings toggle to the meta box.
 *
 * @param array $data Review data.
 */
function verdix_pro_visitor_meta_box_toggle( $data ) {
	$options = get_option( 'verdix_settings', array() );
	if ( empty( $options['enable_visitor_ratings'] ) ) {
		return;
	}
	?>
	<tr>
		<th scope="row"><?php esc_html_e( 'Visitor Ratings', 'verdix-pro' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="verdix_review_disable_visitor_ratings" value="1"
					<?php checked( ! empty( $data['disable_visitor_ratings'] ) ); ?>>
				<?php esc_html_e( 'Disable visitor ratings for this post', 'verdix-pro' ); ?>
			</label>
		</td>
	</tr>
	<?php
}
add_action( 'verdix_meta_box_fields', 'verdix_pro_visitor_meta_box_toggle', 20 );

/**
 * Save per-post visitor ratings toggle.
 *
 * @param array $data    Review data.
 * @param int   $post_id Post ID.
 * @return array Modified data.
 */
function verdix_pro_visitor_save_toggle( $data, $post_id ) {
	$data['disable_visitor_ratings'] = ! empty( $_POST['verdix_review_disable_visitor_ratings'] );
	return $data;
}
add_filter( 'verdix_meta_box_save_data', 'verdix_pro_visitor_save_toggle', 20, 2 );

/**
 * Preserve disable_visitor_ratings through the sanitizer.
 *
 * @param array $clean Sanitized data.
 * @param array $data  Raw data.
 * @return array Modified clean data.
 */
function verdix_pro_sanitize_visitor_toggle( $clean, $data ) {
	$clean['disable_visitor_ratings'] = ! empty( $data['disable_visitor_ratings'] );
	return $clean;
}
add_filter( 'verdix_sanitize_review_data', 'verdix_pro_sanitize_visitor_toggle', 10, 2 );

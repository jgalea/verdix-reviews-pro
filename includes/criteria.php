<?php
defined( 'ABSPATH' ) || exit;

function verdix_pro_enable_criteria( $config ) {
	$config['features']['criteria'] = true;
	return $config;
}
add_filter( 'verdix_editor_config', 'verdix_pro_enable_criteria' );

/**
 * Enable criteria on the Appearance preview so users can see how it looks.
 */
function verdix_pro_preview_criteria( $review ) {
	$review['use_criteria'] = true;
	$review['criteria']     = array(
		array( 'label' => __( 'Features', 'verdix-pro' ),        'rating' => 4.5 ),
		array( 'label' => __( 'Value for Money', 'verdix-pro' ), 'rating' => 4.0 ),
		array( 'label' => __( 'Ease of Use', 'verdix-pro' ),     'rating' => 4.5 ),
	);
	return $review;
}
add_filter( 'verdix_preview_review', 'verdix_pro_preview_criteria' );

function verdix_pro_render_criteria( $review, $post_id ) {
	if ( empty( $review['use_criteria'] ) || empty( $review['criteria'] ) ) {
		return;
	}
	?>
	<div class="verdix-box__criteria">
		<?php foreach ( $review['criteria'] as $criterion ) : ?>
			<div class="verdix-box__criterion">
				<span class="verdix-box__criterion-label"><?php echo esc_html( $criterion['label'] ); ?></span>
				<div class="verdix-box__criterion-bar">
					<div class="verdix-box__criterion-fill" style="width: <?php echo esc_attr( ( $criterion['rating'] / 5 ) * 100 ); ?>%;"></div>
				</div>
				<span class="verdix-box__criterion-value"><?php echo esc_html( $criterion['rating'] ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}
add_action( 'verdix_render_after_heading', 'verdix_pro_render_criteria', 10, 2 );

function verdix_pro_meta_box_criteria( $data ) {
	$criteria = ! empty( $data['criteria'] ) ? $data['criteria'] : array();
	?>
	<tr>
		<th scope="row">
			<label for="verdix_review_use_criteria"><?php esc_html_e( 'Multi-Criteria', 'verdix-pro' ); ?></label>
		</th>
		<td>
			<label>
				<input type="checkbox" id="verdix_review_use_criteria" name="verdix_review_use_criteria" value="1"
					<?php checked( ! empty( $data['use_criteria'] ) ); ?>
					onchange="document.getElementById('verdix-review-criteria-section').style.display = this.checked ? '' : 'none';">
				<?php esc_html_e( 'Enable multi-criteria ratings', 'verdix-pro' ); ?>
			</label>
		</td>
	</tr>
	<tr id="verdix-review-criteria-section" style="<?php echo empty( $data['use_criteria'] ) ? 'display: none;' : ''; ?>">
		<th scope="row"><label><?php esc_html_e( 'Criteria', 'verdix-pro' ); ?></label></th>
		<td>
			<div id="verdix-review-criteria-list">
				<?php foreach ( $criteria as $criterion ) : ?>
					<div class="verdix-criteria-row" style="display: flex; gap: 8px; margin-bottom: 6px; align-items: center;">
						<input type="text" name="verdix_review_criteria_label[]"
							value="<?php echo esc_attr( $criterion['label'] ); ?>"
							placeholder="<?php esc_attr_e( 'e.g., Features', 'verdix-pro' ); ?>"
							class="regular-text" style="flex: 1;">
						<input type="number" name="verdix_review_criteria_rating[]"
							value="<?php echo esc_attr( $criterion['rating'] ); ?>"
							min="0" max="5" step="0.5" style="width: 80px;">
						<button type="button" class="button verdix-criteria-remove"
							onclick="this.parentElement.remove();"><?php esc_html_e( 'Remove', 'verdix-pro' ); ?></button>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button" id="verdix-review-add-criterion"><?php esc_html_e( '+ Add Criterion', 'verdix-pro' ); ?></button>
			<p class="description"><?php esc_html_e( 'Overall rating will be calculated as the average.', 'verdix-pro' ); ?></p>
		</td>
	</tr>
	<?php
}
add_action( 'verdix_meta_box_fields', 'verdix_pro_meta_box_criteria' );

function verdix_pro_save_criteria( $data, $post_id ) {
	$data['use_criteria'] = ! empty( $_POST['verdix_review_use_criteria'] );
	$data['criteria']     = array();

	if ( isset( $_POST['verdix_review_criteria_label'] ) && is_array( $_POST['verdix_review_criteria_label'] ) ) {
		$labels  = array_map( 'sanitize_text_field', wp_unslash( $_POST['verdix_review_criteria_label'] ) );
		$ratings = isset( $_POST['verdix_review_criteria_rating'] ) && is_array( $_POST['verdix_review_criteria_rating'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['verdix_review_criteria_rating'] ) )
			: array();

		foreach ( $labels as $i => $label ) {
			$label = trim( $label );
			if ( '' === $label ) {
				continue;
			}
			$data['criteria'][] = array(
				'label'  => $label,
				'rating' => isset( $ratings[ $i ] ) ? floatval( $ratings[ $i ] ) : 0,
			);
		}
	}

	// The overall rating field can be left at 0 when criteria drive it; fill it in so the review isn't treated as empty.
	if ( $data['use_criteria'] && $data['criteria'] ) {
		$data['rating'] = array_sum( wp_list_pluck( $data['criteria'], 'rating' ) ) / count( $data['criteria'] );
	}

	return $data;
}
add_filter( 'verdix_meta_box_save_data', 'verdix_pro_save_criteria', 10, 2 );

function verdix_pro_sanitize_criteria( $clean, $data ) {
	$clean['use_criteria'] = ! empty( $data['use_criteria'] );
	$clean['criteria']     = array();

	if ( ! empty( $data['criteria'] ) && is_array( $data['criteria'] ) ) {
		foreach ( $data['criteria'] as $criterion ) {
			$label = isset( $criterion['label'] ) ? sanitize_text_field( $criterion['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}
			$clean['criteria'][] = array(
				'label'  => $label,
				'rating' => max( 0, min( 5, round( (float) ( isset( $criterion['rating'] ) ? $criterion['rating'] : 0 ) * 2 ) / 2 ) ),
			);
		}
	}

	if ( $clean['use_criteria'] && $clean['criteria'] ) {
		// Criteria are rated 0-5; scale their average to the review's own rating type.
		$types           = function_exists( 'verdix_get_rating_types' ) ? verdix_get_rating_types() : array();
		$type            = isset( $types[ $clean['rating_type'] ?? '' ] ) ? $types[ $clean['rating_type'] ] : array( 'max' => 5, 'step' => 0.5 );
		$max             = isset( $type['max'] ) ? (float) $type['max'] : 5;
		$step            = ! empty( $type['step'] ) ? (float) $type['step'] : 0.5;
		$average         = array_sum( wp_list_pluck( $clean['criteria'], 'rating' ) ) / count( $clean['criteria'] );
		$clean['rating'] = round( $average / 5 * $max / $step ) * $step;
	}

	return $clean;
}
add_filter( 'verdix_sanitize_review_data', 'verdix_pro_sanitize_criteria', 10, 2 );

function verdix_pro_enqueue_editor_assets() {
	wp_enqueue_style(
		'verdix-pro-criteria',
		VERDIX_PRO_URL . 'assets/css/criteria.css',
		array(),
		VERDIX_PRO_VERSION
	);
	wp_enqueue_script(
		'verdix-pro-criteria-editor',
		VERDIX_PRO_URL . 'assets/js/criteria-editor.js',
		array( 'wp-element', 'wp-components', 'wp-block-editor', 'wp-compose', 'wp-data', 'wp-hooks' ),
		VERDIX_PRO_VERSION,
		true
	);

	$rating_types = array();
	foreach ( verdix_get_rating_types() as $slug => $type ) {
		$rating_types[ $slug ] = array(
			'max'  => (float) $type['max'],
			'step' => (float) $type['step'],
		);
	}
	wp_add_inline_script(
		'verdix-pro-criteria-editor',
		'window.verdixProCriteria = ' . wp_json_encode(
			array(
				'ratingTypes' => $rating_types,
				'i18n'        => array(
					'title'       => __( 'Multi-Criteria', 'verdix-pro' ),
					'enable'      => __( 'Enable multi-criteria ratings', 'verdix-pro' ),
					'label'       => __( 'Criterion', 'verdix-pro' ),
					'placeholder' => __( 'e.g., Features', 'verdix-pro' ),
					'rating'      => __( 'Rating', 'verdix-pro' ),
					'add'         => __( '+ Add Criterion', 'verdix-pro' ),
					'remove'      => __( 'Remove', 'verdix-pro' ),
					'help'        => __( 'Overall rating will be calculated as the average.', 'verdix-pro' ),
				),
			)
		) . ';',
		'before'
	);
}
add_action( 'enqueue_block_editor_assets', 'verdix_pro_enqueue_editor_assets' );

function verdix_pro_enqueue_meta_box_assets( $hook_suffix ) {
	if ( 'post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix ) {
		return;
	}
	wp_enqueue_script(
		'verdix-pro-meta-box',
		VERDIX_PRO_URL . 'assets/js/pro-meta-box.js',
		array(),
		VERDIX_PRO_VERSION,
		true
	);
}
add_action( 'admin_enqueue_scripts', 'verdix_pro_enqueue_meta_box_assets' );

function verdix_pro_enqueue_criteria_css() {
	if ( ! is_singular() ) {
		return;
	}
	$post_id = get_the_ID();
	$review  = verdix_get_review( $post_id );
	if ( ! $review || empty( $review['use_criteria'] ) || empty( $review['criteria'] ) ) {
		return;
	}
	wp_enqueue_style(
		'verdix-pro-criteria-frontend',
		VERDIX_PRO_URL . 'assets/css/criteria.css',
		array( 'verdix-frontend' ),
		VERDIX_PRO_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'verdix_pro_enqueue_criteria_css' );

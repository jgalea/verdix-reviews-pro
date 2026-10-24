<?php
defined( 'ABSPATH' ) || exit;

function verdix_pro_ai_available() {
	return function_exists( 'wp_ai_client_prompt' ) && function_exists( 'wp_supports_ai' ) && wp_supports_ai();
}

function verdix_pro_ai_connectors_url() {
	return admin_url( 'options-connectors.php' );
}

function verdix_pro_ai_unavailable_message() {
	if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
		return __( 'Draft with AI needs WordPress 7.0 or later, which includes the AI client.', 'verdix-pro' );
	}
	if ( ! wp_supports_ai() ) {
		return __( 'AI features are turned off on this site.', 'verdix-pro' );
	}
	return '';
}

function verdix_pro_ai_register_route() {
	register_rest_route(
		'verdix-pro/v1',
		'/draft',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'verdix_pro_ai_draft_callback',
			'permission_callback' => function ( WP_REST_Request $request ) {
				return current_user_can( 'edit_post', (int) $request['post_id'] );
			},
			'args'                => array(
				'post_id' => array(
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
				'title'   => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'content' => array(
					'type'              => 'string',
					'sanitize_callback' => 'wp_kses_post',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'verdix_pro_ai_register_route' );

function verdix_pro_ai_draft_callback( WP_REST_Request $request ) {
	$post = get_post( (int) $request['post_id'] );
	if ( ! $post ) {
		return new WP_Error( 'verdix_pro_ai_no_post', __( 'Post not found.', 'verdix-pro' ), array( 'status' => 404 ) );
	}

	if ( ! verdix_pro_ai_available() ) {
		return new WP_Error( 'verdix_pro_ai_unavailable', verdix_pro_ai_unavailable_message(), array( 'status' => 501 ) );
	}

	$title   = $request->has_param( 'title' ) ? (string) $request['title'] : $post->post_title;
	$content = $request->has_param( 'content' ) ? (string) $request['content'] : $post->post_content;

	$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( strip_shortcodes( excerpt_remove_blocks( $content ) ) ) ) );
	if ( mb_strlen( $text ) < 100 ) {
		return new WP_Error( 'verdix_pro_ai_short', __( 'Write more of the post first. There is not enough content to draft from.', 'verdix-pro' ), array( 'status' => 422 ) );
	}
	$text = mb_substr( $text, 0, 12000 );

	$schema = array(
		'type'                 => 'object',
		'properties'           => array(
			'summary' => array( 'type' => 'string' ),
			'pros'    => array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			),
			'cons'    => array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			),
		),
		'required'             => array( 'summary', 'pros', 'cons' ),
		'additionalProperties' => false,
	);

	$system = 'You draft the summary, pros and cons for a review box from a published review post. '
		. 'Use only claims that appear in the post text. Do not add facts, numbers, comparisons or features the post does not state. '
		. 'Write plain, factual sentences. No marketing words, no superlatives, no exclamation marks. '
		. 'Summary: two or three sentences. Pros and cons: up to five each, short fragments of under twelve words. '
		. 'If the post gives no real cons, return an empty cons array. Do not invent any.';

	$builder = wp_ai_client_prompt( "Post title: {$title}\n\nPost text:\n{$text}" )
		->using_system_instruction( $system )
		->using_temperature( 0.2 )
		->as_json_response( $schema );

	if ( ! $builder->is_supported_for_text_generation() ) {
		return new WP_Error(
			'verdix_pro_ai_no_provider',
			__( 'No AI provider is configured. Add one in Settings > Connectors, then try again.', 'verdix-pro' ),
			array(
				'status'         => 424,
				'connectors_url' => verdix_pro_ai_connectors_url(),
			)
		);
	}

	$result = $builder->generate_text();
	if ( is_wp_error( $result ) ) {
		return new WP_Error( 'verdix_pro_ai_failed', $result->get_error_message(), array( 'status' => 502 ) );
	}

	$data = json_decode( (string) $result, true );
	if ( ! is_array( $data ) ) {
		return new WP_Error( 'verdix_pro_ai_bad_output', __( 'The AI returned an unreadable draft. Try again.', 'verdix-pro' ), array( 'status' => 502 ) );
	}

	$clean_list = function ( $items ) {
		$out = array();
		foreach ( is_array( $items ) ? $items : array() as $item ) {
			$item = sanitize_text_field( (string) $item );
			if ( '' !== $item ) {
				$out[] = $item;
			}
		}
		return array_slice( $out, 0, 5 );
	};

	return rest_ensure_response(
		array(
			'summary' => isset( $data['summary'] ) ? sanitize_textarea_field( (string) $data['summary'] ) : '',
			'pros'    => $clean_list( isset( $data['pros'] ) ? $data['pros'] : array() ),
			'cons'    => $clean_list( isset( $data['cons'] ) ? $data['cons'] : array() ),
		)
	);
}

function verdix_pro_ai_script_config() {
	return array(
		'restUrl'     => esc_url_raw( rest_url( 'verdix-pro/v1/draft' ) ),
		'nonce'       => wp_create_nonce( 'wp_rest' ),
		'unavailable' => verdix_pro_ai_unavailable_message(),
		'i18n'        => array(
			'button'     => __( 'Draft with AI', 'verdix-pro' ),
			'working'    => __( 'Drafting...', 'verdix-pro' ),
			'done'       => __( 'Draft added. Edit it before publishing.', 'verdix-pro' ),
			'replace'    => __( 'This replaces the current summary, pros and cons. Continue?', 'verdix-pro' ),
			'failed'     => __( 'Drafting failed.', 'verdix-pro' ),
			'help'       => __( 'Drafts a summary, pros and cons from the post content. Review and edit the result.', 'verdix-pro' ),
			'connectors' => __( 'Open Connectors', 'verdix-pro' ),
		),
	);
}

function verdix_pro_ai_enqueue_block_editor() {
	wp_enqueue_script(
		'verdix-pro-ai-draft-editor',
		VERDIX_PRO_URL . 'assets/js/ai-draft-editor.js',
		array( 'wp-element', 'wp-components', 'wp-block-editor', 'wp-compose', 'wp-data', 'wp-hooks', 'wp-api-fetch' ),
		VERDIX_PRO_VERSION,
		true
	);
	wp_add_inline_script(
		'verdix-pro-ai-draft-editor',
		'window.verdixProAI = ' . wp_json_encode( verdix_pro_ai_script_config() ) . ';',
		'before'
	);
}
add_action( 'enqueue_block_editor_assets', 'verdix_pro_ai_enqueue_block_editor' );

function verdix_pro_ai_enqueue_classic( $hook_suffix ) {
	if ( 'post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix ) {
		return;
	}
	$screen = get_current_screen();
	if ( $screen && $screen->is_block_editor() ) {
		return;
	}
	wp_enqueue_script(
		'verdix-pro-ai-draft-classic',
		VERDIX_PRO_URL . 'assets/js/ai-draft-classic.js',
		array(),
		VERDIX_PRO_VERSION,
		true
	);
	wp_add_inline_script(
		'verdix-pro-ai-draft-classic',
		'window.verdixProAI = ' . wp_json_encode( verdix_pro_ai_script_config() ) . ';',
		'before'
	);
}
add_action( 'admin_enqueue_scripts', 'verdix_pro_ai_enqueue_classic' );

function verdix_pro_ai_meta_box_row() {
	?>
	<tr>
		<th scope="row"><label><?php esc_html_e( 'AI draft', 'verdix-pro' ); ?></label></th>
		<td>
			<button type="button" class="button" id="verdix-pro-ai-draft"><?php esc_html_e( 'Draft with AI', 'verdix-pro' ); ?></button>
			<span class="spinner" id="verdix-pro-ai-spinner" style="float:none;"></span>
			<p class="description"><?php esc_html_e( 'Drafts a summary, pros and cons from the post content. Review and edit the result.', 'verdix-pro' ); ?></p>
			<div id="verdix-pro-ai-notice" role="status" style="margin-top:6px;"></div>
		</td>
	</tr>
	<?php
}
add_action( 'verdix_meta_box_fields', 'verdix_pro_ai_meta_box_row', 20 );

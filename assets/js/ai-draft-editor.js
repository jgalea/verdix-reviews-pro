( function() {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var Button = wp.components.Button;
	var Notice = wp.components.Notice;
	var createHigherOrderComponent = wp.compose.createHigherOrderComponent;

	var CONFIG = window.verdixProAI || { i18n: {}, unavailable: '' };
	var T = CONFIG.i18n;

	function DraftPanel() {
		var state = useState( { busy: false, notice: null } );
		var ui = state[ 0 ];
		var setUi = state[ 1 ];

		function run() {
			var editor = wp.data.select( 'core/editor' );
			var meta = editor.getEditedPostAttribute( 'meta' ) || {};
			var review = {};
			try { review = JSON.parse( meta._verdix_review || '{}' ) || {}; } catch ( e ) { review = {}; }

			var hasExisting = review.summary || ( review.pros && review.pros.length ) || ( review.cons && review.cons.length );
			if ( hasExisting && ! window.confirm( T.replace ) ) {
				return;
			}

			setUi( { busy: true, notice: null } );
			wp.apiFetch( {
				url: CONFIG.restUrl,
				method: 'POST',
				headers: { 'X-WP-Nonce': CONFIG.nonce },
				data: {
					post_id: editor.getCurrentPostId(),
					title: editor.getEditedPostAttribute( 'title' ) || '',
					content: editor.getEditedPostContent()
				}
			} ).then( function( draft ) {
				review.summary = draft.summary;
				review.pros = draft.pros;
				review.cons = draft.cons;
				wp.data.dispatch( 'core/editor' ).editPost( {
					meta: Object.assign( {}, meta, { _verdix_review: JSON.stringify( review ) } )
				} );
				setUi( { busy: false, notice: { status: 'success', text: T.done } } );
			} ).catch( function( err ) {
				var url = err && err.data && err.data.connectors_url;
				setUi( { busy: false, notice: { status: 'error', text: ( err && err.message ) || T.failed, url: url } } );
			} );
		}

		var children = [];
		if ( CONFIG.unavailable ) {
			children.push( el( 'p', { key: 'na' }, CONFIG.unavailable ) );
		} else {
			children.push( el( 'p', { key: 'help' }, T.help ) );
			children.push( el( Button, {
				key: 'btn',
				variant: 'secondary',
				isBusy: ui.busy,
				disabled: ui.busy,
				onClick: run
			}, ui.busy ? T.working : T.button ) );
		}
		if ( ui.notice ) {
			children.push( el( Notice, { key: 'notice', status: ui.notice.status, isDismissible: false },
				ui.notice.text,
				ui.notice.url ? el( Fragment, null, ' ', el( 'a', { href: ui.notice.url }, T.connectors ) ) : null
			) );
		}
		return el( PanelBody, { title: T.button, initialOpen: true }, children );
	}

	wp.hooks.addFilter( 'editor.BlockEdit', 'verdix-pro/ai-draft', createHigherOrderComponent( function( BlockEdit ) {
		return function( props ) {
			if ( props.name !== 'verdix/review-box' ) {
				return el( BlockEdit, props );
			}
			return el( Fragment, null,
				el( BlockEdit, props ),
				el( InspectorControls, null, el( DraftPanel ) )
			);
		};
	}, 'withVerdixAiDraft' ) );
} )();

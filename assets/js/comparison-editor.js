( function() {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var ServerSideRender = wp.serverSideRender || wp.components.ServerSideRender;
	var Placeholder = wp.components.Placeholder;

	function Edit( props ) {
		var blockProps = useBlockProps();
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;

		var hasIds = attributes.ids && attributes.ids.trim().length > 0;

		var inspector = el( InspectorControls, {},
			el( PanelBody, { title: __( 'Comparison Settings', 'verdix-pro' ) },
				el( TextControl, {
					label: __( 'Post IDs', 'verdix-pro' ),
					value: attributes.ids,
					onChange: function( val ) { setAttributes( { ids: val } ); },
					help: __( 'Comma-separated post IDs to compare.', 'verdix-pro' )
				} ),
				el( TextControl, {
					label: __( 'Fields', 'verdix-pro' ),
					value: attributes.fields,
					onChange: function( val ) { setAttributes( { fields: val } ); },
					help: __( 'Comma-separated: rating, summary, pros, cons, cta, price', 'verdix-pro' )
				} ),
				el( ToggleControl, {
					label: __( 'Highlight Best Pick', 'verdix-pro' ),
					checked: attributes.highlight,
					onChange: function( val ) { setAttributes( { highlight: val } ); }
				} )
			)
		);

		var content;
		if ( hasIds ) {
			content = el( 'div', blockProps,
				el( ServerSideRender, {
					block: 'verdix-pro/compare',
					attributes: attributes
				} )
			);
		} else {
			content = el( 'div', blockProps,
				el( Placeholder, {
					icon: 'editor-table',
					label: __( 'Verdix Compare', 'verdix-pro' ),
					instructions: __( 'Enter post IDs in the block settings to create a comparison table.', 'verdix-pro' )
				} )
			);
		}

		return el( wp.element.Fragment, {}, inspector, content );
	}

	wp.blocks.registerBlockType( 'verdix-pro/compare', {
		title: __( 'Verdix Compare', 'verdix-pro' ),
		icon: 'editor-table',
		category: 'widgets',
		keywords: [ 'compare', 'table', 'review', 'verdix' ],
		edit: Edit,
		save: function() { return null; }
	} );
} )();

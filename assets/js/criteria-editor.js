( function() {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var ToggleControl = wp.components.ToggleControl;
	var TextControl = wp.components.TextControl;
	var Button = wp.components.Button;
	var createHigherOrderComponent = wp.compose.createHigherOrderComponent;

	var CONFIG = window.verdixProCriteria || { i18n: {}, ratingTypes: {} };
	var T = CONFIG.i18n;

	function readReview( meta ) {
		try {
			return JSON.parse( ( meta && meta._verdix_review ) || '{}' ) || {};
		} catch ( e ) {
			return {};
		}
	}

	function clampRating( value ) {
		var n = parseFloat( value );
		if ( isNaN( n ) ) return 0;
		return Math.max( 0, Math.min( 5, Math.round( n * 2 ) / 2 ) );
	}

	function averageRating( review ) {
		var rows = ( review.criteria || [] ).filter( function( c ) { return c.label; } );
		if ( ! rows.length ) return review.rating;
		var sum = rows.reduce( function( total, c ) { return total + clampRating( c.rating ); }, 0 );
		var type = CONFIG.ratingTypes[ review.rating_type || 'star' ] || { max: 5, step: 0.5 };
		var step = type.step || 0.5;
		return Math.round( ( sum / rows.length ) / 5 * type.max / step ) * step;
	}

	function CriteriaPanel() {
		var meta = wp.data.useSelect( function( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' );
		}, [] ) || {};
		var review = readReview( meta );
		var criteria = review.criteria || [];

		function save( changes ) {
			var updated = Object.assign( {}, review, changes );
			if ( updated.use_criteria ) {
				updated.rating = averageRating( updated );
			}
			wp.data.dispatch( 'core/editor' ).editPost( {
				meta: Object.assign( {}, meta, { _verdix_review: JSON.stringify( updated ) } )
			} );
		}

		function setRow( index, changes ) {
			save( {
				criteria: criteria.map( function( c, i ) {
					return i === index ? Object.assign( {}, c, changes ) : c;
				} )
			} );
		}

		var children = [
			el( ToggleControl, {
				key: 'toggle',
				label: T.enable,
				checked: !! review.use_criteria,
				onChange: function( on ) { save( { use_criteria: on } ); }
			} )
		];

		if ( review.use_criteria ) {
			criteria.forEach( function( row, index ) {
				children.push( el( 'div', { key: 'row-' + index, className: 'verdix-criteria-editor-row' },
					el( TextControl, {
						label: T.label,
						hideLabelFromVision: true,
						placeholder: T.placeholder,
						value: row.label || '',
						onChange: function( v ) { setRow( index, { label: v } ); }
					} ),
					el( TextControl, {
						label: T.rating,
						hideLabelFromVision: true,
						type: 'number',
						min: 0,
						max: 5,
						step: 0.5,
						value: row.rating === undefined ? 0 : row.rating,
						onChange: function( v ) { setRow( index, { rating: clampRating( v ) } ); }
					} ),
					el( Button, {
						isDestructive: true,
						variant: 'tertiary',
						onClick: function() {
							save( { criteria: criteria.filter( function( c, i ) { return i !== index; } ) } );
						}
					}, T.remove )
				) );
			} );
			children.push( el( Button, {
				key: 'add',
				variant: 'secondary',
				onClick: function() { save( { criteria: criteria.concat( [ { label: '', rating: 0 } ] ) } ); }
			}, T.add ) );
			children.push( el( 'p', { key: 'help', className: 'description' }, T.help ) );
		}

		return el( PanelBody, { title: T.title, initialOpen: true }, children );
	}

	wp.hooks.addFilter( 'editor.BlockEdit', 'verdix-pro/criteria', createHigherOrderComponent( function( BlockEdit ) {
		return function( props ) {
			if ( props.name !== 'verdix/review-box' ) {
				return el( BlockEdit, props );
			}
			return el( Fragment, null,
				el( BlockEdit, props ),
				el( InspectorControls, null, el( CriteriaPanel ) )
			);
		};
	}, 'withVerdixCriteria' ) );
} )();

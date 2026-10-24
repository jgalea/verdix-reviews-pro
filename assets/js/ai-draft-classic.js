( function() {
	'use strict';

	var CONFIG = window.verdixProAI || { i18n: {}, unavailable: '' };
	var T = CONFIG.i18n;

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) { fn(); return; }
		document.addEventListener( 'DOMContentLoaded', fn );
	}

	function postContent() {
		if ( window.tinyMCE && tinyMCE.get( 'content' ) && ! tinyMCE.get( 'content' ).isHidden() ) {
			return tinyMCE.get( 'content' ).getContent();
		}
		var field = document.getElementById( 'content' );
		return field ? field.value : '';
	}

	function showNotice( box, status, text, url ) {
		box.textContent = '';
		var p = document.createElement( 'p' );
		p.className = 'notice inline notice-' + status;
		p.style.padding = '6px 10px';
		p.appendChild( document.createTextNode( text ) );
		if ( url ) {
			p.appendChild( document.createTextNode( ' ' ) );
			var a = document.createElement( 'a' );
			a.href = url;
			a.textContent = T.connectors;
			p.appendChild( a );
		}
		box.appendChild( p );
	}

	function fillList( field, items ) {
		var container = document.querySelector( '.verdix-repeater[data-field="' + field + '"]' );
		if ( ! container ) { return; }
		var inputs = container.querySelectorAll( 'input[type="text"]' );
		items = items.length ? items : [ '' ];
		items.forEach( function( value, i ) {
			if ( ! inputs[ i ] ) {
				var add = document.querySelector( '.verdix-repeater__add[data-target="' + field + '"]' );
				if ( add ) { add.click(); }
				inputs = container.querySelectorAll( 'input[type="text"]' );
			}
			if ( inputs[ i ] ) { inputs[ i ].value = value; }
		} );
		for ( var j = items.length; j < inputs.length; j++ ) {
			inputs[ j ].value = '';
		}
	}

	ready( function() {
		var button = document.getElementById( 'verdix-pro-ai-draft' );
		if ( ! button ) { return; }
		var spinner = document.getElementById( 'verdix-pro-ai-spinner' );
		var notice = document.getElementById( 'verdix-pro-ai-notice' );

		if ( CONFIG.unavailable ) {
			button.disabled = true;
			showNotice( notice, 'warning', CONFIG.unavailable );
			return;
		}

		button.addEventListener( 'click', function() {
			var summary = document.getElementById( 'verdix_review_summary' );
			var existing = ( summary && summary.value ) ||
				Array.prototype.some.call( document.querySelectorAll( '.verdix-repeater input[type="text"]' ), function( i ) { return i.value; } );
			if ( existing && ! window.confirm( T.replace ) ) { return; }

			var postId = document.getElementById( 'post_ID' );
			var title = document.getElementById( 'title' );
			button.disabled = true;
			spinner.classList.add( 'is-active' );
			notice.textContent = '';

			fetch( CONFIG.restUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': CONFIG.nonce },
				body: JSON.stringify( {
					post_id: postId ? parseInt( postId.value, 10 ) : 0,
					title: title ? title.value : '',
					content: postContent()
				} )
			} ).then( function( res ) {
				return res.json().then( function( body ) { return { ok: res.ok, body: body }; } );
			} ).then( function( r ) {
				if ( ! r.ok ) {
					showNotice( notice, 'error', r.body.message || T.failed, r.body.data && r.body.data.connectors_url );
					return;
				}
				if ( summary ) { summary.value = r.body.summary; }
				fillList( 'pros', r.body.pros );
				fillList( 'cons', r.body.cons );
				showNotice( notice, 'success', T.done );
			} ).catch( function() {
				showNotice( notice, 'error', T.failed );
			} ).then( function() {
				button.disabled = false;
				spinner.classList.remove( 'is-active' );
			} );
		} );
	} );
} )();

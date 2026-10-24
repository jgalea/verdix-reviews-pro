( function() {
	'use strict';

	var containers = document.querySelectorAll( '.verdix-comment-rating__stars' );

	containers.forEach( function( container ) {
		var stars = container.querySelectorAll( '.verdix-comment-star' );
		var input = container.querySelector( 'input[type="hidden"]' );
		var currentValue = 0;

		function setStars( value, className ) {
			stars.forEach( function( star ) {
				var starValue = parseFloat( star.getAttribute( 'data-value' ) );
				if ( starValue <= value ) {
					star.classList.add( className );
				} else {
					star.classList.remove( className );
				}
			} );
		}

		stars.forEach( function( star ) {
			star.addEventListener( 'mouseenter', function() {
				var value = parseFloat( this.getAttribute( 'data-value' ) );
				setStars( value, 'hover' );
			} );

			star.addEventListener( 'mouseleave', function() {
				stars.forEach( function( s ) { s.classList.remove( 'hover' ); } );
			} );

			star.addEventListener( 'click', function( event ) {
				var value = parseFloat( this.getAttribute( 'data-value' ) );

				// Click on left half = half star, right half = full star.
				var rect = this.getBoundingClientRect();
				var x = event.clientX - rect.left;
				if ( x < rect.width / 2 && value > 0.5 ) {
					value = value - 0.5;
				}

				currentValue = value;
				input.value = value;
				setStars( Math.ceil( value ), 'active' );
			} );

			star.addEventListener( 'keydown', function( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					this.click();
				}
			} );
		} );
	} );
} )();

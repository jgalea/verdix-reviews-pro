( function() {
	'use strict';

	var addBtn = document.getElementById( 'verdix-review-add-criterion' );
	if ( ! addBtn ) return;

	var list = document.getElementById( 'verdix-review-criteria-list' );

	addBtn.addEventListener( 'click', function() {
		var row = document.createElement( 'div' );
		row.className = 'verdix-criteria-row';
		row.style.cssText = 'display: flex; gap: 8px; margin-bottom: 6px; align-items: center;';

		var label = document.createElement( 'input' );
		label.type = 'text';
		label.name = 'verdix_review_criteria_label[]';
		label.placeholder = 'e.g., Features';
		label.className = 'regular-text';
		label.style.flex = '1';

		var rating = document.createElement( 'input' );
		rating.type = 'number';
		rating.name = 'verdix_review_criteria_rating[]';
		rating.value = '0';
		rating.min = '0';
		rating.max = '5';
		rating.step = '0.5';
		rating.style.width = '80px';

		var remove = document.createElement( 'button' );
		remove.type = 'button';
		remove.className = 'button verdix-criteria-remove';
		remove.textContent = 'Remove';
		remove.addEventListener( 'click', function() { row.remove(); } );

		row.appendChild( label );
		row.appendChild( rating );
		row.appendChild( remove );
		list.appendChild( row );
	} );
} )();

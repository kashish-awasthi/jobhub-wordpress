( function () {
	'use strict';

	// Mobile navigation toggle.
	const toggle = document.querySelector( '.nav-toggle' );
	const nav = document.getElementById( 'site-nav' );
	if ( toggle && nav ) {
		toggle.addEventListener( 'click', () => {
			const open = toggle.getAttribute( 'aria-expanded' ) === 'true';
			toggle.setAttribute( 'aria-expanded', String( ! open ) );
			nav.classList.toggle( 'is-open', ! open );
		} );
		document.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Escape' && nav.classList.contains( 'is-open' ) ) {
				toggle.setAttribute( 'aria-expanded', 'false' );
				nav.classList.remove( 'is-open' );
				toggle.focus();
			}
		} );
	}

	// Client-side CV size check before upload (server validates too).
	document.querySelectorAll( 'input[type="file"][data-max-mb]' ).forEach( ( input ) => {
		input.addEventListener( 'change', () => {
			const max = parseInt( input.dataset.maxMb, 10 ) * 1024 * 1024;
			const file = input.files && input.files[ 0 ];
			input.setCustomValidity( file && file.size > max ? 'File is larger than ' + input.dataset.maxMb + ' MB.' : '' );
			input.reportValidity();
		} );
	} );
} )();

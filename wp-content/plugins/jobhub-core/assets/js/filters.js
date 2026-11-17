/**
 * Live job filtering (progressive enhancement).
 * Without JS the filter form submits normally and the server filters results.
 */
( function () {
	'use strict';

	const form = document.querySelector( '[data-jobhub-filters]' );
	const results = document.querySelector( '[data-jobhub-results]' );
	if ( ! form || ! results || ! window.JobHubFilters || ! window.fetch ) {
		return;
	}

	const countEl = document.querySelector( '[data-jobhub-count]' );
	const moreBtn = document.querySelector( '[data-jobhub-more]' );
	const pagination = document.querySelector( '[data-jobhub-pagination]' );
	const reset = form.querySelector( '[data-jobhub-reset]' );

	let page = 1;
	let maxPages = parseInt( results.dataset.maxPages || '1', 10 );
	let controller = null;
	let timer = null;

	// JS takes over paging: hide numbered links, use "Load more".
	if ( pagination ) {
		pagination.hidden = true;
	}
	const toggleMore = () => {
		if ( moreBtn ) {
			moreBtn.hidden = page >= maxPages;
		}
	};
	toggleMore();

	const syncUrl = () => {
		const params = new URLSearchParams();
		new FormData( form ).forEach( ( value, key ) => {
			if ( value ) {
				params.set( key, value );
			}
		} );
		const qs = params.toString();
		window.history.replaceState( null, '', form.action + ( qs ? '?' + qs : '' ) );
	};

	const load = async ( append = false ) => {
		if ( controller ) {
			controller.abort();
		}
		controller = new AbortController();

		const data = new FormData( form );
		data.set( 'action', 'jobhub_filter_jobs' );
		data.set( 'nonce', window.JobHubFilters.nonce );
		data.set( 'page', String( page ) );

		results.setAttribute( 'aria-busy', 'true' );
		results.classList.add( 'is-loading' );

		try {
			const res = await fetch( window.JobHubFilters.ajaxUrl, {
				method: 'POST',
				body: data,
				credentials: 'same-origin',
				signal: controller.signal,
			} );
			const json = await res.json();
			if ( ! json.success ) {
				throw new Error( 'Request failed' );
			}

			if ( append ) {
				results.insertAdjacentHTML( 'beforeend', json.data.html );
			} else {
				results.innerHTML = json.data.html;
			}
			maxPages = json.data.maxPages;
			if ( countEl ) {
				const n = json.data.found;
				countEl.textContent = n + ( n === 1 ? ' job' : ' jobs' );
			}
			toggleMore();
			if ( ! append ) {
				syncUrl();
			}
		} catch ( err ) {
			if ( err.name !== 'AbortError' ) {
				// Fall back to a normal page load if AJAX fails.
				form.submit();
			}
		} finally {
			results.removeAttribute( 'aria-busy' );
			results.classList.remove( 'is-loading' );
		}
	};

	const refresh = () => {
		page = 1;
		load( false );
	};

	form.addEventListener( 'submit', ( e ) => {
		e.preventDefault();
		refresh();
	} );

	form.addEventListener( 'change', ( e ) => {
		if ( e.target.matches( 'select, input[type="checkbox"]' ) ) {
			refresh();
		}
	} );

	form.addEventListener( 'input', ( e ) => {
		if ( e.target.matches( 'input[type="search"]' ) ) {
			clearTimeout( timer );
			timer = setTimeout( refresh, 350 );
		}
	} );

	if ( reset ) {
		reset.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			form.reset();
			form.querySelectorAll( 'input[type="search"]' ).forEach( ( i ) => ( i.value = '' ) );
			form.querySelectorAll( 'select' ).forEach( ( s ) => ( s.selectedIndex = 0 ) );
			form.querySelectorAll( 'input[type="checkbox"]' ).forEach( ( c ) => ( c.checked = false ) );
			refresh();
		} );
	}

	if ( moreBtn ) {
		moreBtn.addEventListener( 'click', () => {
			page += 1;
			load( true );
		} );
	}
} )();

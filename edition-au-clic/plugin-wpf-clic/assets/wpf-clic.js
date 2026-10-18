/* WPF Édition au clic (lab) : édition en place des éléments [data-wpf-edit]. */
( function () {
	'use strict';
	const cfg = window.wpfClic;
	const fields = Array.from( document.querySelectorAll( '[data-wpf-edit][data-wpf-id]' ) );
	if ( ! cfg || ! fields.length ) {
		return;
	}
	const t = cfg.i18n;
	let base = cfg.base;
	let active = false;
	let current = null;

	const el = ( tag, attrs = {}, text = '' ) => {
		const node = document.createElement( tag );
		Object.entries( attrs ).forEach( ( [ k, v ] ) => node.setAttribute( k, v ) );
		if ( text ) {
			node.textContent = text;
		}
		return node;
	};

	const toggle = el( 'button', { type: 'button', class: 'wpf-clic-toggle', 'aria-pressed': 'false' }, t.toggleOn );
	const bar = el( 'div', { class: 'wpf-clic-bar', hidden: '' } );
	const toast = el( 'div', { class: 'wpf-clic-toast', role: 'status', 'aria-live': 'polite', hidden: '' } );
	document.body.append( toggle, bar, toast );

	const notify = ( message, isError ) => {
		toast.textContent = message;
		toast.classList.toggle( 'is-error', !! isError );
		toast.hidden = false;
		clearTimeout( notify.timer );
		notify.timer = setTimeout( () => ( toast.hidden = true ), 3500 );
	};

	const send = async ( field, payload ) => {
		const res = await fetch( cfg.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( Object.assign( { field, base }, payload ) ),
		} );
		const data = await res.json().catch( () => ( {} ) );
		if ( 409 === res.status ) {
			throw new Error( t.conflict );
		}
		if ( ! res.ok ) {
			throw new Error( data.message || t.error );
		}
		base = data.base;
		return data;
	};

	const placeBar = ( target ) => {
		const r = target.getBoundingClientRect();
		bar.style.top = `${ window.scrollY + r.bottom + 8 }px`;
		bar.style.left = `${ window.scrollX + Math.max( 8, r.left ) }px`;
		bar.hidden = false;
	};

	const button = ( label, onClick, cls = '' ) => {
		const b = el( 'button', { type: 'button', class: cls }, label );
		b.addEventListener( 'mousedown', ( e ) => e.preventDefault() );
		b.addEventListener( 'click', onClick );
		return b;
	};

	const close = ( restore ) => {
		if ( ! current ) {
			return;
		}
		if ( restore ) {
			current.node.innerHTML = current.before;
		}
		current.node.removeAttribute( 'contenteditable' );
		current.node.classList.remove( 'wpf-clic-editing' );
		current = null;
		bar.hidden = true;
		bar.replaceChildren();
	};

	const commit = async () => {
		if ( ! current ) {
			return;
		}
		const { node, type } = current;
		const id = node.dataset.wpfId;
		try {
			let data;
			if ( 'text' === type ) {
				data = await send( id, { value: node.textContent.trim() } );
			} else if ( 'rich' === type ) {
				data = await send( id, { value: node.innerHTML } );
			} else if ( 'link' === type ) {
				data = await send( id, { value: current.inputs.text.value, href: current.inputs.url.value } );
				node.setAttribute( 'href', data.href );
			}
			node.innerHTML = data.html;
			current.before = data.html;
			close( false );
			notify( t.saved );
		} catch ( err ) {
			notify( err.message, true );
		}
	};

	const editText = ( node, type ) => {
		current = { node, type, before: node.innerHTML };
		node.classList.add( 'wpf-clic-editing' );
		node.setAttribute( 'contenteditable', 'text' === type ? 'plaintext-only' : 'true' );
		node.focus();
		const tools = [];
		if ( 'rich' === type ) {
			tools.push(
				button( t.bold, () => document.execCommand( 'bold' ) ),
				button( t.italic, () => document.execCommand( 'italic' ) ),
				button( t.link, () => {
					const url = window.prompt( t.linkUrl, 'https://' );
					if ( url ) {
						document.execCommand( 'createLink', false, url );
					}
				} )
			);
		}
		tools.push( button( t.save, commit, 'is-primary' ), button( t.cancel, () => close( true ) ) );
		bar.replaceChildren( ...tools );
		placeBar( node );
	};

	const editLink = ( node ) => {
		current = { node, type: 'link', before: node.innerHTML, inputs: {} };
		node.classList.add( 'wpf-clic-editing' );
		const text = el( 'input', { type: 'text', 'aria-label': t.linkText } );
		text.value = node.textContent.trim();
		const url = el( 'input', { type: 'text', 'aria-label': t.linkUrl } );
		url.value = node.getAttribute( 'href' ) || '';
		current.inputs = { text, url };
		bar.replaceChildren(
			el( 'label', {}, t.linkText ), text,
			el( 'label', {}, t.linkUrl ), url,
			button( t.save, commit, 'is-primary' ), button( t.cancel, () => close( true ) )
		);
		placeBar( node );
		text.focus();
	};

	const editImage = ( node ) => {
		if ( ! window.wp || ! window.wp.media ) {
			notify( t.error, true );
			return;
		}
		const frame = window.wp.media( { title: t.image, library: { type: 'image' }, multiple: false } );
		frame.on( 'open', () => frame.content.mode( 'browse' ) );
		frame.on( 'select', async () => {
			const att = frame.state().get( 'selection' ).first().toJSON();
			try {
				const data = await send( node.dataset.wpfId, { attachment_id: att.id, alt: att.alt || node.getAttribute( 'alt' ) || '' } );
				[ 'src', 'srcset', 'sizes', 'alt', 'width', 'height' ].forEach( ( name ) => {
					if ( data[ name ] ) {
						node.setAttribute( name, data[ name ] );
					} else if ( 'srcset' === name || 'sizes' === name ) {
						node.removeAttribute( name );
					}
				} );
				notify( t.saved );
			} catch ( err ) {
				notify( err.message, true );
			}
		} );
		frame.open();
	};

	document.addEventListener( 'click', ( e ) => {
		if ( ! active ) {
			return;
		}
		const node = e.target.closest( '[data-wpf-edit][data-wpf-id]' );
		if ( ! node || ( current && current.node === node ) || bar.contains( e.target ) ) {
			return;
		}
		e.preventDefault();
		close( true );
		const type = node.dataset.wpfEdit;
		if ( 'image' === type ) {
			editImage( node );
		} else if ( 'link' === type ) {
			editLink( node );
		} else {
			editText( node, type );
		}
	}, true );

	document.addEventListener( 'keydown', ( e ) => {
		if ( ! current ) {
			return;
		}
		if ( 'Escape' === e.key ) {
			close( true );
		} else if ( 'Enter' === e.key && 'text' === current.type ) {
			e.preventDefault();
			commit();
		}
	} );

	// Coller du texte brut seulement : rien ne vient de Word ou d'une autre page.
	document.addEventListener( 'paste', ( e ) => {
		if ( current && current.node.contains( e.target ) && 'rich' === current.type ) {
			e.preventDefault();
			document.execCommand( 'insertText', false, e.clipboardData.getData( 'text/plain' ) );
		}
	} );

	toggle.addEventListener( 'click', () => {
		active = ! active;
		close( true );
		document.body.classList.toggle( 'wpf-clic-on', active );
		toggle.setAttribute( 'aria-pressed', String( active ) );
		toggle.textContent = active ? t.toggleOff : t.toggleOn;
	} );
}() );

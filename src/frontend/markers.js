/**
 * Page markers — Figma-style avatar pins + hover preview; thread on click.
 */

import { getAvatarColor, getInitials, formatRelativeTime } from './lib/avatar';
import { elementShellRect, resolveTaskTarget } from './lib/dom';
import { getScrollTargets } from './lib/context';

function plainText( html ) {
	const node = document.createElement( 'div' );
	node.innerHTML = html;
	return ( node.textContent || html || '' ).trim();
}

function buildHoverPreview( task ) {
	const preview = document.createElement( 'div' );
	preview.className = 'forwp-todo-note-preview';
	preview.setAttribute( 'role', 'tooltip' );

	const head = document.createElement( 'div' );
	head.className = 'forwp-todo-note-preview__head';

	const avatar = document.createElement( 'span' );
	avatar.className = 'forwp-todo-note-preview__avatar';
	avatar.style.backgroundColor = getAvatarColor( task.author );
	avatar.textContent = getInitials( task.author_name );

	const meta = document.createElement( 'div' );
	meta.className = 'forwp-todo-note-preview__meta';
	meta.innerHTML = `<strong>${ task.author_name || 'User' }</strong><span>${ formatRelativeTime( task.created ) }</span>`;

	head.append( avatar, meta );

	const body = document.createElement( 'p' );
	body.className = 'forwp-todo-note-preview__text';
	const text = plainText( task.content );
	body.textContent = text.length > 140 ? `${ text.slice( 0, 140 ) }…` : text;

	preview.append( head, body );
	return preview;
}

/**
 * @param {object} options
 * @param {Function} options.getTasks Returns current task list.
 * @param {object} options.i18n
 * @param {Function} options.onMarkerClick (taskId, anchorEl) => void
 * @return {{ refresh: Function, clear: Function, focusTask: Function }}
 */
export function createMarkers( { getTasks, i18n, onMarkerClick } ) {
	let bound = false;
	/** @type {Array<object>} */
	const entries = [];
	const scrollTargets = [];

	function clear() {
		entries.forEach( ( entry ) => {
			entry.observer?.disconnect();
			entry.unbindPreview?.();
			entry.pin.remove();
			entry.target.removeAttribute( 'data-forwp-todo-has-note' );
			entry.target.classList.remove( 'forwp-todo-has-note-host' );
		} );
		entries.length = 0;
	}

	function bindPreview( target, pin ) {
		const previewOn = () => pin.classList.add( 'is-preview' );
		const previewOff = () => pin.classList.remove( 'is-preview' );

		target.addEventListener( 'mouseenter', previewOn );
		target.addEventListener( 'mouseleave', previewOff );
		pin.addEventListener( 'mouseenter', previewOn );
		pin.addEventListener( 'mouseleave', previewOff );

		return () => {
			target.removeEventListener( 'mouseenter', previewOn );
			target.removeEventListener( 'mouseleave', previewOff );
			pin.removeEventListener( 'mouseenter', previewOn );
			pin.removeEventListener( 'mouseleave', previewOff );
		};
	}

	function createAvatarPin( task, index ) {
		const marker = document.createElement( 'button' );
		marker.type = 'button';
		marker.className = 'forwp-todo-avatar-pin';
		marker.dataset.taskId = String( task.id );
		marker.style.setProperty( '--avatar-color', getAvatarColor( task.author ) );
		marker.style.setProperty( '--avatar-offset', String( index * 10 ) );
		marker.textContent = getInitials( task.author_name );
		marker.setAttribute(
			'aria-label',
			plainText( task.content ) || i18n?.checklist || 'Note'
		);

		const preview = buildHoverPreview( task );
		marker.append( preview );

		marker.addEventListener( 'mouseenter', () => {
			marker.classList.add( 'is-hovered' );
		} );
		marker.addEventListener( 'mouseleave', () => {
			marker.classList.remove( 'is-hovered' );
		} );

		return marker;
	}

	function placeEntry( target, tasks ) {
		tasks.forEach( ( task ) => {
			if ( task.anchor && ! target.getAttribute( 'data-forwp-todo-anchor' ) ) {
				target.setAttribute( 'data-forwp-todo-anchor', task.anchor );
			}
		} );

		target.setAttribute( 'data-forwp-todo-has-note', '1' );
		target.classList.add( 'forwp-todo-has-note-host' );

		const pin = document.createElement( 'div' );
		pin.className = 'forwp-todo-note-pin';
		pin.dataset.taskIds = tasks.map( ( task ) => task.id ).join( ',' );

		const highlight = document.createElement( 'div' );
		highlight.className = 'forwp-todo-note-highlight';
		highlight.setAttribute( 'aria-hidden', 'true' );

		const markersWrap = document.createElement( 'div' );
		markersWrap.className = 'forwp-todo-note-markers';

		tasks.forEach( ( task, index ) => {
			markersWrap.append( createAvatarPin( task, index ) );
		} );

		pin.append( highlight, markersWrap );
		document.body.append( pin );

		const unbindPreview = bindPreview( target, pin );

		const reposition = () => {
			const rect = elementShellRect( target );
			pin.style.top = `${ rect.top }px`;
			pin.style.left = `${ rect.left }px`;
			pin.style.width = `${ rect.width }px`;
			pin.style.height = `${ rect.height }px`;
		};

		let observer = null;
		if ( typeof ResizeObserver !== 'undefined' ) {
			observer = new ResizeObserver( reposition );
			observer.observe( target );
		}

		reposition();
		entries.push( {
			target,
			pin,
			tasks,
			reposition,
			observer,
			unbindPreview,
		} );
	}

	function refresh() {
		clear();

		/** @type {Map<Element, object[]>} */
		const groups = new Map();

		getTasks().forEach( ( task ) => {
			const hasBlockIndex =
				task.block_index === 0 ||
				( typeof task.block_index === 'number' && task.block_index > 0 );
			const hasTarget =
				task.anchor || task.selector || task.block_name || hasBlockIndex;

			if ( ! hasTarget ) {
				return;
			}

			const target = resolveTaskTarget( task );
			if ( ! target ) {
				return;
			}

			const bucket = groups.get( target ) || [];
			bucket.push( task );
			groups.set( target, bucket );
		} );

		groups.forEach( ( tasks, target ) => {
			placeEntry( target, tasks );
		} );
	}

	function focusTask( taskId ) {
		entries.forEach( ( entry ) => {
			const focused = entry.tasks.some( ( task ) => task.id === taskId );
			entry.pin.classList.toggle( 'is-focused', focused );
			entry.pin.querySelectorAll( '.forwp-todo-avatar-pin' ).forEach( ( marker ) => {
				marker.classList.toggle(
					'is-focused',
					parseInt( marker.dataset.taskId, 10 ) === taskId
				);
			} );
			if ( focused ) {
				entry.pin.classList.add( 'is-preview' );
			}
		} );
	}

	function onDocumentClick( event ) {
		const marker = event.target.closest( '.forwp-todo-avatar-pin' );
		if ( ! marker ) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();

		const taskId = parseInt( marker.dataset.taskId, 10 );
		if ( taskId ) {
			onMarkerClick( taskId, marker );
		}
	}

	function repositionAll() {
		entries.forEach( ( entry ) => entry.reposition() );
	}

	function bind() {
		if ( bound ) {
			return;
		}

		document.addEventListener( 'click', onDocumentClick );
		getScrollTargets().forEach( ( target ) => {
			target.addEventListener( 'scroll', repositionAll, true );
			scrollTargets.push( target );
		} );
		window.addEventListener( 'resize', repositionAll, true );
		scrollTargets.push( window );
		bound = true;
	}

	bind();

	return { refresh, clear, focusTask };
}

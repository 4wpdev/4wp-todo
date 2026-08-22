/**
 * DOM utilities for page notes overlay.
 */

import {
	elementShellRect,
	getContentRoot as getContextContentRoot,
	getPickRoot,
	getTargetDocument,
	isEditorContext,
	pointerContextFromEvent,
} from './context';

const OVERLAY_SELECTOR =
	'#forwp-todo-frontend-root, .forwp-todo-stack, .forwp-todo-pin-highlight, .forwp-todo-note-highlight, .forwp-todo-note-pin, .forwp-todo-note-tooltip, .forwp-todo-pin-toolbar, .forwp-todo-composer, .forwp-todo-panel, .forwp-todo-section-marker';

const UNPICKABLE_SELECTOR =
	'#wpadminbar, #forwp-todo-frontend-root, .forwp-todo-stack, .forwp-todo-pin-highlight, .forwp-todo-note-highlight, .forwp-todo-note-pin, .forwp-todo-note-tooltip, .forwp-todo-pin-toolbar, .forwp-todo-composer, .forwp-todo-panel, .forwp-todo-section-marker, .block-list-appender, .block-editor-default-block-appender, .block-editor-block-toolbar, .components-popover, .interface-interface-skeleton__footer';

export function detectViewport() {
	return window.matchMedia( '(max-width: 782px)' ).matches ? 'mobile' : 'desktop';
}

export function escapeHtml( text ) {
	const div = document.createElement( 'div' );
	div.textContent = text;
	return div.innerHTML;
}

export function getContentRoot() {
	return getContextContentRoot();
}

export function isOverlayElement( element ) {
	return Boolean( element?.closest( OVERLAY_SELECTOR ) );
}

export function isPickableElement( element, pickRoot ) {
	if ( ! element || ! pickRoot?.contains( element ) ) {
		return false;
	}

	if ( isOverlayElement( element ) ) {
		return false;
	}

	if ( element.closest( UNPICKABLE_SELECTOR ) ) {
		return false;
	}

	const tag = element.tagName;
	if (
		[ 'HTML', 'BODY', 'HEAD', 'SCRIPT', 'STYLE', 'NOSCRIPT', 'SVG', 'IFRAME' ].includes(
			tag
		)
	) {
		return false;
	}

	return true;
}

function blockClassFromName( blockName ) {
	if ( ! blockName ) {
		return '';
	}

	const slug = blockName.replace( /^core\//, '' ).replace( /\//g, '-' );
	return `wp-block-${ slug }`;
}

export function resolveBlockTarget( task, searchRoot = null ) {
	if ( ! task.block_name && ( task.block_index === null || task.block_index === undefined ) ) {
		return null;
	}

	const root = searchRoot || getTargetDocument().body;
	const className = blockClassFromName( task.block_name );
	if ( ! className ) {
		return null;
	}

	const blocks = root.querySelectorAll( `.${ className }` );
	const index = Number( task.block_index );
	if ( ! Number.isFinite( index ) || index < 0 ) {
		return blocks[ 0 ] || null;
	}

	return blocks[ index ] || null;
}

export function resolveTaskTarget( task ) {
	const doc = getTargetDocument();

	if ( task.anchor ) {
		const byAnchor = doc.querySelector(
			`[data-forwp-todo-anchor="${ task.anchor }"]`
		);
		if ( byAnchor ) {
			return byAnchor;
		}
	}

	if ( task.selector ) {
		const isAnchorSelector = /^\[data-forwp-todo-anchor="[^"]+"\]$/.test(
			task.selector
		);

		if ( ! isAnchorSelector || ! task.anchor ) {
			try {
				const bySelector = doc.querySelector( task.selector );
				if ( bySelector ) {
					if ( task.anchor && ! bySelector.getAttribute( 'data-forwp-todo-anchor' ) ) {
						bySelector.setAttribute( 'data-forwp-todo-anchor', task.anchor );
					}
					return bySelector;
				}
			} catch ( e ) {
				// Invalid selector — fall through to block lookup.
			}
		}
	}

	const byBlock = resolveBlockTarget( task );
	if ( byBlock && task.anchor && ! byBlock.getAttribute( 'data-forwp-todo-anchor' ) ) {
		byBlock.setAttribute( 'data-forwp-todo-anchor', task.anchor );
	}

	return byBlock;
}

export { elementShellRect, getPickRoot, isEditorContext, pointerContextFromEvent };

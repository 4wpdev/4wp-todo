/**
 * DOM element picker — hover highlight + click to pin a note.
 */

import {
	elementShellRect,
	getPickRoot,
	isOverlayElement,
	isPickableElement,
	pointerContextFromEvent,
} from './lib/dom';
import { getScrollTargets, getEditorIframe, isEditorContext } from './lib/context';
import { pinElement } from './lib/selector';

const IFRAME_PIN_STYLE_ID = 'forwp-todo-pin-style';

function setPinModeUi( on ) {
	document.body.classList.toggle( 'forwp-todo-pin-mode', on );

	if ( ! isEditorContext() ) {
		return;
	}

	const iframe = getEditorIframe();
	const doc = iframe?.contentDocument;
	if ( ! doc?.body ) {
		return;
	}

	doc.body.classList.toggle( 'forwp-todo-pin-mode', on );

	if ( ! on ) {
		doc.getElementById( IFRAME_PIN_STYLE_ID )?.remove();
		return;
	}

	if ( doc.getElementById( IFRAME_PIN_STYLE_ID ) ) {
		return;
	}

	const style = doc.createElement( 'style' );
	style.id = IFRAME_PIN_STYLE_ID;
	style.textContent =
		'body.forwp-todo-pin-mode, body.forwp-todo-pin-mode * { cursor: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'32\' height=\'32\' viewBox=\'0 0 32 32\'%3E%3Cpath stroke=\'%231d2327\' stroke-width=\'2\' d=\'M16 4v24M4 16h24\'/%3E%3Cpath stroke=\'%23fff\' stroke-width=\'1\' d=\'M16 4v24M4 16h24\'/%3E%3Ccircle cx=\'16\' cy=\'16\' r=\'3\' fill=\'%233858e9\' stroke=\'%23fff\' stroke-width=\'1\'/%3E%3C/svg%3E") 24 24, crosshair !important; }';
	doc.head.append( style );
}

/**
 * @param {object}   options
 * @param {object}   options.i18n
 * @param {Function} options.onSelect Called with { element, anchor, selector, fallbackSelector }.
 * @param {Function} options.onCancel
 * @return {{ activate: Function, deactivate: Function, isActive: Function }}
 */
export function createPinPicker( { i18n, onSelect, onCancel } ) {
	let active = false;
	let highlightLocked = false;
	let highlight = null;
	let toolbar = null;
	let pickRoot = null;
	let highlightedElement = null;
	const scrollTargets = [];
	let canvasDoc = null;

	function ensureUi() {
		if ( highlight ) {
			return;
		}

		highlight = document.createElement( 'div' );
		highlight.className = 'forwp-todo-pin-highlight';
		highlight.hidden = true;
		highlight.setAttribute( 'aria-hidden', 'true' );

		toolbar = document.createElement( 'div' );
		toolbar.className = 'forwp-todo-pin-toolbar';
		toolbar.hidden = true;
		toolbar.innerHTML = `
			<div class="forwp-todo-pin-toolbar__copy">
				<p class="forwp-todo-pin-toolbar__hint"></p>
				<p class="forwp-todo-pin-toolbar__esc"></p>
			</div>
			<button type="button" class="forwp-todo-pin-toolbar__cancel" aria-label=""></button>
		`;

		document.body.append( highlight, toolbar );

		toolbar
			.querySelector( '.forwp-todo-pin-toolbar__cancel' )
			.addEventListener(
				'click',
				( event ) => {
					event.preventDefault();
					event.stopPropagation();
					deactivate();
				},
				true
			);
	}

	function updateHighlight( element ) {
		highlightedElement = element || null;

		if ( ! highlight || ! element ) {
			if ( highlight ) {
				highlight.hidden = true;
			}
			return;
		}

		const rect = elementShellRect( element );
		highlight.hidden = false;
		highlight.style.top = `${ rect.top }px`;
		highlight.style.left = `${ rect.left }px`;
		highlight.style.width = `${ rect.width }px`;
		highlight.style.height = `${ rect.height }px`;
	}

	function pointerFromEvent( event ) {
		const ownerDoc = event.target?.ownerDocument || document;

		if ( isEditorContext() && ownerDoc !== document ) {
			return {
				x: event.clientX,
				y: event.clientY,
				doc: ownerDoc,
				inCanvas: true,
			};
		}

		return pointerContextFromEvent( event );
	}

	function elementFromEvent( event ) {
		const pointer = pointerFromEvent( event );
		if ( ! pointer.inCanvas ) {
			return null;
		}

		const stack = pointer.doc.elementsFromPoint( pointer.x, pointer.y );
		for ( const node of stack ) {
			if ( isPickableElement( node, pickRoot ) ) {
				return node;
			}
		}
		return null;
	}

	function onMouseMove( event ) {
		if ( ! active ) {
			return;
		}

		if ( isOverlayElement( event.target ) ) {
			updateHighlight( null );
			return;
		}

		updateHighlight( elementFromEvent( event ) );
	}

	function onClick( event ) {
		if ( ! active ) {
			return;
		}

		if (
			event.target.closest(
				'.forwp-todo-pin-toolbar__cancel, .forwp-todo-panel__close, .forwp-todo-fab, .forwp-todo-composer'
			)
		) {
			return;
		}

		if ( isOverlayElement( event.target ) ) {
			return;
		}

		const target = elementFromEvent( event );
		if ( ! target ) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();

		const pin = pinElement( target, pickRoot );
		updateHighlight( target );
		deactivate( { preserveHighlight: true, notifyCancel: false } );
		onSelect( {
			element: target,
			...pin,
		} );
	}

	function onKeyDown( event ) {
		if ( ! active || event.key !== 'Escape' ) {
			return;
		}
		event.preventDefault();
		deactivate();
	}

	function bindScrollTargets() {
		getScrollTargets().forEach( ( target ) => {
			target.addEventListener( 'scroll', onScroll, true );
			scrollTargets.push( target );
		} );
		window.addEventListener( 'resize', onScroll, true );
		scrollTargets.push( window );
	}

	function unbindScrollTargets() {
		scrollTargets.forEach( ( target ) => {
			target.removeEventListener( 'scroll', onScroll, true );
		} );
		scrollTargets.length = 0;
		window.removeEventListener( 'resize', onScroll, true );
	}

	function bindCanvasListeners() {
		if ( ! isEditorContext() ) {
			return;
		}

		canvasDoc = getEditorIframe()?.contentDocument || null;
		if ( ! canvasDoc ) {
			return;
		}

		canvasDoc.addEventListener( 'mousemove', onMouseMove, true );
		canvasDoc.addEventListener( 'click', onClick, true );
	}

	function unbindCanvasListeners() {
		if ( ! canvasDoc ) {
			return;
		}

		canvasDoc.removeEventListener( 'mousemove', onMouseMove, true );
		canvasDoc.removeEventListener( 'click', onClick, true );
		canvasDoc = null;
	}

	function activate() {
		if ( active ) {
			return;
		}

		ensureUi();
		pickRoot = getPickRoot();
		active = true;

		setPinModeUi( true );
		toolbar.hidden = false;
		toolbar.querySelector( '.forwp-todo-pin-toolbar__hint' ).textContent =
			i18n?.pinHint || 'Click a section to attach your note';
		toolbar.querySelector( '.forwp-todo-pin-toolbar__esc' ).textContent =
			i18n?.pinEscHint || 'Press Esc to cancel';
		const cancelBtn = toolbar.querySelector(
			'.forwp-todo-pin-toolbar__cancel'
		);
		cancelBtn.textContent = '×';
		cancelBtn.setAttribute(
			'aria-label',
			i18n?.cancelPin || 'Cancel'
		);

		document.addEventListener( 'mousemove', onMouseMove, true );
		document.addEventListener( 'click', onClick, true );
		document.addEventListener( 'keydown', onKeyDown, true );
		bindCanvasListeners();
		bindScrollTargets();
	}

	function onScroll() {
		if ( ! highlightedElement || ( ! active && ! highlightLocked ) ) {
			return;
		}
		updateHighlight( highlightedElement );
	}

	function clearHighlight() {
		highlightLocked = false;
		highlightedElement = null;

		if ( highlight ) {
			highlight.hidden = true;
		}

		if ( ! active ) {
			unbindScrollTargets();
		}
	}

	function deactivate( options = {} ) {
		const { preserveHighlight = false, notifyCancel = true } = options;

		if ( ! active ) {
			if ( ! preserveHighlight ) {
				clearHighlight();
			}
			return;
		}

		active = false;

		if ( preserveHighlight && highlightedElement ) {
			highlightLocked = true;
			updateHighlight( highlightedElement );
		} else {
			clearHighlight();
		}

		setPinModeUi( false );

		if ( toolbar ) {
			toolbar.hidden = true;
		}

		document.removeEventListener( 'mousemove', onMouseMove, true );
		document.removeEventListener( 'click', onClick, true );
		document.removeEventListener( 'keydown', onKeyDown, true );
		unbindCanvasListeners();

		if ( ! preserveHighlight ) {
			unbindScrollTargets();
		}

		if ( notifyCancel ) {
			onCancel?.();
		}
	}

	return {
		activate,
		deactivate,
		clearHighlight,
		isActive: () => active,
	};
}

/**
 * Runtime context — public frontend vs block editor (iframe canvas).
 */

export function isEditorContext() {
	return ( window.forwpTodoFrontend || {} ).context === 'editor';
}

export function getEditorIframe() {
	return (
		document.querySelector( 'iframe[name="editor-canvas"]' ) ||
		document.querySelector( '#editor-canvas' ) ||
		document.querySelector( '.edit-post-visual-editor iframe' ) ||
		document.querySelector( '.editor-visual-editor iframe' )
	);
}

export function getTargetDocument() {
	if ( ! isEditorContext() ) {
		return document;
	}

	const iframe = getEditorIframe();
	return iframe?.contentDocument || document;
}

export function getContentRoot() {
	const doc = getTargetDocument();
	const selectors = isEditorContext()
		? [
				'.editor-styles-wrapper',
				'.block-editor-writing-flow',
				'.is-root-container',
				'.wp-block-post-content',
		  ]
		: [
				'.entry-content',
				'main .wp-block-post-content',
				'.wp-block-post-content',
				'main',
				'article',
				'.site-main',
		  ];

	for ( const selector of selectors ) {
		const node = doc.querySelector( selector );
		if ( node ) {
			return node;
		}
	}

	return doc.body;
}

/**
 * Widest DOM boundary for pin-to-element (whole page / editor canvas).
 *
 * @return {HTMLElement}
 */
export function getPickRoot() {
	return getTargetDocument().body;
}

/**
 * Map element rect to shell document coordinates (for highlight overlay).
 *
 * @param {Element} element Target in canvas or page document.
 * @return {{ top: number, left: number, width: number, height: number }}
 */
export function elementShellRect( element ) {
	const rect = element.getBoundingClientRect();

	if ( ! isEditorContext() ) {
		return {
			top: rect.top + window.scrollY,
			left: rect.left + window.scrollX,
			width: rect.width,
			height: rect.height,
		};
	}

	const iframe = getEditorIframe();
	if ( ! iframe ) {
		return {
			top: rect.top + window.scrollY,
			left: rect.left + window.scrollX,
			width: rect.width,
			height: rect.height,
		};
	}

	const iframeRect = iframe.getBoundingClientRect();

	return {
		top: iframeRect.top + rect.top + window.scrollY,
		left: iframeRect.left + rect.left + window.scrollX,
		width: rect.width,
		height: rect.height,
	};
}

/**
 * @param {MouseEvent} event Pointer event (usually on shell document).
 * @return {{ x: number, y: number, doc: Document, inCanvas: boolean }}
 */
export function pointerContextFromEvent( event ) {
	if ( ! isEditorContext() ) {
		return {
			x: event.clientX,
			y: event.clientY,
			doc: document,
			inCanvas: true,
		};
	}

	const iframe = getEditorIframe();
	if ( ! iframe?.contentDocument ) {
		return {
			x: event.clientX,
			y: event.clientY,
			doc: document,
			inCanvas: false,
		};
	}

	const iframeRect = iframe.getBoundingClientRect();
	const inCanvas =
		event.clientX >= iframeRect.left &&
		event.clientX <= iframeRect.right &&
		event.clientY >= iframeRect.top &&
		event.clientY <= iframeRect.bottom;

	if ( ! inCanvas ) {
		return {
			x: event.clientX,
			y: event.clientY,
			doc: document,
			inCanvas: false,
		};
	}

	return {
		x: event.clientX - iframeRect.left,
		y: event.clientY - iframeRect.top,
		doc: iframe.contentDocument,
		inCanvas: true,
	};
}

export function getScrollTargets() {
	const targets = [ window ];

	if ( isEditorContext() ) {
		const iframe = getEditorIframe();
		if ( iframe?.contentWindow ) {
			targets.push( iframe.contentWindow );
		}
	}

	return targets;
}

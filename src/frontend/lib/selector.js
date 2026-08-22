/**
 * Stable selector + anchor for pinned notes.
 */

export function createAnchorId() {
	return `n-${ Date.now().toString( 36 ) }-${ Math.random()
		.toString( 36 )
		.slice( 2, 7 ) }`;
}

function escapeCssIdent( value ) {
	if ( typeof CSS !== 'undefined' && CSS.escape ) {
		return CSS.escape( value );
	}
	return value.replace( /([^\w-])/g, '\\$1' );
}

function isUniqueSelector( selector, doc = document ) {
	try {
		return doc.querySelectorAll( selector ).length === 1;
	} catch ( e ) {
		return false;
	}
}

function segmentForElement( element ) {
	const doc = element.ownerDocument || document;

	if ( element.id && isUniqueSelector( `#${ escapeCssIdent( element.id ) }`, doc ) ) {
		return `#${ escapeCssIdent( element.id ) }`;
	}

	const blockClass = [ ...element.classList ].find( ( cls ) =>
		cls.startsWith( 'wp-block-' )
	);
	if (
		blockClass &&
		isUniqueSelector( `.${ escapeCssIdent( blockClass ) }`, doc )
	) {
		return `.${ escapeCssIdent( blockClass ) }`;
	}

	const tag = element.tagName.toLowerCase();
	const parent = element.parentElement;
	if ( ! parent ) {
		return tag;
	}

	const sameTag = [ ...parent.children ].filter(
		( child ) => child.tagName === element.tagName
	);
	if ( sameTag.length === 1 ) {
		return tag;
	}

	const index = sameTag.indexOf( element ) + 1;
	return `${ tag }:nth-of-type(${ index })`;
}

/**
 * Build a CSS path from content root to element (fallback if anchor is lost).
 *
 * @param {Element} element  Target element.
 * @param {Element} pickRoot Page/canvas boundary (usually body).
 * @return {string}
 */
export function buildFallbackSelector( element, pickRoot ) {
	const segments = [];
	let current = element;
	const doc = element.ownerDocument || document;

	while ( current && current !== pickRoot && current !== doc.documentElement ) {
		segments.unshift( segmentForElement( current ) );
		current = current.parentElement;
	}

	if ( pickRoot?.id && isUniqueSelector( `#${ escapeCssIdent( pickRoot.id ) }`, doc ) ) {
		return `#${ escapeCssIdent( pickRoot.id ) } ${ segments.join( ' > ' ) }`.trim();
	}

	return segments.join( ' > ' ).trim();
}

/**
 * Assign anchor attribute and return selector pair for REST storage.
 *
 * @param {Element} element  Selected DOM node.
 * @param {Element} pickRoot Pick boundary used for fallback selector.
 * @return {{ anchor: string, selector: string, fallbackSelector: string }}
 */
export function pinElement( element, pickRoot ) {
	const existingAnchor = element.getAttribute( 'data-forwp-todo-anchor' );
	const anchor = existingAnchor || createAnchorId();

	if ( ! existingAnchor ) {
		element.setAttribute( 'data-forwp-todo-anchor', anchor );
	}

	return {
		anchor,
		selector: `[data-forwp-todo-anchor="${ anchor }"]`,
		fallbackSelector: buildFallbackSelector( element, pickRoot ),
	};
}

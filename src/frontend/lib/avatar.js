/**
 * Avatar initials + stable color from user id.
 */

const PALETTE = [
	'#1a73e8',
	'#9334e6',
	'#d93025',
	'#188038',
	'#e37400',
	'#12b5cb',
	'#a142f4',
	'#c5221f',
];

/**
 * @param {string} name Display name.
 * @return {string}
 */
export function getInitials( name ) {
	const trimmed = ( name || '' ).trim();
	if ( ! trimmed ) {
		return '?';
	}

	const parts = trimmed.split( /\s+/ ).filter( Boolean );
	if ( parts.length >= 2 ) {
		return `${ parts[ 0 ][ 0 ] }${ parts[ parts.length - 1 ][ 0 ] }`.toUpperCase();
	}

	return trimmed.slice( 0, 1 ).toUpperCase();
}

/**
 * @param {number} userId User ID.
 * @return {string}
 */
export function getAvatarColor( userId ) {
	const id = Math.abs( parseInt( userId, 10 ) || 0 );
	return PALETTE[ id % PALETTE.length ];
}

/**
 * @param {string} isoDate GMT date string.
 * @return {string}
 */
export function formatRelativeTime( isoDate ) {
	if ( ! isoDate ) {
		return '';
	}

	const date = new Date(
		isoDate.includes( 'T' ) ? isoDate : `${ isoDate.replace( ' ', 'T' ) }Z`
	);
	if ( Number.isNaN( date.getTime() ) ) {
		return '';
	}

	const diffMs = Date.now() - date.getTime();
	const diffMin = Math.floor( diffMs / 60000 );
	if ( diffMin < 1 ) {
		return 'now';
	}
	if ( diffMin < 60 ) {
		return `${ diffMin }m`;
	}

	const diffHours = Math.floor( diffMin / 60 );
	if ( diffHours < 24 ) {
		return `${ diffHours }h`;
	}

	const diffDays = Math.floor( diffHours / 24 );
	if ( diffDays < 30 ) {
		return `${ diffDays }d`;
	}

	const diffMonths = Math.floor( diffDays / 30 );
	if ( diffMonths < 12 ) {
		return `${ diffMonths }mo`;
	}

	return `${ Math.floor( diffMonths / 12 ) }y`;
}

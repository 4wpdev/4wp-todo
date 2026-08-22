/**
 * REST helpers for frontend overlay.
 */

export function createApi( config ) {
	const restRoot = ( config.restRoot || '/wp-json/' ).replace( /\/?$/, '/' );

	return function api( path, options = {} ) {
		const url = `${ restRoot }${ path.replace( /^\//, '' ) }`;
		const headers = {
			'X-WP-Nonce': config.nonce,
			'Content-Type': 'application/json',
			...( options.headers || {} ),
		};

		return fetch( url, { ...options, headers } ).then( async ( res ) => {
			const data = await res.json().catch( () => ( {} ) );
			if ( ! res.ok ) {
				throw new Error( data.message || res.statusText );
			}
			return data;
		} );
	};
}

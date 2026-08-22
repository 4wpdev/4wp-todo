/**
 * Shared TODO taxonomy helpers for admin filters.
 */

export function getFilterDefaults( taxonomy ) {
	const defaults = { view: '' };

	( taxonomy?.dimensions || [] ).forEach( ( dimension ) => {
		if ( dimension.filterable && dimension.filter_param ) {
			defaults[ dimension.filter_param ] = '';
		}
	} );

	return defaults;
}

export function buildFilterQuery( filters, taxonomy ) {
	const params = new URLSearchParams();
	params.set( 'per_page', '100' );

	if ( filters.view ) {
		params.set( 'view', filters.view );
	}

	( taxonomy?.dimensions || [] ).forEach( ( dimension ) => {
		if ( ! dimension.filterable || ! dimension.filter_param ) {
			return;
		}

		const value = filters[ dimension.filter_param ];
		if ( value ) {
			params.set( dimension.filter_param, value );
		}
	} );

	return params.toString();
}

export function dimensionFilterOptions( dimension ) {
	const terms = dimension.terms || [];

	return [
		{
			label: dimension.all_label || dimension.label,
			value: '',
		},
		...terms.map( ( term ) => ( {
			value: term.slug,
			label: term.label,
		} ) ),
	];
}

export function filterableDimensions( taxonomy ) {
	return ( taxonomy?.dimensions || [] ).filter(
		( dimension ) => dimension.filterable && dimension.filter_param
	);
}

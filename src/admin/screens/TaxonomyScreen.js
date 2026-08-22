/**
 * TODO taxonomy tree (Category, Status, Workflow, Page).
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Card, CardBody, Spinner } from '@wordpress/components';

const META_PATH = '/forwp-todo/v1/meta';

function TaxonomyTerms( { terms } ) {
	if ( ! terms?.length ) {
		return (
			<p className="forwp-todo-admin-muted">
				{ __( 'No terms yet.', '4wp-todo' ) }
			</p>
		);
	}

	return (
		<ul className="forwp-todo-tax-tree__terms">
			{ terms.map( ( term ) => (
				<li key={ term.slug }>
					<code>{ term.slug }</code>
					<span className="forwp-todo-tax-tree__term-label">
						{ term.label }
					</span>
				</li>
			) ) }
		</ul>
	);
}

function TaxonomyDimension( { dimension } ) {
	return (
		<li className="forwp-todo-tax-tree__dimension">
			<div className="forwp-todo-tax-tree__dimension-head">
				<strong>{ dimension.label }</strong>
				{ dimension.filterable && (
					<span className="forwp-todo-tax-tree__badge">
						{ __( 'List filter', '4wp-todo' ) }
					</span>
				) }
			</div>
			{ dimension.description && (
				<p className="forwp-todo-admin-muted">{ dimension.description }</p>
			) }
			{ dimension.groups?.length ? (
				dimension.groups.map( ( group ) => (
					<div
						key={ group.label }
						className="forwp-todo-tax-tree__group"
					>
						<span className="forwp-todo-tax-tree__group-label">
							{ group.label }
						</span>
						<TaxonomyTerms terms={ group.terms } />
					</div>
				) )
			) : (
				<TaxonomyTerms terms={ dimension.terms } />
			) }
		</li>
	);
}

export default function TaxonomyScreen() {
	const [ taxonomy, setTaxonomy ] = useState( null );

	useEffect( () => {
		apiFetch( { path: META_PATH } )
			.then( ( meta ) => setTaxonomy( meta.taxonomy || null ) )
			.catch( () => setTaxonomy( null ) );
	}, [] );

	if ( ! taxonomy ) {
		return <Spinner />;
	}

	return (
		<div className="forwp-todo-tab-panel__inner">
			<Card className="forwp-todo-tax-tree-card">
				<CardBody>
					<div className="forwp-todo-tax-tree">
						<div className="forwp-todo-tax-tree__root">
							<h2>{ taxonomy.label }</h2>
							{ taxonomy.description && (
								<p className="forwp-todo-admin-muted">
									{ taxonomy.description }
								</p>
							) }
						</div>
						<ul className="forwp-todo-tax-tree__dimensions">
							{ ( taxonomy.dimensions || [] ).map( ( dimension ) => (
								<TaxonomyDimension
									key={ dimension.id }
									dimension={ dimension }
								/>
							) ) }
						</ul>
					</div>
				</CardBody>
			</Card>
		</div>
	);
}

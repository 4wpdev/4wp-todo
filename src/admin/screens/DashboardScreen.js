/**
 * Dashboard overview.
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Card, CardBody, Spinner } from '@wordpress/components';

const STATS_PATH = '/forwp-todo/v1/stats';
const LISTS_URL = 'admin.php?page=forwp-todo-lists';

export default function DashboardScreen() {
	const [ stats, setStats ] = useState( null );

	useEffect( () => {
		apiFetch( { path: STATS_PATH } )
			.then( setStats )
			.catch( () => setStats( { total: 0, open: 0, closed: 0 } ) );
	}, [] );

	if ( ! stats ) {
		return <Spinner />;
	}

	return (
		<div className="forwp-todo-tab-panel__inner">
			<div className="forwp-todo-stats-grid">
				<Card>
					<CardBody>
						<h3>{ __( 'Open notes', '4wp-todo' ) }</h3>
						<p className="forwp-todo-stat">{ stats.open }</p>
					</CardBody>
				</Card>
				<Card>
					<CardBody>
						<h3>{ __( 'Closed notes', '4wp-todo' ) }</h3>
						<p className="forwp-todo-stat">{ stats.closed }</p>
					</CardBody>
				</Card>
				<Card>
					<CardBody>
						<h3>{ __( 'Total', '4wp-todo' ) }</h3>
						<p className="forwp-todo-stat">{ stats.total }</p>
					</CardBody>
				</Card>
			</div>
			<Card className="forwp-todo-intro-card">
				<CardBody>
					<h3>{ __( 'Quick start', '4wp-todo' ) }</h3>
					<p>
						{ __(
							'Notes created in the block editor or on the frontend appear in TODO Lists. Core note open/close is preserved; 4WP adds categories and workflow lifts.',
							'4wp-todo'
						) }
					</p>
					<p>
						<a className="button button-primary" href={ LISTS_URL }>
							{ __( 'Open TODO Lists', '4wp-todo' ) }
						</a>
					</p>
				</CardBody>
			</Card>
		</div>
	);
}

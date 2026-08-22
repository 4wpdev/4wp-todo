/**
 * 4WP TODO admin shell — screen router (Dashboard / Lists / Taxonomy / Settings).
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Notice } from '@wordpress/components';
import DashboardScreen from './screens/DashboardScreen';
import ListsScreen from './screens/ListsScreen';
import SettingsScreen from './screens/SettingsScreen';

function setupApiFetch() {
	if ( typeof window === 'undefined' || ! window.forwpTodoAdmin ) {
		return false;
	}
	if ( window.forwpTodoAdmin.__middlewareApplied ) {
		return true;
	}
	const { restRoot, nonce } = window.forwpTodoAdmin;
	const root = restRoot.endsWith( '/' ) ? restRoot : `${ restRoot }/`;
	apiFetch.use( apiFetch.createRootURLMiddleware( root ) );
	apiFetch.use( apiFetch.createNonceMiddleware( nonce ) );
	window.forwpTodoAdmin.__middlewareApplied = true;
	return true;
}

function getScreen() {
	const fromConfig = window.forwpTodoAdmin?.screen;
	if ( fromConfig ) {
		return fromConfig;
	}
	const rootEl = document.getElementById( 'forwp-todo-admin-root' );
	return rootEl?.dataset?.screen || 'dashboard';
}

export default function App() {
	const apiReady = setupApiFetch();
	const screen = getScreen();
	const [ error, setError ] = useState( '' );

	let content = null;
	switch ( screen ) {
		case 'lists':
			content = <ListsScreen onError={ setError } />;
			break;
		case 'settings':
			content = <SettingsScreen />;
			break;
		default:
			content = <DashboardScreen />;
			break;
	}

	return (
		<div className="forwp-todo-admin-app">
			{ ! apiReady && (
				<Notice status="error" isDismissible={ false }>
					{ __( '4WP TODO admin scripts failed to load. Hard-refresh this page.', '4wp-todo' ) }
				</Notice>
			) }
			{ error && (
				<Notice status="error" isDismissible={ false } onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			{ content }
		</div>
	);
}

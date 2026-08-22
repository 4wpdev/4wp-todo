/**
 * Public tasks page — full kanban (Gutenberg block mount).
 */
import { createRoot } from '@wordpress/element';
import ListsScreen from '../admin/screens/ListsScreen';
import './style.scss';

function boot() {
	const mount = document.getElementById( 'forwp-todo-tasks-root' );
	if ( ! mount || mount.dataset.forwpTodoTasksReady === '1' ) {
		return;
	}

	mount.dataset.forwpTodoTasksReady = '1';
	mount.innerHTML = '';

	const shell = document.createElement( 'div' );
	shell.className = 'forwp-todo-tasks-page';
	mount.append( shell );

	const root = createRoot( shell );
	root.render(
		<ListsScreen
			onError={ ( message ) => {
				if ( ! message ) {
					return;
				}
				// eslint-disable-next-line no-console
				console.error( message );
			} }
		/>
	);
}

window.forwpTodoTasksPageInit = boot;

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}

import { createRoot } from '@wordpress/element';
import App from './App';
import './style.scss';

const rootEl = document.getElementById( 'forwp-todo-admin-root' );

if ( rootEl ) {
	createRoot( rootEl ).render( <App /> );
}

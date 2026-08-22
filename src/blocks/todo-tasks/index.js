/**
 * forwp/todo-tasks — block editor (live kanban preview).
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { Spinner } from '@wordpress/components';
import { createRoot, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import metadata from '../../../assets/blocks/todo-tasks/block.json';
import ListsScreen from '../../admin/screens/ListsScreen';
import './editor.scss';

function getEditorConfig() {
	return window.forwpTodoTasksBlockEditor || {};
}

function Edit() {
	const blockProps = useBlockProps( {
		className: 'forwp-todo-tasks-block-editor',
	} );
	const mountRef = useRef( null );
	const rootRef = useRef( null );
	const config = getEditorConfig();

	useEffect( () => {
		const mount = mountRef.current;
		if ( ! mount || ! config.canAccess ) {
			return undefined;
		}

		const shell = document.createElement( 'div' );
		shell.className = 'forwp-todo-tasks-page';
		mount.append( shell );

		const root = createRoot( shell );
		rootRef.current = root;
		root.render(
			<ListsScreen
				onError={ ( message ) => {
					if ( message ) {
						// eslint-disable-next-line no-console
						console.error( message );
					}
				} }
			/>
		);

		return () => {
			root.unmount();
			rootRef.current = null;
			mount.innerHTML = '';
		};
	}, [ config.canAccess ] );

	if ( ! config.isLoggedIn ) {
		return (
			<div { ...blockProps }>
				<p className="forwp-todo-tasks-root forwp-todo-tasks-root--guest">
					{ __( 'Log in to view your tasks.', '4wp-todo' ) }
				</p>
			</div>
		);
	}

	if ( ! config.canAccess ) {
		return (
			<div { ...blockProps }>
				<p className="forwp-todo-tasks-root forwp-todo-tasks-root--denied">
					{ __( 'You do not have access to TODO.', '4wp-todo' ) }
				</p>
			</div>
		);
	}

	return (
		<div { ...blockProps }>
			<div ref={ mountRef } className="forwp-todo-tasks-root">
				<div className="forwp-todo-kanban-loading forwp-todo-kanban-loading--boot">
					<Spinner />
				</div>
			</div>
		</div>
	);
}

registerBlockType( metadata.name, {
	apiVersion: metadata.apiVersion,
	title: metadata.title,
	category: metadata.category,
	description: metadata.description,
	icon: metadata.icon,
	keywords: metadata.keywords,
	attributes: metadata.attributes,
	supports: metadata.supports,
	edit: Edit,
	save: () => null,
} );

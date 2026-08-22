/**
 * TODO Lists — Google Tasks kanban (same panel UI as frontend).
 */
import { __ } from '@wordpress/i18n';
import { useState, useEffect, useCallback, useMemo, useRef } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Spinner } from '@wordpress/components';
import { createPanel } from '../../shared/panel';
import {
	TASK_SCOPE_ALL,
	TASK_SCOPE_ASSIGNED,
	TASK_SCOPE_AUTHORED,
	filterTasksByScope,
} from '../../shared/task-scope';

const TASKS_PATH = '/forwp-todo/v1/tasks';
const META_PATH = '/forwp-todo/v1/meta';
const REORDER_PATH = '/forwp-todo/v1/tasks/reorder';

function resolveColumnSortScope( column ) {
	if ( column.postId ) {
		return `page:${ column.postId }`;
	}

	return `column:${ column.id }`;
}

const SCOPE_ALL = TASK_SCOPE_ALL;
const SCOPE_AUTHORED = TASK_SCOPE_AUTHORED;
const SCOPE_ASSIGNED = TASK_SCOPE_ASSIGNED;

function filterTasksByScopeAndUser( tasks, scope, userFilter, currentUserId ) {
	return filterTasksByScope( tasks, scope, currentUserId ).filter( ( task ) => {
		if ( userFilter <= 0 ) {
			return true;
		}

		return (
			Number( task.author ) === Number( userFilter ) ||
			Number( task.assignee || 0 ) === Number( userFilter )
		);
	} );
}

function ListsFilterBar( {
	scope,
	userFilter,
	users,
	canViewAllTasks,
	onScopeChange,
	onUserChange,
} ) {
	if ( ! canViewAllTasks ) {
		return (
			<div
				className="forwp-todo-kanban-toolbar"
				role="region"
				aria-label={ __( 'Filter tasks', '4wp-todo' ) }
			>
				<div
					className="forwp-todo-kanban-scope"
					role="tablist"
					aria-label={ __( 'Task scope', '4wp-todo' ) }
				>
					<button
						type="button"
						role="tab"
						className={
							scope === SCOPE_ALL
								? 'forwp-todo-kanban-scope__btn is-active'
								: 'forwp-todo-kanban-scope__btn'
						}
						aria-selected={ scope === SCOPE_ALL }
						onClick={ () => onScopeChange( SCOPE_ALL ) }
					>
						{ __( 'All mine', '4wp-todo' ) }
					</button>
					<button
						type="button"
						role="tab"
						className={
							scope === SCOPE_AUTHORED
								? 'forwp-todo-kanban-scope__btn is-active'
								: 'forwp-todo-kanban-scope__btn'
						}
						aria-selected={ scope === SCOPE_AUTHORED }
						onClick={ () => onScopeChange( SCOPE_AUTHORED ) }
					>
						{ __( 'Mine', '4wp-todo' ) }
					</button>
					<button
						type="button"
						role="tab"
						className={
							scope === SCOPE_ASSIGNED
								? 'forwp-todo-kanban-scope__btn is-active'
								: 'forwp-todo-kanban-scope__btn'
						}
						aria-selected={ scope === SCOPE_ASSIGNED }
						onClick={ () => onScopeChange( SCOPE_ASSIGNED ) }
					>
						{ __( 'For me', '4wp-todo' ) }
					</button>
				</div>
			</div>
		);
	}

	return (
		<div
			className="forwp-todo-kanban-toolbar"
			role="region"
			aria-label={ __( 'Filter tasks', '4wp-todo' ) }
		>
			<div
				className="forwp-todo-kanban-scope"
				role="tablist"
				aria-label={ __( 'Task scope', '4wp-todo' ) }
			>
				<button
					type="button"
					role="tab"
					className={
						scope === SCOPE_ALL
							? 'forwp-todo-kanban-scope__btn is-active'
							: 'forwp-todo-kanban-scope__btn'
					}
					aria-selected={ scope === SCOPE_ALL }
					onClick={ () => onScopeChange( SCOPE_ALL ) }
				>
					{ __( 'All', '4wp-todo' ) }
				</button>
				<button
					type="button"
					role="tab"
					className={
						scope === SCOPE_AUTHORED
							? 'forwp-todo-kanban-scope__btn is-active'
							: 'forwp-todo-kanban-scope__btn'
					}
					aria-selected={ scope === SCOPE_AUTHORED }
					onClick={ () => onScopeChange( SCOPE_AUTHORED ) }
				>
					{ __( 'Mine', '4wp-todo' ) }
				</button>
				<button
					type="button"
					role="tab"
					className={
						scope === SCOPE_ASSIGNED
							? 'forwp-todo-kanban-scope__btn is-active'
							: 'forwp-todo-kanban-scope__btn'
					}
					aria-selected={ scope === SCOPE_ASSIGNED }
					onClick={ () => onScopeChange( SCOPE_ASSIGNED ) }
				>
					{ __( 'For me', '4wp-todo' ) }
				</button>
			</div>
			<label className="forwp-todo-kanban-user-filter">
				<span className="screen-reader-text">
					{ __( 'Filter by user', '4wp-todo' ) }
				</span>
				<select
					value={ userFilter }
					onChange={ ( event ) =>
						onUserChange( Number( event.target.value ) || 0 )
					}
					disabled={ scope !== SCOPE_ALL }
				>
					<option value={ 0 }>{ __( 'All users', '4wp-todo' ) }</option>
					{ users.map( ( user ) => (
						<option key={ user.id } value={ user.id }>
							{ user.name }
						</option>
					) ) }
				</select>
			</label>
		</div>
	);
}

function tomorrowIso() {
	const date = new Date();
	date.setDate( date.getDate() + 1 );
	return date.toISOString().slice( 0, 10 );
}

function buildPanelI18n() {
	return {
		checklist: __( 'Page notes', '4wp-todo' ),
		addTask: __( 'Add a task', '4wp-todo' ),
		openPageList: __( 'Open page list', '4wp-todo' ),
		takeElement: __( 'Take element', '4wp-todo' ),
		loading: __( 'Loading notes…', '4wp-todo' ),
		empty: __( 'No tasks yet', '4wp-todo' ),
		completed: __( 'Completed', '4wp-todo' ),
		placeholder: __( 'What should change here?', '4wp-todo' ),
		markDone: __( 'Mark as done', '4wp-todo' ),
		markOpen: __( 'Mark as open', '4wp-todo' ),
		taskMenu: __( 'Task menu', '4wp-todo' ),
		editTask: __( 'Edit task', '4wp-todo' ),
		assign: __( 'Assign', '4wp-todo' ),
		unassigned: __( 'Unassigned', '4wp-todo' ),
		assignDepartment: __( 'Set department…', '4wp-todo' ),
		dueDate: __( 'Due date', '4wp-todo' ),
		today: __( 'Today', '4wp-todo' ),
		tomorrow: __( 'Tomorrow', '4wp-todo' ),
		clearDueDate: __( 'Clear due date', '4wp-todo' ),
		pickDueDate: __( 'Pick date…', '4wp-todo' ),
		deleteTask: __( 'Delete task', '4wp-todo' ),
		shortNote: __( 'Short note', '4wp-todo' ),
		department: __( 'Department', '4wp-todo' ),
		assignee: __( 'Assignee', '4wp-todo' ),
		selectDepartment: __( 'Select department', '4wp-todo' ),
		selectAssignee: __( 'Select assignee', '4wp-todo' ),
		createdBy: __( 'By', '4wp-todo' ),
		assignedTo: __( '→', '4wp-todo' ),
		closePanel: __( 'Close', '4wp-todo' ),
		editDone: __( 'Done', '4wp-todo' ),
		dragToReorder: __( 'Drag to reorder', '4wp-todo' ),
		jumpTo: __( 'Jump to section', '4wp-todo' ),
	};
}

function KanbanColumn( {
	column,
	tasks,
	meta,
	loading,
	i18n,
	onUpdate,
	onDelete,
	onQuickAdd,
	onReorder,
} ) {
	const mountRef = useRef( null );
	const panelRef = useRef( null );
	const columnTasks = useMemo(
		() => tasks.filter( column.filter ),
		[ tasks, column ]
	);
	const columnTasksRef = useRef( columnTasks );
	const metaRef = useRef( meta );
	const loadingRef = useRef( loading );

	columnTasksRef.current = columnTasks;
	metaRef.current = meta;
	loadingRef.current = loading;

	const sortScope = resolveColumnSortScope( column );

	useEffect( () => {
		if ( ! mountRef.current ) {
			return undefined;
		}

		if ( ! panelRef.current ) {
			panelRef.current = createPanel( mountRef.current, {
				variant: 'column',
				title: column.title,
				headerLink: column.headerLink || '',
				pageViewUrl: column.pageViewUrl || '',
				i18n,
				getTasks: () => columnTasksRef.current,
				getMeta: () => metaRef.current,
				isLoading: () => loadingRef.current,
				sortScope,
				onReorder: ( taskIds ) => onReorder( sortScope, taskIds ),
				onQuickAdd: ( content ) => onQuickAdd( content, column ),
				onUpdateTask: onUpdate,
				onDeleteTask: onDelete,
			} );
		}

		panelRef.current.render( {
			soft: ! loadingRef.current && Boolean( mountRef.current?.querySelector( '.forwp-todo-panel__head' ) ),
		} );

		return undefined;
	}, [
		column,
		columnTasks,
		meta,
		loading,
		i18n,
		onDelete,
		onQuickAdd,
		onReorder,
		onUpdate,
		sortScope,
	] );

	return (
		<div className="forwp-todo-kanban-column">
			<div ref={ mountRef } className="forwp-todo-panel" />
		</div>
	);
}

export default function ListsScreen( { onError } ) {
	const [ tasks, setTasks ] = useState( [] );
	const [ meta, setMeta ] = useState( {
		categories: {},
		users: [],
		posts: [],
		current_user: { id: 0 },
	} );
	const [ loading, setLoading ] = useState( true );
	const [ scopeFilter, setScopeFilter ] = useState( SCOPE_ALL );
	const [ userFilter, setUserFilter ] = useState( 0 );
	const i18n = useMemo( () => buildPanelI18n(), [] );

	const load = useCallback( async () => {
		setLoading( true );
		onError( '' );
		try {
			const [ taskData, metaData ] = await Promise.all( [
				apiFetch( { path: `${ TASKS_PATH }?per_page=100` } ),
				apiFetch( { path: META_PATH } ),
			] );
			setTasks( taskData.items || [] );
			setMeta( metaData );
		} catch ( e ) {
			onError(
				e?.message ||
					__(
						'Could not load notes. Hard-refresh the page or log in again if you see “Session expired”.',
						'4wp-todo'
					)
			);
			setTasks( [] );
		} finally {
			setLoading( false );
		}
	}, [ onError ] );

	useEffect( () => {
		load();
	}, [ load ] );

	const filterUsers = useMemo( () => {
		const map = new Map( ( meta.users || [] ).map( ( user ) => [ user.id, user ] ) );
		tasks.forEach( ( task ) => {
			if ( task.author && ! map.has( task.author ) && task.author_name ) {
				map.set( task.author, {
					id: task.author,
					name: task.author_name,
				} );
			}
			if ( task.assignee && ! map.has( task.assignee ) && task.assignee_name ) {
				map.set( task.assignee, {
					id: task.assignee,
					name: task.assignee_name,
				} );
			}
		} );
		return Array.from( map.values() ).sort( ( a, b ) =>
			a.name.localeCompare( b.name )
		);
	}, [ meta.users, tasks ] );

	const filteredTasks = useMemo(
		() =>
			filterTasksByScopeAndUser(
				tasks,
				scopeFilter,
				userFilter,
				meta.current_user?.id || 0
			),
		[ meta.current_user?.id, scopeFilter, tasks, userFilter ]
	);

	const handleScopeChange = useCallback( ( nextScope ) => {
		setScopeFilter( nextScope );
		if ( nextScope !== SCOPE_ALL ) {
			setUserFilter( 0 );
		}
	}, [] );

	const columns = useMemo( () => {
		const userId = meta.current_user?.id || 0;
		const dueTomorrow = tomorrowIso();
		const postMap = new Map(
			( meta.posts || [] ).map( ( post ) => [ post.id, post ] )
		);

		filteredTasks.forEach( ( task ) => {
			if ( task.post_id && ! postMap.has( task.post_id ) ) {
				postMap.set( task.post_id, {
					id: task.post_id,
					title: task.post_title || `#${ task.post_id }`,
					edit_url: task.edit_post_url || '',
					view_url: task.context_url || '',
				} );
			}
		} );

		const smart = [
			{
				id: 'mine',
				title: __( 'Assigned to me', '4wp-todo' ),
				filter: ( task ) => ( task.assignee || 0 ) === userId && userId > 0,
				quickAdd: { assignee: userId },
			},
			{
				id: 'tomorrow',
				title: __( 'Due tomorrow', '4wp-todo' ),
				filter: ( task ) => task.due_date === dueTomorrow,
				quickAdd: { due_date: dueTomorrow },
			},
		];

		const pages = Array.from( postMap.values() )
			.sort( ( a, b ) => a.title.localeCompare( b.title ) )
			.map( ( post ) => {
				const viewUrl = post.view_url
					? `${ post.view_url.replace( /#.*$/, '' ) }#forwp-todo-open`
					: '';

				return {
					id: `post-${ post.id }`,
					title: post.title,
					headerLink: post.edit_url || '',
					pageViewUrl: viewUrl,
					postId: post.id,
					quickAdd: { post_id: post.id },
					filter: ( task ) =>
						Number( task.post_id ) === Number( post.id ),
				};
			} );

		return [ ...smart, ...pages ];
	}, [ filteredTasks, meta.current_user?.id, meta.posts ] );

	const updateTask = useCallback(
		async ( id, payload ) => {
			onError( '' );
			try {
				const updated = await apiFetch( {
					path: `${ TASKS_PATH }/${ id }`,
					method: 'POST',
					data: payload,
				} );
				setTasks( ( prev ) =>
					prev.map( ( row ) => ( row.id === id ? updated : row ) )
				);
			} catch ( e ) {
				onError( e?.message || __( 'Update failed.', '4wp-todo' ) );
			}
		},
		[ onError ]
	);

	const deleteTask = useCallback(
		async ( id ) => {
			onError( '' );
			try {
				await apiFetch( {
					path: `${ TASKS_PATH }/${ id }`,
					method: 'DELETE',
				} );
				setTasks( ( prev ) => prev.filter( ( row ) => row.id !== id ) );
			} catch ( e ) {
				onError( e?.message || __( 'Delete failed.', '4wp-todo' ) );
			}
		},
		[ onError ]
	);

	const reorderTasks = useCallback(
		async ( scope, taskIds ) => {
			if ( ! scope || ! taskIds?.length ) {
				return;
			}

			onError( '' );
			try {
				await apiFetch( {
					path: REORDER_PATH,
					method: 'POST',
					data: {
						scope,
						task_ids: taskIds,
					},
				} );
				setTasks( ( prev ) =>
					prev.map( ( task ) => {
						const index = taskIds.indexOf( task.id );
						if ( index === -1 ) {
							return task;
						}

						return {
							...task,
							sort_map: {
								...( task.sort_map || {} ),
								[ scope ]: index,
							},
						};
					} )
				);
			} catch ( e ) {
				onError(
					e?.message || __( 'Could not reorder tasks.', '4wp-todo' )
				);
			}
		},
		[ onError ]
	);

	const quickAdd = useCallback(
		async ( content, column ) => {
			if ( ! content?.trim() ) {
				return;
			}

			const quickDefaults = column.quickAdd || {};
			const postId =
				quickDefaults.post_id ||
				column.postId ||
				meta.posts?.[ 0 ]?.id ||
				tasks.find( ( task ) => task.post_id )?.post_id ||
				0;

			if ( ! postId ) {
				onError(
					__(
						'Pick a page column to add a task, or create a note on a page first.',
						'4wp-todo'
					)
				);
				return;
			}

			onError( '' );
			const payload = {
				content,
				post_id: postId,
				source: 'admin',
			};

			if ( quickDefaults.assignee ) {
				payload.assignee = quickDefaults.assignee;
			}
			if ( quickDefaults.due_date ) {
				payload.due_date = quickDefaults.due_date;
			}

			try {
				const created = await apiFetch( {
					path: TASKS_PATH,
					method: 'POST',
					data: payload,
				} );
				setTasks( ( prev ) => [ created, ...prev ] );
			} catch ( e ) {
				onError( e?.message || __( 'Could not create note.', '4wp-todo' ) );
				throw e;
			}
		},
		[ meta.posts, onError, tasks ]
	);

	if ( loading && tasks.length === 0 ) {
		return (
			<div className="forwp-todo-kanban-loading">
				<Spinner />
			</div>
		);
	}

	return (
		<div className="forwp-todo-kanban-wrap">
			<ListsFilterBar
				scope={ scopeFilter }
				userFilter={ userFilter }
				users={ filterUsers }
				canViewAllTasks={ !! meta.can_view_all_tasks }
				onScopeChange={ handleScopeChange }
				onUserChange={ setUserFilter }
			/>
			<div className="forwp-todo-kanban-board">
				{ columns.map( ( column ) => (
					<KanbanColumn
						key={ column.id }
						column={ column }
						tasks={ filteredTasks }
						meta={ meta }
						loading={ loading }
						i18n={ i18n }
						onUpdate={ updateTask }
						onDelete={ deleteTask }
						onQuickAdd={ quickAdd }
						onReorder={ reorderTasks }
					/>
				) ) }
			</div>
		</div>
	);
}

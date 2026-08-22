/**
 * Frontend checklist overlay — pin picker + REST (no reload).
 */
import './style.scss';

import { createApi } from './lib/api';
import { detectViewport } from './lib/dom';
import { createComposer } from './composer';
import { createMarkers } from './markers';
import { createPanel } from '../shared/panel';
import { createPinPicker } from './pin-picker';
import { createThread } from './thread';

function getConfig() {
	const localized = window.forwpTodoFrontend || {};

	return {
		context: 'frontend',
		restRoot: '/wp-json/',
		nonce: '',
		postId: 0,
		pageUrl: '',
		adminScreenUrl: '',
		adminScreenKey: '',
		storagePostId: 0,
		i18n: {},
		...localized,
		restRoot: localized.restRoot || '/wp-json/',
	};
}

function normalizeContextUrl( url ) {
	if ( ! url ) {
		return '';
	}

	try {
		const parsed = new URL( url, window.location.origin );
		parsed.searchParams.delete( 'forwp-todo-pin' );
		parsed.hash = '';
		return `${ parsed.pathname }${ parsed.search }`;
	} catch ( e ) {
		return String( url ).split( '#' )[ 0 ];
	}
}

const ROOT_HTML = `
	<div class="forwp-todo-fab-wrap">
		<span id="forwp-todo-fab-tooltip" class="forwp-todo-fab__tooltip">Page notes — open list, pin to a section</span>
		<button type="button" id="forwp-todo-fab-trigger" class="forwp-todo-fab" aria-label="Page notes" title="Page notes — open list, pin to a section">
			<span class="forwp-todo-fab__icon forwp-todo-fab__icon--comment" aria-hidden="true"></span>
			<span class="forwp-todo-fab__icon forwp-todo-fab__icon--close" aria-hidden="true"></span>
			<span class="forwp-todo-fab__badge" hidden></span>
		</button>
	</div>
`;

function normalizeFabDom( root ) {
	const fab = root.querySelector( '#forwp-todo-fab-trigger' );
	if ( ! fab ) {
		return;
	}

	let wrap = root.querySelector( '.forwp-todo-fab-wrap' );
	if ( ! wrap ) {
		wrap = document.createElement( 'div' );
		wrap.className = 'forwp-todo-fab-wrap';
		fab.parentNode.insertBefore( wrap, fab );
		wrap.append( fab );
	}

	let tooltip =
		root.querySelector( '#forwp-todo-fab-tooltip' ) ||
		fab.querySelector( '.forwp-todo-fab__tooltip' );
	if ( tooltip && tooltip.parentElement !== wrap ) {
		tooltip.id = 'forwp-todo-fab-tooltip';
		tooltip.className = 'forwp-todo-fab__tooltip';
		wrap.insertBefore( tooltip, fab );
	}
}

function ensureRoot( config ) {
	let root = document.getElementById( 'forwp-todo-frontend-root' );

	if ( root?.closest( '#wpfooter' ) ) {
		document.body.append( root );
	}

	if ( root ) {
		normalizeFabDom( root );
		return root;
	}

	if ( config.context !== 'editor' && config.context !== 'admin' ) {
		return null;
	}

	root = document.createElement( 'div' );
	root.id = 'forwp-todo-frontend-root';
	root.className = 'forwp-todo-frontend-root';
	if ( config.context === 'editor' ) {
		root.className += ' forwp-todo-frontend-root--editor';
	} else if ( config.context === 'admin' ) {
		root.className += ' forwp-todo-frontend-root--admin';
	}
	root.setAttribute( 'aria-live', 'polite' );
	root.innerHTML = ROOT_HTML;

	const i18n = config.i18n || {};
	const fab = root.querySelector( '.forwp-todo-fab' );
	const tooltip = root.querySelector( '#forwp-todo-fab-tooltip' );
	if ( fab ) {
		const hasPageContext = Number( config.postId ) > 0;
		const label = hasPageContext
			? i18n.fabLabel || 'Page notes'
			: i18n.fabLabelAdmin || i18n.allTasksList || 'Tasks';
		const tip =
			i18n.fabTooltip ||
			'Page notes — open list, pin to a section';
		fab.setAttribute( 'aria-label', label );
		fab.setAttribute( 'title', tip );
		if ( tooltip ) {
			tooltip.textContent = tip;
		}
	}

	document.body.append( root );
	return root;
}

function initOverlay() {
	const config = getConfig();
	const root = ensureRoot( config );
	if ( ! root || root.dataset.forwpTodoReady === '1' ) {
		return;
	}

	boot( root, config );
	root.dataset.forwpTodoReady = '1';
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initOverlay );
} else {
	initOverlay();
}

window.forwpTodoInit = initOverlay;

if ( typeof window.wp !== 'undefined' && window.wp.domReady ) {
	window.wp.domReady( initOverlay );
}

function boot( root, config ) {
	const api = createApi( config );
	const isAdminOverlay = config.context === 'admin';
	const hasPageContext = Number( config.postId ) > 0;
	const adminScreenUrl = config.adminScreenUrl || '';
	const adminScreenKey = config.adminScreenKey || '';
	const hasAdminScreenContext =
		isAdminOverlay &&
		! hasPageContext &&
		Boolean( adminScreenKey || adminScreenUrl );
	const canPinLocally =
		( hasPageContext &&
			( config.context === 'frontend' || config.context === 'editor' ) ) ||
		hasAdminScreenContext;
	const showPostPicker = isAdminOverlay && ! hasPageContext && ! hasAdminScreenContext;
	const sortScope = hasPageContext
		? `page:${ config.postId }`
		: hasAdminScreenContext && adminScreenKey
			? `admin:${ adminScreenKey }`
			: 'admin:global';
	const panelI18n = {
		...( config.i18n || {} ),
		checklist: hasPageContext
			? config.i18n?.checklist || 'Page notes'
			: hasAdminScreenContext
				? config.i18n?.checklist || 'Page notes'
				: config.i18n?.allTasksList || 'Tasks',
	};

	const state = {
		tasks: [],
		loading: true,
		meta: { categories: {}, users: [], assignable_posts: [] },
		selectedPostId: Number( config.postId ) || 0,
	};

	function getAssignablePosts() {
		return state.meta.assignable_posts?.length
			? state.meta.assignable_posts
			: state.meta.posts || [];
	}

	function resolvePostId() {
		if ( hasPageContext ) {
			return Number( config.postId );
		}

		if ( state.selectedPostId > 0 ) {
			return state.selectedPostId;
		}

		const posts = getAssignablePosts();
		return posts[ 0 ]?.id ? Number( posts[ 0 ].id ) : 0;
	}

	function resolveStoragePostId() {
		if ( hasPageContext ) {
			return Number( config.postId );
		}

		if ( Number( config.storagePostId ) > 0 ) {
			return Number( config.storagePostId );
		}

		return resolvePostId();
	}

	function resolveContextUrl( postId = resolvePostId() ) {
		if ( hasPageContext && config.pageUrl ) {
			return config.pageUrl;
		}

		if ( hasAdminScreenContext ) {
			return adminScreenUrl;
		}

		const post = getAssignablePosts().find(
			( row ) => Number( row.id ) === Number( postId )
		);

		return post?.view_url || '';
	}

	function resolvePostUrl( postId = resolvePostId() ) {
		return resolveContextUrl( postId );
	}

	function taskMatchesCurrentScreen( task ) {
		if ( ! hasAdminScreenContext ) {
			return true;
		}

		if ( adminScreenKey && task.admin_screen ) {
			return task.admin_screen === adminScreenKey;
		}

		if ( ! adminScreenUrl ) {
			return false;
		}

		const taskUrl = normalizeContextUrl( task.context_url );
		const screenUrl = normalizeContextUrl( adminScreenUrl );

		return Boolean( taskUrl ) && taskUrl === screenUrl;
	}

	function getScreenTasks() {
		return state.tasks.filter( taskMatchesCurrentScreen );
	}

	function getMarkerTasks() {
		return getScreenTasks().filter(
			( task ) =>
				task.anchor ||
				task.selector ||
				task.block_name ||
				task.block_index === 0 ||
				( typeof task.block_index === 'number' && task.block_index > 0 )
		);
	}

	function taskSource() {
		if ( config.context === 'editor' ) {
			return 'editor';
		}

		if ( isAdminOverlay ) {
			return 'admin';
		}

		return 'frontend';
	}

	const fab =
		document.getElementById( 'forwp-todo-fab-trigger' ) ||
		root.querySelector( '.forwp-todo-fab' );

	if ( ! fab ) {
		return;
	}

	normalizeFabDom( root );

	const stackEl = document.createElement( 'div' );
	stackEl.className = 'forwp-todo-stack';
	stackEl.hidden = true;

	const panelEl = document.createElement( 'div' );
	panelEl.className = 'forwp-todo-panel';
	panelEl.hidden = true;

	const composerEl = document.createElement( 'div' );
	composerEl.className = 'forwp-todo-composer';
	composerEl.hidden = true;

	stackEl.append( panelEl, composerEl );
	root.append( stackEl );

	function syncStackVisibility() {
		const visible =
			! panelEl.hidden || ! composerEl.hidden;
		stackEl.hidden = ! visible;
	}

	function openCount() {
		return getScreenTasks().filter( ( t ) => t.is_open ).length;
	}

	function updateBadge() {
		const badge = fab.querySelector( '.forwp-todo-fab__badge' );
		if ( ! badge ) {
			return;
		}
		const count = openCount();
		badge.textContent = count > 0 ? String( count ) : '';
		badge.hidden = count <= 0;
	}

	function updateFabChrome() {
		const inPin = pinPicker?.isActive?.() ?? false;
		const tooltip =
			document.getElementById( 'forwp-todo-fab-tooltip' ) ||
			root.querySelector( '.forwp-todo-fab__tooltip' );

		fab.classList.toggle( 'is-pin-mode', inPin );
		fab.classList.toggle( 'is-active', panel.isOpen() && ! inPin );

		if ( inPin ) {
			fab.setAttribute(
				'aria-label',
				config.i18n?.exitPin || 'Exit selection mode'
			);
			if ( tooltip ) {
				tooltip.textContent =
					config.i18n?.fabTooltipPin || 'Cancel selection (Esc)';
			}
			return;
		}

		const fabLabel = hasPageContext || hasAdminScreenContext
			? config.i18n?.fabLabel || 'Page notes'
			: config.i18n?.fabLabelAdmin ||
				config.i18n?.allTasksList ||
				'Tasks';

		fab.setAttribute( 'aria-label', fabLabel );
		if ( tooltip ) {
			tooltip.textContent =
				config.i18n?.fabTooltip ||
				'Page notes — open list or pin to a section';
		}
	}

	function openPanel() {
		if ( canPinLocally ) {
			stopPinMode();
		}
		panel.toggle( true );
		updateFabChrome();
	}

	function closePanel() {
		panel.toggle( false );
		updateFabChrome();
	}

	function startPinMode() {
		if ( canPinLocally && pinPicker ) {
			panel.toggle( false );
			composer.close?.();
			pinPicker.activate();
			updateFabChrome();
		}
	}

	function stopPinMode( options = {} ) {
		const { notifyCancel = false } = options;

		if ( pinPicker?.isActive?.() ) {
			pinPicker.deactivate( { notifyCancel } );
		} else {
			pinPicker?.clearHighlight?.();
		}
		updateFabChrome();
	}

	let markersRef = null;
	let pinPicker = null;

	const thread = createThread( {
		api,
		i18n: panelI18n,
		currentUser: config.currentUser,
		onOpenPanel: ( taskId ) => {
			openPanel();
			panel.focusTask( taskId );
			markersRef?.focusTask( taskId );
		},
		onFocusTask: ( taskId ) => markersRef?.focusTask( taskId ),
	} );

	if ( canPinLocally ) {
		markersRef = createMarkers( {
			i18n: panelI18n,
			getTasks: () => getMarkerTasks(),
			onMarkerClick: ( taskId, anchor ) => {
				thread.open( taskId, anchor );
				markersRef.focusTask( taskId );
			},
		} );
	}

	const markers = markersRef;

	const panel = createPanel( panelEl, {
		i18n: panelI18n,
		getTasks: () =>
			hasAdminScreenContext ? getScreenTasks() : state.tasks,
		getMeta: () => state.meta,
		isLoading: () => state.loading,
		getPinActive: () => pinPicker?.isActive?.() ?? false,
		onStartPin: () => startPinMode(),
		onQuickAdd: ( content ) => createQuickTask( content ),
		onUpdateTask: ( id, payload ) => updateTask( id, payload ),
		onDeleteTask: ( id ) => deleteTask( id ),
		onClose: () => {
			stopPinMode();
			updateFabChrome();
		},
		onFocusTask: ( taskId ) => markers?.focusTask( taskId ),
		onVisibilityChange: syncStackVisibility,
		getAllTasksUrl: () => config.allTasksUrl || '',
		showPin: canPinLocally || isAdminOverlay,
		showQuickAdd: hasPageContext || isAdminOverlay,
		showPostPicker,
		getPostPickerPosts: () => getAssignablePosts(),
		getPostPickerValue: () => resolvePostId(),
		onPostPickerChange: ( postId ) => {
			state.selectedPostId = postId;
		},
		sortScope,
		onReorder: async ( taskIds ) => {
			if ( ! taskIds.length ) {
				return;
			}

			await api( 'forwp-todo/v1/tasks/reorder', {
				method: 'POST',
				body: JSON.stringify( {
					scope: sortScope,
					task_ids: taskIds,
				} ),
			} );
			state.tasks = state.tasks.map( ( task ) => {
				const index = taskIds.indexOf( task.id );
				if ( index === -1 ) {
					return task;
				}

				return {
					...task,
					sort_map: {
						...( task.sort_map || {} ),
						[ sortScope ]: index,
					},
				};
			} );
			if ( panel.isOpen() ) {
				panel.render( { soft: true } );
			}
		},
	} );

	const composer = canPinLocally
		? createComposer( composerEl, {
				i18n: panelI18n,
				getMeta: () => state.meta,
				onSave: ( draft ) => createTask( draft ),
				onCancel: () => {
					openPanel();
				},
				onOpen: syncStackVisibility,
				onClose: () => {
					pinPicker?.clearHighlight?.();
					syncStackVisibility();
				},
		  } )
		: null;

	if ( canPinLocally ) {
		pinPicker = createPinPicker( {
			i18n: panelI18n,
			onSelect: async ( pin ) => {
				await loadMeta();
				updateFabChrome();
				panel.toggle( true );
				composer.open( pin );
			},
			onCancel: () => {
				updateFabChrome();
			},
		} );
	}

	async function loadMeta() {
		try {
			state.meta = await api( 'forwp-todo/v1/meta' );
			if ( ! state.selectedPostId ) {
				const posts = getAssignablePosts();
				if ( posts[ 0 ]?.id ) {
					state.selectedPostId = Number( posts[ 0 ].id );
				}
			}
			if ( panel.isOpen() ) {
				panel.render( { soft: true } );
			}
		} catch ( e ) {
			// eslint-disable-next-line no-console
			console.error( e );
		}
	}

	async function loadTasks( options = {} ) {
		const silent = options.silent === true;
		const hadTasks = state.tasks.length > 0;

		if ( ! silent ) {
			state.loading = true;
			if ( panel.isOpen() ) {
				panel.render();
			}
		}

		try {
			const query = hasPageContext
				? `forwp-todo/v1/tasks?post_id=${ config.postId }&per_page=100`
				: 'forwp-todo/v1/tasks?per_page=100';
			const data = await api( query );
			state.tasks = data.items || [];
			updateBadge();
			if ( ! silent ) {
				markers?.refresh?.();
			}
		} catch ( e ) {
			// eslint-disable-next-line no-console
			console.error( e );
		} finally {
			state.loading = false;
			if ( panel.isOpen() ) {
				panel.render( { soft: silent || hadTasks } );
			}
		}
	}

	async function createQuickTask( content ) {
		const postId = resolveStoragePostId();
		const pageUrl = resolveContextUrl( postId );

		if ( ! postId || ! content?.trim() ) {
			return;
		}

		const payload = {
			content,
			post_id: postId,
			context_url: pageUrl,
			assignee: 0,
			category: 'content',
			due_date: '',
			source: taskSource(),
			viewport: detectViewport(),
		};

		if ( hasAdminScreenContext && adminScreenKey ) {
			payload.admin_screen = adminScreenKey;
		}

		await api( 'forwp-todo/v1/tasks', {
			method: 'POST',
			body: JSON.stringify( payload ),
		} );
		await loadTasks( { silent: panel.isOpen() } );
	}

	async function createTask( draft ) {
		const postId = resolveStoragePostId();
		const pageUrl = resolveContextUrl( postId );

		const payload = {
			content: draft.content,
			post_id: postId,
			context_url: pageUrl,
			assignee: 0,
			due_date: draft.due_date || '',
			source: taskSource(),
			viewport: detectViewport(),
			anchor: draft.anchor,
			selector: draft.fallbackSelector || draft.selector,
		};

		if ( hasAdminScreenContext && adminScreenKey ) {
			payload.admin_screen = adminScreenKey;
		}

		if ( draft.targetMode === 'assignee' && draft.assignee ) {
			payload.assignee = draft.assignee;
		} else if ( draft.category ) {
			payload.category = draft.category;
		}

		await api( 'forwp-todo/v1/tasks', {
			method: 'POST',
			body: JSON.stringify( payload ),
		} );
		await loadTasks( { silent: panel.isOpen() } );
		openPanel();
	}

	async function updateTask( id, payload ) {
		await api( `forwp-todo/v1/tasks/${ id }`, {
			method: 'POST',
			body: JSON.stringify( payload ),
		} );
		await loadTasks( { silent: true } );
	}

	async function deleteTask( id ) {
		await api( `forwp-todo/v1/tasks/${ id }`, {
			method: 'DELETE',
		} );
		thread.close?.();
		await loadTasks( { silent: panel.isOpen() } );
	}

	fab.addEventListener( 'click', ( event ) => {
		event.preventDefault();
		event.stopPropagation();

		if ( pinPicker?.isActive?.() ) {
			stopPinMode();
			openPanel();
			return;
		}

		if ( panel.isOpen() ) {
			closePanel();
			return;
		}

		openPanel();
	} );

	document.addEventListener( 'click', ( event ) => {
		const trigger = event.target.closest( 'a[href="#forwp-todo-open"]' );
		if ( ! trigger ) {
			return;
		}
		event.preventDefault();
		openPanel();
	} );

	if ( window.location.hash === '#forwp-todo-open' ) {
		openPanel();
	}

	updateFabChrome();
	loadMeta();
	loadTasks();

	if (
		canPinLocally &&
		new URLSearchParams( window.location.search ).get( 'forwp-todo-pin' ) ===
			'1'
	) {
		requestAnimationFrame( () => {
			startPinMode();
		} );
	}
}

/**
 * Page notes panel — Google Tasks–style list.
 */

import { resolveTaskTarget } from '../frontend/lib/dom';
import {
	TASK_SCOPE_ALL,
	TASK_SCOPE_ASSIGNED,
	TASK_SCOPE_AUTHORED,
	filterTasksByScope,
} from './task-scope';

function formatDueDate( value ) {
	if ( ! value ) {
		return '';
	}

	const date = new Date( `${ value }T00:00:00` );
	if ( Number.isNaN( date.getTime() ) ) {
		return value;
	}

	return date.toLocaleDateString( undefined, {
		month: 'short',
		day: 'numeric',
	} );
}

function isoDateOffset( days ) {
	const date = new Date();
	date.setDate( date.getDate() + days );
	return date.toISOString().slice( 0, 10 );
}

function closeMenus() {
	document.querySelectorAll( '.forwp-todo-panel__menu' ).forEach( ( menu ) => {
		menu.hidden = true;
		menu.style.top = '';
		menu.style.left = '';
		menu.style.maxHeight = '';
	} );
}

const VIEWPORT_MARGIN = 8;
const MENU_MAX_HEIGHT = 280;

function positionFloatingMenu( menu, anchor, options = {} ) {
	const { align = 'end' } = options;
	menu.hidden = false;
	menu.style.maxHeight = '';

	const rect = anchor.getBoundingClientRect();
	const width = menu.offsetWidth || 148;
	const measuredHeight = menu.offsetHeight || MENU_MAX_HEIGHT;
	const spaceBelow = window.innerHeight - rect.bottom - VIEWPORT_MARGIN;
	const spaceAbove = rect.top - VIEWPORT_MARGIN;

	let top;
	if ( spaceBelow >= Math.min( measuredHeight, 160 ) || spaceBelow >= spaceAbove ) {
		top = rect.bottom + 4;
	} else {
		top = rect.top - Math.min( measuredHeight, MENU_MAX_HEIGHT ) - 4;
	}

	top = Math.max(
		VIEWPORT_MARGIN,
		Math.min( top, window.innerHeight - VIEWPORT_MARGIN - 120 )
	);
	menu.style.maxHeight = `${ Math.min(
		MENU_MAX_HEIGHT,
		window.innerHeight - top - VIEWPORT_MARGIN
	) }px`;

	let left = align === 'end' ? rect.right - width : rect.left;
	left = Math.max(
		VIEWPORT_MARGIN,
		Math.min( left, window.innerWidth - width - VIEWPORT_MARGIN )
	);

	menu.style.top = `${ top }px`;
	menu.style.left = `${ left }px`;
}

function openMenuAt( menu, anchor ) {
	positionFloatingMenu( menu, anchor, { align: 'end' } );
}

function plainText( html ) {
	const node = document.createElement( 'div' );
	node.innerHTML = html;
	return ( node.textContent || html || '' ).trim();
}

function sortTasksForScope( tasks, scope ) {
	if ( ! scope ) {
		return tasks;
	}

	return [ ...tasks ].sort( ( left, right ) => {
		const leftOrder = resolveSortOrder( left, scope );
		const rightOrder = resolveSortOrder( right, scope );

		if ( leftOrder !== rightOrder ) {
			return leftOrder - rightOrder;
		}

		return ( right.created || '' ).localeCompare( left.created || '' );
	} );
}

/**
 * Resolve manual sort index for a scope, including legacy kanban keys.
 *
 * @param {object} task
 * @param {string} scope
 * @return {number}
 */
export function resolveSortOrder( task, scope ) {
	if ( ! scope || ! task?.sort_map ) {
		return Number.MAX_SAFE_INTEGER;
	}

	if ( typeof task.sort_map[ scope ] === 'number' ) {
		return task.sort_map[ scope ];
	}

	const pageMatch = scope.match( /^page:(\d+)$/ );
	if ( pageMatch ) {
		const legacyKey = `column:post-${ pageMatch[ 1 ] }`;
		if ( typeof task.sort_map[ legacyKey ] === 'number' ) {
			return task.sort_map[ legacyKey ];
		}
	}

	const columnPostMatch = scope.match( /^column:post-(\d+)$/ );
	if ( columnPostMatch ) {
		const pageKey = `page:${ columnPostMatch[ 1 ] }`;
		if ( typeof task.sort_map[ pageKey ] === 'number' ) {
			return task.sort_map[ pageKey ];
		}
	}

	return Number.MAX_SAFE_INTEGER;
}

function bindSortableList( list, onReorder ) {
	if ( ! list || list.dataset.forwpTodoSortBound === '1' ) {
		return;
	}

	list.dataset.forwpTodoSortBound = '1';
	let draggedId = null;

	list.addEventListener( 'dragstart', ( event ) => {
		const handle = event.target.closest( '.forwp-todo-panel__drag-handle' );
		if ( ! handle ) {
			return;
		}

		const item = handle.closest( '.forwp-todo-panel__item' );
		if ( ! item ) {
			return;
		}

		draggedId = item.dataset.taskId || null;
		item.classList.add( 'is-dragging' );
		event.dataTransfer.effectAllowed = 'move';
		event.dataTransfer.setData( 'text/plain', draggedId || '' );
	} );

	list.addEventListener( 'dragend', ( event ) => {
		const item = event.target.closest( '.forwp-todo-panel__item' );
		item?.classList.remove( 'is-dragging' );
		draggedId = null;
	} );

	list.addEventListener( 'dragover', ( event ) => {
		if ( ! draggedId ) {
			return;
		}

		event.preventDefault();
		const item = event.target.closest( '.forwp-todo-panel__item' );
		if ( ! item || item.dataset.taskId === draggedId ) {
			return;
		}

		const dragging = list.querySelector(
			`.forwp-todo-panel__item[data-task-id="${ draggedId }"]`
		);
		if ( ! dragging ) {
			return;
		}

		const rect = item.getBoundingClientRect();
		if ( event.clientY > rect.top + rect.height / 2 ) {
			item.after( dragging );
		} else {
			item.before( dragging );
		}
	} );

	list.addEventListener( 'drop', ( event ) => {
		event.preventDefault();
		if ( ! draggedId || ! onReorder ) {
			return;
		}

		const ids = [ ...list.querySelectorAll( ':scope > .forwp-todo-panel__item' ) ]
			.map( ( element ) => parseInt( element.dataset.taskId || '0', 10 ) )
			.filter( ( id ) => id > 0 );

		onReorder( ids );
	} );
}

function createIcon( iconClass ) {
	const span = document.createElement( 'span' );
	span.className = `forwp-todo-panel__icon ${ iconClass }`;
	span.setAttribute( 'aria-hidden', 'true' );
	return span;
}

function createIconButton( className, ariaLabel, iconClass ) {
	const btn = document.createElement( 'button' );
	btn.type = 'button';
	btn.className = className;
	btn.setAttribute( 'aria-label', ariaLabel );
	btn.append( createIcon( iconClass ) );
	return btn;
}

function createMenuItem( item, onClose ) {
	const btn = document.createElement( 'button' );
	btn.type = 'button';
	btn.className = 'forwp-todo-panel__menu-item';
	if ( item.danger ) {
		btn.classList.add( 'forwp-todo-panel__menu-item--danger' );
	}
	btn.setAttribute( 'role', 'menuitem' );
	btn.textContent = item.label;
	btn.addEventListener( 'click', ( event ) => {
		event.stopPropagation();
		onClose();
		item.onPick();
	} );
	return btn;
}

function positionSubmenu( submenu, anchor ) {
	submenu.hidden = false;
	submenu.style.maxHeight = '';

	const rect = anchor.getBoundingClientRect();
	const width = submenu.offsetWidth || 160;
	const measuredHeight = submenu.offsetHeight || MENU_MAX_HEIGHT;

	let left = rect.right + 4;
	if ( left + width > window.innerWidth - VIEWPORT_MARGIN ) {
		left = rect.left - width - 4;
	}
	left = Math.max(
		VIEWPORT_MARGIN,
		Math.min( left, window.innerWidth - width - VIEWPORT_MARGIN )
	);

	let top = rect.top;
	if ( top + measuredHeight > window.innerHeight - VIEWPORT_MARGIN ) {
		top =
			window.innerHeight -
			VIEWPORT_MARGIN -
			Math.min( measuredHeight, MENU_MAX_HEIGHT );
	}
	top = Math.max( VIEWPORT_MARGIN, top );
	submenu.style.maxHeight = `${ Math.min(
		MENU_MAX_HEIGHT,
		window.innerHeight - top - VIEWPORT_MARGIN
	) }px`;

	submenu.style.top = `${ top }px`;
	submenu.style.left = `${ left }px`;
}

function createMenu( items ) {
	const menu = document.createElement( 'div' );
	menu.className = 'forwp-todo-panel__menu';
	menu.hidden = true;
	menu.setAttribute( 'role', 'menu' );

	const closeAll = () => {
		closeMenus();
	};

	items.forEach( ( item ) => {
		if ( item.type === 'separator' ) {
			const sep = document.createElement( 'div' );
			sep.className = 'forwp-todo-panel__menu-sep';
			sep.setAttribute( 'role', 'separator' );
			menu.append( sep );
			return;
		}

		if ( item.children?.length ) {
			const group = document.createElement( 'div' );
			group.className = 'forwp-todo-panel__menu-group';

			const trigger = document.createElement( 'button' );
			trigger.type = 'button';
			trigger.className =
				'forwp-todo-panel__menu-item forwp-todo-panel__menu-item--sub';
			trigger.setAttribute( 'role', 'menuitem' );
			trigger.setAttribute( 'aria-haspopup', 'true' );
			trigger.textContent = item.label;

			const submenu = document.createElement( 'div' );
			submenu.className = 'forwp-todo-panel__menu forwp-todo-panel__menu--sub';
			submenu.hidden = true;
			submenu.setAttribute( 'role', 'menu' );

			item.children.forEach( ( child ) => {
				submenu.append( createMenuItem( child, closeAll ) );
			} );

			trigger.addEventListener( 'click', ( event ) => {
				event.preventDefault();
				event.stopPropagation();
				document.querySelectorAll( '.forwp-todo-panel__menu--sub' ).forEach( ( sub ) => {
					if ( sub !== submenu ) {
						sub.hidden = true;
					}
				} );
				if ( submenu.hidden ) {
					document.body.append( submenu );
					positionSubmenu( submenu, trigger );
				} else {
					submenu.hidden = true;
				}
			} );

			group.append( trigger, submenu );
			menu.append( group );
			return;
		}

		menu.append( createMenuItem( item, closeAll ) );
	} );

	return menu;
}

function buildSelectOptions( items, selectedValue ) {
	return items
		.map( ( item ) => {
			const selected =
				String( item.value ) === String( selectedValue ) ? ' selected' : '';
			return `<option value="${ item.value }"${ selected }>${ item.label }</option>`;
		} )
		.join( '' );
}

/**
 * @param {HTMLElement} mount
 * @param {object}      options
 * @return {{ render: Function, toggle: Function, isOpen: Function, focusTask: Function, startQuickAdd: Function }}
 */
export function createPanel(
	mount,
	{
		i18n,
		getTasks,
		getMeta,
		isLoading,
		onStartPin,
		onQuickAdd,
		onUpdateTask,
		onDeleteTask,
		onClose,
		onFocusTask,
		onVisibilityChange,
		variant = 'overlay',
		title: columnTitle = '',
		headerLink = '',
		pageViewUrl = '',
		showClose,
		showPin,
		splitCompleted,
		showScopeFilter,
		getAllTasksUrl,
		sortScope = '',
		onReorder,
		showQuickAdd,
		showPostPicker,
		getPostPickerPosts,
		getPostPickerValue,
		onPostPickerChange,
	}
) {
	const isColumn = variant === 'column';
	const shouldShowClose = showClose ?? ! isColumn;
	const shouldShowPin = showPin ?? ! isColumn;
	const shouldShowQuickAdd = showQuickAdd ?? ( isColumn || shouldShowPin );
	const shouldShowPostPicker = showPostPicker ?? false;
	const shouldSplitCompleted = splitCompleted ?? isColumn;
	const shouldShowScopeFilter = showScopeFilter ?? ! isColumn;

	let open = isColumn;
	let focusedId = 0;
	let expandedId = 0;
	let expandedFocus = 'default';
	let expandedOpenedId = 0;
	let flushExpandedSave = null;
	let editingId = 0;
	let quickAddActive = false;
	let completedOpen = false;
	let taskScope = TASK_SCOPE_ALL;
	let lastDoneCount = -1;

	function taskItemSnapshot( task, meta ) {
		return [
			task.id,
			task.is_open ? '1' : '0',
			plainText( task.content ),
			task.due_date || '',
			task.assignee || 0,
			task.category || '',
			task.anchor || '',
			task.selector || '',
			task.block_name || '',
			task.block_index ?? '',
			taskMetaLine( task, meta ),
		].join( '|' );
	}

	function patchTaskItemState( li, task ) {
		li.classList.toggle( 'is-open', task.is_open );
		li.classList.toggle( 'is-done', ! task.is_open );
		li.classList.toggle( 'is-focused', focusedId === task.id );
		li.classList.toggle( 'is-expanded', expandedId === task.id );
	}

	function syncOpenTaskList( list, openTasks, meta ) {
		const scroll = mount.querySelector( '.forwp-todo-panel__scroll' );
		const scrollTop = scroll?.scrollTop ?? 0;
		const existing = new Map();

		list.querySelectorAll( ':scope > .forwp-todo-panel__item' ).forEach( ( li ) => {
			existing.set( parseInt( li.dataset.taskId, 10 ), li );
		} );

		const nextNodes = openTasks.map( ( task ) => {
			const snapshot = taskItemSnapshot( task, meta );
			let li = existing.get( task.id );

			if (
				li &&
				li.dataset.taskSnap === snapshot &&
				expandedId !== task.id &&
				editingId !== task.id
			) {
				patchTaskItemState( li, task );
				existing.delete( task.id );
				return li;
			}

			if ( li ) {
				existing.delete( task.id );
			}

			li = renderTaskItem( task, meta );
			li.dataset.taskSnap = snapshot;
			return li;
		} );

		existing.forEach( ( li ) => li.remove() );

		nextNodes.forEach( ( node, index ) => {
			const current = list.children[ index ];
			if ( current !== node ) {
				list.insertBefore( node, current || null );
			}
		} );

		if ( scroll ) {
			scroll.scrollTop = scrollTop;
		}
	}

	function canSoftSync( loading, openTasks, doneTasks ) {
		if ( loading ) {
			return false;
		}

		if ( quickAddActive || expandedId || editingId ) {
			return false;
		}

		if ( ! mount.querySelector( '.forwp-todo-panel__head' ) ) {
			return false;
		}

		const scroll = mount.querySelector( '.forwp-todo-panel__scroll' );
		if ( ! scroll ) {
			return false;
		}

		if ( openTasks.length && ! scroll.querySelector( '.forwp-todo-panel__list' ) ) {
			return false;
		}

		if ( ! openTasks.length && scroll.querySelector( '.forwp-todo-panel__list' ) ) {
			return false;
		}

		if ( shouldSplitCompleted && doneTasks.length !== lastDoneCount ) {
			return false;
		}

		return true;
	}

	function toggle( next ) {
		if ( isColumn ) {
			open = true;
			mount.hidden = false;
			render();
			return true;
		}

		open = typeof next === 'boolean' ? next : ! open;
		mount.hidden = ! open;
		if ( open ) {
			render();
		} else {
			expandedId = 0;
			expandedFocus = 'default';
			expandedOpenedId = 0;
			editingId = 0;
			quickAddActive = false;
		}
		onVisibilityChange?.();
		return open;
	}

	function isOpen() {
		return open;
	}

	function focusTask( taskId ) {
		focusedId = taskId;
		onFocusTask?.( taskId );
		if ( ! open ) {
			toggle( true );
		} else {
			render();
		}
	}

	function startQuickAdd() {
		quickAddActive = true;
		expandedId = 0;
		editingId = 0;
		if ( ! open ) {
			toggle( true );
			return;
		}
		render();
		requestAnimationFrame( () => {
			mount.querySelector( '.forwp-todo-panel__quick-add-input' )?.focus();
		} );
	}

	function scrollToTask( task ) {
		const target = resolveTaskTarget( task );
		if ( ! target ) {
			return;
		}

		target.scrollIntoView( {
			behavior: 'smooth',
			block: 'center',
		} );
		focusedId = task.id;
		onFocusTask?.( task.id );
	}

	function taskHasPinTarget( task ) {
		const hasBlockIndex =
			task.block_index === 0 ||
			( typeof task.block_index === 'number' && task.block_index > 0 );

		return Boolean(
			task.anchor ||
				task.selector ||
				task.block_name ||
				hasBlockIndex
		);
	}

	function taskMetaLine( task, meta ) {
		const parts = [];

		if ( task.author_name ) {
			parts.push(
				`${ i18n?.createdBy || 'By' } ${ task.author_name }`
			);
		}

		if ( ( task.assignee || 0 ) > 0 && task.assignee_name ) {
			if (
				! task.author_name ||
				task.author_name !== task.assignee_name
			) {
				parts.push(
					`${ i18n?.assignedTo || '→' } ${ task.assignee_name }`
				);
			}
		} else if ( task.category ) {
			const categoryLabel =
				meta.categories?.[ task.category ] || task.category;
			if ( categoryLabel && task.category !== 'content' ) {
				parts.push( categoryLabel );
			}
		}

		return parts.join( ' · ' );
	}

	function bindInlineEdit( input, task ) {
		let submitted = false;

		const commit = () => {
			if ( submitted ) {
				return;
			}
			submitted = true;

			const value = input.value.trim();
			editingId = 0;
			if ( value && value !== plainText( task.content ) ) {
				onUpdateTask( task.id, { content: value } );
				return;
			}
			render();
		};

		input.addEventListener( 'blur', commit );
		input.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Enter' ) {
				event.preventDefault();
				commit();
			}
			if ( event.key === 'Escape' ) {
				submitted = true;
				editingId = 0;
				render();
			}
		} );
	}

	function openExpanded( taskId, focus = 'default' ) {
		expandedId = taskId;
		expandedFocus = focus;
		expandedOpenedId = taskId;
		editingId = 0;
		render();
	}

	function closeExpanded() {
		if ( flushExpandedSave ) {
			flushExpandedSave();
			flushExpandedSave = null;
		}
		expandedId = 0;
		expandedFocus = 'default';
		expandedOpenedId = 0;
		render();
	}

	function renderExpandedEdit( task, meta, container, focus = 'default' ) {
		const categories = meta.categories || {};
		const hasAssignee = ( task.assignee || 0 ) > 0;
		let targetMode = hasAssignee ? 'assignee' : 'department';
		if ( focus === 'assignee' ) {
			targetMode = 'assignee';
		} else if ( focus === 'department' ) {
			targetMode = 'department';
		}

		const departmentItems = [
			{
				value: '',
				label: i18n?.selectDepartment || 'Select department',
			},
			...Object.entries( categories ).map( ( [ value, label ] ) => ( {
				value,
				label,
			} ) ),
		];

		const assigneeItems = [
			{
				value: 0,
				label: i18n?.selectAssignee || 'Select assignee',
			},
			...( meta.users || [] ).map( ( user ) => ( {
				value: user.id,
				label: user.name,
			} ) ),
		];

		container.innerHTML = `
			<label class="forwp-todo-panel__edit-field">
				<span class="forwp-todo-panel__edit-label">${ i18n?.shortNote || 'Note' }</span>
				<input type="text" class="forwp-todo-panel__edit-input" value="${ plainText( task.content ).replace( /"/g, '&quot;' ) }" />
			</label>
			<div class="forwp-todo-panel__date-chips">
				<button type="button" class="forwp-todo-panel__date-chip" data-date="${ isoDateOffset( 0 ) }">${ i18n?.today || 'Today' }</button>
				<button type="button" class="forwp-todo-panel__date-chip" data-date="${ isoDateOffset( 1 ) }">${ i18n?.tomorrow || 'Tomorrow' }</button>
				<label class="forwp-todo-panel__date-chip forwp-todo-panel__date-chip--picker">
					<span class="forwp-todo-panel__icon forwp-todo-panel__icon--date" aria-hidden="true"></span>
					<input type="date" class="forwp-todo-panel__date-picker" value="${ task.due_date || '' }" />
				</label>
			</div>
			<div class="forwp-todo-panel__edit-map">
				<div class="forwp-todo-panel__mode" role="tablist">
					<button type="button" class="forwp-todo-panel__mode-btn ${ targetMode === 'department' ? 'is-active' : '' }" data-mode="department">${ i18n?.department || 'Department' }</button>
					<button type="button" class="forwp-todo-panel__mode-btn ${ targetMode === 'assignee' ? 'is-active' : '' }" data-mode="assignee">${ i18n?.assignee || 'Assignee' }</button>
				</div>
				<div class="forwp-todo-panel__target-slot ${ targetMode === 'assignee' ? 'is-assignee' : 'is-department' }">
					<select class="forwp-todo-panel__edit-select" name="category">${ buildSelectOptions( departmentItems, hasAssignee ? '' : task.category ) }</select>
					<select class="forwp-todo-panel__edit-select" name="assignee">${ buildSelectOptions( assigneeItems, task.assignee || 0 ) }</select>
				</div>
			</div>
		`;

		let mode = targetMode;
		const titleInput = container.querySelector( '.forwp-todo-panel__edit-input' );
		const categorySelect = container.querySelector( '[name="category"]' );
		const assigneeSelect = container.querySelector( '[name="assignee"]' );
		const targetSlot = container.querySelector( '.forwp-todo-panel__target-slot' );
		const datePicker = container.querySelector( '.forwp-todo-panel__date-picker' );
		let dueDate = task.due_date || '';
		let saveTimer = null;

		function markActiveDateChip( activeDate ) {
			container.querySelectorAll( '.forwp-todo-panel__date-chip[data-date]' ).forEach( ( chip ) => {
				chip.classList.toggle( 'is-active', chip.dataset.date === activeDate );
			} );
		}

		if ( dueDate ) {
			markActiveDateChip( dueDate );
		}

		function setMode( next ) {
			mode = next;
			container.querySelectorAll( '.forwp-todo-panel__mode-btn' ).forEach( ( btn ) => {
				btn.classList.toggle( 'is-active', btn.dataset.mode === next );
			} );
			targetSlot.classList.toggle( 'is-department', next === 'department' );
			targetSlot.classList.toggle( 'is-assignee', next === 'assignee' );
		}

		container.querySelectorAll( '.forwp-todo-panel__mode-btn' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => setMode( btn.dataset.mode ) );
		} );

		container.querySelectorAll( '.forwp-todo-panel__date-chip[data-date]' ).forEach( ( chip ) => {
			chip.addEventListener( 'mousedown', ( event ) => {
				event.preventDefault();
			} );
			chip.addEventListener( 'click', () => {
				dueDate = chip.dataset.date || '';
				datePicker.value = dueDate;
				markActiveDateChip( dueDate );
				scheduleSave();
			} );
		} );

		datePicker.addEventListener( 'change', () => {
			dueDate = datePicker.value;
			markActiveDateChip( '' );
			scheduleSave();
		} );

		function scheduleSave() {
			if ( saveTimer ) {
				clearTimeout( saveTimer );
			}
			saveTimer = setTimeout( () => {
				saveTimer = null;
				saveExpanded();
			}, 200 );
		}

		function saveExpanded() {
			const content = titleInput.value.trim();
			if ( ! content ) {
				return;
			}

			const payload = {
				content,
				due_date: dueDate,
				assignee: 0,
			};

			if ( mode === 'assignee' ) {
				payload.assignee = parseInt( assigneeSelect.value || '0', 10 ) || 0;
				if ( payload.assignee ) {
					payload.category = 'content';
				}
			} else {
				payload.category = categorySelect.value || 'content';
				payload.assignee = 0;
			}

			onUpdateTask( task.id, payload );
		}

		titleInput.addEventListener( 'blur', ( event ) => {
			const next = event.relatedTarget;
			if ( next && container.contains( next ) ) {
				return;
			}
			if ( saveTimer ) {
				clearTimeout( saveTimer );
				saveTimer = null;
			}
			saveExpanded();
		} );
		titleInput.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Enter' ) {
				event.preventDefault();
				if ( saveTimer ) {
					clearTimeout( saveTimer );
					saveTimer = null;
				}
				saveExpanded();
			}
		} );
		categorySelect.addEventListener( 'change', scheduleSave );
		assigneeSelect.addEventListener( 'change', scheduleSave );
		container.querySelectorAll( '.forwp-todo-panel__mode-btn' ).forEach( ( btn ) => {
			btn.addEventListener( 'mousedown', ( event ) => {
				event.preventDefault();
			} );
		} );

		if ( focus === 'assignee' ) {
			setMode( 'assignee' );
		} else if ( focus === 'department' ) {
			setMode( 'department' );
		}

		flushExpandedSave = () => {
			if ( saveTimer ) {
				clearTimeout( saveTimer );
				saveTimer = null;
			}
			saveExpanded();
		};

		const editHead = document.createElement( 'div' );
		editHead.className = 'forwp-todo-panel__edit-head';
		const doneBtn = document.createElement( 'button' );
		doneBtn.type = 'button';
		doneBtn.className = 'forwp-todo-panel__edit-done';
		doneBtn.textContent = i18n?.editDone || 'Done';
		doneBtn.addEventListener( 'mousedown', ( event ) => {
			event.preventDefault();
		} );
		doneBtn.addEventListener( 'click', ( event ) => {
			event.preventDefault();
			event.stopPropagation();
			closeExpanded();
		} );
		editHead.append( doneBtn );
		container.prepend( editHead );

		container.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Escape' ) {
				event.preventDefault();
				event.stopPropagation();
				closeExpanded();
			}
		} );
	}

	function renderTaskItem( task, meta ) {
		const li = document.createElement( 'li' );
		li.className = [
			'forwp-todo-panel__item',
			task.is_open ? 'is-open' : 'is-done',
			focusedId === task.id ? 'is-focused' : '',
			expandedId === task.id ? 'is-expanded' : '',
		]
			.filter( Boolean )
			.join( ' ' );
		li.dataset.taskId = String( task.id );

		const metaLine = taskMetaLine( task, meta );
		if ( metaLine ) {
			li.dataset.todoTip = `${ plainText( task.content ) }\n${ metaLine }`;
		} else {
			li.dataset.todoTip = plainText( task.content );
		}

		const row = document.createElement( 'div' );
		row.className = 'forwp-todo-panel__row';

		if ( sortScope ) {
			const dragHandle = document.createElement( 'button' );
			dragHandle.type = 'button';
			dragHandle.className = 'forwp-todo-panel__drag-handle';
			dragHandle.draggable = true;
			dragHandle.setAttribute(
				'aria-label',
				i18n?.dragToReorder || 'Drag to reorder'
			);
			dragHandle.addEventListener( 'mousedown', ( event ) => {
				event.stopPropagation();
			} );
			dragHandle.addEventListener( 'click', ( event ) => {
				event.preventDefault();
				event.stopPropagation();
			} );
			row.append( dragHandle );
		}

		const checkWrap = document.createElement( 'label' );
		checkWrap.className = 'forwp-todo-panel__check';
		checkWrap.setAttribute(
			'aria-label',
			task.is_open
				? i18n?.markDone || 'Mark as done'
				: i18n?.markOpen || 'Mark as open'
		);

		const checkbox = document.createElement( 'input' );
		checkbox.type = 'checkbox';
		checkbox.checked = ! task.is_open;
		checkbox.addEventListener( 'change', () => {
			onUpdateTask( task.id, {
				note_status: checkbox.checked ? 'resolved' : 'reopen',
			} );
		} );
		checkWrap.append( checkbox );

		const main = document.createElement( 'div' );
		main.className = 'forwp-todo-panel__main';

		if ( editingId === task.id ) {
			const input = document.createElement( 'input' );
			input.type = 'text';
			input.className = 'forwp-todo-panel__inline-input';
			input.value = plainText( task.content );
			bindInlineEdit( input, task );
			main.append( input );
			requestAnimationFrame( () => {
				input.focus();
				input.select();
			} );
		} else {
			const text = document.createElement( 'p' );
			text.className = 'forwp-todo-panel__text';
			text.textContent = plainText( task.content );
			let clickTimer = null;
			text.addEventListener( 'click', ( event ) => {
				event.stopPropagation();
				if ( clickTimer ) {
					clearTimeout( clickTimer );
				}
				clickTimer = setTimeout( () => {
					clickTimer = null;
					editingId = task.id;
					expandedId = 0;
					render();
				}, 220 );
			} );
			text.addEventListener( 'dblclick', ( event ) => {
				event.preventDefault();
				event.stopPropagation();
				if ( clickTimer ) {
					clearTimeout( clickTimer );
					clickTimer = null;
				}
				editingId = 0;
				expandedId = task.id;
				expandedFocus = 'default';
				expandedOpenedId = task.id;
				render();
			} );
			main.append( text );
		}

		if ( ( metaLine || task.due_date ) && editingId !== task.id ) {
			const sub = document.createElement( 'p' );
			sub.className = 'forwp-todo-panel__meta-line';
			if ( task.due_date ) {
				const badge = document.createElement( 'span' );
				badge.className = 'forwp-todo-panel__due-badge';
				badge.textContent = formatDueDate( task.due_date );
				sub.append( badge );
				if ( metaLine ) {
					const rest = document.createElement( 'span' );
					rest.className = 'forwp-todo-panel__meta-rest';
					rest.textContent = metaLine;
					sub.append( document.createTextNode( ' ' ), rest );
				}
			} else {
				sub.textContent = metaLine;
			}
			main.append( sub );
		}

		const actions = document.createElement( 'div' );
		actions.className = 'forwp-todo-panel__row-actions';

		if ( taskHasPinTarget( task ) ) {
			const anchorBtn = createIconButton(
				'forwp-todo-panel__icon-btn forwp-todo-panel__anchor',
				i18n?.jumpTo || 'Jump to section',
				'forwp-todo-panel__icon--anchor'
			);
			anchorBtn.addEventListener( 'click', ( event ) => {
				event.preventDefault();
				event.stopPropagation();
				scrollToTask( task );
				if ( open ) {
					patchTaskItemState(
						li,
						task
					);
					mount
						.querySelectorAll( '.forwp-todo-panel__item' )
						.forEach( ( item ) => {
							item.classList.toggle(
								'is-focused',
								parseInt( item.dataset.taskId, 10 ) === task.id
							);
						} );
				}
			} );
			actions.append( anchorBtn );
		}

		const menuBtn = createIconButton(
			'forwp-todo-panel__icon-btn forwp-todo-panel__more',
			i18n?.taskMenu || 'Task menu',
			'forwp-todo-panel__icon--more'
		);

		const assignChildren = [
			{
				label: i18n?.unassigned || 'Unassigned',
				onPick: () =>
					onUpdateTask( task.id, {
						assignee: 0,
					} ),
			},
			...( meta.users || [] ).map( ( user ) => ( {
				label: user.name,
				onPick: () =>
					onUpdateTask( task.id, {
						assignee: user.id,
						category: 'content',
					} ),
			} ) ),
			{
				label: i18n?.assignDepartment || 'Set department…',
				onPick: () => openExpanded( task.id, 'department' ),
			},
		];

		const dueChildren = [
			{
				label: i18n?.today || 'Today',
				onPick: () =>
					onUpdateTask( task.id, {
						due_date: isoDateOffset( 0 ),
					} ),
			},
			{
				label: i18n?.tomorrow || 'Tomorrow',
				onPick: () =>
					onUpdateTask( task.id, {
						due_date: isoDateOffset( 1 ),
					} ),
			},
			{
				label: i18n?.clearDueDate || 'Clear due date',
				onPick: () =>
					onUpdateTask( task.id, {
						due_date: '',
					} ),
			},
			{
				label: i18n?.pickDueDate || 'Pick date…',
				onPick: () => openExpanded( task.id, 'date' ),
			},
		];

		const menuItems = [
			{
				label: i18n?.editTask || 'Edit task',
				onPick: () => openExpanded( task.id, 'default' ),
			},
			{
				label: task.is_open
					? i18n?.markDone || 'Mark as done'
					: i18n?.markOpen || 'Mark as open',
				onPick: () =>
					onUpdateTask( task.id, {
						note_status: task.is_open ? 'resolved' : 'reopen',
					} ),
			},
			{
				label: i18n?.assign || 'Assign',
				children: assignChildren,
			},
			{
				label: i18n?.dueDate || 'Due date',
				children: dueChildren,
			},
			{ type: 'separator' },
			{
				label: i18n?.deleteTask || 'Delete task',
				danger: true,
				onPick: () => onDeleteTask?.( task.id ),
			},
		];

		const menu = createMenu( menuItems );
		menuBtn.addEventListener( 'click', ( event ) => {
			event.preventDefault();
			event.stopPropagation();
			const willOpen = menu.hidden;
			closeMenus();
			if ( willOpen ) {
				document.body.append( menu );
				requestAnimationFrame( () => {
					openMenuAt( menu, menuBtn );
				} );
			}
		} );
		menuBtn.append( menu );
		actions.append( menuBtn );

		row.append( checkWrap, main, actions );
		li.append( row );

		if ( expandedId === task.id ) {
			const edit = document.createElement( 'div' );
			edit.className = 'forwp-todo-panel__edit';
			const focus = expandedFocus;
			renderExpandedEdit( task, meta, edit, focus );
			li.append( edit );
			const shouldFocus = expandedOpenedId === task.id;
			if ( shouldFocus ) {
				requestAnimationFrame( () => {
					if ( focus === 'date' ) {
						const picker = edit.querySelector( '.forwp-todo-panel__date-picker' );
						picker?.showPicker?.();
						picker?.focus();
						return;
					}
					if ( focus === 'assignee' ) {
						edit.querySelector( '[name="assignee"]' )?.focus();
						return;
					}
					if ( focus === 'department' ) {
						edit.querySelector( '[name="category"]' )?.focus();
						return;
					}
					edit.querySelector( '.forwp-todo-panel__edit-input' )?.focus();
				} );
			}
			expandedFocus = 'default';
			expandedOpenedId = 0;
		}

		return li;
	}

	function renderQuickAdd() {
		const row = document.createElement( 'div' );
		row.className = 'forwp-todo-panel__quick-add';

		const check = document.createElement( 'span' );
		check.className = 'forwp-todo-panel__quick-add-check';
		check.setAttribute( 'aria-hidden', 'true' );

		const input = document.createElement( 'input' );
		input.type = 'text';
		input.className = 'forwp-todo-panel__quick-add-input';
		input.placeholder = i18n?.placeholder || 'What should change here?';

		let submitted = false;

		const commit = () => {
			if ( submitted ) {
				return;
			}
			submitted = true;

			const value = input.value.trim();
			quickAddActive = false;
			if ( value ) {
				Promise.resolve( onQuickAdd?.( value ) )
					.catch( () => undefined )
					.finally( () => {
						submitted = false;
						render();
					} );
				return;
			}
			submitted = false;
			render();
		};

		input.addEventListener( 'blur', commit );
		input.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Enter' ) {
				event.preventDefault();
				commit();
			}
			if ( event.key === 'Escape' ) {
				submitted = true;
				quickAddActive = false;
				render();
			}
		} );

		row.append( check, input );
		return row;
	}

	function renderHeader() {
		const head = document.createElement( 'div' );
		head.className = isColumn
			? 'forwp-todo-panel__head forwp-todo-panel__head--column'
			: 'forwp-todo-panel__head';

		const title = document.createElement( 'strong' );
		title.className = 'forwp-todo-panel__title';
		if ( isColumn && columnTitle ) {
			if ( headerLink ) {
				const link = document.createElement( 'a' );
				link.className = 'forwp-todo-panel__title-link';
				link.href = headerLink;
				link.textContent = columnTitle;
				title.append( link );
			} else {
				title.textContent = columnTitle;
			}
		} else {
			title.textContent = i18n?.checklist || 'Page notes';
		}

		const titleWrap = document.createElement( 'div' );
		titleWrap.className = 'forwp-todo-panel__title-wrap';
		titleWrap.append( title );

		const allTasksUrl = getAllTasksUrl?.() || '';
		if ( allTasksUrl && ! isColumn ) {
			const allTasksLink = document.createElement( 'a' );
			allTasksLink.className =
				'forwp-todo-panel__icon-btn forwp-todo-panel__all-tasks';
			allTasksLink.href = allTasksUrl;
			allTasksLink.setAttribute(
				'aria-label',
				i18n?.openAllTasks || 'Open all tasks'
			);
			allTasksLink.title = i18n?.openAllTasks || 'Open all tasks';
			allTasksLink.append( createIcon( 'forwp-todo-panel__icon--jump' ) );
			titleWrap.append( allTasksLink );
		}

		head.append( titleWrap );

		if ( shouldShowClose ) {
			const closeBtn = createIconButton(
				'forwp-todo-panel__icon-btn forwp-todo-panel__close',
				i18n?.closePanel || 'Close',
				'forwp-todo-panel__icon--close'
			);
			closeBtn.addEventListener( 'click', ( event ) => {
				event.preventDefault();
				event.stopPropagation();
				toggle( false );
				onClose?.();
			} );
			head.append( closeBtn );
		}

		mount.append( head );
	}

	function renderScopeFilter() {
		const wrap = document.createElement( 'div' );
		wrap.className = 'forwp-todo-panel__scope';
		wrap.setAttribute( 'role', 'tablist' );
		wrap.setAttribute(
			'aria-label',
			i18n?.taskScope || 'Task scope'
		);

		const scopes = [
			{
				id: TASK_SCOPE_ALL,
				label: i18n?.scopeAll || 'All',
			},
			{
				id: TASK_SCOPE_AUTHORED,
				label: i18n?.scopeMine || 'Mine',
			},
			{
				id: TASK_SCOPE_ASSIGNED,
				label: i18n?.scopeForMe || 'For me',
			},
		];

		scopes.forEach( ( item ) => {
			const btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = [
				'forwp-todo-panel__scope-btn',
				taskScope === item.id ? 'is-active' : '',
			]
				.filter( Boolean )
				.join( ' ' );
			btn.setAttribute( 'role', 'tab' );
			btn.setAttribute( 'aria-selected', taskScope === item.id ? 'true' : 'false' );
			btn.textContent = item.label;
			btn.addEventListener( 'click', () => {
				if ( taskScope === item.id ) {
					return;
				}
				taskScope = item.id;
				render();
			} );
			wrap.append( btn );
		} );

		mount.append( wrap );
	}

	function renderActions() {
		const showPageLink = isColumn && pageViewUrl;
		if (
			! shouldShowQuickAdd &&
			! shouldShowPin &&
			! showPageLink &&
			! shouldShowPostPicker
		) {
			return;
		}

		const actions = document.createElement( 'div' );
		actions.className = 'forwp-todo-panel__actions';

		if ( shouldShowPostPicker ) {
			const posts = getPostPickerPosts?.() || [];
			const pickerWrap = document.createElement( 'label' );
			pickerWrap.className = 'forwp-todo-panel__post-picker';

			const pickerLabel = document.createElement( 'span' );
			pickerLabel.className = 'forwp-todo-panel__post-picker-label';
			pickerLabel.textContent = i18n?.selectPage || 'Page';

			const select = document.createElement( 'select' );
			select.className = 'forwp-todo-panel__post-picker-select';
			select.disabled = ! posts.length;

			if ( ! posts.length ) {
				const option = document.createElement( 'option' );
				option.value = '0';
				option.textContent = i18n?.noPages || 'No pages available';
				select.append( option );
			} else {
				posts.forEach( ( post ) => {
					const option = document.createElement( 'option' );
					option.value = String( post.id );
					option.textContent = post.title || `#${ post.id }`;
					select.append( option );
				} );
			}

			const currentValue = getPostPickerValue?.() || 0;
			if ( currentValue > 0 ) {
				select.value = String( currentValue );
			}

			select.addEventListener( 'change', () => {
				onPostPickerChange?.( parseInt( select.value, 10 ) || 0 );
			} );

			pickerWrap.append( pickerLabel, select );
			actions.append( pickerWrap );
		}

		if ( shouldShowQuickAdd ) {
			const addBtn = document.createElement( 'button' );
			addBtn.type = 'button';
			addBtn.className = 'forwp-todo-panel__action';
			addBtn.append(
				createIcon( 'forwp-todo-panel__icon--add' ),
				document.createTextNode( i18n?.addTask || 'Add a task' )
			);
			addBtn.addEventListener( 'click', () => startQuickAdd() );

			if ( shouldShowPin ) {
				const pinBtn = document.createElement( 'button' );
				pinBtn.type = 'button';
				pinBtn.className = 'forwp-todo-panel__action';
				pinBtn.append(
					createIcon( 'forwp-todo-panel__icon--target' ),
					document.createTextNode( i18n?.takeElement || 'Take element' )
				);
				pinBtn.addEventListener( 'click', () => onStartPin() );
				actions.append( addBtn, pinBtn );
			} else if ( showPageLink ) {
				const pageBtn = document.createElement( 'a' );
				pageBtn.className =
					'forwp-todo-panel__action forwp-todo-panel__action--link';
				pageBtn.href = pageViewUrl;
				pageBtn.target = '_blank';
				pageBtn.rel = 'noopener noreferrer';
				pageBtn.append(
					createIcon( 'forwp-todo-panel__icon--jump' ),
					document.createTextNode(
						i18n?.openPageList || 'Open page list'
					)
				);
				actions.append( addBtn, pageBtn );
			} else {
				actions.append( addBtn );
			}
		} else if ( shouldShowPin ) {
			const pinBtn = document.createElement( 'button' );
			pinBtn.type = 'button';
			pinBtn.className = 'forwp-todo-panel__action';
			pinBtn.append(
				createIcon( 'forwp-todo-panel__icon--target' ),
				document.createTextNode( i18n?.takeElement || 'Take element' )
			);
			pinBtn.addEventListener( 'click', () => onStartPin() );
			actions.append( pinBtn );
		} else if ( showPageLink ) {
			const pageBtn = document.createElement( 'a' );
			pageBtn.className =
				'forwp-todo-panel__action forwp-todo-panel__action--link';
			pageBtn.href = pageViewUrl;
			pageBtn.target = '_blank';
			pageBtn.rel = 'noopener noreferrer';
			pageBtn.append(
				createIcon( 'forwp-todo-panel__icon--jump' ),
				document.createTextNode(
					i18n?.openPageList || 'Open page list'
				)
			);
			actions.append( pageBtn );
		}

		mount.append( actions );
	}

	function renderCompletedSection( doneTasks, meta, scroll ) {
		const wrap = document.createElement( 'div' );
		wrap.className = 'forwp-todo-panel__completed';

		const toggleBtn = document.createElement( 'button' );
		toggleBtn.type = 'button';
		toggleBtn.className = 'forwp-todo-panel__completed-toggle';
		toggleBtn.setAttribute( 'aria-expanded', completedOpen ? 'true' : 'false' );
		toggleBtn.innerHTML = `
			<span class="forwp-todo-panel__completed-chevron${ completedOpen ? ' is-open' : '' }" aria-hidden="true"></span>
			<span>${ i18n?.completed || 'Completed' } (${ doneTasks.length })</span>
		`;
		toggleBtn.addEventListener( 'click', () => {
			completedOpen = ! completedOpen;
			render();
		} );
		wrap.append( toggleBtn );

		if ( completedOpen ) {
			const list = document.createElement( 'ul' );
			list.className = 'forwp-todo-panel__list forwp-todo-panel__list--completed';
			doneTasks.forEach( ( task ) => {
				list.append( renderTaskItem( task, meta ) );
			} );
			wrap.append( list );
		}

		scroll.append( wrap );
	}

	function render( options = {} ) {
		const soft = options.soft === true;
		const meta = getMeta?.() || { categories: {}, users: [], current_user: { id: 0 } };
		const currentUserId = meta.current_user?.id || 0;
		const tasks = shouldShowScopeFilter
			? filterTasksByScope( getTasks(), taskScope, currentUserId )
			: getTasks();
		const loading = isLoading?.() ?? false;
		const openTasks = sortTasksForScope(
			shouldSplitCompleted
				? tasks.filter( ( task ) => task.is_open )
				: tasks,
			sortScope
		);
		const doneTasks = shouldSplitCompleted
			? tasks.filter( ( task ) => ! task.is_open )
			: [];

		if ( soft && canSoftSync( loading, openTasks, doneTasks ) ) {
			const list = mount.querySelector(
				'.forwp-todo-panel__list:not(.forwp-todo-panel__list--completed)'
			);
			if ( list ) {
				syncOpenTaskList( list, openTasks, meta );
				lastDoneCount = doneTasks.length;
				return;
			}
		}

		const previousScroll = mount.querySelector( '.forwp-todo-panel__scroll' );
		const scrollTop = previousScroll?.scrollTop ?? 0;

		mount.innerHTML = '';
		if ( isColumn ) {
			mount.classList.add( 'forwp-todo-panel--column' );
		}
		renderHeader();
		if ( shouldShowScopeFilter ) {
			renderScopeFilter();
		}
		renderActions();

		const scroll = document.createElement( 'div' );
		scroll.className = 'forwp-todo-panel__scroll';

		if ( quickAddActive ) {
			scroll.append( renderQuickAdd() );
		}

		if ( loading ) {
			const loadingEl = document.createElement( 'p' );
			loadingEl.className = 'forwp-todo-panel__empty';
			loadingEl.textContent = i18n?.loading || 'Loading notes…';
			scroll.append( loadingEl );
		} else if ( ! openTasks.length && ! doneTasks.length && ! quickAddActive ) {
			const empty = document.createElement( 'p' );
			empty.className = 'forwp-todo-panel__empty';
			empty.textContent = i18n?.empty || 'No notes on this page yet.';
			scroll.append( empty );
		} else {
			if ( openTasks.length ) {
				const list = document.createElement( 'ul' );
				list.className = 'forwp-todo-panel__list';
				openTasks.forEach( ( task ) => {
					list.append( renderTaskItem( task, meta ) );
				} );
				if ( sortScope && onReorder ) {
					bindSortableList( list, onReorder );
				}
				scroll.append( list );
			}

			if ( shouldSplitCompleted && doneTasks.length ) {
				renderCompletedSection( doneTasks, meta, scroll );
			}
		}

		mount.append( scroll );

		const nextScroll = mount.querySelector( '.forwp-todo-panel__scroll' );
		if ( nextScroll && scrollTop > 0 ) {
			nextScroll.scrollTop = scrollTop;
		}

		lastDoneCount = doneTasks.length;

		if ( quickAddActive ) {
			requestAnimationFrame( () => {
				mount.querySelector( '.forwp-todo-panel__quick-add-input' )?.focus();
			} );
		}
	}

	if ( ! mount.dataset.forwpTodoPanelBound ) {
		mount.dataset.forwpTodoPanelBound = '1';
		document.addEventListener( 'click', ( event ) => {
			if (
				event.target.closest( '.forwp-todo-panel__more' ) ||
				event.target.closest( '.forwp-todo-panel__menu' ) ||
				event.target.closest( '.forwp-todo-panel__menu-item--sub' )
			) {
				return;
			}
			closeMenus();

			if ( ! expandedId || ! mount.isConnected ) {
				return;
			}

			const expandedItem = mount.querySelector(
				'.forwp-todo-panel__item.is-expanded'
			);
			if (
				expandedItem &&
				expandedItem.contains( event.target ) &&
				( event.target.closest( '.forwp-todo-panel__edit' ) ||
					event.target.closest( '.forwp-todo-panel__text' ) )
			) {
				return;
			}

			closeExpanded();
		} );
	}

	return { render, toggle, isOpen, focusTask, startQuickAdd };
}

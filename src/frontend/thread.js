/**
 * Figma-style comment thread popover (root note + replies).
 */

import { getAvatarColor, getInitials, formatRelativeTime } from './lib/avatar';

function plainText( html ) {
	const node = document.createElement( 'div' );
	node.innerHTML = html;
	return ( node.textContent || html || '' ).trim();
}

function createAvatar( userId, name, className = 'forwp-todo-thread__avatar' ) {
	const avatar = document.createElement( 'span' );
	avatar.className = className;
	avatar.style.backgroundColor = getAvatarColor( userId );
	avatar.textContent = getInitials( name );
	avatar.setAttribute( 'aria-hidden', 'true' );
	return avatar;
}

function renderMessage( item, isRoot = false ) {
	const row = document.createElement( 'div' );
	row.className = isRoot
		? 'forwp-todo-thread__message forwp-todo-thread__message--root'
		: 'forwp-todo-thread__message';

	const content = document.createElement( 'div' );
	content.className = 'forwp-todo-thread__message-content';

	const meta = document.createElement( 'div' );
	meta.className = 'forwp-todo-thread__message-meta';
	meta.innerHTML = `<strong>${ item.author_name || 'User' }</strong><span>${ formatRelativeTime( item.created ) }</span>`;

	const body = document.createElement( 'div' );
	body.className = 'forwp-todo-thread__message-body';
	body.textContent = plainText( item.content );

	content.append( meta, body );
	row.append( createAvatar( item.author, item.author_name ), content );
	return row;
}

/**
 * @param {object} options
 * @return {{ open: Function, close: Function, isOpen: Function }}
 */
export function createThread( {
	api,
	i18n,
	currentUser,
	onOpenPanel,
	onFocusTask,
} ) {
	let rootEl = null;
	let activeTaskId = 0;
	let loading = false;

	function ensureRoot() {
		if ( rootEl ) {
			return rootEl;
		}

		rootEl = document.createElement( 'div' );
		rootEl.className = 'forwp-todo-thread';
		rootEl.hidden = true;
		rootEl.setAttribute( 'role', 'dialog' );
		rootEl.setAttribute( 'aria-modal', 'false' );
		document.body.append( rootEl );
		return rootEl;
	}

	function close() {
		if ( ! rootEl ) {
			return;
		}
		rootEl.hidden = true;
		rootEl.innerHTML = '';
		activeTaskId = 0;
	}

	function isOpen() {
		return Boolean( rootEl && ! rootEl.hidden );
	}

	function positionNear( anchor ) {
		if ( ! rootEl || ! anchor ) {
			return;
		}

		const card = rootEl.querySelector( '.forwp-todo-thread__card' );
		if ( ! card ) {
			return;
		}

		const rect = anchor.getBoundingClientRect();
		const cardWidth = card.offsetWidth || 340;
		const cardHeight = card.offsetHeight || 200;
		const margin = 10;

		let top = rect.bottom + margin;
		let left = rect.right - cardWidth;

		if ( left + cardWidth > window.innerWidth - margin ) {
			left = window.innerWidth - cardWidth - margin;
		}
		if ( left < margin ) {
			left = margin;
		}

		if ( top + cardHeight > window.innerHeight - margin ) {
			top = rect.top - cardHeight - margin;
		}
		if ( top < margin ) {
			top = margin;
		}

		rootEl.style.top = `${ top }px`;
		rootEl.style.left = `${ left }px`;
	}

	function renderCard( task, replies ) {
		const shell = ensureRoot();
		shell.innerHTML = '';
		shell.hidden = false;
		shell.setAttribute(
			'aria-label',
			i18n?.comment || 'Comment'
		);

		const card = document.createElement( 'div' );
		card.className = 'forwp-todo-thread__card';

		const head = document.createElement( 'div' );
		head.className = 'forwp-todo-thread__head';

		const title = document.createElement( 'strong' );
		title.className = 'forwp-todo-thread__head-title';
		title.textContent = i18n?.comment || 'Comment';

		const actions = document.createElement( 'div' );
		actions.className = 'forwp-todo-thread__head-actions';

		const panelBtn = document.createElement( 'button' );
		panelBtn.type = 'button';
		panelBtn.className = 'forwp-todo-thread__head-btn';
		panelBtn.textContent = i18n?.openList || 'Open list';
		panelBtn.addEventListener( 'click', () => {
			onOpenPanel?.( task.id );
			close();
		} );

		const closeBtn = document.createElement( 'button' );
		closeBtn.type = 'button';
		closeBtn.className = 'forwp-todo-thread__head-btn forwp-todo-thread__head-btn--close';
		closeBtn.setAttribute( 'aria-label', i18n?.close || 'Close' );
		closeBtn.textContent = '×';
		closeBtn.addEventListener( 'click', () => close() );

		actions.append( panelBtn, closeBtn );
		head.append( title, actions );

		const body = document.createElement( 'div' );
		body.className = 'forwp-todo-thread__body';
		body.append( renderMessage( task, true ) );

		replies.forEach( ( reply ) => {
			body.append( renderMessage( reply, false ) );
		} );

		const replyWrap = document.createElement( 'div' );
		replyWrap.className = 'forwp-todo-thread__reply';

		const user = currentUser || {};
		replyWrap.append( createAvatar( user.id, user.name, 'forwp-todo-thread__reply-avatar' ) );

		const form = document.createElement( 'form' );
		form.className = 'forwp-todo-thread__reply-form';

		const input = document.createElement( 'input' );
		input.type = 'text';
		input.className = 'forwp-todo-thread__reply-input';
		input.placeholder = i18n?.reply || 'Reply';
		input.autocomplete = 'off';

		const submit = document.createElement( 'button' );
		submit.type = 'submit';
		submit.className = 'forwp-todo-thread__reply-submit';
		submit.setAttribute( 'aria-label', i18n?.reply || 'Reply' );
		submit.innerHTML =
			'<span class="forwp-todo-thread__reply-arrow" aria-hidden="true"></span>';

		form.append( input, submit );
		replyWrap.append( form );

		form.addEventListener( 'submit', async ( event ) => {
			event.preventDefault();
			const content = input.value.trim();
			if ( ! content || loading ) {
				return;
			}

			loading = true;
			submit.disabled = true;
			try {
				const data = await api(
					`forwp-todo/v1/tasks/${ task.id }/replies`,
					{
						method: 'POST',
						body: JSON.stringify( { content } ),
					}
				);
				renderCard( data.task, data.replies || [] );
				onFocusTask?.( task.id );
			} catch ( e ) {
				// eslint-disable-next-line no-console
				console.error( e );
			} finally {
				loading = false;
				submit.disabled = false;
			}
		} );

		card.append( head, body, replyWrap );
		shell.append( card );
	}

	async function open( taskId, anchor ) {
		activeTaskId = taskId;
		onFocusTask?.( taskId );

		const shell = ensureRoot();
		shell.hidden = false;
		shell.innerHTML =
			'<div class="forwp-todo-thread__card forwp-todo-thread__card--loading"><p class="forwp-todo-thread__loading">' +
			( i18n?.loading || 'Loading…' ) +
			'</p></div>';
		positionNear( anchor );

		try {
			const data = await api( `forwp-todo/v1/tasks/${ taskId }/thread` );
			renderCard( data.task, data.replies || [] );
			positionNear( anchor );
			shell.querySelector( '.forwp-todo-thread__reply-input' )?.focus();
		} catch ( e ) {
			// eslint-disable-next-line no-console
			console.error( e );
			close();
		}
	}

	document.addEventListener( 'click', ( event ) => {
		if ( ! isOpen() ) {
			return;
		}
		if (
			event.target.closest( '.forwp-todo-thread' ) ||
			event.target.closest( '.forwp-todo-avatar-pin' )
		) {
			return;
		}
		close();
	} );

	document.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Escape' && isOpen() ) {
			close();
		}
	} );

	return { open, close, isOpen };
}

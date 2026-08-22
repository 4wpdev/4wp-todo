/**
 * Compact composer after picking a DOM element (Google Tasks expanded row).
 */

function isoDateOffset( days ) {
	const date = new Date();
	date.setDate( date.getDate() + days );
	return date.toISOString().slice( 0, 10 );
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
 * @return {{ open: Function, close: Function }}
 */
export function createComposer(
	mount,
	{ i18n, getMeta, onSave, onCancel, onClose, onOpen }
) {
	let draft = null;

	function close() {
		draft = null;
		mount.hidden = true;
		mount.innerHTML = '';
		onClose?.();
	}

	function open( pin ) {
		const meta = getMeta?.() || { categories: {}, users: [] };
		const categories = meta.categories || {};

		draft = {
			anchor: pin.anchor,
			selector: pin.selector,
			fallbackSelector: pin.fallbackSelector,
			text: '',
			targetMode: 'department',
			category: '',
			assignee: 0,
			due_date: '',
		};

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

		mount.hidden = false;
		mount.innerHTML = `
			<div class="forwp-todo-composer__head">
				<div class="forwp-todo-composer__head-text">
					<strong>${ i18n?.newNote || 'New note' }</strong>
					<span>${ i18n?.pinnedTo || 'Pinned to section' }</span>
				</div>
				<button type="button" class="forwp-todo-composer__close" aria-label="${ i18n?.cancelPin || 'Cancel' }">×</button>
			</div>
			<div class="forwp-todo-composer__sheet">
				<div class="forwp-todo-composer__row">
					<span class="forwp-todo-composer__check" aria-hidden="true"></span>
					<input type="text" class="forwp-todo-composer__title" maxlength="500" placeholder="${ i18n?.placeholder || 'What should change here?' }" />
				</div>
				<div class="forwp-todo-composer__field">
					<span class="forwp-todo-composer__field-label">${ i18n?.dueDate || 'Due date' }</span>
					<div class="forwp-todo-composer__chips">
						<button type="button" class="forwp-todo-composer__chip" data-date="${ isoDateOffset( 0 ) }">${ i18n?.today || 'Today' }</button>
						<button type="button" class="forwp-todo-composer__chip" data-date="${ isoDateOffset( 1 ) }">${ i18n?.tomorrow || 'Tomorrow' }</button>
						<label class="forwp-todo-composer__chip forwp-todo-composer__chip--picker">
							<span>${ i18n?.pickDueDate || 'Pick date…' }</span>
							<input type="date" class="forwp-todo-composer__date-input" name="due_date" />
						</label>
					</div>
				</div>
				<div class="forwp-todo-composer__field">
					<span class="forwp-todo-composer__field-label">${ i18n?.routeTo || 'Route to' }</span>
					<div class="forwp-todo-composer__route">
						<div class="forwp-todo-composer__route-tabs" role="tablist">
							<button type="button" class="forwp-todo-composer__route-tab is-active" data-mode="department" role="tab">${ i18n?.department || 'Department' }</button>
							<button type="button" class="forwp-todo-composer__route-tab" data-mode="assignee" role="tab">${ i18n?.assignee || 'Assignee' }</button>
						</div>
						<div class="forwp-todo-composer__route-slot is-department">
							<select class="forwp-todo-composer__select" name="category" aria-label="${ i18n?.department || 'Department' }">${ buildSelectOptions( departmentItems, draft.category ) }</select>
							<select class="forwp-todo-composer__select" name="assignee" aria-label="${ i18n?.assignee || 'Assignee' }">${ buildSelectOptions( assigneeItems, draft.assignee ) }</select>
						</div>
					</div>
				</div>
				<div class="forwp-todo-composer__actions">
					<button type="button" class="forwp-todo-composer__save">${ i18n?.addTask || 'Add task' }</button>
					<button type="button" class="forwp-todo-composer__cancel">${ i18n?.cancelComposer || 'Cancel' }</button>
				</div>
				<p class="forwp-todo-composer__hint">${ i18n?.saveHint || 'Enter in the note field also saves' }</p>
			</div>
		`;

		const titleInput = mount.querySelector( '.forwp-todo-composer__title' );
		const targetSlot = mount.querySelector( '.forwp-todo-composer__route-slot' );
		const categorySelect = mount.querySelector( '[name="category"]' );
		const assigneeSelect = mount.querySelector( '[name="assignee"]' );
		const datePicker = mount.querySelector( '[name="due_date"]' );
		const modeButtons = mount.querySelectorAll( '.forwp-todo-composer__route-tab' );

		function setTargetMode( mode ) {
			draft.targetMode = mode;

			modeButtons.forEach( ( button ) => {
				button.classList.toggle( 'is-active', button.dataset.mode === mode );
			} );

			targetSlot.classList.toggle( 'is-department', mode === 'department' );
			targetSlot.classList.toggle( 'is-assignee', mode === 'assignee' );

			if ( mode === 'department' ) {
				assigneeSelect.value = '0';
				categorySelect.focus();
				return;
			}

			categorySelect.value = '';
			assigneeSelect.focus();
		}

		modeButtons.forEach( ( button ) => {
			button.addEventListener( 'click', () => setTargetMode( button.dataset.mode ) );
		} );

		mount.querySelectorAll( '.forwp-todo-composer__chip[data-date]' ).forEach( ( chip ) => {
			chip.addEventListener( 'click', () => {
				draft.due_date = chip.dataset.date || '';
				datePicker.value = draft.due_date;
				mount.querySelectorAll( '.forwp-todo-composer__chip[data-date]' ).forEach( ( btn ) => {
					btn.classList.toggle( 'is-active', btn === chip );
				} );
				mount
					.querySelector( '.forwp-todo-composer__chip--picker' )
					?.classList.remove( 'is-active' );
			} );
		} );

		datePicker.addEventListener( 'change', () => {
			draft.due_date = datePicker.value;
			mount.querySelectorAll( '.forwp-todo-composer__chip[data-date]' ).forEach( ( btn ) => {
				btn.classList.remove( 'is-active' );
			} );
			if ( draft.due_date ) {
				mount
					.querySelector( '.forwp-todo-composer__chip--picker' )
					?.classList.add( 'is-active' );
			}
		} );

		function cancelComposer() {
			close();
			onCancel();
		}

		function saveNote() {
			const text = titleInput.value.trim();
			if ( ! text || ! draft ) {
				titleInput.focus();
				return;
			}

			const payload = {
				content: text,
				anchor: draft.anchor,
				selector: draft.selector,
				fallbackSelector: draft.fallbackSelector,
				targetMode: draft.targetMode,
				category: '',
				assignee: 0,
				due_date: draft.due_date || datePicker.value || '',
			};

			if ( draft.targetMode === 'department' ) {
				payload.category = categorySelect.value || '';
			} else {
				const assignee = parseInt( assigneeSelect.value || '0', 10 );
				payload.assignee = Number.isFinite( assignee ) ? assignee : 0;
			}

			onSave( payload );
			close();
		}

		mount.querySelector( '.forwp-todo-composer__close' ).addEventListener( 'click', cancelComposer );
		mount.querySelector( '.forwp-todo-composer__cancel' ).addEventListener( 'click', cancelComposer );
		mount.querySelector( '.forwp-todo-composer__save' ).addEventListener( 'click', saveNote );

		titleInput.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Enter' ) {
				event.preventDefault();
				saveNote();
			}
			if ( event.key === 'Escape' ) {
				event.preventDefault();
				cancelComposer();
			}
		} );

		titleInput.focus();
		onOpen?.();
	}

	return { open, close };
}

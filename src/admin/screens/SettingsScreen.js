/**
 * Plugin settings.
 */
import { __ } from '@wordpress/i18n';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CheckboxControl,
	Notice,
	RadioControl,
	SelectControl,
	Spinner,
	ToggleControl,
} from '@wordpress/components';

const SETTINGS_PATH = '/forwp-todo/v1/settings';

function cloneSettings( data ) {
	return {
		frontend_enabled: !! data.frontend_enabled,
		hide_admin_bar: !! data.hide_admin_bar,
		show_admin_bar_todo: !! data.show_admin_bar_todo,
		visibility_rules: ( data.visibility_rules || [] ).map( ( rule ) => ( {
			role: rule.role,
			mode: rule.mode === 'selected' ? 'selected' : 'all',
			user_ids: ( rule.user_ids || [] ).map( ( id ) => Number( id ) ),
		} ) ),
		super_admin_user_ids: ( data.super_admin_user_ids || [] ).map( ( id ) =>
			Number( id )
		),
		tasks_page_id: Number( data.tasks_page_id || 0 ),
		catalog: data.catalog || {
			roles: [],
			users_by_role: {},
			admin_users: [],
		},
		pages: data.pages || [],
	};
}

function RoleAccessRule( { rule, catalog, onChange, onRemove } ) {
	const roleLabel =
		catalog.roles?.find( ( role ) => role.slug === rule.role )?.label ||
		rule.role;
	const users = catalog.users_by_role?.[ rule.role ] || [];

	const toggleUser = ( userId, checked ) => {
		const next = new Set( rule.user_ids || [] );
		if ( checked ) {
			next.add( userId );
		} else {
			next.delete( userId );
		}
		onChange( {
			...rule,
			user_ids: Array.from( next ),
		} );
	};

	return (
		<Card className="forwp-todo-access-rule">
			<CardBody>
				<div className="forwp-todo-access-rule__head">
					<strong>{ roleLabel }</strong>
					<Button variant="tertiary" isDestructive onClick={ onRemove }>
						{ __( 'Remove role', '4wp-todo' ) }
					</Button>
				</div>
				<RadioControl
					label={ __( 'Who can see TODO', '4wp-todo' ) }
					selected={ rule.mode }
					options={ [
						{
							label: __( 'Everyone in this role', '4wp-todo' ),
							value: 'all',
						},
						{
							label: __( 'Selected users only', '4wp-todo' ),
							value: 'selected',
						},
					] }
					onChange={ ( mode ) =>
						onChange( {
							...rule,
							mode,
							user_ids: mode === 'selected' ? rule.user_ids || [] : [],
						} )
					}
				/>
				{ rule.mode === 'selected' && (
					<div className="forwp-todo-access-rule__users">
						{ users.length === 0 ? (
							<p className="forwp-todo-admin-muted">
								{ __(
									'No users found for this role.',
									'4wp-todo'
								) }
							</p>
						) : (
							users.map( ( user ) => (
								<CheckboxControl
									key={ user.id }
									label={ user.name }
									checked={ ( rule.user_ids || [] ).includes(
										user.id
									) }
									onChange={ ( checked ) =>
										toggleUser( user.id, checked )
									}
								/>
							) )
						) }
					</div>
				) }
			</CardBody>
		</Card>
	);
}

function SuperAdminPicker( { userIds, adminUsers, onChange } ) {
	const toggleUser = ( userId, checked ) => {
		const next = new Set( userIds || [] );
		if ( checked ) {
			next.add( userId );
		} else {
			next.delete( userId );
		}
		onChange( Array.from( next ) );
	};

	return (
		<div className="forwp-todo-access-rule__users">
			{ adminUsers.length === 0 ? (
				<p className="forwp-todo-admin-muted">
					{ __( 'No administrators found.', '4wp-todo' ) }
				</p>
			) : (
				adminUsers.map( ( user ) => (
					<CheckboxControl
						key={ user.id }
						label={ user.name }
						checked={ ( userIds || [] ).includes( user.id ) }
						onChange={ ( checked ) => toggleUser( user.id, checked ) }
					/>
				) )
			) }
		</div>
	);
}

export default function SettingsScreen() {
	const [ settings, setSettings ] = useState( null );
	const [ draft, setDraft ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ saved, setSaved ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ addRole, setAddRole ] = useState( '' );

	const load = useCallback( () => {
		setError( '' );
		return apiFetch( { path: SETTINGS_PATH } )
			.then( ( data ) => {
				const next = cloneSettings( data );
				setSettings( next );
				setDraft( next );
				setSaved( false );
			} )
			.catch( () =>
				setError(
					__(
						'Could not load settings. Refresh the page and try again.',
						'4wp-todo'
					)
				)
			);
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	const isDirty = useMemo( () => {
		if ( ! settings || ! draft ) {
			return false;
		}
		return JSON.stringify( settings ) !== JSON.stringify( draft );
	}, [ draft, settings ] );

	const availableRoles = useMemo( () => {
		if ( ! draft?.catalog?.roles ) {
			return [];
		}
		const used = new Set(
			( draft.visibility_rules || [] ).map( ( rule ) => rule.role )
		);
		return draft.catalog.roles.filter( ( role ) => ! used.has( role.slug ) );
	}, [ draft ] );

	const updateDraft = ( patch ) => {
		setDraft( ( current ) => ( { ...current, ...patch } ) );
		setSaved( false );
	};

	const addRoleRule = () => {
		if ( ! addRole ) {
			return;
		}
		updateDraft( {
			visibility_rules: [
				...( draft.visibility_rules || [] ),
				{ role: addRole, mode: 'all', user_ids: [] },
			],
		} );
		setAddRole( '' );
	};

	const updateRule = ( index, nextRule ) => {
		const rules = [ ...( draft.visibility_rules || [] ) ];
		rules[ index ] = nextRule;
		updateDraft( { visibility_rules: rules } );
	};

	const removeRule = ( index ) => {
		updateDraft( {
			visibility_rules: ( draft.visibility_rules || [] ).filter(
				( _rule, ruleIndex ) => ruleIndex !== index
			),
		} );
	};

	const save = async () => {
		if ( ! draft ) {
			return;
		}

		setSaving( true );
		setError( '' );
		setSaved( false );

		try {
			const data = await apiFetch( {
				path: SETTINGS_PATH,
				method: 'POST',
				data: {
					frontend_enabled: draft.frontend_enabled,
					hide_admin_bar: draft.hide_admin_bar,
					show_admin_bar_todo: draft.show_admin_bar_todo,
					visibility_rules: draft.visibility_rules,
					super_admin_user_ids: draft.super_admin_user_ids,
					tasks_page_id: draft.tasks_page_id,
				},
			} );
			const next = cloneSettings( data );
			setSettings( next );
			setDraft( next );
			setSaved( true );
		} catch ( e ) {
			setError(
				e?.message ||
					__(
						'Could not save settings. Check your connection and try again.',
						'4wp-todo'
					)
			);
		} finally {
			setSaving( false );
		}
	};

	if ( ! draft ) {
		return error ? (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		) : (
			<Spinner />
		);
	}

	return (
		<div className="forwp-todo-tab-panel__inner">
			{ error ? (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) : null }
			{ saved ? (
				<Notice status="success" isDismissible={ false }>
					{ __( 'Settings saved.', '4wp-todo' ) }
				</Notice>
			) : null }

			<Card className="forwp-todo-intro-card">
				<CardBody>
					<h3>{ __( 'Page notes overlay', '4wp-todo' ) }</h3>
					<p>
						{ __(
							'When enabled, allowed users see the round checklist button on the frontend, in the block editor, and across wp-admin.',
							'4wp-todo'
						) }
					</p>
					<ToggleControl
						label={ __( 'Enable page notes overlay', '4wp-todo' ) }
						checked={ !! draft.frontend_enabled }
						onChange={ ( value ) =>
							updateDraft( { frontend_enabled: value } )
						}
					/>
					<ToggleControl
						label={ __(
							'Hide WordPress admin bar on the frontend',
							'4wp-todo'
						) }
						help={ __(
							'Applies while frontend page notes are enabled, so reviewers get a cleaner page view.',
							'4wp-todo'
						) }
						checked={ !! draft.hide_admin_bar }
						onChange={ ( value ) =>
							updateDraft( { hide_admin_bar: value } )
						}
						disabled={ ! draft.frontend_enabled }
					/>
				</CardBody>
			</Card>

			<Card>
				<CardBody>
					<h3>{ __( 'Admin toolbar', '4wp-todo' ) }</h3>
					<p className="forwp-todo-admin-muted">
						{ __(
							'Floating checklist on the frontend, in the block editor, and across wp-admin. Users with access see the round button (bottom-right) and can open the task list from the admin bar.',
							'4wp-todo'
						) }
					</p>
					<ToggleControl
						label={ __( 'Show TODO in the admin bar', '4wp-todo' ) }
						checked={ !! draft.show_admin_bar_todo }
						onChange={ ( value ) =>
							updateDraft( { show_admin_bar_todo: value } )
						}
					/>
				</CardBody>
			</Card>

			<Card>
				<CardBody>
					<h3>{ __( 'All tasks page', '4wp-todo' ) }</h3>
					<p className="forwp-todo-admin-muted">
						{ __(
							'When set, “Open all tasks” in Page notes and the admin bar goes here for everyone. Users without wp-admin access rely on this page. The 4WP TODO Tasks block is inserted automatically when you pick a page.',
							'4wp-todo'
						) }
					</p>
					<SelectControl
						label={ __( 'Public tasks page', '4wp-todo' ) }
						value={ String( draft.tasks_page_id || 0 ) }
						options={ [
							{
								label: __( '— Select a page —', '4wp-todo' ),
								value: '0',
							},
							...( draft.pages || [] ).map( ( page ) => ( {
								label: page.title,
								value: String( page.id ),
							} ) ),
						] }
						onChange={ ( value ) =>
							updateDraft( {
								tasks_page_id: Number( value ) || 0,
							} )
						}
					/>
					{ draft.tasks_page_id > 0 && (
						<p className="forwp-todo-admin-muted">
							{ ( draft.pages || [] )
								.filter( ( page ) => page.id === draft.tasks_page_id )
								.map( ( page ) => (
									<span key={ page.id }>
										{ page.edit_url ? (
											<a href={ page.edit_url }>
												{ __( 'Edit page', '4wp-todo' ) }
											</a>
										) : null }
										{ page.view_url ? (
											<>
												{ ' · ' }
												<a
													href={ page.view_url }
													target="_blank"
													rel="noopener noreferrer"
												>
													{ __( 'View page', '4wp-todo' ) }
												</a>
											</>
										) : null }
									</span>
								) ) }
						</p>
					) }
					<p className="forwp-todo-admin-muted">
						<code>{ '<!-- wp:forwp/todo-tasks /-->' }</code>
					</p>
				</CardBody>
			</Card>

			<Card>
				<CardBody>
					<h3>{ __( 'Who can see TODO', '4wp-todo' ) }</h3>
					<p className="forwp-todo-admin-muted">
						{ __(
							'Add roles, then choose everyone in the role or pick specific users. Example: Administrators and Shop managers, but only Irmantė, Anatoliy, Mykyta, and Alona.',
							'4wp-todo'
						) }
					</p>
					<p className="forwp-todo-admin-muted">
						{ __(
							'When no roles are listed, anyone who can edit posts keeps access (legacy default).',
							'4wp-todo'
						) }
					</p>

					{ ( draft.visibility_rules || [] ).map( ( rule, index ) => (
						<RoleAccessRule
							key={ rule.role }
							rule={ rule }
							catalog={ draft.catalog }
							onChange={ ( nextRule ) => updateRule( index, nextRule ) }
							onRemove={ () => removeRule( index ) }
						/>
					) ) }

					{ availableRoles.length > 0 && (
						<div className="forwp-todo-access-add">
							<SelectControl
								label={ __( 'Add role', '4wp-todo' ) }
								value={ addRole }
								options={ [
									{
										label: __( 'Select a role…', '4wp-todo' ),
										value: '',
									},
									...availableRoles.map( ( role ) => ( {
										value: role.slug,
										label: role.label,
									} ) ),
								] }
								onChange={ setAddRole }
							/>
							<Button
								variant="secondary"
								onClick={ addRoleRule }
								disabled={ ! addRole }
							>
								{ __( 'Add role rule', '4wp-todo' ) }
							</Button>
						</div>
					) }
				</CardBody>
			</Card>

			<Card>
				<CardBody>
					<h3>{ __( 'Super admins (view all tasks)', '4wp-todo' ) }</h3>
					<p className="forwp-todo-admin-muted">
						{ __(
							'Everyone else only sees tasks they created or that are assigned to them. Pick administrators who should see every task in Lists and on the frontend.',
							'4wp-todo'
						) }
					</p>
					<SuperAdminPicker
						userIds={ draft.super_admin_user_ids || [] }
						adminUsers={ draft.catalog?.admin_users || [] }
						onChange={ ( nextIds ) =>
							updateDraft( { super_admin_user_ids: nextIds } )
						}
					/>
				</CardBody>
			</Card>

			<Card>
				<CardBody>
					<h3>{ __( 'Integration API', '4wp-todo' ) }</h3>
					<p className="forwp-todo-admin-muted">
						{ __(
							'Other 4WP plugins can discover routes, hooks, and PHP helpers via REST or global functions.',
							'4wp-todo'
						) }
					</p>
					<pre className="forwp-todo-api-snippet">
						<code>GET /wp-json/forwp-todo/v1/integration</code>
					</pre>
					<p className="forwp-todo-admin-muted">
						{ __(
							'PHP: forwp_todo_create_task(), forwp_todo_user_can_access(), forwp_todo_get_integration_registry().',
							'4wp-todo'
						) }
					</p>
				</CardBody>
			</Card>

			<div
				className="forwp-todo-toolbar"
				role="group"
				aria-label={ __( 'Settings actions', '4wp-todo' ) }
			>
				<div className="forwp-todo-toolbar__primary">
					<Button
						variant="primary"
						className="forwp-todo-btn-dashicon"
						onClick={ save }
						disabled={ saving || ! isDirty }
						isBusy={ saving }
					>
						<span
							className="dashicons dashicons-saved"
							aria-hidden="true"
						/>
						<span>{ __( 'Save', '4wp-todo' ) }</span>
					</Button>
					<Button
						variant="secondary"
						className="forwp-todo-btn-dashicon"
						onClick={ load }
						disabled={ saving }
					>
						<span
							className="dashicons dashicons-update"
							aria-hidden="true"
						/>
						<span>{ __( 'Reload', '4wp-todo' ) }</span>
					</Button>
				</div>
				<div className="forwp-todo-toolbar__secondary">
					<p className="forwp-todo-toolbar__hint">
						{ isDirty
							? __(
									'You have unsaved changes.',
									'4wp-todo'
							  )
							: __(
									'Settings are up to date.',
									'4wp-todo'
							  ) }
					</p>
				</div>
			</div>
		</div>
	);
}

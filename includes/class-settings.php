<?php
/**
 * Plugin settings (options API).
 *
 * @package ForWP\Todo
 */

namespace ForWP\Todo;

use ForWP\Todo\Admin\Menu;

defined( 'ABSPATH' ) || exit;

/**
 * Stored plugin settings.
 */
final class Settings {

	public const OPTION_KEY = 'forwp_todo_settings';

	public const ACCESS_CAP = 'forwp_todo_access';

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'frontend_enabled'       => true,
			'hide_admin_bar'         => false,
			'show_admin_bar_todo'    => true,
			'visibility_rules'       => array(),
			'super_admin_user_ids'   => array(),
			'tasks_page_id'          => 0,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$merged = array_merge( self::defaults(), $stored );

		if ( ! is_array( $merged['visibility_rules'] ) ) {
			$merged['visibility_rules'] = array();
		}

		if ( ! is_array( $merged['super_admin_user_ids'] ) ) {
			$merged['super_admin_user_ids'] = array();
		}

		$merged['super_admin_user_ids'] = array_values(
			array_unique(
				array_map( 'absint', $merged['super_admin_user_ids'] )
			)
		);

		$merged['tasks_page_id'] = absint( $merged['tasks_page_id'] ?? 0 );

		return $merged;
	}

	/**
	 * @param array<string, mixed> $changes Partial settings.
	 * @return array<string, mixed>
	 */
	public static function update( array $changes ): array {
		$current = self::get();

		foreach ( array( 'frontend_enabled', 'hide_admin_bar', 'show_admin_bar_todo' ) as $key ) {
			if ( ! array_key_exists( $key, $changes ) ) {
				continue;
			}

			$current[ $key ] = (bool) $changes[ $key ];
		}

		if ( array_key_exists( 'visibility_rules', $changes ) ) {
			$current['visibility_rules'] = self::sanitize_visibility_rules( $changes['visibility_rules'] );
		}

		if ( array_key_exists( 'super_admin_user_ids', $changes ) ) {
			$current['super_admin_user_ids'] = self::sanitize_super_admin_user_ids(
				$changes['super_admin_user_ids']
			);
		}

		if ( array_key_exists( 'tasks_page_id', $changes ) ) {
			$current['tasks_page_id'] = self::sanitize_tasks_page_id( $changes['tasks_page_id'] );
		}

		update_option( self::OPTION_KEY, $current, false );

		if ( array_key_exists( 'tasks_page_id', $changes ) && $current['tasks_page_id'] > 0 ) {
			Task_Page_Display::ensure_page_has_tasks_block( (int) $current['tasks_page_id'] );
		}

		return $current;
	}

	/**
	 * @param mixed $rules Raw visibility rules from REST/UI.
	 * @return list<array{role: string, mode: string, user_ids: list<int>}>
	 */
	public static function sanitize_visibility_rules( $rules ): array {
		if ( ! is_array( $rules ) ) {
			return array();
		}

		$valid_roles = array_keys( wp_roles()->roles );
		$sanitized   = array();
		$seen_roles  = array();

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$role = sanitize_key( (string) ( $rule['role'] ?? '' ) );
			if ( '' === $role || ! in_array( $role, $valid_roles, true ) || isset( $seen_roles[ $role ] ) ) {
				continue;
			}

			$mode     = 'selected' === ( $rule['mode'] ?? '' ) ? 'selected' : 'all';
			$user_ids = array();

			if ( 'selected' === $mode && ! empty( $rule['user_ids'] ) && is_array( $rule['user_ids'] ) ) {
				foreach ( $rule['user_ids'] as $user_id ) {
					$user_id = absint( $user_id );
					if ( $user_id <= 0 ) {
						continue;
					}

					$user = get_userdata( $user_id );
					if ( ! $user || ! in_array( $role, (array) $user->roles, true ) ) {
						continue;
					}

					$user_ids[] = $user_id;
				}

				$user_ids = array_values( array_unique( $user_ids ) );
			}

			$seen_roles[ $role ] = true;
			$sanitized[]         = array(
				'role'      => $role,
				'mode'      => $mode,
				'user_ids'  => $user_ids,
			);
		}

		return $sanitized;
	}

	/**
	 * @param mixed $user_ids Raw super-admin user IDs from REST/UI.
	 * @return list<int>
	 */
	public static function sanitize_super_admin_user_ids( $user_ids ): array {
		if ( ! is_array( $user_ids ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $user_ids as $user_id ) {
			$user_id = absint( $user_id );
			if ( $user_id <= 0 ) {
				continue;
			}

			$user = get_userdata( $user_id );
			if ( ! $user || ! user_can( $user_id, 'manage_options' ) ) {
				continue;
			}

			$sanitized[] = $user_id;
		}

		return array_values( array_unique( $sanitized ) );
	}

	/**
	 * @param mixed $page_id Raw page ID from REST/UI.
	 * @return int
	 */
	public static function sanitize_tasks_page_id( $page_id ): int {
		$page_id = absint( $page_id );
		if ( $page_id <= 0 ) {
			return 0;
		}

		$post = get_post( $page_id );
		if ( ! $post || 'page' !== $post->post_type ) {
			return 0;
		}

		return $page_id;
	}

	/**
	 * @return string
	 */
	public static function get_tasks_page_url(): string {
		$page_id = absint( self::get()['tasks_page_id'] ?? 0 );
		if ( $page_id <= 0 ) {
			return '';
		}

		$url = get_permalink( $page_id );
		return $url ? (string) $url : '';
	}

	/**
	 * Post ID used to store notes when the context is not a front-end permalink.
	 *
	 * @return int
	 */
	public static function get_note_storage_post_id(): int {
		$page_id = absint( self::get()['tasks_page_id'] ?? 0 );
		if ( $page_id > 0 ) {
			return $page_id;
		}

		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		return ! empty( $pages[0] ) ? (int) $pages[0] : 0;
	}

	/**
	 * Whether the user should open TODO Lists in wp-admin.
	 *
	 * @param int $user_id User ID (0 = current user).
	 * @return bool
	 */
	public static function user_can_open_admin_lists( int $user_id = 0 ): bool {
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();
		if ( $user_id <= 0 || ! self::user_can_access_todo( $user_id ) ) {
			return false;
		}

		return user_can( $user_id, 'edit_posts' );
	}

	/**
	 * Resolve the “all tasks” URL for overlay links and admin bar.
	 *
	 * @param int $user_id User ID (0 = current user).
	 * @return string
	 */
	public static function resolve_all_tasks_url( int $user_id = 0 ): string {
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();
		$url     = self::get_tasks_page_url();

		if ( '' === $url && self::user_can_open_admin_lists( $user_id ) ) {
			$url = admin_url( 'admin.php?page=' . Menu::LISTS_PAGE_SLUG );
		}

		/**
		 * Filter the URL used for “open all tasks” links.
		 *
		 * @param string $url     Resolved URL.
		 * @param int    $user_id User ID.
		 */
		return (string) apply_filters( 'forwp_todo_all_tasks_url', $url, $user_id );
	}

	/**
	 * Published pages for the settings page picker.
	 *
	 * @return list<array{id: int, title: string, edit_url: string, view_url: string}>
	 */
	public static function get_pages_catalog(): array {
		$pages = get_pages(
			array(
				'sort_column' => 'post_title',
				'sort_order'  => 'ASC',
				'post_status' => array( 'publish', 'draft' ),
			)
		);

		$items = array();
		foreach ( $pages as $page ) {
			$items[] = array(
				'id'       => (int) $page->ID,
				'title'    => (string) get_the_title( $page ),
				'edit_url' => (string) get_edit_post_link( $page->ID, 'raw' ),
				'view_url' => (string) get_permalink( $page ),
			);
		}

		return $items;
	}

	/**
	 * Users who may browse every task (Lists kanban + REST), not only own/assigned.
	 *
	 * @param int $user_id User ID (0 = current user).
	 * @return bool
	 */
	public static function user_can_view_all_tasks( int $user_id = 0 ): bool {
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();
		if ( $user_id <= 0 ) {
			return false;
		}

		$settings = self::get();
		$allowed  = in_array( $user_id, $settings['super_admin_user_ids'], true );

		if ( ! $allowed && empty( $settings['visibility_rules'] ) ) {
			$allowed = user_can( $user_id, 'edit_posts' );
		}

		/**
		 * Filter whether a user may view all tasks (not only authored/assigned).
		 *
		 * @param bool $allowed Whether the user may view all tasks.
		 * @param int  $user_id User ID.
		 */
		return (bool) apply_filters( 'forwp_todo_user_can_view_all_tasks', $allowed, $user_id );
	}

	/**
	 * Roles and users for the settings access picker.
	 *
	 * @return array{roles: list<array{slug: string, label: string}>, users_by_role: array<string, list<array{id: int, name: string}>>}
	 */
	public static function get_access_catalog(): array {
		$roles         = array();
		$users_by_role = array();

		foreach ( wp_roles()->roles as $slug => $role ) {
			$roles[] = array(
				'slug'  => (string) $slug,
				'label' => translate_user_role( (string) $role['name'] ),
			);

			$users = get_users(
				array(
					'role'    => $slug,
					'fields'  => array( 'ID', 'display_name' ),
					'orderby' => 'display_name',
					'order'   => 'ASC',
				)
			);

			$users_by_role[ $slug ] = array();
			foreach ( $users as $user ) {
				$users_by_role[ $slug ][] = array(
					'id'   => (int) $user->ID,
					'name' => (string) $user->display_name,
				);
			}
		}

		$admin_users = array();
		$admins      = get_users(
			array(
				'capability' => 'manage_options',
				'fields'     => array( 'ID', 'display_name' ),
				'orderby'    => 'display_name',
				'order'      => 'ASC',
			)
		);

		foreach ( $admins as $user ) {
			$admin_users[] = array(
				'id'   => (int) $user->ID,
				'name' => (string) $user->display_name,
			);
		}

		return array(
			'roles'         => $roles,
			'users_by_role' => $users_by_role,
			'admin_users'   => $admin_users,
		);
	}

	/**
	 * @param int $user_id User ID (0 = current user).
	 * @return bool
	 */
	public static function user_can_access_todo( int $user_id = 0 ): bool {
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();
		if ( $user_id <= 0 ) {
			return false;
		}

		$rules = self::get()['visibility_rules'];
		if ( empty( $rules ) ) {
			/**
			 * Filter TODO access for integrations and bundle overrides.
			 *
			 * @param bool $allowed Whether the user may access TODO.
			 * @param int  $user_id User ID.
			 */
			return (bool) apply_filters( 'forwp_todo_user_can_access', user_can( $user_id, 'edit_posts' ), $user_id );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$allowed = false;

		foreach ( $rules as $rule ) {
			if ( ! in_array( $rule['role'], (array) $user->roles, true ) ) {
				continue;
			}

			if ( 'all' === $rule['mode'] ) {
				$allowed = true;
				break;
			}

			if ( in_array( $user_id, $rule['user_ids'], true ) ) {
				$allowed = true;
				break;
			}
		}

		/**
		 * Filter TODO access for integrations and bundle overrides.
		 *
		 * @param bool $allowed Whether the user may access TODO.
		 * @param int  $user_id User ID.
		 */
		return (bool) apply_filters( 'forwp_todo_user_can_access', $allowed, $user_id );
	}

	/**
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'forwp_todo_hide_admin_bar', array( __CLASS__, 'filter_hide_admin_bar' ) );
		add_filter(
			'user_has_cap',
			static function ( array $allcaps, array $caps, array $args, \WP_User $user ): array {
				unset( $args );

				if ( ! in_array( self::ACCESS_CAP, $caps, true ) ) {
					return $allcaps;
				}

				$allcaps[ self::ACCESS_CAP ] = self::user_can_access_todo( (int) $user->ID );

				return $allcaps;
			},
			10,
			4
		);
	}

	/**
	 * @param bool $hide Default from overlay.
	 * @return bool
	 */
	public static function filter_hide_admin_bar( bool $hide ): bool {
		unset( $hide );

		return self::should_hide_admin_bar();
	}

	/**
	 * @return bool
	 */
	public static function is_frontend_enabled(): bool {
		return (bool) self::get()['frontend_enabled'];
	}

	/**
	 * @return bool
	 */
	public static function should_hide_admin_bar(): bool {
		return (bool) self::get()['hide_admin_bar'];
	}

	/**
	 * @return bool
	 */
	public static function show_admin_bar_todo(): bool {
		return (bool) self::get()['show_admin_bar_todo'];
	}
}

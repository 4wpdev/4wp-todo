=== 4WP TODO ===
Contributors: 4wpdev
Tags: tasks, checklist, team, project management, notes
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Team tasks and checklists inside WordPress — Gutenberg notes, kanban lists, frontend overlay, and REST for the 4WP ecosystem.

== Description ==

**4WP TODO** helps your team capture action items on pages and posts, organize them in kanban-style lists, and track completion without leaving WordPress.

Tasks are stored as native **Gutenberg notes** (`comment_type = note`) with 4WP metadata for category, assignee, due date, workflow, and page context. Other plugins can create tasks via `forwp_todo_create_task()` or the REST API.

A plugin by [4wp.dev](https://4wp.dev/). **4WP** is our project brand; this plugin is not affiliated with, endorsed, or sponsored by WordPress.

= Key features =

* **Frontend overlay** — floating panel on the public site for logged-in team members (FAB + task list)
* **Admin kanban** — **4WP TODO → TODO Lists** with columns (Assigned to me, Due tomorrow, per-page lanes)
* **Dashboard** — open/closed counts and activity overview
* **Taxonomy dimensions** — Category, Status, Workflow (manage terms under Categories / Statuses / Workflows)
* **Settings** — frontend overlay toggle, admin bar link, **Who can see TODO** visibility rules, optional tasks page with block
* **Gutenberg block** — `forwp/todo-tasks` for a full kanban on a page
* **REST API** — `forwp-todo/v1` for admin React UI and integrations
* **Integration API** — PHP helpers and discovery endpoints for other 4WP plugins

= How it works =

1. Activate the plugin. Users with access see **4WP TODO** in wp-admin and the frontend overlay when enabled.
2. Create tasks from the overlay, admin lists, or programmatically.
3. Assign categories, due dates, and workflows; close notes when done.
4. Configure **Settings → Who can see TODO** to limit access by role and user.

Requires WordPress **6.4+** with block editor notes support.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/4wp-todo/` or install from the Plugins screen.
2. Activate **4WP TODO**.
3. Open **4WP TODO → Dashboard** or **TODO Lists** in wp-admin.
4. Optional: open **Settings** to configure overlay, admin bar, visibility rules, and a dedicated tasks page.

== Frequently Asked Questions ==

= Can other plugins create tasks? =

Yes. Call `forwp_todo_create_task( $args )` in PHP or use `POST /wp-json/forwp-todo/v1/tasks` with a logged-in user who has TODO access.

= How is access controlled? =

By default, users with `edit_posts` can use TODO. When **visibility rules** are configured under Settings, a user must match at least one rule (role + all users or selected users). Capability: `forwp_todo_access`.

= Where are tasks stored? =

As WordPress **notes** on posts (core comment type `note`) with 4WP comment meta — not a separate custom post type for each task.

= Does this require other 4WP plugins? =

No. It runs standalone. Optional integrations use hooks and REST documented in the Integration API.

= Is there a public frontend for visitors? =

The overlay and task lists are for logged-in users with TODO access only.

== Screenshots ==

1. Admin TODO Lists kanban
2. Frontend overlay panel on a page
3. Settings — visibility rules and overlay options

== Changelog ==

= 1.0.2 =
* REST: GET `/meta` no longer lists every site user or every post with notes for TODO users who cannot view all tasks. The catalog is limited to people and posts on notes they can already see.

= 1.0.1 =
* REST: `POST /tasks` requires `edit_post` on the target post (or TODO access for the configured storage post); dedicated `permission_callback` and repository guard.
* REST: listing tasks by `post_id` returns 403 when the user cannot attach notes to that post (unless they can view all tasks).

= 1.0.0 =
* Initial WordPress.org release: Gutenberg note tasks, admin Dashboard and TODO Lists kanban, frontend overlay.
* Settings: overlay toggle, admin bar link, visibility rules, optional tasks page with `forwp/todo-tasks` block.
* REST API (`forwp-todo/v1`) and Integration API for other plugins.
* Taxonomies: Category, Status, Workflow with admin management screens.

== Upgrade Notice ==

= 1.0.2 =
Security: GET `/meta` no longer enumerates all users and notes-posts for limited TODO-access users.

= 1.0.1 =
Security fix: REST task creation verifies the user may edit the target post before attaching a note.

= 1.0.0 =
Initial public release — team tasks and checklists using native WordPress notes.

=== 4WP Todo ===
Contributors: 4wpdev
Tags: tasks, checklist, team, project management, workflow
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Team tasks and checklists for the 4WP plugin ecosystem.

== Description ==

**4WP Todo** helps your team capture action items, review them as a checklist, and track completion across 4WP projects.

Use categories (QA, Development, SEO, Content, For client), assign tasks, set due dates, and link tasks to pages or posts. Other 4WP plugins can create tasks programmatically via `forwp_todo_create_task()`.

This is an early scaffold (0.1.0): admin checklist UI, internal storage, and a basic REST API. Frontend overlays and deep integrations come next.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/4wp-todo/` or install from WordPress.org when published.
2. Activate **4WP Todo** through the **Plugins** screen.
3. Open **4WP Todo → Tasks** in wp-admin.

== Frequently Asked Questions ==

= Can other plugins create tasks? =

Yes. Call `forwp_todo_create_task( $args )` or fire the `forwp_todo_create_task` action with the same argument shape.

= Is this a full project management tool? =

Not yet. The MVP focuses on lightweight team checklists inside WordPress.

== Changelog ==

= 0.1.0 =
* Initial scaffold: task storage, admin checklist page, REST list/create/update, integration hooks.

=== LogTrail - User Activity Logs ===
Contributors: nsgawli
Tags: activity log, audit log, user activity, security, woocommerce
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

LogTrail is a user activity log & audit log plugin that tracks logins, content changes, WooCommerce, and site settings in real time.

== Description ==

**LogTrail - User Activity Logs** is an easy & powerful WordPress activity log plugin that keeps a complete audit trail of everything that happens on your site. From failed login attempts to post edits, plugin changes, and WooCommerce orders, LogTrail records the user, timestamp, IP address, and contextual details behind every event — so you always know who did what, and when.

Whether you're a site owner who wants peace of mind, an agency managing client sites, or a WooCommerce store tracking orders and product changes, LogTrail gives you a searchable, filterable activity log dashboard without slowing your site down.

= Why use an activity log plugin? =

Without a user activity log, it's nearly impossible to answer basic questions after something goes wrong: Who deleted that page? Who changed the site's permalink structure? Was that a failed login attempt or a real intrusion? LogTrail answers those questions automatically by logging activity as it happens, in a dedicated database table, so nothing is missed.

= Track user & security activity =

* Log successful and failed login attempts.
* Track user registrations, profile updates, and role changes.
* Monitor password changes and account-level activity.
* Keep a security audit log to help spot suspicious behavior early.

= Track content changes =

* Log post, page, and custom post type changes — created, published, updated, trashed, and restored.
* Log comment activity, including status changes and deletions.
* Track media library uploads, updates, and deletions.
* Log navigation menu changes.
* Track widget changes.

= Track site & plugin activity =

* Log plugin activation, deactivation, installs, and updates.
* Log theme activation, installs, and updates.
* Log changes to WordPress core settings (site title, URL, permalinks, timezone, and more).

= WooCommerce activity log =

If WooCommerce is active, LogTrail automatically extends its logging to your store:

* Log new WooCommerce orders, order status changes, edits, refunds, and deletions.
* Track product creation, updates, stock/status changes, and deletions.
* Log product category changes and deletions.
* Track coupon and product review activity.

= Dashboard, reports & exports =

* Searchable, filterable activity log dashboard inside wp-admin.
* At-a-glance activity summary via an optional dashboard widget.
* Daily and weekly email activity reports sent to the recipients you choose.
* Export logs to CSV for reporting, audits, or compliance needs.
* Automatic cleanup of old log entries with a configurable retention period.

= Fine-grained control over what gets logged =

* Exclude specific users, roles, or IP addresses from being logged.
* Exclude specific post types, statuses, plugins, themes, menus, or widgets.
* Exclude custom post meta and user meta fields.
* Choose from Essential, Recommended, or Complete event presets, or enable individual events yourself.

= Privacy & security by design =

* Activity logs are only accessible to administrators (`manage_options` capability).
* Integrates with WordPress's built-in Personal Data Export and Erase Personal Data tools, so LogTrail-collected data is included in GDPR data requests.
* Does not send any data to external servers — everything stays on your site.

== Contribute ==
LogTrail User Activity Logs is an open-source project. You can view the full sources (unminified JS) and contribute to the project on GitHub: [Click here](https://github.com/nsgawli/wp-logtrail)

== External Services ==
This plugin does not connect to external services for its functionalities.

== Installation ==

1. Upload the `logtrail` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Visit the "LogTrail" menu in wp-admin to view and configure activity logs.

= Using The WordPress Dashboard =

1. Navigate to the 'Add New' in the plugins dashboard
2. Search for LogTrail User Activity Logs
3. Click 'Install Now'
4. Activate the plugin in the plugin dashboard

= Uploading in WordPress Dashboard =

1. Download `logtrail.zip` from this page
2. Navigate to the 'Add New' in the plugins dashboard
3. Navigate to the 'Upload' area
4. Select `logtrail.zip` from your computer
5. Click 'Install Now'
6. Activate the plugin in the plugin dashboard

= Using FTP =

1. Download `logtrail.zip` from this page
2. Extract the `logtrail` directory to your computer
3. Upload the `logtrail` directory to the `/wp-content/plugins/` directory
4. Activate the plugin in the plugin dashboard

== Frequently Asked Questions ==

= What does LogTrail actually log? =

By default LogTrail logs authentication events (logins, logouts, failed logins), content changes (posts, pages, custom post types, comments, media), user account changes, menu and widget changes, plugin and theme activity, and core WordPress settings changes. You can choose an Essential, Recommended, or Complete preset, or select individual events to log from the Events screen.

= Where are the logs stored? =

Logs are stored in a dedicated database table created by the plugin, separate from the WordPress options and post tables.

= Does this plugin slow down my site? =

Logging runs on standard WordPress action and filter hooks and writes to a dedicated table, so the impact on page load times is minimal.

= Does LogTrail work with WooCommerce? =

Yes. When WooCommerce is active, LogTrail automatically starts logging WooCommerce orders, products, product categories, coupons, and reviews — no extra setup required.

= Can I control how long logs are kept? =

Yes. LogTrail can automatically delete log entries older than a period you choose (in days, months, or years) so your database doesn't grow indefinitely. Automatic cleanup can also be turned off if you want to keep logs indefinitely.

= Can I exclude certain users, roles, or IP addresses from being logged? =

Yes. The Exclude settings let you leave out specific users, roles, IP addresses, post types, post statuses, plugins, themes, menus, widgets, and even specific post meta or user meta keys.

= Can I export the activity logs? =

Yes. Logs can be exported to CSV directly from the activity log dashboard, which is useful for reporting, audits, or compliance records.

= Can I get email reports of site activity? =

Yes. LogTrail can send daily and/or weekly email summaries of site activity to the recipients you configure, so you don't have to log in to check the dashboard.

= Who can view the activity logs? =

Only users with the `manage_options` capability (administrators, by default) can access the LogTrail dashboard, settings, and event logs.

= Is LogTrail GDPR-friendly? =

LogTrail does not send any data to external services — all logs stay in your site's own database. LogTrail also hooks into WordPress's built-in "Export Personal Data" and "Erase Personal Data" privacy tools, so any LogTrail data tied to a user is included when you handle a data request.

= What happens to the logs if I deactivate the plugin? =

Logs are preserved when the plugin is deactivated. Uninstalling the plugin removes the stored logs.

== Screenshots ==

1. Activity Log Dashboard
2. Activity Logs
3. Events Registry
4. Settings
5. Email Settings
6. Log Exclude Settings

== Changelog ==

= 1.0.0 =
* Initial release.

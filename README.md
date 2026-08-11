# LogTrail - User Activity Logs

Easy & powerful WordPress activity log plugin that tracks logins, content changes, WooCommerce activity, and site settings changes in real time.

## Overview
LogTrail keeps a complete audit trail of everything that happens on your WordPress site. From failed login attempts to post edits, plugin changes, and WooCommerce orders, LogTrail records the user, timestamp, IP address, and contextual details behind every event in a dedicated database table — so you always know who did what, and when. Logs are searchable and filterable from a dashboard inside wp-admin, without slowing your site down.

## Features

**User & security activity**
- Successful and failed login attempts
- User registrations, profile updates, and role changes
- Password changes and account-level activity

**Content changes**
- Posts, pages, and custom post types — created, published, updated, trashed, restored
- Comment activity, including status changes and deletions
- Media library uploads, updates, and deletions
- Navigation menu and widget changes

**Site & plugin activity**
- Plugin activation, deactivation, installs, and updates
- Theme activation, installs, and updates
- Core WordPress settings changes (site title, URL, permalinks, timezone, and more)

**WooCommerce activity** (auto-enabled when WooCommerce is active)
- New orders, order status changes, edits, refunds, and deletions
- Product creation, updates, stock/status changes, and deletions
- Product category, coupon, and product review activity

**Dashboard, reports & exports**
- Searchable, filterable activity log dashboard in wp-admin
- Optional dashboard widget with an at-a-glance activity summary
- Daily and weekly email activity reports
- CSV export for reporting, audits, or compliance
- Configurable automatic cleanup of old log entries

**Fine-grained control**
- Exclude specific users, roles, or IP addresses
- Exclude specific post types, statuses, plugins, themes, menus, or widgets
- Exclude custom post meta and user meta fields
- Essential, Recommended, or Complete event presets, or enable individual events

**Privacy & security**
- Logs are only accessible to administrators (`manage_options` capability)
- Integrates with WordPress's Personal Data Export / Erase Personal Data tools
- Does not send any data to external servers — everything stays on your site

## Requirements
- WordPress 6.2+
- PHP 7.4+
- WooCommerce (optional, for WooCommerce activity logging)

## Installation & Build
This repo ships source files that need to be built into the `build/` directory before the plugin will run.

1. Install dependencies:
   - `npm install`
   - `composer install`
2. Build assets:
   - `npm run start` — development build with watch
   - `npm run build` — production build
3. Activate **LogTrail - User Activity Logs** from the WordPress Plugins screen.

## Usage
1. After activation, open the **LogTrail** menu in wp-admin.
2. Review the activity log dashboard, or use the Events screen to choose an Essential/Recommended/Complete preset (or enable individual events).
3. Configure exclusions (users, roles, IPs, post types, plugins, themes, etc.) under the Exclude settings.
4. Set up daily/weekly email reports and log retention under Settings.
5. Export logs to CSV as needed for audits or compliance.

## Translation
To generate the `.pot` file for translations:
```bash
bash make-pot.sh
```
This runs a production build and writes `languages/logtrail.pot` using `wp i18n make-pot`.

## Documentation & Support
See [readme.txt](readme.txt) for the full WordPress.org plugin description, FAQ, and changelog.

## Contributing
LogTrail is open source. View the source and contribute on GitHub: https://github.com/nsgawli/wp-logtrail

## License
GPLv3 or later. See [LICENSE](LICENSE) for details.

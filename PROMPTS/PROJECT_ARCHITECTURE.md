# LogTrail Pro Project Architecture

## Document Purpose

This document defines the architecture, conventions, and development rules for the LogTrail Pro WordPress plugin.
Before making code changes, review `AI_RULES.md` for mandatory AI agent development rules.

Primary audience:
- AI coding agents (ChatGPT Codex, Claude Code, Gemini CLI, etc.)
- Human contributors who need to extend the plugin without breaking existing behavior

This guide is based on the current repository implementation and observed project conventions.
When a rule below is marked as a principle or target, it is still expected even if parts of the current codebase are in transition.

## Project Overview

LogTrail Pro is a WordPress admin-focused activity logging plugin.
It tracks key site events (authentication, users, content, plugins, themes, menus, widgets, settings), stores logs in a dedicated database table, and exposes data through a REST API consumed by React admin applications.

Current runtime layers:
- PHP plugin bootstrap and module loader
- Modular activity logger classes attached to WordPress hooks
- Data model layer for persistent log storage and analytics queries
- REST API controllers for logs, settings, and dashboard data
- React admin interfaces for activity logs, event settings, dashboard, and admin settings
- Shared React framework components and hooks under `src/framework`

High-level request flow:
1. WordPress action/filter fires
2. Specific logger class collects metadata and context
3. Event settings check determines whether the action is enabled
4. Exclusion engine checks if event should be suppressed
5. Log record is inserted into `wp_logtrail_logs`
6. React admin UI fetches records and aggregates via `/logtrail/v1/*`

## Project Goals and Philosophy

Core goals:
- Provide reliable, structured activity visibility for site administrators
- Keep logging architecture modular and extensible
- Preserve backward compatibility wherever possible
- Prioritize WordPress-native patterns and compatibility
- Keep the codebase maintainable for long-term evolution

Philosophy in practice:
- Security-first by default (sanitize, validate, escape, permission checks)
- Minimal coupling between modules (separate logger classes, dedicated REST controllers, focused services)
- Reuse before create (framework components, helper utilities, model methods)
- Incremental improvements over risky rewrites
- Avoid unnecessary dependencies and frameworks

## Free vs Pro Strategy

Repository facts:
- This repository is the Pro plugin package (`Plugin Name: LogTrail Pro - User Activity Logs`)
- Code is namespaced and structured as a standalone plugin

Project strategy for free/pro compatibility:
- Keep domain model and event taxonomy generic (`Events`, `Actions`, `Severity`) so behavior can remain consistent across editions
- Preserve extension points (`logtrail_registered_events`, `logtrails_admin_submenus`) so feature layering remains possible
- Avoid hardcoding implementation details that would block shared behavior between editions
- Keep public REST shapes and option names stable to reduce migration friction
- Prefer additive Pro enhancements rather than breaking core logging semantics

Guideline for contributors:
- If adding Pro-only functionality, isolate it in a dedicated module and avoid changing existing public contracts unless absolutely required

## Plugin Folder Structure

Top-level structure (important directories):
- `includes/` PHP source (autoloaded via Composer PSR-4)
- `src/` React/JS source
- `build/` compiled JS/CSS assets from `@wordpress/scripts`
- `vendor/` Composer autoload

Important PHP module paths:
- `includes/ActivityLoggers/` logger architecture (one class per domain)
- `includes/Models/` database model classes
- `includes/RestApi/` REST controllers and base controller
- `includes/Utils/` utility helpers (e.g., exclusion matching)
- `includes/Installation/` installation, defaults, and upgrade scaffolding
- `includes/Dashboard/` dashboard service logic
- `includes/EventSettings/` event registry, presets, enable/disable settings
- `includes/Admin/` admin menu/page registration and asset enqueueing

React source paths:
- `src/activity-logs/` activity log UI
- `src/events-settings/` event control UI
- `src/dashboard/` dashboard/reporting UI
- `src/admin-settings/` plugin settings UI
- `src/framework/` reusable component system, hooks, middleware, icons, utilities

Build and tooling:
- Composer autoload: `LogTrail\\` => `includes/`
- Webpack aliases:
  - `@` => `src/`
  - `@framework` => `src/framework/`
- Entry points compiled to `build/*`:
  - `activity-logs/index`
  - `admin-settings/index`
  - `events-settings/index`
  - `dashboard/index`
  - `common/index`

## Coding Standards

Project-wide standards:
- Follow WordPress Coding Standards and PHPCS
- Use detailed PHPDoc comments for classes and methods
- Keep code modular and readable
- Prefer small, focused services over monolithic classes
- Maintain consistency with neighboring files before introducing new patterns
- Keep backward compatibility whenever possible

Formatting and style expectations:
- Respect existing style in the touched file
- Avoid broad unrelated refactors in feature/fix PRs
- Keep functions single-purpose where practical

## PHP Standards

Required:
- WordPress coding style and WordPress APIs
- PSR-4 autoloading via Composer
- Namespacing under `LogTrail\...`
- Defensive checks for direct access (`defined( 'ABSPATH' ) || exit` pattern)
- Detailed PHPDoc for public/protected APIs

Plugin bootstrap conventions:
- Constants are defined centrally in plugin bootstrap
- Runtime interface flags are set (`WPLT_ADMIN_INTERFACE`, `WPLT_FRONTEND_INTERFACE`, `WPLT_REST_REQUEST`)
- Module loading is conditional in `Init::run()` (admin, REST, AJAX, frontend)

Database safety conventions:
- Use `$wpdb->prepare()` for dynamic SQL
- Use typed format arrays for inserts
- Avoid unsafe interpolation for untrusted values

## JavaScript/React Standards

Framework and libraries currently used:
- React + ReactDOM
- `@wordpress/api-fetch`
- `@wordpress/url` and WordPress package ecosystem
- `react-router-dom` (HashRouter in admin apps)
- `react-hook-form`
- `recharts`, `react-icons`, `react-select`, and selected utility packages

Required standards:
- Use existing shared framework components from `src/framework/components`
- Use existing hooks from `src/framework/hooks` where possible
- Use `@wordpress/api-fetch` for admin API requests
- Keep page modules separated by feature (`components`, `hooks`, `services`, `utils`)
- Reuse existing UI components before creating new ones

Do not:
- Introduce Tailwind
- Introduce Bootstrap
- Add unnecessary UI framework layers

## REST API Architecture

Namespace:
- `logtrail/v1`

Base controller responsibilities:
- Common permission callback (`current_user_can( 'manage_options' )`)
- Standard success payload shape:
  - `success: true`
  - `data: ...`
- Standard WP_Error helper for failures

Registered controller modules:
- Logs: `includes/RestApi/Logs/Logs.php`
- Dashboard: `includes/RestApi/Dashboard/DashboardController.php`
- Settings General: `includes/RestApi/Settings/General.php`
- Settings Events: `includes/RestApi/Settings/Events.php`
- Settings Exclude: `includes/RestApi/Settings/Exclude.php`

Implemented route groups:
- `/logs` (GET list, POST create)
- `/logs/{id}` (GET details)
- `/logs/stats` (GET summary stats)
- `/logs/export` (GET export rows)
- `/dashboard` (GET dashboard analytics)
- `/settings/general-settings` (GET/PUT)
- `/settings/events` (GET/PUT)
- `/settings/exclude-settings` (GET/PUT)
- `/settings/exclude-settings/defaults` (GET)
- `/settings/exclude-settings/options` (GET)
- `/settings/exclude-settings/users` (GET)

REST conventions:
- Sanitize request params at the controller boundary
- Keep response shape stable for existing React clients
- Permission checks are mandatory for privileged routes
- Prefer model/service calls over inline business logic in controllers

## Database Architecture

Primary table:
- `{$wpdb->prefix}logtrail_logs`

Columns:
- `id` bigint unsigned, PK
- `timestamp` datetime
- `user_id` bigint unsigned
- `ip_address` varchar(45)
- `event_type` varchar(100)
- `object_type` varchar(100)
- `object_id` bigint unsigned
- `action` varchar(100)
- `message` text
- `before_data` longtext
- `after_data` longtext
- `context` text
- `severity` varchar(20)
- `site_id` int

Indexes currently present:
- `idx_user_id`
- `idx_event_type`
- `idx_timestamp`
- `idx_object_type`
- `idx_action`
- `idx_site_id`

Data access layer:
- `LogTrail\Models\LogTrail_Logs`
- Handles insert, bulk insert, filtering, counting, and dashboard aggregates

Performance principles for large tables:
- Query only required rows (pagination/limits)
- Keep filters index-aware where possible
- Avoid expensive unbounded scans in UI endpoints
- Preserve prepared statement usage
- Consider millions-of-records behavior before introducing heavy queries

## Logger Architecture

Core design:
- Modular logger architecture using separate logger classes
- All loggers inherit from `AbstractLogger`
- Logger classes live under `includes/ActivityLoggers/`

Current logger modules:
- `UserActivityLogger`
- `PostActivityLogger`
- `PluginActivityLogger`
- `ThemeActivityLogger`
- `WPSettingsActivityLogger`
- `MenuActivityLogger`
- `WidgetActivityLogger`

Base logger responsibilities (`AbstractLogger`):
- Inject logs model
- Build default log payload
- Collect common context (user, roles, IP, user-agent, URL)
- Enforce event enablement (`EventSettings::is_enabled`)
- Enforce exclusion policy (`ExcludeHelper::should_exclude`)
- Serialize context to JSON before insert

Design rules:
- One logger class per domain of WordPress events
- Hook registration done in logger constructor via dedicated `register_hooks()`
- Keep per-hook methods concise and focused
- Include meaningful context for audit usefulness

## Event Registry Architecture

Key modules:
- `EventRegistry` defines categories/actions metadata
- `EventSettings` stores enabled/disabled state per event/action
- `EventPresets` defines preset profiles (`essential`, `recommended`, `complete`)

Event taxonomy constants:
- `Events`: authentication, user, content, plugin, theme, menu, widget, settings
- `Actions`: create/update/delete/restore/login/logout/etc.
- `Severity`: info/warning/error/critical/debug

Extensibility:
- `logtrail_registered_events` filter allows external event registration/override

Behavior:
- Event enabled state defaults to true for all known actions
- Logger insert path checks enabled state before writing records

## Dashboard and Reporting Architecture

Backend:
- `DashboardService` orchestrates aggregated analytics
- Model provides summary/time-series/distribution/top lists/recent alerts

Frontend:
- `src/dashboard` feature app
- Widgets for timeline, severity, categories, top users, top events, and alerts
- REST source: `/logtrail/v1/dashboard`

Reporting principles:
- Keep dashboard queries bounded by date windows
- Reuse model aggregation methods
- Keep payloads chart-ready to reduce frontend transformation cost

## Exclusion Engine Architecture

Core module:
- `includes/Utils/ExcludeHelper.php`

Purpose:
- Centralized suppression logic that prevents writing excluded records

Supported exclusion dimensions (as implemented):
- Users
- Roles
- IPs
- Post types
- Post statuses
- Post meta keys
- User meta keys
- Plugins
- Themes

Pattern matching:
- Case-insensitive matching
- Supports wildcard patterns using `*`
- Utility methods normalize and compare values

Settings storage:
- Option: `logtrail_exclude_settings`
- Defaults installed under `includes/Installation/Settings/Exclude.php`

## Settings Architecture

Settings domains:
- General settings (`logtrail_general_settings`)
- Event settings (`logtrail_event_settings`)
- Exclude settings (`logtrail_exclude_settings`)

Backend structure:
- Installation defaults in `includes/Installation/Settings/*`
- REST read/write in `includes/RestApi/Settings/*`

Frontend structure:
- `src/admin-settings/general-settings`
- `src/admin-settings/exclude-settings`
- `src/admin-settings/email-settings`
- `src/admin-settings/advanced-settings`

Design rules:
- Normalize/sanitize input at REST boundary
- Keep option schemas backward-compatible
- Add new settings keys as additive changes

## React Component Guidelines

Current architecture:
- Shared UI primitives under `src/framework/components`
- Feature modules compose framework components rather than custom duplications

Required rules:
- Existing reusable components should always be reused instead of creating duplicates
- Reuse existing helpers and hooks before adding new abstractions
- Keep component APIs consistent with nearby components
- Keep feature-specific logic in feature hooks/services
- Keep presentation and transport concerns separated

State and side-effect conventions:
- Hooks for data loading (`useLogs`, `useDashboard`, `useEvents`)
- Services for API calls (`services/*.js`)
- Utility functions for serialization/query assembly

## CSS Guidelines

Mandatory:
- Use project CSS only
- Do not introduce Tailwind or Bootstrap
- Prefix all CSS classes and IDs with `wptl`

Current style architecture:
- Feature-local CSS files (component/page scoped)
- Shared framework component CSS under `src/framework/components/**/index.css`
- Common CSS bundle entry under `src/common/common.css`

Guidelines:
- Prefer local component styles over global overrides
- Keep selectors explicit and predictable
- Preserve existing naming structure (`wptl-...`)

## Security Guidelines

Non-negotiable rules:
- Security first: sanitize, validate, and escape all input/output
- Follow WordPress nonce and capability checks for all privileged actions
- Use prepared SQL statements

Current enforcement points:
- REST permission callback via capability check
- Param sanitization in REST controllers
- SQL preparation in model and direct DB lookups

Contributor checklist for new endpoints/actions:
- Add capability checks
- Sanitize all request fields
- Escape output when rendering into HTML
- Use prepared queries for dynamic SQL

## Performance Guidelines

Target environment:
- Sites with very large activity log volumes (including millions of rows)

Required practices:
- Paginate list endpoints
- Avoid returning oversized payloads by default
- Use indexes and selective filters
- Keep aggregate windows bounded where possible
- Avoid unnecessary repeated API calls (cache when suitable)

Frontend-specific:
- Use existing API cache middleware where available
- Avoid redundant requests in effect loops
- Keep large table rendering efficient

## Internationalization Guidelines

Existing i18n stack:
- Text domain: `logtrail`
- PHP uses `__`, `esc_attr__`, etc.
- JS uses `@wordpress/i18n`
- Script translations loaded with `wp_set_script_translations`

Rules:
- Wrap user-facing strings in translation functions
- Keep text domain consistent (`logtrail`)
- Preserve translation readiness in new screens and endpoints

Note:
- Some legacy artifacts (e.g., POT generation script naming/domain) should be aligned over time, but new work must follow current plugin text domain conventions.

## Naming Conventions

PHP:
- Namespace root: `LogTrail\`
- Class names in PascalCase
- Modules grouped by domain folder

Options and constants:
- Option names prefixed with `logtrail_`
- Plugin constants prefixed with `WPLT_`

REST:
- Namespace: `logtrail/v1`
- Route groups by domain (`logs`, `settings`, `dashboard`)

CSS:
- Class/id prefix: `wptl-`

JavaScript:
- Feature folders use kebab-case
- Component files mostly `index.js` + `index.css`

## Git Commit Guidelines

Observed project history uses Conventional Commit-like prefixes (for example `feat:`, `refactor:`).

Recommended commit rules:
- Use concise prefixes: `feat:`, `fix:`, `refactor:`, `perf:`, `docs:`, `chore:`
- Scope commit message to one logical change
- Avoid bundling unrelated refactors with feature work
- Mention compatibility-impacting changes explicitly
- Reference affected module domain in message when possible

## Future Development Principles

Required principles for all future work:
- Keep backward compatibility whenever possible
- Avoid breaking public APIs
- Prefer extensibility through hooks and filters
- Keep the plugin developer-friendly
- Prefer modular services over large monolithic classes
- Maintain consistency with existing project architecture before introducing new patterns
- Minimize dependencies and avoid framework churn

Architecture evolution strategy:
- Favor additive changes to existing contracts
- Introduce new module boundaries only when justified
- Keep migration/upgrade paths explicit

## Instructions for AI Coding Agents

Always follow these rules when generating or modifying code in this repository:

- Always inspect existing code before generating new code.
- Reuse existing helpers and utilities.
- Reuse existing UI components.
- Never create duplicate functionality.
- Preserve backward compatibility.
- Follow existing naming conventions.
- Keep files consistent with neighboring files.
- Minimize dependencies.
- Never introduce unnecessary frameworks.
- Prefer incremental changes over large rewrites.
- Generate production-quality code, not demo code.
- Ensure PHPCS compliance.
- Ensure WordPress.org compatibility.
- Consider performance on sites with millions of activity log records.
- Consider multisite compatibility where applicable.

Agent workflow checklist:
1. Locate existing domain module (logger, REST controller, settings page, model, framework component).
2. Reuse/extend that module first.
3. Validate security boundaries (capabilities, sanitization, prepared SQL).
4. Validate backward compatibility and response shape stability.
5. Keep CSS and component usage aligned with project conventions.

## Current Project Status

This status summary is based on current repository implementation and recent commit history.

### Completed or Substantially Implemented

- Plugin bootstrap and PSR-4 loading are in place.
- Core installation flow creates log table and default settings.
- Modular logger architecture is implemented with domain-specific logger classes.
- Event registry, presets, and enabled/disabled action settings are implemented.
- Exclusion engine is implemented with multiple exclusion dimensions.
- REST API for logs, settings (general/events/exclude), and dashboard is implemented.
- Activity Logs React app is implemented (table, filters, details, export, add log).
- Dashboard React app is implemented with multiple widgets and analytics views.
- Events Settings React app is implemented with presets and per-action toggles.
- General Settings and Exclude Settings admin screens are implemented.
- Shared React framework components are substantial and actively reused.

### Partially Implemented / In Progress

- Admin settings sections for Email and Advanced are scaffolded but not fully wired to backend persistence.
- Some UI actions (for example certain export actions inside detail drawers) are present as UI affordances and may need complete behavior wiring.
- API caching middleware exists and is used in selected settings resources; broader consistency can be improved.

### Scaffolded / Planned but Not Yet Implemented End-to-End

- `includes/Frontend/Autoloader.php` currently has no active frontend module logic.
- `includes/Ajax/Autoloader.php` is currently empty.
- Upgrade runner (`includes/Installation/Upgrades/Upgrade.php`) is currently a stub.
- Retention/automation jobs are planned to run via WP-Cron but are not yet fully implemented in this codebase.

### Architectural Strengths Today

- Clear module separation by domain (logger/rest/model/settings/dashboard)
- Strong reuse potential due to shared framework layer
- Good base for extensibility through filters and submenu hooks

### Recommended Near-Term Priorities

1. Complete settings modules that are currently scaffolded (email/advanced) with backend parity.
2. Implement automated retention workflows for auto-delete settings using scheduler infrastructure.
3. Expand multisite-specific handling where required (site-aware querying/administration behavior).
4. Add/strengthen test and lint gating for PHPCS and JS quality.
5. Continue hardening high-volume query paths for very large datasets.

## Appendix: Key Architectural Decisions (Quick Reference)

- Follow WordPress Coding Standards and PHPCS.
- Use detailed PHPDoc comments for classes and methods.
- PSR-4 autoloading and namespacing are mandatory.
- Activity loggers live under `includes/ActivityLoggers/`.
- Models live under `includes/Models/`.
- REST API modules live under `includes/RestApi/`.
- Utilities live under `includes/Utils/`.
- Installation modules live under `includes/Installation/`.
- Dashboard modules live under `includes/Dashboard/`.
- React admin interfaces use `@wordpress/api-fetch`.
- Reuse existing components and utilities before creating new ones.
- Do not introduce Tailwind or Bootstrap.
- Use project CSS and prefix selectors with `wptl`.
- Preserve backward compatibility and avoid public API breakage.
- Optimize database behavior for large log tables.
- Sanitize, validate, escape, and enforce permissions/nonces.
- Use prepared SQL statements.
- Prefer extensibility via hooks/filters.
- Prefer modular services over monoliths.
- Keep architecture-consistent, incremental, production-grade changes.

# LogTrail Pro — Free vs Pro Feature Roadmap

Legend: ✅ shipped in free today · 🔧 partially built / needs verification · ⭕ not started · 🆕 new item added in this pass (not in the original CSV)

This file is meant to be worked one row at a time. Background and rationale for every judgment call made here live in the accompanying plan discussion; the short version:
- No license/feature-gating mechanism exists yet at all (no `License` class, no Freemius/EDD). Anything shipping in this codebase today is simply free.
- Pro is planned as a **separate plugin/codebase** (see `free_pro_architecture.md`) that hooks into `@wordpress/hooks` filters (`logtrail.xxx`) exposed by free. Those filter points are not built yet — that's Phase 0 below and blocks every other Pro row.
- Two rows the original CSV marked Pro-only are already built and shipping in free (Scheduled Reports, Saved Search Filters). Decision: keep them Free — pulling shipped functionality would break existing users for no gain.
- Competitive research (Melapress WP Activity Log, Simple History, Stream, Logtivity, Activity Monitor Pro — 2026 pricing pages) was used to validate, correct, and extend the list. Items with no market precedent found anywhere are explicitly flagged **speculative** rather than presented as proven demand.

---

## Core Logging

| Feature | Free | Pro | Status | Notes |
|---|---|---|---|---|
| User Activity (Logins, Profiles, etc.) | Yes | Yes | 🔧 | `UserActivityLogger` exists, but no dedicated `Authentication_Logger` yet (login/logout/session/failed-login events from event-list.txt §1 aren't fully built). Needed to actually deliver this Free promise. |
| Content Changes (Posts, Pages, CPTs) | Yes | Yes | ✅ | `PostActivityLogger` |
| Plugin & Theme Activity | Yes | Yes | ✅ | `PluginActivityLogger`, `ThemeActivityLogger` |
| WordPress Core & Settings Changes | Yes | Yes | ✅ | `WPSettingsActivityLogger` |
| Widget & Menu Changes | Yes | Yes | ✅ | `WidgetActivityLogger`, `MenuActivityLogger` |
| Media Library Activity | Yes | Yes | ✅ | `MediaActivityLogger` |
| "Before & After" Value Logging | Limited (key WP options only) | Comprehensive (customizable) | 🔧 | Verify actual scope of "key options" during Phase 1. |
| Scheduled Tasks (Cron) Logging | Yes | Yes | ⭕ | event-list.txt §10 `Cron_Logger` not built yet. |
| Database Schema Change Logging | Yes | Yes | ⭕ | event-list.txt §11 `Database_Logger` not built yet. |
| 🆕 User-Agent / browser / device parsing | Yes | Yes | ⭕ | Near-zero-cost enrichment of data already captured alongside IP. |
| 🆕 "Last login" shown on user's own profile screen | Yes | Yes | ⭕ | Small UX win, high perceived value, cheap to build. |
| 🆕 Historical backfill/import on Pro upgrade | — | Yes | ⭕ | Reduces upgrade friction (Simple History pattern). |

## Log Viewer & Search

| Feature | Free | Pro | Status | Notes |
|---|---|---|---|---|
| Intuitive Log Viewer Interface | Yes | Yes | ✅ | `src/activity-logs` |
| Basic Keyword Search | Yes | Yes | ✅ | |
| Basic Filters (Date, User, Event Type) | Yes | Yes | ✅ | |
| Advanced Search Filters (IP range, severity, custom fields) | No | Yes | ⭕ | Market-validated Pro lever (Melapress, Simple History both gate this). |
| **Saved Search Filters** | **Yes** | **Yes** | ✅ | **Reclassified to Free.** `useSavedFilters.js` already ships in free; original CSV said Pro — decision made to keep it free rather than break existing users. |
| Boolean Search Operators | No | Yes | ⭕ | |
| Real-Time Activity Feed | Yes | Yes | 🔧 | Dashboard exists; true live-updating (WebSocket/AJAX push) behavior unconfirmed — verify before marketing as "real-time." |
| 🆕 Bulk select + bulk delete/export | Yes | Yes | ⭕ | Baseline table UX, not a premium lever. |
| 🆕 In-editor "Activity Panel" (inline per-post history) | No | Yes | ⭕ | Market-validated (Simple History Premium); reuses data `PostActivityLogger` already captures. |
| 🆕 Stealth/Hidden mode (hide log UI from chosen roles) | No | Yes | ⭕ | Market-validated (Simple History Premium); valuable for agencies hiding the log from client logins. |
| 🆕 WP-CLI support (query/export logs) | Yes | Yes | ⭕ | Stream ships this free; targets developers/agencies who become the Pro upsell audience. |

## Alerts & Notifications

| Feature | Free | Pro | Status | Notes |
|---|---|---|---|---|
| Basic Email Alerts for Critical Admin Actions | Limited | Yes | 🔧 | `EmailReportsService` is a scheduled digest, not event-triggered alerting — the actual alert engine doesn't exist yet. |
| Advanced Configurable Email Alerts | No | Yes | ⭕ | |
| SMS Alerts (gateway integration) | No | Yes | ⭕ | Twilio-style, per Melapress. |
| Slack & Webhook Notifications | No | Yes | ⭕ | |
| Threshold-Based Alerts | No | Yes | ⭕ | Reframed: fold into one general rule-based alert engine rather than standalone numeric-threshold logic (weak standalone market precedent — competitors sell rule-based alerts broadly, not this specifically). |
| 🆕 Rule-Based Smart Alerts / Anomaly Detection | No | Yes | ⭕ | Elevated from the team's own event-list.txt "AI ideas" section ("the one I'd build first") and validated by Activity Monitor Pro's premium AI-anomaly feature. Build this first among alerts — same engine also powers Threshold Alerts above. |

## Reporting

| Feature | Free | Pro | Status | Notes |
|---|---|---|---|---|
| Basic Log Export (CSV) | Yes | Yes | ✅ | `Logs.php` `/logs/export` route |
| Detailed Reports (HTML, PDF, CSV) | No | Yes | ⭕ | No PDF export exists yet. |
| **Scheduled Reports (Email Delivery)** | **Yes** | **Yes** | ✅ | **Reclassified to Free.** `EmailReportsService.php` + settings UI already ship in free; original CSV said Pro — decision made to keep it free. |
| Report Templates (Security, User Activity, Compliance) | No | Yes (customizable) | ⭕ | |
| Stats & Summary Dashboards | No | Yes | ⭕ | Renamed from "Activity Heatmaps or Charts" — no vendor markets heatmaps specifically; "stats/summary dashboard" is the real, validated framing. |
| 🆕 Compliance control-mapping (tag events to SOC2/HIPAA/PCI-DSS controls) | No | Yes | ⭕ | **Speculative** — no direct market precedent found, but makes "Report Templates" genuinely audit-defensible rather than a PDF skin. |

## User Session Management

| Feature | Free | Pro | Status | Notes |
|---|---|---|---|---|
| View Active Logged-in Users | No | Yes | ⭕ | Needs session-tracking infra; pair with `Authentication_Logger` build. |
| Remotely Terminate User Sessions | No | Yes | ⭕ | |

## Log Storage & Archiving

| Feature | Free | Pro | Status | Notes |
|---|---|---|---|---|
| Configurable Log Retention (e.g. up to 90 days) | Yes | Yes (indefinite option) | ✅ | `AutoDeleteLogsService.php` — confirm the actual enforced day-cap matches the "90 days" claim. |
| Manual Log Clearing | Yes | Yes | ✅ | |
| External Database Logging (MySQL) | No | Yes | ⭕ | |
| Log Archiving (local / off-site) | No | Yes (automated, off-site) | ⭕ | |
| SIEM Integration (Syslog, cloud SIEMs) | No | Yes | ⭕ | Melapress ships Splunk/CloudWatch/Loggly/Papertrail/Syslog at its Enterprise tier — good reference target list. |
| Off-Site Backups | No | Yes | ⭕ | Renamed from "Tamper-Proof ... (Encrypted)" — no vendor markets encryption as a standalone feature; treat it as an implementation detail of archiving, not a separate line item. |

## Security & Compliance

| Feature | Free | Pro | Status | Notes |
|---|---|---|---|---|
| IP Address handling | Yes (anonymized) | Yes (full/precise IP) | 🔧 | Reframed per Simple History's actual mechanic — anonymized-by-default is Free, precise/full IP storage is what Pro unlocks. Original CSV had this as flat Free/Free. |
| Advanced File Integrity Monitoring (FIM) | Limited (core WP scan) | Yes (FTP/direct edits, diff view) | ⭕ | Nothing built yet. Note: Melapress sells FIM as a fully separate product (Melapress File Monitor) — worth deciding whether this belongs inside LogTrail Pro at all, or as a future standalone product. |
| AI-Powered / Rule-Based Anomaly Detection | Limited | Yes (advanced) | ⭕ | Same underlying engine as the Alerts section above — don't build twice. |
| GDPR DSAR Report Generation | No | Yes | 🔧 | `PersonalDataExporter.php` already integrates with WP core's Privacy Tools (export/erase) — confirm how much of "DSAR generation" is already covered before scoping new Pro work. |
| 🆕 Exclude specific users/roles from logging | Yes | Yes | 🔧 | Already exists as an Exclude setting — make sure it's clearly surfaced/documented as a Free capability rather than buried, since it's a privacy baseline, not a premium lever. |
| GeoIP Integration (login/activity location) | No | Yes | ⭕ | Pairs naturally with anomaly alerts ("login from unfamiliar location"). |

## Access Control & Collaboration

| Feature | Free | Pro | Status | Notes |
|---|---|---|---|---|
| Role-Based Log Access Control | Yes | Yes | ✅ | Confirm actual scope (role-level vs. per-user). |
| Log Entry Bookmarking / Notes | No | Yes | ⭕ | Market-validated — Simple History Premium calls these "Sticky Events." |

## Integrations

| Feature | Free | Pro | Status | Notes |
|---|---|---|---|---|
| WooCommerce Logging | Limited (~38 events) | Yes (full, ~178 events) | 🔧 | Matches the `[FREE]` tags already in event-list.txt — existing split is sound, no change needed. |
| Page Builder Logging (Elementor, Beaver, Bricks) | No | Yes (deep, granular) | ⭕ | |
| 🆕 Shallow support for a few more top plugins (Gravity Forms, Yoast SEO, ACF) | Yes | — | ⭕ | Market correction: competitors ship *basic* third-party plugin sensors free (Melapress, Simple History) — split this the same way WooCommerce already is. |
| Other Popular Plugins — deep coverage + LMS breadth | No | Yes (broader support) | ⭕ | Pro keeps the deeper granularity + LMS plugins on top of the Free basics above. |
| Developer API for Custom Logging | Yes | Yes | 🔧 | The `@wordpress/hooks` filter points planned in `free_pro_architecture.md` are still unbuilt — this is Phase 0 foundation work and blocks every other Pro extensibility feature. |
| 🆕 No-code "Custom Event Builder" (map any hook to a log entry via UI) | No | Yes | ⭕ | **Speculative** — no market precedent found anywhere; a genuine differentiation bet, not proven demand. |
| 🆕 Security-plugin bridge (Wordfence/Sucuri events in the same timeline) | No | Yes | ⭕ | **Speculative** — no market precedent found. |
| 🆕 Read-only external REST API (API-key auth) | No | Yes | ⭕ | **Speculative** — no vendor advertises this; only worth building against a specific customer ask. |

## Multisite & Agency

| Feature | Free | Pro | Status | Notes |
|---|---|---|---|---|
| Multisite Support (Network Mode) | No | Yes | ⭕ | |
| 🆕 White-Label / Agency Branding | No | Yes | ⭕ | Standard agency-tier pricing driver across the WP plugin ecosystem. |
| 🆕 Centralized Cross-Site Dashboard + Global Alert Rules | No | Yes | ⭕ | Logtivity's actual differentiator — bigger lift than simple rebranding, but stronger agency willingness-to-pay; natural extension of Multisite Support. |

---

## Suggested build order (work top to bottom)

**Phase 0 — Foundation (blocks everything below):**
1. Build the `@wordpress/hooks` filter points in free per `free_pro_architecture.md` (settings tabs, log-list columns/filters, dashboard widgets).
2. Scaffold the separate pro plugin with a minimal license/activation check (none exists today).
3. No action needed on Scheduled Reports / Saved Search Filters — already reclassified Free above.

**Phase 1 — Finish promised Free basics:**
4. Build `Authentication_Logger`, `Cron_Logger`, `Database_Logger`, `Notification_Logger`.
5. Ship the small Free additions: user-agent parsing, per-user last-login display, bulk actions, WP-CLI, shallow Gravity Forms/Yoast/ACF support, and clearly surface the existing Exclude-users setting.

**Phase 2 — First Pro wave (lowest new-infra cost):**
6. Advanced Search Filters + Boolean operators (extends existing filter infra).
7. Log Bookmarking/Notes, Stealth mode, In-editor Activity Panel.
8. Remaining ~140 scoped WooCommerce Pro events (already enumerated in event-list.txt).
9. Rule-based smart alerts / anomaly detection (also covers Threshold Alerts).

**Phase 3 — Bigger-infra Pro:**
10. Session management (view/terminate) — pair with Authentication_Logger.
11. GeoIP (pairs with alerts).
12. PDF export, Report Templates, Stats & Summary Dashboards.
13. Page Builder + deep other-plugin (LMS/Forms/SEO) integrations.

**Phase 4 — Enterprise-tier:**
14. External DB logging, off-site archiving, SIEM integration, multisite network dashboard.
15. File Integrity Monitoring — decide in-product vs. separate product first.
16. Speculative differentiation bets (white-label agency dashboard, custom event builder, external API, compliance control-mapping) — revisit against real customer demand before committing engineering time.

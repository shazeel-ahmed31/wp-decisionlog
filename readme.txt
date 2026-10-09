=== WP DecisionLog ===
Contributors: shazeelahmed
Tags: development, documentation, decisions, audit, review
Requires at least: 6.4
Tested up to: 6.8.3
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Keep the reason behind website changes with private development decisions, contextual notices, review reminders and documentation exports.

== Description ==
WP DecisionLog stores technical decisions in custom tables and presents them through a WordPress-native React admin dashboard. Record reasons, risks, affected components, dependencies and comments. Link stable identifiers for supported admin-screen notices and allowlisted change monitoring.

Elementor and WooCommerce integrations are optional. Monitoring never records setting values. Administrators can export JSON/CSV and import validated JSON without overwriting existing UUIDs. Review reminders use WP-Cron; email summaries are optional.

See the bundled README.md and documentation for the exact supported screens, settings and known limitations. This is a screen-level contextual warning system, not universal change interception.

== Installation ==
1. Upload the installable ZIP through Plugins > Add New > Upload Plugin.
2. Activate separately on each site; network activation is unsupported.
3. Open DecisionLog and record your first technical decision.
4. Add stable links for contextual notices and associated monitoring.

== Frequently Asked Questions ==
= Do I need Elementor or WooCommerce? =
No. Both adapters are optional.
= Does deactivation delete data? =
No. Uninstall deletes data only when the administrator explicitly enables that setting.
= Can this execute code snippets? =
No. They are stored and displayed as text.
= Can every setting be monitored? =
No. Only the documented allowlist is monitored; no passwords or configuration values are audited.
= Why are reminders late? =
WP-Cron depends on traffic. Use an external scheduler if accurate timing is important.

== Changelog ==
= 1.0.0 =
Initial decision management, private dashboard/API, stable links, dependency checks, review reminders, metadata-only activity and portable exports/imports.

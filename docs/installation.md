# Installation and operations

The upload ZIP contains `wp-decisionlog/wp-decisionlog.php` at the expected level. Upload the release ZIP (not the source ZIP) in WordPress. Activation checks database table creation, grants the administrator role `manage_decisionlog`, sets defaults, and schedules daily review processing.

Deactivate/reactivate without losing records. Migrations run only when the schema version differs. Back up the database before upgrades. On multisite, activate per site; network activation is unsupported.

Only administrators with the plugin capability can read records. If a role customization removes that capability, restore it explicitly or reactivate the plugin. No public frontend records or frontend CSS are installed.

Review reminders use site-local review dates; persisted timestamps are UTC. WP-Cron is opportunistic and may run late without traffic. External scheduling can call WordPress Cron at a suitable interval. Optional mail sends a count only to the site administrator, once per new decision/date marker. Mail failure is audited without automatic retries to avoid spam; verify your site's mail transport separately.

Uninstall keeps data by default. To intentionally remove it, enable **Delete all DecisionLog data when the plugin is uninstalled** in Settings & export, save, deactivate, and delete the plugin. Back up/export first. Deactivation alone never deletes records. Each multisite site's explicit setting determines its cleanup.

Decision code snippets and comments are text; do not use them for passwords, tokens or payment details. Exports contain private documentation and must be stored securely. Site owners need no build tooling or package-registry credentials.

# Development and testing report — WP DecisionLog 1.0.0

## Implemented

Decision CRUD, all requested decision fields, timestamped comments, stable component links, directed dependencies with cycle prevention, real React dashboard/search/filters/pagination/charts, private REST API, contextual admin warnings, allowlisted metadata-only change monitoring, public-hook Elementor and WooCommerce adapters, review queue/Cron/reminder mail, audit filters, JSON and CSV exports, validated UUID-based JSON imports with comments/relationships, Settings API registration, activation/migration, retention-aware uninstall, and compiled installation assets.

## Environment

Executed on a disposable Docker WordPress 6.8.3 / PHP 8.2.29 installation with MariaDB 11.4, Node 24.19.0, npm 11.9.0, Composer 2, PHPUnit 9.6.38, WPCS 3.4.1, and Chromium/Playwright. PHP 8.1 and WordPress 6.4 are declared minimums; the full minimum-version matrix has not been executed. No Elementor or WooCommerce plugin was installed in the test site.

## Executed checks

| Check | Result |
| --- | --- |
| WordPress installation and DecisionLog activation | Passed; six prefixed InnoDB tables and administrator capability/Cron created |
| Real WordPress PHP integration suite | Passed: 25 tests, 148 assertions |
| PHP syntax lint | Passed on plugin, development and test PHP files |
| WordPress Coding Standards | Passed with the documented project ruleset |
| Production JavaScript/CSS build | Passed |
| JavaScript API/build regression tests | Passed: 4 tests |
| Chromium browser smoke | Passed: login, live statistics, create, edit, comment, search, contextual warning, settings, activity page, component grouping, mobile render; no JavaScript page errors |
| Repeatable development startup | Passed initial installation and subsequent startup using scripts/dev-start.sh |
| HTTP API authentication | Anonymous/missing cookie nonce 401; invalid nonce 403; valid administrator nonce 200 |
| Release ZIP upload and activation | Passed through the actual Upload Plugin screen in a second clean WordPress installation; activated and rendered real empty dashboard without npm/Composer |
| ZIP layout/CRC/runtime assets | Verified by scripts/package.py |

The PHP suite covers activation/schema/Cron, CRUD/cascading deletion, invalid inputs, all endpoint permissions for anonymous access and subscriber denial, REST status codes, graph cycles/missing targets, safe audit metadata, selected-option monitoring, ignored options/disabled monitoring, reminders/deduplication/completion, exports/import duplicates and prevalidation, circular imports, CSV formulas, text-only code, escaped notices, filters/pagination/stats, comments/dismissal expiry, optional integration absence, optional public-hook adapter handlers, portable comments, deduplicated mocked email transport, opt-in uninstall, and deactivation/reactivation.

Optional adapter handler tests exercise Elementor document matching, conservative widget matching (`possible-dependency`), and allowlisted WooCommerce option matching using actual plugin service methods. They do **not** prove live Elementor editor or WooCommerce store compatibility. Mail tests verify the WordPress mail hook and deduplication with an intercepted transport; external email delivery was not tested.

The coding-standard ruleset runs `WordPress`. It exempts direct-query/cache advisories because the plugin owns custom relational tables and intentionally reads live authorized data. Narrow inline annotations explain constant dynamic query clauses/runtime argument-array false positives, temporary CSV streams, read-only screen parameters, and explicitly gated uninstall DDL. Value/identifier preparation, capability, escaping and sanitization checks remain enabled. Runtime errors are not suppressed.

Initial browser checks uncovered incorrect select accessible names and search failure with WordPress plain permalinks. Both were corrected; plain permalink URL handling has an automated regression test. Initial Composer downloads required the host's trusted CA bundle and an allowed Git source route; TLS and package verification remained enabled.

## Reproduce

```sh
bash scripts/dependencies.sh
bash scripts/dev-start.sh
bash scripts/check.sh
# Chromium must be installed; override CHROMIUM_PATH if needed.
npm run test:browser
python3 scripts/package.py
```

Integration tests are intentionally destructive to DecisionLog data in the **disposable** local test database. The suite requires `WPDL_TEST_ALLOW=1`; scripts/check.sh sets it only in the development container. Do not point it at a production site. Docker startup creates private local credentials outside the source checkout. Site owners need no npm/Composer commands.

## Remaining validation and limitations

- Live Elementor editor/template/widget and WooCommerce settings/product/version compatibility tests remain unrun: downloads.wordpress.org access was denied by the running network policy. Required domains were saved in the environment draft for review. Neither optional integration is required for the verified core workflow.
- No claim of production certification, exhaustive security review, minimum-version matrix, complete WCAG audit, or compatibility with every theme/plugin.
- Frontend text is English; translation catalogs/full frontend localization remain future work.
- No PDF reports, arbitrary option/file/source diff monitoring, automatic universal dependency discovery, destructive-setting interception, or rollback. These are not simulated in the UI.
- WP-Cron requires traffic or external scheduling. External mail delivery must be configured separately; failed delivery is audited without automatic resend.
- Imports never overwrite records and report partial storage errors. Cross-site numeric component identifiers need relinking. Exports are bounded to 5000 decisions; imports to 2 MiB/500 decisions/1000 comments per decision. Authors/timestamps are reassigned on import.
- Multisite requires per-site activation; network activation is explicitly rejected. Multisite installation behavior has not been exercised in a live network.
- Screenshots are genuine browser captures with records created during the QA workflow, not bundled demonstration data.

## Delivery

`artifacts/wp-decisionlog-v1.0.0.zip`: uploadable runtime plugin, compiled assets and documentation, no vendor/node_modules or build requirement.

`artifacts/wp-decisionlog-source.zip`: complete project, lockfiles, source, tests, scripts, screenshots and documentation, excluding local dependencies/credentials/Git metadata.

`artifacts/SHA256SUMS`: archive integrity hashes. No GitHub publication or push was performed.

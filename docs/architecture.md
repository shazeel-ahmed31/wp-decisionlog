# Architecture and integrity

`wp-decisionlog.php` defines metadata/constants and registers lifecycle hooks. `Plugin` initializes migrations and independent services. There are no runtime Composer dependencies.

- `Database`: creates/version-checks six site-prefixed InnoDB tables with `dbDelta`.
- `Decision_Manager`: validation, relational CRUD, pagination/search, links, comments and directed graph integrity.
- `REST_API` / `Permissions`: bounded requests, schemas, private endpoints, administrator capability enforcement.
- `Audit_Logger`: safe metadata-only history; never stores changing values or user-entered decision contents.
- `Change_Monitor` and adapters: explicit public lifecycle hooks and allowlists; relevant active decisions become needs-review.
- `Warning_Manager`: screen notices and per-user dismissal; Elementor uses its public editor footer hook.
- `Review_Scheduler`: daily due-date processing with unique reminder markers and optional summary mail.
- `Export_Manager`: JSON UUID graph and comments; CSV text fields; prevalidated bounded import with duplicate skips and explicit storage-error summaries.
- `Admin`: Settings API registration, admin menu and native `wp-element` assets.
- React pages: independent list/detail/editor/overview/activity/components/settings views using the real private API.

## Indexes

Decisions: primary numeric ID; unique UUID; status/review date, component type, updated timestamp. Links: unique decision/kind/identifier; kind/identifier index. Dependencies: unique source/target; target index. Activity: timestamp/ID, related decision and event type. Notifications: unique decision/review date. Comments: decision/ID index.

Decision saves and cascading deletes transact related rows and audit events. Dependency replacement uses `GET_LOCK` around validation and transaction so concurrent graph writers cannot create a cycle. Deletion uses the same graph advisory lock and removes incoming and outgoing edges. Row locks coordinate concurrent saves, comments and deletion. References must exist at validation time. There are no database foreign keys managed by dbDelta; integrity is enforced by services, so direct external SQL mutations are unsupported. The connection must support MySQL/MariaDB advisory locks. IDs are immutable; deletion does not reuse public IDs intentionally.

Links are manually declared relationships, not automatic proof of dependency. Screen IDs, option names, plugin basenames, theme stylesheet slugs, post IDs and Elementor document/widget IDs are stable to the extent those owning components preserve them. Moving records between sites may require relinking numeric post IDs.

Data is site-local. Administrator capability grants are role-based. Multisite is supported only through per-site activation; the uninstall setting is honored separately per site. Migrations preserve existing records; deactivation clears Cron only. Opt-in uninstall removes plugin tables/options/dismissal metadata and the role capability.

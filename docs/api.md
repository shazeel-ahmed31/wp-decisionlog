# Private REST API

Base: `/wp-json/wp-decisionlog/v1/`; plain permalinks use WordPress's `?rest_route=/wp-decisionlog/v1/...` form. Require logged-in authorization with **both** `manage_options` and `manage_decisionlog`. React sends `X-WP-Nonce` from `wp_create_nonce('wp_rest')`. WordPress checks cookie-authenticated REST nonces; no custom cookie authentication bypass is added. Unauthenticated users receive 401, unauthorized logged-in users 403.

| Method | Resource | Result |
| --- | --- | --- |
| GET / POST | `/decisions` | Paginated list / create (201) |
| GET / PUT / DELETE | `/decisions/{id}` | Detail / partial update / cascade delete |
| GET | `/dashboard/stats` | Counts, distributions, upcoming reviews |
| GET | `/components` | Component/type group counts (50/page) |
| GET | `/activity` | Paginated metadata-only events |
| GET | `/reviews` | Due active or needs-review records |
| POST | `/decisions/{id}/review` | Complete current review; active with optional next date |
| GET / POST | `/decisions/{id}/dependencies` | IDs / replace outgoing edges |
| GET / POST | `/decisions/{id}/comments` | Recent comments (100/page) / add (201) |
| GET | `/export?format=json` or `csv` | JSON document / filename and CSV text |
| POST | `/import` | Validated portable JSON import summary |
| GET / PUT | `/settings` | Configuration / boolean-only update |
| POST | `/warnings/{id}/dismiss` | Per-user notice dismissal |

All requests are bounded to 2 MiB. Server-generated IDs, author IDs and creation timestamps cannot be overwritten by decision requests. Errors use WordPress's `{code,message,data:{status}}` format. Invalid inputs return 400; missing records 404; graph lock conflict 409; oversized requests 413; storage errors 500. Successful updates return 200. CSV is returned as a JSON envelope `{filename,content}` so the authenticated dashboard can safely download it.

## Create a decision

```json
{
 "title": "Mobile menu spacing fix",
 "description": "Adjusted navigation CSS below 768px.",
 "reason": "The cart drawer overlapped navigation links.",
 "reversal_risk": "Navigation becomes inaccessible on mobile.",
 "component": "Primary navigation",
 "component_type": "custom-css",
 "risk": "high",
 "status": "active",
 "review_date": "2027-01-15",
 "code_snippet": ".menu { gap: 1rem; }",
 "reference_url": "https://example.org/issue/123",
 "notes": "Review after theme update.",
 "links": [{"kind":"theme","identifier":"twentytwentyfive"}]
}
```

Required: nonempty title, reason, component; valid component_type, risk and status. Title/component limit 200 characters, each long field 100000 bytes, at most 50 links (identifier up to 191 characters). Reference URLs must use HTTP(S). Review date must be a real `YYYY-MM-DD` date or empty/null. Code snippets preserve text and are never executable.

Status enums: `active`, `needs-review`, `deprecated`, `archived`. Risks: `low`, `medium`, `high`, `critical`. Component types: `wordpress-core`, `wordpress-settings`, `theme`, `plugin`, `elementor-page`, `elementor-widget`, `woocommerce`, `custom-css`, `custom-javascript`, `custom-php`, `database`, `other`.

List filters: `search`, `status`, `risk`, `component_type`, `component`, `page` (positive integer), `per_page` (1–100 effective limit). List envelope: `{items,total,page,per_page}`. Activity supports `event_type`, optional positive `decision_id`, page/per_page. Comment requests use `{body:"plain text"}` with 10000-byte limit; list includes `{items,page,has_more}`. Detail includes links, numeric dependency IDs and author display name.

Dependency POST: `{"dependencies":[12,34]}` replaces all outgoing dependencies. Empty array removes them. Maximum 100 IDs; missing targets, self edges and cycles are rejected. Review POST: `{"review_date":"2027-03-01"}` schedules the next review; empty date completes with no new scheduled review.

Settings keys: `monitoring`, `reminders`, `email_reminders`, `delete_on_uninstall`, all booleans. GET also reports detected optional integrations.

## Portable import/export

JSON export: `{schema_version:1,exported_at:"ISO8601",decisions:[...]}`. Records include UUID, fields, stable links, comments and `dependency_uuids`. Imports require schema_version **integer 1**, at most 500 records and 1000 comments/record. Duplicate UUIDs inside a file, unknown dependencies, invalid records and circular imported graphs are rejected before record writes. UUIDs already in the database are skipped, including their relationships and comments; there is no overwrite mode. New records/comments are attributed to the importing administrator with current timestamps. Local numeric IDs and exported author IDs are never trusted as identifiers.

Summary: `{imported,skipped,errors:[{uuid,message}]}`. Validation failures write no records; runtime storage failures can yield a partial import with explicit errors. JSON exports include comments/relationships; CSV contains scalar decision fields and escapes formula-prefix cells. Audit history and reminder state are not imported/exported. Limits are documented; a database backup is appropriate for larger data sets.

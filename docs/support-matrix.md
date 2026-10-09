# Supported contextual warnings and monitoring

A **component** field describes the affected area in human language. **Component links** drive matching. A link must contain the exact kind and identifier below. Only active / needs-review decisions show notices. Archived/deprecated records stay in the dashboard but are excluded from notices and change flags.

| Link kind | Exact identifier | Notice contexts | Monitored changes |
| --- | --- | --- | --- |
| `option` | `blogname`, `blogdescription` | Settings → General | `updated_option` for these keys |
| `option` | `show_on_front`, `page_on_front`, `page_for_posts`, `posts_per_page` | Settings → Reading | Updates to these keys |
| `option` | `permalink_structure` | Settings → Permalinks | Updates to this key |
| `option` | `default_comment_status` | Settings → Discussion | Updates to this key |
| `theme` | Theme stylesheet directory slug | Appearance → Themes for current theme | Theme switch matches both previous and new stylesheet |
| `plugin` | Plugin basename, e.g. `woocommerce/woocommerce.php` | Plugins list for installed matching plugins | Activation/deactivation; own DecisionLog lifecycle excluded |
| `post` | Numeric post ID | Classic/block post edit admin notice area | WooCommerce product post save when WooCommerce is active; ordinary pages/posts are not monitored |
| `elementor-document` | Numeric main document ID (page or template) | Post edit admin and Elementor public editor footer | `elementor/document/after_save`, main document ID |
| `elementor-widget` | `documentID:widgetID`, e.g. `42:a1b2c3d` | Related-decision panel in that document's Elementor editor footer | Document save conservatively flags all linked widgets; outcome `possible-dependency`, not proof of widget-specific change |
| `option` | WooCommerce allowlist below | WooCommerce → Settings, all tabs | Allowlisted option updates only while WooCommerce active |
| `woocommerce-screen` | `wc-settings` | WooCommerce → Settings | Screen association only; no automatic universal change monitoring |
| `screen` | Exact WordPress `get_current_screen()->id` | Matching admin screen's notice area | Screen association only; no implicit monitoring |

WooCommerce allowlist:

- `woocommerce_currency`
- `woocommerce_price_num_decimals`
- `woocommerce_enable_ajax_add_to_cart`
- `woocommerce_cart_redirect_after_add`
- `woocommerce_calc_taxes`
- `woocommerce_prices_include_tax`
- `woocommerce_manage_stock`

Product monitoring concerns `save_post_product` updates to the product post. It does not claim to monitor all product metadata, inventory changes, checkout, orders or payment credentials. DecisionLog does not alter commerce behavior.

Core selected-option updates log only the option key, actor, time and detected outcome. Actual old/new values are never saved. Changes to unknown options, custom PHP/JS/CSS files, direct SQL, external deployments, arbitrary plugin settings, or unrelated Elementor internals cannot be monitored by this release. Links to those components still provide useful manual documentation and screen notices where assigned.

Notices are screen-level context before an operation, not per-field interception. They do not block submissions or undo changes. Dismissal is per user and per site, and expires when the canonical decision content or its relationships change. Elementor's footer panel can be collapsed with its native disclosure control. Core block-editor notice visibility depends on WordPress's admin notice layout. Elementor editor support is restricted to the documented public hook and requires live version-specific validation.

# Implementation audit of AFS 2.0.0 (Phase 0)

Audited 2026-11-04 on branch `docs/afs-next-generation-plan` (production source identical to `master` at `5ca5e8a`, WordPress.org r3775464). Everything below is read from the code and verified by the tests listed at the end.

## 1. Inventory (shipped production files: 18, ~65 KB zip)
| Area | Files | Lines |
|---|---|---|
| Bootstrap | `ajax-filter-search.php`, `uninstall.php` | 47, 14 |
| Settings and options | `includes/class-afs-settings.php` (class `AFSAdmin`) | 361 |
| Config/state normalization, query, HTML | `includes/class-afs-renderer.php` (class `AFS_Renderer`) | 566 |
| Shortcode, block render, assets | `includes/class-afs-frontend.php` | 168 |
| REST route | `includes/class-afs-rest.php` | 119 |
| 1.x compatibility layer | `includes/compat.php` | 164 |
| Helpers | `includes/functions.php` | 82 |
| Item template | `afs-template/default.php` | 58 |
| Block (no build step) | `blocks/feed/block.json`, `editor.js`, `editor.asset.php` | 31, 203, 15 |
| Front end | `public/js/afs.js` (9.9 KB raw, 3.0 KB gzip), `public/css/afs.css` (6.8 KB raw, 1.8 KB gzip) | 328, 396 |
| Admin | `admin/js/admin.js` | 29 |
Repo-only: `bin/` (build, seed, smoke, compat scripts), `docs/`, `design/`, `.wordpress-org/` assets, `phpcs.xml.dist`, `.wp-env.json`.

## 2. Data flow
1. Shortcode or block calls `AFS_Frontend::render()` -> `AFS_Renderer::normalize_config()` (validates post type, taxonomy, terms, per page, order, layout, toggles) and `normalize_state()` (search, one term, month, year, page, layout) -> `render_widget()` (filters plus first page of results, server-rendered).
2. JavaScript intercepts form, tab, pagination and layout clicks, calls `GET /wp-json/afs/v1/results` with the **config fields from a `data-afs-config` attribute plus the state**, and replaces the results container's innerHTML with the server's HTML.
3. `AFS_Renderer::query()` builds one `WP_Query` (`post_status` publish, `s`, one `date_query`, one `tax_query`), applies the `afs_query_args` filter, and renders each item via `return_template()` (theme override aware).
4. State for the first feed is mirrored to the URL (`afs_s`, `afs_term`, `afs_month`, `afs_year`, `afs_page`, `afs_layout`) and read back on page load.

## 3. Strengths to preserve (do not rebuild)
- Server-render-first, works without JS, accessible semantics (labels, `aria-current`, live region, unique landmark labels), shareable URLs, multiple independent feeds, Gutenberg block plus shortcode, unified REST path for page load and AJAX, template override with parent-theme support, `--afs-*` tokens, 4 documented filters, published-only queries, input caps, clean `phpcs` and Plugin Check, 1.x compatibility layer with a single off switch, 18-file lean package, no jQuery or external requests.

## 4. Constraints and technical debt (what limits the next generation)
| # | Finding | Why it matters | Severity for roadmap |
|---|---|---|---|
| 1 | **Single fixed filter set.** Search, month, year and one taxonomy rendered as tabs; `state['term']` is a single slug; REST has one `afs_term` | Multiple taxonomies, checkboxes, chips need a state model of arrays and a filter registry | High |
| 2 | **Config travels from the client** (`data-afs-config`: post type, taxonomy, terms, per page, order) and is re-validated on each request | Safe today (validated, published-only), but a saved "experience" must be resolved server-side by ID so the client cannot reshape queries and so Pro rules cannot be bypassed | High |
| 3 | `AFS_Renderer` mixes normalization, querying, HTML for controls, results and pagination in one 566-line static class | Hard to test and to extend with new filter types | High |
| 4 | **Settings are a global singleton** (`afs_settings`: one default post type/taxonomy/layout) | A builder needs many saved experiences; existing option must migrate to a "default experience" without changing output | Medium |
| 5 | Query uses `SQL_CALC_FOUND_ROWS` (WP default) | Cost grows with data; load-more mode can avoid it | Medium |
| 6 | Keyword search is WP's `LIKE` search on title/content/excerpt | Measured cliff: 245 ms DB time at 50k posts (see PERFORMANCE-BASELINE.md) | Medium |
| 7 | No response caching headers on the REST route; only `years()` is cached (object cache keyed by `posts` last_changed) | Anonymous results could be edge-cached safely; widget cold start costs 54 ms at 50k posts | Low/Medium |
| 8 | Results are HTML strings; no JSON data contract | Fine for server rendering; builder preview and Pro need a stable response schema (`html` plus `state`, `counts`) | Medium |
| 9 | Only the first feed syncs the URL; params are single-valued | Multi-filter URLs need an array format and per-feed namespacing | Medium |
| 10 | Admin UX is a settings page plus block inspector; no preview, no saved experiences, no presets | The builder is the largest gap vs. WP Grid Builder / Search & Filter Pro | High (product) |
| 11 | No PHPUnit/JS unit tests, no CI; tests are HTTP-level scripts plus manual axe runs | Needed before refactoring the query/state core | High |
| 12 | Browser coverage is Chrome only; themes tested: Twenty Twenty-Five (and Twenty Fifteen visually); multisite and page builders untested | Compatibility claims must stay conservative | Medium |
| 13 | Results items have no image/thumbnail support in the default template | Competitors lead with cards; needs `wp_get_attachment_image` with sizes/lazy loading | Medium (product) |
| 14 | `my_action` legacy endpoint is a public AJAX endpoint (read-only, validated, same renderer) | Intended; plan deprecation, keep tests | Low |
| 15 | Search string has no length cap on REST and the legacy action (1.0.4 capped at 200) | Not exploitable (URL limit, WordPress term limit); a cap is a 2.0.1 hardening item | Low |
| 16 | Multisite uninstall removes only the running site's option | Known | Low |
| 17 | `index.php` silence files from 1.x are gone | Cosmetic hardening | Low |

## 5. Security properties (verified)
Public endpoint is read-only and validated; published-only is enforced in `query()`; unknown post types fall back; markup is escaped on output; base URL for links must be same-site (`wp_validate_redirect`); no state-changing front-end endpoint; settings use the Settings API (nonce and capability) with server-side sanitization. A single choke point should keep enforcing published-only even when `afs_query_args` is filtered (design item in TECHNICAL-ARCHITECTURE.md).

## 6. Tests actually run (2026-11-04, PHP 8.4.27 / WordPress 7.1.4 / MariaDB 12.3.3 in wp-env)
| Check | Result |
|---|---|
| `bin/smoke-test.sh` | 14 of 14 pass |
| `bin/compat-test.sh` (1.x shims, legacy ajax, injection attempts, array/huge/garbage input, template override) | 37 of 37 pass |
| `phpcs` (WordPress + PHPCompatibility, tracked PHP files) | clean |
| WordPress Plugin Check (shipping files) | no errors |
| Package check (new, see RELEASE-PLAN.md) | production zip byte-identical to published 2.0.0 |
| Performance baseline (100 / 1,000 / 10,000 / 50,000 posts) | recorded in PERFORMANCE-BASELINE.md |
Earlier in the project (same code): axe WCAG 2.2 AA clean on 5 pages at 1200px and 390px; PHP 7.4 / WP 6.0 smoke and compat suites pass; real upgrade 1.0.4 to 2.0.0 through the admin UI.
Not run: Firefox/Safari, multisite, page builders, manual screen-reader testing, automatic update verification from WordPress.org (still pending at the time of writing).

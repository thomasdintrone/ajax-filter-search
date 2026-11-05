# Technical architecture proposal (Phase 0)

Design only. Nothing here is implemented or approved. Builds on IMPLEMENTATION-AUDIT.md (what exists) and PERFORMANCE-BASELINE.md (what it costs). Principle: **evolve, do not rewrite**: every 2.0.0 shortcode, block, URL, template override and compatibility shim keeps working.

## 1. Core idea: an "Experience"
A saved, versioned configuration object that fully describes one search and filter widget:
```
Experience {
  id, schema_version, title,
  content:  { post_types[], taxonomies[], orderby, order, per_page, limits },
  filters:  [ { id, type, source, label, control, default, options{} } ... ]   // ordered
  layout:   { preset, mode(list|grid|rows|tiles), columns, image{ratio,size}, fields[], pagination(numbered|load_more) },
  design:   { tokens{ accent, radius, spacing, ... }, inherit_theme: true },
  access:   { published_only: true }   // invariant, not configurable
}
```
- **Storage:** a private custom post type `afs_experience` (no public query, `show_ui` false, REST disabled; capability `manage_options`, filterable) holding the JSON in post content or meta, with `schema_version`. Alternatives considered: a single option (does not scale to many experiences), JSON files in uploads (harder permissions/backup). CPT gives IDs, revisions, import/export and WP-native backup.
- **Legacy mapping:** shortcode and block attributes become an **inline experience** built on the fly (same `normalize_config` rules), so `[ajax_filter_search ...]` output is unchanged. The existing `afs_settings` option becomes the defaults for inline experiences and a read-only "Default (from settings)" experience; no migration of user data, nothing rewritten.
- **Resolve on the server:** new route `GET /afs/v1/experiences/{id}/results?state...` takes only an ID and state. The client no longer sends post type, taxonomy or limits (today's `data-afs-config` keeps working for legacy/inline use). This closes debt item 2 and lets Pro rules be enforced server-side.

## 2. Filters as typed definitions
```
interface AFS_Filter {
  id(): string;  type(): string;
  normalize(raw): value|null;           // validate and cap
  apply(WP_Query args, value): args;    // pure function of state
  options(context): [ {value,label,count?} ];   // for rendering
  render(value, options): html;         // control markup
}
```
- **Free types:** `search`, `taxonomy` (checkbox, radio, select, tabs/pills; hierarchical), `date` (month, year, range of years), `sort` (user-facing), `author` (optional).
- **Pro types (via the same registry):** `meta`/`acf` (text, number range, date range, select), `relationship`, `group` (AND/OR), conditional visibility.
- **Source adapters:** `taxonomy`, `date`, `post_field`, then Pro adapters for meta plugins. Adapters declare capability to supply counts.
- **Semantics (Free):** AND across filters, OR within one multi-select filter; empty value = no constraint. Pro adds explicit group logic.
- **State:** a structured map `filters[<id>] = scalar|array`, `sort`, `page`, `layout`. URL form `afs_f[category]=news,guides&afs_q=report&afs_page=2` (feed-namespaced as `afs_<n>_...` for non-first feeds when URL sync is extended). **Backward compatibility:** the existing `afs_s`, `afs_term`, `afs_month`, `afs_year`, `afs_page`, `afs_layout` parameters are read and mapped for the first feed and keep producing the same pages.
- **Single choke point:** `AFS_Query::build( $experience, $state )` produces WP_Query args from the registry, then **enforces `post_status=publish` after all filters run**, including `afs_query_args`. Unpublished access can only be added by a dedicated, documented, default-off capability for authenticated server-side use, never from request input.

## 3. Rendering
Split `AFS_Renderer` into: `AFS_State` (normalize/serialize), `AFS_Query` (args), `AFS_Controls` (filter markup), `AFS_Results` (cards/rows/tiles and pagination), `AFS_Widget` (wrapper, assets, labels). Output stays server-rendered HTML (SEO, no-JS, caching). Presets are CSS plus a small template per layout; the existing default template keeps working (the **Classic** preset equals today's markup and look).
- **JS:** stays dependency-free; split into a small core (state, fetch, URL, focus and announcements) plus optional modules (drawer, load more). Budget: core under 8 KB gzip.
- **REST response contract (versioned):** `{ version, html, found, pages, state, counts? }`. Legacy `/results` keeps today's shape (`html`, `found`, `pages`).

## 4. Builder architecture (Content > Filters > Layout > Design > Publish)
- **Screen:** an admin page (React via `@wordpress/element` and `@wordpress/components`) as a single-page app, sections as the five steps; autosave drafts as the experience's draft revision.
- **Build tooling:** introduce `@wordpress/scripts` for **development only**; commit built assets under `build/` so the shipped zip has no Node dependency and the package check can allowlist `build/`. (Today the block is hand-written without a build.)
- **Live preview reuses the real renderer:** `POST /afs/v1/preview` (administrators only, REST nonce, capability check) accepts a draft experience, validates it with the same schema, and returns the actual server-rendered widget HTML plus scoped CSS; shown in an isolated container (shadow DOM or iframe) with device-width toggles. No second rendering implementation to drift.
- **Setup wizard:** detects public post types and taxonomies (`get_post_types`, `get_object_taxonomies`), offers Blog / Directory / Portfolio / Resource Library recipes that create a **new** experience; never edits an existing one.
- **Publish step:** block (selects an experience by ID), shortcode `[ajax_filter_search experience="12"]`, copy buttons, usage list (where is it used), and a **health check** (missing taxonomy, empty terms, zero published posts, performance warning at large data).
- **Accessibility of the builder itself:** keyboard-operable reordering (up/down buttons, no drag-only), labeled controls, WCAG 2.2 AA target sizes.
- **Import/export:** JSON with `schema_version`, validated on import (no code, no arbitrary callbacks), size limit, preview before apply.

## 5. Performance architecture
- Keep **WP_Query** for taxonomy, date, author, sort and pagination (measured good to 50k posts).
- Add `no_found_rows` plus an "n+1" strategy for load-more mode to avoid `SQL_CALC_FOUND_ROWS`.
- **Caching:** object-cache the year list and static option lists keyed by `posts` last_changed (already done for years); optional short-lived cache of result HTML keyed by (experience version, normalized state, `posts` last_changed); `Cache-Control: public, max-age=60, s-maxage=...` for anonymous GET results only when the response carries no user-specific content (it never does for published-only data); ETag support.
- **Search:** the measured cliff is keyword search. Options: (a) keep WP search with a shorter default scope (title and excerpt); (b) optional MySQL FULLTEXT on title/content via an opt-in index table; (c) Pro index with relevance. Benchmark each at 10k/50k before choosing.
- **Index (Phase 4):** `{prefix}afs_index(object_id, filter_key, value, term_id, parent, post_date)` rebuilt incrementally on `save_post`, `set_object_terms`, `delete_post`, with a batched rebuild via WP-Cron/Action Scheduler-style queue; used only for counts and facet availability; WP_Query remains the source of truth for results. Gate: it must beat the WP_Query baseline on counts at 50k posts by a stated margin and survive concurrent saves.

## 6. Security architecture
- Validate and cap everything at the boundary (types, lengths, option counts, filter count per experience, page size) and again in `AFS_Query`; reject unknown filter ids.
- Experience writes require capability plus nonce; schema validation on save and import; stored config is never executed.
- Published-only is a code-level invariant with a regression test (see TEST-STRATEGY.md). Experience IDs are not secrets; no private content is rendered by public endpoints.
- Escape on output everywhere; builder preview output comes from the same escaped renderer.
- Keep the 1.0.3 regression suite (shortcode injection attempts, draft exposure, array and oversized input) as a permanent release gate.
- Analytics (Pro, later): opt-in, aggregated, no personal data by default, no raw query logs without consent.

## 7. Extension API for Pro (Pro never forks or patches Free)
Stable, documented, versioned hooks and registries: `afs_register_filter_types`, `afs_register_source_adapters`, `afs_experience_schema` (extend fields), `afs_query_args` (existing), `afs_results_response`, `afs_register_layouts`, a JS slot for builder panels, and `AFS_API_VERSION` / `Requires AFS` checks so an add-on refuses to load against an incompatible Free. Free must work with the add-on absent and the add-on must degrade gracefully when Free updates.

## 8. Backward compatibility plan
Legacy shortcodes, `afs_feed()`, `AFSAdmin::afs_retrieve()`, `my_action`, template overrides, 1.x constants and handles stay (shim switch `afs_enable_legacy_compat`). New markup carries `data-afs-version`; existing sites keep **Classic** until they opt into a preset. Provide tests for each shim. Deprecations get a published schedule (not before 3.0).

## 9. Risks
| Risk | Mitigation |
|---|---|
| State/URL redesign breaks bookmarks | Read legacy params forever; add a test per legacy param |
| Refactor regresses published-only or input limits | Single choke point plus security regression suite before any refactor merges |
| Builder scope creep | Ship Content/Filters/Layout first; Design tokens panel second; wizard last |
| Build tooling complicates a lean plugin | Dev-only tooling, built assets committed, package check allowlist |
| Index complexity and stale counts | Defer to Phase 4; opt-in; WP_Query remains truth; background rebuilds; kill switch |
| Preset CSS leaks into themes | Everything scoped under `.afs[data-preset]`; visual-regression tests on 3 themes |
| Pro coupling | Add-on only through documented hooks; API version check |
| Licensing/ownership unresolved | See LICENSING-PROVENANCE.md; resolve before any paid distribution |

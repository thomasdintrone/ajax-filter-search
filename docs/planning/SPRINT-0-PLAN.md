# Sprint 0 plan (pre-implementation report)

Status: **proposal, nothing implemented.** Written 2026-11-14 against the roadmap `ajax-filter-search-roadmap.md` (sections 3, 4, 8) and the code on `docs/afs-next-generation-plan` (production source == `master` 5ca5e8a == WordPress.org 2.0.0). Evidence labels: **[V]** verified today from source or a command I ran, **[R]** from the roadmap, not re-checked, **[H]** my hypothesis to test.

## 0. Repo state found
- Working tree clean on `docs/afs-next-generation-plan` (19f995e, in sync with origin); `master` = 5ca5e8a; tags v1.0.3, v1.0.4, v2.0.0.
- Local-only branch `chore/package-exclude-ai` (b08b8e6): `.ai` exclusion + `bin/check-package.sh`; **not merged; needed before any CI package check.**
- `master` has no tests dir, no `.github/`, no `package.json`; only `bin/*.sh` HTTP scripts (smoke 14, compat 37). `.ai/` and `docs/planning/` (Phase 0 work) exist only on the docs branch.
- Local env problem: `wp-env start` fails today building its Docker image (`curl https://getcomposer.org/installer` exits 60, an SSL error inside the build; the host's own curl works; cause not diagnosed). I used plain Docker (wordpress image + mariadb + wp-cli) for all probes below.

## (a) How the code maps onto roadmap section 4
Roadmap code claims re-checked and **all confirmed [V]**: FILTER_TYPE global constant (`class-afs-renderer.php:482`), first-feed-only URL sync (`class-afs-frontend.php:145`), `aria-live="polite"` on the whole results region, Update button always rendered, `.afs-switch-btn{min-height:2rem}`, block `supports` is only `html:false, align`, no featured image anywhere, `get_terms()` uncached in `tab_terms()`, REST route sends no `Cache-Control`/ETag (`X-Robots-Tag: noindex` is core's), results replaced via `innerHTML`.

| Roadmap element | What exists today | Gap / work |
|---|---|---|
| **D3 filter fields** (typed, ordered `filters[]`, each with sanitize / to_query_args / render / counts) | Fixed set hard-coded in `AFS_Renderer::render_widget()` and `normalize_config()/normalize_state()`: search, month, year (two booleans `show_months/show_years`), one taxonomy as tabs, layout switch, Update/Reset. State = `{s, term, month, year, paged, layout}`; **one term, scalar values** | Registry + interface do not exist. Mapping is clean: search->`search`; month+year->`date` (mode year-month); tabs->`taxonomy` (ui tabs, single); `sort` is config-only today (`orderby/order`); layout switch is a view control, not a filter. `afs_*` URL keys map 1:1 and must keep working |
| **Query choke point** | `AFS_Renderer::query()` builds one `WP_Query` (publish only, `s`, one `date_query`, one `tax_query`), then `apply_filters('afs_query_args')`. **Filter runs after `post_status` is set, so a site filter can override it** | Move to a `AFS_Query` class; enforce `post_status=publish` after filters (Phase 0 finding); keep the hook |
| **D4 designs/tokens** | One stylesheet (6.7 KB / 1.8 KB gzip), 7 `--afs-*` tokens, `afs-layout-list|grid` classes, accent set by inline style from `afs_appearance_accent` | "Classic" = today's CSS, exactly; add `data-afs-design` attribute and per-design stylesheets later. Token names are already public API, keep |
| **D7 registries/hooks** | 4 filters: `afs_query_args`, `afs_template_file`, `afs_enable_legacy_compat`, `afs_legacy_globals`. All renderer methods are `static` | No `fields/designs/template_parts/admin_tabs/rest_meta` registries, no `AFS_API_VERSION`, no response filter. **Main Sprint 0 refactor risk:** static class hard to extend; do it behind golden tests |
| **Store / JS** | `public/js/afs.js`, ES5 IIFE, `Widget` class, state read from DOM form controls plus `this.page`; no events; `fetch` with AbortController; focus not managed after swaps | Becomes store state + actions (spike 1). Roadmap's `afs:loaded` etc. are new |
| **Block** | `block.json` apiVersion 3, 12 flat attributes mapped 1:1 onto shortcode attributes, hand-written `editor.js` (`createElement`, ServerSideRender) | D4 `deprecated` not needed yet (attributes unchanged); D2/D5 are post-Sprint 0 |
| **Templates** | `afs-template/default.php`; `return_template($template='default.php')` already takes a name and checks child, then parent theme, plus `afs_template_file` | C2 (`card.php` etc.) is mostly additive |
| **Settings** | `afs_settings` option, 7 fields, one global default feed | Fine for Sprint 0 |
| **Legacy (T4, T5)** | `includes/compat.php`; `FILTER_TYPE` defined inside `render_results()` | Move constant into compat layer only, pass `$afs_terms`/`$afs_taxonomy` (already passed) |

**New finding the roadmap does not cover:** the REST route trusts config sent by the browser (post type, taxonomy, terms, per_page, order). It is validated and published-only, so it is safe, but D3's inline `filters[]` plus Pro rules need integrity: a **signed config blob** (HMAC of the normalized config embedded in the page and required by REST) or saved server-side configs referenced by ID (Phase 0's "Experience" idea). Add to spike 2.

**Where roadmap and my Phase 0 documents differ (need your call):**
1. JS: roadmap = Interactivity API + WP 6.5; Phase 0 = keep dependency-free vanilla JS, 6.0 floor. Spike 1 decides.
2. Config model: inline `filters[]` attribute vs saved Experience CPT (Phase 0). Roadmap's builder is later; Sprint 0 should only avoid closing either door.
3. Designs: roadmap Clean/Classic/Compact/Minimal; `.ai/PROJECT_CONTEXT.md` lists Editorial/Directory/Portfolio/Resource Library. Naming and scope differ.
4. **Roadmap A1/B2 change the default look and hide Update in 2.1**; PROJECT_CONTEXT says existing sites keep their appearance until they opt in. Proposal: Classic stays the default for upgraded sites (store `afs_design_default=classic` on upgrade); Clean is the default only for new installs. Needs your decision (O7 below).
5. Roadmap G1 (`noindex,follow` on filtered states, default on) changes crawl behavior for existing sites; I would ship it default-off for upgrades.

## (b) The three spikes (time-boxed; each ends in a decision record in `docs/decisions/`)

### Spike 1: Interactivity API vs hand-written ES module (2 to 3 days)
**Facts gathered [V] (WordPress core files, versions 6.5.5 / 6.8.3 / 7.1.4):**
- Runtime weight is large relative to us: `interactivity` module **13.2 / 14.1 / 16.1 KB gzip** (6.5.5 / 6.8.3 / 7.1.4). Our entire JS today is **3.0 KB gzip**. The router module is 1.4 KB gzip on 6.5.5 but **12.0 KB** on 7.1.4 (it was rewritten). So a page with a feed would go from about 3 KB to roughly 17 to 19 KB of script on 6.5 and roughly 19 to 28 KB on 7.1 (more with the router). Shared only if the theme already loads Interactivity.
- **Store extension (D1's key assumption) is confirmed in source:** `store(namespace, {state, ...block})` deep-merges into an existing namespace (`deepMerge(target, block); deepMerge(target.state, state)`), unless the first caller locked it (lock errors otherwise). So a Pro plugin can extend an `afs` store, provided the free store is **not locked**. Load-order and private-store behavior still to test.
- **API surface differs across the floor and the ceiling:** 6.5.5 exports lack `getServerState`, `getServerContext`, `splitTask`, `withSyncEvent` and `watch`; 6.8.3 adds all but `watch`; 7.1.4 has all. PHP helpers `wp_interactivity_get_context/get_element` appear only from 6.8. Code must use the intersection or feature-detect. My recollection (to verify in the spike, from the 6.8 dev note) is that handlers reading the event synchronously need `withSyncEvent` on newer versions, which does not exist on 6.5.

**Build both prototypes** with identical behavior to `afs.js` (filters, debounce, pagination, abort, URL state with popstate, layout switch, summary toggle, two feeds, legacy IDs) in a throwaway branch.

| Criterion | Pass threshold | How measured |
|---|---|---|
| Functional parity | 100% of the Playwright scenarios used for the legacy snapshots | E2E run on WP 6.5.5 and 7.1.4, PHP 8.2 / 8.4 |
| No-JS parity | server HTML identical with JS disabled; links and form work | HTML diff, Playwright with `javaScriptEnabled:false` |
| **Shortcode path** | works without a block (module enqueued; directives processed with `wp_interactivity_process_directives()` or equivalent) | fixture page using `[ajax_filter_search]` |
| Interaction latency | INP p75 <= 200 ms, 20 scripted filter changes, mid-tier mobile profile (4x CPU throttle) | PerformanceObserver event timing + Lighthouse |
| Layout shift | CLS ~ 0 for swaps | Lighthouse / trace |
| Weight | **report** own gzip KB, runtime KB, total first-load JS on a feed page, cold cache; own code <= 12 KB gzip. Decision input, not pass/fail | build output |
| Extension | a throwaway "Pro stub" plugin adds an action and a state key to `afs` in **both load orders**, on 6.5 and 7.1; private-store lock does not break it | stub plugin + E2E |
| Test ergonomics | state/actions unit-testable without a browser | trial with Node test runner / `@wordpress/jest` |
| Build burden | `@wordpress/scripts --experimental-modules` output committed to `build/`; package check allowlists it; dependency declaration for modules verified without a bundler | package check |

Decision rule (proposed): choose Interactivity only if every functional, latency, extension and shortcode criterion passes on **both** 6.5.5 and 7.1.4 **and** you accept the weight delta; otherwise ship a hand-written ES module with a small documented registry and DOM events (`afs:loaded`, `afs:state`) for Pro. The weight call is yours.

### Spike 2: REST fragment + JSON meta vs the Interactivity router (1 to 2 days)
**Baseline measured today [V] (seeded site, Twenty Twenty-Five, 10 items, single hits so timings are noisy):**
- REST fragment: 13.1 KB raw JSON (the HTML is JSON-escaped), 1.3 KB gzip. Full page fetch (what the router would pull): 82.8 KB raw, 13.8 KB gzip. A full page is about **10x** the gzip payload and depends on the theme.
- REST route has no cache headers or ETag; every interaction runs an uncached `WP_Query` (Phase 0 benchmark: 8 to 12 queries; taxonomy/date requests under 15 ms PHP at 50k posts; keyword search is the slow path: 245 ms at 50k).

| Criterion | Pass threshold |
|---|---|
| Payload per interaction | <= 15 KB gzip on the 10k-post fixture |
| Server time | cached <= 50 ms; uncached p95 <= 300 ms for taxonomy/date/pagination at 10k posts (keyword search measured separately, target under 500 ms until an index exists) |
| Cacheability | second identical request served with `Cache-Control`/ETag 304 or object-cache hit; verified with a page-cache plugin on and off |
| Multi-feed | two feeds on a page update independently (router refreshes whole regions; must not break the other feed) |
| Theme independence | works on 3 themes without relying on theme markup |
| Focus/announcement control | focus stays on control or moves per the a11y rules |
| Config integrity | REST rejects requests whose config is not signed/known (post type, terms, per_page cannot be reshaped by the client) while legacy `/results` shape keeps working |
| Response contract | `{version, html, found, pages, meta{ total, pages, term_counts, active_labels, announcement }}` documented; legacy keys unchanged |
Expected recommendation (to be confirmed by the numbers): keep REST fragment plus JSON `meta`; do not adopt the router.

### Spike 3: the `afs` RSS feed endpoint (1 day)
**Facts [V] (WordPress 7.1.4):** core feed URLs already combine the main query vars: `/?cat=2&s=Announcement&year=2026&feed=rss2` returned **2 items, equal to AFS's 2 results** for category + keyword + year; `cat=2&year=2026&monthnum=10` returned 1; `category_name=news,events` is accepted. Limits seen: feed length follows Settings > Reading (10 here); **`post_type=page` was ignored** (returned posts although only a few pages exist); my multi-term, ordering and paging counts were not discriminating (all capped at 10) so they are **unverified**; custom taxonomies only work if registered with `query_var` (not tested); `afs_query_args` customizations and any future multi-filter semantics (AND/OR groups, arrays) cannot be expressed in core vars.

Criteria:
| Criterion | Pass threshold |
|---|---|
| Parity | for 30 generated states, feed item GUIDs equal the first N REST results, same order |
| Published only | draft/pending/future/private/password fixtures never appear |
| Validity | feed parses with `xmllint` and a feed parser; correct `lastBuildDate`, GUIDs, escaping |
| Caching | ETag/Last-Modified honored |
| Security | no new unvalidated parameters; item cap; same normalizer as REST |
| Discoverability | `<link rel="alternate">` emitted for the active state; no-JS works |
Decision rule: if core vars reach full parity for every state AFS can produce and no `afs_query_args` customization is needed, generate core feed URLs (no new endpoint); otherwise add a thin endpoint that reuses `AFS_Query` (my default expectation, because state v2 will outgrow core vars).

## (c) Test and CI plan
**Principle:** the production behavior is frozen as executable fixtures first; refactors must keep them green.

1. **Legacy snapshots of 2.0.0 markup.** `bin/generate-legacy-snapshots.sh` checks out tag `v2.0.0` into a worktree, seeds fixed fixtures (fixed IDs, fixed dates, fixed slugs), and writes normalized HTML/JSON to `tests/snapshots/2.0.0/`. Fixtures: every legacy shortcode form (`filter_type`, `filter_by`, `filter_months/years`, `offset`, `posts_per_page`, `layout`, show_* flags), `[afs_feed]`, the block render for each attribute, two feeds on one page, the REST response for the 776-request matrix I already built (kept so far only in untracked `docs-private/verify/`), `my_action` responses, compat on and off, a verbatim **1.0.3 `default.php` theme override**, and a child-theme override. Normalizer strips only nonces, `wp_unique_id` counters and absolute host. Tests compare with `assertSame`; updating needs `UPDATE_SNAPSHOTS=1` and a reviewed diff. Snapshots are generated **from the tag, not the working tree**.
2. **Unit and integration (PHPUnit with the WP test suite):** normalizers, URL-state mapper (legacy keys), published-only invariant for REST, shortcode, block and `my_action` including after `afs_query_args`, REST schema and caps, settings sanitization, uninstall, multisite smoke. Convert the 51 assertions in `smoke-test.sh` and `compat-test.sh` to PHPUnit/REST contract tests; keep the shell scripts until parity is proven.
3. **Security regression suite (permanent):** the 1.0.3 vectors (injection through every field, unpublished exposure, array/oversized/garbage input), XSS and SQL-style strings, `base_url` abuse, CORS and method checks; plus a test that **fails on any outbound HTTP** from the plugin (`pre_http_request` trap).
4. **E2E (Playwright + `@axe-core/playwright`):** filters, pagination, back button, deep link with JS disabled, two feeds, block insertion, keyboard-only, mobile viewport; axe with WCAG 2.2 AA tags on every fixture page, light/dark, 390 and 1200 px; visual snapshots (light, dark, mobile, RTL) with reviewed baselines.
5. **Performance baseline** (`docs/baselines/2.0.0.json`): Phase 0 numbers formalized (1k/10k/50k posts renderer timings, HTTP latency, queries per request) plus front-end: JS/CSS gzip, Lighthouse/INP and CLS on a 10k-post seeded site, DB query counts. Budgets start as CI warnings, become failures after the spikes set them.
6. **Matrix:** WordPress **6.0 (current floor) and 6.5.5, latest 7.1.x**, plus a trunk/beta job allowed to fail; PHP 7.4, 8.1, 8.2, 8.4 (not every pair: 6.5 with 8.4 is outside WordPress's official support, so 6.5 pairs with 7.4 to 8.3). Full suite on (floor, PHP 7.4), (6.5.5, 8.2), (latest, 8.4); E2E + axe on (6.5.5, 8.2) and (latest, 8.4); multisite on one cell; Chromium on every PR, Firefox/WebKit nightly. Themes: Twenty Twenty-Five and a classic default theme every run, Astra/GeneratePress/Kadence smoke weekly.
7. **GitHub Actions jobs:** lint (phpcs/WPCS, PHPStan, eslint/stylelint), unit+integration matrix, e2e+axe, **package** (build from tracked files, `bin/check-package.sh` allowlist, install zip on a clean site, PHP log empty; requires merging `chore/package-exclude-ai` first), Plugin Check action, **upgrade** (install the `v2.0.0` zip, seed, update to the build, replay snapshots), nightly performance. No secrets; WordPress.org deployment stays manual and approval-gated.
8. **Guards for your hard rules, enforced in CI:** a "protected paths" check fails any PR that touches the plugin header block, `LICENSE`, copyright lines, `readme.txt` or `.wordpress-org/` unless the PR carries an explicit approval label; a grep job fails on `is_pro`, license-key or phone-home patterns; the outbound-HTTP trap above.
9. **Local fallback:** a `docker-compose.yml` dev stack (WordPress + MariaDB + wp-cli) because wp-env currently fails to build here; same seed and test scripts.

## (d) What changes if the minimum WordPress becomes 6.5
- **Reach [V, api.wordpress.org/stats today]:** 88.5% of reporting sites are on WP 6.5 or newer (66.2% on 7.1). Raising the floor excludes **4.3%** more (6.0 to 6.4); 7.3% are on versions below 6.0, already unsupported by 2.0.0. Those sites stay on 2.0.0 and get no further updates; decide whether to keep a security-only 2.0.x line.
- **Code that becomes available:** `viewScriptModule` in `block.json`, `wp_register_script_module()`/`wp_enqueue_script_module()`, the Interactivity PHP API (`wp_interactivity_state/config/data_wp_context/process_directives`). Nothing in today's code depends on 6.0-only behavior (no version checks found), so raising the floor needs no code removal.
- **Compatibility traps at the floor [V]:** the runtime and API differ between 6.5 and 7.1 (exports listed in spike 1; router rewritten); CI must run both ends, and code must feature-detect.
- **Weight:** +13 to +16 KB gzip of core runtime on a feed page (versus 3.0 KB total today); the live listing says "light front end" and Phase 0 copy claims about 5 KB, so wording must follow the measured result.
- **Text that would change (needs your approval, not touched):** header `Requires at least`, `readme.txt` `Requires at least`, Upgrade Notice, changelog; WordPress.org will stop offering the plugin to sites below 6.5.
- **Matrix:** drop the 6.0 job after 6.5 ships, add 6.5.5 (min) and latest; PHP floor stays 7.4.
- **Recommendation:** **do not settle O1 before spike 1.** If the spike picks a hand-written module the floor can stay 6.0 and nothing changes.

## Sprint 0 work breakdown and estimate (Claude-assisted working days, rough)
| Item | Days |
|---|---|
| Merge packaging fix (`chore/package-exclude-ai`) after your approval; branch setup | 0.5 |
| Spikes 1, 2, 3 with decision records | 5 to 7 |
| Tooling (package.json, wp-scripts, composer, docker-compose fallback, PHPStan config) | 2 |
| Legacy snapshot generator + fixtures + PHPUnit scaffolding + conversion of 51 assertions | 4 |
| Playwright + axe + visual snapshot baselines | 3 |
| GitHub Actions workflows + protected-paths guard | 2 |
| Registries/hooks/`AFS_API_VERSION` (in `includes/`, not the plugin header), `AFS_Query` choke point, `FILTER_TYPE` move, behind green snapshots | 4 |
| Baselines + docs (`docs/extending.md`, decisions) | 2 |
| **Total** | **about 22 to 25 days, roughly 4 to 5 weeks calendar** (the roadmap's "weeks, not quarters" is tight; the spikes could shorten it if one option fails fast) |

## Decisions I need before implementing
1. Approve this plan and the three spikes (and the decision rules above).
2. O7: default design for upgraded sites (my proposal: Classic) and for new installs (Clean).
3. Is the signed-config requirement for REST accepted as part of spike 2 / Sprint 0?
4. Merge `chore/package-exclude-ai` first (adds `.ai` exclusion and the package check)?
5. Base branch for implementation: `master` (docs/`.ai` live only on the docs branch) or the docs branch?
6. Design names: roadmap set or the four use-case presets?
7. Do you want a security-only 2.0.x line for sites below the new floor, if the floor moves?
8. Defer O1 (6.5 floor) until spike 1 reports?

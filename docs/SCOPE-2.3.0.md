# Scope: 2.3.0, "Beautiful content discovery"

Status: **shipped as 2.3.0 (SVN r3828281, tag v2.3.0); audit fixes follow in 2.3.1 (see RELEASE-2.3.0.md).** Approved direction: two new opt-in designs (Editorial Grid and Resource Library, working names), card options, a searchable term picker, dark-theme support for the new designs, block patterns and honest screenshots. Prototypes: `design/prototypes/` (`editorial.html`, `library.html`). Publishing to WordPress.org is a separate approved step (see `RELEASE-2.2.0.md` for the SVN steps).

## Goals and non-goals

Goals: a visually strong, image-first and compact-row experience in minutes; no change to any existing site.

Non-goals (later releases): multiple taxonomies, Load More, mobile filter drawer (2.4.0, needs the state redesign); Portfolio and Directory designs; the visual builder; keyword highlighting; custom labels; anything Pro.

## Decisions already fixed

- Classic and Modern keep their markup and look byte for byte. New options are ignored by them.
- New designs are opt-in per feed, per site setting, or by shortcode and block attribute. Existing sites do not change on update.
- No new URL parameters, so bookmarked `afs_*` URLs keep working.
- No free-form CSS from users. Ratio, columns and similar are fixed choices.
- Published-only stays enforced in `AFS_Query` after the `afs_query_args` filter.

## Finding that shapes the work

The results route builds its config from a short list of request parameters (`class-afs-rest.php`, `route_args()` and `config_and_state()`), and `design` is not one of them. Today that is harmless because a design is only a wrapper class and a stylesheet. A new design changes the **item markup**, so the design and card options must be sent with every request, or the first page (server render) and later pages (REST) would differ. This is the main correctness risk and has its own test.

## New options

| Option (shortcode / block / REST) | Values | Default |
|---|---|---|
| `design` | `modern`, `classic`, `editorial`, `library` | unchanged |
| `show_image` / `showImage` | true, false | Editorial on, Library on, Classic and Modern ignore it |
| `image_ratio` | `16:10`, `3:2`, `4:3`, `1:1`, `16:9` | `16:10` |
| `columns` | 1 to 4 (grid designs; fewer on narrow screens) | 3 |
| `show_excerpt`, `excerpt_length` | true or false; 10 to 80 words | on, 25 words |
| `show_date`, `show_time`, `show_category` | true, false | date on, time off, category on |

All are validated against fixed lists in `AFS_Renderer::normalize_config()`. Unknown values fall back to the default. They map to CSS custom properties (`--afs-ratio`, `--afs-cols`) set from the validated values only.

## Work packages (in order)

| # | Package | Main files | Effort |
|---|---|---|---|
| 1 | **Config plumbing.** New keys in `normalize_config`; add them to `route_args()` and `config_and_state()`; include in the client config; they join the cache key automatically because the whole config is hashed. | `class-afs-renderer.php`, `class-afs-rest.php`, `class-afs-feed.php` | M |
| 2 | **Card helpers.** One small class that returns the image, excerpt and meta for the current post: `wp_get_attachment_image` with `sizes`, lazy loading and dimensions; missing-image tile; word-based excerpt; **skips image and excerpt for password-protected posts** (same guard as `get_the_selected_excerpt`); call `update_post_thumbnail_cache()` for the query to avoid one query per card. | new `includes/class-afs-card.php` | M |
| 3 | **Designs and templates.** Register `editorial` and `library` in `AFS_Design::all()` with a template name each. `return_template()` gets an optional fourth argument (the options) so existing overrides keep working; theme overrides of `editorial.php` and `library.php` work through the same lookup. Consume `afs_register_design` for third-party designs (first use of the registry). | `class-afs-design.php`, `functions.php`, `afs-template/editorial.php`, `afs-template/library.php` | M |
| 4 | **CSS.** *(Done.)* Three files instead of two: a shared `afs-discovery.css` (controls, chips, result bar, pagination, states) plus `afs-editorial.css` and `afs-library.css`, enqueued only for feeds that use them, like `afs-modern`. About 5.9 KB gzip together with the base. Selects drop the native arrow (`appearance: none`) for an inset chevron about 1 rem from the right edge, with a dark-theme variant. Tokens from the prototype, `color-mix` with fallbacks, dark theme through theme colours and `prefers-color-scheme`, reduced motion, logical properties. The grouped top bar (search, count, sort, layout, chips) is new markup for the new designs only. | `public/css/`, `class-afs-frontend.php` | M |
| 5 | **Settings and block UI.** *(Done.)* Design picker gains the two designs; new card options as a "Cards" group; block sidebar controls; **term picker** (token field from core data, stored as the existing comma-separated slugs, so no migration). | `class-afs-settings.php`, `blocks/feed/block.json`, `editor.js` | M |
| 6 | **Block patterns.** *(Done.)* One pattern per design that inserts a configured block. | new `includes/patterns.php` | S |
| 7 | **Cache.** Confirm `AFS_Cache::mark()` runs when an attachment or featured image changes (`edit_attachment`, `delete_attachment`); add if missing. | `class-afs-cache.php` | S |
| 8 | **Tests.** See below. | `tests/` | M |
| 9 | **Docs and assets.** Fix the screenshot capture race, retake and add screenshots, readme changelog and FAQ, `docs/extending.md`, `.pot`, `docs/RELEASE-2.3.0.md`. | `docs/screenshots/capture.mjs`, `readme.txt`, `docs/` | M |

Overall: L (roughly 3 weeks of focused work). Packages 1 to 3 first, since 4 to 6 depend on them.

## Compatibility and migration

- Existing feeds resolve to the same design as before (`AFS_Design::resolve()` is unchanged for `classic` and `modern`), so no output changes. The 2.0.0 snapshots and the Classic snapshot suite must pass with zero diff.
- No data migration. New settings are additive keys with defaults that reproduce current behaviour. The block's new attributes default to empty.
- Template overrides: `default.php` is unchanged and still the template for Classic and Modern. `return_template()` keeps its three-argument call working.
- `AFS_API_VERSION` is bumped only if a registry contract changes; consuming `afs_register_design` is documented as additive.

## Security and performance

- Excerpts and images are never output for password-protected posts. A test covers drafts, private and protected posts.
- Images through core functions only; excerpts escaped; ratio, columns and design from fixed lists; nothing from the request is echoed into CSS.
- Budgets: core CSS plus one new design at 12 KB gzip or less (today the Classic CSS is about 2.2 KB gzip and Modern about 3.0 KB; JS about 4.4 KB). No new JS is planned. Images get width and height so layout shift is zero.
- No outbound requests, no new dependencies.

## Acceptance criteria

1. Existing snapshot, security, REST caching and contract suites pass unchanged.
2. A feed with `design="editorial"` renders identical markup on first load and through REST (contract test, including card options).
3. Axe reports zero violations for both designs in light and dark, at desktop and 390 px.
4. Core CSS plus one new design is 12 KB gzip or less; no layout shift from images (CLS 0 in the e2e check).
5. Password-protected, draft and private posts never expose an excerpt or image.
6. The term picker is operable by keyboard and writes the same attribute value the text field did.
7. A fresh install reaches a styled Editorial feed in five steps or fewer (e2e, timed).
8. The `afs_cache_version` changes when a featured image or attachment is edited.
9. A theme override of `default.php` still takes effect for Classic and Modern.

## Regression tests

Existing: `LegacySnapshotTest`, `SecurityRegressionTest`, `RestCachingTest`, `SortChipsStatesTest`, `DesignTest`, e2e `visual`, `a11y`, `contract`, the classic-theme compose run and the upgrade test from the previous release. New: option validation (valid, invalid, hostile), the design pass-through contract, protected-post guards, cache invalidation on attachment change, term picker e2e, visual baselines for both designs (light, dark, desktop, mobile).

## Documentation and screenshots

Fix the capture race (wait for `.afs:not(.afs-loading)` and `aria-busy="false"` instead of `networkidle`), retake all screenshots, lead the listing with the Editorial grid, add Library, mobile, dark and the block patterns. Update the readme changelog, FAQ and Upgrade Notice. Protected text (`readme.txt`, the plugin header version) needs the `approved-protected` label on the PR from the start.

## Release steps

Topic branches per package merged into `release/2.3.0`, one PR with the label, all CI green, then the same publish flow as 2.2.1 and 2.2.2 (you run the SVN commit), tag, docs record, live verification after the 6-hour hold.

## Open decisions

1. Final design names.
2. Default design for new installs (Modern today; Editorial proposed).
3. Whether Classic and Modern should ever get image and excerpt options (proposed: no).
4. Whether to add an author-name option (not in this scope).

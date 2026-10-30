# 1.0.3 to 2.0.0 compatibility

Compared from `git show v1.0.3:...` against this branch. "Verified" means I ran it on the test site; the rest is from reading the code.

## Summary of risk
| Risk | Who is affected |
|---|---|
| **High** | Sites with custom CSS or JS written against the old Bootstrap-style markup (`#newsForm`, `.btn`, `.col-xs-*`, `.fa`, etc.) |
| **High** | Sites that relied on `[ajax_filter_search filter_by="..."]` as a keyword preset (its meaning changed) |
| **Medium** | Themes or plugins that relied on things 1.0.3 leaked globally: the Font Awesome CSS, the front-end `ajaxurl`/`scriptpath` JS variables, jQuery being loaded on every page |
| **Low** | Theme template overrides (verified working), saved settings (verified), the two shortcodes (still registered) |

## HTML markup (default output)
| 1.0.3 | 2.0.0 |
|---|---|
| `div#afs-wrapper > div.press-releases > form#newsForm > div.row > div.col-xs-1TableRowItem2 ...` | `div#afs-wrapper.afs.afs-wrapper.afs-layout-list|grid > h2.afs-sr-only + form.afs-form + div.afs-results` |
| IDs: `newsForm`, `newsPanel`, `newsPanelResults`, `newsViewOptionsPanel`, `updateBtn`, `resetBtn`, `pageLastRecord`, `recordCount`, `post-<ID>` on items | **Removed** (duplicate IDs broke multi-feed pages). A second feed on a page gets `#afs-wrapper-2`, and so on |
| Bootstrap classes: `row`, `col-xs-*`, `col-sm-*`, `form-control`, `form-group*`, `btn btn-primary|default`, `pull-right`, `clearfix`, `hidden-xs`, `visible-xs`, `has-feedback` | **Removed** (`col-sm-2` / `col-sm-10` still exist only inside items so old templates keep working). Buttons are `.afs-btn`, `.afs-btn-primary` |
| Font Awesome icons (`span.fa fa-search|list-ul|th|plus-square|chevron-right`, pagination arrows) | **Removed**; no external icon font. Arrows are text characters |
| Other legacy classes: `press-releases`, `scroll`, `jscroll-inner`, `afs-Panel*`, `afs-FilterPanel1`, `afs-TableWrapper`, `afs-Loadingdata`, `afs-HasGA`, `afs-GaLabel`, `afs-EventTracking`, `role="article"` | Removed |
| Kept: `afs-Tabs`, `afs-CommonTabs` (+ `li.active`), `afs-Filters`, `afs-Switch`, `afs-TableHeader`, `afs-TableRowItem`, `gridPR<n>`, `prDateCol`, `afs-PRDate`, `afs-PRTimezone`, `afs-PRTime`, `afs-timezone`, `afs-PRTools`, `afs-PRSummary`, `post-nav`, `pagination` | |
| Tabs: `<a rel="slug" href="#">` | `<a data-afs-term="slug" href="?afs_term=slug">` inside `<nav>`; the mobile `<select name="category">` dropdown is gone (tabs wrap) |
| List/Grid switch: `<li><a rel="listPR|gridPR">`, wrapper class `afs-TableWrapper.gridPR` | `<button data-afs-layout aria-pressed>`; wrapper class `afs-layout-grid|list` |
| Filters: unlabeled inputs (`name=filterBy|filterMonths|filterYears`, `type=text`) in a `<form id=newsForm>` | Labeled fields `afs_s`, `afs_month`, `afs_year` (`type=search`), form is a real GET form |
| Summary toggle: `<a class="showSummary">` + `.afs-Hidden` panel | `<button class="afs-summary-toggle" aria-expanded>` + `hidden` panel (old class still works in overridden templates; verified) |
| Item date shown twice (column + `prDateRow`), `n/d/Y` format | Shown once, in the site's date format; category `<h6>` is `<p class="afs-terms">`, title `<h4>` is `<h3>` (default template only) |
| Pagination: `a.btn.btn-default|btn-primary` with `href="2"`, prev/next with title attributes | `nav.afs-pagination.post-nav > ul.pagination > li > a.afs-btn[data-afs-page]`, real URLs, `aria-current` |
| Count text: "Displaying X to Y (of N)" with `#pageLastRecord`, `#recordCount` | Same text; spans are classed `afs-first|afs-last|afs-total` |

## CSS and assets
| 1.0.3 | 2.0.0 |
|---|---|
| `core/css/style.min.css` (~200 KB Bootstrap 3 subset + custom), `main.min.css`, `custom.min.css`, SCSS sources, CodeKit config | One file, `public/css/afs.css` (~7 KB), custom-property themable (`--afs-accent` etc.). Old file URLs no longer exist |
| Font Awesome 4.6.3 loaded from `maxcdn.bootstrapcdn.com` on **every** page | Not loaded |
| Hard-coded font `proxima-nova`, 18px, colors `#f1b45c`, `#798490` | Inherits the theme font; accent still `#f1b45c` by default |
| Style handle `afs-styles` | `afs-styles` (same handle, new file) |
| Script handles `afs-script-js`, `afs-fontawesome` | `afs-script`; `afs-fontawesome` removed. Code that dequeues/deregisters the old handles will silently do nothing |
| Assets enqueued on every front-end page | Only on pages that render a feed |
| jQuery dependency; globals `ajaxurl` and `scriptpath` printed in `wp_head` on every page | No jQuery dependency (WordPress may still load it for other reasons); the globals are **no longer printed** |

## Shortcodes
| Item | 1.0.3 | 2.0.0 |
|---|---|---|
| `[ajax_filter_search]` | registered; attributes `post_type, post_tax, posts_per_page, filter_type, filter_by, filter_months, filter_years, filter_withPDF, offset` | registered; same attributes accepted except `filter_withPDF` (was never used; ignored). Adds `terms, layout, orderby, order, search, show_tabs, show_views, show_search, show_months, show_years` |
| **`filter_by`** | passed to the query as the **search keyword**, although the readme documented it as category slugs | **Limits to term slugs**, as documented. Keyword preset is now `search="..."`. Anyone using `filter_by` as a keyword must switch to `search` |
| `filter_type` | term slugs | Unchanged (alias of `terms`) |
| `offset` | page number (despite the name) | Unchanged |
| Per-shortcode `post_type` / `posts_per_page` / `filter_*` | honored on first page load, **lost** on the first ajax update (ajax used only the global settings) | Honored for the life of the page; this is a bug fix but changes what visitors see after clicking |
| Two feeds on one page | broke (duplicate IDs, PHP "constant already defined") | Supported |
| `[afs_feed]` | registered (inner shortcode) | Still registered; output is results only, wrapped in `div.afs`, no ajax |
| Query | no `post_status` (drafts leaked through ajax) | `post_status=publish`; sticky posts not pinned; order unchanged (date DESC) |
| "Uncategorized" tab/label hiding | by term **name** | by term **slug** |
| Ajax transport | `admin-ajax.php?action=my_action` (POST) | `GET /wp-json/afs/v1/results`. Anything calling `my_action` or `get_selected` directly, or blocking REST for visitors, will break |
| Without JavaScript | non-functional filters | Works through normal links and a GET form |

## Settings
- Option `afs_settings` and its keys (`afs_general_post_type`, `_post_taxonomy`, `_posts_per_page`, `_show_filters`, `_views`) are **unchanged**; old data loads as-is (verified with a 1.x-shaped option).
- New keys: `afs_general_layout`, `afs_appearance_accent`. Defaults fill in.
- Values are now validated on save (posts per page clamped to 1 to 100, taxonomy must belong to the post type).
- Settings page URL is the same (Settings > Ajax Filter Search); the single-tab layout became one page with two sections. The color picker and media-library scripts no longer load on every admin page.
- `uninstall.php` is new: deleting the plugin removes `afs_settings`.

## Public PHP interfaces
| Item | Status |
|---|---|
| `AFSAdmin::afs_retrieve()`, `AFSAdmin::afs_get_option()`, `AFSAdmin::init()`, `AFSAdmin::plugin_settings_link()` | **Kept** (same signatures) |
| `get_the_selected_excerpt( $length )`, `return_template( $template )` | **Kept** (output is now plain text, and `return_template` accepts two optional extra args and checks the parent theme as well as the child) |
| Constants `AFS_VERSION`, `AFS_URL`, `AFS_SUB`, `AFS_SETTINGS`, `AFS_PAGE_TITLE`, `AFS_MENU_TAB_TITLE`, `AFS_CAPABILITIES`, `AFS_MENU_SLUG`, `AFS_PLUGIN_PATH`, `AFS_PLUGIN_FILE`, `AFS_PLUGIN_URL` | Kept |
| Constant `FILTER_TYPE` (used by 1.x templates) | Kept for BC, but only defined once per request, so it reflects the **first** feed only. New code should use `$afs_terms` |
| Constants `AFS_RELEASE`, `AFS_CALLBACK`, `AFS_PLUGIN_ADMIN_URL` | **Removed** |
| Classes `AFSSettings`, global `$afs`, function `AFSinit()` | **Removed** (the main file now boots the new classes directly) |
| `AFSAdmin::$tabs`, `init_setting_tabs()`, `afs_settings_init()`, `afs_add_admin_menu()`, `afs_options_page()`, the `afs_general_*` / `afs_style_options_*` field callbacks, `load_core_scripts()`, `load_admin_scripts()` (old behavior) | **Removed / renamed** |
| Functions `ajax_filter_search()`, `afs_feed()`, `afs_page_navi()`, `my_action_callback()`, `get_selected_taxonomy()`, `myplugin_ajaxurl()` | **Removed** (the shortcodes are now registered by `AFS_Frontend`; call `do_shortcode()` instead) |
| Hooks `wp_ajax_my_action`, `wp_ajax_nopriv_my_action`, `wp_ajax_get_selected`, `wp_ajax_nopriv_get_selected`, `wp_head` ajaxurl printer | **Removed** |
| Filter `wp_bootstrap_pagination_defaults` | Removed |
| New | REST route `afs/v1/results`, filters `afs_query_args`, `afs_template_file`, block `ajax-filter-search/feed`, classes `AFS_Renderer`, `AFS_REST`, `AFS_Frontend` |

## Theme template overrides (`your-theme/afs-template/default.php`)
- Path and filename are unchanged. **Verified:** the unmodified 1.0.3 `default.php` installed as a theme override renders 10 items with no PHP warnings, and its `a.showSummary` toggle works (a small compatibility shim was added for that).
- Old overrides keep their old markup, so they lose Font Awesome icons and keep their Bootstrap column classes (only `col-sm-2`/`col-sm-10` are styled).
- New variables available inside templates: `$afs_terms`, `$afs_taxonomy`.

## Likely breakages on existing installs (checklist)
1. Custom CSS targeting old IDs/classes. Fix: re-target `.afs-*` or the new `--afs-*` variables.
2. Anything using `filter_by` as a keyword. Fix: use `search`.
3. Theme/plugins that used the plugin's Font Awesome, `ajaxurl` global, or jQuery on the front end.
4. Caching or security plugins that block `/wp-json/` for anonymous visitors (the filters need it).
5. Code that dequeues `afs-script-js` / `afs-fontawesome`, or calls removed PHP functions (`afs_feed()`, `afs_page_navi()`).
6. Visual differences: font, spacing and date format now follow the theme and site settings.

## Recommended release order
Ship the security-only **1.0.4** first (zero front-end change), then publish 2.0.0 as a clearly labeled major version with the upgrade notice already in `readme.txt`.

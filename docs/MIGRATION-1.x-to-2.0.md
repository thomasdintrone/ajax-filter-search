# Migrating from Ajax Filter Search 1.x to 2.0

2.0 is a major update. Most sites need no changes, but the front-end markup and styles are new.

## Before you update
1. **Back up** your site (files and database).
2. **Try it on a staging copy** if you have one, and look at every page that shows a feed.
3. Make sure you are on 1.0.4 first (the last 1.x release; it includes the security fixes that 2.0 also contains).

## What keeps working (no action needed)
- Your settings (Settings > Ajax Filter Search) carry over unchanged.
- `[ajax_filter_search]` and `[afs_feed]` and their attributes (`post_type`, `post_tax`, `posts_per_page`, `filter_type`, `filter_months`, `filter_years`, `offset`).
- Theme template overrides at `your-theme/afs-template/default.php`.
- The PHP helpers `get_the_selected_excerpt()`, `return_template()` and `AFSAdmin::afs_retrieve()`.
- A deprecated compatibility layer keeps the 1.x functions (`afs_feed()`, `ajax_filter_search()`, `AFSinit()`), classes and constants, the old `my_action` ajax action, the old script/style handles (`afs-script-js`, `afs-fontawesome`), the first feed's element IDs (`newsForm`, `newsPanelResults`, `updateBtn`, `resetBtn`, `newsViewOptionsPanel`) and the `ajaxurl` / `scriptpath` JavaScript globals (on pages with a feed) working. To switch it all off: `add_filter( 'afs_enable_legacy_compat', '__return_false' );`

## What changes (check these)
| Area | 1.x | 2.0 | What to do |
|---|---|---|---|
| Markup and CSS | Bootstrap-style classes and a ~200 KB stylesheet (`.row`, `.col-*`, `.btn`, `#newsForm`, ...) | New markup, one small stylesheet scoped to `.afs`, themable with `--afs-*` CSS variables | Re-check pages; update custom CSS to the `.afs-*` classes, or the variables |
| Icons | Font Awesome loaded from a CDN | No icon font | Add your own icons if your theme relied on this one |
| `filter_by` | Keyword preset (readme said category slugs) | Limits the feed to category slugs | Use `search="keyword"` for a starting keyword |
| Per-shortcode settings | Lost after the first filter click | Kept | Nothing; this fixes a 1.x bug |
| Multiple feeds | Broke | Supported | Optional |
| Ajax transport | `admin-ajax.php?action=my_action` | `/wp-json/afs/v1/results` (REST) | If a security or caching plugin blocks REST for visitors, allow `/wp-json/afs/v1/`. Custom code calling `my_action` still works via the compatibility layer, but move to REST |
| JavaScript | jQuery | Plain JavaScript | Custom scripts that hooked the old DOM need updating |
| Template overrides | Old markup | Still used as-is; they keep their old markup, so they lose icons | Optionally rebase on the new `afs-template/default.php` (adds `$afs_terms`, `$afs_taxonomy`) |
| Unpublished posts | Feed excluded drafts on page load | Feed only ever lists published posts, including for administrators (1.0.4 shows private posts to users allowed to read them) | None |

## New things you can use
Gutenberg block ("Ajax Filter Search"), `terms`, `layout`, `orderby`, `order`, `search`, `show_tabs`, `show_views`, `show_search`, `show_months`, `show_years` shortcode attributes, shareable filter URLs, an accent color setting, and the `afs_query_args` and `afs_template_file` filters.

## If something looks wrong
1. Clear page and CDN caches (old pages may hold the old markup).
2. Check the browser console and your PHP error log.
3. Roll back to 1.0.4 from your backup or by installing the 1.0.4 zip from the plugin's Advanced View page, then tell us what broke: https://wordpress.org/support/plugin/ajax-filter-search/

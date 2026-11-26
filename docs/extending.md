# Extending Ajax Filter Search

This describes the public extension points of the free plugin. They are versioned by `AFS_API_VERSION` (an integer defined in `includes/api.php`, not in the plugin header). It is bumped only when something an add-on must react to changes. An add-on should check it and show an admin notice, not a fatal error, if the free plugin is older than it needs:

```php
if ( ! defined( 'AFS_API_VERSION' ) || AFS_API_VERSION < 1 ) {
    // show a notice and stop
}
```

The free plugin only stores what add-ons register. It never checks licenses and never makes outbound requests.

## Registries

Register on the `afs_register` action (fires on `init`, priority 5). Each call returns `true` when stored, `false` for an invalid id or a duplicate (the first registration wins).

| Function | Registry |
|---|---|
| `afs_register_field( $id, $args )` | filter fields |
| `afs_register_design( $id, $args )` | designs |
| `afs_register_template_part( $id, $args )` | template parts |
| `afs_register_admin_tab( $id, $args )` | settings screen tabs |
| `afs_register_rest_meta( $id, $args )` | extra keys for the REST response |

`afs_registered( $type )` returns the items of a type; the `afs_registry_items` filter can adjust them. `$args` is owned by the registrant. The free plugin reads **designs** (2.3.0): a design registered with `afs_register_design( 'acme', array( 'label' => 'Acme', 'template' => 'acme.php', 'item_class' => 'acme-card' ) )` can be used as `design="acme"` on a shortcode or block. `template` is a plain file name looked up like `default.php` (your theme's `afs-template/` first, then the plugin's); without it the design uses the default item markup and is only a class on the feed (`afs-design-acme`). A built-in slug (`classic`, `modern`, `editorial`, `library`) cannot be replaced. The other registries are stored but not yet read; later versions will consume them behind the same API.

## Filters

| Filter | Purpose |
|---|---|
| `afs_query_args` | Change the `WP_Query` arguments of a feed. `post_status` and `perm` are reset to published-only afterwards, so the filter can sort, exclude or add clauses but cannot expose drafts, scheduled or private posts. |
| `afs_template_file` | Replace the template used for each result item. |
| `afs_rest_response` | Adjust the `/afs/v1/results` response data. The response carries `html`, `found` and `pages` (unchanged since 2.0.0) and `meta` (`found`, `pages`, `page`, `per_page`, `instance`, `state`). Add keys; do not remove any. |
| `afs_rest_cacheable` | Return `false` to stop the results route from sending validators or caching (for example when your own `afs_query_args` filter depends on the visitor). Signed-in requests are never cached. |
| `afs_rest_cache_control` | The `Cache-Control` value of an anonymous results response (default `public, max-age=60, stale-while-revalidate=300`); return `''` to send none. |
| `afs_cache_version` | The counter behind the `ETag` and the cached results. It changes when posts, terms, the plugin's settings or the theme change; append to it to invalidate everything. A change to a template override or to your own filter is not detected, so give those a new value here. |
| `afs_noindex_filtered_states` | Return `false` to stop filtered views of a feed page from being marked `noindex, follow`. The setting "Hide filtered views from search engines" (default on) does the same for the whole site. The unfiltered page stays indexable and WordPress already sets its canonical link to the permalink without query arguments. |
| `afs_register_patterns` | Return `false` to leave out the two block patterns (Editorial Grid and Resource Library) that the plugin adds to the inserter under "Ajax Filter Search". |
| `afs_enable_feed` | Return `false` to remove the RSS route. |
| `afs_enable_legacy_compat` | Turn the 1.x compatibility layer off. |

## Designs

A feed has one of two designs. **Classic** is the look of 2.0 and 2.1; **Modern** is a cleaner card-based look. New installs get Modern; a site that already had the plugin keeps Classic until it chooses otherwise (Settings > Appearance > Design).

- Per feed: `design="modern"` or `design="classic"` on the shortcode, or the Design setting of the block. It overrides the site setting.
- The markup is the same in both designs. A Modern feed only adds the class `afs-design-modern` to its wrapper, and its stylesheet (`public/css/afs-modern.css`) is loaded only when a Modern feed is on the page, so Classic pages are unchanged.
- Theming: both designs read the `--afs-*` custom properties. Modern also has `--afs-radius`, `--afs-radius-lg`, `--afs-border`, `--afs-surface`, `--afs-soft`, `--afs-muted`, `--afs-ink`, `--afs-tint` and `--afs-on-accent`, for example `.afs-design-modern { --afs-radius: 4px; --afs-surface: #fafafa; }`.
- Text on the accent colour is chosen automatically (white or near-black, whichever contrasts more), so any accent colour stays readable in both designs.
- `AFS_Design::resolve()` returns the design for a request and `AFS_Design::site_default()` the site-wide default.

## Sort, filter chips and empty states (opt-in)

Three helpers are off unless turned on, so existing feeds render exactly as in 2.0.0:

| Option | Shortcode | Block | Setting |
|---|---|---|---|
| Sort control: Newest, Oldest, A to Z, Recently updated, Most commented | `show_sort="true"` | Sort control | Settings > Sort control |
| Active filter chips, Clear all, and a "Clear all filters" link when nothing matches | `show_chips="true"` | Active filter chips | Settings > Active filter chips |

The shortcode or block value overrides the setting. The sort is the visitor's choice and is kept in the address bar as `afs_sort` (left out when it equals the feed's own order) and accepted by `/afs/v1/results` and `/afs/v1/feed`; an unknown value is ignored. Chips are plain links, so they work without JavaScript; the script turns a click into an update. The sort is not a filter: Clear all and the chips keep it.

When an update fails, a feed that does not own the address bar shows a message with a Try again button; the first feed falls back to a normal page load.

## RSS feed

`GET /wp-json/afs/v1/feed` returns an RSS 2.0 feed for any filter state. It takes the same parameters as `/afs/v1/results` (`post_type`, `post_tax`, `terms`, `posts_per_page`, `orderby`, `order`, `afs_s`, `afs_term`, `afs_month`, `afs_year`, `afs_page`), validated the same way. It uses the same query as the results (`AFS_Query`), so only published content is listed and `afs_query_args` applies, and WordPress's own RSS template renders it, so escaping is core's. Anonymous responses carry an `ETag` and `Cache-Control`; signed-in requests are never cached. Validator matching tolerates the `-gzip` suffix Apache adds.

`afs_feed_url( $config, $state )` builds the URL for a normalized config and state (`AFS_Renderer::normalize_config()` and `normalize_state()`). Return `false` from the `afs_enable_feed` filter to remove the route. The plugin does not yet print a subscribe link or a `<link rel="alternate">`; that is a visible change left for the design work.

## JavaScript

The front-end script exposes `window.afsRegistry`. An add-on can load before or after it:

```js
( window.afsRegistry = window.afsRegistry || { q: [] } );
var use = function ( api ) {
    api.on( 'results', function ( e ) { /* e.root, e.data (html, found, pages, meta), e.state */ } );
};
window.afsRegistry.use ? window.afsRegistry.use( use ) : window.afsRegistry.q.push( use );
```

| Hook | Payload |
|---|---|
| `loaded` | `{ root, config }`, once per feed when it is ready |
| `before-request` | `{ url, state, root }`; change `url.searchParams` to add parameters to the request |
| `results` | `{ root, data, state }`, after new results are in the page |
| `error` | `{ root, error }`, when a request fails |

`loaded`, `results` and `error` are also dispatched on the feed element as bubbling DOM events `afs:loaded`, `afs:results` and `afs:error`, with the payload in `event.detail`. A hook that throws is logged and ignored. `api.version` matches `AFS_API_VERSION`.

If a request cannot complete (network failure, server error, a response that is not JSON), the feed that owns the address bar falls back to a normal page load of the same filter state, which the server renders in full. Any other feed shows an error message.

## One query

Every output goes through `AFS_Query::args()` / `AFS_Query::run()` so the visibility rules are identical for the page, the REST route and anything built later.

## 1.x constant

The global `FILTER_TYPE` constant that 1.x theme overrides read is now defined by the compatibility layer (`AFS_Compat::define_filter_type()`), so it exists only while the layer is on. New templates should use the `$afs_terms` and `$afs_taxonomy` variables passed to them.

## Item templates and card options (2.3.0)

Each design renders its items with a template: Classic and Modern use `default.php`, Editorial Grid `editorial.php`, Resource Library `library.php`. Copy one to `your-theme/afs-template/` to override it (a child theme is checked first). Inside the loop a template gets `$afs_terms`, `$afs_taxonomy` and `$afs_options` (`show_image`, `image_ratio`, `columns`, `show_excerpt`, `excerpt_length`, `show_date`, `show_time`, `show_category`). `return_template( $template, $terms, $taxonomy, $options )` takes the options as an optional fourth argument, so 1.x calls with three arguments keep working.

`AFS_Card` has the helpers the built-in templates use: `image()` (a lazy-loaded featured image, or a placeholder tile, hidden from assistive technology), `excerpt()` (cut by words), `term_names()` and `has_image()`. Image and excerpt are never returned for a password-protected post; do the same in your own template.

A design with its own markup sends its name and options with every request so the first page and the results route render identical items. Classic and Modern send nothing extra.

## Styling the new designs (2.3.0)

Editorial Grid and Resource Library feeds carry `afs-design-editorial` or `afs-design-library` and `afs-discovery` on the root element, and load `afs-discovery.css` plus one file per design (never loaded for Classic or Modern). Colours come from the theme: borders, surfaces and muted text are mixed from the text colour, so a dark theme gives a dark feed. Tokens you can set from a theme: `--afs-accent`, `--afs-radius`, `--afs-radius-sm`, `--afs-gap`, `--afs-control` (control height). The plugin sets `--afs-cols` and `--afs-ratio` per feed from the validated `columns` and `image_ratio` options; the grid never goes below 15rem per card, so `columns` is a maximum that drops on narrow screens.

For these designs the active-filter chips and the result count are grouped in a `.afs-resultbar` above the items (Classic and Modern keep the count below).

## Settings and block (2.3.0)

The settings screen has a Cards group with site-wide defaults for the card options (`afs_cards_show_image`, `afs_cards_image_ratio`, `afs_cards_columns`, `afs_cards_show_excerpt`, `afs_cards_excerpt_length`, `afs_cards_show_date`, `afs_cards_show_time`, `afs_cards_show_category`, all inside the `afs_settings` option). A shortcode attribute or block setting overrides the default for one feed; an unset block control (empty or 0) falls back to it. Sites that saved settings before 2.3.0 get the defaults with no migration, because missing keys read as their defaults. Classic and Modern ignore the card settings.

The design picker and the block's Design control list every design from `AFS_Design::all()`. In the editor the block gets `window.afsEditor` (the designs and whether each has its own markup, the site's default design and taxonomy) and shows the Cards controls only when the effective design has its own markup. The Limit-to-terms control is a searchable picker over the feed's taxonomy; the block still stores the slugs, comma separated, exactly as the shortcode's `terms` attribute does.

## Block patterns (2.3.0)

The inserter has two patterns in an "Ajax Filter Search" category: an Editorial Grid (six cards, three columns, sort and chips on) and a Resource Library (ten rows, sort and chips on). Each is just the feed block with attributes it already has, so it adds no settings, and everything in it can be changed in the block sidebar after inserting. `AFS_Patterns::definitions()` lists them.

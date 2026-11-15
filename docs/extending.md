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

`afs_registered( $type )` returns the items of a type; the `afs_registry_items` filter can adjust them. `$args` is owned by the registrant. In this version the free plugin stores registrations but does not yet read them; later versions will consume them behind the same API.

## Filters

| Filter | Purpose |
|---|---|
| `afs_query_args` | Change the `WP_Query` arguments of a feed. `post_status` and `perm` are reset to published-only afterwards, so the filter can sort, exclude or add clauses but cannot expose drafts, scheduled or private posts. |
| `afs_template_file` | Replace the template used for each result item. |
| `afs_rest_response` | Adjust the `/afs/v1/results` response data. The response carries `html`, `found` and `pages` (unchanged since 2.0.0) and `meta` (`found`, `pages`, `page`, `per_page`, `instance`, `state`). Add keys; do not remove any. |
| `afs_rest_cacheable` | Return `false` to stop the results route from sending validators or caching (for example when your own `afs_query_args` filter depends on the visitor). Signed-in requests are never cached. |
| `afs_rest_cache_control` | The `Cache-Control` value of an anonymous results response (default `public, max-age=60, stale-while-revalidate=300`); return `''` to send none. |
| `afs_cache_version` | The counter behind the `ETag` and the cached results. It changes when posts, terms, the plugin's settings or the theme change; append to it to invalidate everything. A change to a template override or to your own filter is not detected, so give those a new value here. |
| `afs_enable_legacy_compat` | Turn the 1.x compatibility layer off. |

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

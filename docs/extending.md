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
| `afs_rest_response` | Adjust the `/afs/v1/results` response data (`html`, `found`, `pages` are kept by default; add keys, do not remove them). |
| `afs_enable_legacy_compat` | Turn the 1.x compatibility layer off. |

## One query

Every output goes through `AFS_Query::args()` / `AFS_Query::run()` so the visibility rules are identical for the page, the REST route and anything built later.

## 1.x constant

The global `FILTER_TYPE` constant that 1.x theme overrides read is now defined by the compatibility layer (`AFS_Compat::define_filter_type()`), so it exists only while the layer is on. New templates should use the `$afs_terms` and `$afs_taxonomy` variables passed to them.

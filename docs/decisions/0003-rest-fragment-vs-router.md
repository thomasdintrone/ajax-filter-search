# 0003: REST fragment with JSON `meta`, or the Interactivity router (spike 2)

Date: 2026-11-14. Status: **accepted** (owner approved all three recommendations, 2026-11-15). Code: `spikes/rest/` (PHPUnit) and `spikes/frontend/` (router arm). Numbers: `.cache/spike-rest-results.json`, `.cache/spike-frontend-results.json`.

## REST fragment prototype (PHPUnit, WordPress 7.1.4, 10,015 posts)

| Criterion | Result |
|---|---|
| Payload per interaction (JSON with HTML and `meta`) | 12 items: 12.9 KB raw, **1.6 KB gzip**; 100 items: 5.1 KB gzip (limit 15 KB) |
| Uncached p95 over 28 filter states | **25.6 ms** in-process (limit 300 ms); median 5.4 ms |
| Cached p95 | 0.2 ms (limit 50 ms); conditional request returning 304: 0.2 ms |
| `ETag` and `Cache-Control: public, max-age=60, stale-while-revalidate=300` | present; the ETag is derived from post and term change counters, so a 304 needs no query |
| Invalidation | publishing a post changes the ETag; unpublishing removes it from output |
| Two feeds | separate cache entries and ETags, correct item counts each |
| Signed config (HMAC over the feed-level fields) | valid signature 200; changed terms 403; raised `posts_per_page` 403; visitor state (search, page) not signed and still works; unsigned legacy-style request still 200 (lenient mode); **a salt rotation invalidates every issued signature** |
| Shipped `/afs/v1/results` | unchanged shape (`html`, `found`, `pages`), no cache headers |

Limits: timings are in-process with the default in-memory object cache and a warm database; a persistent cache (Redis, Memcached) and real HTTP at 10k posts were not measured. The signature protects feed-level config only if the shortcode output carries it; that markup change was not built.

## Router arm (WordPress 6.5.5 and 7.1.4, two feeds)

| Criterion | Result |
|---|---|
| Payload per interaction | whole page: **14.1-14.6 KB gzip** (78-88 KB raw) versus about 1.6 KB for the fragment, roughly 9x, and it grows with the theme |
| Layout shift during interactions | **0.16 (7.1.4) and 0.20 (6.5.5)**, above the 0.1 "good" line; fragment variants stay at 0 to 0.03 |
| Interaction p75 | 32 ms (7.1.4), 64 ms (6.5.5): fine |
| Multi-feed | second feed's state survived a first-feed navigation, but a second feed's interaction rewrites the shared `afs_*` URL parameters, so the feeds are not independent (parity check failed); fixing it needs per-feed URL namespaces |
| Search focus | kept while typing in the prototype |
| Theme independence | the router swaps regions from the full page, so output depends on the theme rendering the same region; a classic theme was not tested |

## Recommendation

Keep the REST fragment as the transport. Add JSON `meta` (found, pages, page, per_page, state) and `ETag` / `Cache-Control` as a backward-compatible addition: existing consumers still get `html`, `found`, `pages`. Do not use the router. Signed config is feasible and cheap, but its cost is real (a salt rotation breaks cached pages until they are purged); ship it in lenient mode first (accept unsigned requests) and decide separately whether to enforce.

## Not covered

Persistent object cache, real HTTP timing at 10k posts, CDN behavior for `stale-while-revalidate`, classic and Twenty Twenty-Five theme comparison, and screen-reader announcements.

## Implementation notes (2026-11-15)

Shipped as `meta`, `ETag`, `Cache-Control` and an object cache on `/afs/v1/results`; the 2.0.0 keys (`html`, `found`, `pages`) are unchanged and the frozen snapshots still pass. Signed config is not built yet.

- **A bug the PHPUnit run could not see.** The first version built the `ETag` from core's `wp_cache_get_last_changed()`. That value is only stable with a persistent object cache; on a site without Redis or Memcached it is regenerated on every request, so a conditional request never matched. Real HTTP tests caught it. The validator now comes from `AFS_Cache`, one autoloaded option bumped once per request when posts, terms, the plugin settings or the theme change.
- **Signed-in requests are never cached** and get `Cache-Control: private, no-cache`, because a site's own `afs_query_args` filter may depend on the visitor. A site can also turn caching off with `afs_rest_cacheable`.
- **Known limit:** edits to a theme template override or to a site's own filter code do not change the counter. The 60-second `max-age` and the 5-minute object-cache lifetime bound the staleness; `afs_cache_version` lets a site invalidate on demand.


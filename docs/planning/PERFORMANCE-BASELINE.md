# Performance baseline of AFS 2.0.0 (Phase 0)

Measured 2026-11-04. These are **development-container numbers, not product claims.**

## Method
- Stack: wp-env (Docker Desktop on an arm64 Mac, 10 CPUs), PHP 8.4.27, WordPress 7.1.4, MariaDB 12.3.3, WP_DEBUG on, **no persistent object cache**, opcache available.
- Data: synthetic posts (published, 12 repeated sentences of body text, random dates over 8 years, 1 to 2 of 44 categories each). Generator and benchmark are reproducible: `docs/planning/benchmark/generate-posts.php` and `benchmark-renderer.php` (run with `wp eval-file`).
- In-process timings call `AFS_Renderer::render_results()` with `wp_cache_flush()` before every run (cold), median of 5. "db ms" is summed query time from `$wpdb->queries`. HTTP numbers are the average of 10 to 20 `curl` requests to `/wp-json/afs/v1/results` including the full WordPress bootstrap.
- Limits: single run per size, no concurrency, no network latency, no page cache or CDN, one data distribution, no custom fields or taxonomy-heavy data.

## Results (median ms, cold; queries per request in brackets)
| Scenario | 1,000 posts | 10,000 posts | 50,000 posts |
|---|---|---|---|
| Page 1, no filters (10 items) | 8.7 (10 q) | 12.0 (10 q) | 27.6 (10 q) |
| Keyword search "report" | 12.5 (8 q) | **53.4** (8 q) | **245.3** (8 q) |
| One category (largest) | 8.5 (12 q) | 8.7 (12 q) | 13.8 (12 q) |
| Month and year | 3.8 (8 q) | 8.1 (8 q) | 9.9 (8 q) |
| Deep page (page 40) | 8.1 | 11.4 | 27.7 |
| 100 items per page | 65.9 | 70.6 | 93.5 |
| Widget shell (tabs and year list), cold | 10.5 (12 q) | 18.5 | 53.8 |
| Widget shell, warm | 8.4 (0 q) | 6.2 | 7.5 |
| HTTP: keyword search | | 110 ms | 301 ms |
| HTTP: one category | | 56 ms | 61 ms |
| HTTP: deep page | | 68 ms (page 300) | 76 ms (page 1500) |
Database size at 50,000 posts: 64 MB.

## What it tells us
1. **Taxonomy, date and pagination queries scale well** on WP_Query up to 50k posts (a category request stays under 15 ms of PHP time).
2. **Keyword search is the first bottleneck**: it grows roughly linearly (about 46 ms at 10k and 235 ms of DB time at 50k) because WordPress searches with `LIKE '%term%'`. An optional search index or MySQL FULLTEXT strategy is the first performance feature worth building; do not promise "instant search at 50k+" until it is built and measured.
3. **Rendering 100 items costs about 60 ms of PHP** (template, terms and excerpt per item), independent of data size; keep default page sizes small and consider lighter card templates.
4. **The widget's year list is the other cold-start cost** (54 ms at 50k posts, then cached). It could use a cheaper source or longer cache.
5. **Dynamic facet counts are not benchmarked** (not implemented). They would need an index; see TECHNICAL-ARCHITECTURE.md for the proposed design and the benchmark to run before committing to it.
6. Query counts are low and constant (8 to 12 per request).

## Next measurements (Phase 4 gate)
Repeat on production-like hosting with a persistent object cache; add custom-field and multi-taxonomy datasets; concurrent load (for example 20 parallel requests); measure memory per request; compare WP_Query vs a FULLTEXT or custom index for search; define budgets (suggested: p95 under 300 ms HTTP for taxonomy/date filters at 50k posts on typical shared hosting; keyword search under 500 ms until an index exists).

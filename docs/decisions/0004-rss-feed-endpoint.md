# 0004: RSS feed for a filtered view (spike 3)

Date: 2026-11-14. Status: **recommendation, awaiting owner approval**. Code: `spikes/rss/` (not shipped; excluded from the package).

Decision rule from the plan: use core feed URLs if they give full parity with the REST results; otherwise a thin endpoint that reuses the plugin's own query.

## What was measured

Run with `bin/test.sh --testsuite spikes --group spike` on WordPress 7.1.4 (40 tests, 98 assertions, all passing). Fixture: 20 posts across 3 categories, 3 years, with draft, pending, scheduled and private posts mixed in.

| Check | Result |
|---|---|
| Core feed URL vs AFS results for 34 filter states (category x year x month, keyword, no filter) | **34 of 34 return the same post IDs in the same order** (33 non-empty, 1 legitimately empty) |
| Several categories at once (`terms=news,events`) | Same IDs (OR semantics match) |
| Title sort, 5 per page, page 2 | Same IDs |
| Prototype endpoint reusing `AFS_Renderer::query()` (6 states) | Well-formed XML, `<guid>` list equals the AFS results |
| Prototype: draft, pending, scheduled, private content | Never appears |
| Prototype: `afs_query_args` filter | Applied |
| Prototype: `&`, `<`, `>`, quotes and `]]>` in titles and content | XML stays valid and the parsed title text is correct |

## Where core feed URLs fall short (the reason they fail the rule)

1. **`afs_query_args` is not applied.** A site that uses the filter to exclude a post sees that post in the core feed but not in the AFS results (measured: post 1001 present in the core feed, absent from AFS).
2. **A feed URL can ask for other post types.** `?feed=rss2&post_type=page` returned pages in the test environment. The AFS REST route only serves the post type and taxonomy a feed is configured for. A core URL cannot carry that restriction. (An earlier note that this parameter was "ignored" was wrong; it came from a quick HTTP probe and is corrected here.)
3. **Length follows the site-wide `posts_per_rss` setting**, not the feed's own `posts_per_page`.
4. The config-level restrictions (limited term set, post type) live in the shortcode or block, so a plain core URL cannot reproduce them for a given feed.

## Recommendation

Build a thin endpoint that calls the same query path as the REST results (the planned `AFS_Query` choke point), so one query definition serves HTML, JSON and RSS. Use core's RSS 2.0 template to render, so escaping and structure stay core's job. The prototype shows this is small and behaves identically for the tested states.

Notes for the build sprint:
- Core's `do_feed_rss2()` uses `require_once`, so it only outputs once per PHP process; the prototype loads the template directly with `load_template( ..., false )`. A real endpoint serves one feed per request, but tests must account for this.
- The prototype swaps the global `$wp_query`; the real implementation should restore it (the prototype does) and run inside a feed request.

## Not measured in this spike

ETag / Last-Modified / conditional GET, cache headers, auto-discovery `<link rel="alternate">` on pages, and request-rate limits. These are build-sprint acceptance criteria, not results. The spike does not show them working.

## Consequences

No production code changed. If approved, the feed becomes a Sprint 1+ item behind the `AFS_Query` refactor. It adds a read-only public endpoint, so it falls under the security regression suite (published-only, input caps) before release.

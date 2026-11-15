# Sprint 0 spikes

Throwaway experiments that inform decisions in `docs/decisions/`. Nothing here ships (excluded from zips and `git archive`), and nothing here changes production code: spike plugins hook the public seams (`afs_query_args`, REST filters, shortcode output filters).

- `rss/`: spike 3, feed parity against core feed URLs and a prototype endpoint (PHPUnit):
  `bin/test.sh --testsuite spikes --group spike`
- `rest/`: spike 2, REST response metadata, caching headers and signed config, plus a router comparison (needs a running stack).
- `frontend/`: spike 1, the Interactivity API prototype versus a plain ES module (needs running stacks on WordPress 6.5.5 and latest).

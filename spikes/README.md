# Sprint 0 spikes

Throwaway experiments that inform decisions in `docs/decisions/`. Nothing here ships (excluded from zips and `git archive`), and nothing here changes production code: spike plugins hook the public seams (`afs_query_args`, the `do_shortcode_tag` filter, REST route registration).

## `rss/` (spike 3, decision 0004)

Feed parity against core feed URLs and a prototype endpoint. PHPUnit:

```bash
bin/test.sh --testsuite spikes --group spike
```

Results are written to `.cache/spike-rss-results.json`.

## `rest/` (spike 2, decision 0003)

A prototype `afs-spike/v1/results` route with JSON `meta`, ETag / Cache-Control, an object cache keyed on post and term change counters, and an HMAC-signed config. The shipped `afs/v1/results` route is untouched and checked by the same tests. Runs in the same PHPUnit command as above (10,000 generated posts). Results: `.cache/spike-rest-results.json`.

## `frontend/` (spike 1 and the router half of spike 2, decisions 0002 and 0003)

Prototype plugins selected per request with `?afs_variant=` so one page can be compared in four forms:

| Variant | What it is |
|---|---|
| `baseline` | The shipped 2.0.0 script |
| `interactivity` | `data-wp-*` directives added to the widget markup (shortcode path, `wp_interactivity_process_directives`), a store in a script module, results fetched from the REST fragment |
| `router` | Same directives, but filtering uses the Interactivity router (fetches the whole page and swaps `data-wp-router-region`) |
| `esm` | The shipped script converted to an ES module plus a small hook registry |

`?afs_pro=first|last` loads a stand-in for a separate add-on in either order. `theme-classic/` is a tiny non-block theme for the classic-theme checks.

```bash
# one-time: two stacks, WordPress 6.5.5 (PHP 8.2) and the current release (PHP 8.4)
AFS_PORT=8865 WP_IMAGE=wordpress:6.5-php8.2-apache docker compose -p afs65 -f docker-compose.yml -f spikes/frontend/compose.spikes.yml up -d --wait
AFS_PORT=8871 WP_IMAGE=wordpress:php8.4-apache   docker compose -p afs71 -f docker-compose.yml -f spikes/frontend/compose.spikes.yml up -d --wait
spikes/frontend/setup-stack.sh afs65 8865 wordpress:6.5-php8.2-apache
spikes/frontend/setup-stack.sh afs71 8871 wordpress:php8.4-apache
spikes/frontend/wp.sh 65 plugin activate afs-spike-interactivity afs-spike-esm afs-spike-pro-stub
spikes/frontend/wp.sh 71 plugin activate afs-spike-interactivity afs-spike-esm afs-spike-pro-stub

# one-time: the browser driver (kept out of the repo)
mkdir -p .cache/spike-node && (cd .cache/spike-node && npm init -y && npm i puppeteer-core)

# measure (needs Google Chrome); writes .cache/spike-frontend-results.json
node spikes/frontend/measure.mjs 65 71
```

Interaction timing uses the Event Timing API with a 4x CPU throttle (`AFS_THROTTLE` to change it). With only 37 posts the numbers show script cost, not database cost; the database side is measured in `rest/`.

Tear down with `docker compose -p afs65 down -v` and `docker compose -p afs71 down -v`.

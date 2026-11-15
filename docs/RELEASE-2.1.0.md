# Release 2.1.0: checklist

Status: **prepared, not published.** Nothing has been tagged, and nothing has been sent to WordPress.org. The steps below are for the person who owns the WordPress.org account to run; publishing is a manual, approved step (see `MAINTAINING.md`).

Package: `build/ajax-filter-search-2.1.0.zip` (built by `bin/build-zip.sh` from the tracked files and `.distignore`; 24 files; the allowlist check passed).

## What is in 2.1.0

Everything since 2.0.0, all backward compatible:

- Optional sort control and active-filter chips with Clear all, plus an empty-state link and a Try again button (off by default: `show_sort`, `show_chips`, block controls, Settings).
- RSS 2.0 feed for any filter state at `/wp-json/afs/v1/feed`.
- Results endpoint: a `meta` block, cache validators and `Cache-Control` for logged-out visitors, empty 304 answers.
- Filtered views are `noindex, follow` (setting, on by default).
- Dismissible admin notice for 1.x theme overrides and styles, with a link to the migration guide.
- Extension points for add-ons (registries, `AFS_API_VERSION`, `afs_register`, `afs_rest_response`, `afs_cache_version`, `afs_feed_url()`, JavaScript hooks and DOM events).
- The Update button is hidden when JavaScript is active.
- Tab list cached.

## Behavior changes to know about

1. **Published only is enforced after the `afs_query_args` filter.** A site that used that filter to widen `post_status` (for example to show private posts to editors) will stop seeing non-published posts. This is a security hardening and is stated in the changelog and the upgrade notice.
2. **Update button hidden with JavaScript.** Every control already applies on change and Enter submits the search; the button stays for visitors without JavaScript.
3. **Filtered views are noindex by default.** Sites that want them indexed can turn it off in Settings or with `afs_noindex_filtered_states`.
4. **Results responses are cacheable for 60 seconds for logged-out visitors** (`Cache-Control: public, max-age=60, stale-while-revalidate=300`). Edits to a theme template override or to a site's own filter code are not detected by the validator; `afs_cache_version` lets a site invalidate on demand.

## Files that differ from 2.0.0 (the shipping set)

Modified (14): `ajax-filter-search.php`, `blocks/feed/block.json`, `blocks/feed/editor.asset.php`, `blocks/feed/editor.js`, `includes/class-afs-frontend.php`, `includes/class-afs-renderer.php`, `includes/class-afs-rest.php`, `includes/class-afs-settings.php`, `includes/compat.php`, `languages/ajax-filter-search.pot`, `public/css/afs.css`, `public/js/afs.js`, `readme.txt`, `uninstall.php`.

Added (6): `includes/api.php`, `includes/class-afs-cache.php`, `includes/class-afs-feed.php`, `includes/class-afs-migration.php`, `includes/class-afs-query.php`, `includes/class-afs-registry.php`.

Unchanged: `LICENSE`, the copyright line, the plugin title and tags, and everything in `.wordpress-org/` (banner, icon, screenshots).

## Not changed, on purpose (decide separately)

- **Screenshots and banner.** The listing still shows the 2.0.0 screenshots. The sort control, chips and empty state are not shown in any of them. Adding screenshots means updating `.wordpress-org/` and the `== Screenshots ==` list in `readme.txt`.
- **Tested up to** stays 7.1 and **Requires at least** stays 6.0 (the script-module path needs 6.5 and is not used).

## Checks run on this exact build

Run `AFS_APPROVED_PROTECTED=1 bin/check-guards.sh` locally (the protected-text guard reports the approved header, version and readme change; in CI the pull request carries the `approved-protected` label).

- PHP coding standards, PHPStan level 5, ESLint.
- PHPUnit on WordPress 6.0/PHP 7.4, 6.5/PHP 8.2 and 8.3, 6.8/PHP 8.1 and 8.2, latest/PHP 8.4, a multisite run and the WordPress beta.
- Browser tests (behavior, accessibility, HTTP contract, extension points, visual) on a block theme and a classic theme.
- Upgrade from 1.0.4 and from 2.0.0 onto this build.
- Official Plugin Check on the release package.

The result of the local run is recorded in the pull request.

## Publish (WordPress.org SVN)

Enter the password only at the `svn` prompt, never in chat. These commands change nothing until the final `svn commit`.

```bash
cd ~/Repos/streetcar-productions/ajax-filter-search-svn
svn status                                   # expect: no output
svn up                                       # updates the working copy only
svn ls ^/ajax-filter-search/tags/            # expect: ... 2.0.0/ and no 2.1.0/

# 1. Replace trunk with the package, then record additions and deletions.
rsync -a --delete --exclude='.svn' ~/Repos/streetcar-productions/ajax-filter-search/build/ajax-filter-search/ trunk/
svn add --force trunk
svn status | grep '^!' | awk '{print $2}' | xargs -r svn rm   # only if anything was deleted (none expected)
svn status                                   # expect: 14 M and 6 A under trunk/, nothing else
svn diff trunk | less                        # eyeball: version 2.1.0, readme, the new includes

# 2. Tag.
svn cp trunk tags/2.1.0
diff -rq trunk tags/2.1.0 -x .svn            # expect no output

# 3. One commit for trunk and the tag. assets/ is unchanged.
svn commit trunk tags/2.1.0 --username longislandfreelancewebdesigner \
  -m "Release 2.1.0: sort, filter chips, RSS feed, caching headers, noindex for filtered views, extension points"
```

After the commit:

```bash
svn up && svn log -v -l 1                    # one revision: trunk changes and tags/2.1.0 added
svn diff ^/ajax-filter-search/trunk ^/ajax-filter-search/tags/2.1.0   # empty
```

WordPress.org holds a new release for about six hours before offering it to sites. Then verify the live update with `docs-private/verify/verify-live-update.sh` (the plugins API reports 2.1.0, the downloaded zip matches the package, and a site on 2.0.0 is offered the update).

## After publishing

Tag the same Git commit (the merge commit of the release pull request) and push the tag:

```bash
git checkout master && git pull
git tag -a v2.1.0 -m "Ajax Filter Search 2.1.0"
git push origin v2.1.0
```

## If something is wrong

A bad release is fixed by a new version, not by editing a published tag. Prepare 2.1.1 from `master`, repeat this checklist, and let WordPress.org offer the newer version. If the problem is serious, set the previous version as the stable tag in `trunk/readme.txt` as a stop-gap and commit that alone.

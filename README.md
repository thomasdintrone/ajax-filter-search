# Ajax Filter Search

WordPress plugin: a filterable, searchable, paginated list of posts or custom post types, updated with ajax. Published on WordPress.org as [ajax-filter-search](https://wordpress.org/plugins/ajax-filter-search/).

**GitHub is the source of truth for development. WordPress.org SVN is a release target only.** See the user-facing docs in [readme.txt](readme.txt).

## Layout

| Path | What |
|---|---|
| `ajax-filter-search.php` | Bootstrap and constants |
| `includes/` | Settings, renderer (HTML and queries), REST endpoint, shortcode/block/assets |
| `afs-template/default.php` | Per-item template; themes override it at `your-theme/afs-template/default.php` |
| `blocks/feed/` | Gutenberg block (no build step; `editor.js` uses the `wp.*` globals) |
| `public/` | Front-end CSS and JS (no dependencies) |
| `admin/js/` | Settings-screen script |
| `.wordpress-org/` | Store assets (banner, screenshots). Synced to SVN `/assets`, never shipped in the zip |
| `bin/` | Dev scripts (seed content, smoke test, zip build) |

## Development

Requires Docker, Node and Composer.

```bash
composer install
npx @wordpress/env start          # http://localhost:8890  (admin / password)
npx @wordpress/env run cli wp eval-file wp-content/plugins/ajax-filter-search/bin/seed-test-content.php
bin/smoke-test.sh                 # HTTP checks against the seeded site
composer lint                     # WordPress coding standards + PHP 7.4 compatibility
```

To test another PHP/WordPress version, add a git-ignored `.wp-env.override.json`, for example `{ "phpVersion": "7.4", "core": "WordPress/WordPress#6.0" }`.

## Release

1. Bump the version in `ajax-filter-search.php` (header and `AFS_VERSION`) and `Stable tag` / changelog in `readme.txt`; update `Tested up to`.
2. `bin/build-zip.sh` builds `build/ajax-filter-search-<version>.zip` from `.distignore`.
3. Copy the built tree into SVN `trunk/`, `svn add`/`svn rm` as needed, and sync `.wordpress-org/` into SVN `assets/`.
4. `svn cp trunk tags/<version>`, then `svn ci`.
5. Tag the same commit in git: `git tag -a v<version> && git push --tags`.

## License

GPL v2 or later.

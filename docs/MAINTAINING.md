# Maintaining Ajax Filter Search

GitHub is the source of truth for development. WordPress.org SVN is a release target only.

## Current state
| Item | Value |
|---|---|
| Production version | **2.0.0** (WordPress.org SVN revision r3775464, published 2026-10-30) |
| Previous releases | 1.0.4 security release (r3774078); 1.0.3 original |
| Supported | WordPress 6.0 and up (tested to 7.1.4), PHP 7.4 and up (tested on 7.4 and 8.4) |
| Git release commits | 2.0.0 = `36c8469`; 1.0.4 = `d39cb21`; 1.0.3 = `a510c2b` (tag `v1.0.3`). Tags `v1.0.4` and `v2.0.0` and the `master` update are prepared but pending approval |
| Source layout | see `README.md` |

## Build and package
```bash
composer install
bin/build-zip.sh        # builds build/ajax-filter-search-<version>.zip
```
The script starts from `git archive HEAD` (tracked files only), then removes everything in `.distignore`. The zip contains only production files (currently 18). Keep private notes in `docs-private/` (git-ignored, never packaged) and design sources in `design/` (not packaged).

## Test
```bash
npx @wordpress/env start
npx @wordpress/env run cli wp eval-file wp-content/plugins/ajax-filter-search/bin/seed-test-content.php
bin/smoke-test.sh && bin/compat-test.sh
composer lint
```
Also run the official Plugin Check against the shipping files, and test the minimum stack (a git-ignored `.wp-env.override.json` with PHP 7.4 and WordPress 6.0) and the latest stack. Accessibility was checked with axe (WCAG 2.2 AA) at desktop and phone widths.

## Release checklist
1. Bump the version in `ajax-filter-search.php` (header and `AFS_VERSION`) and in `readme.txt` (Stable tag, Tested up to, changelog, Upgrade Notice).
2. Run the tests above; build the zip; list its contents; confirm no docs, design, bin or private files.
3. In an SVN checkout: update `trunk/` to the package, copy `.wordpress-org/*` to `assets/` (set `svn:mime-type image/png`), `svn cp trunk tags/<version>`, review `svn status`, and commit trunk, tag and assets in one revision.
4. After publishing, verify: the plugins API, the downloaded zip against your package, the assets, and the readme. WordPress.org holds updates for about 6 hours before offering them to sites.
5. Tag the same Git commit (`v<version>`) and push.

## Security considerations
- The feed endpoint is public and read-only and lists published posts only; all input is validated and capped on the server. There are no state-changing front-end endpoints.
- The deprecated 1.x compatibility layer (including the `my_action` ajax action) reuses the same validation and renderer. Switch it off with `add_filter( 'afs_enable_legacy_compat', '__return_false' );`.
- Report vulnerabilities privately (see the security policy once added), not in public issues.

## Compatibility limitations
Tested with default WordPress themes and Chrome; Firefox, Safari, page builders and multisite are untested. The compatibility layer keeps 1.x functions, constants, handles, the legacy ajax action and the first feed's IDs, but the Bootstrap-based markup and the icon font are gone (see `docs/MIGRATION-1.x-to-2.0.md`). Private posts are never shown in feeds. The REST route must be reachable by visitors.

## Known issues and notes
- Sites with auto-updates enabled will install 2.0.0 without seeing the Upgrade Notice (it appears on Dashboard > Updates).
- The `LICENSE` file is GPLv3 while the plugin header and readme say GPL v2 or later; unresolved by decision.
- Uninstall removes the settings of the site it runs on (on multisite, other sites keep theirs).

## Roadmap (summary)
2.0.x stability and compatibility testing; 2.1.x small free enhancements driven by user feedback (parent-category tabs, custom labels, featured-image option); a possible separate Pro add-on is only under evaluation.

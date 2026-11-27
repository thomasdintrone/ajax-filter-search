# Maintaining Ajax Filter Search

GitHub is the source of truth for development. WordPress.org SVN is a release target only.

## Current state
| Item | Value |
|---|---|
| Production version | **2.3.0** (WordPress.org SVN r3828281, published 2026-11-26; Git tag `v2.3.0` = `3e2bc45`). Whether WordPress.org is offering the update to sites is checked with `bin/verify-live-release.sh 2.3.0 2.2.2`; do not assume it |
| Release history | 2.3.0 r3828281; 2.2.2 r3827794; 2.2.1 r3827748 (all three 2026-11-26); 2.2.0 r3809958 (2026-11-18); 2.1.0 r3803994; 2.0.0 r3775464 (2026-10-30, `36c8469`); 1.0.4 security release r3774078; 1.0.3 original. Each has a Git tag `v<version>`. Per-release notes: `docs/RELEASE-*.md` |
| In progress | 2.3.1 (audit fixes, `fix/2.3.0-audit-remediation`, PR 31): prepared, not published; see `docs/RELEASE-2.3.1.md` |
| Supported | WordPress 6.0 and up (tested to 7.1.4), PHP 7.4 and up (tested on 7.4 and 8.4) |
| Git | `master` is the integration branch (2.3.0 and its docs, `d679789`, when this was written). Historical: until the 2.0.0 release `master` equalled the 2.0.0 source (merge commit `40e44bd`) |
| Source layout | see `README.md` |

## Build and package
```bash
composer install
bin/build-zip.sh        # builds build/ajax-filter-search-<version>.zip
```
The script starts from `git archive HEAD` (tracked files only), then removes everything in `.distignore`. The zip contains only production files; `bin/check-package.sh` prints the count (38 files in the 2.3.0 build; it grows with the new designs, templates, block and admin assets, so use the script's number, not a number written here). Keep private notes in `docs-private/` (git-ignored, never packaged) and design sources in `design/` (not packaged).

## Test
```bash
composer install
bin/test.sh                      # PHPUnit in Docker: 2.0.0 snapshots, security regression, no-outbound-HTTP, published-only
bin/check-guards.sh              # protected text and no Pro/license code
bin/smoke-test.sh && bin/compat-test.sh   # HTTP-level checks against a running seeded site (docker compose or wp-env)
composer lint                    # WordPress coding standards + PHP 7.4 compatibility
composer analyse                 # PHPStan level 5 on the shipping code
npm ci --ignore-scripts && bin/e2e.sh   # Playwright + axe (WCAG 2.2 AA) + visual snapshots + interaction budget, inside the Playwright image
npm run lint:js                  # ESLint on the shipping JavaScript
AFS_THEME=afs-classic-min bin/e2e.sh   # behaviour, accessibility and HTTP-contract specs on a classic theme
bin/upgrade-test.sh 1.0.4        # install a published release, swap in this build, check nothing broke (also: 2.0.0, 2.2.2; AFS_STRICT=1 compares raw bytes, AFS_TEST_MODERN=1 starts on Modern). It builds from committed files (git archive HEAD), so commit first
bin/plugin-check.sh              # official Plugin Check against the release package
```
CI (`.github/workflows/ci.yml`) runs lint and PHPStan, the guards, PHPUnit on WordPress 6.0/PHP 7.4, 6.5/8.2, 6.5/8.3, 6.8/8.1, 6.8/8.2 and latest/8.4 (plus a latest multisite cell and a WordPress beta job that is allowed to fail; run multisite locally with `AFS_MULTISITE=1 bin/test.sh`), checks that the committed 2.0.0 snapshots equal what tag `v2.0.0` produces, builds the package, and runs the browser tests (block theme and a classic theme), Plugin Check and the two upgrade paths.

A nightly workflow (`.github/workflows/nightly.yml`) runs the behaviour, accessibility and contract specs in Firefox and WebKit (`PW_BROWSER=firefox bin/e2e.sh feed.spec.mjs`), and a latency check on about 10,000 posts (`AFS_PERF_10K=1 bin/e2e.sh`, budget REST p95 under 300 ms). `tests/e2e/contract.spec.mjs` holds the HTTP checks that used to exist only in `bin/smoke-test.sh` and `bin/compat-test.sh`; those scripts stay for manual use against a running site.

**Visual baselines** (`tests/e2e/__screenshots__`) are only valid inside the Playwright image that `bin/e2e.sh` uses. When a change is meant to alter the look, run `bin/e2e.sh --update-snapshots`, look at every changed image, and commit them with the change. The comparison tolerance is 0.2% of pixels, tight enough to notice a button appearing or disappearing. The Classic `list-dark-desktop` baseline is still byte-identical to `list-light-desktop` because Classic has no dark styling; it exists to catch a future dark Classic. The Editorial Grid and Resource Library have their own dark baselines (`designs.spec.mjs`: `editorial-dark-desktop`, `library-dark-desktop`), which do differ from light.

The e2e stack uses the seeded content from `bin/seed-test-content.php` (the default "Hello world!" post is deleted so dates stay fixed). Browser tests scan only the plugin's own region (`.afs`) with axe; theme markup is out of scope.

Test the minimum stack (a git-ignored `.wp-env.override.json` with PHP 7.4 and WordPress 6.0) and the latest stack when changing anything version-sensitive.

## Screenshots
`docs/screenshots/seed-demo.php` builds a small fictional demo newsroom and `docs/screenshots/capture.mjs` takes the ten WordPress.org screenshots from it (needs Google Chrome, `npm ci --ignore-scripts`, and a stack on `http://localhost:8892`).

1. `node docs/screenshots/fetch-photos.mjs` downloads the free Unsplash photos the demo articles use (listed in `photos.json`, credited in `CREDITS.md`) into `docs/screenshots/photos/`, which is git-ignored: the photos are never stored in the repository or shipped in the plugin.
2. Start the stack, install WordPress with the throwaway `admin` / `password` account, activate the plugin and run `seed-demo.php` (`wp eval-file`). The demo pages put their heading inside a wide group and use the theme's "Page (No Title)" template, so the title lines up with the feed (a demo-site setting, not plugin CSS).
3. `node docs/screenshots/capture.mjs` writes the ten images to `.cache/screenshots/`, framed from the page heading down to the bottom of the feed. It waits for each feed to finish loading (not for `networkidle`), refuses to save the filtered shot if the results do not match the filters, and refuses the two-feed shot unless the feeds show different states.
4. Copy the images to `.wordpress-org/`, look at every one, and keep the `== Screenshots ==` list in `readme.txt` in the same order (it is documented at the top of `capture.mjs`). Re-crop the two "What's new" thumbnails (`admin/images/whatsnew-*.webp`) from screenshots 1 and 2 (`cwebp`).

## Banner and icon
The approved artwork is raster, kept in `design/approved-artwork/` (not shipped; `design/` is in `.distignore`) with `asset-manifest.json` holding each source's SHA-256 and a copy of the handoff note. `design/export-approved-artwork.sh` verifies the hashes and writes `.wordpress-org/banner-1544x500.png`, `banner-772x250.png`, `icon-256x256.png` and `icon-128x128.png` (WordPress.org's sizes; no `icon.svg` is in `.wordpress-org/`, so the PNG icons are used). The old `design/render-assets.mjs` is superseded and refuses to run. There is no vector master and the typefaces are not identified; changes to the artwork need the owner's approval. `admin/images/icon.svg` (the settings screen header) is a separate vector and is unchanged.

## Release checklist
1. Bump the version in `ajax-filter-search.php` (header and `AFS_VERSION`) and in `readme.txt` (Stable tag, Tested up to, changelog, Upgrade Notice).
2. Run the tests above; build the zip; list its contents; confirm no docs, design, bin or private files.
3. In an SVN checkout: update `trunk/` to the package, copy `.wordpress-org/*` to `assets/` (set `svn:mime-type image/png`), `svn cp trunk tags/<version>`, review `svn status`, and commit trunk, tag and assets in one revision.
4. After publishing, verify: the plugins API, the downloaded zip against your package, the assets, and the readme. WordPress.org holds updates for about 6 hours before offering them to sites; once it has passed, `bin/verify-live-release.sh <new> <previous>` checks the API version, that the downloadable zip equals the tag, and that a site on the previous version is offered the update.
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

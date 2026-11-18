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
| `bin/` | Dev scripts (tests, snapshot generator, guards, seed content, smoke test, zip build and package check) |
| `tests/` | PHPUnit suite (`tests/phpunit`), 2.0.0 snapshots, fixtures |
| `.github/workflows/ci.yml` | CI: lint, guards, PHPUnit matrix, frozen-snapshot check, package build |

## Development

Requires Docker and Composer (Node is only needed for future front-end tooling).

### Tests (Docker, no local WordPress needed)
```bash
composer install
bin/test.sh                                    # PHPUnit on the default stack (WordPress 7.1 / PHP 8.3)
WP_IMAGE=wordpress:6.0-php7.4-apache bin/test.sh   # any wordpress:<ver>-php<ver>-apache image (the CI matrix)
bin/test.sh --group security                   # one group: snapshot, security
bin/test.sh --group known-gap                  # documented open gaps; these are expected to fail today
bin/generate-snapshots.sh                      # rebuild tests/snapshots/2.0.0 from the v2.0.0 tag (never from the working tree)
bin/e2e.sh                                     # Playwright + axe + visual + interaction budget (needs: npm ci --ignore-scripts)
bin/upgrade-test.sh 1.0.4                      # published release -> this build (also 2.0.0)
bin/plugin-check.sh                            # official Plugin Check on the release package
bin/check-guards.sh                            # protected text (header, LICENSE, readme, .wordpress-org) and no Pro/license/HTTP code
bin/build-zip.sh                               # release zip plus the package allowlist check
composer lint                                  # WordPress coding standards + PHP 7.4 compatibility
composer analyse                               # PHPStan level 5 on the shipping code
npm run lint:js                                # ESLint on the shipping JavaScript
```
Two designs (Modern for new installs, Classic for existing sites) are described in [docs/extending.md](docs/extending.md#designs), which also lists the extension points for add-ons (registries, `AFS_API_VERSION`, filters) are documented in [docs/extending.md](docs/extending.md).

`tests/snapshots/2.0.0` freezes the rendered output of every 2.0.0 entry point (shortcodes, block, REST, deep links, a 1.0.3 theme override, compat on and off). A refactor must keep them green; an intentional change needs a reviewed snapshot update.

### A running site (for browsing, seeding, `bin/smoke-test.sh`)
```bash
docker compose up -d --wait                    # http://localhost:8890
docker compose run --rm wpcli wp core install --url=http://localhost:8890 --title=AFS --admin_user=admin --admin_password=password --admin_email=dev@example.com --skip-email
docker compose run --rm wpcli wp plugin activate ajax-filter-search
docker compose run --rm wpcli wp eval-file wp-content/plugins/ajax-filter-search/bin/seed-test-content.php
bin/smoke-test.sh
```
`wp-env` (`.wp-env.json`) also works where its image build succeeds; `bin/compat-test.sh` still drives a wp-env site.

## Release

1. Bump the version in `ajax-filter-search.php` (header and `AFS_VERSION`) and `Stable tag` / changelog in `readme.txt`; update `Tested up to`.
2. `bin/build-zip.sh` builds `build/ajax-filter-search-<version>.zip` from tracked files and `.distignore`, then `bin/check-package.sh` verifies it (allowlist of top-level entries, no repo-only files such as `.ai/`, `tests/`, `docs/`).
3. Copy the built tree into SVN `trunk/`, `svn add`/`svn rm` as needed, and sync `.wordpress-org/` into SVN `assets/`.
4. `svn cp trunk tags/<version>`, then `svn ci`.
5. Tag the same commit in git: `git tag -a v<version> && git push --tags`.

## License

GPL v2 or later.

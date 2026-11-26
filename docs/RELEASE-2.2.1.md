# Release 2.2.1: checklist

Status: **published** to WordPress.org as SVN r3827748 (trunk, `tags/2.2.1` and the two re-rendered banners in `assets/`) on 2026-11-26; Git tag `v2.2.1`. Follows the steps in `RELEASE-2.2.0.md`, kept as the record and the template for the next release.

## What changes

Discoverability only. No code, behavior, settings or output changes.

- Plugin name (header and `readme.txt` title): "Ajax Filter Search: Filter Posts by Category, Date & Keyword". The slug, text domain, `AFS_` prefixes, menu label and block title stay "Ajax Filter Search".
- Tags: ajax filter, filter posts, live search, category filter, custom post type.
- Short description and plugin description reworded to say what the plugin does.
- Banner tagline updated (`design/banner.html`, re-rendered to `.wordpress-org/banner-*.png`).
- Version 2.2.1, changelog and upgrade notice, `.pot` updated.

## Publish

Same SVN steps as 2.2.0 (trunk, `tags/2.2.1`, and `assets/` for the two banners), then `bin/verify-live-release.sh 2.2.1 2.2.0`. Note that `Name` in the update-check request in that script is still the short name; the endpoint matches on the plugin file, so this is harmless.

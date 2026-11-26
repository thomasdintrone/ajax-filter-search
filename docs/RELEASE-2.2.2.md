# Release 2.2.2: checklist

Status: **prepared, not published**. Follows the steps in `RELEASE-2.2.0.md`; publishing to WordPress.org is a separate, approved step.

## What changes

Fixes a defect in 2.2.1. The new short description was 152 characters; WordPress.org allows 150 and truncated it (warning shown to plugin authors on the listing). The last sentence is now "Shortcode and block included." (146 characters), in `readme.txt`, the plugin header and the `.pot`.

- No code, behavior, settings or output changes.
- `bin/check-guards.sh` now fails if the readme short description or the header Description is over 150 characters. The 2.2.1 check missed this because nothing measured it.
- Version 2.2.2, changelog and upgrade notice.
- Banners and assets are unchanged, so nothing goes to `assets/`.

## Publish

Same SVN steps as 2.2.0 (trunk and `tags/2.2.2`), then `bin/verify-live-release.sh 2.2.2 2.2.1`. Also confirm the red "Short Description is too long" warning is gone from the listing.

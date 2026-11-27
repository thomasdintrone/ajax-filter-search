# Release 2.3.1: checklist

Status: **published** to WordPress.org as SVN r3828499 (trunk, `tags/2.3.1` and the 14 listing images in `assets/`) on 2026-11-26; Git tag `v2.3.1` (commit `4cd6521`). The live check after the roughly 6-hour hold (`bin/verify-live-release.sh 2.3.1 2.3.0`) is still to run, and the 2.3.0 update offer was not yet confirmed when 2.3.1 was committed. Earlier wording follows: prepared, not published. Version 2.3.1 follows the independent audit of the published 2.3.0 (SVN r3828281, tag `v2.3.0`). Codex re-reviewed the remediation and signed off on commit `b927575`; the version bump, changelog, upgrade notice and listing assets are in this release commit. Publishing to WordPress.org is done by the owner (SVN steps in `RELEASE-2.2.0.md`). The Git tag `v2.3.1` was made after SVN was published, on the merged `master` commit.

Package: `build/ajax-filter-search-2.3.1.zip` (from `bin/build-zip.sh`; `bin/check-package.sh` passes).

## What is in 2.3.1
Fixes only; no new settings. Details of each finding, with its test, are in the "Audit remediation" section of `RELEASE-2.3.0.md`. Since that section was written:
- **Phone toolbar in right-to-left text:** the layout switch no longer overlaps the result count (logical spacing); covered by a geometry test in LTR and RTL.
- **Term picker:** every term gets a label no other term has, selection works by slug, the term cache is per taxonomy, and lookup tables have no inherited keys, so terms named `constructor`, `toString` or `__proto__` work.
- **Docs:** `MAINTAINING.md` states the real release state.
- **Listing:** the owner-approved banner and icon (`design/approved-artwork/`, exported by `design/export-approved-artwork.sh`) and ten retaken screenshots.

Unchanged: `LICENSE`, the copyright line, the plugin title, tags and short description. Changed protected text: `readme.txt` (changelog, upgrade notice, stable tag) and the listing images, which carry the `approved-protected` label on the pull request.

Classic and Modern output is byte-identical to 2.2.2 (`AFS_STRICT=1 bin/upgrade-test.sh 2.2.2`, with and without `AFS_TEST_MODERN=1`). The "What's new" card keeps its 2.3.0 id, so nobody who dismissed it sees it again.

## Publish (WordPress.org SVN)
As in `RELEASE-2.2.0.md`: `svn up`; replace `trunk/` with the package; copy `.wordpress-org/*.png` (2 banners, 2 icons, 10 screenshots) to `assets/`; `svn cp trunk tags/2.3.1`; review `svn status`; one commit for trunk, tag and assets. Then tag `v2.3.1`, record the revision here and run `bin/verify-live-release.sh 2.3.1 2.3.0` after the roughly 6-hour hold. Also run the still-open `bin/verify-live-release.sh 2.3.0 2.2.2` before publishing, so 2.3.1 is not stacked on an unconfirmed 2.3.0.

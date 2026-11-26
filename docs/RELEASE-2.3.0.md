# Release 2.3.0: checklist

Status: **published** to WordPress.org as SVN r3828281 (trunk, `tags/2.3.0` and the ten replaced screenshots in `assets/`) on 2026-11-26; Git tag `v2.3.0` (commit `3e2bc45`). The live check after WordPress.org's roughly 6-hour hold (`bin/verify-live-release.sh 2.3.0 2.2.2`) was still to be run when this was written, so the update offer is not yet confirmed. An independent audit of the published build found the issues listed under "Audit remediation" below; they are fixed on the branch `fix/2.3.0-audit-remediation` for a 2.3.1 release (not yet versioned, tagged or published). What follows describes 2.3.0 as it was released.

Package: `build/ajax-filter-search-2.3.0.zip` (built by `bin/build-zip.sh` from the tracked files and `.distignore`; the allowlist check passes).

## What is in 2.3.0

- **Two new designs with their own item markup:** the Editorial Grid (image cards with a category badge, excerpt and date) and the Resource Library (compact rows with an optional square thumbnail). Names are working names. Chosen per feed (`design="editorial|library"`, or the block's Design setting), else by Settings > Appearance.
- **Card options:** featured image, image shape (16:10, 3:2, 4:3, 1:1, 16:9), columns (1 to 4, a maximum), excerpt and its length (10 to 80 words), date, time and category badge. Site-wide defaults under Settings > Cards; a shortcode attribute or block setting overrides them for one feed. All validated against fixed lists; only the validated column count and ratio reach CSS.
- **Theme aware:** the two designs derive borders, surfaces and muted text from the theme's text colour, so a dark theme gives a dark feed. Reduced motion and forced colours are handled.
- **Block:** a Cards panel (only for designs with their own markup), a searchable term picker (stores the same comma-separated slugs as before), and two block patterns (Editorial Grid, Resource Library) in an "Ajax Filter Search" inserter category.
- **Design and card options travel with every request** for the new designs, so the first page and the AJAX results render the same items (a contract test and a browser test cover it). Classic and Modern send nothing extra.
- **Security:** a password-protected post never shows its image or excerpt; drafts, private and future posts stay out; the cache version also changes when a featured image or attachment changes.
- **Refinements from the visual review (the two new designs only):** one compact toolbar with the layout switch beside the tabs as icon buttons; a search placeholder; Reset only when it is the one way to clear filters; one clear action in an empty result; aligned Resource Library rows; a refined missing-image tile; hover and keyboard focus rings; a tighter phone layout (the plugin's controls before the first result drop from 457px to about 270px; the collapsible mobile filter panel is a later milestone).
- **Cards settings:** dependent options hide with their parent and keep their saved values; the time is part of the date; the card folds when the site's design is not one of the new ones; the block shows the current default next to "Use plugin setting".
- **"What's new" card** on the settings screen for existing sites (admin only, dismissible per user, no outside requests, never on a fresh install).
- **Developers:** `afs_register_design` is now read (a design can name its own item template and class; a built-in slug cannot be replaced; template names must be plain file names); `afs_register_patterns` turns the patterns off; `return_template()` takes an optional options argument.
- **Screenshots:** all ten retaken from the demo content (`docs/screenshots/`) in a new order: Editorial Grid, Resource Library, phone, dark theme, filtered, settings, block editor, two independent feeds (an Editorial Grid feed and a Modern feed), empty result, Classic. The capture script waits for each feed to finish loading and checks that the filtered and two-feed shots show what their captions say; the 2.2.x screenshot 2 had been taken while a request was still in flight (the dimmed 2026 results beside "2025").
- **A bug the new screenshots exposed, fixed before release:** on a site whose default design is the Editorial Grid or Resource Library, a feed that names `design="modern"` or `design="classic"` rendered the site design's items after an AJAX update, because the update request did not carry the feed's design. Such a feed now sends it (only in that case, so every other site's markup is unchanged). Tests cover the first load and the update.

## Existing installs

Nothing changes on update. Classic and Modern feeds render exactly as before (the frozen 2.0.0 snapshots and the existing visual baselines pass); settings saved before 2.3.0 read the new card keys as their defaults, with no migration; shortcodes, block attributes, bookmarked `afs_*` links and theme template overrides are unchanged. A site only changes when its owner chooses a new design.

## Things to know

1. The Cards settings are ignored by Classic and Modern.
2. The Resource Library is always a list: the layout switch is hidden and the image-shape option does not apply (thumbnails are square).
3. Images in the new designs are lazy-loaded with an empty `alt` and the image link is hidden from assistive technology, because the title link is the real link.
4. The block patterns need WordPress 5.5 or later (the plugin already requires 6.0).
5. The release is not published until the owner commits to SVN. WordPress.org then holds the update about 6 hours.

## Files that differ from 2.2.2 (the shipping set)

Added (10): `afs-template/editorial.php`, `afs-template/library.php`, `includes/class-afs-card.php`, `includes/class-afs-patterns.php`, `includes/class-afs-announcement.php`, `public/css/afs-discovery.css`, `public/css/afs-editorial.css`, `public/css/afs-library.css`, `admin/images/whatsnew-editorial.webp`, `admin/images/whatsnew-library.webp`.

Modified: `ajax-filter-search.php`, `blocks/feed/block.json`, `blocks/feed/editor.asset.php`, `blocks/feed/editor.js`, `includes/class-afs-cache.php`, `includes/class-afs-design.php`, `includes/class-afs-frontend.php`, `includes/class-afs-renderer.php`, `includes/class-afs-rest.php`, `includes/class-afs-settings.php`, `includes/functions.php`, `languages/ajax-filter-search.pot`, `readme.txt`.

Unchanged: `LICENSE`, the copyright line, the plugin title, tags and short description (146 characters), banners and icons.

Listing assets (`.wordpress-org/`, copied to SVN `assets/`): screenshots 1 to 10 are replaced; banners and icons are unchanged.

## Checks run on this exact build

`AFS_APPROVED_PROTECTED=1 bin/check-guards.sh` locally (the protected-text guard reports the approved version, readme and screenshot changes; in CI the pull request carries the `approved-protected` label from the start).

- PHP coding standards, PHPStan, ESLint.
- PHPUnit (234 tests), including the frozen 2.0.0 snapshots, the security regression suite, the new design, card, settings, block and pattern tests.
- Browser tests (135 pass, 1 skipped): behavior, accessibility (axe, light and dark, 390 and 1200 px, both new designs), HTTP contract, extension points, visual baselines (including a dark-themed page), the onboarding path and the keyboard-only term picker.
- Upgrade from 2.2.2, 2.0.0 and 1.0.4 onto this build (`bin/upgrade-test.sh`): every check passes, and from 2.0.0 the pages and REST output are identical after the upgrade (whitespace between tags ignored; since the remediation `AFS_STRICT=1 bin/upgrade-test.sh 2.2.2` compares raw bytes too, and Classic and Modern output matches 2.2.2 exactly apart from asset version numbers, per-request ids and the additive UI-strings script).
- The official Plugin Check on the release package (`bin/plugin-check.sh`): 0 errors, 0 warnings.

## Publish (WordPress.org SVN)

The same steps as `RELEASE-2.2.0.md`: in the SVN checkout, `svn up`, replace `trunk/` with the package, copy `.wordpress-org/screenshot-*.png` to `assets/`, `svn cp trunk tags/2.3.0`, review `svn status`, and commit trunk, tag and assets in one revision with your own credentials (the password only at the prompt). Then tag Git (`v2.3.0`), record the revision here, and after the roughly 6-hour hold run `bin/verify-live-release.sh 2.3.0 2.2.2` and check the listing (title, tags, the "short description too long" warning must stay absent, the ten screenshots and their captions).


## Audit remediation (for 2.3.1)

Fixed on `fix/2.3.0-audit-remediation`, each with a test that failed before the fix:

1. **Protected content.** Editorial Grid and Resource Library showed the featured image of a password-protected post, and the REST results and the feed route could expose or cache protected excerpts for visitors without the password. Protection is now decided from the post's own password field (never the visitor's cookie), for the image, the excerpt and the core RSS feed; the cache version carries a new epoch so entries cached by 2.3.0 are not reused.
2. **Attachment cache.** Replacing a featured image's file or editing its metadata now invalidates cached results and ETags, as changing the featured image already did.
3. **Chips off for one feed.** A feed with `show_chips="false"` on a site whose default is chips on kept chips after an AJAX update; the client config now says so, so the feed stays as rendered.
4. **Term picker.** Terms that share a name (children of different parents) are shown as `Name (slug)` and stored by slug, so choosing the second one no longer selects the first.
5. **Byte identity.** The Classic and Modern layout switch is printed from the original template again; `AFS_STRICT=1 bin/upgrade-test.sh` proves raw identity against 2.2.2 (Classic and Modern sites).

Known limits: the collapsible mobile filter panel stays deferred; a site-wide cache plugin that ignores `Cache-Control` for logged-out requests can still serve a page cached before a post became protected until that cache is purged.

### Listing artwork for 2.3.1

The banner and icon are replaced by the owner-approved artwork (Paper & Ink banner, "New layouts" copy; outer-corners icon). Exports: `banner-1544x500.png`, `banner-772x250.png`, `icon-256x256.png`, `icon-128x128.png`, made by `design/export-approved-artwork.sh` from the sources in `design/approved-artwork/` (hashes verified). The source banner is 2172x724 (3.0:1) and WordPress.org's shape is 3.088:1, so 21 px of blank paper is trimmed from the bottom edge; nothing else is cropped. At 32 px the icon's corner marks are faint but the letters hold. Banner, icon and screenshots are copied to SVN `assets/` by the owner at release time. The settings screen header keeps its own `admin/images/icon.svg`.

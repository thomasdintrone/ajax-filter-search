# Release 2.2.0: checklist

Status: **published** to WordPress.org as SVN r3809958 (trunk, `tags/2.2.0` and the banner, icons and ten screenshots in `assets/`) on 2026-11-18; Git tag `v2.2.0`. The steps below are kept as the record and as the template for the next release. The previous release is recorded in `RELEASE-2.1.0.md`.

Package: `build/ajax-filter-search-2.2.0.zip` (built by `bin/build-zip.sh` from the tracked files and `.distignore`; the allowlist check passes).

## What is in 2.2.0

- **Modern design** (card-based results, pill tabs, a cleaner filter bar, a segmented layout switch). Chosen per feed (`design="modern|classic"` or the block's Design setting), else by Settings > Appearance, else by the site default.
- **Who gets what:** new installs default to Modern. Sites that already have the plugin keep Classic (the 2.x look) until they choose Modern. A Classic feed's markup and stylesheet are unchanged.
- **Redesigned settings screen:** header, section navigation, cards, segmented Yes/No controls, a visual design picker, copyable example shortcodes, a sticky save bar.
- **Contrast:** text on the accent colour (buttons, the active tab, the current page) is white or near-black, whichever reads better, in both designs. Previously Classic always used dark text, which failed contrast with a dark accent.
- **Fix:** submitting a search no longer fires a second, delayed request (the 400 ms search timer was not cancelled), which could undo a click on a tab or page link made right afterwards.

## Things to know

1. **One edge case for "who gets what":** a site that has the plugin installed but has never saved its settings looks the same as a new install to the plugin. If such a site is deactivated and reactivated, it is treated as new and gets Modern. Sites that ever saved settings, or that were set up on 2.2.0 or later, are never switched.
2. The Modern design's accent on a new install is blue (`#2563eb`); a saved accent always wins.
3. Both designs read the `--afs-*` custom properties; see `docs/extending.md#designs`.

## Files that differ from 2.1.0 (the shipping set)

Modified (12): `admin/js/admin.js`, `ajax-filter-search.php`, `blocks/feed/block.json`, `blocks/feed/editor.asset.php`, `blocks/feed/editor.js`, `includes/class-afs-frontend.php`, `includes/class-afs-renderer.php`, `includes/class-afs-settings.php`, `languages/ajax-filter-search.pot`, `public/css/afs.css`, `public/js/afs.js`, `readme.txt`.

Added (4): `admin/css/settings.css`, `admin/images/icon.svg`, `includes/class-afs-design.php`, `public/css/afs-modern.css`.

Unchanged: `LICENSE`, the copyright line, the plugin title and tags.

Listing assets (`.wordpress-org/`, copied to SVN `assets/`): the **banner** (both sizes) and **icon** (128 and 256) are rebuilt (the filter badge now sits above the card; the icon is exact vector artwork, source `design/icon.svg`, regenerate with `node design/render-assets.mjs`); **screenshots 1 to 9** are retaken in the Modern design and the redesigned settings screen; **screenshot 10** (the Classic design) is new.

## Checks run on this exact build

Run `AFS_APPROVED_PROTECTED=1 bin/check-guards.sh` locally (the protected-text guard reports the approved version, readme and asset changes; in CI the pull request carries the `approved-protected` label).

- PHP coding standards, PHPStan level 5, ESLint.
- PHPUnit on WordPress 6.0/PHP 7.4, 6.5/PHP 8.2 and 8.3, 6.8/PHP 8.1 and 8.2, latest/PHP 8.4, a multisite run and the WordPress beta.
- Browser tests (behavior, accessibility, HTTP contract, extension points, visual) on a block theme and a classic theme, in both designs; the nightly runs Firefox and WebKit.
- Upgrade from 1.0.4 and from 2.0.0 onto this build (an upgraded site stays Classic and renders identically).
- Official Plugin Check on the release package.

## Publish (WordPress.org SVN)

Enter the password only at the `svn` prompt, never in chat. These commands change nothing until the final `svn commit`.

```bash
cd ~/Repos/streetcar-productions/ajax-filter-search-svn
svn status                                   # expect: no output
svn up
svn ls ^/ajax-filter-search/tags/            # expect: ... 2.1.0/ and no 2.2.0/

# 1. Replace trunk with the package, then record additions.
rsync -a --delete --exclude='.svn' ~/Repos/streetcar-productions/ajax-filter-search/build/ajax-filter-search/ trunk/
svn add --force trunk
svn status | grep '^!'                       # expect: no output (nothing deleted)
svn status trunk                             # expect: 12 M and 4 A

# 1b. Listing assets.
cp ~/Repos/streetcar-productions/ajax-filter-search/.wordpress-org/{banner,icon,screenshot}-*.png assets/
svn add --force assets
svn propset svn:mime-type image/png assets/screenshot-10.png
svn status assets                            # expect: M for 2 banners, 2 icons, screenshot-1 to 9; A for screenshot-10

# 2. Tag.
svn cp trunk tags/2.2.0
diff -rq trunk tags/2.2.0 -x .svn            # expect no output

# 3. One commit for trunk, the tag and the assets.
svn commit trunk tags/2.2.0 assets --username longislandfreelancewebdesigner \
  -m "Release 2.2.0: Modern design, redesigned settings screen, contrast and search fixes, new banner, icon and screenshots"
```

After the commit, tag the Git commit (the merge commit of the release pull request) and push the tag, then after WordPress.org's hold of about six hours verify with `bin/verify-live-release.sh 2.2.0 2.1.0` (the script's screenshot line should say 10).

```bash
git checkout master && git pull
git tag -a v2.2.0 -m "Ajax Filter Search 2.2.0"
git push origin v2.2.0
```

## If something is wrong

A bad release is fixed by a new version, not by editing a published tag. Prepare 2.2.1 from `master` and repeat this checklist. If the problem is serious, point `Stable tag` in `trunk/readme.txt` back at 2.1.0 as a stop-gap and commit that alone.

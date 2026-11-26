# 2.3.0 visual review: assessment and proposed refinements

Status update: the owner approved including the recommended refinements and the announcement in 2.3.0. They are implemented on the 2.3.0 refinements branch, with tests (section 3 items 1 to 6, 9 and 10 and the hover and focus rings; section 5; section 6). Item 8 (excerpt ellipsis) was reviewed and needs no change. Done since: the photography (free Unsplash photos, decision 1), the ten listing screenshots retaken in the new order, and the two announcement thumbnails cropped from them. The collapsible mobile panel stays deferred.

Original status: **proposal for approval. Nothing in the plugin, the release package, the SVN copy or the listing has been changed.** 2.3.0 is merged to `master` and staged in SVN, but not published. Every picture referred to below is a mockup or a capture of the current build, in `design/prototypes/refinements/`.

How to read this: each item is marked **screenshot only** (no plugin change), **plugin change** (and whether it touches only the two new designs), or **deferred**. A plugin change needs new tests and new baselines, so it is cheaper to do before the independent QA audit than after.

## 1. What I measured first

| Finding | Evidence |
|---|---|
| The page title and the plugin sit in different columns, and there is a large gap above the plugin | Demo theme (Twenty Twenty-Five) constrains the page title to 645px while the feed is wide; the feed started about 280px down |
| Fixing it needs no plugin CSS | Using the theme's own "Page (No Title)" template plus a heading inside the wide group: title and grid both start at x=50, and the feed starts at y=124. Real capture: `real/framing-editorial-desktop.png` |
| On a 390px phone the plugin's controls take 457px of an 844px screen before the first result | Measured on the demo: tabs 47, filters 274, layout switch 42, result bar 22, plus spacing; first result at y=566 (67% down the screen) |
| Reset, "Clear all" and "Clear all filters" overlap | Real captures show Reset beside the chips' Clear all, and all three in the empty state (`real/framing-two-feeds.png`, `real/framing-empty-state.png`) |
| The Resource Library headlines shift left on rows without an image | Visible in the current screenshot 2 |
| The mobile category tabs clip at the right edge | The fourth tab is cut off in the current mobile capture |
| "Existing layouts unchanged" is verified, but only for part of the upgrade matrix | The repo's upgrade test compares output before and after only when upgrading from 2.0.0. I ran the same comparison from 2.2.2 for a site on Classic and a site on Modern: pages and REST output are identical after upgrading. See section 6 |

## 2. Listing screenshots (all screenshot only)

| Request | Proposal |
|---|---|
| Realistic photography | Needs a sourcing decision (see decision 1). The mockups use clearly labelled stand-ins that show the planned composition, not final images. About 14 subjects cover every screenshot: reading room, community workshop volunteers, harbor at dusk with lanterns, cyclists in a protected lane, team meeting, workshop bench, harbor from above, printed report on a desk, books on a fair table, seed swap, mural, ferry, solar panels. No recognizable faces, logos or readable signs, so no releases are needed. |
| Titles aligned with the content width, less whitespace | Demo page configuration only: the "Page (No Title)" template and a heading inside the wide group (verified above). No plugin CSS. |
| Frame the plugin, not the theme | Crop each shot from the heading down, dropping the site header and the footer. The demo menu is also trimmed so the page list is not a distraction. |
| Multiple feeds clearly independent | Two feeds with their own headings ("Latest stories", "Press releases"), different designs and different active states at the same time (Events tab with a chip in feed 1, a search with a chip in feed 2). Real capture: `real/framing-two-feeds.png`. Only the first feed on a page writes to the address bar; this shot shows each feed's own state. |
| Helpful empty state | Real capture: `real/framing-empty-state.png`. Improved copy and one clear action are proposed in section 3 (item 10). |
| Order and captions | Section 7 lists the new order and captions. The Modern design, the default for new installs, has no slot in the requested order (decision 3). |

## 3. Frontend refinements (the two new designs only)

Reference images: `editorial-idle.png`, `editorial-active.png`, `library.png`. Classic and Modern are not touched.

| # | Refinement | Type | Notes |
|---|---|---|---|
| 1 | **One toolbar:** tabs and the layout switch on the first row; search, month, year and sort on the second; chips on the left and the count on the right of a slim result bar | plugin change, CSS and a small markup move | Today the layout switch and the count each take a row |
| 2 | **Search placeholder** from the post type's own label ("Search Posts", "Search Resources"), label kept for assistive technology | plugin change, PHP | WordPress translates these labels, so no new translatable string for post types. Placeholder text must meet 4.5:1 contrast (my first mockup grey was 3.8:1 and failed; the reference uses 5.6:1) |
| 3 | **Icon layout switch:** two icon buttons, accessible names "Grid view" and "List view", `aria-pressed`, filled selected state, inline SVG | plugin change, markup | No icon library, no new dependency |
| 4 | **Reset de-emphasised:** hidden when no filter is active and when chips are on (chips carry the single "Clear all") | plugin change, CSS plus a few lines of JavaScript | The JavaScript only toggles a class on the feed root after each update, for these designs only. The server renders the same class on first load; a contract test keeps them equal |
| 5 | **Resource Library alignment:** the thumbnail column is always reserved; a row with no image gets a small monogram tile | plugin change, markup and CSS | Removes the `afs-row--nothumb` variant. Tests and baselines referring to it change |
| 6 | **Better missing-image tile in the Editorial Grid:** soft tinted gradient, faint dot pattern, a monogram badge | plugin change, CSS | Same tile family as item 5 |
| 7 | **Hover and focus:** hover raises the border and adds a soft shadow (motion-safe only); the keyboard focus ring is a clear two-colour outline around the card; optional "whole card clickable" through the title link | hover and focus: plugin change, CSS. Whole-card link: deferred | The title stays the only link, so assistive technology is unchanged |
| 8 | **Excerpts:** keep the three-line clamp in cards and one line in rows. The PHP word limit and the CSS clamp can both add an ellipsis, so a row can end "find m…". Let the CSS ellipsis do the work in rows | plugin change, small | |
| 9 | **Compact mobile, CSS only (interim):** wrap the category pills instead of clipping them, put month, year and sort in one compact row, tighten spacing | plugin change, CSS | Expected to save about 100 to 150px; to be measured. Not a replacement for item 11 |
| 10 | **Empty state:** "No results for “zebra submarine”", one primary "Clear all filters" button, no duplicate Clear all next to it | plugin change, PHP and CSS (two new strings) | |
| 11 | **Collapsible mobile filter panel** (below) | **deferred** | See section 4 |

## 4. Mobile experience

Reference: `mobile.png` (today's real capture beside the proposal).

**Proposal:** search stays visible and prominent; one row holds a **Filters** button (with the count of active filters) and the **Sort** control; the active filters stay visible as chips; the count and the icon layout switch share one line. Category, month and year move into a disclosure panel: a `button` with `aria-expanded` and `aria-controls`, not a modal drawer, so there is no focus trap to get wrong. The category tabs become wrapped pills inside the panel, so nothing is clipped. With JavaScript off the panel is simply open.

**Result in the mockup:** the first result moves from 566px to 313px, about 45% earlier, and every function is still reachable.

**Cost:** new markup for these designs (a wrapper and a toggle button), JavaScript for the disclosure and the active count, a new e2e accessibility pass (keyboard, focus return, screen reader names), and new baselines. It overlaps the roadmap's 2.4.0 "mobile filter drawer" and should be built once, with that work.

**Recommendation:** ship item 9 (CSS only) in 2.3.0, and build the panel as the first piece of the next milestone. Classic and Modern are untouched in both cases.

## 5. Administration: the Cards settings

Reference: `cards-settings.html` (interactive; open it in a browser) and the three `cards-settings-*.png` states.

| Request | Proposal |
|---|---|
| Image shape only when images are on | Hide "Image shape" when Featured image is No. It stays described as Editorial Grid only |
| Excerpt length only with excerpts | Hide it when Excerpt is No |
| Time consistent with date | Rename to "Include the time" and show it only when Publication date is Yes. Templates then render the time only with the date, so "time without date" cannot happen |
| Only relevant for supporting designs | **Do not hide the Cards card based on the site design.** A site on Modern can still have a feed with `design="editorial"` that uses these defaults. Instead the card collapses (a `details` element) with a note "Used by feeds that choose Editorial Grid or Resource Library" and opens by default when the site design is Editorial or Library |
| Explain defaults and overrides | A short callout at the top of the card, and in the block "Use plugin setting (Show)" so the block shows the current default |
| Preserve saved values | Hidden rows are hidden, never disabled or removed, so their values still submit and apply again when the option is turned back on. A note says so |
| Native controls | Unchanged: the design picker, the Yes/No segments and WordPress components in the block |

Type: plugin change (admin JavaScript, labels, one template rule, block editor data). The larger visual builder remains a separate milestone.

## 6. "What's new" announcement for existing users

Reference: `whats-new-desktop.png` and `whats-new-mobile.png`.

**Design:** a soft, light card in the plugin's own admin style (blue accent, rounded, a gentle shadow), a "New in 2.3.0" pill, your headline and supporting copy, a green reassurance panel, two real screenshots on the right (stacked on a phone), and three actions. Dismiss is a clear ✕ with an accessible name.

**Copy:**

- Headline: Meet the next generation of Ajax Filter Search.
- Supporting: Discover a more beautiful way to showcase your content. New editorial layouts, compact resource libraries, featured images, customizable excerpts and intuitive design controls.
- Reassurance: **Your existing feeds are unchanged.** Layouts, settings, shortcodes and blocks look and work exactly as before. Nothing changes unless you choose a new design.
- Actions: **Explore the new designs** (scrolls to the design picker), **See what's new** (the changelog on the plugin's WordPress.org page, in a new tab), **Maybe later**.

**The reassurance is backed by tests:** the frozen 2.0.0 snapshots; the existing visual baselines; upgrade tests from 2.0.0 and from 2.2.2 (Classic and Modern) with identical pages and REST output; a block rendered with no new attributes is identical to before. The copy says "unchanged", not "guaranteed".

**"Preview an experience":** not supported without building a preview, so it is left out. The screenshots in the card are the preview. A real preview (the block renderer in a modal) is a later idea.

**Placement:** only on Settings > Ajax Filter Search, above the sections. Not on the dashboard, not on other admin screens, not as a global `admin_notices` banner. A small "What's new" link beside the version badge reopens it.

**Who sees it:** users with the plugin's own capability (`manage_options`) only. Shown only on a site that already had the plugin before 2.3.0 (fresh installs set a marker at activation and never see it, since they already get the new look by default). It stops showing once the site's default design is Editorial Grid or Resource Library.

**Dismissal:** both ✕ and "Maybe later" dismiss permanently, per user (user meta keyed by version, so a future announcement can appear once), through a nonce-checked request with a no-JavaScript fallback. After dismissing, focus moves to the page heading. No cookies, no local storage.

**Accessible and responsive:** a labelled region (not an alert), no autofocus, 44px targets, decorative images with empty alt, reduced motion respected, stacks below 782px, tested with axe.

**No tracking:** no external requests of any kind; the two images ship inside the plugin as compressed WebP (about 60 to 80 KB each, only loaded when the card shows). The only external link is the changelog, opened on request.

**Not an upsell:** no mention of Pro, pricing or licensing.

Type: plugin change, admin only (a new class, two images, a stylesheet block, tests). It does not touch any frontend output.

## 7. Listing: proposed order and captions

| New # | Image | Caption |
|---|---|---|
| 1 | Editorial Grid with photography | The Editorial Grid design: photo cards with category badges, excerpts, search, sorting and filters. |
| 2 | Resource Library | The Resource Library design: compact, searchable rows with optional thumbnails. |
| 3 | Mobile | The Editorial Grid on a phone. (If the panel ships later: "On a phone, search comes first and filters are one tap away.") |
| 4 | Dark theme | The new designs take their colors from your theme, so they work on dark themes. |
| 5 | Filtered results | Active filter chips and a sort order, kept in the address bar so the view can be shared. |
| 6 | Settings | The settings screen: choose a design and what each card shows. |
| 7 | Block editor | The block in the editor, with the Cards controls and the searchable term picker. |
| 8 | Two independent feeds | Two feeds on one page, each with its own design and filters. |
| 9 | Empty state | An empty result, with one clear way to reset the filters. |
| 10 | Classic | The Classic design, the look of earlier versions. |

Mapping from the current listing (old number to new): 1 to 1, 2 to 2, 4 to 3, 5 to 4, 3 to 5, 8 to 6, 9 to 7, 7 to 8, 10 to 10. The empty state (new 9) is a new capture. The current Modern screenshot (old 6) has no slot (decision 3). `capture.mjs` and the readme list change together; the capture script already waits for each feed to finish loading.

## 8. What goes where

| Group | Items |
|---|---|
| **Screenshot only** (no plugin change, no re-test of code) | Photography; demo page template and heading; crops; the two-feed composition; empty-state capture; new order and captions |
| **Recommended for 2.3.0** (new designs and new settings only; small; before QA) | Section 3 items 1 to 6, 8, 9, 10 and the hover and focus part of 7; section 5 (progressive disclosure); section 6 (announcement, admin only, can also follow as 2.3.1) |
| **Deferred** | Collapsible mobile filter panel and drawer (with the roadmap's 2.4.0 work); whole-card clickable; category-tinted fallback tones and a site-wide default image; a real preview in the announcement; the larger visual builder |

Rough effort for the recommended plugin changes: about 4 to 6 working days including tests, new baselines, retaken screenshots and a fresh upgrade and Plugin Check run. The announcement is about a third of that. The panel is about a week on its own.

## 9. Compatibility and QA

- No new dependencies: the icons are inline SVG; the announcement uses two shipped images.
- Classic and Modern output stays byte-identical (frozen 2.0.0 snapshots, visual baselines, and the 2.2.2 upgrade comparison above).
- Shortcodes, saved settings, block attributes, template overrides, URL behaviour and the supported WordPress and PHP versions are not changed by any item above. Items 2 to 6 add or change markup in the two new designs only; their template overrides (`editorial.php`, `library.php`) keep working, but a theme that copied them would not pick up the new icon and tile markup, which the release notes should say.
- Initial rendering and AJAX updates: the toolbar is in the form, which AJAX does not replace; only the results fragment is replaced. The new active-state class is the one place both paths must agree, so it gets a contract test like the existing first-page-equals-REST test.
- Tests to change or add: the Library "no thumbnail" assertions, six visual baselines, the layout-switch assertions (now icon buttons with the same `data-afs-layout` and `aria-pressed`), a placeholder and label test, an axe pass for the new states, settings disclosure tests, announcement tests (visibility, capability, nonce, persistence, fresh install, no external requests).
- I also propose making the 2.2.2 comparison a permanent part of `bin/upgrade-test.sh` (one condition), so the reassurance stays verified.

## Decisions needed

1. **Photography source:** (a) licensed stock with a permissive license (for example Unsplash or Pexels; per-photo credit recorded in `docs/screenshots/CREDITS.md`, files only in the demo and never in the plugin package), (b) photos you supply, or (c) AI-generated images (not photography; would need to be disclosed). I will not download anything until you choose; for (a) I would list each file, its source and its size first.
2. **Which refinements go into 2.3.0:** my recommendation is section 8's middle row, with the announcement included. Or: screenshots only, and everything else after 2.3.0.
3. **Modern design in the listing:** the requested order has no Modern shot. Suggest the two-feed screenshot uses an Editorial Grid feed and a Modern feed, or add an eleventh screenshot.
4. **Search label:** visually hidden with a placeholder (compact, my proposal) or a small visible label kept above the field.
5. **Announcement:** include in 2.3.0, or ship as 2.3.1 so the release does not wait on it.

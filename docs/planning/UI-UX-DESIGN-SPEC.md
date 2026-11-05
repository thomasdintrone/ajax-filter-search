# Frontend UI/UX design specification (Phase 0 direction)

Direction for review. Static visual prototype: `docs/planning/prototypes/presets.html` (screenshot `presets.png`). Not production code.

## Goals
Make results look designed, not generic; stay light, accessible and theme-friendly; keep existing sites unchanged until they opt in; avoid a Bootstrap look, heavy assets and CSS leakage.

## Design system
- **Scope:** all CSS under `.afs`; presets under `.afs[data-preset="editorial|directory|portfolio|library|classic"]`. Nothing styles bare elements outside `.afs`.
- **Tokens (extend today's `--afs-accent/-text/-muted/-border/-surface/-radius/-gap`):** color roles (accent, accent-contrast, surface, surface-raised, border, muted, danger), type (inherit theme `font-family`; size scale via `em`), spacing scale (4/8/12/16/24/32), radius, elevation (none/sm), focus ring (2px accent, 2px offset, never removed), image ratio (`--afs-ratio`), card gap, columns. Tokens settable from the builder and by themes.
- **Theme inheritance:** typography, link color and body color inherit; accent defaults to the setting (default `#f1b45c` today) and gets an automatic accessible contrast color; dark themes work by inheriting colors; no fixed backgrounds except subtle surfaces using `color-mix` fallbacks.
- **Motion:** 150 ms opacity or transform only, disabled under `prefers-reduced-motion`; skeleton shimmer off in reduced motion.

## Components
Search field (with clear button), select, **chips** (selected filters, removable, with "clear all"), checkbox and radio groups (counts when available), segmented layout switch, result **card** (image, category pill, title, meta, excerpt), **row** (compact), **tile** (image-first), pagination (numbered) and **load more**, result count and sort, **skeleton**, **empty state**, **error state**, **mobile filter drawer**.
- **Loading:** keep today's non-destructive dim for fast responses; skeleton after 300 ms; focus stays where it was.
- **Empty state:** says what was searched, offers "Clear filters", suggests removing the most restrictive chip, and never shows a blank panel.
- **Error state:** concise message with retry; no raw errors.
- **Mobile drawer:** below 640 px the filter panel collapses into a "Filters (n)" button opening a dialog (focus trap, Escape closes, focus returns, active filter count, "Show N results" button).

## Presets (opt-in; Classic remains the default for existing installs)
1. **Editorial (blog/magazine):** pill tabs, 3-column image cards (16:10), category pill, date and reading metadata.
2. **Directory:** left filter panel with counts and chips, sort control, information-rich rows with thumbnails.
3. **Portfolio:** square or 4:3 tiles, minimal metadata on hover or beneath, understated chips.
4. **Resource Library:** dense table-like rows (title, type, date, link), column-style sort, no images.
5. **Classic:** exactly today's markup and look (compatibility).
Default for brand-new installs: to be decided by the owner (suggested Editorial).

## Accessibility requirements (WCAG 2.2 AA target; do not claim compliance without manual testing)
Labeled controls; visible focus not obscured; target size at least 24 px (prefer 40 px); color contrast 4.5:1 text and 3:1 UI; no information by color alone (chips carry text and "remove" labels); announce result counts and filter changes via the live region (polite, debounced); keyboard operation for drawer, chips and pagination; no drag-only interactions (builder reorder via buttons); works at 200% zoom and 320 px width; respects reduced motion; unique landmark names per feed.

## Content and media
Images via `wp_get_attachment_image` with `sizes`, lazy loading and a fixed aspect ratio to avoid layout shift; fallback tile when no featured image; excerpts trimmed server-side; i18n of every string; RTL-safe logical properties.

## Performance budgets
Core CSS plus one preset: at most 12 KB gzip total (today 1.8 KB for everything); core JS at most 8 KB gzip (today 3.0 KB); drawer and load-more modules lazy-loaded; zero external requests; no layout shift from images.

## Acceptance (to be reviewed visually)
Each preset reviewed on desktop, tablet, 390 px phone, in light and dark themes, and on three block themes and one classic theme; axe clean; screenshots become the WordPress.org images (accurate, no mockups).

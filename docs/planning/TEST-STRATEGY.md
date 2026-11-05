# Test strategy (Phase 0)

## Where we are (2026-11-04, verified)
| Layer | Exists today | Result |
|---|---|---|
| Static analysis | `phpcs` (WordPress + PHPCompatibilityWP 7.4+), WordPress Plugin Check | clean / no errors |
| HTTP-level suites | `bin/smoke-test.sh` (14), `bin/compat-test.sh` (37: 1.x shims, legacy ajax, injection attempts, array/huge/garbage input, theme template override) against wp-env | all pass |
| Accessibility | axe-core WCAG 2.2 AA scans driven by a throw-away Puppeteer script (not in repo) | clean on 5 pages at 1200px and 390px (earlier run) |
| Matrix | PHP 7.4 / WP 6.0 and PHP 8.4 / WP 7.1.4 (suites); earlier PHP 5.6 / WP 4.6.1 for the 1.0.4 patch | pass |
| Benchmarks | in-process and HTTP timings at 1k/10k/50k posts (`docs/planning/benchmark/`) | recorded |
| Package integrity | manual comparison to the published zip | byte-identical |
**Gaps:** no PHPUnit or JavaScript unit tests, no CI, no golden-file HTML tests, no end-to-end browser tests in the repo, no visual regression, no multisite, only Chrome (headless) tested, no page-builder or popular-theme tests, no manual screen-reader testing, no automated check that the package excludes `.ai/`.

## Target pyramid
1. **Unit (PHPUnit, no WordPress where possible):** state normalizers, filter definitions, query-arg builder, URL serializer and legacy-parameter mapper, experience schema validator. Property-style tests for limits (lengths, counts, ranges).
2. **Integration (WP test suite via wp-env tests container):** REST contract (params, types, error codes, response schema), published-only invariant for every route and the legacy action (draft, pending, future, private, password, trashed), shortcode and block output, settings sanitization, uninstall, multisite smoke.
3. **Golden-file tests:** save the exact HTML for a fixed fixture set (legacy shortcode forms, template override, multiple feeds) and fail on any unexpected diff; the guard for "existing sites look the same" during the refactor.
4. **JavaScript:** Node test runner (no framework required) for the state module (URL read/write, debounce, abort handling); keep the shipped JS dependency-free.
5. **End-to-end (Playwright on wp-env):** filter, paginate, back button, deep link without JS, two feeds, block insertion and editing, builder flows (later), drawer on mobile viewports, keyboard-only paths.
6. **Accessibility:** axe in the E2E run on every preset; a manual checklist (screen reader with VoiceOver and NVDA, 200% zoom, keyboard-only, reduced motion) before any accessibility claim.
7. **Visual regression:** screenshots per preset on 3 themes (Twenty Twenty-Five, one classic theme, one popular third-party theme) at 3 widths; review diffs, not auto-approve.
8. **Performance:** the existing benchmark scripts become a repeatable job with budgets (PERFORMANCE-BASELINE.md); run on release candidates and when search or index code changes.
9. **Security regression suite (permanent gate):** the 1.0.3 vectors (shortcode injection through every field, attribute smuggling attempts, unpublished exposure), XSS payloads in every input and output context, SQL-style strings, null bytes, oversized and array-typed input, `base_url` abuse, REST parameter fuzzing, CORS/method checks, nonce and capability tests for every admin write (experiences, import), and "published-only after `afs_query_args`".
10. **Compatibility matrix:** PHP 7.4 / 8.0 / 8.2 / 8.4; WordPress 6.0, a mid release, latest, and latest beta; multisite; block themes and classic themes; Firefox, Safari, Chrome, Edge, iOS Safari, Android Chrome; with and without persistent object cache; page builders only after explicit testing (Elementor, Bricks) and only claimed if tested.
11. **Packaging:** `bin/check-package.sh` (allowlist, forbidden patterns, `git archive` export-ignore) in CI; a job that builds the zip and installs it on a clean site, activates, runs the smoke suite and checks the PHP log is empty.
12. **Upgrade tests:** install the previous published release, seed data, update via the admin UI path, verify settings, shims and security, then PHP log (script exists: the prepared live-update verification).

## CI design (GitHub Actions; not yet created)
Jobs: lint (phpcs), unit, integration (matrix PHP x WP), e2e (Chromium), package (build, check, install), benchmark (manual or nightly). Required status checks on `master`. Artifacts: built zip, screenshots, benchmark JSON. No secrets needed; WordPress.org deployment stays manual and approval-gated.

## Release gates (all must pass)
Security suite, golden-file tests unchanged unless intentionally updated, axe clean, compatibility matrix subset for the release, package check, upgrade test from the latest published release, PHP log empty, benchmarks within budget, human visual review of presets.

## Test data
Seed script (exists), plus fixtures: unpublished statuses, password-protected, hierarchical terms, non-Latin slugs, apostrophes and quotes in titles, very long titles, no-featured-image, large sets (1k/10k/50k generator exists).

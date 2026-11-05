# CLAUDE_REPORT.md: Ajax Filter Search

Claude Code owns this report. Append dated `FROM CLAUDE:` entries; retain history.

For every checkpoint include: branch and HEAD SHA, scope implemented, files changed, tests actually run (including failures/skips), security and compatibility checks, limitations, blockers, any unpushed local work, and next proposed action. Never claim a release is approved from a development checkpoint.

## Current status

FROM CHATGPT (2026-11-01): Phase 0 competitor and repository audit requested but not yet reported. No next-generation implementation is authorized. See `.ai/PROJECT_CONTEXT.md` for the detailed plan.

## Claude reports

Append new reports below.

### FROM CLAUDE: Phase 0 report (2026-11-04)

**Checkpoint:** branch `docs/afs-next-generation-plan`, planning documents at commit `d61f719` (on top of `85e8346`). Phase 0 only; no implementation, no merge, no release. Documentation commits are pushed to this branch. **One non-documentation commit is local only** (see "Unpushed local work").

#### Scope completed
Audited the 2.0.0 implementation, researched the named competitors with primary sources where reachable, built a feature-gap matrix, proposed UI/UX direction and the builder architecture, recommended the Free/Pro split, evaluated performance, security and compatibility, drafted milestones, documented licensing/provenance facts, and made `.ai/` exclusion from release packages testable.

#### Documents created (all in `docs/planning/`, excluded from release zips by `.distignore`)
`IMPLEMENTATION-AUDIT.md`, `PERFORMANCE-BASELINE.md` (+ `benchmark/` scripts), `COMPETITIVE-ANALYSIS.md`, `FEATURE-GAP-MATRIX.md`, `PRODUCT-ROADMAP.md`, `UI-UX-DESIGN-SPEC.md` (+ static `prototypes/presets.html` and `.png`), `TECHNICAL-ARCHITECTURE.md`, `FREE-VS-PRO.md`, `TEST-STRATEGY.md`, `RELEASE-PLAN.md`, `LICENSING-PROVENANCE.md`. This repo is public, so these are public; they contain no secrets, personal data, pricing decisions or exploit details (scanned). Competitor prices are the vendors' public prices.

#### Key findings
1. **Do not rebuild what exists.** Verified present: block, shortcode, multiple feeds, server-rendered results, URL state, no-JS fallback, template override, CSS tokens, compatibility layer, published-only queries, input caps, clean lint and Plugin Check, 18-file package, assets about 5 KB gzip.
2. **Real gaps vs. the field:** multiple taxonomy filters, user-visible sort, selected chips, load more, mobile drawer, image cards, custom fields, counts, a visual builder. The state model is single-term and the renderer is one 566-line class, so these need a filter registry and state v2 first.
3. **Config travels from the client** (`data-afs-config`) and is re-validated each request: safe today, but saved experiences should be resolved server-side by ID.
4. **Performance:** taxonomy, date and pagination scale well to 50k posts (a category request is under 15 ms PHP at 50k). **Keyword search is the cliff** (53 ms at 10k, 245 ms DB time at 50k). Dynamic counts would need an index (not built, not measured).
5. **Market (confirmed from WordPress.org API and vendor pages):** free filter plugins with 40k to 70k installs exist (Ajax Search Lite, Filter Everything, Search & Filter, WooCommerce filters); AFS has about 60 installs. Paid tiers cluster at $39 to $99 per site per year; competitors paywall integrations, counts, SEO URLs and scale, not basic filtering.
6. **Packaging gap found and reproduced:** `.ai/` would ship (`.distignore` lacks it; no `.gitattributes`); a zip built from this branch would include it.
7. **Licensing facts (no conclusion drawn):** `Copyright: Drive Media, Inc.` in every header since 1.0.0; `LICENSE` is unmodified GPLv3 template text; readme says GPLv2 or later; header said `GPL` until 2.0.0; 1.0.0 Plugin URI was `curran-connors.com`; 1.x shipped a Bootstrap-style stylesheet with no attribution header and class names from investor-relations/SEC-filing design; 2.0 work is AI-assisted. Full list and open questions in `LICENSING-PROVENANCE.md`.

#### Recommendations
- Milestones M0 to M8 in `PRODUCT-ROADMAP.md`; **insert a foundation milestone (CI, golden-file tests, renderer split, single published-only choke point) before the visual redesign**; presets first as the first visible release; builder after the registry; index only after a benchmark gate; Pro only after the extension API exists, rights are resolved and the owner approves.
- Free keeps everything it has plus presets, chips, multi-taxonomy filters, sort, load more, drawer, basic builder; Pro gets multiple post types, custom fields, AND/OR groups, dynamic counts, index tools, advanced cards, analytics, integrations (`FREE-VS-PRO.md`).

#### Technical risks
State/URL redesign breaking bookmarks; refactor regressions in published-only and limits; builder scope creep; build tooling in a lean plugin; index complexity; CSS leakage from presets; Pro coupling; unresolved copyright and license before any paid distribution. Mitigations are in `TECHNICAL-ARCHITECTURE.md`.

#### Testing performed (2026-11-04, PHP 8.4.27, WordPress 7.1.4, MariaDB 12.3.3, wp-env)
| Test | Result |
|---|---|
| `bin/smoke-test.sh` | 14 of 14 pass |
| `bin/compat-test.sh` (shims, legacy ajax, injection attempts, hostile input, template override) | 37 of 37 pass |
| `phpcs` on tracked PHP | clean |
| WordPress Plugin Check (shipping files) | no errors |
| Benchmarks at 1k, 10k, 50k posts (also 100) | recorded; see `PERFORMANCE-BASELINE.md` |
| Package check: production zip vs published 2.0.0 | 18 files byte-identical |
| Package check: negative test (`.ai/` injected into a zip) | check fails as intended |
| Sensitive-content scan of everything pushed | clean |
Not run: Firefox/Safari, multisite, page builders, manual screen reader, competitor hands-on testing, automatic-update verification from WordPress.org (the update was still in WordPress.org's 6-hour hold when last checked; the prepared verification script has not been run). Competitor "R/?" cells in the matrix are unverified.

#### Unpushed local work (needs Tom's approval; not documentation)
Local branch `chore/package-exclude-ai` (commit `b08b8e6`): `.distignore` adds `.ai`; new `.gitattributes` (export-ignore for repo-only paths); new `bin/check-package.sh` (allowlist and forbidden-pattern check plus `git archive` check); `bin/build-zip.sh` runs it. Tested as above. Until it is approved and merged, **do not build a release from any branch containing `.ai/`.** Also local and untracked (never to be pushed): `docs-private/` notes from earlier sessions. Also, `origin` for this repo was switched from HTTPS to SSH (`git@github.com:thomasdintrone/ajax-filter-search.git`) per Tom's standing rule.

#### Unresolved questions for Tom
1. Rights: who owned the 1.0.0 code, what is Drive Media's role, what is the `curran-connors.com` relationship, did the 1.x layout come from another project, and which license statement is intended (see `LICENSING-PROVENANCE.md`)? **Blocks any paid distribution.**
2. Approve merging `chore/package-exclude-ai` (packaging isolation)?
3. Default preset for new installs (suggest Editorial)?
4. Static counts free and dynamic counts Pro, and multi-taxonomy filters in Free (recommended)?
5. Pursue a Pro edition now, or gate on adoption evidence (about 60 installs today)?
6. Competitor "?" cells: authorize hands-on trials (some vendors offer trials or refunds; FacetWP offers no refunds) before product decisions rely on them?

#### Proposed Phase 1 scope (for approval; none started)
**M0 plus the test harness part of M1, about 2 to 3 weeks:** merge the packaging fix; GitHub Actions CI (phpcs, Plugin Check, smoke and compat suites, package check, PHP 7.4 and 8.4); PHPUnit scaffolding; golden-file HTML tests for the current output (legacy shortcodes, template override, multiple feeds); a permanent security regression suite; the small hardening items (search length cap, multisite uninstall, optional `index.php` guards). **No production behavior change and no new features.** Then re-review before the refactor (M1) and the first visible release (M2 presets).

**Next action:** stop and wait for ChatGPT's "Check AFS" review. Nothing has been merged, released, tagged, or published to WordPress.org.

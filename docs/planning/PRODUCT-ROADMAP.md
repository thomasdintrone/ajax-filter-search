# Product roadmap proposal (Phase 0 output)

Version labels are planning labels, not commitments. Complexity: **S** (days), **M** (1 to 2 weeks), **L** (3 to 5 weeks), **XL** (6+ weeks) of focused engineering, rough and to be re-estimated at each phase gate. Every milestone ships behind opt-in and needs acceptance review before merge or release.

## Adjustments to the proposed order (from the audit)
1. **Add a foundation milestone first.** The audit shows a monolithic renderer, no unit tests, no CI and a single-term state model. Refactoring those safely requires tests first.
2. **Move the filter registry and state model ahead of the visual redesign's multi-filter UI**, because chips, checkboxes and multi-taxonomy filters depend on them. Presets (CSS and templates) can still start early on the existing markup.
3. **Builder after the registry and Experience model**, since it edits those objects.
4. **Index only after the benchmark gate** (search is the measured cliff, not taxonomy).

## Milestones
| ID | Candidate | Scope | Complexity | Depends on | Key acceptance criteria |
|---|---|---|---|---|---|
| M0 | 2.0.x | Backlog from earlier planning: search length cap, multisite uninstall, optional `index.php`, browser and theme test matrix, listing copy; **GitHub Actions CI** (phpcs, Plugin Check, smoke and compat suites, package check); PHPUnit scaffolding | M | none | CI green on PHP 7.4 and 8.4; `bin/check-package.sh` in CI; no behavior change |
| M1 | 2.0.x/internal | **Foundation refactor:** split renderer into state, query, controls, results classes; single published-only choke point; security regression suite; no visible change | L | M0 | All existing tests pass unchanged; new unit tests for normalizers/query; zero diff in HTML for the legacy fixtures (golden-file tests) |
| M2 | 2.1.0 | **Presets and design tokens** on current markup: Classic, Editorial, Directory-lite, Library; cards with featured images; skeleton, empty and error states; opt-in per feed | L | M1 (golden tests) | Existing sites unchanged by default; axe clean; CSS budget met; visual review on 3 themes |
| M3 | 2.2.0 | **Filter registry and state v2:** taxonomy filter (checkbox/radio/select/tabs, hierarchical, multiple taxonomies), sort control, chips, per-filter and global reset, load more, static counts, mobile drawer, array URL state with legacy param mapping | XL | M1 | AND/OR semantics documented and tested; legacy URLs still work; keyboard and screen-reader behavior verified; 1.0.3 vectors still blocked |
| M4 | 2.3.0 | **Experiences and the visual builder** (Content, Filters, Layout, Design, Publish), preview endpoint, wizard recipes, block selects an experience, import/export | XL | M2, M3 | Builder keyboard-operable; preview equals frontend render; migration creates a default experience without changing output; no overwrite of existing configs |
| M5 | 2.4.0/internal | **Performance and extension foundation:** caching headers, `no_found_rows` load more, optional search index (if gate passes), documented extension API with API version, contract tests | L | M3 | Benchmarks at 10k/50k recorded and meet agreed budgets; API documented; contract tests in CI |
| M6 | Pro 0.1 | **Pro add-on MVP** (separate plugin): multiple post types, ACF/meta filters, AND/OR groups | XL | M5, rights resolved, owner approval | Add-on uses only public hooks; Free unaffected when add-on absent |
| M7 | Pro 0.2+ | Dynamic counts (index), advanced card builder, analytics, integrations (demand-led) | XL each | M6 | Per-feature acceptance; privacy review for analytics |
| M8 | continuous | Listing and documentation refresh with accurate screenshots and demos; live demo site | M | M2+ | Screenshots from real UI only; claims verified |

## First implementation milestone recommendation
Start with **M0 plus M1** (about 4 to 6 weeks): it carries no user-visible risk, unlocks everything else, and addresses the audit's top findings (tests, CI, monolith, single choke point). The first user-visible release should be **M2 presets** because it delivers the most visible quality gain with the least behavioral risk.

## Phase 1 scope proposed for approval
M0 (CI, package check, backlog hardening) and the *test harness* part of M1 (golden-file tests of current output, PHPUnit setup, security regression suite). No refactor of production code and no new features until those tests exist and Tom approves.

## Cross-cutting rules
Never publish from a feature branch; every milestone ends with the readiness checklist in RELEASE-PLAN.md; keep 1.0.4 protections; no WooCommerce-first work until demand is shown.

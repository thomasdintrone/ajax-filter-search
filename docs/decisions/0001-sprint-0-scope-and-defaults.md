# 0001: Sprint 0 scope and defaults

Date: 2026-11-15. Status: accepted by "Approve plan" (the individual decisions below are my reading of that approval; correct any that are wrong).

Context: `docs/planning/SPRINT-0-PLAN.md` listed eight decisions. The owner replied "Approve plan" without answering them one by one.

| # | Decision | Applied default |
|---|---|---|
| 1 | Approve the plan and spike decision rules | Yes |
| 2 | Default design for upgraded vs new installs | Upgraded sites keep **Classic** (today's look); the new design applies to new installs only. Not implemented in Sprint 0 |
| 3 | Signed config for the REST route | Accepted as a spike 2 deliverable; not implemented yet |
| 4 | Packaging fix | Cherry-picked into the Sprint 0 branch (`feat/sprint-0-foundation`); **not merged to master, not pushed** |
| 5 | Base branch | `master` (docs and `.ai` stay on `docs/afs-next-generation-plan`) |
| 6 | Design names | Undecided; no design work in Sprint 0 |
| 7 | Security-only 2.0.x line below a new floor | Deferred until the floor is decided |
| 8 | Raise the minimum WordPress to 6.5 | **Deferred until spike 1 reports.** No change to header, readme or `LICENSE` |

Hard rules in force (enforced by `bin/check-guards.sh` in CI): nothing that shipped free in 2.0.0 is removed or gated; shortcodes, `afs_*` URL parameters, hooks and theme template overrides keep working; no Pro code, license checks or external requests; the plugin header, `LICENSE`, copyright lines and WordPress.org-facing text are not touched without explicit approval.

Consequences: Sprint 0 work lands as tests, tooling and documentation first (no production behavior change); the three spikes and the registry/query refactor follow, behind the frozen snapshots.

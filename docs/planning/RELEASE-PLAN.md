# Release plan (Phase 0)

Approvals: pushes of implementation, merges, tags, WordPress.org commits, deployment and any destructive change require the owner's explicit approval (see `.ai/PROJECT_CONTEXT.md`). Documentation-only updates may be pushed to the agreed docs branch after a sensitive-content check.

## Branching
- `master` = production, always equal to the latest WordPress.org release plus documentation.
- `docs/afs-next-generation-plan` = planning and collaboration documents only.
- One short-lived branch per milestone (for example `feat/m1-foundation`), merged by approval after review; release candidates on `release/x.y`.
- Tags `vX.Y.Z` annotated, created only after the WordPress.org release is verified; the tag points at the commit the package was built from.

## Versioning and compatibility policy
Semantic versions. Minor releases add opt-in features; major only for breaking changes (none planned). Supported: current minimum WordPress 6.0 and PHP 7.4, to be reviewed per release. The 1.x compatibility layer stays through all 2.x; any deprecation is announced one major version ahead. New visual presets and the builder are opt-in; existing sites keep **Classic**.

## Per-release checklist (extends `docs/MAINTAINING.md`)
1. Scope frozen, changelog and Upgrade Notice drafted (concise, no exploit details); version bumped in header, constant and readme.
2. Gates pass: TEST-STRATEGY.md release gates; benchmarks recorded.
3. `bin/build-zip.sh` builds from tracked files only; **package check passes**; zip contents listed and compared to the previous release.
4. Upgrade test from the previous published release through the admin UI.
5. WordPress.org: SVN trunk, `tags/X.Y.Z` and `assets/` in one commit; verify the plugins API, download zip checksum equality, assets, readme and Upgrade Notice; remember the roughly 6-hour update hold, then run the live-update verification.
6. Tag and push in Git; update `docs/MAINTAINING.md` state.
7. Post-release monitoring (support forum, error reports) for at least a week before the next milestone merges.

## Packaging isolation for `.ai/` and planning docs
Current state on `master` and the docs branch: `.distignore` excludes `docs`, `bin`, `design`, `.wordpress-org` and `docs-private` but **not `.ai/`**, and there is no `.gitattributes`; a zip built from the docs branch would include `.ai/` (reproduced). A fix with an automated check is prepared **locally** on branch `chore/package-exclude-ai` (not pushed, awaiting approval): `.distignore` adds `.ai`; `.gitattributes` adds `export-ignore` for repo-only paths (also keeps GitHub's "Download ZIP" lean); `bin/check-package.sh` verifies a zip against an allowlist of top-level entries and forbidden patterns and that `git archive` honors export-ignore, and `bin/build-zip.sh` runs it. I tested it passes on the production zip (byte-identical to published 2.0.0) and fails when `.ai/` is injected. **Until that branch is approved and merged, do not build a release from any branch containing `.ai/`.** WordPress.org releases are built only from `master` after the fix merges.

## Rollout and rollback
Opt-in features ship dark by default. If a release is faulty: fix forward with a patch release (WordPress never downgrades sites); keep the previous package for manual reinstall; for a catastrophic issue ask the plugins team for help; keep the previous tag documented in the readme's upgrade guidance.

## Communication
Readme changelog and Upgrade Notice per release; migration guide updates; support-forum replies only with approval; screenshots only from the real UI.

## Open decisions for the owner
Default preset for new installs; whether builder and Pro work needs separate repositories; when to add CI (recommended with M0); whether to rename the planning branch's `docs/planning` content once the real product docs go public.

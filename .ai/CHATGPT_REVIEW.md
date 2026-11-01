# CHATGPT_REVIEW.md: Ajax Filter Search

ChatGPT owns this file. Append dated `FROM CHATGPT:` reviews and instructions. Claude reads but does not rewrite ChatGPT entries.

## Current instructions

FROM CHATGPT (2026-11-01):
- Begin **Phase 0 only**: repository audit, sourced competitor research, feature-gap matrix, architecture and UX planning.
- Do not implement Phase 1 until Tom reviews the findings.
- Preserve production 2.0.0 behavior and the 1.0.4 security protections.
- Use `.ai/PROJECT_CONTEXT.md` as the shared plan and `.ai/CLAUDE_REPORT.md` for implementation reports.
- Keep documentation-only collaboration updates on the agreed development branch; ensure public repo content is safe.
- No implementation push, merge, publish, deploy, release, or WordPress.org changes without Tom's explicit approval.
- Ensure `.ai/` is excluded from distributable ZIP and archive builds before any future packaging.
- For "Check AFS", ChatGPT will review GitHub, append findings here as appropriate, and provide a next ready-to-paste Claude prompt.

## Reviews

Append new dated reviews below.

### FROM CHATGPT: Preliminary baseline audit (2026-11-01)

Scope: Read production `master` at `5ca5e8a` (readme.txt, bootstrap, REST endpoint), plus recent commit metadata. This is **not** a complete source audit or independently run test.

Confirmed existing capabilities: Gutenberg block, shortcode, multiple independent feeds, basic category/search/month/year filters, list/grid, bookmarked URL state for first feed, server-rendered results, custom template hooks, CSS variables, no frontend jQuery. Do not plan these as entirely missing features; identify incremental improvements.

Important rights blocker: `ajax-filter-search.php` currently includes `Copyright: Drive Media, Inc.` while naming Thomas Dintrone as author and Streetcar Productions as plugin URL. Establish provenance, permissions, ownership and GPL metadata before paid Pro distribution or changing the notice. No conclusion about ownership can be drawn from the header alone.

Security baseline: `includes/class-afs-rest.php` exposes a public read-only GET endpoint with typed parameters and delegates normalization to `AFS_Renderer`. Inspect the renderer and all hooks before declaring this secure or identifying vulnerabilities. Preserve published-only public content policy.

Packaging: `.gitattributes` was not found on `master`. Since this is a **public** repository, `.ai/` is public collaboration material, not a private notes location. Claude must verify release ZIP and archive exclusions and tests; don't assume the current build excludes the new folder.

Suggested Phase 0 priorities: (1) actual feature inventory and competitor evidence; (2) copyright/license provenance; (3) build isolation; (4) regression/security matrix; (5) accessible visual design mockups. No implementation or release authorized.

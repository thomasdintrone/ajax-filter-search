# Ajax Filter Search — Next-Generation Upgrade Plan and Claude Code Handoff

Status: Proposed roadmap, awaiting audit and approval. Owner: Streetcar Productions.
Baseline: WordPress.org Ajax Filter Search 2.0.0 (SVN r3775464).
Working branch for this documentation: `docs/afs-next-generation-plan`.

## Mission
Evolve Ajax Filter Search into a beautiful, intuitive, performant WordPress content discovery solution, with a compelling free edition and a commercially viable Pro add-on. Improve frontend design, administrator experience, filter capabilities, results presentation, onboarding, accessibility, and extensibility. Preserve existing users and security protections.

**Guardrails:** No deployment, WordPress.org publishing, public tagging, production-branch merges, or unapproved remote pushes. This documentation branch was created explicitly for the handoff. Claude may inspect, research, prototype locally, test, and commit locally; request approval before pushing further changes. Do not implement all phases at once.

## Current baseline to verify
Version 2.0.0 introduced REST-based AJAX filtering, Gutenberg block, server-rendered initial results, responsive list/grid, multiple instances, URL/bookmark history, compatibility shims, and updated branding. Version 1.0.4 fixed user-controlled shortcode execution and exposure of unpublished content. Public results must remain published-only. Audit code instead of assuming any listed feature is absent.

## Competitive research — Phase 0
Research official docs, pricing, demos, WordPress.org listings, and current capabilities for:
- FacetWP
- WP Grid Builder
- Search & Filter Pro
- Filter Everything
- JetSmartFilters
- Ajax Search Pro
- AJAX Post Search and Filter
- Advanced AJAX Product Filters
- Additional relevant competitors discovered during research

Build a sourced feature-gap matrix covering taxonomy and post-type support; ACF/meta/relationship fields; facets, AND/OR, conditional filters; indexing/performance; relevance/autocomplete; card/grid layouts; visual builder; Gutenberg and page builders; mobile/accessibility; analytics; setup complexity; extensibility; free/pro packaging and price. Label confirmed facts, unverified claims, and our hypotheses. Identify differentiators rather than blindly cloning competitors.

Audit current repository architecture, REST endpoints, WP_Query logic, JS/CSS, templates, blocks, admin UX, settings, tests, compatibility, packaging, and performance. Record baseline test results and risks. Phase 0 is research/design only; stop for review before implementation.

## Product positioning
Beautiful, searchable WordPress content in minutes, without custom code. Initial audience: blogs, editorial publishers, resource libraries, directories, portfolios, and custom post types. Do not prioritize WooCommerce-specific functionality over core content discovery until justified by demand.

## Phase 1 — Frontend visual redesign (candidate 2.1.0)
Create scoped design tokens, modern accessible search/filter controls, chips, responsive result cards, typography, spacing, image ratios, loading/skeleton/empty states, and mobile filter drawer. Offer four high-quality free presets:
1. Editorial: magazine/blog image cards, category pills, metadata.
2. Directory: sidebar filters, sorting, counts, information-rich cards.
3. Portfolio: visual project grids, understated metadata.
4. Resource Library: compact searchable structured rows.

Support theme inheritance and reduced motion. Avoid generic Bootstrap appearance, heavy frontend assets, CSS leakage, and gratuitous animation. Existing sites retain their current appearance unless explicitly opting into a new preset. Prototype and visually review before changing production behavior.

## Phase 2 — Filtering essentials (candidate 2.2.0)
Add/verify multiple taxonomy filters, checkbox/radio/multi-select controls, selected chips, per-filter and global reset, result counts, date/title sorting, hierarchical terms, defaults, configurable labels, load-more pagination, accessible mobile drawer, reliable URL history, and useful zero-results states. Define and test AND/OR semantics, keyboard/focus behavior, screen-reader announcements, and independent instances.

## Phase 3 — Visual experience builder (candidate 2.3.0)
Unified workflow: **Content → Filters → Layout → Design → Publish**.
- Content: post type, taxonomies, ordering, published-only public status, limits.
- Filters: add/remove/reorder, source field, control type, labels, defaults.
- Layout: preset, grid/list, columns, images, excerpts, metadata, pagination.
- Design: colors, type, radii, spacing, theme inheritance, device previews.
- Publish: Gutenberg block, shortcode, copy action, usage help.

Preview should reuse actual frontend rendering where practical. Create a one-click setup wizard for blog, directory, portfolio, resource library based on detected public post types and taxonomies. Avoid overwriting existing configurations.

## Phase 4 — Advanced filtering foundation (candidate 2.4.0/internal)
Design typed field/filter definitions, query normalization and validation, custom-field adapters, extensibility hooks, cache strategy, optional index, safe incremental rebuilds, and versioned APIs. Benchmark representative datasets of 100, 1k, 10k, and 50k posts. Measure latency, memory, and database load; don't invent performance claims. Retain WP_Query when sufficient.

## Phase 5 — Pro add-on MVP
Separate add-on that extends Free through stable documented APIs, never a fork or patch of Free. Proposed premium value:
- Multiple post types per result set.
- Advanced ACF, Meta Box, Pods, numeric/date/relationship filtering.
- AND/OR groups, dependent and conditional facets.
- Dynamic option counts and context-aware facet availability.
- Advanced visual card builder with custom fields, conditional badges, reusable templates.
- Indexing and performance tools when warranted.
- Import/export and reusable experiences.

Design license/update compatibility and commercial distribution only after approval. Resolve GPL/license metadata and third-party copyright ownership before selling. Do not implement payments/licensing yet.

## Phase 6 — Demand-led Pro expansion
Privacy-conscious opt-in analytics (popular and zero-result searches, filter usage, result clicks); WooCommerce-specific integrations; Elementor/Bricks support; saved searches; advanced facets; diagnostics; performance advisor. Do not collect personally identifying search data by default. AI-assisted setup is experimental, only if better than deterministic setup.

## Phase 7 — Presentation and growth
Create accurate, appealing WordPress.org screenshots demonstrating actual editorial, directory, resource library, mobile, Gutenberg and admin builder experiences. Improve listing copy, documentation, and live demos. No misleading screenshots, claims, or unapproved publication.

## Free vs Pro principles
**Free:** AJAX search, modern presets, responsive cards, basic builder, Gutenberg/shortcode, basic custom post types, multiple taxonomies, chips, sorting, pagination, mobile drawer, accessible behavior.
**Pro:** advanced data sources, sophisticated logic, indexed/dynamic facets, advanced card builder, analytics, business integrations, reusable/importable advanced configurations. Do not cripple the free experience.

## Differentiation candidates
- One-click complete experiences by use case.
- Theme-aware design system.
- Filter configuration health diagnostics.
- Helpful smart empty states.
- Reusable configurations.
- Pro performance advisor.
- Privacy-first discovery analytics.

## Quality and release gates
Regression tests for 2.0.0 shortcodes, blocks, URL state, templates, REST, caching, multiple instances, legacy compatibility, and published-only queries. Explicit tests preventing the 1.0.3 vulnerabilities: shortcode injection, unauthorized data exposure, XSS/SQLi, invalid inputs. Test supported PHP/WP versions, multisite, common themes, keyboard and WCAG 2.2 AA goals, mobile browsers, and performance. Maintain clear migrations, opt-in visual upgrades, and rollback strategy.

Candidate versions are planning labels, not commitments. Each phase needs measurable acceptance criteria and review before implementation/merge/release.

## Phase 0 deliverables
Create private planning documents (outside distributable WordPress.org package):
- `COMPETITIVE-ANALYSIS.md`
- `FEATURE-GAP-MATRIX.md`
- `PRODUCT-ROADMAP.md`
- `UI-UX-DESIGN-SPEC.md`
- `TECHNICAL-ARCHITECTURE.md`
- `FREE-VS-PRO.md`
- `TEST-STRATEGY.md`
- `RELEASE-PLAN.md`

These filenames are suggested; prefer a private docs directory and exclude it from release packaging.

## Check AFS workflow
The owner will periodically tell ChatGPT **"Check AFS"**. ChatGPT uses its connected GitHub integration to inspect the remote repository, recent commits, branches, pull requests, and these roadmap documents; assess completed work, tests, blockers, and deviations; and provide a concise progress report plus the next ready-to-paste Claude Code prompt.

**Important:** ChatGPT can only inspect work visible through GitHub. Local/uncommitted/unpushed work is not visible. Claude should make small, clearly described commits and, when authorized, push work to the agreed development branch, keeping the roadmap and progress log current. Never push, merge, publish, or deploy solely because the check-in workflow exists. If work is local-only, provide a completion report that the owner can paste into ChatGPT.

## Immediate Claude instruction
Start with **Phase 0 only**. Audit the repository and research the market, produce the above artifacts and optionally static local UI prototypes. Report findings, proposed phase effort, security/compatibility risks, and first implementation milestone. **Stop and await review before Phase 1.**

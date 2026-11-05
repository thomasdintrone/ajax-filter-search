# Free versus Pro recommendation (Phase 0)

Recommendation for review. **No pricing, licensing mechanism or distribution channel is proposed here**; those need the owner's approval and the rights questions in LICENSING-PROVENANCE.md resolved first.

## Principles
1. The Free plugin stays fully useful on its own and is never degraded to sell Pro.
2. Free is where the experience quality lives: design, accessibility, builder basics, presets.
3. Pro monetizes data complexity and scale, integrations and business tooling, which are costly to build and support, not basic filtering.
4. Pro is a **separate add-on plugin** that needs Free and extends it only through documented, versioned hooks (TECHNICAL-ARCHITECTURE.md section 7). No forks or patches.
5. Free must keep working if the add-on is removed; existing configurations never lock.

## Evidence from the market (see COMPETITIVE-ANALYSIS.md)
Competitors draw the line at integrations (page builders, plugins), dynamic counts, SEO URLs, large option libraries, card builders and license size. Filter Everything Pro (confirmed) and Search & Filter Pro (confirmed) both keep core filtering in the free or entry tier.

## Proposed split
| Capability | Free | Pro |
|---|---|---|
| AJAX search, category/month/year, list and grid, shortcode, Gutenberg block, multiple feeds, shareable URLs, no-JS fallback | **Yes (exists today)** | |
| Four design presets (Editorial, Directory, Portfolio, Library) plus Classic, design tokens, theme inheritance, accent color | Yes | |
| Responsive cards with featured images, selected chips, per-filter and global reset, sort control, load more, mobile drawer | Yes | |
| Multiple taxonomy filters, checkbox/radio/select controls, hierarchical terms, AND across filters and OR within a filter | Yes | |
| Static term counts (cheap, from term data) and total result count | Yes | |
| Basic visual builder (Content, Filters, Layout, Design, Publish), saved experiences, setup wizard recipes, health check | Yes | |
| Accessibility behavior, reduced motion, empty and error states | Yes | |
| Developer hooks and template override | Yes | |
| Multiple post types in one result set | | Pro |
| ACF, Meta Box, Pods and custom-field filters; numeric and date ranges; relationship fields | | Pro |
| Explicit AND/OR groups; dependent and conditional filters | | Pro |
| Dynamic, context-aware option counts and auto-hiding empty options (needs the index) | | Pro |
| Index and performance tools; background rebuilds | | Pro |
| Advanced card builder: custom fields, conditional badges, reusable card templates | | Pro |
| Import/export and reusable experience library | | Pro |
| Privacy-first analytics (popular and zero-result searches, filter usage, clicks) | | Pro (later) |
| WooCommerce-specific integration, Elementor/Bricks widgets, saved searches | | Pro (demand-led, later) |

## Decisions for the owner
1. **Static vs dynamic counts:** recommended split above (static free, dynamic Pro). Alternative: no counts in Free.
2. **Multiple taxonomy filters in Free** (recommended; matches PROJECT_CONTEXT) vs keeping it Pro.
3. **Default preset for new installs** (suggest Editorial).
4. **Whether a Pro edition is pursued at all** and when (suggest gating on adoption evidence: the free plugin has about 60 active installs today, so Pro revenue assumptions should wait for adoption data).
5. **Distribution and licensing model** for Pro: not proposed; blocked on rights clarification.

## What must exist in Free before Pro is possible
The registry and hook API (filters, adapters, layouts, schema extension), an API version constant, stable REST response schema, and contract tests that Pro will run against. Building these is part of the Free roadmap, not Pro.

## Risks
Feature creep in Free (keep presets few and high quality); a Pro that feels like a paywalled fix (avoid by keeping all current behavior in Free); support burden of data-source integrations (Pro only); GPL obligations (Pro add-on code must be GPL-compatible; see LICENSING-PROVENANCE.md).

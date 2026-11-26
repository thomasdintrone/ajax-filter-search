# AFS future ideas backlog

Status: brainstorming only, 2026-11-26. Not approved implementation scope. Complete the current modernization proposal first; verify existing functionality and validate demand before prioritizing. This is a public repository: record only non-confidential product ideas.

## Potential free enhancements

- Smart discovery: administrator-defined synonyms and suggested searches, accessible autocomplete, related categories, helpful zero-result recovery. No external service required.
- Standalone search block/widget/shortcode: redirect to a configured AFS results page with compatible URL state.
- Specialized starter experiences: timeline (publication dates), team directory, masonry gallery, knowledge base, media library, download center. Verify metadata and accessibility requirements; avoid unsupported event/date or download claims.
- Named reusable AFS Collections: centrally manage, embed, duplicate and preview saved experiences; evaluate basic configuration portability.
- Smart setup wizard: detect public content types, taxonomies and image availability; recommend configurations without AI or external services.
- Collection health checker: detect missing taxonomies/images, empty configurations, invalid attributes, costly queries and template override compatibility; avoid unsupported diagnostic claims.
- Interactive demo/WordPress Playground: let prospective users try actual layouts, filters and styling.
- Native WordPress integration: block patterns, Global Styles, Site Editor, developer hooks, evaluate Query Loop and block bindings feasibility before promising compatibility.

## Suggested prioritization

First investigate reusable collections, setup wizard and standalone search; then specialized layouts and autocomplete. Demo and diagnostics can be prioritized based on adoption and support needs. Query Loop integration needs feasibility research.

## Guardrails

Preserve current free capabilities, legacy rendering, published-only public content, accessibility, privacy and performance. Reconcile with PROJECT_CONTEXT.md after product-owner approval. No implementation, release or roadmap supersession authorized by this backlog.

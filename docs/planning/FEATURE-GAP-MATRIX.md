# Feature-gap matrix (Phase 0)

**AFS column = verified from the 2.0.0 source and tests (2026-11-04).** Competitor cells carry only what the evidence in COMPETITIVE-ANALYSIS.md supports: **C** confirmed, **R** reported, **?** unknown/not verified, **no** confirmed absent. "Free"/"Pro" refers to the competitor's own packaging where known.

| Capability | AFS 2.0.0 | FacetWP | WP Grid Builder | Search & Filter Pro | Filter Everything | JetSmartFilters | Ajax Search Pro/Lite |
|---|---|---|---|---|---|---|---|
| Taxonomy filtering | **Yes, one taxonomy, one term at a time (tabs)** | C | C | C | C (free) | C | R |
| Multiple taxonomies at once | **No** | R | C | C | C | R | R |
| Post-type support | Any public type, **one per feed** | C | C (types, terms, users) | C | C (any type) | C | C |
| Multiple post types in one result set | **No** | R | ? | ? | ? | R | R |
| ACF / custom field / meta filters | **No** | R | C (fields in cards) | C (ACF, Pods, Meta Box) | C (ACF listed) | R | R |
| Relationship fields | **No** | R | ? | ? | ? | ? | ? |
| AND/OR logic, conditional filters | **No** | R | ? | ? | R (step-by-step) | R | ? |
| Dynamic option counts | **No** (total result count only) | R (indexed) | R | ? | C (Pro) | R | ? |
| Index / performance layer | **No** (WP_Query; see baseline) | C (indexer) | ? | ? | ? | R (indexer) | ? |
| Keyword search | **Yes** (debounced, WP search) | C | C | C | ? | C | C (core product) |
| Autocomplete / relevance tuning | **No** | C (autocomplete facet) | ? | ? | ? | ? | C (core product) |
| Date filters | **Month and year dropdowns** | C (date ranges) | R | ? | ? | C (date range) | ? |
| Sorting | **Config only (date, title, modified); not user-facing** | C | C | ? | C (sorting widget) | ? | ? |
| Selected-filter chips / reset | Reset button only | C (reset) | ? | ? | C (chips widget) | ? | ? |
| Pagination / load more | **Numbered pagination only** | C (pager, load more) | C | ? | ? | C | ? |
| Layouts | **List and grid (text cards; no images)** | own templates | C (20+ cards) | ? | ? | builder widgets | C (result layouts) |
| Card/featured-image support | **Template override only** | via template | C | ? | ? | C | C (image results) |
| Visual builder for the experience | **No** (settings page and block sidebar) | no (developer-led) [H] | C (card and facet editors) | C (Query Builder, Styles Editor) | ? | C (via Elementor/Bricks) | C (admin options) |
| Gutenberg block | **Yes** | ? | C | C (blocks) | ? | C | ? |
| Shortcode | **Yes** | C | C | C | ? | ? | C |
| Page builders (Elementor, Bricks, ...) | **Untested** (shortcode should work) | R | ? | C (Elementor, Beaver, WPBakery) | C (Pro) | C (Elementor, Bricks) | R (Elementor) |
| Mobile experience | Responsive; **no filter drawer** | ? | ? | ? | C (mobile widget, Pro) | ? | ? |
| Accessibility evidence | **axe WCAG 2.2 AA clean; no manual SR test** | ? | ? | ? | ? | ? | ? |
| Shareable URL state | **Yes (first feed on a page)**; works without JS | ? | ? | ? | C (SEO URLs, Pro) | R (permalinks) | ? |
| Multiple independent feeds per page | **Yes** | ? | ? | ? | C (filter sets) | C (providers) | ? |
| Analytics | **No** | ? | ? | ? | ? | ? | R (popular queries) |
| Extensibility | **4 filters, template override, CSS tokens** | C (many hooks) | C | ? | ? | ? | ? |
| Setup effort | Low; settings page | Higher | Medium | Medium (query builder) | Low | Medium | Medium (400+ options reported) |
| Free tier | **Fully functional, but basic** | trial only | none | free "Search & Filter" | Yes (50k installs) | none | Yes (Lite) |
| Entry paid price | n/a | $99/yr | $49/yr | $49/yr | $69/yr | about $75/yr | unverified |

## Reading the matrix
- **Biggest real gaps vs. the field:** multiple taxonomy filters, user-visible sorting, selected chips, richer result layouts (images), load more, a mobile filter drawer, and any custom-field support.
- **Where AFS is already competitive or ahead (verified):** works without JavaScript with shareable URLs; tiny assets; clean axe results; multiple feeds; Gutenberg plus shortcode; simple setup; published-only security model; legacy compatibility.
- **Do not rebuild:** block, shortcode, multi-feed, URL state, template override, CSS tokens, compat layer (see IMPLEMENTATION-AUDIT.md).
- **Cells marked "?" need hands-on verification** before they are used in any marketing or product decision.

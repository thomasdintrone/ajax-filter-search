# Competitive analysis (Phase 0)

Researched 2026-11-04. Documentation only; no implementation is authorized by this file.

**Evidence labels**
- **[C]** Confirmed: read from the vendor's own page (pricing, features) or the WordPress.org API on 2026-11-04.
- **[R]** Reported: from a search-result snippet, third-party review or directory. Treat as unverified.
- **[H]** Hypothesis: my inference, to test before relying on it.

**Limits of this research:** no competitor plugin was installed or hands-on tested; feature lists come from marketing and documentation pages; prices change and some vendors show promotional prices. Ajax Search Pro's CodeCanyon page returned HTTP 403 to my fetch, so its price is unverified.

## Market snapshot (WordPress.org API, [C], 2026-11-04)
| Plugin (free, WordPress.org) | Active installs | Rating | Last updated |
|---|---|---|---|
| Ajax Search Lite (live search and filter) | 70,000 | 96 | 2026-10-19 |
| Filter Everything (WordPress and WooCommerce filters) | 50,000 | 92 | 2026-11-02 |
| Advanced AJAX Product Filters (WooCommerce) | 50,000 | 94 | 2026-09-07 |
| Search & Filter (free edition) | 40,000 | 90 | 2026-08-22 |
| SearchWP Live Ajax Search | 40,000 | 94 | 2026-08-08 |
| AJAX Post Search and Filter | 70 | 100 | 2026-08-27 |
| **Ajax Filter Search (this plugin)** | **60** | **90** | 2026-10-30 |
Not on WordPress.org (commercial only): FacetWP, WP Grid Builder, JetSmartFilters, Search & Filter Pro.
Observation [C/H]: the free filter market is led by plugins with tens of thousands of installs; the largest are WooCommerce-oriented or search-oriented. Content-discovery (blog, directory, resource library) filtering is served mainly by commercial products.

## Competitors
### FacetWP (commercial)
- **Pricing [C]:** Basic $99/yr (1 to 3 sites), Professional $249/yr (20 sites), Agency $349 (100 sites), Enterprise $499 (500 sites); all official add-ons included; 20% off renewals; no refunds, limited free trial. Source: facetwp.com/pricing.
- **Capabilities [R]:** many facet types (checkboxes, dropdowns, radio, sliders, search, autocomplete, date and number ranges, rating, proximity, map, pager with load more, sort, reset; add-ons such as hierarchy select, color, A-Z); an indexer that re-indexes on save, with a scheduled re-index add-on; indexes custom post types, custom fields and standard fields.
- **Positioning [H]:** developer-oriented and extremely flexible; the layout is usually your own template; strongest on data sources and indexing, lighter on built-in visual design.
- **Lesson:** an index is what makes dynamic counts and large data sets work; paywalling by license size rather than feature is a viable model.

### WP Grid Builder (commercial)
- **Pricing [C]:** Basic $49/yr (1 site), Premium $99/yr (3), Ultimate $249/yr (unlimited); all add-ons included; 14-day refund; requires PHP 7.0 and WP 6.0 or newer.
- **Capabilities [C]:** 20+ facet types, a card builder with 20+ pre-made cards, grids from post types, taxonomy terms or users, ACF fields in cards, Gutenberg blocks (grid and facet) plus shortcodes, WooCommerce and Easy Digital Downloads. **[R]** its docs reportedly advise against filtering beyond about 50,000 posts.
- **Lesson:** the strongest "visual card builder plus facets in one" proposition; the closest model for our builder idea. Cards are the visible differentiator.

### Search & Filter Pro (commercial; free "Search & Filter" exists)
- **Pricing [C]:** Starter $49/yr (1 site), Professional $99 (3), Agency $219 (100); 30-day refund.
- **Capabilities [C]:** blocks and shortcodes, a Query Builder and Styles Editor, integrations with Elementor, Beaver Builder, WPBakery, ACF, Pods and Meta Box. The free edition has 40,000 installs [C].
- **Lesson:** a free base with an upgrade path works; "query builder" framing resonates; builder integrations are table stakes at the paid tier.

### Filter Everything (free + Pro)
- **Free [C]:** 50,000 installs. Filters any post type, auto-generated filters, AJAX, step-by-step filtering, sorting widget, checkboxes, radios, labels, dropdowns, range sliders. The free version filters native WordPress loops only.
- **Pro pricing [C]:** $69/yr (1 site plus 1 dev site), $199/yr (10 sites plus 10 dev sites).
- **Pro adds [C]:** filtering of page-builder content, other plugins and custom code, instant filter counts, unlimited filter sets, SEO-friendly URLs and sitemap for filtered pages, WooCommerce extras, mobile filter widget, auto-hiding empty filters.
- **Lesson:** the free/Pro line is drawn at integrations, counts, SEO and scale, not at basic filtering.

### JetSmartFilters (Crocoblock; commercial)
- **Pricing [C]:** about $75/yr for 1 site and $199 for unlimited sites on the product page (a promotional price was also displayed); bundled with the Crocoblock suite.
- **Capabilities [C/R]:** Elementor, Gutenberg and Bricks; AJAX; an indexer; hierarchy select; SEO-friendly filter URLs; about 12 filter types (Crocoblock help center; widget counts vary by page) [R].
- **Lesson:** tied to a builder ecosystem; reaching builder users is a distribution advantage we lack.

### Ajax Search Pro / Ajax Search Lite (WP Dreams)
- **Free [C]:** Ajax Search Lite, 70,000 installs, rating 96.
- **Pro [R]:** sold on CodeCanyon (price unverified; one third-party review said around $36 for a one-year license); live search, taxonomy filtering, WooCommerce, extensive options (reportedly 400+), analytics of popular queries.
- **Lesson:** a search-first product with large free adoption; relevance and autocomplete are its center, which we should not try to out-feature.

### Others seen
- AJAX Post Search and Filter: 70 installs [C]; negligible. Advanced AJAX Product Filters: WooCommerce [C]. FiboSearch (100,000 installs) and YITH WooCommerce Ajax Search (30,000, rating 60) are WooCommerce search [C]. These confirm that "WooCommerce first" is crowded; AFS's roadmap correctly avoids it initially.

## Pricing patterns [C]
Entry price for one site is $39 to $99 per year; 3 to 10 sites about $99 to $199; unlimited or agency about $219 to $350. Money-back windows 14 to 30 days. Products charge by site count more than by feature tier, and bundle all add-ons.

## Differentiator candidates (hypotheses [H], to validate)
1. **Complete use-case experiences:** one-click Editorial / Directory / Portfolio / Resource Library setups with good design out of the box.
2. **Server-render-first and shareable by default:** AFS already works without JavaScript, keeps filter state in the URL and has accessible semantics; competitors' behavior here is unverified.
3. **Very light assets:** about 5 KB compressed today (measured); competitors' weights are unmeasured.
4. **Accessibility as a feature:** axe WCAG 2.2 AA scans are clean today; formalize with manual screen-reader checks before making claims.
5. **Theme-aware tokens** instead of a Bootstrap-like look.
6. **Diagnostics and smart empty states** (a configuration health check; helpful zero-results guidance).
7. **Privacy-first analytics (Pro)** with no personal data by default.

## What not to do
Do not clone the whole facet-engine feature list; do not lead with WooCommerce; do not promise indexing or "50k+ post" scale until measured (see PERFORMANCE-BASELINE.md).

## Sources
Official pages fetched 2026-11-04: facetwp.com/pricing, searchandfilter.com/pricing, filtereverything.pro/pricing and /resources/free-vs-pro-whats-the-difference, wpgridbuilder.com and /pricing, crocoblock.com/plugins/jetsmartfilters, api.wordpress.org plugin API. Search-result summaries for FacetWP docs, Crocoblock help center, Ajax Search Pro reviews: marked [R].

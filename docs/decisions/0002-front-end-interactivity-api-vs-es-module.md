# 0002: Interactivity API or a hand-written ES module (spike 1)

Date: 2026-11-14. Status: **recommendation, awaiting owner approval**. Code: `spikes/frontend/`. Raw numbers: `.cache/spike-frontend-results.json` (not committed; reproduce with `spikes/frontend/measure.mjs`).

## Setup

WordPress 6.5.5 (PHP 8.2) and 7.1.4 (PHP 8.4) in Docker, Twenty Twenty-Five-era default block theme, page with two feeds (grid with search, and a second with limited terms), 37 seeded posts. Headless Chrome, 4x CPU throttle, Event Timing API. Four variants on the same page: shipped 2.0.0 script, Interactivity API port, hand-written ES module, Interactivity router.

## Results

| Criterion | Baseline 2.0.0 | Interactivity API | ES module |
|---|---|---|---|
| Own JS, gzip | 3.0 KB | 2.7 KB | 3.1 KB (limit 12 KB) |
| Core runtime added, gzip | none | **13.1 KB (6.5.5) / 16.1 KB (7.1.4)** where nothing else loads it | none |
| Interaction p75 (4x throttle) | 40-48 ms | 40-48 ms | 40-48 ms (limit 200 ms) |
| Layout shift during interactions | 0 | 0.03 | 0 |
| Parity checks (tab, search, selects, reset, paging, back button, second feed, summary toggle) | all pass | **all but layout toggle** | all pass |
| No-JS parity (deep link, submit button, paging links) | pass | pass | pass |
| Console errors | none | **6.5.5: `insertBefore` NotFoundError** | none |
| Server-rendered directives on the shortcode path (`wp_interactivity_process_directives`) | n/a | works on 6.5.5 and 7.1.4 | n/a |

Caveats on these numbers: 37 posts, so this is script cost only; the `select` and `page` interactions did not register as timed interactions (puppeteer's select is not a real input event; page links vanish when filters leave one page), so they are not in the p75; timings are quantized to 8 ms; layout-shift values below about 0.01 are noise (they appear on baseline runs too). On this block theme the navigation block already loads the Interactivity runtime, so the page-weight difference between variants is hidden here. **The runtime cost on a classic theme was not measured**; the figures above are the runtime file sizes.

## Findings that matter more than the timings

1. **Extending the store depends on load order.** A separate add-on that adds a new key works in either order. An add-on that overrides a function the free plugin also defines is overwritten when the add-on's module runs first (measured on 7.1.4: override lost). With a hook registry (the ES module variant) both orders worked on both versions.
2. **WordPress 6.5 pitfall (affects any module approach).** With a block theme, 6.5 prints script modules in `wp_head` after the template has rendered, so a module enqueued later (for example in `wp_footer`) is silently dropped. Modules must be enqueued at render time. My first prototype hit this.
3. **The Interactivity port threw a DOM error on 6.5.5** when results were replaced inside a hydrated region. Not diagnosed further; not seen on 7.1.4.
4. **Layout toggle did not pass in the Interactivity prototype** and I did not find the cause. This is an unresolved prototype defect, not a proven API limit.
5. The API surface differs by version (`withSyncEvent` exists only from 6.8; the prototype guards for it). Raising the minimum to 6.5 does not remove these differences; it only guarantees the module system exists.

## Recommendation

Keep a hand-written ES module (the current approach, converted to a script module), with a small hook registry as the supported extension point, and do not adopt the Interactivity API for the free plugin's front end now.

Reasons: equal speed and no-JS behavior; no added runtime; the extension story is order-independent; no 6.5 error; no dependence on an API that changed shape in 6.8. The Interactivity API remains an option later behind the same markup because the server HTML is already complete.

**The page-weight trade-off is yours to call:** the Interactivity API is "free" only on sites where another block already loads its runtime (13-16 KB gzip otherwise), and it aligns with core's direction. If you prefer core alignment over independence, the prototype shows it is feasible but needs the layout and 6.5 issues solved and a rule that add-ons never override free-plugin functions.

## Not covered

Classic-theme runtime weight, Twenty Twenty-Five specifically (the stack used the image's default block theme), screen-reader announcement testing, and real-device INP.

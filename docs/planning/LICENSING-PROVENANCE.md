# Licensing and copyright provenance (Phase 0)

Facts verified from git history, the WordPress.org SVN repository and public pages on 2026-11-04, followed by unresolved questions. **No ownership conclusion is drawn.** This is a fact record, not legal advice.

## Verified facts
| # | Fact | Evidence |
|---|---|---|
| 1 | The plugin header has said `Copyright: Drive Media, Inc.` from the first plugin commit (2016-07-20, `7f2fedf`) through every release, including today's 2.0.0 header. Author is `Thomas Dintrone`; no other copyright line exists in the code | `git show <commit>:ajax-filter-search.php` for `7f2fedf`, `a510c2b`, current |
| 2 | The same header said `License: GPL` (no version) in 1.0.0 to 1.0.4; 2.0.0 changed it to `License: GPL v2 or later` (a change made during the 2.0.0 work and disclosed at the time) | same |
| 3 | `readme.txt` said `License: GPLv2 or later` with the GPLv2 URI from 1.0.0 onward | `git show` of `readme.txt` |
| 4 | `LICENSE` is the unmodified GPL **version 3** text, created by GitHub's "Initial commit" (`a83a651`, 2016-07-20, author `Thomas Dintrone`); it still contains the template placeholders (`{year}`, `{name of author}`), i.e. no project-specific copyright line | `git log -- LICENSE`, file contents |
| 5 | The `LICENSE` file ships inside the WordPress.org packages for 1.0.x and 2.0.0 | published zips/SVN |
| 6 | The first plugin header (1.0.0) listed `Plugin URI: http://curran-connors.com`; it appears in SVN tags 1.0.1 and 1.0.2; the 1.0.3 commit (2016-08-06) changed it to `streetcarpro.com` | git history, SVN `tags/1.0.1` |
| 7 | Author URI and Twitter in the header point to `streetcarpro.com` / `@streetcarpro`; WordPress.org lists the plugin under contributor `longislandfreelancewebdesigner` (profile name "Thomas J Dintrone"); the plugin's WordPress.org homepage is `streetcarpro.com` | WordPress.org API |
| 8 | All 52 commits across all refs in the repository history are authored by one identity (`Thomas Dintrone` / `thomasdintrone`, one email address); there are no co-author trailers; SVN commits use the WordPress.org account named above | `git log --format=%an`, `svn log` |
| 9 | 1.x shipped a ~8,800-line stylesheet (`core/css/_core.scss`) with about 970 Bootstrap-3-style selectors scoped under `#afs-wrapper` and **no license or attribution header** (only generic matches for words such as "copyright" in selectors); it was added to the repo on 2016-07-27 (`7fa3896`) | git history |
| 10 | 1.x CSS/markup class names include `afs-FilingDownload...`, `afs-FilingAbstract`, `#secform`, `.corporate`, `PR` prefixes (`afs-PRDate` etc.), i.e. terms from investor-relations / SEC-filing page design | `_core.scss`, `_custom.scss`, `default.php` of 1.0.3 |
| 11 | 1.x loaded Font Awesome 4.6.3 from a CDN (not bundled) | `admin/settings.php` at 1.0.3 |
| 12 | 2.0.0 removed the Bootstrap-derived stylesheet and the Font Awesome load; older tags (1.0.1 to 1.0.4) remain downloadable from WordPress.org and still contain them | SVN tags |
| 13 | The 2.0.0 rewrite (code, CSS, JS, docs, assets) was produced in sessions with Claude Code as an AI assistant at the owner's direction; commits are authored by the owner without AI trailers per the owner's standing instruction | project history |
| 14 | `streetcarpro.com` did not respond to my request on 2026-11-04 (HTTP 000), so I could not read any public ownership statement on it | `curl` |

## What the facts do not establish
Who owned the copyright in the 1.0.0 code at creation (individual, employer, client, or company); whether `Drive Media, Inc.` is the intended rightsholder for the whole plugin, only parts, or neither; whether the `curran-connors.com` Plugin URI and the investor-relations naming indicate the code originated in work for a third party; whether any third party's text or CSS beyond Bootstrap-style selectors was copied; which GPL version the owner intends (the files say GPL unversioned, GPLv2 or later, and GPLv3 in different places); who owns AI-assisted portions of the 2.0 work.

## Unresolved questions (owner to answer or investigate)
1. Who commissioned or employed the 1.0.0 work, and under what agreement (employment, contract, none)? Is there written permission or an assignment?
2. What is the relationship among Drive Media, Inc., Streetcar Productions and `curran-connors.com`?
3. Was the 1.x layout, class vocabulary or CSS adapted from another project (the SEC-filing naming suggests a source), and if so is it licensed for GPL redistribution?
4. Did the Bootstrap-derived CSS in 1.x comply with its license terms (attribution notice)? Impact is historical (removed in 2.0), but tags remain public.
5. Which license statement is intended going forward (suggested options for counsel to evaluate: GPL v2 or later throughout, plus the GPLv3 text as an option available under "or later"), and who should be named in the copyright notice with which years?
6. How should AI-assisted code be attributed or documented?
7. Does any Pro distribution (a separate add-on) require different or additional licensing, and who is the contracting party?

## Prerequisites before any paid distribution
Written clarity on the rightsholder(s) and authority to license; a consistent license header, readme, `LICENSE` and `NOTICE` (third-party credits if any); confirmation that nothing derived from a third party's project remains or is properly licensed; a decision on how the owner or company will be named; counsel review. **Until then, do not edit the existing copyright notice, the `LICENSE` file or the license headers, and do not sell anything.** The existing notice and history must be preserved.

## Hygiene already done
The 2.0.0 header preserves the `Copyright: Drive Media, Inc.` line verbatim; the original text remains in git history, tag `v1.0.3` and the published SVN tags.

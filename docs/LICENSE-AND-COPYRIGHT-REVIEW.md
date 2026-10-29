# License and copyright review (for your review; not legal advice)

## What the repository actually says
| Where | Statement | Origin |
|---|---|---|
| `LICENSE` | Full GNU GPL **version 3** text, unmodified (generic FSF template, no project-specific copyright line) | Created by the GitHub "Initial commit" `a83a651` (2016-07-20), i.e. the license picked when the repo was made |
| `ajax-filter-search.php` header, 1.0.0 to 1.0.3 | `License: GPL` (no version) and `Copyright: Drive Media, Inc.` | Commit `7f2fedf` (2016-07-20) |
| `readme.txt`, 1.0.0 to 1.0.3 | `License: GPLv2 or later`, URI to the GPLv2 text | Same commit |
| WordPress.org SVN `trunk` and tags 1.0.1 to 1.0.3 | Shipped all three of the above together | r1459367 onward |

## Is it really inconsistent?
Mostly in presentation, not in the rights granted:
- An unversioned "GPL" means the recipient may choose any version (GPLv2 section 9, GPLv3 section 14).
- "GPLv2 or later" already includes GPLv3 as an option.
- So every user who received 1.0.x could use it under GPLv2 or GPLv3. Nothing in the files grants *less* than "v2 or later", and nothing says "v3 only" or "v2 only".
- Rights already granted for copies already distributed cannot be taken back by any later change.

The only genuine risk is a future change that **narrows** the grant (declaring "v2 only" or "v3 only"), or leaving the files unclear enough that a downstream user cannot tell which terms apply.

## Safest way to resolve it (no change to anyone's existing rights)
1. **Standardize on "GPL v2 or later"** in the plugin header and readme (this is what WordPress.org expects and what the readme already said). It keeps GPLv3 available to recipients.
2. **Leave `LICENSE` (GPLv3) untouched**, and optionally add the GPLv2 text beside it (for example `license-gpl-2.0.txt`) with a one-line pointer in the README: "Licensed under GPLv2 or later; the GPLv2 and GPLv3 texts are both included." No text is deleted.
3. Keep the `Copyright: Drive Media, Inc.` line (see below).
4. Do not touch what was already published: tags 1.0.1 to 1.0.3 stay as they are.

## State of this branch
- The 2.0.0 commit **removed** the `Copyright: Drive Media, Inc.` and `Twitter: @streetcarpro` header lines and changed `License: GPL` to `License: GPL v2 or later`. I should not have dropped the copyright line without asking.
- **Fixed on this branch:** the `Copyright: Drive Media, Inc.` line is restored verbatim in the plugin header. The original wording remains in git history too (commit `7f2fedf`, tag `v1.0.3`, and WordPress.org SVN tags).
- `Twitter:` is not restored (not a copyright item, and not a recognized WordPress header). Say if you want it back.
- `License: GPL v2 or later` is my change from the unversioned `GPL`. It matches the readme and the GPL "or later" language; revert it if you prefer the original wording.
- `LICENSE` is unchanged.

## Questions only you can answer
1. **Who holds the copyright?** The header names Drive Media, Inc., but the commits are by Thomas Dintrone. If the code was written for Drive Media (employment or contract), that company, not you personally, may control how the license is stated for future releases. Please confirm Drive Media's position before the license wording changes, even though the "v2 or later" wording only restates what was already published.
2. Should the notice read `Copyright (c) 2016 Drive Media, Inc.` with a year, and add an entry for the 2.0.0 work (`Copyright (c) 2026 ...`)? I have not invented any holder or year.
3. The 2.0.0 rewrite was produced with AI assistance. Ownership and copyright of AI-assisted code is unsettled in some jurisdictions, so you may want counsel to confirm how that code should be attributed. That is a legal question I cannot answer.

# 1.0.3 security assessment and proposed 1.0.4

Status: **assessment only. Nothing has been released.** The candidate fix exists as local git branch `security/1.0.4-backport` (one commit on top of tag `v1.0.3`) and as `docs/1.0.4-security-backport.patch`. It is not pushed to GitHub or SVN.

## Vulnerabilities in 1.0.3 (all reproduced on a test site, WP 7.1.4 / PHP 8.4)

### 1. Unauthenticated shortcode injection (primary)
`core/includes/functions.php`, `my_action_callback()`, registered for `wp_ajax_nopriv_my_action` (no login needed, no nonce).
It copies `$_POST['filingType']`, `filterBy`, `filterMonths`, `filterYears`, `withPDF` and `page` straight into a string:

    do_shortcode('[afs_feed ... filter_by="'.$filter_by.'" ...]');

A value containing `"]` ends the shortcode, so anything after it is parsed as additional shortcodes.
Proof: `filterBy=zzz"][caption]INJECTED-BY-ATTACKER[/caption][afs_feed a="` returned `INJECTED-BY-ATTACKER` in the response.
Impact: any visitor can run **any registered shortcode, with attributes of their choice**, from any plugin or theme on the site. Severity depends on what else is installed (shortcodes that read files, query data, send mail, embed remote content, etc.); with common shortcodes it is at least a reflected content-injection / information-disclosure issue, and it is a standing risk as plugins are added.

### 2. Attribute smuggling
Same cause: `filterBy=x" post_type="page` adds a second `post_type` attribute that overrides the admin's setting, so a visitor can choose the post type, offset and filters regardless of the configured feed.

### 3. Draft and scheduled post disclosure (found during testing)
`admin-ajax.php` runs with `is_admin()` true, and `WP_Query` then adds protected statuses to the default status list. The 1.0.3 query sets no `post_status`, so the unauthenticated ajax response included **draft and scheduled posts** (title, permalink, date, excerpt). Reproduced: a draft "SECRET DRAFT" and a future post appeared in the ordinary feed. (Private posts were not exposed; they need a capability.)

### 4. Lesser issues
- `wp_ajax_nopriv_get_selected` (admin settings helper) was callable by anyone and echoed unescaped option markup. Low impact (taxonomy names/labels), but it should be admin-only.
- On PHP 8, a request with missing POST fields causes a fatal error (undefined index / type error), and a second `[afs_feed]` on a page raises a "constant already defined" warning. Not exploitable, but they break the endpoint.
- Not changed in the patch (needs author access, so not a visitor attack): term names and shortcode attributes are echoed without escaping.

## Proposed minimal 1.0.4 (4 files, +51/-23)
| Change | File |
|---|---|
| Validate every POST value (slug list, text, month 1-12, 4-digit year, integer page) and call `afs_feed( $array )` **directly**; no shortcode string is built or parsed | `core/includes/functions.php` |
| `'post_status' => 'publish'` in the feed query | `core/includes/shortcodes.php` |
| `get_selected` made admin-only (`manage_options`), `nopriv` registration removed, output escaped, input validated | `core/includes/functions.php` |
| Version 1.0.4, Tested up to 7.1, changelog and upgrade notice | `ajax-filter-search.php`, `readme.txt` |

No nonce was added: the endpoint is public and read-only, and nonces break on full-page-cached sites (the usual cause of broken ajax after caching).

### Verified side by side (original 1.0.3 vs patched, same site)
| Check | 1.0.3 | 1.0.4 candidate |
|---|---|---|
| Injected shortcode executes | yes | **no** |
| Smuggled `post_type=page` honored | yes | **no** |
| Drafts / scheduled posts in ajax feed | yes (3) | **no** |
| Unauthenticated taxonomy endpoint | answers with option markup | **no longer available to visitors** (returns `0`) |
| Shortcode page HTML (`[ajax_filter_search]`) | - | **byte-identical** |
| Ajax output with a category filter, and with month + year + page | - | **byte-identical** |
| Ajax output, unfiltered | includes drafts | same markup, only the unpublished items replaced by published ones |

Markup, CSS classes, shortcode attributes and settings are untouched.

### Not yet done for 1.0.4 (needed before release)
Test on older WordPress/PHP (1.0.3 declares WP 3.6+; I only ran 1.0.4 on WP 7.1.4 / PHP 8.4), run Plugin Check, and decide on the `Tested up to` claim. Releasing 1.0.4 from SVN `trunk` before 2.0.0 means trunk will need the 1.0.3 layout for that one commit; 2.0.0 then follows.

## Recommendation
Ship 1.0.4 first, as a small security-only release, then 2.0.0 later. Existing users get the fix without any front-end change, and 2.0.0 can be reviewed and staged separately. Disclosure: after 1.0.4 is live, the readme changelog describes the fix; consider reporting to the WordPress.org plugins team / a CVE only if you want a public record.

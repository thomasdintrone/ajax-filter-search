=== Ajax Filter Search ===
Contributors: longislandfreelancewebdesigner
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=34C7NUYWTVYFA
Tags: ajax filter, search posts, custom post type, pagination, shortcode
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Displays posts or custom post types in a friendly, filterable format using ajax so there's no page reload!

== Description ==

Ajax Filter Search is a small but powerful plugin that displays your posts or custom post type in a list or grid with category tabs, search, month and year filters and pagination, all using ajax so there's no page reload.

A visitor can filter your posts by search keyword, month, year and category to get the exact content they're looking for. It's a great tool for anyone looking to show a reel of press releases, blog posts, upcoming events, whatever you can think of.

= Features =

* **Gutenberg block and shortcode** - Add the "Ajax Filter Search" block, or use the `[ajax_filter_search]` shortcode anywhere.
* **Multiple feeds per page** - Every feed has its own post type, categories, layout and filters.
* **Shareable links** - The first feed on a page keeps its filters and page number in the address bar, so a filtered view can be bookmarked or shared, and the back button works. It also works with JavaScript turned off.
* **Fast, light front end** - Plain JavaScript (no jQuery), a small stylesheet, and assets that load only on pages that contain a feed. No external fonts or icon libraries.
* **Accessible** - Labeled form fields, keyboard friendly tabs and buttons, live-region updates and a current-page indicator.
* **Easy to theme** - Set your accent color in Settings, or override any `--afs-*` CSS variable. Developers can override the item template and use filters.
* **Settings panel** - Choose the default post type, taxonomy, posts per page, layout and more under Settings > Ajax Filter Search.

= Example shortcodes =

    [ajax_filter_search]

    [ajax_filter_search terms="news,press-releases" posts_per_page="12" layout="grid"]

    [ajax_filter_search post_type="event" post_tax="event_type" orderby="title" order="ASC" show_views="false"]

= Shortcode attributes =

All attributes are optional. Anything you leave out uses the setting from Settings > Ajax Filter Search.

* `post_type` - post type slug.
* `post_tax` - taxonomy slug used for the category tabs (or `none`).
* `terms` - comma separated term slugs to limit the feed to. (`filter_by` and `filter_type` are accepted as older names for the same thing.)
* `posts_per_page` - 1 to 100.
* `layout` - `list` or `grid`, the layout the feed starts in.
* `orderby` - `date`, `title` or `modified`. `order` - `DESC` or `ASC`.
* `search` - a keyword to start with. `filter_years` / `filter_months` - start on a year (for example 2025) or month (1 to 12).
* `show_tabs`, `show_views`, `show_search`, `show_months`, `show_years` - `true` or `false`.

= Please Rate Ajax Filter Search! =

Your ratings make a big difference! If you like and use Ajax Filter Search, please consider taking the time to [rate the plugin](https://wordpress.org/support/plugin/ajax-filter-search/reviews/). Your ratings and reviews will help this plugin grow and provide the motivation needed to keep pushing it forward.

== Frequently Asked Questions ==

= What are the steps to getting Ajax Filter Search to display on my website? =

1. Add the "Ajax Filter Search" block, or copy the shortcode `[ajax_filter_search]`, into your page.
2. Optionally adjust the defaults under Settings > Ajax Filter Search.
3. View the page and watch Ajax Filter Search fetch your posts.

= Is it possible to only display posts from 1 or 2 categories? =

Yes. Add the `terms` attribute with the term *slugs* you want, separated by commas, for example `[ajax_filter_search terms="news,events"]`. Make sure the slugs belong to the taxonomy selected for that post type. (If you used `filter_by` with an older version, it still works.)

= Is the ajax functionality secure? =

The filters call a read-only REST endpoint (`/wp-json/afs/v1/results`) that only ever returns published content of public post types. All incoming values are validated and sanitized on the server, and the endpoint cannot change anything on your site, so no nonce is needed. Since version 2.0.0 the old `admin-ajax` actions are gone.

= Can I change how each result looks? =

Yes. Copy `afs-template/default.php` from the plugin folder into `your-theme/afs-template/default.php` (a child theme is checked first) and edit it. Templates written for version 1.x keep working. You can also use the `afs_query_args` and `afs_template_file` filters.

= Does it load anything from third-party servers? =

No. Version 1.x loaded Font Awesome from a CDN on every page. Version 2.0.0 loads nothing external.

= How do I change the colors? =

Pick the accent color under Settings > Ajax Filter Search. For more control, set CSS variables in your theme, for example `.afs { --afs-accent: #0a7; --afs-radius: 0; }`.

= What happens to my data if I uninstall? =

The plugin's single settings option is removed when you delete the plugin. Your posts are never touched.

== Installation ==

= Using The WordPress Dashboard =

1. Navigate to 'Add New' in the plugins dashboard
2. Search for 'Ajax Filter Search'
3. Click 'Install Now'
4. Activate the plugin on the Plugin dashboard

= Uploading in WordPress Dashboard =

1. Navigate to 'Add New' in the plugins dashboard
2. Navigate to the 'Upload' area
3. Select `ajax-filter-search.zip` from your computer
4. Click 'Install Now'
5. Activate the plugin in the Plugin dashboard

= Using FTP =

1. Download `ajax-filter-search.zip`
2. Extract the `ajax-filter-search` directory to your computer
3. Upload the `ajax-filter-search` directory to the `/wp-content/plugins/` directory
4. Activate the plugin in the Plugin dashboard

== Screenshots ==

1. Overview of Ajax Filter Search in action: search filters, the post reel, pagination and the list / grid views.
2. A visitor can instantly narrow results by keyword, month and year.
3. "Summary" buttons reveal a teaser of each post.
4. Grid view.
5. Settings panel where you choose a post type (including custom post types), a taxonomy and other options.
6. Optional category tabs at the top of the reel.

== Changelog ==

= 2.0.0 =
* New: Gutenberg block with editor controls and live preview.
* New: Multiple feeds on one page, each with its own settings.
* New: Shareable, bookmarkable filter state in the address bar for the first feed on a page; works without JavaScript; back button supported.
* New: Shortcode attributes `terms`, `layout`, `orderby`, `order`, `search`, `show_tabs`, `show_views`, `show_search`, `show_months`, `show_years`. `filter_by` now does what the old documentation said (limit to category slugs).
* New: Accent color setting and `--afs-*` CSS variables.
* New: Settings for the default layout; `afs_query_args` and `afs_template_file` filters.
* Security: Replaced the unauthenticated admin-ajax handler, which built shortcodes from raw POST data, with a validated read-only REST endpoint. All output is escaped and all settings are validated on save.
* Improved: Front end rewritten in plain JavaScript (jQuery no longer required) with a small, scoped stylesheet. Assets load only on pages that use the plugin.
* Improved: Accessibility - labeled fields, ARIA states, keyboard friendly controls, announced updates.
* Improved: Fully translatable; dates and month names follow the site language and settings.
* Improved: Works with PHP 7.4 to 8.4 and WordPress 6.0 to 7.1; removed all PHP warnings from earlier versions (for example when two feeds shared a page).
* Improved: Child themes are checked for template overrides; the settings page and its scripts only load on the plugin's own screen.
* Removed: The Font Awesome CDN, the bundled Bootstrap-derived CSS, SCSS sources and development junk from the plugin package.
* Added: `uninstall.php` removes the plugin's setting on deletion.

= 1.0.3 =
* Function / Structure Improvements
* Bug fixes to address Page loop error.
* Added a template capability to the loop to allow for customization in the future.
* Added "filter_by" attribute to the shortcode to allow for selected categories in the loop
* Began ground work for color selection options in settings page

= 1.0.2 =
* Update to test Version #

= 1.0.1 =
* CSS Edits, A Few Bug Fixes, Added Screenshots, Robots text edits, Compiled SCSS files

= 1.0.0 =
* Ajax Filter Search

== Upgrade Notice ==

= 2.0.0 =
Major update: new block, multiple feeds, REST-based ajax, accessibility and security improvements. The Bootstrap-derived styles and Font Awesome are gone; if you styled the old markup, please check your pages. Theme template overrides written for 1.x keep working.

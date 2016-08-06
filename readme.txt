=== Ajax Filter Search ===
Contributors: longislandfreelancewebdesigner
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=34C7NUYWTVYFA
Tags: ajax search, ajax filter, search posts, search using ajax, filter posts by year, filter posts by month, filter posts by date, pagination using ajax, display posts in table format, custom post type search
Requires at least: 3.6
Stable tag: 1.0.3
Tested up to: 4.5.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Displays posts or custom post types in a friendly, filterable format using ajax so there's no page reload!

== Description ==

Ajax Filter Search is a small but powerful plugin that displays your post or custom post type in a tabled format with pagination, all using ajax so there's no page reload! 

A user can filter your posts by search keyword, month, year, and category to get the exact content they're looking for. This is a great tool for anyone looking to show a reel of Press Releases, Blog Posts, Upcoming Events, whatever you can think of!


= Features =
* **Setting Panel** - Customize your version of Ajax Filter Search by updating various plugin settings (see screenshots).
* **Customizeable Shortcode** - Beginning the process of allow overriding of the default values in the general settings with custom shortcode attributes. In the future, this will give you the ability to have multiple shortcodes throughout the site. For now, You can add a "filter_by" attribute to show selected categories/taxonomies to the default query.

***

= Example Shortcode =

    [ajax_filter_search filter_by="category-slug-1,category-slug2"]

***
   

= Tested Browsers =

* Firefox (Mac, PC)
* Chrome (Mac, PC, iOS, Android)
* Safari (Mac, iOS)
* IE10+

***


= Please Rate Ajax Filter Search! =

Your ratings make a big difference! If you like and use Ajax Filter Search, please consider taking the time to [rate my plugin](http://wordpress.org/support/view/plugin-reviews/ajax-filter-search). Your ratings and reviews will help this plugin grow and provide the motivation needed to keep pushing it forward.



== Frequently Asked Questions ==


= What are the steps to getting Ajax Filter Search to display on my website =

1. Copy the shortcode [ajax_filter_search]
2. Add the shortcode to your page, by adding it through the content editor or placing it directly within one of your template files.
3. Load a page with your shortcode in place and watch Ajax Filter Search fetch your posts. 

= Is it possible to only display post from 1 or 2 categories? =

Yes! Simply add the "filter_by" attribute to the shortcode and enter the *category slug* of the selected category/taxonomy you'd like to display. For multiple categories, separate each with a comma (,).

Make sure you have the correct slug for the correct post type & taxonomy you selected..

= Is the ajax functionality secure? =

Yes, Ajax Filter Search uses admin-ajax and nonces in order to protect URLs and forms from being misused.

= Can I make modifications to the plugin code? =

Yep, but just know that any edits may affect future versions.


== Installation ==

How to install Ajax Filter Search.

= Using The WordPress Dashboard =

1. Navigate to the 'Add New' in the plugins dashboard
2. Search for 'Ajax Filter Search'
3. Click 'Install Now'
4. Activate the plugin on the Plugin dashboard

= Uploading in WordPress Dashboard =

1. Navigate to the 'Add New' in the plugins dashboard
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

1. Overview of AFS in action. Here you see search filters, the post reel, pagination capabilities and dual reel views (List & Grid)
2. See how a user can instantly improve their search experience by filtering out the most relevant content for them.
3. "Summary" buttons pull the excerpt from the post so they can view a teaser of the content for that particular post
4. Grid View
5. Settings panel where you can choose a post type (including custom post types) and an optional taxonomy among other options. New features coming soon!
6. Optional Filter Tabs at the top of the reel to filter results by category.


== Changelog ==

= 1.0.3 =
* Function / Structure Improvements
* Bug fixes to address Page loop error.
* Added a template capability to the loop to allow for customization in the future.
* Added "filter_by" attritbute to the shortcode to allow for selected categories in the loop
* Began ground work for color selection options in settings page

= 1.0.2 =
* Update to test Version #

= 1.0.1 =
* CSS Edits, A Few Bug Fixes, Added Screenshots, Robots text edits, Compiled SCSS files

= 1.0.0 =
* Ajax Filter Search

== Upgrade Notice ==

* None 



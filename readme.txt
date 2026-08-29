=== Admin Posts Navigation ===
Contributors: mbrinson
Tags: admin, navigation, posts, pages, editor
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Navigate between posts, pages, and custom post types without returning to the post list. Includes customizable sorting and ascending/descending navigation order.

== Description ==

Admin Posts Navigation solves a common WordPress admin workflow problem: having to go back to the post list every time you want to edit the next or previous post.

**Key Features:**

* **Seamless Navigation**: Navigate directly between posts without returning to the post list
* **Customizable Sorting**: Choose between Menu Order, Date Published, Alphabetical, or Post ID sorting
* **Ascending / Descending Order**: Choose the navigation direction independently from the selected sorting method
* **User Preferences**: Each user's sorting method and direction are saved per post type
* **Universal Support**: Works with posts, pages, and all supported public custom post types automatically
* **Dual Editor Support**: Full compatibility with both Classic Editor and Gutenberg Block Editor
* **Smart Defaults**: Uses Date Published for posts and Menu Order for pages by default
* **Position Tracking**: Shows your current position (e.g., "Position: 3 of 15 posts")
* **Security First**: Rate limiting, nonce verification, and capability checks
* **Performance Optimized**: Intelligent caching and optimized database queries
* **Developer Friendly**: Multiple filter hooks for customization

**How It Works:**

In the **Classic Editor**, navigation buttons appear below the post title. A "Sort By" dropdown lets you choose the sorting method, while a separate "Order" dropdown lets you choose Ascending or Descending order.

In **Gutenberg**, a navigation panel appears in the Document Settings sidebar with Previous/Next buttons, a "Sort By" dropdown, and a separate "Order" dropdown.

**Sorting Options:**

* **Menu Order**: Navigate using the WordPress `menu_order` value
* **Date Published**: Navigate by publication date
* **Alphabetical**: Navigate by post title
* **Post ID**: Navigate by WordPress post ID

**Order Options:**

* **Ascending**: Navigate from lower to higher values (for example, Menu Order 1, 2, 3, 4)
* **Descending**: Navigate from higher to lower values (for example, Menu Order 4, 3, 2, 1)

The sorting method and direction work independently. For example, you can navigate Alphabetically in Ascending order (A-Z) or Descending order (Z-A).

Each user's sorting method and order preferences are remembered per post type, so posts, pages, and custom post types can each use different navigation settings.

**Perfect For:**

* Content managers editing multiple posts in sequence
* Sites that use Menu Order to organize pages or custom post types
* Bloggers reviewing and updating existing content
* Developers working with custom post types
* Anyone who finds the default WordPress admin workflow tedious

The plugin automatically detects supported public custom post types and provides navigation for any content type the current user can edit.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/admin-posts-navigation` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Start editing any supported post, page, or custom post type - navigation controls will appear automatically.

== Frequently Asked Questions ==

= Does this work with custom post types? =

Yes! The plugin automatically detects and supports public custom post types that the current user has permission to edit. No configuration is normally needed.

= Does this work with both Classic Editor and Gutenberg? =

Absolutely. In Classic Editor, buttons appear below the post title with inline sorting and order controls. In Gutenberg, a navigation panel appears in the Document Settings sidebar with Previous/Next buttons and integrated sorting and order controls.

= Can I customize the sorting method? =

Yes! Choose from four sorting options: Menu Order, Date Published, Alphabetical, or Post ID.

= Can I choose Ascending or Descending order? =

Yes. The Order control lets you independently choose Ascending or Descending navigation for any of the available sorting methods.

For example:

* Menu Order + Ascending: 1, 2, 3, 4
* Menu Order + Descending: 4, 3, 2, 1
* Alphabetical + Ascending: A-Z
* Alphabetical + Descending: Z-A
* Post ID + Ascending: Lower IDs to higher IDs
* Post ID + Descending: Higher IDs to lower IDs

= Are my sorting preferences saved? =

Yes! Each user's sorting method and direction are saved individually per post type. For example, you can sort posts by Date Published in Descending order while sorting pages by Menu Order in Ascending order, and those preferences will be remembered across sessions.

= What is Menu Order? =

Menu Order uses WordPress's built-in `menu_order` field. It is commonly used for pages and custom post types that support manual ordering.

When Menu Order is selected with Ascending order, lower menu order values appear first.

= What are the default sorting settings? =

Pages default to Menu Order. Posts default to Date Published. If no order direction has previously been saved, the default direction is Ascending.

Once you select a different sorting method or direction, your preference is saved for that post type.

= Does this affect site performance? =

No. The plugin only loads its navigation interface in the WordPress admin area and uses intelligent caching to minimize database queries.

Navigation cache keys include both the selected sorting method and direction so that Ascending and Descending navigation results are cached independently.

= Can I exclude certain post types? =

Yes, use the `twf_admin_posts_navigation_excluded_types` filter to exclude specific post types.

== Screenshots ==

1. Classic Editor navigation buttons with Sort By and Order dropdowns below the post title
2. Gutenberg navigation panel with Sort By and Order controls in the Document Settings sidebar
3. Position counter showing the current location in the post sequence
4. Sort By dropdown with Menu Order, Date Published, Alphabetical, and Post ID options
5. Order dropdown with Ascending and Descending options

== Changelog ==

= 1.5.0 =

* Added Menu Order as a selectable navigation sorting option
* Added separate Ascending and Descending navigation order control
* Added Ascending/Descending order preference storage per user and post type
* Added Ascending/Descending control to the Classic Editor navigation interface
* Added Ascending/Descending control to the Gutenberg navigation panel
* Updated AJAX sorting preferences to save both the sorting method and direction
* Updated navigation queries to honor the selected Ascending or Descending direction
* Updated navigation cache keys to include both sorting method and direction
* Pages now default to Menu Order sorting
* Updated plugin asset version for improved cache busting

= 1.4.1 =

* Improved security with better nonce verification
* Enhanced user permission checks

= 1.4.0 =

* Added customizable sorting dropdown with Date Published, Alphabetical, and Post ID options
* Added user preference storage - sort preferences saved per post type
* Enhanced Classic Editor with inline sorting dropdown
* Enhanced Gutenberg with sorting control in navigation panel
* Added AJAX functionality for real-time sort preference updates
* Improved caching system to include sort method in cache keys
* Added proper cleanup of user preferences on plugin uninstall

= 1.3.2 =

* Performance improvements - removed direct meta_query usage

= 1.3.1 =

* Fixed textdomain consistency issues

= 1.3.0 =

* Initial release
* Classic Editor and Gutenberg support
* Automatic custom post type detection
* Security features: rate limiting, nonce verification
* Performance optimization with intelligent caching

== Upgrade Notice ==

= 1.5.0 =
Adds Menu Order sorting and a separate Ascending/Descending order control for Classic Editor and Gutenberg. Sorting method and direction preferences are saved independently for each post type.

= 1.4.1 =
Security improvements with enhanced nonce verification and user permission checks.

= 1.4.0 =
Major update! Added customizable sorting options with user preferences. Choose between Date Published, Alphabetical, or Post ID sorting.

== Developer Hooks ==

**Filters:**

* `twf_admin_posts_navigation_excluded_types` - Exclude specific post types from navigation
* `twf_admin_posts_navigation_orderby` - Customize ordering for specific post types
* `twf_admin_posts_navigation_order` - Customize sort direction for specific post types
* `twf_admin_posts_navigation_query_args` - Modify the query arguments for finding posts

**Example Usage:**

```php
// Exclude a custom post type
add_filter('twf_admin_posts_navigation_excluded_types', function($excluded) {
    $excluded[] = 'my_private_post_type';
    return $excluded;
});

// Custom ordering for events
add_filter('twf_admin_posts_navigation_orderby', function($orderby, $post_type) {
    if ($post_type === 'event') {
        return 'meta_value';
    }
    return $orderby;
}, 10, 2);

// Navigate only through featured posts
add_filter('twf_admin_posts_navigation_query_args', function($args, $current_post) {
    if ($current_post->post_type === 'post') {
        $args['meta_query'] = array(
            array(
                'key' => 'featured_post',
                'value' => '1',
                'compare' => '='
            )
        );
    }
    return $args;
}, 10, 2);
```

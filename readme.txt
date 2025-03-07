=== WP Markdown Exporter ===
Contributors: xiaoyao
Donate link: https://paypal.me/1q43?country.x=C2&locale.x=zh_XC
Tags: markdown, export, posts, images, content
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Export your WordPress posts as Markdown files with images, making it easy to migrate content or create backups in a portable format.

== Description ==

WP Markdown Exporter is a powerful tool that allows you to export your WordPress posts as Markdown files, complete with images. This plugin is perfect for content creators who want to:

* Create portable backups of their content
* Migrate posts to another platform that supports Markdown
* Edit content offline in Markdown format
* Archive posts in a format that's not dependent on WordPress

**Key Features:**

* Export posts as clean Markdown files
* Download and include images used in posts
* Filter posts by category and date range
* Create separate archives for posts and images
* Maintain proper formatting during conversion
* Simple and intuitive user interface

The plugin handles complex WordPress content structures, including Gutenberg blocks, and converts them to clean, readable Markdown format.

== Installation ==

1. Upload the `wp-markdown-exporter` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Tools > Markdown Exporter' to use the plugin

== Frequently Asked Questions ==

= Where are the exported files stored? =

All exported files are stored in the `/wp-content/uploads/wp-markdown-exports/` directory on your server. You can download them directly from the export page.

= Can I export only specific posts? =

No, but you can filter posts by category and date range before exporting.

= Does the plugin handle images? =

Yes, the plugin can download all images used in your posts and include them in a separate archive. Image URLs in the Markdown files will be updated to point to the local images.

= What about custom post types? =

The current version focuses on standard WordPress posts. Support for custom post types may be added in future versions.

= Is the ZIP extension required? =

Yes, the PHP ZIP extension is required to create the archives for download.

== Screenshots ==

1. The main export interface with filtering options
2. Export results with download links
3. Example of exported Markdown content

== Changelog ==

= 1.0.0 =
* Initial release
* Export posts as Markdown files
* Download and include images
* Filter by category and date
* Create separate archives for posts and images

== Upgrade Notice ==

= 1.0.0 =
Initial release of WP Markdown Exporter.

== Usage ==

1. Go to 'Tools > Markdown Exporter' in your WordPress admin
2. Select categories to filter posts (optional)
3. Set a date range to filter posts (optional)
4. Choose whether to include images
5. Click 'Export to Markdown'
6. Download the generated archives

The plugin will create two downloadable archives:
* Posts Archive - Contains all your posts as Markdown files
* Images Archive - Contains all images used in your posts (if you chose to include images)
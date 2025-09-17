# AnsarWebsitePlugins
Plugins for the new WordPress site for Ansare.

## Simple Upcoming Events

This plugin lets editors create a small list of upcoming events. Each event
supports an optional link that can point to an external URL **or** to any page
on the site. Links open in a new tab when rendered via the `[upcoming_events]`
shortcode.

## Interactive US Map

This plugin provides a shortcode `[interactive_us_map]` that displays a Google map of the contiguous United States. Cities configured in the plugin settings page appear as animated pins on the map. Each pin links to an internal page chosen by the administrator.

In the WordPress admin area, navigate to **Interactive US Map** to:

- Enter your Google Maps API key.
- Add new cities with latitude, longitude and a page link.
- Remove existing cities.

When rendered, hovering a pin causes it to glow. Clicking a pin opens the page associated with that city in a new tab.

## Ansar Community Blog

This plugin powers a rich community blog experience. Administrators can publish articles through a dedicated editor that supports formatted content, inline images, optional attached artwork, and a featured flag. Articles can be assigned to existing categories or new categories created on the fly. Subscribers stored by the plugin receive email notifications whenever a new article is published.

The front-end `[ansar_blog]` shortcode renders the Tailwind-powered blog layout provided in the design brief. It highlights the featured article, lists recent articles with a load-more interaction (up to six), exposes search within the main content area, showcases the five busiest categories, and offers an email sign-up form. The `[ansar_blog_all_categories]` shortcode outputs a dedicated page with the complete category list for the "View All Categories" link. Both pages are created automatically when the plugin is activated so they can be dropped into any navigation menu.

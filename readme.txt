=== FastCloudWP – Media Offload & CDN Delivery in 2 minutes ===
Contributors: fastcloudwp, santerref
Tags: media, cdn, offload, cloud storage, performance
Requires at least: 6.1
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.1
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically offload your WordPress media library to FastCloudWP. Reduces server load, frees up disk space, and keeps your site fast.

== Description ==

WordPress media libraries grow fast. Images, videos, and documents pile up on your server, increasing disk space usage and server load with every request. FastCloudWP automatically offloads your media library to cloud storage and rewrites every URL so visitors get files from the CDN, faster and with less load on your server.

**No AWS. No IAM. No S3 buckets. No storage configuration.** Just [create a FastCloudWP account](https://app.fastcloudwp.com/register), paste your site key into the plugin, and you're done. Everything else is handled for you, including a **5 GB free plan, unlimited sites, no time limit**.

= Features =

* **Automatic offloading:** New uploads are automatically offloaded to cloud storage immediately after processing; images, videos, documents, and all thumbnail sizes included.
* **CDN URL rewriting:** Frontend `src` and `srcset` attributes are rewritten on the fly to serve media from the CDN. Compatible with any theme, page builder, or plugin that uses standard WordPress media functions.
* **Bulk migration:** Offload your entire existing media library in bulk from the admin dashboard.
* **Optional local deletion:** Free up disk space by removing local copies after offloading.
* **Offload status tracking:** Each attachment is tracked (`queued`, `pending`, `offloaded`) so you always know what's been moved.
* **WP-CLI support:** Offload media and free disk space from the command line, with progress bars.
* **Settings UI:** Clean admin interface to connect, configure, and monitor everything.

= Pro Plan =

Upgrade to Pro for more storage and advanced features:

* **100 GB storage** (20x the free plan)
* **Custom CDN domain:** serve assets from your own domain, e.g. `cdn.yoursite.com`
* **Direct restore to WordPress:** restore offloaded media back to your server in one click
* **Priority support**

Learn more at [fastcloudwp.com](https://fastcloudwp.com/).

== Installation ==

1. Install and activate the plugin using WordPress' built-in installer.
2. Access **FastCloudWP** from the WordPress admin menu.
3. Register for a free account at [fastcloudwp.com](https://fastcloudwp.com/).
4. Create a new website in your FastCloudWP dashboard.
5. Copy your site key and paste it into the plugin, then click **Connect**.
6. Use the **Offload** tab to migrate your existing media library in bulk.

== Frequently Asked Questions ==

= Is setup really that simple? =

Yes. Install the plugin, paste your site key, done. No AWS account, no IAM policies, no bucket config, no `wp-config.php` edits. If you can copy a password, you can set up FastCloudWP.

= Will this break my site or hurt my SEO? =

No. Every media URL is rewritten automatically and kept in sync. Your site looks identical to visitors and to Google — the only difference is that images now load from the CDN instead of your server.

= Will I get surprise bandwidth bills if my site goes viral? =

Never. Bandwidth and CDN delivery are included on every plan. One flat monthly price based on storage — no egress fees, no per-request charges, no overages.

= Does it work with WooCommerce, Elementor, Divi, or ACF? =

Yes. FastCloudWP rewrites URLs across the full WordPress filter graph, so page builders, custom fields, galleries, and WooCommerce product images all serve from the CDN with zero extra config.

= What's the catch with the free plan? =

There isn't one. 5 GB, unlimited sites, full bulk migration, full CDN, no time limit. The free tier exists so you can prove it works before you pay anything.

== External Services ==

This plugin connects to the **FastCloudWP** service to store and deliver your media files.

When you connect the plugin, your site communicates with the FastCloudWP API at `https://app.fastcloudwp.com/` to authenticate, offload media files, and receive delivery status callbacks. Media files uploaded to WordPress are transmitted to FastCloudWP's servers and served to visitors from the FastCloudWP CDN.

This service is operated by FastCloudWP. Please review their policies before using the plugin:

* [Privacy Policy](https://fastcloudwp.com/privacy)
* [Terms of Service](https://fastcloudwp.com/terms)

No data is sent to FastCloudWP until you explicitly connect the plugin using your site key.

== Screenshots ==

1. The FastCloudWP dashboard showing offload progress and media statistics.
2. The Connect your Website screen
3. The Settings screen to configure offload options.

== Changelog ==

= 1.0.2 =
* Added support panel to the dashboard with links to WordPress.org forum, email, and Discord.
* Added /cdn-ready webhook so the app can signal when the CDN certificate is live.
* URL rewriting is now gated on CDN readiness — original URLs are served until the app confirms the CDN is ready.
* Fixed HTML rewriter incorrectly rewriting plugin-generated files (Elementor CSS, cache, etc.) stored in root-level upload directories.
* Added a file extension blocklist to prevent non-media files (CSS, JS, PHP, etc.) from being rewritten.
* Added a yellow "Configuring CDN…" status indicator in the dashboard while the CDN certificate is being provisioned.
* Added automatic migration for sites connected before v1.0.2 so URL rewriting continues without requiring a reconnect.

= 1.0.1 =
* Improved onboarding screen with a guided two-step setup to make connecting your site easier.

= 1.0.0 =
* Initial release with automatic media offloading to FastCloudWP cloud storage.
* CDN URL rewriting for frontend `src` and `srcset` attributes on the fly.
* Batch offload existing media library from the admin dashboard.
* Optional local file deletion to free up disk space after offloading.
* Per-attachment status tracking (`queued`, `pending`, `offloaded`).
* WP-CLI commands to offload media and free disk space from the command line.
* Clean admin settings UI to connect, configure, and monitor offloads.
* Custom domain support for serving media from your own CDN domain.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

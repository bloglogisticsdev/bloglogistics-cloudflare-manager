=== BlogLogistics Cloudflare Manager ===
Contributors: bloglogistics
Tags: cloudflare, cache, purge, development mode
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 1.0.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Manage Cloudflare cache, Development Mode, and response header checks from WordPress.

== Description ==

BlogLogistics Cloudflare Manager provides focused Cloudflare controls under the shared BlogLogistics dashboard menu.

* Connect a restricted user API token and automatically detect the Cloudflare zone.
* Show zone details and the WordPress site host separately.
* Clear up to 30 site URLs or paths, or clear the entire zone after confirmation.
* Record the last successful purge request using the WordPress timezone.
* Turn Development Mode on or off and refresh the remaining time.
* Inspect a public site URL without login cookies or following redirects.
* Disconnect only after opening the section and confirming token deletion.
* Read a built-in setup tutorial with a link to Cloudflare’s current instructions.

Results appear beside the action controls without page reloads. Connection status uses green or red backgrounds. Open BlogLogistics → Cloudflare Manager to use the controls.

The saved API token is encrypted and is not displayed again. Keep a secure copy elsewhere. You may need to reconnect after moving the site or changing its security settings. PHP Sodium or OpenSSL is required for secure token storage.

This plugin does not automatically purge cache when content changes. Controls affect Cloudflare only and do not clear WordPress, hosting, browser, or other plugins’ caches. Development Mode and full-zone purges affect the entire Cloudflare zone, including other subdomains.

External services: connecting, checking zone status, purging, and controlling Development Mode send authenticated requests from this server to https://api.cloudflare.com/client/v4. Purge requests include the chosen URLs. URL checks send an unauthenticated request to this site. Update checks contact https://updates.bloglogistics.com; installing updates downloads the release ZIP from GitHub. No token is sent to the update service or GitHub by this plugin.

Cloudflare terms: https://www.cloudflare.com/terms/
Cloudflare privacy: https://www.cloudflare.com/privacypolicy/
Cloudflare token instructions: https://developers.cloudflare.com/fundamentals/api/get-started/create-token/

== Installation ==

1. Download the latest BlogLogistics Cloudflare Manager plugin ZIP from its release page, or install it through Install BlogLogistics Plugins if available.
2. In WordPress, open Plugins → Add New Plugin → Upload Plugin, choose bloglogistics-cloudflare-manager.zip, and activate it.
3. Open BlogLogistics → Cloudflare Manager.
4. Expand Show Cloudflare setup tutorial. Create a user token with Zone → Cache Purge → Purge and Zone → Zone → Read, restricted to this site’s specific zone.
5. Optionally add Zone → Zone Settings → Edit to use Development Mode.
6. Paste the token and select Connect to Cloudflare. Keep a secure copy elsewhere.

== Frequently Asked Questions ==

= Does connecting clear the cache? =
No. Connection verifies the user token and locates the zone. Cache Purge permission is checked only when a purge is submitted. The plugin does not perform a test purge while connecting.

= Why are the Cloudflare zone and WordPress host different? =
The zone can be example.com while WordPress uses www.example.com or another subdomain. This is normal. Selected URL purges accept only the WordPress Site Address host; full-zone purges affect the whole zone.

= Can I recover a token after disconnecting? =
Only if you kept a copy elsewhere. The plugin deletes its current stored copy after confirmation. This does not revoke the token at Cloudflare or erase older database backups. Opening or closing the disconnect section does not delete anything.

= Why does the URL check show DYNAMIC, a redirect, or no Cloudflare header? =
It shows one unauthenticated response fetched from this server. Cache rules, DNS routing, redirects, and the requested resource affect the headers. This diagnostic is separate from API connection status. The URL must be publicly accessible; redirects are not followed.

= What does the last purge message mean? =
Cloudflare accepted a purge request. This does not establish that the requested content had been cached or that every cache variant was targeted. The record includes the scope, zone, and date in the WordPress timezone. Failed requests do not replace it.

= Can I purge URLs using a custom cache key? =
This version sends plain URLs. If a custom cache key includes headers, use Cloudflare’s corresponding header-aware purge tools. Worker-managed caches or custom routing can need separate handling.

= Why is Development Mode unavailable? =
The optional Zone Settings Edit permission may be missing, the token may lack access to the zone, or the setting may not be editable. Purging does not require this extra permission. A countdown is estimated locally after a Cloudflare response; refresh to confirm automatic expiry.

= Does this replace SpeedStack or other cache plugins? =
No. It provides separate manual Cloudflare controls, with its own saved token and settings. It does not read or change another plugin’s settings. Changes to the shared Cloudflare zone are visible to other tools managing that zone.

= What happens when I deactivate or delete the plugin? =
Deactivation preserves the connection. Deleting the plugin through WordPress removes its saved connection and purge record, including per-site settings on multisite. Neither action revokes a Cloudflare token or changes Development Mode.

== Changelog ==

= 1.0.0 =
* Initial release with token connection, automatic zone discovery, zone details, and shared BlogLogistics menu integration.
* Add selected URL and confirmed entire-zone purges, with a timestamped last-purge record.
* Add Development Mode controls, response header checks, inline results, and a Cloudflare setup tutorial.
* Add encrypted token storage and confirmed disconnection.
* Add support for automatic plugin update checks.

== Upgrade Notice ==

= 1.0.0 =
Initial release. Requires WordPress 7.1 or later and PHP 8.3 or later.

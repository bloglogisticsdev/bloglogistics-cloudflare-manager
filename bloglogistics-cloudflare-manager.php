<?php
/**
 * Plugin Name:       BlogLogistics Cloudflare Manager
 * Plugin URI:        https://github.com/bloglogisticsdev/bloglogistics-cloudflare-manager
 * Description:       Manage Cloudflare cache, purge selected URLs or an entire zone, control Development Mode, and inspect cache response headers directly from WordPress.
 * Version:           1.0.2
 * Requires at least: 7.1
 * Requires PHP:      8.3
 * Author:            BlogLogistics
 * Author URI:        https://www.bloglogistics.com
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Update URI:        https://updates.bloglogistics.com/plugins/bloglogistics-cloudflare-manager.json
 * Text Domain:       bloglogistics-cloudflare-manager
 * Domain Path:       /languages
 *
 * @package BlogLogistics_Cloudflare_Manager
 */

defined( 'ABSPATH' ) || exit;

define( 'BLOGLOGISTICS_CFM_VERSION', '1.0.2' );
define( 'BLOGLOGISTICS_CFM_FILE', __FILE__ );
define( 'BLOGLOGISTICS_CFM_DIR', plugin_dir_path( __FILE__ ) );
define( 'BLOGLOGISTICS_CFM_SLUG', 'bloglogistics-cloudflare-manager' );

// Load the bundled updater when available, without making the site depend on it.
$bloglogistics_cfm_updater_file = BLOGLOGISTICS_CFM_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
if ( is_readable( $bloglogistics_cfm_updater_file ) ) {
	require_once $bloglogistics_cfm_updater_file;
	if ( class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
		\YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://updates.bloglogistics.com/plugins/bloglogistics-cloudflare-manager.json',
			__FILE__,
			BLOGLOGISTICS_CFM_SLUG
		);
	}
}
unset( $bloglogistics_cfm_updater_file );

add_action(
	'plugins_loaded',
	static function (): void {
		load_plugin_textdomain( BLOGLOGISTICS_CFM_SLUG, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		require_once BLOGLOGISTICS_CFM_DIR . 'includes/class-cloudflare-api.php';
		require_once BLOGLOGISTICS_CFM_DIR . 'includes/class-plugin.php';
		new \BlogLogistics\CloudflareManager\Plugin();
	}
);

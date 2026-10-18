<?php
/** Remove only this plugin's saved settings when WordPress deletes the plugin. */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$bloglogistics_cfm_cleanup = static function (): void {
	delete_option( 'bloglogistics_cfm_connection' );
	delete_option( 'bloglogistics_cfm_last_purge' );
	delete_option( 'bloglogistics_cfm_action_lock' );
	delete_option( 'external_updates-bloglogistics-cloudflare-manager' );
	wp_clear_scheduled_hook( 'puc_cron_check_updates-bloglogistics-cloudflare-manager' );
};

if ( is_multisite() ) {
	// Paginate rather than loading an entire network into memory.
	$bloglogistics_cfm_offset = 0;
	do {
		$bloglogistics_cfm_sites = get_sites( array( 'fields' => 'ids', 'number' => 100, 'offset' => $bloglogistics_cfm_offset ) );
		foreach ( $bloglogistics_cfm_sites as $bloglogistics_cfm_site ) {
			switch_to_blog( $bloglogistics_cfm_site );
			$bloglogistics_cfm_cleanup();
			restore_current_blog();
		}
		$bloglogistics_cfm_offset += 100;
	} while ( count( $bloglogistics_cfm_sites ) === 100 );
} else {
	$bloglogistics_cfm_cleanup();
}

// Plugin Update Checker stores its update state in the network option on multisite.
delete_site_option( 'external_updates-bloglogistics-cloudflare-manager' );

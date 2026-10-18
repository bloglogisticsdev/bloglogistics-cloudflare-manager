<?php
/** Admin integration and authenticated action handlers. */

namespace BlogLogistics\CloudflareManager;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	public const OPTION = 'bloglogistics_cfm_connection';
	public const PURGE_OPTION = 'bloglogistics_cfm_last_purge';
	public const LOCK = 'bloglogistics_cfm_action_lock';
	private string $page_hook = '';

	public function __construct() {
		// Run after normal menu registration so an existing BlogLogistics parent wins.
		add_action( 'admin_menu', array( $this, 'menu' ), PHP_INT_MAX );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_ajax_bloglogistics_cfm', array( $this, 'ajax' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( BLOGLOGISTICS_CFM_FILE ), array( $this, 'links' ) );
	}

	public function menu(): void {
		global $menu;
		$parent = '';
		foreach ( (array) $menu as $item ) {
			if ( 'bloglogistics' === ( $item[2] ?? '' ) || 'bloglogistics' === strtolower( trim( wp_strip_all_tags( $item[0] ?? '' ) ) ) ) {
				$parent = $item[2];
				break;
			}
		}
		if ( '' === $parent ) {
			$parent = 'bloglogistics';
			add_menu_page( 'BlogLogistics', 'BlogLogistics', 'manage_options', $parent, array( $this, 'parent_page' ), 'dashicons-rss', 58 );
		}
		$this->page_hook = add_submenu_page( $parent, 'BlogLogistics Cloudflare Manager', __( 'Cloudflare Manager', 'bloglogistics-cloudflare-manager' ), 'manage_options', BLOGLOGISTICS_CFM_SLUG, array( $this, 'render' ) );
	}

	public function parent_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap"><h1>BlogLogistics</h1><p>' . esc_html__( 'Use the BlogLogistics submenu items to manage your installed plugins.', 'bloglogistics-cloudflare-manager' ) . '</p></div>';
	}

	public function links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . BLOGLOGISTICS_CFM_SLUG ) ) . '">' . esc_html__( 'Settings', 'bloglogistics-cloudflare-manager' ) . '</a>' );
		return $links;
	}

	public function assets( string $hook ): void {
		if ( '' === $this->page_hook || $hook !== $this->page_hook ) {
			return;
		}
		$base = plugin_dir_url( BLOGLOGISTICS_CFM_FILE );
		wp_enqueue_style( BLOGLOGISTICS_CFM_SLUG, $base . 'assets/admin.css', array(), BLOGLOGISTICS_CFM_VERSION );
		wp_enqueue_script( BLOGLOGISTICS_CFM_SLUG, $base . 'assets/admin.js', array(), BLOGLOGISTICS_CFM_VERSION, true );
		wp_localize_script( BLOGLOGISTICS_CFM_SLUG, 'BlogLogisticsCFM', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'bloglogistics_cfm' ),
			'working' => __( 'Working…', 'bloglogistics-cloudflare-manager' ),
			'networkError' => __( 'The result could not be received. The action may have completed. Refresh the connection or Development Mode status before repeating an action.', 'bloglogistics-cloudflare-manager' ),
			'on' => __( 'Turn on Development Mode', 'bloglogistics-cloudflare-manager' ),
			'off' => __( 'Turn off Development Mode', 'bloglogistics-cloudflare-manager' ),
			'unknown' => __( 'Check Development Mode status first', 'bloglogistics-cloudflare-manager' ),
			'mode' => __( 'Current mode:', 'bloglogistics-cloudflare-manager' ),
			'seconds' => __( 'seconds remaining', 'bloglogistics-cloudflare-manager' ),
			'estimate' => __( 'Time is estimated from the last Cloudflare response. Refresh to confirm automatic expiry.', 'bloglogistics-cloudflare-manager' ),
			'unavailable' => __( 'Time remaining was not provided by Cloudflare.', 'bloglogistics-cloudflare-manager' ),
			'expired' => __( 'Automatic expiry is due. Refresh status to confirm.', 'bloglogistics-cloudflare-manager' ),
			'disconnectedDomainSummary' => sprintf( __( 'Cloudflare domain: %1$s. WordPress site host: %2$s.', 'bloglogistics-cloudflare-manager' ), __( 'Not detected', 'bloglogistics-cloudflare-manager' ), Cloudflare_API::host() ),
		) );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$connection = get_option( self::OPTION, array() );
		$connection = is_array( $connection ) ? $connection : array();
		$has_token = ! empty( $connection['token'] );
		$host = Cloudflare_API::host();
		$domain = ! empty( $connection['zone_name'] ) ? $connection['zone_name'] : preg_replace( '/^www\./', '', $host );
		$purge = $this->last_purge();
		require BLOGLOGISTICS_CFM_DIR . 'includes/admin-page.php';
	}

	private function last_purge(): string {
		$purge = get_option( self::PURGE_OPTION, array() );
		if ( ! is_array( $purge ) || empty( $purge['time'] ) || ! is_numeric( $purge['time'] ) ) {
			return __( 'No successful Cloudflare purge has been recorded by this plugin.', 'bloglogistics-cloudflare-manager' );
		}
		$scope = 'zone' === ( $purge['scope'] ?? '' ) ? __( 'Entire zone', 'bloglogistics-cloudflare-manager' ) : sprintf( __( '%d selected URL(s)', 'bloglogistics-cloudflare-manager' ), (int) ( $purge['count'] ?? 0 ) );
		return sprintf( __( 'Cloudflare accepted the purge request. %1$s. %2$s. Zone: %3$s.', 'bloglogistics-cloudflare-manager' ), $scope, wp_date( 'Y-m-d H:i:s T', (int) $purge['time'] ), $purge['zone_name'] ?? '' );
	}

	/** Returns credentials only to the server-side caller, never to JSON or templates. */
	private function connection(): array|\WP_Error {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		if ( ! is_string( $saved['token'] ?? null ) || '' === $saved['token'] || ! is_string( $saved['zone_id'] ?? null ) ||
			! preg_match( '/^[a-f0-9]{32}$/i', $saved['zone_id'] ) || ! is_string( $saved['zone_name'] ?? null ) ) {
			return new \WP_Error( 'connection', __( 'Connect to Cloudflare first.', 'bloglogistics-cloudflare-manager' ) );
		}
		$zone_name = strtolower( $saved['zone_name'] ?? '' );
		$host = Cloudflare_API::host();
		if ( ( $saved['host'] ?? '' ) !== $host || '' === $zone_name || ( $host !== $zone_name && ! str_ends_with( $host, '.' . $zone_name ) ) ) {
			return new \WP_Error( 'connection', __( 'The WordPress site host has changed or no longer matches the saved zone. Reconnect to detect the correct zone before making changes.', 'bloglogistics-cloudflare-manager' ) );
		}
		$token = Cloudflare_API::decrypt( $saved['token'] );
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$saved['token'] = $token;
		return $saved;
	}

	private function zone_details( array $zone ): array {
		return array(
			'zone' => (string) ( $zone['name'] ?? '' ), 'id' => (string) ( $zone['id'] ?? '' ),
			'host' => Cloudflare_API::host(), 'status' => (string) ( $zone['status'] ?? 'unknown' ),
		);
	}

	/** Shared dispatch also makes security and API behaviour independently testable. */
	public function dispatch( string $operation, array $input ): array|\WP_Error {
		if ( 'disconnect' === $operation ) {
			if ( '1' !== ( $input['confirm'] ?? '' ) ) {
				return new \WP_Error( 'confirm', __( 'Tick the confirmation checkbox before disconnecting.', 'bloglogistics-cloudflare-manager' ) );
			}
			delete_option( self::OPTION );
			if ( false !== get_option( self::OPTION, false ) ) {
				return new \WP_Error( 'storage', __( 'WordPress could not delete the saved Cloudflare connection. The token is still saved. Please try again after checking database access.', 'bloglogistics-cloudflare-manager' ) );
			}
			return array( 'message' => __( 'Cloudflare disconnected. This plugin’s saved token was deleted. The token has not been revoked at Cloudflare.', 'bloglogistics-cloudflare-manager' ), 'connected' => false );
		}
		if ( 'connect' === $operation ) {
			$token = trim( $input['token'] ?? '' );
			// Permit legacy and new prefixed tokens, without assuming a fixed length.
			if ( strlen( $token ) < 20 || strlen( $token ) > 512 || ! preg_match( '/^[A-Za-z0-9_\-]+$/D', $token ) ) {
				return new \WP_Error( 'token', __( 'Paste a complete user API token, without Bearer, quotes, or spaces.', 'bloglogistics-cloudflare-manager' ) );
			}
			$encrypted = Cloudflare_API::encrypt( $token );
			if ( is_wp_error( $encrypted ) ) {
				return $encrypted;
			}
			$verified = Cloudflare_API::request( $token, '/user/tokens/verify' );
			if ( is_wp_error( $verified ) ) {
				return $verified;
			}
			if ( 'active' !== ( $verified['status'] ?? '' ) ) {
				return new \WP_Error( 'token', __( 'The user API token is not active.', 'bloglogistics-cloudflare-manager' ) );
			}
			$zone = Cloudflare_API::discover( $token );
			if ( is_wp_error( $zone ) ) {
				return $zone;
			}
			$saved = array( 'token' => $encrypted, 'zone_id' => $zone['id'], 'zone_name' => strtolower( $zone['name'] ), 'host' => Cloudflare_API::host() );
			update_option( self::OPTION, $saved, false );
			if ( get_option( self::OPTION ) !== $saved ) {
				return new \WP_Error( 'storage', __( 'WordPress could not save the connection. Check database access before retrying.', 'bloglogistics-cloudflare-manager' ) );
			}
			return array( 'message' => __( 'Connected to Cloudflare. The token is active and zone access was verified. Cache Purge permission is checked when you submit a purge.', 'bloglogistics-cloudflare-manager' ), 'connected' => true, 'details' => $this->zone_details( $zone ), 'lastPurge' => $this->last_purge() );
		}
		if ( 'check_url' === $operation ) {
			$url = Cloudflare_API::site_url( $input['url'] ?? '' );
			if ( is_wp_error( $url ) ) {
				return $url;
			}
			// No cookies, API token, redirect following, or cache-busting query string.
			$response = wp_safe_remote_get( $url, array( 'timeout' => 15, 'redirection' => 0, 'sslverify' => true, 'limit_response_size' => 1024, 'cookies' => array(), 'headers' => array( 'User-Agent' => 'BlogLogistics-Cloudflare-Manager/' . BLOGLOGISTICS_CFM_VERSION ) ) );
			if ( is_wp_error( $response ) ) {
				return new \WP_Error( 'check', __( 'This server could not safely fetch that public site URL. Check DNS, HTTPS, firewall rules, and whether the site uses a public IP address.', 'bloglogistics-cloudflare-manager' ) );
			}
			$headers = array();
			foreach ( array( 'cf-cache-status', 'cf-ray', 'age', 'cache-control', 'server', 'location', 'vary' ) as $header ) {
				$value = wp_remote_retrieve_header( $response, $header );
				$headers[ $header ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
			}
			return array( 'message' => __( 'Response inspected from this server. Redirects were not followed. This result does not verify API connectivity or every Cloudflare location.', 'bloglogistics-cloudflare-manager' ), 'inspection' => array( 'url' => $url, 'http' => wp_remote_retrieve_response_code( $response ), 'headers' => $headers ) );
		}
		$saved = $this->connection();
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$path = '/zones/' . $saved['zone_id'];
		if ( 'status' === $operation ) {
			$zone = Cloudflare_API::request( $saved['token'], $path );
			if ( is_wp_error( $zone ) ) {
				return $zone;
			}
			if ( ( $zone['id'] ?? '' ) !== $saved['zone_id'] || strtolower( $zone['name'] ?? '' ) !== $saved['zone_name'] ) {
				return new \WP_Error( 'zone', __( 'Cloudflare zone details no longer match. Reconnect before making changes.', 'bloglogistics-cloudflare-manager' ) );
			}
			return array( 'message' => __( 'Cloudflare connection verified. Zone access is available.', 'bloglogistics-cloudflare-manager' ), 'connected' => true, 'details' => $this->zone_details( $zone ), 'lastPurge' => $this->last_purge() );
		}
		if ( 'purge_urls' === $operation || 'purge_all' === $operation ) {
			if ( 'purge_all' === $operation ) {
				if ( '1' !== ( $input['confirm'] ?? '' ) ) {
					return new \WP_Error( 'confirm', __( 'Tick the confirmation checkbox before clearing the entire zone cache.', 'bloglogistics-cloudflare-manager' ) );
				}
				$body = array( 'purge_everything' => true );
			} else {
				$urls = Cloudflare_API::urls( $input['urls'] ?? '' );
				if ( is_wp_error( $urls ) ) {
					return $urls;
				}
				$body = array( 'files' => $urls );
			}
			$result = Cloudflare_API::request( $saved['token'], $path . '/purge_cache', 'POST', $body );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$record = array( 'time' => time(), 'scope' => 'purge_all' === $operation ? 'zone' : 'urls', 'count' => isset( $urls ) ? count( $urls ) : 0, 'zone_name' => $saved['zone_name'] );
			update_option( self::PURGE_OPTION, $record, false );
			if ( get_option( self::PURGE_OPTION ) !== $record ) {
				return array( 'message' => __( 'Cloudflare accepted the cache purge request, but WordPress could not save its timestamp. The last-purge record below has not been updated. Do not repeat the purge just to update the record.', 'bloglogistics-cloudflare-manager' ) );
			}
			return array( 'message' => __( 'Cloudflare accepted the cache purge request. This does not prove the requested content was previously cached. Check an affected URL if needed.', 'bloglogistics-cloudflare-manager' ), 'lastPurge' => $this->last_purge() );
		}
		if ( 'development_get' === $operation || 'development_set' === $operation ) {
			if ( 'development_set' === $operation && ! in_array( $input['value'] ?? '', array( 'on', 'off' ), true ) ) {
				return new \WP_Error( 'development', __( 'Choose on or off for Development Mode.', 'bloglogistics-cloudflare-manager' ) );
			}
			$result = Cloudflare_API::request( $saved['token'], $path . '/settings/development_mode', 'development_set' === $operation ? 'PATCH' : 'GET', 'development_set' === $operation ? array( 'value' => $input['value'] ) : null );
			if ( is_wp_error( $result ) ) {
				return new \WP_Error( 'development', $result->get_error_message() . ' ' . __( 'Development Mode needs optional Zone → Zone Settings → Edit permission. Other cache controls can still be used.', 'bloglogistics-cloudflare-manager' ) );
			}
			$development = Cloudflare_API::development( $result );
			if ( is_wp_error( $development ) ) {
				return $development;
			}
			return array( 'message' => __( 'Development Mode status received from Cloudflare.', 'bloglogistics-cloudflare-manager' ), 'development' => $development );
		}
		return new \WP_Error( 'operation', __( 'Unknown action.', 'bloglogistics-cloudflare-manager' ) );
	}

	/** Acquire atomically at the database level, independent of an object cache. */
	private function acquire_lock(): array|\WP_Error {
		global $wpdb;
		$lock = array( 'owner' => wp_generate_uuid4(), 'expires' => time() + 600 );
		$value = maybe_serialize( $lock );
		$insert = $wpdb->prepare(
			"INSERT IGNORE INTO `{$wpdb->options}` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, %s)",
			self::LOCK, $value, 'off'
		);
		$inserted = $wpdb->query( $insert );
		if ( 0 === $inserted ) {
			$stored = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM `{$wpdb->options}` WHERE option_name = %s", self::LOCK ) );
			$existing = maybe_unserialize( $stored );
			if ( null !== $stored && ( ! is_array( $existing ) || ( $existing['expires'] ?? 0 ) < time() ) ) {
				// A compare-and-delete cannot remove a newer request's replacement lock.
				$wpdb->query( $wpdb->prepare( "DELETE FROM `{$wpdb->options}` WHERE option_name = %s AND option_value = %s", self::LOCK, $stored ) );
				$inserted = $wpdb->query( $insert );
			}
		}
		if ( 1 === $inserted ) {
			wp_cache_delete( self::LOCK, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
			return $lock;
		}
		return new \WP_Error( 'lock', false === $inserted
			? __( 'WordPress could not start the Cloudflare action. Check database access and try again.', 'bloglogistics-cloudflare-manager' )
			: __( 'Another Cloudflare action is running. Wait for it to finish before trying again.', 'bloglogistics-cloudflare-manager' ), array( 'status' => false === $inserted ? 503 : 409 ) );
	}

	private function release_lock( array $lock ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$wpdb->options}` WHERE option_name = %s AND option_value = %s", self::LOCK, maybe_serialize( $lock ) ) );
		wp_cache_delete( self::LOCK, 'options' );
	}

	public function ajax(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage Cloudflare.', 'bloglogistics-cloudflare-manager' ) ), 403 );
			return;
		}
		if ( false === check_ajax_referer( 'bloglogistics_cfm', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your security session has expired. Reload this settings page and try again.', 'bloglogistics-cloudflare-manager' ) ), 403 );
			return;
		}
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_send_json_error( array( 'message' => __( 'Use a POST request for this action.', 'bloglogistics-cloudflare-manager' ) ), 405 );
			return;
		}
		$input = array();
		foreach ( array( 'operation', 'token', 'confirm', 'url', 'urls', 'value' ) as $key ) {
			$input[ $key ] = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
		}
		if ( ! in_array( $input['operation'], array( 'connect', 'disconnect', 'status', 'check_url', 'purge_urls', 'purge_all', 'development_get', 'development_set' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown action.', 'bloglogistics-cloudflare-manager' ) ), 400 );
			return;
		}
		$lock = $this->acquire_lock();
		if ( is_wp_error( $lock ) ) {
			wp_send_json_error( array( 'message' => $lock->get_error_message() ), (int) $lock->get_error_data()['status'] );
			return;
		}
		try {
			$result = $this->dispatch( $input['operation'], $input );
		} catch ( \Throwable $e ) {
			$result = new \WP_Error( 'unexpected', __( 'The action could not be completed. Refresh its status before trying again.', 'bloglogistics-cloudflare-manager' ) );
		} finally {
			$this->release_lock( $lock );
		}
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		} else {
			wp_send_json_success( $result );
		}
	}
}

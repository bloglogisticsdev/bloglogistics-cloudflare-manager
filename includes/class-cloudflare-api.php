<?php
/** Cloudflare transport, token encryption, and site URL validation. */

namespace BlogLogistics\CloudflareManager;

defined( 'ABSPATH' ) || exit;

final class Cloudflare_API {
	private const BASE = 'https://api.cloudflare.com/client/v4';

	public static function host(): string {
		return strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
	}

	/** Only allow the saved WordPress site host, never sibling subdomains. */
	public static function site_url( string $input ): string|\WP_Error {
		$input = trim( $input );
		if ( '' === $input || strlen( $input ) > 4096 || preg_match( '/[\x00-\x20\x7f\\\\]/', $input ) ) {
			return new \WP_Error( 'url', __( 'Enter a valid URL or a site path beginning with /. Spaces must be URL encoded.', 'bloglogistics-cloudflare-manager' ) );
		}
		if ( str_starts_with( $input, '//' ) ) {
			return new \WP_Error( 'url', __( 'Use a full http:// or https:// URL, or a site path beginning with a single /.', 'bloglogistics-cloudflare-manager' ) );
		}
		$home = wp_parse_url( home_url( '/' ) );
		if ( str_starts_with( $input, '/' ) ) {
			$input = $home['scheme'] . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' ) . $input;
		}
		$url = wp_parse_url( $input );
		if ( ! is_array( $url ) || ! in_array( strtolower( $url['scheme'] ?? '' ), array( 'https', 'http' ), true ) ||
			strtolower( $url['host'] ?? '' ) !== self::host() || isset( $url['user'] ) || isset( $url['pass'] ) || isset( $url['fragment'] ) ) {
			return new \WP_Error( 'url', __( 'Only this WordPress site host is accepted. Remove login credentials and #fragments from URLs.', 'bloglogistics-cloudflare-manager' ) );
		}
		$port = $url['port'] ?? ( 'https' === strtolower( $url['scheme'] ) ? 443 : 80 );
		$allowed_ports = isset( $home['port'] ) ? array( (int) $home['port'] ) : array( 80, 443 );
		if ( ! in_array( $port, $allowed_ports, true ) || ! filter_var( $input, FILTER_VALIDATE_URL ) ) {
			return new \WP_Error( 'url', __( 'The URL or port is not valid for this site.', 'bloglogistics-cloudflare-manager' ) );
		}
		// Normalise only the hostname and scheme. Preserve path case and query order.
		return strtolower( $url['scheme'] ) . '://' . self::host() . ( isset( $url['port'] ) ? ':' . $url['port'] : '' ) .
			( $url['path'] ?? '/' ) . ( isset( $url['query'] ) ? '?' . $url['query'] : '' );
	}

	/** Validate the whole batch before making any purge request. */
	public static function urls( string $input ): array|\WP_Error {
		if ( strlen( $input ) > 125000 ) {
			return new \WP_Error( 'urls', __( 'The URL list is too large. Enter up to 30 URLs.', 'bloglogistics-cloudflare-manager' ) );
		}
		$lines = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $input ) ), static fn( $s ) => '' !== $s ) );
		if ( count( $lines ) < 1 || count( $lines ) > 30 ) {
			return new \WP_Error( 'urls', __( 'Enter between 1 and 30 URLs or site paths, one per line.', 'bloglogistics-cloudflare-manager' ) );
		}
		$result = array();
		foreach ( $lines as $line ) {
			$url = self::site_url( $line );
			if ( is_wp_error( $url ) ) {
				return $url;
			}
			$result[] = $url;
		}
		return array_values( array_unique( $result ) );
	}

	private static function key(): string {
		return hash( 'sha256', wp_salt( 'auth' ) . wp_salt( 'secure_auth' ) . '|bloglogistics-cfm|' . get_current_blog_id(), true );
	}

	public static function encrypt( string $token ): string|\WP_Error {
		try {
			if ( function_exists( 'sodium_crypto_secretbox' ) ) {
				$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
				return 's1:' . base64_encode( $nonce . sodium_crypto_secretbox( $token, $nonce, self::key() ) );
			}
			if ( function_exists( 'openssl_encrypt' ) ) {
				$nonce = random_bytes( 12 );
				$tag = '';
				$encrypted = openssl_encrypt( $token, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $nonce, $tag );
				if ( false !== $encrypted ) {
					return 'o1:' . base64_encode( $nonce . $tag . $encrypted );
				}
			}
		} catch ( \Throwable $e ) {
			// Never log credentials or crypto internals.
		}
		return new \WP_Error( 'crypto', __( 'Secure token storage is unavailable. Ask your host to enable PHP Sodium or OpenSSL.', 'bloglogistics-cloudflare-manager' ) );
	}

	public static function decrypt( string $value ): string|\WP_Error {
		$bytes = base64_decode( substr( $value, 3 ), true );
		try {
			if ( false !== $bytes && str_starts_with( $value, 's1:' ) && strlen( $bytes ) > 24 && function_exists( 'sodium_crypto_secretbox_open' ) ) {
				$plain = sodium_crypto_secretbox_open( substr( $bytes, 24 ), substr( $bytes, 0, 24 ), self::key() );
			} elseif ( false !== $bytes && str_starts_with( $value, 'o1:' ) && strlen( $bytes ) > 28 && function_exists( 'openssl_decrypt' ) ) {
				$plain = openssl_decrypt( substr( $bytes, 28 ), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr( $bytes, 0, 12 ), substr( $bytes, 12, 16 ) );
			}
			if ( isset( $plain ) && is_string( $plain ) && '' !== $plain ) {
				return $plain;
			}
		} catch ( \Throwable $e ) {
			// A change to WordPress salts invalidates the saved encrypted token.
		}
		return new \WP_Error( 'crypto', __( 'The saved token cannot be unlocked. Reconnect using your token. WordPress security salts or PHP encryption support may have changed.', 'bloglogistics-cloudflare-manager' ) );
	}

	/** Fixed API origin, no redirects, TLS verification, and no credential-bearing errors. */
	public static function request( string $token, string $path, string $method = 'GET', ?array $body = null ): array|\WP_Error {
		$args = array(
			'method' => $method, 'timeout' => 15, 'redirection' => 0, 'sslverify' => true,
			'limit_response_size' => 1048576,
			'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json' ),
		);
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body'] = wp_json_encode( $body );
		}
		$response = wp_remote_request( self::BASE . $path, $args );
		if ( is_wp_error( $response ) ) {
			$message = 'GET' === $method
				? __( 'Cloudflare could not be reached. Check this server’s outbound HTTPS access and try again.', 'bloglogistics-cloudflare-manager' )
				: __( 'The Cloudflare response was not received. The action may have completed. Refresh the relevant status before repeating it.', 'bloglogistics-cloudflare-manager' );
			return new \WP_Error( 'transport', $message );
		}
		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) || true !== ( $data['success'] ?? false ) || ! is_array( $data['result'] ?? null ) ) {
			$message = match ( $code ) {
				401, 403 => __( 'Cloudflare rejected this request. Check the token, its permissions, expiry, IP restrictions, and access to this specific zone.', 'bloglogistics-cloudflare-manager' ),
				429 => __( 'Cloudflare’s rate limit was reached. Wait before trying again.', 'bloglogistics-cloudflare-manager' ),
				default => sprintf( __( 'Cloudflare did not accept the request (HTTP %d). Check its API status and token permissions, then try again.', 'bloglogistics-cloudflare-manager' ), $code ),
			};
			return new \WP_Error( 'cloudflare', $message, array( 'status' => $code ) );
		}
		return $data['result'];
	}

	public static function discover( string $token ): array|\WP_Error {
		$labels = explode( '.', self::host() );
		if ( count( $labels ) < 2 || filter_var( self::host(), FILTER_VALIDATE_IP ) ) {
			return new \WP_Error( 'zone', __( 'Set a public domain in WordPress Settings → General → Site Address before connecting.', 'bloglogistics-cloudflare-manager' ) );
		}
		// Exact name filters avoid unrelated zones and pagination ambiguity. Prefer a subdomain zone.
		while ( count( $labels ) >= 2 ) {
			$name = implode( '.', $labels );
			$zones = self::request( $token, '/zones?name=' . rawurlencode( $name ) . '&per_page=50' );
			if ( is_wp_error( $zones ) ) {
				return $zones;
			}
			foreach ( $zones as $zone ) {
				if ( is_array( $zone ) && strtolower( $zone['name'] ?? '' ) === $name && preg_match( '/^[a-f0-9]{32}$/i', $zone['id'] ?? '' ) ) {
					return $zone;
				}
			}
			array_shift( $labels );
		}
		return new \WP_Error( 'zone', __( 'No matching Cloudflare zone was found. Grant Zone → Zone → Read and restrict the token to the zone containing this site.', 'bloglogistics-cloudflare-manager' ) );
	}

	public static function development( array $result ): array|\WP_Error {
		if ( ! in_array( $result['value'] ?? '', array( 'on', 'off' ), true ) ) {
			return new \WP_Error( 'development', __( 'Cloudflare returned an unexpected Development Mode response. Refresh its status before trying again.', 'bloglogistics-cloudflare-manager' ) );
		}
		return array(
			'value' => $result['value'],
			'remaining' => 'on' === $result['value'] && isset( $result['time_remaining'] ) && is_numeric( $result['time_remaining'] ) ? max( 0, (int) $result['time_remaining'] ) : null,
			'editable' => false !== ( $result['editable'] ?? true ),
		);
	}
}

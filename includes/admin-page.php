<?php
/** Settings page. All token values stay server-side. */
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap blcfm" id="blcfm" data-saved="<?php echo $has_token ? '1' : '0'; ?>">
	<h1>BlogLogistics Cloudflare Manager</h1>
	<p class="blcfm-intro"><?php esc_html_e( 'Manage Cloudflare cache for this WordPress site. Connect a user API token restricted to the correct zone to get started.', 'bloglogistics-cloudflare-manager' ); ?></p>
	<noscript><div class="notice notice-error inline"><p><?php esc_html_e( 'Enable JavaScript to use the Cloudflare controls. No actions are submitted without JavaScript.', 'bloglogistics-cloudflare-manager' ); ?></p></div></noscript>
	<div class="blcfm-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Cloudflare settings', 'bloglogistics-cloudflare-manager' ); ?>">
		<button type="button" class="blcfm-tab" id="blcfm-tab-purge" role="tab" aria-controls="blcfm-purge" aria-selected="true" tabindex="0" data-blcfm-tab="purge"><?php esc_html_e( 'Clear cache', 'bloglogistics-cloudflare-manager' ); ?></button>
		<button type="button" class="blcfm-tab" id="blcfm-tab-development" role="tab" aria-controls="blcfm-development" aria-selected="false" tabindex="-1" data-blcfm-tab="development"><?php esc_html_e( 'Development mode', 'bloglogistics-cloudflare-manager' ); ?></button>
		<button type="button" class="blcfm-tab" id="blcfm-tab-inspect" role="tab" aria-controls="blcfm-inspect" aria-selected="false" tabindex="-1" data-blcfm-tab="inspect"><?php esc_html_e( 'Check a URL', 'bloglogistics-cloudflare-manager' ); ?></button>
		<button type="button" class="blcfm-tab" id="blcfm-tab-connection" role="tab" aria-controls="blcfm-connection" aria-selected="false" tabindex="-1" data-blcfm-tab="connection"><?php esc_html_e( 'Connection', 'bloglogistics-cloudflare-manager' ); ?></button>
	</div>
	<p id="blcfm-connection-hint" class="blcfm-connection-hint"><?php esc_html_e( 'Use the Connection tab to connect to Cloudflare or refresh its status. Cache and Development Mode controls become available after the connection is verified.', 'bloglogistics-cloudflare-manager' ); ?></p>
	<section class="blcfm-card" id="blcfm-purge" role="tabpanel" aria-labelledby="blcfm-tab-purge" tabindex="0">
		<h2 id="blcfm-purge-title"><?php esc_html_e( 'Clear Cloudflare cache', 'bloglogistics-cloudflare-manager' ); ?></h2>
		<p class="blcfm-last"><strong><?php esc_html_e( 'Last Cloudflare purge:', 'bloglogistics-cloudflare-manager' ); ?></strong> <span id="blcfm-last-purge"><?php echo esc_html( $purge ); ?></span></p>
		<h3><?php esc_html_e( 'Clear selected URLs at Cloudflare', 'bloglogistics-cloudflare-manager' ); ?></h3>
		<form method="post" data-operation="purge_urls">
			<label for="blcfm-urls"><?php esc_html_e( 'Enter one URL or site path per line, up to 30. Only this site’s URLs are accepted.', 'bloglogistics-cloudflare-manager' ); ?></label>
			<textarea id="blcfm-urls" name="urls" rows="6" class="large-text code" maxlength="125000" required placeholder="<?php echo esc_attr( home_url( '/' ) . "\n/example-page/\n/wp-content/uploads/example.jpg" ); ?>" aria-describedby="blcfm-urls-help"></textarea>
			<p class="description" id="blcfm-urls-help"><?php esc_html_e( 'Paths begin at the domain root. Query strings are preserved. For custom cache keys containing request headers or Worker-managed caches, use Cloudflare’s matching purge controls.', 'bloglogistics-cloudflare-manager' ); ?></p>
			<p><button class="button button-primary" type="submit" disabled data-needs-connection><?php esc_html_e( 'Clear these Cloudflare URLs', 'bloglogistics-cloudflare-manager' ); ?></button></p>
			<div class="blcfm-result" role="status" aria-live="polite" tabindex="-1"></div>
		</form>
		<div class="blcfm-divider"></div>
		<h3><?php esc_html_e( 'Clear the entire Cloudflare cache', 'bloglogistics-cloudflare-manager' ); ?></h3>
		<p class="blcfm-warning"><?php esc_html_e( 'This affects all cached content in the detected zone, including other subdomains. Prefer clearing selected URLs when possible.', 'bloglogistics-cloudflare-manager' ); ?></p>
		<form method="post" data-operation="purge_all">
			<p><label><input type="checkbox" name="confirm" value="1" required> <?php esc_html_e( 'I understand the entire zone cache will be cleared.', 'bloglogistics-cloudflare-manager' ); ?></label></p>
			<button class="button blcfm-danger" type="submit" disabled data-needs-connection><?php esc_html_e( 'Clear entire Cloudflare cache', 'bloglogistics-cloudflare-manager' ); ?></button>
			<div class="blcfm-result" role="status" aria-live="polite" tabindex="-1"></div>
		</form>
	</section>
	<section class="blcfm-card" id="blcfm-development" role="tabpanel" aria-labelledby="blcfm-tab-development" tabindex="0" hidden>
		<h2 id="blcfm-development-title"><?php esc_html_e( 'Development Mode', 'bloglogistics-cloudflare-manager' ); ?></h2>
		<p><?php esc_html_e( 'Needs the optional Zone Settings Edit permission for a user API token. Cloudflare turns it off automatically after three hours. This bypasses its edge cache temporarily; it does not clear cached files. The setting affects the entire zone, including other subdomains.', 'bloglogistics-cloudflare-manager' ); ?></p>
		<p id="blcfm-development-status" role="status"><?php esc_html_e( 'Current mode: unknown. Connect and check the status.', 'bloglogistics-cloudflare-manager' ); ?></p>
		<form method="post" data-operation="development_set" id="blcfm-development-set">
			<input type="hidden" name="value" value="">
			<button class="button button-primary" type="submit" disabled data-needs-development><?php esc_html_e( 'Check Development Mode status first', 'bloglogistics-cloudflare-manager' ); ?></button>
			<div class="blcfm-result" role="status" aria-live="polite" tabindex="-1"></div>
		</form>
		<form method="post" data-operation="development_get" id="blcfm-development-get">
			<button class="button" type="submit" disabled data-needs-connection><?php esc_html_e( 'Check Development Mode time remaining', 'bloglogistics-cloudflare-manager' ); ?></button>
			<div class="blcfm-result" role="status" aria-live="polite" tabindex="-1"></div>
		</form>
	</section>
	<section class="blcfm-card" id="blcfm-inspect" role="tabpanel" aria-labelledby="blcfm-tab-inspect" tabindex="0" hidden>
		<h2 id="blcfm-inspect-title"><?php esc_html_e( 'Check one URL at Cloudflare', 'bloglogistics-cloudflare-manager' ); ?></h2>
		<p><?php esc_html_e( 'This inspects one response from this server. A missing header or DYNAMIC result does not mean the connection failed. No login cookies are sent, and redirects are shown without being followed.', 'bloglogistics-cloudflare-manager' ); ?></p>
		<form method="post" data-operation="check_url">
			<label for="blcfm-check-url"><?php esc_html_e( 'URL or site path', 'bloglogistics-cloudflare-manager' ); ?></label>
			<input class="large-text" id="blcfm-check-url" type="text" name="url" maxlength="4096" required value="<?php echo esc_attr( home_url( '/' ) ); ?>">
			<p><button class="button" type="submit" disabled><?php esc_html_e( 'Check URL', 'bloglogistics-cloudflare-manager' ); ?></button></p>
			<div class="blcfm-result" role="status" aria-live="polite" tabindex="-1"></div>
			<div id="blcfm-inspection" hidden></div>
		</form>
	</section>
	<section class="blcfm-card" id="blcfm-connection" role="tabpanel" aria-labelledby="blcfm-tab-connection" tabindex="0" hidden>
		<h2 id="blcfm-connection-title"><?php esc_html_e( 'Cloudflare connection', 'bloglogistics-cloudflare-manager' ); ?></h2>
		<div id="blcfm-status" class="blcfm-status blcfm-status-error" role="status"><?php echo $has_token ? esc_html__( 'Saved token found. Checking Cloudflare connection…', 'bloglogistics-cloudflare-manager' ) : esc_html__( 'Not connected to Cloudflare.', 'bloglogistics-cloudflare-manager' ); ?></div>
		<p id="blcfm-zone-summary"><?php echo esc_html( sprintf( __( 'Cloudflare domain: %1$s. WordPress site host: %2$s.', 'bloglogistics-cloudflare-manager' ), $connection['zone_name'] ?? __( 'Not detected', 'bloglogistics-cloudflare-manager' ), $host ) ); ?></p>
		<form method="post" data-operation="connect" id="blcfm-connect-form">
			<label for="blcfm-token"><strong><?php esc_html_e( 'Cloudflare user API token', 'bloglogistics-cloudflare-manager' ); ?></strong></label>
			<input id="blcfm-token" name="token" type="password" class="regular-text" autocomplete="new-password" spellcheck="false" maxlength="512" required aria-describedby="blcfm-token-help">
			<p id="blcfm-token-help" class="description"><?php esc_html_e( 'The saved token is never displayed here. Keep a secure copy elsewhere. To replace it, paste a new token and connect again. A failed connection attempt keeps the previous saved token.', 'bloglogistics-cloudflare-manager' ); ?></p>
			<p><button class="button button-primary" type="submit" disabled><?php esc_html_e( 'Connect to Cloudflare', 'bloglogistics-cloudflare-manager' ); ?></button></p>
			<div class="blcfm-result" role="status" aria-live="polite" tabindex="-1"></div>
		</form>
		<form method="post" data-operation="status" id="blcfm-status-form">
			<button class="button" type="submit" disabled data-needs-saved><?php esc_html_e( 'Refresh Cloudflare connection', 'bloglogistics-cloudflare-manager' ); ?></button>
			<div class="blcfm-result" role="status" aria-live="polite" tabindex="-1"></div>
		</form>
		<details id="blcfm-zone-details">
			<summary><?php esc_html_e( 'Show Cloudflare zone details', 'bloglogistics-cloudflare-manager' ); ?></summary>
			<dl class="blcfm-details">
				<dt><?php esc_html_e( 'Zone ID', 'bloglogistics-cloudflare-manager' ); ?></dt><dd id="blcfm-zone-id"><?php echo esc_html( $connection['zone_id'] ?? '…' ); ?></dd>
				<dt><?php esc_html_e( 'Zone status', 'bloglogistics-cloudflare-manager' ); ?></dt><dd id="blcfm-zone-status"><?php esc_html_e( 'Refresh connection to check', 'bloglogistics-cloudflare-manager' ); ?></dd>
			</dl>
		</details>
		<details id="blcfm-disconnect" <?php echo $has_token ? '' : 'hidden'; ?>>
			<summary><?php esc_html_e( 'Disconnect Cloudflare', 'bloglogistics-cloudflare-manager' ); ?></summary>
			<p><?php esc_html_e( 'Opening this section does not delete the token. Close it to keep the saved token.', 'bloglogistics-cloudflare-manager' ); ?></p>
			<p><?php esc_html_e( 'Once confirmed, BlogLogistics Cloudflare Manager deletes its saved copy of the API token. You can reconnect with a copy you kept elsewhere; otherwise you will need a new token. This does not revoke the token at Cloudflare.', 'bloglogistics-cloudflare-manager' ); ?></p>
			<form method="post" data-operation="disconnect">
				<p><label><input type="checkbox" name="confirm" value="1" required> <?php esc_html_e( 'I understand the saved Cloudflare token will be permanently deleted.', 'bloglogistics-cloudflare-manager' ); ?></label></p>
				<button class="button blcfm-danger" type="submit" disabled data-needs-saved><?php esc_html_e( 'Confirm disconnect and delete token', 'bloglogistics-cloudflare-manager' ); ?></button>
				<div class="blcfm-result" role="status" aria-live="polite" tabindex="-1"></div>
			</form>
		</details>
	<div class="blcfm-tutorial" id="blcfm-tutorial">
		<details>
			<summary><?php esc_html_e( 'Show Cloudflare setup tutorial', 'bloglogistics-cloudflare-manager' ); ?></summary>
			<p><?php esc_html_e( 'These steps are for a Cloudflare user API token. Dashboard labels may change. Keep the token secret and save a secure copy, Cloudflare displays the secret only once.', 'bloglogistics-cloudflare-manager' ); ?></p>
			<ol class="blcfm-tutorial-steps">
				<li><?php esc_html_e( 'In Cloudflare, open My Profile → API Tokens. Select Create Token.', 'bloglogistics-cloudflare-manager' ); ?></li>
				<li><?php esc_html_e( 'Choose Create Custom Token, then select Get started if that button is shown.', 'bloglogistics-cloudflare-manager' ); ?></li>
				<li><?php esc_html_e( 'For Token name, use this suggested name:', 'bloglogistics-cloudflare-manager' ); ?> <code id="blcfm-token-suggestion"><?php echo esc_html( $domain . ' - BlogLogistics Cloudflare Manager' ); ?></code> <?php esc_html_e( 'The suggestion uses the WordPress Site Address host initially and the detected zone after connection. If needed, use the parent zone containing your site.', 'bloglogistics-cloudflare-manager' ); ?></li>
				<li><?php esc_html_e( 'Under Permissions, choose Zone → Cache Purge → Purge.', 'bloglogistics-cloudflare-manager' ); ?></li>
				<li><?php esc_html_e( 'Select Add more. Choose Zone → Zone → Read. This lets the plugin detect and read this website’s zone.', 'bloglogistics-cloudflare-manager' ); ?></li>
				<li><?php esc_html_e( 'For Development Mode, select Add more again and choose Zone → Zone Settings → Edit. This permission is optional. Without it, cache purging still works, but Development Mode may be unavailable.', 'bloglogistics-cloudflare-manager' ); ?></li>
				<li><?php esc_html_e( 'Under Zone Resources, choose Include → Specific zone and select the Cloudflare zone containing this website. Do not select all zones. Account Resources is not the zone restriction. Leave Client IP Address Filtering and TTL empty unless you specifically need those limits.', 'bloglogistics-cloudflare-manager' ); ?></li>
				<li><?php esc_html_e( 'Select Continue to summary. Review the token name, permissions, and specific zone, then select Create Token.', 'bloglogistics-cloudflare-manager' ); ?></li>
				<li><?php esc_html_e( 'Copy the token to a secure location. Paste it into the Cloudflare user API token field above, then select Connect to Cloudflare. The plugin finds the Zone ID automatically.', 'bloglogistics-cloudflare-manager' ); ?></li>
			</ol>
			<p><a href="https://developers.cloudflare.com/fundamentals/api/get-started/create-token/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Cloudflare’s current token instructions', 'bloglogistics-cloudflare-manager' ); ?></a></p>
		</details>
	</div>
	</section>
</div>

<?php
// === FILE: includes/admin-settings.php ===
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_menu', function() {
	add_options_page(
		'BIMI Checker Settings',
		'BIMI Checker',
		'manage_options',
		'bimi-checker-settings',
		'bimi_checker_render_settings_page'
	);
} );

add_action( 'admin_init', function() {
	register_setting( 'bimi_checker', 'bimi_checker_dns_mode', [
		'type' => 'string', 'sanitize_callback' => function( $v ) {
			return in_array( $v, [ 'system', 'custom' ], true ) ? $v : 'system';
		}
	] );
	register_setting( 'bimi_checker', 'bimi_checker_dns_servers', [
		'type' => 'string', 'sanitize_callback' => 'bimi_checker_sanitize_servers'
	] );
	register_setting( 'bimi_checker', 'bimi_checker_dns_timeout', [
		'type' => 'integer', 'sanitize_callback' => function( $v ) { return max(1, absint($v)); }
	] );
	register_setting( 'bimi_checker', 'bimi_checker_dns_retries', [
		'type' => 'integer', 'sanitize_callback' => function( $v ) { return max(0, absint($v)); }
	] );

	add_settings_section( 'bimi_checker_dns', 'DNS Resolver', '__return_false', 'bimi_checker' );

	add_settings_field( 'bimi_checker_dns_mode', 'Resolver Mode', function() {
		$mode = get_option( 'bimi_checker_dns_mode', 'system' );
		?>
		<label><input type="radio" name="bimi_checker_dns_mode" value="system" <?php checked( $mode, 'system' ); ?>/> Use system resolver (OS / local)</label><br>
		<label><input type="radio" name="bimi_checker_dns_mode" value="custom" <?php checked( $mode, 'custom' ); ?>/> Use custom nameserver(s)</label>
		<p class="description">System mode uses PHP's dns_get_record via your server's /etc/resolv.conf. Custom mode requires the Net_DNS2 library if you want to target specific nameservers.</p>
		<?php
	}, 'bimi_checker', 'bimi_checker_dns' );

	add_settings_field( 'bimi_checker_dns_servers', 'Custom nameserver(s)', function() {
		$val = esc_attr( (string) get_option( 'bimi_checker_dns_servers', '' ) );
		?>
		<input type="text" name="bimi_checker_dns_servers" value="<?php echo $val; ?>" class="regular-text" placeholder="e.g., 127.0.0.1, 8.8.8.8" />
		<p class="description">Comma-separated IPs or hostnames. Leave blank to use system resolver. Example: <code>127.0.0.1, 8.8.8.8</code></p>
		<?php
	}, 'bimi_checker', 'bimi_checker_dns' );

	add_settings_field( 'bimi_checker_dns_timeout', 'DNS timeout (seconds)', function() {
		$val = absint( get_option( 'bimi_checker_dns_timeout', 3 ) );
		?>
		<input type="number" min="1" name="bimi_checker_dns_timeout" value="<?php echo esc_attr( $val ); ?>" />
		<?php
	}, 'bimi_checker', 'bimi_checker_dns' );

	add_settings_field( 'bimi_checker_dns_retries', 'DNS retries', function() {
		$val = absint( get_option( 'bimi_checker_dns_retries', 1 ) );
		?>
		<input type="number" min="0" name="bimi_checker_dns_retries" value="<?php echo esc_attr( $val ); ?>" />
		<?php
	}, 'bimi_checker', 'bimi_checker_dns' );
} );

function bimi_checker_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	?>
	<div class="wrap">
		<h1>BIMI Checker — Settings</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'bimi_checker' ); ?>
			<?php do_settings_sections( 'bimi_checker' ); ?>
			<?php submit_button(); ?>
		</form>
		<hr/>
		<h2>About the Custom DNS Resolver</h2>
		<ol>
			<li><strong>System</strong> mode uses your server's configured resolver (fastest to deploy).</li>
			<li><strong>Custom</strong> mode targets specific nameservers (e.g., <code>127.0.0.1</code> for a local caching resolver, or <code>8.8.8.8</code> for Google Public DNS). This mode requires the <code>Net_DNS2</code> PHP library to be available.</li>
		</ol>
		<p>Tip: If <code>Net_DNS2</code> is not installed, the plugin will automatically fall back to the system resolver.</p>
	</div>
	<?php
}

/** Sanitize a CSV list of servers to "host:port" or host/IP only */
function bimi_checker_sanitize_servers( $val ) {
	$val = (string) $val;
	$list = array_filter( array_map( 'trim', explode( ',', $val ) ) );
	$clean = [];
	foreach ( $list as $part ) {
		// Allow hostnames, IPv4, IPv6 (with optional :port)
		if ( preg_match( '/^([A-Za-z0-9.-]+|\[[0-9A-Fa-f:]+\]|[0-9.]+)(:\d{1,5})?$/', $part ) ) {
			$clean[] = $part;
		}
	}
	return implode( ', ', array_unique( $clean ) );
}
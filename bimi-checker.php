<?php
/**
 * Plugin Name: BIMI Checker
 * Description: Validate BIMI and DMARC settings for a domain. Use shortcode [bimi_checker]. Adds settings to choose DNS resolver(s).
 * Version: 1.0.4
 * Author: Matthew Vernhout / BIMI Group
 * Author URI: https://github.com/EmailKarma
 * Plugin URI: https://github.com/EmailKarma
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'BIMI_CHECKER_PATH', plugin_dir_path( __FILE__ ) );
define( 'BIMI_CHECKER_URL',  plugin_dir_url( __FILE__ ) );

// Guard against missing includes (no output!)
$includes = [
  BIMI_CHECKER_PATH . 'includes/class-bimi-dns-resolver.php',
  BIMI_CHECKER_PATH . 'includes/admin-settings.php',
  BIMI_CHECKER_PATH . 'includes/class-bimi-validator.php',
];
$missing = array_values(array_filter($includes, static function($p){ return ! file_exists($p); }));
if ( $missing ) {
  add_action('admin_notices', static function() use ($missing) {
    echo '<div class="notice notice-error"><p><strong>BIMI Checker:</strong> Missing include(s):<br><code>'
      . esc_html( implode(', ', $missing) )
      . '</code></p></div>';
  });
  return;
}

require_once $includes[0];
require_once $includes[1];
require_once $includes[2];

register_activation_hook( __FILE__, static function() {
  $defaults = [
    'bimi_checker_dns_mode'    => 'system',
    'bimi_checker_dns_servers' => '',
    'bimi_checker_dns_timeout' => 3,
    'bimi_checker_dns_retries' => 1,
  ];
  foreach ( $defaults as $k => $v ) {
    if ( get_option( $k, null ) === null ) {
      update_option( $k, $v );
    }
  }
});

// Front-end assets (restored v1.0.3 look)
add_action( 'wp_enqueue_scripts', static function() {
  wp_register_style( 'bimi-checker', BIMI_CHECKER_URL . 'assets/css/style.css', [], '1.0.4' );
  wp_register_script( 'bimi-checker', BIMI_CHECKER_URL . 'assets/js/bimi.js', [ 'jquery' ], '1.0.4', true );

  // Localize for AJAX ping/error trapping
  wp_localize_script( 'bimi-checker', 'BIMIChecker', [
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    'nonce'   => wp_create_nonce( 'bimi_checker_nonce' ),
  ] );
});

// AJAX ping endpoint (helps identify -1 / 403 issues)
add_action( 'wp_ajax_bimi_checker_ping', 'bimi_checker_ping' );
add_action( 'wp_ajax_nopriv_bimi_checker_ping', 'bimi_checker_ping' );
function bimi_checker_ping() {
  if ( ! isset($_POST['_ajax_nonce']) || ! wp_verify_nonce( $_POST['_ajax_nonce'], 'bimi_checker_nonce' ) ) {
    wp_send_json_error( [ 'message' => 'Invalid or missing nonce. Check caching/security rules blocking admin-ajax.' ], 403 );
  }
  wp_send_json_success( [ 'message' => 'OK' ] );
}

// Template helper
function bimi_checker_render_template( $template_vars = [] ) {
  extract( $template_vars, EXTR_SKIP );
  $tpl = BIMI_CHECKER_PATH . 'templates/form-and-results.php';
  if ( file_exists( $tpl ) ) {
    include $tpl;
  }
}

// Shortcode
add_shortcode( 'bimi_checker', static function( $atts ) {
  $atts      = shortcode_atts( [ 'domain' => '', 'selector' => '' ], $atts );
  $domain    = isset($_GET['bimi_domain'])    ? sanitize_text_field($_GET['bimi_domain'])    : $atts['domain'];
  $selector  = isset($_GET['bimi_selector'])  ? sanitize_text_field($_GET['bimi_selector'])  : $atts['selector'];
  $selector  = $selector !== '' ? $selector : 'default';

  wp_enqueue_style( 'bimi-checker' );
  wp_enqueue_script( 'bimi-checker' );

  $resolver  = BIMI_DNS_Resolver::make_from_settings();
  $validator = new BIMI_Validator( $resolver );

  $result = null;
  if ( $domain ) {
    $result = $validator->check_domain_with_dmarc( $domain, $selector );
  }

  ob_start();
  bimi_checker_render_template([
    'domain'   => $domain,
    'selector' => $selector,
    'result'   => $result,
  ]);
  return ob_get_clean();
});

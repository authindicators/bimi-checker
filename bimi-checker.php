<?php
/**
 * Plugin Name: BIMI Checker
 * Description: Validate BIMI and DMARC settings for a domain. Use shortcode [bimi_checker]. Follow us: https://bsky.app/profile/bimigroup.bsky.social.
 * Version: 1.0.3
 * Author: Matthew Vernhout / BIMI Group
 * Author URI: https://github.com/EmailKarma
 * Plugin URI: https://github.com/pepipost/BIMI-official
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) { exit; }

define('BIMICHECKER_VERSION', '1.0.3');
define('BIMICHECKER_PATH', plugin_dir_path(__FILE__));
define('BIMICHECKER_URL',  plugin_dir_url(__FILE__));

require_once BIMICHECKER_PATH . 'includes/class-dns-utils.php';
require_once BIMICHECKER_PATH . 'includes/class-bimi-parser.php';
require_once BIMICHECKER_PATH . 'includes/class-dmarc-checker.php';
require_once BIMICHECKER_PATH . 'includes/template-render.php';

/**
 * Front-end assets + AJAX config.
 */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('bimi-checker-css', BIMICHECKER_URL . 'assets/css/style.css', [], BIMICHECKER_VERSION);
    wp_enqueue_script('bimi-checker-js', BIMICHECKER_URL . 'assets/js/app.js', ['jquery'], BIMICHECKER_VERSION, true);

    wp_localize_script('bimi-checker-js', 'bimiChecker', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('bimi_checker_nonce'),
    ]);
});

/**
 * Shortcode: [bimi_checker]
 */
add_shortcode('bimi_checker', function () {
    ob_start();
    bimi_checker_render_form();
    return ob_get_clean();
});

/**
 * AJAX endpoints.
 */
add_action('wp_ajax_bimi_checker_check', 'bimi_checker_check_ajax');
add_action('wp_ajax_nopriv_bimi_checker_check', 'bimi_checker_check_ajax');

/**
 * AJAX handler: validates inputs, fetches DNS, evaluates BIMI/DMARC, returns JSON.
 */
function bimi_checker_check_ajax() {
    // Local helpers (safe across PHP versions)
    $find_record = function(array $txts, $marker) {
        foreach ($txts as $t) {
            if (stripos($t, $marker) !== false) return $t;
        }
        return null;
    };
    $parse_tags = function($txt) {
        $tags = [];
        foreach (explode(';', (string)$txt) as $part) {
            $part = trim($part);
            if ($part === '' || strpos($part, '=') === false) continue;
            [$k, $v] = array_map('trim', explode('=', $part, 2));
            $tags[strtolower($k)] = $v;
        }
        return $tags;
    };
    $is_https = function($url) {
        if (!is_string($url) || $url === '') return false;
        $scheme = parse_url($url, PHP_URL_SCHEME);
        return strtolower((string)$scheme) === 'https';
    };

    try {
        check_ajax_referer('bimi_checker_nonce', 'nonce');

        $domain   = isset($_POST['domain']) ? sanitize_text_field($_POST['domain']) : '';
        $selector = isset($_POST['selector']) && $_POST['selector'] !== '' ? sanitize_text_field($_POST['selector']) : 'default';

        if ($domain === '') {
            wp_send_json_error(['message' => 'Please provide a domain.'], 400);
        }

        // ---- Get BIMI TXT strings (robust) ----
        if (class_exists('DNS_Utils') && method_exists('DNS_Utils', 'get_bimi_txts')) {
            $bimi_txts = DNS_Utils::get_bimi_txts($domain, $selector);
        } else {
            $fqdn      = strtolower($selector ?: 'default') . '._bimi.' . strtolower(trim($domain));
            $recs      = @dns_get_record($fqdn, DNS_TXT);
            $bimi_txts = [];
            if (is_array($recs)) {
                foreach ($recs as $r) {
                    if (isset($r['txt'])) {
                        $bimi_txts[] = is_array($r['txt']) ? implode('', $r['txt']) : (string)$r['txt'];
                    } elseif (isset($r['entries']) && is_array($r['entries'])) {
                        $bimi_txts[] = implode('', $r['entries']);
                    }
                }
            }
            $bimi_txts = array_map(function($s){ return trim($s, "\"' \t\r\n"); }, $bimi_txts);
        }

        // ---- Get DMARC TXT strings (robust) ----
        if (class_exists('DNS_Utils') && method_exists('DNS_Utils', 'get_dmarc_txts')) {
            $dmarc_txts = DNS_Utils::get_dmarc_txts($domain);
        } else {
            $host       = '_dmarc.' . strtolower(trim($domain));
            $recs       = @dns_get_record($host, DNS_TXT);
            $dmarc_txts = [];
            if (is_array($recs)) {
                foreach ($recs as $r) {
                    if (isset($r['txt'])) {
                        $dmarc_txts[] = is_array($r['txt']) ? implode('', $r['txt']) : (string)$r['txt'];
                    } elseif (isset($r['entries']) && is_array($r['entries'])) {
                        $dmarc_txts[] = implode('', $r['entries']);
                    }
                }
            }
            $dmarc_txts = array_map(function($s){ return trim($s, "\"' \t\r\n"); }, $dmarc_txts);
        }

        // ---- Parse BIMI ----
        $bimi_raw  = $find_record((array)$bimi_txts, 'v=BIMI1');
        $bimi_tags = $bimi_raw ? $parse_tags($bimi_raw) : [];
        $bimi_out  = [];
        $logo_url  = null;

        if ($bimi_raw) {
            $bimi_out[] = ['state'=>'ok', 'label'=>'BIMI record found', 'detail'=>$bimi_raw];

            $a   = $bimi_tags['a']   ?? '';
            $l   = $bimi_tags['l']   ?? '';
            $avp = isset($bimi_tags['avp']) ? strtolower($bimi_tags['avp']) : '';

            // a= (VMC URL)
            if ($a !== '') {
                $bimi_out[] = $is_https($a)
                    ? ['state'=>'ok',    'label'=>'a= VMC URL', 'detail'=>$a]
                    : ['state'=>'error', 'label'=>'a= VMC URL', 'detail'=>'a= must be HTTPS'];
            } else {
                $bimi_out[] = ['state'=>'warn', 'label'=>'a= VMC URL', 'detail'=>'Not present (self-asserted)'];
            }

            // l= (Logo URL, required)
            if ($l !== '') {
                if ($is_https($l)) {
                    $bimi_out[] = ['state'=>'ok', 'label'=>'l= Logo URL', 'detail'=>$l];
                    $logo_url = $l;
                } else {
                    $bimi_out[] = ['state'=>'error', 'label'=>'l= Logo URL', 'detail'=>'l= must be HTTPS'];
                }
            } else {
                $bimi_out[] = ['state'=>'error', 'label'=>'l= Logo URL', 'detail'=>'Missing (required)'];
            }

            // avp= (optional: brand|personal). If missing, WARN.
            if ($avp !== '') {
                $bimi_out[] = in_array($avp, ['personal','brand'], true)
                    ? ['state'=>'ok',    'label'=>'avp= Attribute', 'detail'=>$avp]
                    : ['state'=>'error', 'label'=>'avp= Attribute', 'detail'=>'Must be personal or brand'];
            } else {
                $bimi_out[] = ['state'=>'warn', 'label'=>'avp= Attribute', 'detail'=>'Not set'];
            }
        } else {
            $bimi_out[] = ['state'=>'error','label'=>'BIMI record', 'detail'=>'No BIMI record found'];
        }

        // ---- Parse DMARC ----
        $dmarc_raw  = $find_record((array)$dmarc_txts, 'v=DMARC1');
        $dmarc_tags = $dmarc_raw ? $parse_tags($dmarc_raw) : [];
        $dmarc_out  = [];

        if ($dmarc_raw) {
            $dmarc_out[] = ['state'=>'ok', 'label'=>'DMARC record found', 'detail'=>$dmarc_raw];

            $p   = isset($dmarc_tags['p']) ? strtolower($dmarc_tags['p']) : '';
            $sp  = isset($dmarc_tags['sp']) ? strtolower($dmarc_tags['sp']) : $p;
            $pct = isset($dmarc_tags['pct']) ? (int)$dmarc_tags['pct'] : 100;
            $rua = $dmarc_tags['rua'] ?? '';

            $dmarc_out[] = in_array($p, ['quarantine','reject'], true)
                ? ['state'=>'ok',    'label'=>'p= (domain policy)',    'detail'=>$p]
                : ['state'=>'error', 'label'=>'p= (domain policy)',    'detail'=>'Must be quarantine or reject'];

            $dmarc_out[] = in_array($sp, ['quarantine','reject'], true)
                ? ['state'=>'ok',    'label'=>'sp= (subdomain policy)','detail'=>$sp]
                : ['state'=>'error', 'label'=>'sp= (subdomain policy)','detail'=>'Must be quarantine or reject (or inherit p=)'];

            $dmarc_out[] = ($pct === 100)
                ? ['state'=>'ok',    'label'=>'pct=', 'detail'=>'100']
                : ['state'=>'warn',  'label'=>'pct=', 'detail'=> (string)$pct . ' (recommend 100 or omit)'];

            $dmarc_out[] = $rua
                ? ['state'=>'ok',    'label'=>'rua=', 'detail'=>$rua]
                : ['state'=>'warn',  'label'=>'rua=', 'detail'=>'Not set'];
        } else {
            $dmarc_out[] = ['state'=>'error','label'=>'DMARC record', 'detail'=>'No DMARC record found'];
        }

        wp_send_json_success([
            'bimi'  => $bimi_out,
            'dmarc' => $dmarc_out,
            'logo'  => $logo_url,
        ]);
    }
    catch (Exception $e) {
        wp_send_json_error(['message' => 'Server error: ' . $e->getMessage()], 500);
    } catch (Error $e) {
        wp_send_json_error(['message' => 'Server error: ' . $e->getMessage()], 500);
    }
}

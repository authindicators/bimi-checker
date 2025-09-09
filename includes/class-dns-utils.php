<?php
if (!defined('ABSPATH')) { exit; }

class DNS_Utils {
    /** Return an array of TXT strings for a FQDN, normalised and safe. */
    public static function get_txt_strings($fqdn) {
        $recs = @dns_get_record($fqdn, DNS_TXT);
        if (!is_array($recs) || empty($recs)) return [];
        $out = [];
        foreach ($recs as $r) {
            if (isset($r['txt'])) {
                $out[] = is_array($r['txt']) ? implode('', $r['txt']) : (string)$r['txt'];
            } elseif (isset($r['entries']) && is_array($r['entries'])) {
                $out[] = implode('', $r['entries']);
            }
        }
        return array_map(function($s){ return trim($s, "\"' \t\r\n"); }, $out);
    }

    public static function get_bimi_txts($domain, $selector = 'default') {
        $domain = strtolower(trim($domain));
        $selector = $selector ?: 'default';
        $host = "{$selector}._bimi.{$domain}";
        return self::get_txt_strings($host);
    }

    public static function get_dmarc_txts($domain) {
        $domain = strtolower(trim($domain));
        $host = "_dmarc.{$domain}";
        return self::get_txt_strings($host);
    }
}

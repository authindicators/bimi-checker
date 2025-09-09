<?php
if (!defined('ABSPATH')) { exit; }

class BIMI_Parser {
    /** Return first v=BIMI1 TXT string, or null. */
    public static function find_record(array $txts) {
        foreach ($txts as $t) {
            if (stripos($t, 'v=BIMI1') !== false) return $t;
        }
        return null;
    }

    /** Parse k=v; pairs into array (lowercase keys). */
    public static function parse_tags($txt) {
        $tags = [];
        foreach (explode(';', (string)$txt) as $part) {
            $part = trim($part);
            if ($part === '') continue;
            if (strpos($part, '=') === false) { $tags[strtolower($part)] = true; continue; }
            [$k,$v] = array_map('trim', explode('=', $part, 2));
            $tags[strtolower($k)] = $v;
        }
        return $tags;
    }

    public static function is_https($url) {
        if (!is_string($url) || $url === '') return false;
        $scheme = parse_url($url, PHP_URL_SCHEME);
        return strtolower((string)$scheme) === 'https';
    }
}

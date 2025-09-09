<?php
if (!defined('ABSPATH')) { exit; }

class DMARC_Checker {
    public static function find_record(array $txts) {
        foreach ($txts as $t) {
            if (stripos($t, 'v=DMARC1') !== false) return $t;
        }
        return null;
    }

    public static function parse_tags($txt) {
        $tags = [];
        foreach (explode(';', (string)$txt) as $part) {
            $part = trim($part);
            if ($part === '' || strpos($part,'=') === false) continue;
            [$k,$v] = array_map('trim', explode('=', $part, 2));
            $tags[strtolower($k)] = $v;
        }
        return $tags;
    }
}

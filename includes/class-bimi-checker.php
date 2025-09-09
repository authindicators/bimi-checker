# File: bimi-checker/includes/class-bimi-checker.php
<?php
if (!defined('ABSPATH')) { exit; }

class BIMI_Checker_Core {
    private $domain;
    private $selector;

    public function __construct($domain, $selector = 'default') {
        $this->domain = BIMI_DNS_Utils::clean_domain($domain);
        $this->selector = $selector ?: 'default';
    }

    public function run() {
        $errors = [];
        if (!$this->domain) {
            return [ 'ok' => false, 'errors' => ['Please enter a domain.'] ];
        }
        if (!preg_match('/^[a-z0-9.-]+$/', $this->domain)) {
            return [ 'ok' => false, 'errors' => ['Domain contains invalid characters.'] ];
        }

        $bimi = $this->check_bimi();
        $dmarc = DMARC_Checker::evaluate($this->domain);

        return [
            'ok' => true,
            'domain' => $this->domain,
            'selector' => $this->selector,
            'bimi' => $bimi,
            'dmarc' => $dmarc,
            'summary' => $this->summary($bimi, $dmarc),
        ];
    }

    private function check_bimi() {
        $host = $this->selector . '._bimi.' . $this->domain;
        $txts = BIMI_DNS_Utils::get_txt($host);
        $status = [];

        if (!$txts) {
            $status[] = [ 'label' => 'BIMI record present', 'state' => 'error', 'detail' => 'No BIMI record found at ' . esc_html($host) . '.' ];
            return [ 'host' => $host, 'exists' => false, 'record' => null, 'tags' => [], 'status' => $status, 'type' => 'none', 'logo' => null ];
        }

        // Pick first BIMI-like record
        $record = null;
        foreach ($txts as $t) {
            if (stripos($t, 'v=BIMI1') !== false) { $record = $t; break; }
        }
        if (!$record) { $record = $txts[0]; }

        $tags = BIMI_Parser::parse($record);

        $status[] = [ 'label' => 'BIMI record present', 'state' => (stripos($record, 'v=BIMI1')!==false) ? 'ok' : 'warn', 'detail' => esc_html($record) ];

        // Validate tags
        $a = isset($tags['a']) ? trim($tags['a']) : '';
        $l = isset($tags['l']) ? trim($tags['l']) : '';
        $avp = isset($tags['avp']) ? strtolower(trim($tags['avp'])) : '';

        $type = $a ? 'mark-certificate' : 'self-asserted';

        if ($a) {
            $is_https = BIMI_Parser::is_https_url($a);
            $status[] = [ 'label' => 'a= Verified Mark Certificate URL', 'state' => $is_https ? 'ok' : 'error', 'detail' => $is_https ? esc_html($a) : 'a= must be an HTTPS URL.' ];
        } else {
            $status[] = [ 'label' => 'a= Verified Mark Certificate URL', 'state' => 'warn', 'detail' => 'a= not present; treated as self-asserted.' ];
        }

        if ($l) {
            $is_https = BIMI_Parser::is_https_url($l);
            $status[] = [ 'label' => 'l= Logo URL (SVG)', 'state' => $is_https ? 'ok' : 'error', 'detail' => $is_https ? esc_html($l) : 'l= must be an HTTPS URL.' ];
        } else {
            $status[] = [ 'label' => 'l= Logo URL (SVG)', 'state' => 'error', 'detail' => 'l= is required and must be an HTTPS URL to an SVG.' ];
        }

        if ($avp) {
            $allowed = ['personal','brand'];
            $valid = in_array($avp, $allowed, true);
            $status[] = [ 'label' => 'avp= Attribute', 'state' => $valid ? 'ok' : 'error', 'detail' => $valid ? esc_html($avp) : 'avp= must be "personal" or "brand" when provided.' ];
        } else {
            $status[] = [ 'label' => 'avp= Attribute (optional)', 'state' => 'info', 'detail' => 'Not set.' ];
        }

        return [
            'host' => $host,
            'exists' => true,
            'record' => $record,
            'tags' => $tags,
            'status' => $status,
            'type' => $type,
            'logo' => $l ?: null,
        ];
    }

    private function summary($bimi, $dmarc) {
        $items = [];
        $items[] = ($bimi['exists'] && $bimi['logo']) ? 'BIMI record found.' : 'No valid BIMI record.';
        $items[] = $bimi['type'] === 'mark-certificate' ? 'Uses a Verified Mark Certificate.' : 'Self-asserted (no certificate URL).';
        if (!$dmarc['exists']) {
            $items[] = 'DMARC missing.';
        } else {
            $p = isset($dmarc['tags']['p']) ? strtolower($dmarc['tags']['p']) : '';
            $sp = isset($dmarc['tags']['sp']) ? strtolower($dmarc['tags']['sp']) : $p;
            $pct = isset($dmarc['tags']['pct']) ? intval($dmarc['tags']['pct']) : 100;
            $items[] = 'DMARC policy: p=' . ($p ?: 'n/a') . ', sp=' . ($sp ?: 'n/a') . ', pct=' . $pct . '.';
        }
        return $items;
    }
}
<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'BIMI_Validator' ) ) {
  class BIMI_Validator {
    /** @var BIMI_DNS_Resolver */
    private $resolver;

    public function __construct( BIMI_DNS_Resolver $resolver ) {
      $this->resolver = $resolver;
    }

    public function check_domain_with_dmarc( string $domain, string $selector = 'default' ) : array {
      $domain   = strtolower( trim( $domain ) );
      $selector = strtolower( trim( $selector ) );
      if ( $selector === '' ) { $selector = 'default'; }

      $lookup = $this->lookup_bimi( $domain, $selector );
      if ( is_wp_error( $lookup ) ) {
        return [
          'ok'            => false,
          'error'         => $lookup->get_error_message(),
          'label'         => $selector . '._bimi.' . $domain,
          'selector'      => $selector,
          'domain'        => $domain,
          'bimi_found'    => false,
          'bimi_first'    => [],
          'bimi_first_kv' => [],
          'txt_raw'       => [],
          'dmarc'         => null,
          'uri_validation'=> [],
          'lps'           => [ 'present' => false, 'value' => null, 'valid' => null, 'prefixes' => [] ],
        ];
      }

      $parsedBimi = $lookup['parsed'];
      $bimi_found = count( $parsedBimi ) === 1;
      $bimi_first = $parsedBimi[0] ?? [];

      // DMARC remains evaluated for the domain entered by the user.
      $dmarc = $this->get_dmarc( $domain );

      return [
        'ok'             => $bimi_found,
        'label'          => $lookup['label'],
        'selector'       => $selector,
        'domain'         => $domain,
        'record_domain'  => $lookup['record_domain'],
        'fallback_used'  => $lookup['fallback_used'],
        'txt_raw'        => $lookup['raw'],
        'txt_ignored'    => $lookup['ignored'],
        'parsed'         => $parsedBimi,
        'bimi_found'     => $bimi_found,
        'bimi_first'     => $bimi_first,
        'bimi_first_kv'  => $bimi_first,
        'dmarc'          => $dmarc,
        'uri_validation' => $this->validate_bimi_uris( $bimi_first ),
        'lps'            => $this->validate_lps( $bimi_first ),
      ];
    }

    /**
     * Query a BIMI label and discard TXT records that do not begin with the
     * current BIMI version tag. This prevents wildcard SPF or unrelated TXT
     * records from being mistaken for BIMI.
     */
    private function lookup_bimi( string $domain, string $selector ) {
      $label = $selector . '._bimi.' . $domain;
      $txt   = $this->resolver->query( $label, 'TXT' );

      if ( is_wp_error( $txt ) ) {
        return $txt;
      }

      $filtered = $this->filter_bimi_txt( (array) $txt );

      // Only attempt Organizational Domain fallback when a reliable resolver
      // for that domain is available. WordPress itself does not ship a PSL.
      if ( empty( $filtered['parsed'] ) ) {
        $org_domain = $this->get_organizational_domain( $domain );

        if ( $org_domain && $org_domain !== $domain ) {
          $org_label = $selector . '._bimi.' . $org_domain;
          $org_txt   = $this->resolver->query( $org_label, 'TXT' );

          if ( ! is_wp_error( $org_txt ) ) {
            $org_filtered = $this->filter_bimi_txt( (array) $org_txt );
            if ( ! empty( $org_filtered['parsed'] ) ) {
              return [
                'label'         => $org_label,
                'record_domain' => $org_domain,
                'fallback_used' => true,
                'raw'           => $org_filtered['raw'],
                'ignored'       => array_merge( $filtered['ignored'], $org_filtered['ignored'] ),
                'parsed'        => $org_filtered['parsed'],
              ];
            }
          }
        }
      }

      return [
        'label'         => $label,
        'record_domain' => $domain,
        'fallback_used' => false,
        'raw'           => $filtered['raw'],
        'ignored'       => $filtered['ignored'],
        'parsed'        => $filtered['parsed'],
      ];
    }

    private function filter_bimi_txt( array $txt_records ) : array {
      $raw     = [];
      $ignored = [];
      $parsed  = [];

      foreach ( $txt_records as $txt ) {
        $txt = trim( (string) $txt );

        // Current BIMI discovery requires the record to START with v=BIMI1.
        if ( ! preg_match( '/^v\s*=\s*BIMI1(?:\s*;|\s*$)/i', $txt ) ) {
          if ( $txt !== '' ) { $ignored[] = $txt; }
          continue;
        }

        $item = $this->parse_tag_value_record( $txt );
        if ( isset( $item['v'] ) && strcasecmp( $item['v'], 'BIMI1' ) === 0 ) {
          $raw[]    = $txt;
          $parsed[] = $item;
        }
      }

      return [ 'raw' => $raw, 'ignored' => $ignored, 'parsed' => $parsed ];
    }

    private function parse_tag_value_record( string $record ) : array {
      $item = [];
      foreach ( array_map( 'trim', explode( ';', $record ) ) as $part ) {
        if ( $part === '' || strpos( $part, '=' ) === false ) { continue; }
        list( $key, $value ) = array_map( 'trim', explode( '=', $part, 2 ) );
        $item[ strtolower( $key ) ] = $value;
      }
      return $item;
    }

    private function validate_bimi_uris( array $record ) : array {
      $logo     = isset( $record['l'] ) ? trim( (string) $record['l'] ) : '';
      $evidence = isset( $record['a'] ) ? trim( (string) $record['a'] ) : '';

      return [
        'l' => $this->validate_logo_uri( $logo ),
        'a' => $this->validate_evidence_uri( $evidence ),
      ];
    }

    private function validate_logo_uri( string $uri ) : array {
      if ( $uri === '' ) {
        return [ 'status' => 'warn', 'message' => 'No logo URI is published.' ];
      }
      if ( ! $this->is_https_uri( $uri ) ) {
        return [ 'status' => 'error', 'message' => 'Logo URI must use HTTPS.' ];
      }

      $path = strtolower( (string) wp_parse_url( $uri, PHP_URL_PATH ) );
      if ( preg_match( '/\.pem$/i', $path ) ) {
        return [ 'status' => 'error', 'message' => 'Logo URI points to a .pem file; l= must retrieve a BIMI-compatible SVG/SVGZ image.' ];
      }
      if ( preg_match( '/\.(svg|svgz)$/i', $path ) ) {
        return [ 'status' => 'ok', 'message' => '' ];
      }

      return [ 'status' => 'warn', 'message' => 'Logo URI does not end in .svg or .svgz. The URL can still be valid, but the retrieved resource must be a BIMI-compatible SVG/SVGZ image.' ];
    }

    private function validate_evidence_uri( string $uri ) : array {
      // Empty a= is permitted by BIMI.
      if ( $uri === '' ) {
        return [ 'status' => 'ok', 'message' => 'No evidence document URI is published.' ];
      }
      if ( ! $this->is_https_uri( $uri ) ) {
        return [ 'status' => 'error', 'message' => 'Evidence document URI must use HTTPS.' ];
      }

      $path = strtolower( (string) wp_parse_url( $uri, PHP_URL_PATH ) );
      if ( preg_match( '/\.pem$/i', $path ) ) {
        return [ 'status' => 'ok', 'message' => '' ];
      }

      return [ 'status' => 'warn', 'message' => 'Evidence document URI does not end in .pem. This may not be supported by all BIMI implementations.' ];
    }

    private function is_https_uri( string $uri ) : bool {
      if ( ! filter_var( $uri, FILTER_VALIDATE_URL ) ) { return false; }
      $scheme = strtolower( (string) wp_parse_url( $uri, PHP_URL_SCHEME ) );
      $host   = (string) wp_parse_url( $uri, PHP_URL_HOST );
      return $scheme === 'https' && $host !== '';
    }

    private function validate_lps( array $record ) : array {
      if ( ! array_key_exists( 'lps', $record ) ) {
        return [ 'present' => false, 'value' => null, 'valid' => null, 'prefixes' => [], 'message' => '' ];
      }

      $value = trim( (string) $record['lps'] );
      if ( $value === '' ) {
        return [
          'present'  => true,
          'value'    => '',
          'valid'    => true,
          'prefixes' => [],
          'message'  => 'Empty lps= enables local-part selector processing for any normalized local-part.',
        ];
      }

      $prefixes = array_map( 'trim', explode( ',', $value ) );
      $prefixes = array_values( array_filter( $prefixes, static function( $prefix ) { return $prefix !== ''; } ) );

      if ( empty( $prefixes ) ) {
        return [ 'present' => true, 'value' => $value, 'valid' => false, 'prefixes' => [], 'message' => 'lps= does not contain a usable prefix.' ];
      }

      foreach ( $prefixes as $prefix ) {
        // Prefixes need to be usable against the normalized local-part selector.
        if ( strlen( $prefix ) > 63 || ! preg_match( '/^[A-Za-z0-9-]+$/', $prefix ) ) {
          return [
            'present'  => true,
            'value'    => $value,
            'valid'    => false,
            'prefixes' => $prefixes,
            'message'  => 'lps= contains a prefix that cannot be used with a normalized BIMI local-part selector.',
          ];
        }
      }

      return [
        'present'  => true,
        'value'    => $value,
        'valid'    => true,
        'prefixes' => $prefixes,
        'message'  => 'Valid local-part selector prefix list. A sender local-part is required to test the secondary BIMI lookup.',
      ];
    }

    /**
     * Organizational Domain requires a Public Suffix List implementation.
     * If a PSL-aware library is present, use it. Otherwise allow another
     * plugin/site to provide the result via a WordPress filter.
     */
    private function get_organizational_domain( string $domain ) : ?string {
      $filtered = apply_filters( 'bimi_checker_organizational_domain', null, $domain );
      if ( is_string( $filtered ) && $filtered !== '' ) {
        return strtolower( trim( $filtered, '. ' ) );
      }

      // Conservative built-in fallback for ordinary single-label public
      // suffixes such as .com, .net, .org, and newer gTLDs. For two-letter
      // country-code suffixes we do not guess because domains such as
      // example.co.uk require PSL knowledge.
      $labels = array_values( array_filter( explode( '.', strtolower( trim( $domain, '. ' ) ) ) ) );
      if ( count( $labels ) >= 2 ) {
        $tld = end( $labels );
        if ( strlen( $tld ) > 2 ) {
          return $labels[ count( $labels ) - 2 ] . '.' . $labels[ count( $labels ) - 1 ];
        }
      }

      return null;
    }

    private function get_dmarc( string $domain ) {
      $name = '_dmarc.' . $domain;
      $txt  = $this->resolver->query( $name, 'TXT' );
      if ( is_wp_error( $txt ) ) {
        return [
          'ok'        => false,
          'error'     => $txt->get_error_message(),
          'record'    => null,
          'parsed'    => [],
          'present'   => [],
          'effective' => [],
        ];
      }

      // DMARC can also coexist with unrelated TXT. Select the DMARC record.
      $record = '';
      foreach ( array_map( 'trim', (array) $txt ) as $candidate ) {
        if ( preg_match( '/^v\s*=\s*DMARC1(?:\s*;|\s*$)/i', $candidate ) ) {
          $record = $candidate;
          break;
        }
      }

      $tags = $record !== '' ? $this->parse_tag_value_record( $record ) : [];
      $present = [
        'p'   => array_key_exists( 'p', $tags ),
        'sp'  => array_key_exists( 'sp', $tags ),
        'pct' => array_key_exists( 'pct', $tags ),
        'rua' => array_key_exists( 'rua', $tags ),
      ];
      $effective = [
        'pct' => ( $present['pct'] && $tags['pct'] !== '' ) ? $tags['pct'] : '100',
      ];

      return [
        'ok'        => $record !== '',
        'record'    => $record,
        'parsed'    => $tags,
        'present'   => $present,
        'effective' => $effective,
      ];
    }
  }
}

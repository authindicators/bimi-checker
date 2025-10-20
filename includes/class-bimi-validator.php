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
      $bimiLabel = $selector . '._bimi.' . $domain;

      // BIMI TXT
      $txt = $this->resolver->query( $bimiLabel, 'TXT' );
      if ( is_wp_error( $txt ) ) {
        return [
          'ok'           => false,
          'error'        => $txt->get_error_message(),
          'label'        => $bimiLabel,
          'selector'     => $selector,
          'domain'       => $domain,
          'bimi_found'   => false,
          'bimi_first'   => [],
          'bimi_first_kv'=> [],
          'brand_type'   => null,
          'dmarc'        => null,
          'avp'          => ['A'=>false,'V'=>false,'P'=>false],
        ];
      }

      $parsedBimi = $this->parse_bimi_txt( (array) $txt );
      $bimi_found = ! empty( $parsedBimi );
      $bimi_first = $parsedBimi[0] ?? [];

      // DMARC
      $dmarc = $this->get_dmarc( $domain );

      // AVP flags
      $avp = [
        'A' => $this->avp_a( $parsedBimi ),
        'V' => $this->avp_v( $parsedBimi ),
        'P' => $this->avp_p( $dmarc ),
      ];

      // Brand/Personal hint (non-spec convenience): VMC present => Brand, else Personal
      $brand_type = ! empty( $bimi_first['a'] ) ? 'Brand' : 'Personal';

      return [
        'ok'            => $bimi_found,
        'label'         => $bimiLabel,
        'selector'      => $selector,
        'domain'        => $domain,
        'txt_raw'       => $txt,
        'parsed'        => $parsedBimi,
        'bimi_found'    => $bimi_found,
        'bimi_first'    => $bimi_first,
        'bimi_first_kv' => $bimi_first, // alias for template clarity
        'brand_type'    => $brand_type,
        'dmarc'         => $dmarc,
        'avp'           => $avp,
      ];
    }

    private function parse_bimi_txt( array $txt_records ) : array {
      $out = [];
      foreach ( $txt_records as $txt ) {
        $parts = array_map( 'trim', explode( ';', $txt ) );
        $item  = [];
        foreach ( $parts as $p ) {
          if ( $p === '' ) { continue; }
          if ( strpos( $p, '=' ) !== false ) {
            list( $k, $v ) = array_map( 'trim', explode( '=', $p, 2 ) );
            $item[ strtolower($k) ] = $v;
          }
        }
        if ( ! empty( $item ) ) { $out[] = $item; }
      }
      return $out;
    }

	private function get_dmarc( string $domain ) {
	$name = '_dmarc.' . $domain;
	$txt  = $this->resolver->query( $name, 'TXT' );
	if ( is_wp_error( $txt ) ) {
		return [
		'ok'      => false,
		'error'   => $txt->get_error_message(),
		'record'  => null,
		'parsed'  => [],
		'present' => [],
		'effective' => []
		];
	}

	$records = array_map( 'trim', (array) $txt );
	$record  = $records[0] ?? '';
	$tags    = [];
	foreach ( array_map('trim', explode(';', $record) ) as $frag ) {
		if ( $frag === '' ) { continue; }
		if ( strpos( $frag, '=' ) !== false ) {
		list( $k, $v ) = array_map('trim', explode('=', $frag, 2) );
		$tags[ strtolower($k) ] = $v;
		}
	}

	// Track presence (published vs not)
	$present = [
		'p'   => array_key_exists('p',   $tags),
		'sp'  => array_key_exists('sp',  $tags),
		'pct' => array_key_exists('pct', $tags),
		'rua' => array_key_exists('rua', $tags),
	];

	// Effective values (business logic)
	$effective = [
		// pct: if not published, treat as 100
		'pct' => ($present['pct'] && $tags['pct'] !== '') ? $tags['pct'] : '100',
	];

	return [
		'ok'        => ! empty( $record ),
		'record'    => $record,
		'parsed'    => $tags,       // raw published tags only
		'present'   => $present,    // booleans indicating publication
		'effective' => $effective,  // computed defaults (pct)
	];
	}

    private function avp_a( array $parsedBimi ) : bool {
      foreach ( $parsedBimi as $rec ) { if ( ! empty( $rec['a'] ) ) return true; }
      return false;
    }
    private function avp_v( array $parsedBimi ) : bool {
      foreach ( $parsedBimi as $rec ) { if ( isset($rec['v']) && strcasecmp($rec['v'],'BIMI1')===0 ) return true; }
      return false;
    }
    private function avp_p( $dmarc ) : bool {
      if ( ! is_array($dmarc) || empty( $dmarc['parsed']['p'] ) ) { return false; }
      $p = strtolower( $dmarc['parsed']['p'] );
      return in_array( $p, [ 'quarantine', 'reject' ], true );
    }
  }
}

<?php
// === FILE: includes/class-bimi-dns-resolver.php ===
if ( ! class_exists( 'BIMI_DNS_Resolver' ) ) :
class BIMI_DNS_Resolver {
	private $mode;      // 'system' or 'custom'
	private $servers;   // array
	private $timeout;   // int seconds
	private $retries;   // int

	public static function make_from_settings() : self {
		$mode    = get_option( 'bimi_checker_dns_mode', 'system' );
		$servers = array_filter( array_map( 'trim', explode( ',', (string) get_option( 'bimi_checker_dns_servers', '' ) ) ) );
		$timeout = absint( get_option( 'bimi_checker_dns_timeout', 3 ) );
		$retries = absint( get_option( 'bimi_checker_dns_retries', 1 ) );
		return new self( $mode, $servers, $timeout, $retries );
	}

	public function __construct( string $mode = 'system', array $servers = [], int $timeout = 3, int $retries = 1 ) {
		$this->mode    = in_array( $mode, [ 'system', 'custom' ], true ) ? $mode : 'system';
		$this->servers = $servers;
		$this->timeout = max(1, $timeout);
		$this->retries = max(0, $retries);
	}

	/**
	 * Query DNS for $name/$type.
	 * $type examples: 'TXT','A','AAAA','CNAME'.
	 * Returns a normalized array of records, or WP_Error.
	 */
	public function query( string $name, string $type = 'TXT' ) {
		$name = trim( $name, ". \t\r\n" );
		$type = strtoupper( $type );

		if ( $this->mode === 'custom' && ! empty( $this->servers ) && class_exists( '\\Net_DNS2_Resolver' ) ) {
			try {
				$r = new \Net_DNS2_Resolver([
					'nameservers' => $this->servers,
					'timeout'     => $this->timeout,
					'retries'     => $this->retries,
				]);
				$resp = $r->query( $name, $type );
				return $this->normalize_netdns2( $resp, $type );
			} catch ( \Exception $e ) {
				return new \WP_Error( 'dns_error', 'DNS query failed: ' . $e->getMessage() );
			}
		}

		// Fallback: system resolver via dns_get_record (uses OS / local resolver).
		$map = [ 'A' => DNS_A, 'AAAA' => DNS_AAAA, 'CNAME' => DNS_CNAME, 'TXT' => DNS_TXT ];
		$flag = $map[ $type ] ?? DNS_TXT;
		$records = @dns_get_record( $name, $flag );
		if ( $records === false ) {
			return new \WP_Error( 'dns_error', 'DNS query failed using system resolver.' );
		}
		return $this->normalize_dns_get_record( $records, $type );
	}

	private function normalize_dns_get_record( array $records, string $type ) : array {
		$out = [];
		foreach ( $records as $rec ) {
			if ( $type === 'TXT' && isset( $rec['txt'] ) ) {
				$out[] = $rec['txt'];
			} elseif ( $type === 'A' && isset( $rec['ip'] ) ) {
				$out[] = $rec['ip'];
			} elseif ( $type === 'AAAA' && isset( $rec['ipv6'] ) ) {
				$out[] = $rec['ipv6'];
			} elseif ( $type === 'CNAME' && isset( $rec['target'] ) ) {
				$out[] = rtrim( $rec['target'], '.' );
			}
		}
		return array_values( array_unique( $out ) );
	}

	private function normalize_netdns2( $response, string $type ) : array {
		$out = [];
		if ( empty( $response->answer ) ) return $out;
		foreach ( $response->answer as $rr ) {
			$rrtype = strtoupper( $rr->type ?? '' );
			if ( $type === 'TXT' && $rrtype === 'TXT' && isset( $rr->text ) ) {
				$out[] = is_array( $rr->text ) ? implode( '', $rr->text ) : (string) $rr->text;
			} elseif ( $type === 'A' && $rrtype === 'A' && isset( $rr->address ) ) {
				$out[] = $rr->address;
			} elseif ( $type === 'AAAA' && $rrtype === 'AAAA' && isset( $rr->address ) ) {
				$out[] = $rr->address;
			} elseif ( $type === 'CNAME' && $rrtype === 'CNAME' && isset( $rr->cname ) ) {
				$out[] = rtrim( $rr->cname, '.' );
			}
		}
		return array_values( array_unique( $out ) );
	}
}
endif;
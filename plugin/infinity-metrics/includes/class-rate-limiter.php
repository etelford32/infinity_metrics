<?php
/**
 * Collector rate limiting.
 *
 * @package InfinityMetrics
 */

defined( 'ABSPATH' ) || exit;

/**
 * Applies short per-source and per-client request limits.
 */
final class Infinity_Metrics_Rate_Limiter {

	/**
	 * Consume one request allowance.
	 *
	 * @param int    $source_id Source ID.
	 * @param string $client_ip Client IP, which is hashed and never stored raw.
	 * @return bool True when allowed.
	 */
	public static function allow( $source_id, $client_ip ) {
		$window       = 60;
		$source_limit = (int) apply_filters( 'infinity_metrics_source_rate_limit', 600 );
		$client_limit = (int) apply_filters( 'infinity_metrics_client_rate_limit', 60 );
		$bucket       = (int) floor( time() / $window );
		$client_hash  = hash_hmac( 'sha256', (string) $client_ip, wp_salt( 'nonce' ) );

		return self::consume( 'source_' . $source_id . '_' . $bucket, $source_limit, $window + 5 )
			&& self::consume( 'client_' . $source_id . '_' . $client_hash . '_' . $bucket, $client_limit, $window + 5 );
	}

	/**
	 * Increment one transient counter.
	 *
	 * @param string $identity Counter identity.
	 * @param int    $limit    Maximum requests.
	 * @param int    $ttl      Transient lifetime.
	 * @return bool
	 */
	private static function consume( $identity, $limit, $ttl ) {
		$key   = 'im_rate_' . md5( $identity );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}
		set_transient( $key, $count + 1, $ttl );
		return true;
	}
}

<?php
/**
 * Visitor identification for the rate limit of the public REST routes.
 *
 * @link       https://flaviowaser.ch
 * @since      2.2.0
 *
 * @package    SWFL
 * @subpackage SWFL_Plugin/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Works out which rate-limit bucket a request belongs to.
 *
 * The address is REMOTE_ADDR. Only when REMOTE_ADDR is one of the proxy ranges the administrator listed under
 * "Trusted proxies" the visitor is read from X-Forwarded-For, walking from the right and skipping trusted
 * proxies. IPv6 visitors are bucketed by their /64 prefix, IPv4 visitors by their full address.
 *
 * @since      2.2.0
 * @package    SWFL
 * @subpackage SWFL_Plugin/includes
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class SWFL_Client_Ip {

	/**
	 * Most proxy ranges that are read from the setting and the filter.
	 *
	 * @since 2.2.0
	 * @var   int
	 */
	const MAX_RANGES = 100;

	/**
	 * Most X-Forwarded-For hops that are looked at, counted from the right.
	 *
	 * @since 2.2.0
	 * @var   int
	 */
	const MAX_HOPS = 32;

	/**
	 * Bucket of everything that has no readable address.
	 *
	 * @since 2.2.0
	 * @var   string
	 */
	const UNKNOWN = 'unknown';

	/**
	 * Shortest prefix of a trusted IPv4 range.
	 *
	 * Wider ranges are invalid: the settings page saves while the administrator is still typing, and a half-typed
	 * "10.0.0.0/1" must not make half of the internet a trusted proxy.
	 *
	 * @since 2.2.0
	 * @var   int
	 */
	const MIN_PREFIX_V4 = 8;

	/**
	 * Shortest prefix of a trusted IPv6 range, see MIN_PREFIX_V4.
	 *
	 * @since 2.2.0
	 * @var   int
	 */
	const MIN_PREFIX_V6 = 16;

	/**
	 * First 12 bytes of an IPv4-mapped IPv6 address (::ffff:0:0/96).
	 *
	 * @since 2.2.0
	 * @var   string
	 */
	const MAPPED_PREFIX = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

	/**
	 * Get the rate-limit bucket of the current request.
	 *
	 * @since 2.2.0
	 * @return string Bucket such as `4:203.0.113.7` or `6:20010db800010002`, or `unknown`.
	 */
	public static function get_bucket() {
		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ranges      = self::get_trusted_ranges();
		$forwarded   = '';
		// The header is chosen by the client, so it is only read when the administrator trusts the connecting proxy.
		if ( $ranges && isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		}

		$resolved = self::resolve( $remote_addr, $forwarded, $ranges );

		/**
		 * Filters the address that identifies a visitor for the rate limit of the public REST routes.
		 *
		 * The default is REMOTE_ADDR, or the visitor taken from X-Forwarded-For when REMOTE_ADDR is a trusted proxy.
		 * IPv6 addresses are grouped by their /64 prefix after this filter.
		 *
		 * @since 2.2.0
		 * @param string $ip          Address, empty when none could be read.
		 * @param string $remote_addr REMOTE_ADDR as sent by the web server.
		 */
		$ip = apply_filters( 'swfl_client_ip', null === $resolved ? '' : inet_ntop( $resolved ), $remote_addr );

		return self::to_bucket( self::parse_ip( is_string( $ip ) ? $ip : '' ) );
	}

	/**
	 * Find the visitor behind a connection.
	 *
	 * @since 2.2.0
	 * @param string $remote_addr   REMOTE_ADDR.
	 * @param string $forwarded_for X-Forwarded-For header, empty when not read.
	 * @param array  $ranges        Trusted proxy ranges from parse_range().
	 * @return string|null Binary address (4 or 16 bytes), null when REMOTE_ADDR is unreadable.
	 */
	public static function resolve( $remote_addr, $forwarded_for, $ranges ) {
		$remote = self::parse_ip( $remote_addr );
		if ( null === $remote || empty( $ranges ) || '' === trim( $forwarded_for ) || ! self::in_ranges( $remote, $ranges ) ) {
			return $remote;
		}

		// The connecting proxy stands for the visitor unless a hop from the right is a readable address outside the trusted ranges.
		$visitor = $remote;
		$hops    = array_slice( explode( ',', $forwarded_for ), -self::MAX_HOPS );
		for ( $i = count( $hops ) - 1; $i >= 0; $i-- ) {
			$hop = self::parse_ip( $hops[ $i ] );
			// An unreadable hop cannot be trusted, so the walk ends there.
			if ( null === $hop || ! self::in_ranges( $hop, $ranges ) ) {
				$visitor = null === $hop ? $remote : $hop;
				break;
			}
		}

		return $visitor;
	}

	/**
	 * Turn an address into its rate-limit bucket.
	 *
	 * @since 2.2.0
	 * @param string|null $binary Binary address from parse_ip().
	 * @return string `4:` and the IPv4 address, `6:` and the 64-bit prefix of an IPv6 address in hex, or `unknown`.
	 */
	public static function to_bucket( $binary ) {
		if ( null === $binary ) {
			return self::UNKNOWN;
		}

		return 4 === strlen( $binary ) ? '4:' . inet_ntop( $binary ) : '6:' . bin2hex( substr( $binary, 0, 8 ) );
	}

	/**
	 * Read an address as sent by a web server or proxy: IPv4, IPv6, with port, in brackets, with a zone id.
	 *
	 * IPv4-mapped IPv6 addresses (::ffff:203.0.113.7) are returned as IPv4.
	 *
	 * @since 2.2.0
	 * @param string $text Address.
	 * @return string|null Binary address (4 or 16 bytes), null when it is not an address.
	 */
	public static function parse_ip( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text || strlen( $text ) > 64 ) {
			return null;
		}
		if ( '[' === $text[0] ) {
			$end = strpos( $text, ']' );
			if ( false === $end ) {
				return null;
			}
			$text = substr( $text, 1, $end - 1 );
		} elseif ( 1 === substr_count( $text, ':' ) && false !== strpos( $text, '.' ) ) {
			$text = substr( $text, 0, (int) strpos( $text, ':' ) );
		}
		$zone = strpos( $text, '%' );
		if ( false !== $zone ) {
			$text = substr( $text, 0, $zone );
		}
		$binary = self::to_binary( $text );
		if ( null !== $binary && 16 === strlen( $binary ) && self::MAPPED_PREFIX === substr( $binary, 0, 12 ) ) {
			$binary = substr( $binary, 12 );
		}

		return $binary;
	}

	/**
	 * Read a trusted proxy entry: an address or a CIDR range such as 173.245.48.0/20 or 2400:cb00::/32.
	 *
	 * @since 2.2.0
	 * @param string $text Entry.
	 * @return array|null Array with `binary` and `bits`, null when it is invalid or wider than MIN_PREFIX_V4 (IPv4) or MIN_PREFIX_V6 (IPv6). IPv4-mapped ranges become IPv4 ranges.
	 */
	public static function parse_range( $text ) {
		$parts  = explode( '/', trim( (string) $text ), 2 );
		$binary = self::to_binary( $parts[0] );
		$range  = null;
		if ( null !== $binary ) {
			$bits = 8 * strlen( $binary );
			if ( isset( $parts[1] ) ) {
				$bits = 1 === preg_match( '/^\d{1,3}$/D', $parts[1] ) ? (int) $parts[1] : 0;
			}
			// parse_ip() turns IPv4-mapped visitors into IPv4, so a mapped range (::ffff:10.0.0.0/104) has to be the IPv4 range it stands for.
			if ( 16 === strlen( $binary ) && $bits >= 96 && self::MAPPED_PREFIX === substr( $binary, 0, 12 ) ) {
				$binary = substr( $binary, 12 );
				$bits  -= 96;
			}
			$min = 4 === strlen( $binary ) ? self::MIN_PREFIX_V4 : self::MIN_PREFIX_V6;
			if ( $bits >= $min && $bits <= 8 * strlen( $binary ) ) {
				$range = array(
					'binary' => $binary,
					'bits'   => $bits,
				);
			}
		}

		return $range;
	}

	/**
	 * Write a range the way the setting stores it.
	 *
	 * @since 2.2.0
	 * @param array $range Range from parse_range().
	 * @return string Address and prefix length, e.g. `10.0.0.0/8`.
	 */
	public static function format_range( $range ) {
		return inet_ntop( $range['binary'] ) . '/' . $range['bits'];
	}

	/**
	 * Whether an address is inside one of the ranges.
	 *
	 * @since 2.2.0
	 * @param string $binary Binary address.
	 * @param array  $ranges Ranges from parse_range().
	 * @return bool
	 */
	public static function in_ranges( $binary, $ranges ) {
		foreach ( $ranges as $range ) {
			if ( strlen( $binary ) !== strlen( $range['binary'] ) ) {
				continue;
			}
			$bytes = intdiv( $range['bits'], 8 );
			$rest  = $range['bits'] % 8;
			if ( 0 !== $bytes && substr( $binary, 0, $bytes ) !== substr( $range['binary'], 0, $bytes ) ) {
				continue;
			}
			if ( 0 === $rest ) {
				return true;
			}
			$mask = ( 0xFF << ( 8 - $rest ) ) & 0xFF;
			if ( ( ord( $binary[ $bytes ] ) & $mask ) === ( ord( $range['binary'][ $bytes ] ) & $mask ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the trusted proxy ranges: the setting, then the swfl_trusted_proxies filter.
	 *
	 * @since 2.2.0
	 * @return array[] Ranges from parse_range(), empty while the feature is off.
	 */
	public static function get_trusted_ranges() {
		$setting = get_option( 'swfl_trusted_proxies', '' );
		$entries = is_string( $setting ) ? self::split_entries( $setting ) : array();

		/**
		 * Filters the proxy addresses and CIDR ranges whose X-Forwarded-For header is trusted.
		 *
		 * The default is the setting "Trusted proxies" (empty: forwarded headers are ignored).
		 *
		 * @since 2.2.0
		 * @param string[] $entries Addresses and ranges, e.g. `173.245.48.0/20`.
		 */
		$entries = apply_filters( 'swfl_trusted_proxies', $entries );

		$ranges = array();
		foreach ( is_array( $entries ) ? array_slice( $entries, 0, self::MAX_RANGES ) : array() as $entry ) {
			$range = is_string( $entry ) ? self::parse_range( $entry ) : null;
			if ( null !== $range ) {
				$ranges[] = $range;
			}
		}

		return $ranges;
	}

	/**
	 * Split the text of the setting into entries: separated by line breaks, spaces, commas or semicolons; # starts a comment.
	 *
	 * @since 2.2.0
	 * @param string $text Setting text.
	 * @return string[] Entries.
	 */
	public static function split_entries( $text ) {
		$text = preg_replace( '/#[^\r\n]*/', '', $text );

		return is_string( $text ) ? preg_split( '/[\s,;]+/', $text, -1, PREG_SPLIT_NO_EMPTY ) : array();
	}

	/**
	 * Convert a plain address (no port, brackets or zone) to binary.
	 *
	 * @since 2.2.0
	 * @param string $text Address.
	 * @return string|null Binary address, null when invalid.
	 */
	private static function to_binary( $text ) {
		// filter_var() checks first, as inet_pton() raises a warning for text that is not an address.
		if ( false === filter_var( $text, FILTER_VALIDATE_IP ) ) {
			return null;
		}
		$binary = inet_pton( $text );

		return false === $binary ? null : $binary;
	}
}

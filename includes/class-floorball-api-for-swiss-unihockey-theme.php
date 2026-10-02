<?php
/**
 * Theme, seed colour and table style helpers.
 *
 * @link       https://flaviowaser.ch
 * @since      2.0.1
 *
 * @package    Swiss_Floorball_Api
 * @subpackage Swiss_Floorball_Api/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the theme options and derives contrast-safe CSS colours from them.
 *
 * Split out of Swiss_Floorball_API_Display, which only renders API data.
 *
 * @since      2.0.1
 * @package    Swiss_Floorball_Api
 * @subpackage Swiss_Floorball_Api/includes
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class Swiss_Floorball_API_Theme {

	/**
	 * Get the configured colour theme.
	 *
	 * @since 1.0.0
	 *
	 * @return string One of 'auto', 'light' or 'dark'; falls back to 'auto'.
	 */
	public static function get_theme() {
		$theme = get_option( 'swissfloorball_theme', 'auto' );
		return in_array( $theme, array( 'auto', 'light', 'dark' ), true ) ? $theme : 'auto';
	}

	/**
	 * Get the configured seed colour.
	 *
	 * @since 1.0.6
	 *
	 * @return string Normalised 6-digit hex colour like '#0066cc', or '' when unset or invalid.
	 */
	public static function get_seed_color() {
		$color = sanitize_hex_color( trim( (string) get_option( 'swissfloorball_seed_color', '' ) ) );
		if ( empty( $color ) ) {
			return '';
		}
		if ( 4 === strlen( $color ) ) {
			$color = '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
		}
		return strtolower( $color );
	}

	/**
	 * Build the inline CSS that derives the colour tokens from the seed colour.
	 *
	 * The palette is computed here instead of with color-mix() so the WCAG AA contrast of every
	 * on-* pair can be guaranteed for any seed. Light, auto-dark and forced-dark are separate blocks
	 * because the base stylesheet sets the dark tokens with a specificity of 0,2,0.
	 *
	 * @since 1.0.6
	 *
	 * @return string CSS, or '' when no seed colour is configured.
	 */
	public static function get_seed_css() {
		$seed = self::get_seed_color();
		if ( '' === $seed ) {
			return '';
		}
		$seed   = self::hex_to_rgb( $seed );
		$white  = array( 255, 255, 255 );
		$black  = array( 0, 0, 0 );
		$dark   = self::hex_to_rgb( '#121417' );
		$grey_l = self::hex_to_rgb( '#5f6368' );
		$grey_d = self::hex_to_rgb( '#c4c7c5' );

		// Light: darken the seed until white text on it reaches 4.5:1.
		$primary = $seed;
		foreach ( array( 0, 0.2, 0.4, 0.6, 0.8 ) as $step ) {
			$primary = self::mix_colors( $black, $seed, 1 - $step );
			if ( self::contrast_ratio( $primary, $white ) >= 4.5 ) {
				break;
			}
		}
		$secondary = self::mix_colors( $grey_l, $primary, 0.5 );
		$light     = array(
			'primary'                => $primary,
			'on-primary'             => self::best_on_color( $primary ),
			'primary-container'      => self::mix_colors( $white, $seed, 0.15 ),
			'on-primary-container'   => self::mix_colors( $black, $seed, 0.25 ),
			'secondary'              => $secondary,
			'on-secondary'           => self::best_on_color( $secondary ),
			'secondary-container'    => self::mix_colors( $white, $secondary, 0.12 ),
			'on-secondary-container' => self::mix_colors( $black, $secondary, 0.25 ),
			'focus-ring'             => $primary,
		);

		// Dark: lighten the seed until it reaches 4.5:1 against the dark surface.
		$primary = $seed;
		foreach ( array( 0, 0.2, 0.4, 0.6, 0.8 ) as $step ) {
			$primary = self::mix_colors( $white, $seed, 1 - $step );
			if ( self::contrast_ratio( $primary, $dark ) >= 4.5 ) {
				break;
			}
		}
		$secondary  = self::mix_colors( $grey_d, $primary, 0.5 );
		$dark_theme = array(
			'primary'                => $primary,
			'on-primary'             => self::best_on_color( $primary ),
			'primary-container'      => self::mix_colors( $dark, $seed, 0.35 ),
			'on-primary-container'   => self::mix_colors( $white, $seed, 0.15 ),
			'secondary'              => $secondary,
			'on-secondary'           => self::best_on_color( $secondary ),
			'secondary-container'    => self::mix_colors( $dark, $secondary, 0.3 ),
			'on-secondary-container' => self::mix_colors( $white, $secondary, 0.15 ),
			'focus-ring'             => $primary,
		);

		$light_scopes = '.swiss-floorball-plugin,.sfa-admin-wrap';
		$auto_scopes  = '.swiss-floorball-plugin:not([data-sfa-theme="light"]),.sfa-admin-wrap:not([data-sfa-theme="light"])';
		$dark_scopes  = '.swiss-floorball-plugin[data-sfa-theme="dark"],.sfa-admin-wrap[data-sfa-theme="dark"]';

		$css  = $light_scopes . '{' . self::tokens_to_css( $light ) . '}';
		$css .= '@media (prefers-color-scheme: dark){' . $auto_scopes . '{' . self::tokens_to_css( $dark_theme ) . '}}';
		$css .= $dark_scopes . '{' . self::tokens_to_css( $dark_theme ) . '}';
		return $css;
	}

	/**
	 * Get the configured table style.
	 *
	 * @since 1.0.6
	 *
	 * @return string 'flat' (default) or 'classic'.
	 */
	public static function get_table_style() {
		$style = get_option( 'swissfloorball_table_style', 'flat' );
		return in_array( $style, array( 'flat', 'classic' ), true ) ? $style : 'flat';
	}

	/**
	 * Whether table rows are striped.
	 *
	 * @since 1.0.6
	 *
	 * @return bool True when zebra striping is enabled.
	 */
	public static function is_table_striped() {
		return '1' === (string) get_option( 'swissfloorball_table_striped', '0' );
	}

	/**
	 * Get a configured table colour as a normalised hex value.
	 *
	 * @since 1.0.6
	 *
	 * @param string $name One of 'accent', 'header', 'divider' or 'highlight'.
	 * @return string Colour like '#0066cc', or '' when unset or invalid.
	 */
	private static function get_table_color( $name ) {
		$color = sanitize_hex_color( trim( (string) get_option( 'swissfloorball_table_' . $name . '_color', '' ) ) );
		if ( empty( $color ) ) {
			return '';
		}
		if ( 4 === strlen( $color ) ) {
			$color = '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
		}
		return strtolower( $color );
	}

	/**
	 * Move a colour towards black or white until it reaches the contrast ratio against a background.
	 *
	 * @since 1.0.6
	 *
	 * @param int[] $rgb        Colour to adjust.
	 * @param int[] $background Background colour.
	 * @param float $minimum    Required contrast ratio.
	 * @return int[] Adjusted colour; unchanged when it already has enough contrast.
	 */
	private static function ensure_contrast( $rgb, $background, $minimum ) {
		$target   = self::best_on_color( $background );
		$adjusted = $rgb;
		foreach ( array( 0, 0.2, 0.4, 0.6, 0.8, 1 ) as $step ) {
			$adjusted = self::mix_colors( $rgb, $target, $step );
			if ( self::contrast_ratio( $adjusted, $background ) >= $minimum ) {
				break;
			}
		}
		return $adjusted;
	}

	/**
	 * Build the inline CSS for the table colours configured in the backend.
	 *
	 * Colours are adjusted so the accent line keeps 3:1 and the header text 4.5:1 against the
	 * light or dark surface; the highlight is mixed into the surface so row text stays readable.
	 * Unset options emit nothing and fall back to the token defaults in the stylesheet.
	 *
	 * @since 1.0.6
	 *
	 * @return string CSS, or '' when no table colour is configured.
	 */
	public static function get_table_css() {
		$colors = array();
		foreach ( array( 'accent', 'header', 'divider', 'highlight' ) as $name ) {
			$hex = self::get_table_color( $name );
			if ( '' !== $hex ) {
				$colors[ $name ] = self::hex_to_rgb( $hex );
			}
		}
		if ( empty( $colors ) ) {
			return '';
		}

		$build = function ( $surface ) use ( $colors ) {
			$css = '';
			if ( isset( $colors['accent'] ) ) {
				$css .= '--sfa-table-accent:' . self::rgb_to_hex( self::ensure_contrast( $colors['accent'], $surface, 3 ) ) . ';';
			}
			if ( isset( $colors['header'] ) ) {
				$css .= '--sfa-table-header-color:' . self::rgb_to_hex( self::ensure_contrast( $colors['header'], $surface, 4.5 ) ) . ';';
			}
			if ( isset( $colors['divider'] ) ) {
				$css .= '--sfa-table-divider:' . self::rgb_to_hex( $colors['divider'] ) . ';';
			}
			if ( isset( $colors['highlight'] ) ) {
				$css .= '--sfa-table-highlight:' . self::rgb_to_hex( self::mix_colors( $surface, $colors['highlight'], 0.18 ) ) . ';';
			}
			return $css;
		};

		$light = $build( array( 255, 255, 255 ) );
		$dark  = $build( self::hex_to_rgb( '#121417' ) );

		$css  = '.swiss-floorball-plugin,.sfa-admin-wrap{' . $light . '}';
		$css .= '@media (prefers-color-scheme: dark){.swiss-floorball-plugin:not([data-sfa-theme="light"]),.sfa-admin-wrap:not([data-sfa-theme="light"]){' . $dark . '}}';
		$css .= '.swiss-floorball-plugin[data-sfa-theme="dark"],.sfa-admin-wrap[data-sfa-theme="dark"]{' . $dark . '}';
		return $css;
	}

	/**
	 * Turn a token map into CSS declarations.
	 *
	 * @since 1.0.6
	 *
	 * @param array $tokens Token name (without prefix) => RGB array.
	 * @return string CSS declarations.
	 */
	private static function tokens_to_css( $tokens ) {
		$css = '';
		foreach ( $tokens as $name => $rgb ) {
			$css .= '--sfa-sys-color-' . $name . ':' . self::rgb_to_hex( $rgb ) . ';';
		}
		return $css;
	}

	/**
	 * Convert a validated 6-digit hex colour to an RGB array.
	 *
	 * @since 1.0.6
	 *
	 * @param string $hex Colour like '#0066cc'.
	 * @return int[] Red, green and blue (0-255).
	 */
	private static function hex_to_rgb( $hex ) {
		return array(
			hexdec( substr( $hex, 1, 2 ) ),
			hexdec( substr( $hex, 3, 2 ) ),
			hexdec( substr( $hex, 5, 2 ) ),
		);
	}

	/**
	 * Convert an RGB array to a 6-digit hex colour.
	 *
	 * @since 1.0.6
	 *
	 * @param int[] $rgb Red, green and blue (0-255).
	 * @return string Colour like '#0066cc'.
	 */
	private static function rgb_to_hex( $rgb ) {
		return sprintf( '#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2] );
	}

	/**
	 * Mix two colours in sRGB, like color-mix( in srgb, $color $weight, $base ).
	 *
	 * @since 1.0.6
	 *
	 * @param int[] $base   Base RGB colour.
	 * @param int[] $color  RGB colour mixed into the base.
	 * @param float $weight Share of $color, 0 to 1.
	 * @return int[] Mixed RGB colour.
	 */
	private static function mix_colors( $base, $color, $weight ) {
		$mixed = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$mixed[ $i ] = (int) round( $color[ $i ] * $weight + $base[ $i ] * ( 1 - $weight ) );
		}
		return $mixed;
	}

	/**
	 * Relative luminance of an RGB colour (WCAG 2.x).
	 *
	 * @since 1.0.6
	 *
	 * @param int[] $rgb Red, green and blue (0-255).
	 * @return float Luminance, 0 to 1.
	 */
	private static function relative_luminance( $rgb ) {
		$lin = array();
		foreach ( $rgb as $i => $channel ) {
			$c         = $channel / 255;
			$lin[ $i ] = ( $c <= 0.03928 ) ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
	}

	/**
	 * WCAG contrast ratio between two colours.
	 *
	 * @since 1.0.6
	 *
	 * @param int[] $a First RGB colour.
	 * @param int[] $b Second RGB colour.
	 * @return float Contrast ratio, 1 to 21.
	 */
	private static function contrast_ratio( $a, $b ) {
		$la = self::relative_luminance( $a );
		$lb = self::relative_luminance( $b );
		return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
	}

	/**
	 * Pick white or black, whichever has the better contrast on the given background.
	 *
	 * @since 1.0.6
	 *
	 * @param int[] $background RGB background colour.
	 * @return int[] RGB colour (white or black).
	 */
	private static function best_on_color( $background ) {
		$white = array( 255, 255, 255 );
		$black = array( 0, 0, 0 );
		return ( self::contrast_ratio( $background, $white ) >= self::contrast_ratio( $background, $black ) ) ? $white : $black;
	}
}

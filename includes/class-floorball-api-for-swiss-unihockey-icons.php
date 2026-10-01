<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the inline SVG icon set (Material Symbols) used across admin screens.
 *
 * Icons are embedded as inline SVG rather than an icon font so the plugin never
 * requests anything from Google at runtime (admin screens only).
 *
 * @link       https://flaviowaser.ch
 * @since      1.0.6
 *
 * @package    Swiss_Floorball_Api
 * @subpackage Swiss_Floorball_Api/includes
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class Swiss_Floorball_Api_Icons {

	/**
	 * Path data for each available icon, keyed by name.
	 *
	 * Sourced from Google's Material Symbols (Filled) icon set, 24x24 grid.
	 *
	 * @since 1.0.6
	 * @var   array<string,string>
	 */
	private static $paths = array(
		'hockey'      => 'M2 17v3h2v-4H3c-.55 0-1 .45-1 1zm7-1H5v4l4.69-.01c.38 0 .72-.21.89-.55l.87-1.9-1.59-3.48L9 16zm12.71.29A.997.997 0 0 0 21 16h-1v4h2v-3c0-.28-.11-.53-.29-.71zm-8.11-3.45L17.65 4H14.3l-1.76 3.97-.49 1.1-.05.14L9.7 4H6.35l4.05 8.84 1.52 3.32.08.18 1.42 3.1c.17.34.51.55.89.55L19 20v-4h-4l-1.4-3.16z',
		'settings'    => 'M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.488.488 0 0 0-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54a.484.484 0 0 0-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z',
		'chart'       => 'M4 9h4v11H4zm12 4h4v7h-4zm-6-9h4v16h-4z',
		'warning'     => 'M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z',
		'group'       => 'M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z',
		'search'      => 'M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z',
		'calendar'    => 'M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zM9 14H7v-2h2v2zm4 0h-2v-2h2v2zm4 0h-2v-2h2v2zm-8 4H7v-2h2v2zm4 0h-2v-2h2v2zm4 0h-2v-2h2v2z',
		'check'       => 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z',
		'delete'      => 'M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z',
		'back'        => 'M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z',
		'description' => 'M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z',
		'trophy'      => 'M19 5h-2V3H7v2H5c-1.1 0-2 .9-2 2v1c0 2.55 1.92 4.63 4.39 4.94A5.01 5.01 0 0 0 11 15.9V19H7v2h10v-2h-4v-3.1a5.01 5.01 0 0 0 3.61-2.96C19.08 12.63 21 10.55 21 8V7c0-1.1-.9-2-2-2zM5 8V7h2v3.82C5.84 10.4 5 9.3 5 8zm14 0c0 1.3-.84 2.4-2 2.82V7h2v1z',
		'storage'     => 'M2 20h20v-4H2v4zm2-3h2v2H4v-2zM2 4v4h20V4H2zm4 3H4V5h2v2zm-4 7h20v-4H2v4zm2-3h2v2H4v-2z',
	);

	/**
	 * Build the sanitized inline SVG markup for an icon.
	 *
	 * @since 1.0.6
	 * Names resolve against the built-in path map first, then against
	 * public/icons/<name>.svg (Material Symbols Outlined, FILL=1).
	 *
	 * @param string       $name Icon name (lowercase letters, digits, underscore).
	 * @param string|array $args Optional. A string is used as additional CSS class(es).
	 *                           An array may contain 'class' and 'label' (text alternative).
	 * @return string Sanitized inline SVG markup, or an empty string if icons are disabled or $name is unknown.
	 */
	public static function get( $name, $args = '' ) {
		if ( '1' !== (string) get_option( 'swissfloorball_show_icons', '1' ) || ! is_string( $name ) || ! preg_match( '/^[a-z0-9_]+$/', $name ) ) {
			return '';
		}

		if ( ! is_array( $args ) ) {
			$args = array( 'class' => (string) $args );
		}
		$args = wp_parse_args(
			$args,
			array(
				'class' => '',
				'label' => '',
			)
		);

		$icon = self::resolve( $name );
		if ( null === $icon ) {
			return '';
		}

		$class = trim( 'sfa-icon swfl-icon ' . $args['class'] );

		if ( '' !== (string) $args['label'] ) {
			$a11y = sprintf( 'role="img" aria-label="%s"', esc_attr( $args['label'] ) );
		} else {
			$a11y = 'aria-hidden="true"';
		}

		$svg = sprintf(
			'<svg class="%1$s" xmlns="http://www.w3.org/2000/svg" viewBox="%2$s" width="1em" height="1em" fill="currentColor" %3$s focusable="false"><path d="%4$s"/></svg>',
			esc_attr( $class ),
			esc_attr( $icon['viewbox'] ),
			$a11y,
			esc_attr( $icon['path'] )
		);

		/**
		 * Filters the icon SVG markup. The result is sanitized again with wp_kses().
		 *
		 * @param string $svg  SVG markup.
		 * @param string $name Icon name.
		 * @param array  $args Icon arguments (class, label).
		 */
		$svg = apply_filters( 'swfl_icon_svg', $svg, $name, $args );

		return wp_kses( (string) $svg, self::allowed_svg_html() );
	}

	/**
	 * Echo the sanitized inline SVG markup for an icon.
	 *
	 * @since 1.0.6
	 * @param string       $name Icon name.
	 * @param string|array $args Optional. See self::get().
	 * @return void
	 */
	public static function render( $name, $args = '' ) {
		echo self::get( $name, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized via wp_kses() in self::get().
	}

	/**
	 * Look up the path data and viewBox for an icon.
	 *
	 * @param string $name Validated icon name.
	 * @return array|null Array with 'path' and 'viewbox', or null if unknown/unreadable.
	 */
	private static function resolve( $name ) {
		if ( isset( self::$paths[ $name ] ) ) {
			return array(
				'path'    => self::$paths[ $name ],
				'viewbox' => '0 0 24 24',
			);
		}

		$file     = plugin_dir_path( __DIR__ ) . 'public/icons/' . $name . '.svg';
		$contents = is_readable( $file ) ? file_get_contents( $file ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local plugin file.
		if ( ! is_string( $contents ) || ! preg_match( '/<path[^>]*\sd="([^"]+)"/', $contents, $path ) ) {
			return null;
		}

		$viewbox = '0 0 24 24';
		if ( preg_match( '/viewBox="(-?[0-9. -]+)"/', $contents, $vb ) ) {
			$viewbox = $vb[1];
		}

		return array(
			'path'    => $path[1],
			'viewbox' => $viewbox,
		);
	}

	/**
	 * Allowed SVG tags/attributes for wp_kses().
	 *
	 * @since 1.0.6
	 * @return array
	 */
	private static function allowed_svg_html() {
		return array(
			'svg'  => array(
				'class'       => true,
				'xmlns'       => true,
				'viewbox'     => true,
				'width'       => true,
				'height'      => true,
				'fill'        => true,
				'aria-hidden' => true,
				'aria-label'  => true,
				'role'        => true,
				'focusable'   => true,
			),
			'path' => array(
				'd'    => true,
				'fill' => true,
			),
			'span' => array(
				'class' => true,
			),
		);
	}
}

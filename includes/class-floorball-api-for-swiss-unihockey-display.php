<?php
/**
 * Fired during plugin activation
 *
 * @link       https://flaviowaser.ch
 * @since      1.0.0
 *
 * @package    Swiss_Floorball_Api
 * @subpackage Swiss_Floorball_Api/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to handle HTML display of API data.
 *
 * @since      1.0.0
 * @package    Swiss_Floorball_Api
 * @subpackage Swiss_Floorball_Api/includes
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class Swiss_Floorball_API_Display {

	/**
	 * API Client instance.
	 *
	 * @var Swiss_Floorball_API_Client
	 */
	private static $client;

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

	/**
	 * Render an error banner for failed or empty API responses.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Already translated message (escaped here, late).
	 * @return void
	 */
	private static function render_error_notice( $message ) {
		echo '<div class="sfa-info-box sfa-info-box--danger" role="alert"><p>' . Swiss_Floorball_Api_Icons::get( 'error' ) . ' ' . esc_html( $message ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon markup sanitized via wp_kses() in Swiss_Floorball_Api_Icons::get().
	}

	/**
	 * Render the notice for a failed or empty API response.
	 *
	 * Explains when the chosen API source does not offer the requested endpoint, so
	 * administrators know to change the source instead of suspecting a broken plugin.
	 *
	 * @since 1.1.0
	 *
	 * @param array|WP_Error $api_response Response returned by the client.
	 * @return void
	 */
	private static function render_fetch_error( $api_response ) {
		if ( is_wp_error( $api_response ) && 'swfl_endpoint_unavailable' === $api_response->get_error_code() ) {
			self::render_error_notice( __( 'Diese Daten sind in der kostenlosen API nicht verfügbar. Sie sind nur über die Partner-API verfügbar, die in den Plugin-Einstellungen aktiviert werden kann.', 'swiss-floorball-api' ) );
			return;
		}
		if ( is_wp_error( $api_response ) && in_array( $api_response->get_error_code(), array( 'swfl_partner_credentials', 'swfl_partner_auth' ), true ) ) {
			self::render_error_notice( __( 'Anmeldung an der Partner-API fehlgeschlagen. Bitte API Key und Secret in den Plugin-Einstellungen prüfen.', 'swiss-floorball-api' ) );
			return;
		}
		self::render_error_notice( __( 'Daten konnten nicht geladen werden.', 'swiss-floorball-api' ) );
	}

	/**
	 * Get the API client instance.
	 *
	 * @return Swiss_Floorball_API_Client
	 */
	private static function get_client() {
		if ( null === self::$client ) {
			self::$client = new Swiss_Floorball_API_Client();
		}
		return self::$client;
	}

	/**
	 * Safely read a text entry from a games grid row.
	 *
	 * Cancelled games come without a time, so cells may hold fewer text entries than usual.
	 *
	 * @since 1.0.0
	 *
	 * @param array $row   Grid row from the API.
	 * @param int   $cell  Cell index.
	 * @param int   $index Text index within the cell.
	 * @return string Raw text, or an empty string if missing.
	 */
	private static function get_cell_text( $row, $cell, $index ) {
		if ( isset( $row['cells'][ $cell ]['text'][ $index ] ) && is_scalar( $row['cells'][ $cell ]['text'][ $index ] ) ) {
			return (string) $row['cells'][ $cell ]['text'][ $index ];
		}
		return '';
	}

	/**
	 * Get the configured season, falling back to the current year.
	 *
	 * An unset or empty option is stored as 0, which get_option() defaults would not
	 * catch, so the fallback is applied here. The year uses the site timezone.
	 *
	 * @since 1.0.0
	 *
	 * @return int Season year.
	 */
	public static function get_current_season() {
		$season = absint( get_option( 'swissfloorball_actual_season', 0 ) );
		if ( 0 === $season ) {
			$season = absint( current_time( 'Y' ) );
		}
		return $season;
	}

	/**
	 * Retrieve all teams of the club (Admin)
	 *
	 * @param int|string $swissfloorball_club_number Club ID.
	 * @return void
	 */
	public static function render_club_teams( $swissfloorball_club_number ) {
		if ( empty( $swissfloorball_club_number ) ) {
			return;
		}

		$client       = self::get_client();
		$api_response = $client->fetch_data( 'clubs/' . $swissfloorball_club_number . '/statistics' );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			self::render_fetch_error( $api_response );
			return;
		}

		$rows           = $api_response['data']['regions'][0]['rows'];
		$team_count     = count( $rows );
		$title          = isset( $api_response['data']['title'] ) ? $api_response['data']['title'] : '';
		$current_season = self::get_current_season();

		?>
		<h2 class="sfa-section-title"><?php Swiss_Floorball_Api_Icons::render( 'group' ); ?> <?php echo esc_html( $title ); ?></h2>
		<?php /* translators: %d: number of teams registered with Swiss Floorball. */ ?>
		<p><?php printf( esc_html__( 'Teams bei Swiss Floorball angemeldet: %d', 'swiss-floorball-api' ), intval( $team_count ) ); ?></p>

		<div class="sfa-table-wrap"><table class="sfa-data-table">
			<caption class="sfa-visually-hidden"><?php esc_html_e( 'Teams des Clubs', 'swiss-floorball-api' ); ?></caption>
			<thead>
				<tr>
					<th scope="col" class="sfa-col-minor"><?php esc_html_e( 'Team ID', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Team Name', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Meisterschaft', 'swiss-floorball-api' ); ?></th>
					<th scope="col" class="sfa-col-minor"><?php esc_html_e( 'League ID', 'swiss-floorball-api' ); ?></th>
					<th scope="col" class="sfa-col-minor"><?php esc_html_e( 'Game Class ID', 'swiss-floorball-api' ); ?></th>
					<th scope="col" class="sfa-col-minor"><?php esc_html_e( 'Group ID', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Cup', 'swiss-floorball-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $rows as $row ) :
					$team_id       = $row['team_id'];
					$league_id     = '-';
					$game_class_id = '-';
					$group_id      = '-';

					// Fetch team games to get league/game_class/group IDs.
					$games_response = $client->fetch_data(
						'games',
						array(
							'mode'    => 'team',
							'team_id' => $team_id,
							'season'  => $current_season,
						)
					);

					if ( ! is_wp_error( $games_response ) && isset( $games_response['data']['tabs'][0]['link']['ids'] ) ) {
						$ids = $games_response['data']['tabs'][0]['link']['ids'];
						// IDs format: [season, league, game_class, group].
						if ( count( $ids ) >= 4 ) {
							$league_id     = $ids[1];
							$game_class_id = $ids[2];
							$group_id      = $ids[3];
						}
					}
					?>
					<tr>
						<td class="sfa-col-minor" data-label="<?php esc_attr_e( 'Team ID', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $team_id ); ?></td>
						<td data-label="<?php esc_attr_e( 'Team Name', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $row['cells'][0]['text'][0] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Meisterschaft', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $row['cells'][1]['text'][0] ); ?></td>
						<td class="sfa-col-minor" data-label="<?php esc_attr_e( 'League ID', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $league_id ); ?></td>
						<td class="sfa-col-minor" data-label="<?php esc_attr_e( 'Game Class ID', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $game_class_id ); ?></td>
						<td class="sfa-col-minor" data-label="<?php esc_attr_e( 'Group ID', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $group_id ); ?></td>
						<td data-label="<?php esc_attr_e( 'Cup', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $row['cells'][2]['text'][0] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table></div>
		<?php
	}

	/**
	 * Retrieve all teams of the club (Public)
	 *
	 * @param int|string $swissfloorball_club_number Club ID.
	 * @return void
	 */
	public static function render_club_teams_pub( $swissfloorball_club_number ) {
		if ( empty( $swissfloorball_club_number ) ) {
			return;
		}

		$client       = self::get_client();
		$api_response = $client->fetch_data( 'clubs/' . $swissfloorball_club_number . '/statistics' );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			self::render_fetch_error( $api_response );
			return;
		}

		$rows  = $api_response['data']['regions'][0]['rows'];
		$title = isset( $api_response['data']['title'] ) ? $api_response['data']['title'] : '';

		?>
		<h3 class="sfa-section-title"><?php Swiss_Floorball_Api_Icons::render( 'group' ); ?> <?php echo esc_html( $title ); ?></h3>
		<div class="sfa-table-wrap"><table class="sfa-data-table">
			<caption class="sfa-visually-hidden"><?php esc_html_e( 'Platzierungen der Club-Teams', 'swiss-floorball-api' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Team Name', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Meisterschaft Platzierung', 'swiss-floorball-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Team Name', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $row['cells'][0]['text'][0] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Meisterschaft Platzierung', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $row['cells'][1]['text'][0] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table></div>
		<?php
	}

	/**
	 * Get club games.
	 *
	 * @param int|string $swissfloorball_club_number Club ID.
	 * @param int|string $season Season ID.
	 * @param bool       $is_backend Optional. Whether to render for backend. Default false.
	 * @return void
	 */
	public static function render_club_games( $swissfloorball_club_number, $season, $is_backend = false ) {
		Swiss_Floorball_API_Widgets::render_admin_games( 'club', absint( $swissfloorball_club_number ), absint( $season ), __( 'Clubspiele', 'swiss-floorball-api' ), __( 'Spiele des Clubs', 'swiss-floorball-api' ) );
	}

	/**
	 * Get team games.
	 *
	 * @param int|string $swissfloorball_team_number Team ID.
	 * @param int|string $season Season ID.
	 * @param bool       $is_backend Optional. Whether to render for backend (includes Game ID). Default false.
	 * @return void
	 */
	public static function render_team_games( $swissfloorball_team_number, $season, $is_backend = false ) {
		Swiss_Floorball_API_Widgets::render_admin_games( 'team', absint( $swissfloorball_team_number ), absint( $season ), __( 'Teamspiele', 'swiss-floorball-api' ), __( 'Spiele des Teams', 'swiss-floorball-api' ) );
	}

	/**
	 * Get leagues.
	 *
	 * @return void
	 */
	public static function render_leagues() {
		$client       = self::get_client();
		$api_response = $client->fetch_data( 'leagues' );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['entries'] ) ) {
			self::render_fetch_error( $api_response );
			return;
		}

		$entries = $api_response['entries'];
		$title   = isset( $api_response['text'] ) ? $api_response['text'] : '';

		?>
		<h2 class="sfa-section-title"><?php Swiss_Floorball_Api_Icons::render( 'trophy' ); ?> <?php echo esc_html( $title ); ?></h2>

		<div class="sfa-table-wrap"><table class="sfa-data-table">
			<caption class="sfa-visually-hidden"><?php esc_html_e( 'Ligen', 'swiss-floorball-api' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Name', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'League Nummer', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Game_class', 'swiss-floorball-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $entries as $entry ) {
					$name       = $entry['text'];
					$league     = $entry['set_in_context']['league'];
					$game_class = $entry['set_in_context']['game_class'];
					?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Name', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $name ); ?></td>
						<td data-label="<?php esc_attr_e( 'League Nummer', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $league ); ?></td>
						<td data-label="<?php esc_attr_e( 'Game_class', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $game_class ); ?></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table></div>
		<?php
	}

	/**
	 * Get seasons.
	 *
	 * @return void
	 */
	public static function render_seasons() {
		$client       = self::get_client();
		$api_response = $client->fetch_data( 'seasons' );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['entries'] ) ) {
			self::render_fetch_error( $api_response );
			return;
		}

		$entries = $api_response['entries'];
		$title   = isset( $api_response['text'] ) ? $api_response['text'] : '';

		?>
		<h2 class="sfa-section-title"><?php Swiss_Floorball_Api_Icons::render( 'calendar' ); ?> <?php echo esc_html( $title ); ?></h2>

		<div class="sfa-table-wrap"><table class="sfa-data-table">
			<caption class="sfa-visually-hidden"><?php esc_html_e( 'Saisons', 'swiss-floorball-api' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Club Name', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Season_id', 'swiss-floorball-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $entries as $entry ) {
					$name      = $entry['text'];
					$season_id = $entry['set_in_context']['season'];
					?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Club Name', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $name ); ?></td>
						<td data-label="<?php esc_attr_e( 'Season_id', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $season_id ); ?></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table></div>
		<?php
	}

	/**
	 * Get clubs.
	 *
	 * @return void
	 */
	public static function render_clubs() {
		$client       = self::get_client();
		$api_response = $client->fetch_data( 'clubs' );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['entries'] ) ) {
			self::render_fetch_error( $api_response );
			return;
		}

		$entries = $api_response['entries'];
		$title   = isset( $api_response['text'] ) ? $api_response['text'] : '';

		?>
		<h2 class="sfa-section-title"><?php Swiss_Floorball_Api_Icons::render( 'group' ); ?> <?php echo esc_html( $title ); ?></h2>

		<div class="sfa-table-wrap"><table class="sfa-data-table">
			<caption class="sfa-visually-hidden"><?php esc_html_e( 'Clubs', 'swiss-floorball-api' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Club Name', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Club_id', 'swiss-floorball-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $entries as $entry ) {
					$name    = $entry['text'];
					$club_id = $entry['set_in_context']['club_id'];
					?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Club Name', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $name ); ?></td>
						<td data-label="<?php esc_attr_e( 'Club_id', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $club_id ); ?></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table></div>
		<?php
	}

	/**
	 * Get calendars (Webcal link).
	 *
	 * @param int|null $team_id Team ID.
	 * @param int|null $club_id Club ID.
	 * @param int|null $season Season ID.
	 * @param int|null $league League ID.
	 * @param int|null $game_class Game Class ID.
	 * @param int|null $group Group ID.
	 * @return void
	 */
	public static function render_calendars( $team_id = null, $club_id = null, $season = null, $league = null, $game_class = null, $group = null ) {
		$is_backend = false;
		// 1. Construct WebCal Link URL (keep existing logic)
		$params = array();

		// Determine mode for games API.
		$games_params = array();
		$mode         = '';

		if ( $team_id ) {
			$params['team_id']       = $team_id;
			$mode                    = 'team';
			$games_params['team_id'] = $team_id;
		} elseif ( $club_id ) {
			$params['club_id']       = $club_id;
			$mode                    = 'club';
			$games_params['club_id'] = $club_id;
		} elseif ( $season && $league && $game_class && $group ) {
			$params['season']     = $season;
			$params['league']     = $league;
			$params['game_class'] = $game_class;
			$params['group']      = $group;
			$mode                 = 'group'; // Assuming group mode exists or fallback to filtering? API docs for 'games' usually support team/club. Let's try to infer or use what we have.
			// If specific group params are passed, we might not be able to fetch "games" easily without a specific mode if the API doesn't support it directly for groups in the same way.
			// However, the user request specifically mentioned team_id example.
			// Let's assume for now we try to fetch games if we have a team or club.
			// If we only have group params, we might need a different endpoint or strategy, but let's focus on team/club first as per request.
		} else {
			echo '<p>' . esc_html__( 'Fehlende Parameter für Kalender.', 'swiss-floorball-api' ) . '</p>';
			return;
		}

		// The feed is generated by the plugin itself (REST route), as the former calendar export no longer exists.
		$url = add_query_arg( $params, rest_url( 'swfl/v1/calendar' ) );

		// 2. Fetch Games if possible
		if ( ! empty( $mode ) ) {
			$games_params['mode'] = $mode;
			// If season is not set in params but needed for games, we might need to default it.
			// The shortcode might not pass season. If not, we should probably use the current season option.
			if ( empty( $season ) ) {
				$season = self::get_current_season();
			}
			$games_params['season'] = $season;

			// If we are in group mode (custom params), we might need to pass them to games endpoint if supported,
			// or we might skip games display if not supported.
			// For now, let's proceed with team/club which are the main use cases.

			$client       = self::get_client();
			$api_response = $client->fetch_data( 'games', $games_params );

			if ( ! is_wp_error( $api_response ) && isset( $api_response['data']['regions'][0]['rows'] ) ) {
				$rows = $api_response['data']['regions'][0]['rows'];

				// Filter for upcoming games.
				$upcoming_games = array();
				$now            = time();

				foreach ( $rows as $row ) {
					$date_str = self::get_cell_text( $row, 0, 0 ); // e.g. "26.11.2025" or "Abgesagt".
					$time_str = self::get_cell_text( $row, 0, 1 ); // e.g. "20:00"; missing for cancelled games.

					if ( '' !== $time_str ) {
						$dt = DateTime::createFromFormat( 'd.m.Y H:i', $date_str . ' ' . $time_str );
					} else {
						// Without a time, compare against the end of the day.
						$dt = DateTime::createFromFormat( 'd.m.Y H:i:s', $date_str . ' 23:59:59' );
					}

					// Unparseable dates (e.g. cancelled games) are shown instead of silently dropped.
					if ( ! $dt || $dt->getTimestamp() >= $now ) {
						$upcoming_games[] = $row;
					}
				}

				// Render Table if we have upcoming games.
				if ( ! empty( $upcoming_games ) ) {
					?>
					<h3 class="sfa-calendar-title"><?php Swiss_Floorball_Api_Icons::render( 'schedule' ); ?> <?php esc_html_e( 'Nächste Spiele', 'swiss-floorball-api' ); ?></h3>
					<div class="sfa-table-wrap"><table class="sfa-data-table sfa-calendar-table">
						<caption class="sfa-visually-hidden"><?php esc_html_e( 'Spielkalender', 'swiss-floorball-api' ); ?></caption>
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Datum', 'swiss-floorball-api' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Heim', 'swiss-floorball-api' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Gast', 'swiss-floorball-api' ); ?></th>
								<th scope="col" class="sfa-col-tiny"><?php esc_html_e( 'Ort', 'swiss-floorball-api' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $upcoming_games as $game ) :
								$date           = self::get_cell_text( $game, 0, 0 );
								$time           = self::get_cell_text( $game, 0, 1 );
								$place_location = self::get_cell_text( $game, 1, 0 );
								$place_name     = self::get_cell_text( $game, 1, 1 ); // Sometimes location is split.

								// Read team names from the games list to avoid one detail request per game.
								if ( 'club' === $mode ) {
									$team_home = self::get_cell_text( $game, 3, 0 );
									$team_away = self::get_cell_text( $game, 4, 0 );
								} else {
									$team_home = self::get_cell_text( $game, 2, 0 );
									$team_away = self::get_cell_text( $game, 3, 0 );
								}

								?>
								<tr>
									<td data-label="<?php esc_attr_e( 'Datum', 'swiss-floorball-api' ); ?>"><?php echo esc_html( trim( $date . ' ' . $time ) ); ?></td>
									<td data-label="<?php esc_attr_e( 'Heim', 'swiss-floorball-api' ); ?>">
										<?php echo esc_html( $team_home ); ?>
									</td>
									<td data-label="<?php esc_attr_e( 'Gast', 'swiss-floorball-api' ); ?>">
										<?php echo esc_html( $team_away ); ?>
									</td>
									<td class="sfa-col-tiny" data-label="<?php esc_attr_e( 'Ort', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $place_location ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table></div>
					<?php
				} else {
					echo '<p>' . esc_html__( 'Keine kommenden Spiele gefunden.', 'swiss-floorball-api' ) . '</p>';
				}
			}
		}

		?>
		<div class="sfa-calendar-link">
			<a href="<?php echo esc_url( preg_replace( '#^https?://#', 'webcal://', $url ), array( 'webcal' ) ); ?>" target="_blank" rel="noopener noreferrer" class="sfa-calendar-subscribe">
				<?php Swiss_Floorball_Api_Icons::render( 'calendar' ); ?> <?php esc_html_e( 'Kalender abonnieren', 'swiss-floorball-api' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Get cups.
	 *
	 * @return void
	 */
	public static function render_cups() {
		$client       = self::get_client();
		$api_response = $client->fetch_data( 'cups' );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			self::render_fetch_error( $api_response );
			return;
		}

		$rows  = $api_response['data']['regions'][0]['rows'];
		$title = isset( $api_response['data']['title'] ) ? $api_response['data']['title'] : 'Cups';

		?>
		<h2 class="sfa-section-title"><?php Swiss_Floorball_Api_Icons::render( 'trophy' ); ?> <?php echo esc_html( $title ); ?></h2>
		<div class="sfa-table-wrap"><table class="sfa-data-table">
			<caption class="sfa-visually-hidden"><?php esc_html_e( 'Cups', 'swiss-floorball-api' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Runde', 'swiss-floorball-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Runde', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $row['cells'][0]['text'][0] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table></div>
		<?php
	}

	/**
	 * Get groups.
	 *
	 * @param int|string $season Season ID.
	 * @param int|string $league League ID.
	 * @param int|string $game_class Game Class ID.
	 * @return void
	 */
	public static function render_groups( $season, $league, $game_class ) {
		$client       = self::get_client();
		$api_response = $client->fetch_data(
			'groups',
			array(
				'season'     => $season,
				'league'     => $league,
				'game_class' => $game_class,
				'format'     => 'dropdown',
			)
		);

		if ( is_wp_error( $api_response ) || ! isset( $api_response['entries'] ) ) {
			self::render_fetch_error( $api_response );
			return;
		}

		$entries = $api_response['entries'];
		$title   = isset( $api_response['text'] ) ? $api_response['text'] : 'Gruppen';

		?>
		<h2 class="sfa-section-title"><?php Swiss_Floorball_Api_Icons::render( 'group' ); ?> <?php echo esc_html( $title ); ?></h2>
		<div class="sfa-table-wrap"><table class="sfa-data-table">
			<caption class="sfa-visually-hidden"><?php esc_html_e( 'Gruppen', 'swiss-floorball-api' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Gruppe', 'swiss-floorball-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $entries as $entry ) : ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Gruppe', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $entry['text'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table></div>
		<?php
	}

	/**
	 * Get teams.
	 *
	 * @return void
	 */
	public static function render_teams() {
		$client       = self::get_client();
		$api_response = $client->fetch_data( 'teams' );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			self::render_fetch_error( $api_response );
			return;
		}

		$rows  = $api_response['data']['regions'][0]['rows'];
		$title = isset( $api_response['data']['title'] ) ? $api_response['data']['title'] : 'Teams';

		?>
		<h2 class="sfa-section-title"><?php Swiss_Floorball_Api_Icons::render( 'group' ); ?> <?php echo esc_html( $title ); ?></h2>
		<div class="sfa-table-wrap"><table class="sfa-data-table">
			<caption class="sfa-visually-hidden"><?php esc_html_e( 'Teams', 'swiss-floorball-api' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Name', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Website', 'swiss-floorball-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $rows as $row ) {
					$name    = $row['cells'][0]['text'][0];
					$website = isset( $row['cells'][2]['url']['href'] ) ? $row['cells'][2]['url']['href'] : '';
					?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Name', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $name ); ?></td>
						<td data-label="<?php esc_attr_e( 'Website', 'swiss-floorball-api' ); ?>"><a href="<?php echo esc_url( $website ); ?>" target="_blank"><?php echo esc_html( $website ); ?></a></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table></div>
		<?php
	}

	/**
	 * Get club games callout.
	 *
	 * @param int|string $swissfloorball_club_number Club ID.
	 * @param int|string $season Season ID.
	 * @return void
	 */
	public static function render_club_games_callout( $swissfloorball_club_number, $season ) {
		$client       = self::get_client();
		$api_response = $client->fetch_data(
			'games',
			array(
				'mode'    => 'club',
				'club_id' => $swissfloorball_club_number,
				'season'  => $season,
			)
		);

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			return;
		}

		$rows  = $api_response['data']['regions'][0]['rows'];
		$count = count( $rows );

		$limit = max( 0, $count - 3 );

		for ( $i = 0; $i < $limit; $i++ ) {
			if ( ! isset( $rows[ $i ]['link']['ids'][0] ) ) {
				continue;
			}
			$game_id      = $rows[ $i ]['link']['ids'][0];
			$game_details = self::get_gamedetails( $game_id );

			if ( ! $game_details ) {
				continue;
			}

			$result     = explode( ':', $game_details[6] );
			$score_home = isset( $result[0] ) ? $result[0] : '-';
			$score_away = isset( $result[1] ) ? $result[1] : '-';

			?>
			<div class="card">
				<div class="row card-body">
					<div class="col">
						<img src="<?php echo esc_url( $game_details[3] ); ?>" alt="Vereinslogo" class="img-fluid rounded-start">
						<h5 class="card-title text-center"><?php echo esc_html( $score_home ); ?></h5>
					</div>
					<div class="col-6">
						<div class="card-body text-center">
							<p class="card-text"><?php echo esc_html( $game_details[1] ); ?></p>
							<p class="card-text"><?php echo esc_html( $game_details[9] ); ?></p>
							<p class="card-text"><?php echo esc_html( $game_details[7] . ', ' . $game_details[8] ); ?></p>
						</div>
					</div>
					<div class="col">
						<img src="<?php echo esc_url( $game_details[5] ); ?>" alt="Vereinslogo" class="img-fluid rounded-start">
						<h5 class="card-title text-center"><?php echo esc_html( $score_away ); ?></h5>
					</div>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Get club games cards.
	 *
	 * @param int|string $swissfloorball_club_number Club ID.
	 * @param int|string $season Season ID.
	 * @return void
	 */
	public static function render_club_games_cards( $swissfloorball_club_number, $season ) {
		$client       = self::get_client();
		$api_response = $client->fetch_data(
			'games',
			array(
				'mode'    => 'club',
				'club_id' => $swissfloorball_club_number,
				'season'  => $season,
			)
		);

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			self::render_fetch_error( $api_response );
			return;
		}

		$rows  = $api_response['data']['regions'][0]['rows'];
		$count = count( $rows );

		?>
		<div class="container">
			<div class="row">
				<?php
				$start = 2;
				$end   = max( 0, $count - 2 );

				for ( $i = $start; $i < $end; $i++ ) {
					if ( ! isset( $rows[ $i ]['link']['ids'][0] ) ) {
						continue;
					}
					$game_id      = $rows[ $i ]['link']['ids'][0];
					$game_details = self::get_gamedetails( $game_id );

					if ( ! $game_details ) {
						continue;
					}
					?>
					<div class="col-sm-6 md-4 mb-3">
						<div class="card">
							<div class="col">
								<div class="text-center">
									<h5><?php echo esc_html( $game_details[0] ); ?></h5>
									<div class="sfa-table-wrap"><table class="sfa-data-table">
										<caption class="sfa-visually-hidden"><?php esc_html_e( 'Spielpaarung', 'swiss-floorball-api' ); ?></caption>
										<tr>
											<th scope="row"></th>
											<td><img src="<?php echo esc_url( $game_details[3] ); ?>" alt="Vereinslogo" class="img-fluid rounded-start sfa-card-logo"></td>
											<td>
												<?php echo esc_html( $game_details[6] ); ?><br>
												<p><?php echo esc_html( $game_details[7] . ', ' . $game_details[8] ); ?></p>
											</td>
											<td><img src="<?php echo esc_url( $game_details[5] ); ?>" alt="Vereinslogo" class="img-fluid rounded-start sfa-card-logo"></td>
										</tr>
									</table></div>
									<p><?php echo esc_html( $game_details[1] ); ?></p>
								</div>
							</div>
						</div>
					</div>
					<?php
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get team details image.
	 *
	 * @param int|string $team_id Team ID.
	 * @return string
	 */
	public static function get_teamdetails_image( $team_id ) {
		$client       = self::get_client();
		$api_response = $client->fetch_data( 'teams/' . $team_id );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'][0]['cells'][1]['image']['url'] ) ) {
			return '';
		}

		return $api_response['data']['regions'][0]['rows'][0]['cells'][1]['image']['url'];
	}

	/**
	 * Get game details.
	 *
	 * @param int|string $game_id Game ID.
	 * @return array|false
	 */
	public static function get_gamedetails( $game_id ) {
		$client       = self::get_client();
		$api_response = $client->fetch_data( 'games/' . $game_id );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'][0]['cells'] ) ) {
			return false;
		}

		$cells = $api_response['data']['regions'][0]['rows'][0]['cells'];

		$gamedetails_team_home_image    = isset( $cells[0]['image']['url'] ) ? $cells[0]['image']['url'] : '';
		$gamedetails_team_home_teamname = isset( $cells[1]['text'][0] ) ? $cells[1]['text'][0] : '';
		$gamedetails_team_away_image    = isset( $cells[2]['image']['url'] ) ? $cells[2]['image']['url'] : '';
		$gamedetails_team_away_teamname = isset( $cells[3]['text'][0] ) ? $cells[3]['text'][0] : '';
		$gamedetails_result             = isset( $cells[4]['text'][0] ) ? $cells[4]['text'][0] : '';
		$gamedetails_playdate           = isset( $cells[5]['text'][0] ) ? $cells[5]['text'][0] : '';
		$gamedetails_playtime           = isset( $cells[6]['text'][0] ) ? $cells[6]['text'][0] : '';
		$gamedetails_venue              = isset( $cells[7]['text'][0] ) ? $cells[7]['text'][0] : '';

		$gamedetails_title    = isset( $api_response['data']['title'] ) ? $api_response['data']['title'] : '';
		$gamedetails_subtitle = isset( $api_response['data']['subtitle'] ) ? $api_response['data']['subtitle'] : '';

		return array(
			$gamedetails_title,
			$gamedetails_subtitle,
			$gamedetails_team_home_teamname,
			$gamedetails_team_home_image,
			$gamedetails_team_away_teamname,
			$gamedetails_team_away_image,
			$gamedetails_result,
			$gamedetails_playdate,
			$gamedetails_playtime,
			$gamedetails_venue,
		);
	}

	/**
	 * Render game details table.
	 *
	 * @param int|string $game_id Game ID.
	 * @return void
	 */
	public static function render_game_details_table( $game_id ) {
		$client       = self::get_client();
		$api_response = $client->fetch_data( 'games/' . $game_id );

		if ( is_wp_error( $api_response ) ) {
			self::render_fetch_error( $api_response );
			return;
		}

		if ( isset( $api_response['data'] ) ) {
			echo '<div class="sfa-card">';

			// Title/Header.
			$title = isset( $api_response['data']['title'] ) ? $api_response['data']['title'] : 'Match Details';
			echo '<h3>' . esc_html( $title ) . '</h3>';
			if ( ! empty( $api_response['data']['subtitle'] ) && is_string( $api_response['data']['subtitle'] ) ) {
				echo '<p class="sfa-widget__subtitle">' . esc_html( $api_response['data']['subtitle'] ) . '</p>';
			}

			if ( isset( $api_response['data']['regions'][0]['rows'] ) ) {
				foreach ( $api_response['data']['regions'] as $region ) {
					if ( ! empty( $region['title'] ) ) {
						echo '<h4 class="sfa-region-title">' . esc_html( $region['title'] ) . '</h4>';
					}
					if ( isset( $region['rows'] ) ) {
						echo '<div class="sfa-table-container sfa-table-container-flat">';
						echo '<div class="sfa-table-wrap"><table class="sfa-data-table"><caption class="sfa-visually-hidden">' . esc_html__( 'Spieldetails', 'swiss-floorball-api' ) . '</caption>';

						// Check for headers.
						$headers = isset( $api_response['data']['headers'] ) ? $api_response['data']['headers'] : null;
						if ( ! empty( $headers ) ) {
							echo '<thead><tr>';
							foreach ( $headers as $header ) {
								$header_text = isset( $header['text'] ) ? $header['text'] : ( is_string( $header ) ? $header : '' );
								echo '<th scope="col">' . esc_html( $header_text ) . '</th>';
							}
							echo '</tr></thead>';
						}

						foreach ( $region['rows'] as $row ) {
							echo '<tr>';
							if ( isset( $row['cells'] ) ) {
								foreach ( $row['cells'] as $cell ) {
									echo '<td>';
									// Check if cell contains an image.
									if ( isset( $cell['image'] ) ) {
										$img_url = isset( $cell['image']['url'] ) ? $cell['image']['url'] : '';
										$img_alt = isset( $cell['image']['alt'] ) ? $cell['image']['alt'] : '';
										if ( ! empty( $img_url ) ) {
											echo '<img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $img_alt ) . '" class="sfa-cell-image">';
										}
									}
									// Display text content.
									if ( isset( $cell['text'] ) ) {
										foreach ( $cell['text'] as $text ) {
											echo esc_html( $text ) . '<br>';
										}
									}
									echo '</td>';
								}
							}
							echo '</tr>';
						}
						echo '</table></div>';
						echo '</div>';
					}
				}
			} else {
				echo '<pre>' . esc_html( print_r( $api_response, true ) ) . '</pre>'; // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Fallback dump of an unknown response shape.
			}

			echo '</div>';
		} else {
			echo '<div class="sfa-empty-state"><p class="sfa-empty-state-text">' . esc_html__( 'Keine Details verfügbar.', 'swiss-floorball-api' ) . '</p></div>';
		}
	}

	/**
	 * Get team ranking.
	 *
	 * @param int|string $season Season ID.
	 * @param int|string $league League ID.
	 * @param int|string $game_class Game Class ID.
	 * @param int|string $group Group ID.
	 * @return void
	 */
	public static function render_team_ranking( $season, $league, $game_class, $group ) {
		$client       = self::get_client();
		$api_response = $client->fetch_data(
			'rankings',
			array(
				'season'     => $season,
				'league'     => $league,
				'game_class' => $game_class,
				'group'      => $group,
			)
		);

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			self::render_fetch_error( $api_response );
			return;
		}

		$rows  = $api_response['data']['regions'][0]['rows'];
		$title = isset( $api_response['data']['title'] ) ? $api_response['data']['title'] : 'Rangliste';

		?>
		<h3 class="sfa-section-title"><?php Swiss_Floorball_Api_Icons::render( 'chart' ); ?> <?php echo esc_html( $title ); ?></h3>
		<div class="sfa-table-wrap"><table class="sfa-data-table">
			<caption class="sfa-visually-hidden"><?php esc_html_e( 'Rangliste', 'swiss-floorball-api' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Rang', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Team', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Spiele', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Tordifferenz', 'swiss-floorball-api' ); ?></th>
					<th scope="col" class="sfa-align-right"><?php esc_html_e( 'Punkte', 'swiss-floorball-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $rows as $row ) {
					$rank      = isset( $row['cells'][0]['text'][0] ) ? $row['cells'][0]['text'][0] : '';
					$team      = isset( $row['cells'][2]['text'][0] ) ? $row['cells'][2]['text'][0] : '';
					$games     = isset( $row['cells'][3]['text'][0] ) ? $row['cells'][3]['text'][0] : '';
					$goal_diff = isset( $row['cells'][10]['text'][0] ) ? $row['cells'][10]['text'][0] : '';
					$points    = isset( $row['cells'][12]['text'][0] ) ? $row['cells'][12]['text'][0] : '';
					?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Rang', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $rank ); ?></td>
						<td data-label="<?php esc_attr_e( 'Team', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $team ); ?></td>
						<td data-label="<?php esc_attr_e( 'Spiele', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $games ); ?></td>
						<td data-label="<?php esc_attr_e( 'Tordifferenz', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $goal_diff ); ?></td>
						<td class="sfa-align-right" data-label="<?php esc_attr_e( 'Punkte', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $points ); ?></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table></div>
		<?php
	}

	/**
	 * Get rankings (alias).
	 *
	 * @param int|string $season Season ID.
	 * @param int|string $league League ID.
	 * @param int|string $game_class Game Class ID.
	 * @param int|string $group Group ID.
	 * @return void
	 */
	public static function render_rankings( $season, $league, $game_class, $group ) {
		self::render_team_ranking( $season, $league, $game_class, $group );
	}

	/**
	 * Get player details.
	 *
	 * @since 1.0.0
	 * @param int|string $player_id Player ID.
	 * @return void
	 */
	public static function render_player( $player_id ) {
		$client       = self::get_client();
		$api_response = $client->fetch_data( 'players/' . $player_id );

		if ( is_wp_error( $api_response ) ) {
			self::render_fetch_error( $api_response );
			return;
		}

		$data = isset( $api_response['data'] ) ? $api_response['data'] : $api_response;

		// The response shape was not verified live, so render by structure and never dump raw data.
		if ( is_array( $data ) && isset( $data['regions'] ) && is_array( $data['regions'] ) ) {
			$rendered = false;
			foreach ( $data['regions'] as $region ) {
				if ( empty( $region['rows'] ) || ! is_array( $region['rows'] ) ) {
					continue;
				}
				if ( ! $rendered ) {
					echo '<div class="sfa-table-wrap"><table class="sfa-data-table"><caption class="sfa-visually-hidden">' . esc_html__( 'Spielerprofil', 'swiss-floorball-api' ) . '</caption><tbody>';
					$rendered = true;
				}
				foreach ( $region['rows'] as $row ) {
					if ( empty( $row['cells'] ) || ! is_array( $row['cells'] ) ) {
						continue;
					}
					echo '<tr>';
					foreach ( $row['cells'] as $cell ) {
						$text = '';
						if ( isset( $cell['text'] ) && is_array( $cell['text'] ) ) {
							$text = implode( ' ', array_filter( $cell['text'], 'is_scalar' ) );
						}
						echo '<td>' . esc_html( $text ) . '</td>';
					}
					echo '</tr>';
				}
			}
			if ( $rendered ) {
				echo '</tbody></table></div>';
				return;
			}
		} elseif ( is_array( $data ) ) {
			$lines = array();
			foreach ( $data as $key => $value ) {
				if ( ! is_scalar( $value ) || '' === (string) $value ) {
					continue;
				}
				$lines[ $key ] = $value;
			}
			if ( ! empty( $lines ) ) {
				echo '<div class="sfa-table-wrap"><table class="sfa-data-table"><caption class="sfa-visually-hidden">' . esc_html__( 'Spielerprofil', 'swiss-floorball-api' ) . '</caption><tbody>';
				foreach ( $lines as $key => $value ) {
					echo '<tr><th scope="row">' . esc_html( str_replace( '_', ' ', (string) $key ) ) . '</th><td>' . esc_html( (string) $value ) . '</td></tr>';
				}
				echo '</tbody></table></div>';
				return;
			}
		}

		echo '<p>' . esc_html__( 'Keine Spielerdaten verfügbar.', 'swiss-floorball-api' ) . '</p>';
	}

	/**
	 * Get national players.
	 *
	 * @return void
	 */
	public static function render_national_players() {
		$client       = self::get_client();
		$api_response = $client->fetch_data( 'national_players' );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			self::render_fetch_error( $api_response );
			return;
		}

		$rows  = $api_response['data']['regions'][0]['rows'];
		$title = isset( $api_response['data']['title'] ) ? $api_response['data']['title'] : 'Nationalspieler';

		?>
		<h2 class="sfa-section-title"><?php Swiss_Floorball_Api_Icons::render( 'person' ); ?> <?php echo esc_html( $title ); ?></h2>
		<div class="sfa-table-wrap"><table class="sfa-data-table">
			<caption class="sfa-visually-hidden"><?php esc_html_e( 'Nationalspieler', 'swiss-floorball-api' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Nr', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Position', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Name', 'swiss-floorball-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $rows as $row ) {
					$nr   = isset( $row['cells'][0]['text'][0] ) ? $row['cells'][0]['text'][0] : '';
					$pos  = isset( $row['cells'][1]['text'][0] ) ? $row['cells'][1]['text'][0] : '';
					$name = isset( $row['cells'][2]['text'][0] ) ? $row['cells'][2]['text'][0] : '';
					?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Nr', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $nr ); ?></td>
						<td data-label="<?php esc_attr_e( 'Position', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $pos ); ?></td>
						<td data-label="<?php esc_attr_e( 'Name', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $name ); ?></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table></div>
		<?php
	}

	/**
	 * Get topscorers.
	 *
	 * @param int|string $season Season ID.
	 * @param int|string $league League ID.
	 * @param int|string $game_class Game Class ID.
	 * @param int|string $group Deprecated. Ignored, the topscorers/su endpoint has no group parameter.
	 * @return void
	 */
	public static function render_topscorers( $season, $league, $game_class, $group = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Kept for backwards compatibility.
		$client       = self::get_client();
		$api_response = $client->fetch_data(
			'topscorers/su',
			array(
				'season'     => $season,
				'league'     => $league,
				'game_class' => $game_class,
			)
		);

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			self::render_fetch_error( $api_response );
			return;
		}

		$rows  = $api_response['data']['regions'][0]['rows'];
		$title = isset( $api_response['data']['title'] ) ? $api_response['data']['title'] : 'Topscorer';

		?>
		<h3 class="sfa-section-title"><?php Swiss_Floorball_Api_Icons::render( 'trophy' ); ?> <?php echo esc_html( $title ); ?></h3>
		<div class="sfa-table-wrap"><table class="sfa-data-table">
			<caption class="sfa-visually-hidden"><?php esc_html_e( 'Topscorer', 'swiss-floorball-api' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Rang', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Spieler', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Team', 'swiss-floorball-api' ); ?></th>
					<th scope="col" class="sfa-align-right"><?php esc_html_e( 'Tore', 'swiss-floorball-api' ); ?></th>
					<th scope="col" class="sfa-align-right"><?php esc_html_e( 'Assists', 'swiss-floorball-api' ); ?></th>
					<th scope="col" class="sfa-align-right"><?php esc_html_e( 'Punkte', 'swiss-floorball-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $rows as $row ) {
					$rank    = isset( $row['cells'][0]['text'][0] ) ? $row['cells'][0]['text'][0] : '';
					$player  = isset( $row['cells'][1]['text'][0] ) ? $row['cells'][1]['text'][0] : '';
					$team    = isset( $row['cells'][2]['text'][0] ) ? $row['cells'][2]['text'][0] : '';
					$goals   = isset( $row['cells'][3]['text'][0] ) ? $row['cells'][3]['text'][0] : '';
					$assists = isset( $row['cells'][4]['text'][0] ) ? $row['cells'][4]['text'][0] : '';
					$points  = isset( $row['cells'][5]['text'][0] ) ? $row['cells'][5]['text'][0] : '';
					?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Rang', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $rank ); ?></td>
						<td data-label="<?php esc_attr_e( 'Spieler', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $player ); ?></td>
						<td data-label="<?php esc_attr_e( 'Team', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $team ); ?></td>
						<td class="sfa-align-right" data-label="<?php esc_attr_e( 'Tore', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $goals ); ?></td>
						<td class="sfa-align-right" data-label="<?php esc_attr_e( 'Assists', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $assists ); ?></td>
						<td class="sfa-align-right" data-label="<?php esc_attr_e( 'Punkte', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $points ); ?></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table></div>
		<?php
	}

	/**
	 * Get game events.
	 *
	 * @param int|string $game_id Game ID.
	 * @param bool       $is_backend Optional. Whether to render for backend. Default false.
	 * @return void
	 */
	public static function render_game_events( $game_id, $is_backend = false ) {
		$client       = self::get_client();
		$api_response = $client->fetch_data( 'game_events/' . $game_id );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			self::render_fetch_error( $api_response );
			return;
		}

		$rows = $api_response['data']['regions'][0]['rows'];

		?>
		<h3><?php esc_html_e( 'Match-Telegramm', 'swiss-floorball-api' ); ?></h3>
		<div class="sfa-table-wrap"><table class="sfa-data-table">
			<caption class="sfa-visually-hidden"><?php esc_html_e( 'Spielereignisse', 'swiss-floorball-api' ); ?></caption>
					<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Zeit', 'swiss-floorball-api' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Ereignis', 'swiss-floorball-api' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Goal', 'swiss-floorball-api' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Team', 'swiss-floorball-api' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $rows as $row ) {
				$time   = isset( $row['cells'][0]['text'][0] ) ? $row['cells'][0]['text'][0] : '';
				$event  = isset( $row['cells'][1]['text'][0] ) ? $row['cells'][1]['text'][0] : '';
				$player = isset( $row['cells'][3]['text'][0] ) ? $row['cells'][3]['text'][0] : '';
				$team   = isset( $row['cells'][2]['text'][0] ) ? $row['cells'][2]['text'][0] : '';
				// Extract goal score from event string if present (e.g., "Torschütze 1:0").
				$goal = '';
				if ( preg_match( '/(\d+:\d+)/', $event, $matches ) ) {
					$goal = $matches[1];
				}
				if ( ! empty( $player ) ) {
					$event .= ' - ' . $player;
				}
				?>
				<tr>
					<td data-label="<?php esc_attr_e( 'Zeit', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $time ); ?></td>
					<td data-label="<?php esc_attr_e( 'Ereignis', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $event ); ?></td>
					<td data-label="<?php esc_attr_e( 'Goal', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $goal ); ?></td>
					<td data-label="<?php esc_attr_e( 'Team', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $team ); ?></td>
				</tr>
				<?php
			}
			?>
		</tbody>
		</table></div>
		<?php
	}
}

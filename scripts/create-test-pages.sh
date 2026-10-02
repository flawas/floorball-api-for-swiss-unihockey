#!/bin/sh
# Creates one WordPress page per shortcode (slug = shortcode name) for clean screenshots.
# Runs inside the wp-cli container; pages are recreated on every run so they stay current.
set -e

links=""

create_page() {
	slug="$1"
	content="$2"
	for id in $(wp post list --post_type=page --name="$slug" --field=ID --allow-root); do
		wp post delete "$id" --force --allow-root >/dev/null
	done
	new_id=$(wp post create --post_type=page --post_title="$slug" --post_status=publish --post_name="$slug" --post_content="$content" --porcelain --allow-root)
	links="$links<!-- wp:navigation-link {\"label\":\"$slug\",\"type\":\"page\",\"id\":$new_id,\"url\":\"/$slug/\",\"kind\":\"post-type\"} /-->"
}

create_page swfl-club-games '[swfl-club-games club_id="637" season="2026"]'
create_page swfl-team-games '[swfl-team-games team_id="427892" season="2026" page_size="5"]'
create_page swfl-club-team-games '[swfl-club-team-games club_id="637" season="2026" page_size="6"]'
create_page swfl-league-games '[swfl-league-games game_class="11" league="6" season="2026" group="Gruppe 6"]'
create_page swfl-rankings '[swfl-rankings season="2026" league="6" game_class="11" group="Gruppe 6"]'
create_page swfl-mobiliar-topscorer '[swfl-mobiliar-topscorer season="2026"]'
create_page swfl-clubs '[swfl-clubs]'
create_page swfl-teams '[swfl-teams]'
create_page swfl-club-teams '[swfl-club-teams]'
create_page swfl-cups '[swfl-cups]'
create_page swfl-groups '[swfl-groups season="2026" league="6" game_class="11"]'
create_page swfl-calendars '[swfl-calendars club_id="637" season="2026"]'
create_page swfl-topscorers '[swfl-topscorers season="2026" league="6" game_class="11" group="Gruppe 6"]'
create_page swfl-player '[swfl-player player_id="1"]'
create_page swfl-national-players '[swfl-national-players]'
create_page swfl-game-events '[swfl-game-events game_id="1096214"]'

# One "Shortcodes" dropdown in the main navigation, so every page is reachable and screenshots show a tidy menu.
nav_id=$(wp post list --post_type=wp_navigation --field=ID --allow-root | head -1)
menu="<!-- wp:navigation-submenu {\"label\":\"Shortcodes\",\"type\":\"\",\"url\":\"#\",\"kind\":\"custom\"} -->$links<!-- /wp:navigation-submenu -->"
if [ -n "$nav_id" ]; then
	wp post update "$nav_id" --post_content="$menu" --allow-root
fi

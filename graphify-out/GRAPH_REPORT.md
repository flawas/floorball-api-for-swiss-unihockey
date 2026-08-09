# Graph Report - .  (2026-08-09)

## Corpus Check
- 44 files · ~168,741 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 146 nodes · 196 edges · 25 communities (20 shown, 5 thin omitted)
- Extraction: 65% EXTRACTED · 35% INFERRED · 0% AMBIGUOUS · INFERRED: 68 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_Public Display Rendering Engine|Public Display Rendering Engine]]
- [[_COMMUNITY_Admin Panel & Plugin Loader|Admin Panel & Plugin Loader]]
- [[_COMMUNITY_Public Shortcodes & Settings Field|Public Shortcodes & Settings Field]]
- [[_COMMUNITY_Project Docs & Release Assets|Project Docs & Release Assets]]
- [[_COMMUNITY_Plugin Core Bootstrap|Plugin Core Bootstrap]]
- [[_COMMUNITY_ActivationDeactivation Lifecycle|Activation/Deactivation Lifecycle]]
- [[_COMMUNITY_API Client & Fetch Layer|API Client & Fetch Layer]]
- [[_COMMUNITY_Localization (i18n)|Localization (i18n)]]
- [[_COMMUNITY_Plugin Icon Asset|Plugin Icon Asset]]

## God Nodes (most connected - your core abstractions)
1. `Swiss_Floorball_API_Display` - 38 edges
2. `is_wp_error()` - 24 edges
3. `Swiss_Floorball_Api_Public` - 20 edges
4. `Swiss_Floorball_Api_Admin` - 18 edges
5. `Swiss Floorball API WordPress Plugin` - 15 edges
6. `get_option()` - 11 edges
7. `Swiss_Floorball_Api` - 10 edges
8. `Swiss_Floorball_Api_Loader` - 6 edges
9. `__()` - 4 edges
10. `Swiss_Floorball_Api_Activator` - 3 edges

## Surprising Connections (you probably didn't know these)
- `Swiss Floorball API WordPress Plugin` --references--> `Plugin Banner 772x250`  [EXTRACTED]
  README.md → assets/banner-772x250.jpeg
- `Swiss Floorball API WordPress Plugin` --references--> `Admin Backend Screenshot`  [EXTRACTED]
  README.md → assets/screenshot-admin-backend.png
- `Swiss Floorball API WordPress Plugin` --references--> `Admin Club Backend Screenshot`  [EXTRACTED]
  README.md → assets/screenshot-admin-club-backend.png
- `Swiss Floorball API WordPress Plugin` --references--> `Calendar Backend Screenshot`  [EXTRACTED]
  README.md → assets/screenshot-calendar-backend.png
- `Swiss Floorball API WordPress Plugin` --references--> `Calendar Frontend Screenshot`  [EXTRACTED]
  README.md → assets/screenshot-calendar-frontend.png

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Release Automation Pipeline** — _github_workflows_release_yml_release_workflow, _github_workflows_release_release_doc, readme_plugin [INFERRED 0.85]
- **WordPress Admin Interface Views** — assets_screenshot_admin_backend_admin_backend, assets_screenshot_admin_club_backend_club_backend, assets_screenshot_calendar_backend_calendar_backend, assets_screenshot_teams_backend_teams_backend [INFERRED 0.85]
- **Frontend Shortcode Display Views** — assets_screenshot_calendar_frontend_calendar_frontend, assets_screenshot_ranking_frontend_ranking_frontend, readme_shortcode_system [INFERRED 0.75]

## Communities (25 total, 5 thin omitted)

### Community 1 - "Admin Panel & Plugin Loader"
Cohesion: 0.11
Nodes (3): Swiss_Floorball_Api_Admin, Swiss_Floorball_Api_Loader, __()

### Community 3 - "Project Docs & Release Assets"
Cohesion: 0.14
Nodes (18): Bug Report Issue Template, Feature Request Issue Template, Release Checklist Doc, GitHub Actions Release Workflow, Plugin Banner 772x250, Plugin Banner 914x298, Admin Backend Screenshot, Admin Club Backend Screenshot (+10 more)

### Community 5 - "Activation/Deactivation Lifecycle"
Cohesion: 0.20
Nodes (4): activateSwissFloorballApi(), deactivateSwissFloorballApi(), Swiss_Floorball_Api_Activator, Swiss_Floorball_Api_Deactivator

### Community 6 - "API Client & Fetch Layer"
Cohesion: 0.24
Nodes (5): Swiss_Floorball_API_Client, WP_Error, wp_remote_get(), wp_remote_retrieve_body(), wp_remote_retrieve_response_code()

## Knowledge Gaps
- **9 isolated node(s):** `Bug Report Issue Template`, `Feature Request Issue Template`, `Release Checklist Doc`, `GPL v2 License`, `Local Docker Dev Environment` (+4 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **5 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Swiss_Floorball_Api_Admin` connect `Admin Panel & Plugin Loader` to `Public Shortcodes & Settings Field`?**
  _High betweenness centrality (0.148) - this node is a cross-community bridge._
- **Why does `is_wp_error()` connect `Public Display Rendering Engine` to `Admin Panel & Plugin Loader`, `API Client & Fetch Layer`?**
  _High betweenness centrality (0.119) - this node is a cross-community bridge._
- **Why does `Swiss_Floorball_API_Display` connect `Public Display Rendering Engine` to `Public Shortcodes & Settings Field`?**
  _High betweenness centrality (0.114) - this node is a cross-community bridge._
- **Are the 13 inferred relationships involving `Swiss_Floorball_API_Display` (e.g. with `.get_calendars_func()` and `.get_club_games_func()`) actually correct?**
  _`Swiss_Floorball_API_Display` has 13 INFERRED edges - model-reasoned connections that need verification._
- **Are the 23 inferred relationships involving `is_wp_error()` (e.g. with `.sanitize_club_number()` and `.fetch_data()`) actually correct?**
  _`is_wp_error()` has 23 INFERRED edges - model-reasoned connections that need verification._
- **Are the 4 inferred relationships involving `Swiss Floorball API WordPress Plugin` (e.g. with `Bug Report Issue Template` and `Feature Request Issue Template`) actually correct?**
  _`Swiss Floorball API WordPress Plugin` has 4 INFERRED edges - model-reasoned connections that need verification._
- **What connects `Bug Report Issue Template`, `Feature Request Issue Template`, `Release Checklist Doc` to the rest of the system?**
  _9 weakly-connected nodes found - possible documentation gaps or missing edges._
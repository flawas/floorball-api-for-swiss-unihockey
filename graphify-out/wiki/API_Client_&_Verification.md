# API Client & Verification

> 10 nodes

## Key Concepts

- **verify_api.php** (8 connections) — `verify_api.php`
- **.fetch_data()** (5 connections) — `includes/class-floorball-api-for-swiss-unihockey-client.php`
- **Swiss_Floorball_API_Client** (2 connections) — `includes/class-floorball-api-for-swiss-unihockey-client.php`
- **wp_remote_get()** (2 connections) — `verify_api.php`
- **wp_remote_retrieve_response_code()** (2 connections) — `verify_api.php`
- **wp_remote_retrieve_body()** (2 connections) — `verify_api.php`
- **WP_Error** (2 connections) — `verify_api.php`
- **class-floorball-api-for-swiss-unihockey-client.php** (1 connections) — `includes/class-floorball-api-for-swiss-unihockey-client.php`
- **_e()** (1 connections) — `verify_api.php`
- **.__construct()** (1 connections) — `verify_api.php`

## Relationships

- [API Display Layer](API_Display_Layer.md) (2 shared connections)
- [Admin Backend](Admin_Backend.md) (1 shared connections)
- [Public Frontend & Settings](Public_Frontend_%26_Settings.md) (1 shared connections)

## Source Files

- `includes/class-floorball-api-for-swiss-unihockey-client.php`
- `verify_api.php`

## Audit Trail

- EXTRACTED: 19 (73%)
- INFERRED: 7 (27%)
- AMBIGUOUS: 0 (0%)

---

*Part of the graphify knowledge wiki. See [index](index.md) to navigate.*
<?php
/**
 * Script to convert README.md to readme.txt for WordPress Plugin Directory.
 *
 * Usage: php scripts/convert_readme.php
 */

$readme_md = __DIR__ . '/../README.md';
$readme_txt = __DIR__ . '/../readme.txt';

if ( ! file_exists( $readme_md ) ) {
    die( "Error: README.md not found.\n" );
}

$content = file_get_contents( $readme_md );

// 1. Extract Header
$header = '';
$body = $content;

// Parse metadata block (assumes it's at the top, after the title)
// We look for lines starting with **Key:** Value
$metadata = [];
$lines = explode( "\n", $content );
$new_lines = [];
$in_header = true;
$plugin_name = 'Swiss Floorball API'; // Default

foreach ( $lines as $line ) {
    // Extract Plugin Name from first H1
    if ( preg_match( '/^#\s+(.+)$/', $line, $matches ) ) {
        $plugin_name = trim( $matches[1] );
        // Remove " for WordPress" if present, as readme.txt title is usually just the plugin name
        $plugin_name = str_replace( ' for WordPress', '', $plugin_name );
        continue; // Skip H1 in body
    }

    // Skip images/badges at the top
    if ( $in_header && ( strpos( $line, '![' ) === 0 || empty( trim( $line ) ) ) ) {
        continue;
    }

    // Parse Metadata
    if ( $in_header && preg_match( '/^\*\*(.+?):\*\*\s*(.+)$/', $line, $matches ) ) {
        $key = trim( $matches[1] );
        $value = trim( $matches[2] );
        $metadata[ $key ] = $value;
        continue;
    }

    // End of header detection (first non-empty, non-metadata line)
    if ( $in_header && ! empty( trim( $line ) ) ) {
        $in_header = false;
    }

    if ( ! $in_header ) {
        $new_lines[] = $line;
    }
}

// Construct readme.txt header
$header_out = "=== $plugin_name ===\n";
foreach ( $metadata as $key => $value ) {
    $header_out .= "$key: $value\n";
}
$header_out .= "\n";

// 2. Process Body
$body = implode( "\n", $new_lines );

// Convert Headings
// ## Heading -> == Heading ==
// ### Heading -> = Heading =
// Emojis in headings are dropped: WordPress.org only recognises plain section names (e.g. "== Changelog ==").
$body = preg_replace( '/^##\s+(?:[^\p{L}\p{N}\s]+\s*)?(.+)$/mu', '== $1 ==', $body );
$body = preg_replace( '/^###\s+(?:[^\p{L}\p{N}\s]+\s*)?(.+)$/mu', '= $1 =', $body );

// Convert Screenshots section
// In readme.txt, screenshots are a list:
// 1. screenshot-1.png Description
// In README.md, we might have them differently.
// Let's assume standard list format in MD: "1. **Title**: Description" or just "1. Description"
// We need to ensure it matches what WP expects if possible, or just leave it as text if it's close enough.
// WP expects:
// == Screenshots ==
// 1. screenshot-1.png Description
// 2. screenshot-2.png Description

// For now, simple markdown list conversion is usually fine, but let's clean up bolding in lists if needed.
// $body = preg_replace( '/^\d+\.\s+\*\*(.+?)\*\*[:\s]*(.+)$/m', '$1: $2', $body ); // Optional cleanup

// 3. Write to file
$final_content = $header_out . trim( $body ) . "\n";

if ( file_put_contents( $readme_txt, $final_content ) ) {
    echo "Successfully generated readme.txt\n";
} else {
    die( "Error: Could not write to readme.txt\n" );
}

<?php
/**
 * Script to convert README.md to readme.txt for the WordPress Plugin Directory.
 *
 * The WordPress.org parser knows only a few top-level sections (Description, Installation, FAQ,
 * Screenshots, Changelog, Upgrade Notice) and renders neither tables nor images. So this script
 * maps the GitHub README onto those sections, turns tables into lists, drops images and emoji
 * and converts every FAQ entry into its own heading.
 *
 * Usage: php scripts/convert_readme.php
 */

$readme_md  = __DIR__ . '/../README.md';
$readme_txt = __DIR__ . '/../readme.txt';

if ( ! file_exists( $readme_md ) ) {
	die( "Error: README.md not found.\n" );
}

/**
 * Removes emoji and other symbols from the start of a string.
 *
 * @param string $text Text, e.g. a heading or a bold list label.
 * @return string
 */
function swfl_strip_emoji( $text ) {
	$text = preg_replace( '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}]+\s*/u', '', $text );
	return trim( $text );
}

/**
 * Splits a markdown table row into trimmed cells.
 *
 * @param string $line Table row.
 * @return string[]
 */
function swfl_table_cells( $line ) {
	return array_map( 'trim', explode( '|', trim( trim( $line ), '|' ) ) );
}

/**
 * Turns a markdown table into a bullet list, because readme.txt has no tables.
 *
 * @param string[] $rows Table lines including header and separator.
 * @return string[] Lines of the list.
 */
function swfl_table_to_list( array $rows ) {
	$header = swfl_table_cells( array_shift( $rows ) );
	array_shift( $rows ); // Separator row.
	$out = array();

	foreach ( $rows as $row ) {
		$cells = swfl_table_cells( $row );
		$label = $cells[0];
		$rest  = array_slice( $cells, 1 );

		// Availability matrix: list the columns that carry a check mark.
		if ( count( $rest ) > 0 && 0 === count( array_diff( $rest, array( '✅', '❌' ) ) ) ) {
			$yes = array();
			foreach ( $rest as $i => $cell ) {
				if ( '✅' === $cell ) {
					$yes[] = $header[ $i + 1 ];
				}
			}
			$out[] = '* ' . $label . ' – available with: ' . implode( ', ', $yes );
			continue;
		}

		$line = '* ' . ( 0 === strpos( $label, '`' ) ? $label : '**' . $label . '**' );
		if ( isset( $header[1] ) && 'Type' === $header[1] ) {
			$line .= ' (' . $rest[0] . ')';
			$rest  = array_slice( $rest, 1 );
		}
		if ( isset( $rest[0] ) ) {
			$line .= ' – ' . $rest[0];
		}
		if ( isset( $rest[1] ) && 'None' !== $rest[1] ) {
			$line .= ' Attributes: ' . $rest[1] . '.';
		}
		$out[] = $line;
	}

	return $out;
}

/**
 * Converts markdown body text: tables to lists, fenced code to indented code, no images or emoji.
 *
 * @param string[] $lines Lines of one section.
 * @return string[]
 */
function swfl_clean_body( array $lines ) {
	$out      = array();
	$in_code  = false;
	$table    = array();
	$previous = '';
	$count    = count( $lines );

	for ( $i = 0; $i < $count; $i++ ) {
		$line = rtrim( $lines[ $i ] );

		// Fenced code becomes a tab-indented block, which renders reliably on WordPress.org.
		if ( preg_match( '/^```/', $line ) ) {
			$in_code = ! $in_code;
			if ( $in_code && '' !== end( $out ) ) {
				$out[] = '';
			}
			if ( ! $in_code ) {
				$out[] = '';
			}
			continue;
		}
		if ( $in_code ) {
			$out[] = '' === $line ? '' : "\t" . $line;
			continue;
		}

		// Table rows are collected and converted once the table ends.
		if ( preg_match( '/^\|.*\|$/', $line ) ) {
			$table[] = $line;
			if ( ! isset( $lines[ $i + 1 ] ) || ! preg_match( '/^\|.*\|\s*$/', $lines[ $i + 1 ] ) ) {
				if ( '' !== end( $out ) ) {
					$out[] = '';
				}
				$out    = array_merge( $out, swfl_table_to_list( $table ), array( '' ) );
				$table  = array();
			}
			continue;
		}

		// Images and their italic caption lines are not supported.
		if ( preg_match( '/^!\[/', $line ) ) {
			if ( isset( $lines[ $i + 1 ] ) && preg_match( '/^\*[^*].*\*$/', trim( $lines[ $i + 1 ] ) ) ) {
				++$i;
			}
			continue;
		}

		// Emoji in bold list labels, e.g. "* **🔌 Plug & Play:**".
		$line = preg_replace_callback(
			'/^(\*\s+\*\*)([^*]+)(\*\*)/u',
			function ( $m ) {
				return $m[1] . swfl_strip_emoji( $m[2] ) . $m[3];
			},
			$line
		);

		// A list needs a blank line before it.
		if ( preg_match( '/^(\*|-|\d+\.)\s/', $line ) && '' !== $previous && ! preg_match( '/^(\*|-|\d+\.)\s/', $previous ) && '' !== end( $out ) ) {
			$out[] = '';
		}

		$out[]    = $line;
		$previous = $line;
	}

	return $out;
}

/**
 * Converts sub headings inside a section to the readme.txt form.
 *
 * @param string[] $lines   Cleaned lines.
 * @param string   $level   Markdown heading prefix to convert, e.g. '###'.
 * @param string   $replace Format, %s is the heading text.
 * @return string[]
 */
function swfl_convert_headings( array $lines, $level, $replace ) {
	foreach ( $lines as $i => $line ) {
		if ( 0 === strpos( $line, $level . ' ' ) ) {
			$lines[ $i ] = sprintf( $replace, swfl_strip_emoji( substr( $line, strlen( $level ) + 1 ) ) );
		}
	}
	return $lines;
}

/**
 * Joins lines and squeezes runs of blank lines.
 *
 * @param string[] $lines Lines.
 * @return string
 */
function swfl_join( array $lines ) {
	return trim( preg_replace( "/\n{3,}/", "\n\n", implode( "\n", $lines ) ) );
}

// 1. Split into the title, the metadata block, the intro and the H2 sections.
$lines    = explode( "\n", file_get_contents( $readme_md ) );
$name     = 'Swiss Floorball API';
$metadata = array();
$intro    = array();
$sections = array();
$current  = null;

foreach ( $lines as $line ) {
	if ( null === $current ) {
		if ( preg_match( '/^#\s+(.+)$/', $line, $m ) ) {
			$name = trim( str_replace( ' for WordPress', '', $m[1] ) );
		} elseif ( preg_match( '/^\*\*(Contributors|Donate link|Tags|Requires at least|Tested up to|Requires PHP|Stable tag|License|License URI):\*\*\s*(.+?)\s*$/', $line, $m ) ) {
			$metadata[ trim( $m[1] ) ] = trim( $m[2] );
		} elseif ( preg_match( '/^##\s+(.+)$/', $line, $m ) ) {
			$current              = swfl_strip_emoji( trim( $m[1] ) );
			$sections[ $current ] = array();
		} elseif ( '' !== trim( $line ) && 0 !== strpos( $line, '![' ) && 0 !== strpos( $line, '[![' ) ) {
			$intro[] = $line;
		}
		continue;
	}
	if ( preg_match( '/^##\s+(.+)$/', $line, $m ) ) {
		$current              = swfl_strip_emoji( trim( $m[1] ) );
		$sections[ $current ] = array();
		continue;
	}
	$sections[ $current ][] = $line;
}

// The first intro line is the short description (max. 150 characters).
$short_description = array_shift( $intro );

$out = "=== $name ===\n";
foreach ( $metadata as $key => $value ) {
	$out .= "$key: $value\n";
}
$out .= "\n" . $short_description . "\n\n";

// 2. Description: intro plus the reference sections as sub headings.
$description_parts = array( 'Features', 'Shortcodes', 'Customization', 'Icons', 'Developer Hooks', 'External Services' );
$description       = swfl_clean_body( $intro );
foreach ( $description_parts as $part ) {
	if ( ! isset( $sections[ $part ] ) ) {
		continue;
	}
	$body        = swfl_convert_headings( swfl_clean_body( $sections[ $part ] ), '###', '**%s**' );
	$description = array_merge( $description, array( '', "= $part =", '' ), $body );
}
$out .= "== Description ==\n\n" . swfl_join( $description ) . "\n\n";

// 3. Installation, with its sub sections as headings.
if ( isset( $sections['Installation'] ) ) {
	$body = swfl_convert_headings( swfl_clean_body( $sections['Installation'] ), '###', '= %s =' );
	$out .= "== Installation ==\n\n" . swfl_join( $body ) . "\n\n";
}

// 4. FAQ: every "**Q: ...**" paragraph becomes a heading.
if ( isset( $sections['FAQ'] ) ) {
	$faq = swfl_join( swfl_clean_body( $sections['FAQ'] ) );
	$faq = preg_replace( '/^\*\*Q:\s*(.+?)\*\*\s*\nA:\s*/m', "= $1 =\n\n", $faq );
	$out .= "== Frequently Asked Questions ==\n\n" . $faq . "\n\n";
}

// 5. Screenshots: numbered captions. The files are assets/screenshot-N.png.
if ( isset( $sections['Screenshots'] ) ) {
	$captions = array();
	foreach ( $sections['Screenshots'] as $line ) {
		if ( preg_match( '/^(\d+)\.\s+\*\*(.+?)\*\*:\s*(.+)$/', $line, $m ) ) {
			$captions[] = $m[1] . '. ' . $m[2] . ': ' . preg_replace( '/\s*\(`[^`]+`\)\s*$/', '', trim( $m[3] ) );
		}
	}
	$out .= "== Screenshots ==\n\n" . implode( "\n", $captions ) . "\n\n";
}

// 6. Changelog: version headings become "= 2.1.1 (date) =".
if ( isset( $sections['Changelog'] ) ) {
	$changelog = swfl_convert_headings( swfl_clean_body( $sections['Changelog'] ), '###', '= %s =' );
	$changelog = array_filter( $changelog, function ( $l ) {
		return '---' !== $l && 0 !== strpos( $l, '*Developed by' );
	} );
	$changelog = preg_replace( '/^= (\d+\.\d+\.\d+) \((\d{4}-\d{2}-\d{2})\) =$/m', '= $1 – $2 =', swfl_join( $changelog ) );
	$changelog = preg_replace( '/^(= .+ =)\n(\*)/m', "$1\n\n$2", $changelog );
	$out .= "== Changelog ==\n\n" . $changelog . "\n\n";
}

// 7. Upgrade notice: only the latest breaking release has one.
if ( isset( $sections['Upgrading to 2.0.0'] ) ) {
	$out .= "== Upgrade Notice ==\n\n= 2.0.0 =\nBreaking changes: the API source, the calendar feed, shortcode attributes and the table markup changed. Read the upgrade guide at https://github.com/flawas/floorball-api-for-swiss-unihockey/blob/main/docs/migration.md before updating.\n";
}

$out = rtrim( $out ) . "\n";

if ( file_put_contents( $readme_txt, $out ) ) {
	echo "Successfully generated readme.txt\n";
} else {
	die( "Error: Could not write to readme.txt\n" );
}

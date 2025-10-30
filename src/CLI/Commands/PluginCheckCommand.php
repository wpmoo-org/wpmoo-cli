<?php
/**
 * WordPress Plugin Check command.
 *
 * Executes `wp plugin check` against the active plugin slug and pretty‑prints
 * the results with colours and a tabular summary. The command can auto‑detect
 * the WordPress installation root when `--path`/`WP_PATH` is not provided.
 *
 * @package WPMoo\CLI
 * @since 0.3.0
 * @link  https://wpmoo.org WPMoo – WordPress Micro Object-Oriented Framework.
 * @link  https://github.com/wpmoo/wpmoo GitHub Repository.
 * @license GPL-3.0-or-later
 */

namespace WPMoo\CLI\Commands;

use WPMoo\CLI\Contracts\CommandInterface;
use WPMoo\CLI\Support\Base;
use WPMoo\CLI\Console;

/**
 * Runs WP Plugin Check and renders a readable report.
 */
class PluginCheckCommand extends Base implements CommandInterface {
	/**
	 * Handle the command.
	 *
	 * Options (argv style):
	 *  --path=<WP root>       Absolute path to WordPress root.
	 *  --slug=<plugin-slug>   Plugin slug to check (defaults to detected slug).
	 *  --no-strict            Use non‑strict JSON format.
	 *  --ignore-codes=A,B     Comma‑separated list of codes to ignore.
	 *
	 * @param array<int, string> $args CLI arguments (after the command verb).
	 * @return int Exit code (0=OK, 1=errors found).
	 */
	public function handle( array $args = array() ) {
		$opts = $this->parseOptions( $args );

		$wp_path = null;
		if ( ! empty( $opts['path'] ) ) {
			$wp_path = (string) $opts['path'];
		} elseif ( getenv( 'WP_PATH' ) ) {
			$wp_path = (string) getenv( 'WP_PATH' );
		}
		// Fallback: try to auto-detect a WordPress root by walking parents and
		// common web roots (public_html, htdocs, WordPress, wp).
		if ( ! $wp_path || ! is_dir( $wp_path ) ) {
			$cwd      = getcwd();
			$start_at = $cwd ? (string) $cwd : '.';
			$detected = $this->detect_wp_root( $start_at );
			if ( $detected && is_dir( $detected ) ) {
				$wp_path = $detected;
				Console::comment( '→ Auto-detected WordPress at: ' . $wp_path );
			}
		}
		if ( ! $wp_path || ! is_dir( $wp_path ) ) {
			Console::warning( 'Set --path=<wordpress_root> or WP_PATH env.' );
			return 0; // Do not fail CI locally.
		}

		$slug   = ! empty( $opts['slug'] ) ? (string) $opts['slug'] : $this->plugin_slug();
		$format = $opts['strict'] ? 'strict-json' : 'json';

		Console::line();
		Console::comment( 'WP Plugin Check — ' . $slug );

		// Run `wp plugin check` and capture output.
		$args = array( '--path=' . $wp_path, 'plugin', 'check', $slug, '--format=' . $format );
		if ( $opts['ignore'] ) {
			$args[] = '--ignore-codes=' . $opts['ignore'];
		}
		list( $exit, $out ) = $this->execute_command( 'wp', $args, null );
		$raw                = implode( "\n", $out );
		$rows               = $this->parse_json_rows( $raw );

		$failed = $this->render_table( $rows );
		Console::line();
		if ( $failed ) {
			Console::error( 'Plugin Check reported errors' );
			return 1;
		}
		Console::info( 'Plugin Check OK' );
		return 0;
	}

	/**
	 * Parse argv options into a normalized array.
	 *
	 * @param array<int, string> $args Raw argv values.
	 * @return array{path: ?string, slug: ?string, strict: bool, ignore: string}
	 */
	private function parseOptions( array $args ) {
		$opts = array(
			'path'   => null,
			'slug'   => null,
			'strict' => true,
			'ignore' => 'trademarked_term',
		);
		foreach ( $args as $arg ) {
			if ( 0 === strpos( $arg, '--path=' ) ) {
				$opts['path'] = substr( $arg, 7 );
			}
			if ( 0 === strpos( $arg, '--slug=' ) ) {
				$opts['slug'] = substr( $arg, 7 );
			}
			if ( '--no-strict' === $arg ) {
				$opts['strict'] = false;
			}
			if ( 0 === strpos( $arg, '--ignore-codes=' ) ) {
				$opts['ignore'] = substr( $arg, 15 );
			}
		}
		return $opts;
	}

	/**
	 * Extract JSON rows from noisy CLI output.
	 *
	 * @param string $raw Raw combined output from the command.
	 * @return array<int, array<string, mixed>> Parsed rows.
	 */
	private function parse_json_rows( $raw ) {
		$start = strpos( $raw, '[' );
		$end   = strrpos( $raw, ']' );
		if ( false === $start || false === $end || $end < $start ) {
			return array();
		}
		$json = substr( $raw, $start, $end - $start + 1 );
		$data = json_decode( $json, true );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Render the coloured table and summary. Returns true if any errors.
	 *
	 * @param array<int, array<string, mixed>> $rows Parsed rows from JSON.
	 * @return bool Whether any ERROR rows were present.
	 */
	private function render_table( array $rows ) {
		$errors   = 0;
		$warnings = 0;
		$items    = array();
		foreach ( $rows as $r ) {
			$type    = strtoupper( (string) ( $r['type'] ?? '' ) );
			$code    = (string) ( $r['code'] ?? '' );
			$msg     = (string) ( $r['message'] ?? '' );
			$items[] = array( $type, $code, $msg );
			if ( 'ERROR' === $type ) {
				++$errors; }
			if ( 'WARNING' === $type ) {
				++$warnings; }
		}
		if ( empty( $items ) ) {
			Console::info( '✔ No issues found' );
			return false;
		}

		// Column widths.
		$w1 = 4;
		$w2 = 4;
		foreach ( $items as $it ) {
			$w1 = max( $w1, strlen( $it[0] ) );
			$w2 = max( $w2, strlen( $it[1] ) );
		}
		$pad = function ( $s, $w ) {
			$l = strlen( $s );
			return $s . str_repeat( ' ', max( 0, $w - $l ) );
		};
		// Header.
		$header = '  ' . $pad( 'TYPE', $w1 ) . '  ' . $pad( 'CODE', $w2 ) . '  MESSAGE';
		Console::comment( $header );
		Console::comment( str_repeat( '-', strlen( $header ) ) );
		// Rows.
		foreach ( $items as $it ) {
			$type = $it[0];
			$code = $it[1];
			$msg  = $it[2];
			$line = '  ' . $pad( $type, $w1 ) . '  ' . $pad( $code, $w2 ) . '  ' . $msg;
			if ( 'ERROR' === $type ) {
				Console::error( $line );
			} elseif ( 'WARNING' === $type ) {
				Console::warning( $line );
			} else {
				Console::line( $line );
			}
		}
		// Summary.
		$summary = array();
		if ( $errors ) {
			$summary[] = 'Errors: ' . $errors;
		}
		if ( $warnings ) {
			$summary[] = 'Warnings: ' . $warnings;
		}
		Console::line();
		if ( $summary ) {
			Console::comment( implode( '  ', $summary ) );
		}
		// Failed if any errors.
		return $errors > 0;
	}

	/**
	 * Best‑effort WordPress root auto‑detection.
	 *
	 * Walk up to five parent levels from base; at each level, check for
	 * wp-config.php in the directory itself or in common web roots.
	 *
	 * @param string $base Base directory.
	 * @return string|null Absolute path to WP root, or null if not found.
	 */
	private function detect_wp_root( $base ) {
		$base = (string) $base;
		$max  = 5;
		$dir  = realpath( $base );
		if ( false === $dir ) {
			$dir = $base;
		}
		for ( $i = 0; $i <= $max; $i++ ) {
			$probe = rtrim( (string) $dir, '/\\' );
			if ( '' !== $probe && file_exists( $probe . DIRECTORY_SEPARATOR . 'wp-config.php' ) ) {
				return $probe;
			}
			$web_roots = array( 'public_html', 'htdocs', 'wordpress', 'wp' );
			foreach ( $web_roots as $root ) {
				$alt = $probe . DIRECTORY_SEPARATOR . $root;
				if ( file_exists( $alt . DIRECTORY_SEPARATOR . 'wp-config.php' ) ) {
					return $alt;
				}
			}
			$parent = dirname( $probe );
			if ( $parent === $probe ) {
				break; // Reached filesystem root.
			}
			$dir = $parent;
		}
		return null;
	}
}

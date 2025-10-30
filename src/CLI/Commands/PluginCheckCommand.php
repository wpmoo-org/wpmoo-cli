<?php

namespace WPMoo\CLI\Commands;

use WPMoo\CLI\Contracts\CommandInterface;
use WPMoo\CLI\Support\Base;
use WPMoo\CLI\Console;

class PluginCheckCommand extends Base implements CommandInterface {
	public function handle( array $args = array() ) {
		$opts = $this->parseOptions( $args );

		$wp_path = null;
		if ( ! empty( $opts['path'] ) ) {
			$wp_path = (string) $opts['path'];
		} elseif ( getenv( 'WP_PATH' ) ) {
			$wp_path = (string) getenv( 'WP_PATH' );
		}
		if ( ! $wp_path || ! is_dir( $wp_path ) ) {
			Console::warning( 'Set --path=<wordpress_root> or WP_PATH env.' );
			return 0; // Do not fail CI locally.
		}

		$slug   = ! empty( $opts['slug'] ) ? (string) $opts['slug'] : $this->plugin_slug();
		$format = $opts['strict'] ? 'strict-json' : 'json';

		Console::line();
		Console::comment( 'WP Plugin Check — ' . $slug );

		// Run `wp plugin check` and capture output to a temp file.
		$tmp  = $this->create_temp_file();
		$args = array( '--path=' . $wp_path, 'plugin', 'check', $slug, '--format=' . $format );
		if ( $opts['ignore'] ) {
			$args[] = '--ignore-codes=' . $opts['ignore'];
		}
		list( $exit, $out ) = $this->execute_command( 'wp', $args, null );
		// Persist output for parsing/pretty print.
		file_put_contents( $tmp, implode( "\n", $out ) );

		$rows = $this->parse_json_rows( (string) file_get_contents( $tmp ) );
		@unlink( $tmp );

		$failed = $this->render_table( $rows );
		Console::line();
		if ( $failed ) {
			Console::error( 'Plugin Check reported errors' );
			return 1;
		}
		Console::info( 'Plugin Check OK' );
		return 0;
	}

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
}

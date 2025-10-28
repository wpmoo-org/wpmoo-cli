<?php

namespace WPMoo\CLI\Support;

use WPMoo\Core\App;
use WPMoo\CLI\Console;
use WPMoo\Support\I18n\PotGenerator;

/**
 * Shared helpers for modular CLI commands.
 * Extracted from the former framework Core CLI.
 */
class Base {
	// Bring over the implementation from the framework's Base (trimmed for brevity in this scaffold)
	// Note: In your current codebase this already exists under wpmoo/src/CLI/Support/Base.php.
	// For the split, copy the full contents here to make CLI standalone (with wpmoo/wpmoo dependency).

	// To keep this patch focused, we forward minimal methods used by CLI router.
	protected static function framework_base_path() {
		return defined( 'WPMOO_PATH' ) ? rtrim( WPMOO_PATH, '/\\' ) . DIRECTORY_SEPARATOR : self::base_path(); }
	protected static function base_path() {
		$app = App::instance();
		return rtrim( $app->path( '' ), '/\\' ) . DIRECTORY_SEPARATOR; }

	protected function environmentSummary() {
		$base_path  = self::framework_base_path();
		$metadata   = $this->detect_project_metadata( rtrim( $base_path, '/\\' ) );
		$namespace  = $this->detect_primary_namespace( $base_path );
		$version    = defined( 'WPMOO_VERSION' ) ? WPMOO_VERSION : $this->detect_current_version( $base_path );
		$wp_cli_ver = null;
		if ( ! $version && ! empty( $metadata['version'] ) ) {
			$version = $metadata['version']; }
		return array(
			'version'          => $version,
			'wp_cli_version'   => $wp_cli_ver,
			'plugin_file'      => ! empty( $metadata['main'] ) ? basename( (string) $metadata['main'] ) : null,
			'plugin_name'      => ! empty( $metadata['name'] ) ? $metadata['name'] : null,
			'plugin_version'   => ! empty( $metadata['version'] ) ? $metadata['version'] : null,
			'plugin_namespace' => $namespace,
		);
	}

	protected function detect_project_metadata( $path ) {
		$metadata = array(
			'name'    => null,
			'version' => null,
			'main'    => null,
			'slug'    => null,
		);
		foreach ( glob( rtrim( $path, '/\\' ) . DIRECTORY_SEPARATOR . '*.php' ) as $file ) {
			$c = @file_get_contents( $file );
			if ( $c === false ) {
				continue;
			}
			if ( preg_match( '/^[ \t\/*#@]*Plugin Name:\s*(.*)$/mi', $c ) ) {
				$metadata['main'] = $file;
				$metadata['slug'] = basename( $file, '.php' );
				break; }
		}
		$composer = rtrim( $path, '/\\' ) . DIRECTORY_SEPARATOR . 'composer.json';
		if ( file_exists( $composer ) ) {
			$raw = @file_get_contents( $composer );
			if ( $raw !== false ) {
				$d = json_decode( $raw, true );
				if ( is_array( $d ) ) {
					$metadata['name']    = $d['name'] ?? $metadata['name'];
					$metadata['version'] = $d['version'] ?? $metadata['version']; }
			}
		}
		if ( empty( $metadata['slug'] ) ) {
			$metadata['slug'] = basename( rtrim( $path, '/\\' ) ); }
		return $metadata;
	}

	protected function detect_primary_namespace( $base_path ) {
		$composer = rtrim( $base_path, '/\\' ) . DIRECTORY_SEPARATOR . 'composer.json';
		if ( ! file_exists( $composer ) ) {
			return null;
		}
		$raw = @file_get_contents( $composer );
		if ( $raw === false ) {
			return null;
		} $d = json_decode( $raw, true );
		if ( ! is_array( $d ) ) {
			return null;
		}
		$psr4 = $d['autoload']['psr-4'] ?? ( $d['autoload-dev']['psr-4'] ?? array() );
		if ( ! is_array( $psr4 ) || empty( $psr4 ) ) {
			return null;
		}
		$keys = array_keys( $psr4 );
		return rtrim( (string) $keys[0], '\\' );
	}

	protected function detect_current_version( $base_path ) {
		$composer = rtrim( $base_path, '/\\' ) . DIRECTORY_SEPARATOR . 'composer.json';
		if ( file_exists( $composer ) ) {
			$raw = @file_get_contents( $composer );
			if ( $raw !== false ) {
				$d = json_decode( $raw, true );
				if ( is_array( $d ) && isset( $d['version'] ) ) {
					return (string) $d['version'];
				}
			}
		}
		return null;
	}
}

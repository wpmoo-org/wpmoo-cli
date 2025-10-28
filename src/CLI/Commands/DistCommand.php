<?php

namespace WPMoo\CLI\Commands;

use WPMoo\CLI\Contracts\CommandInterface;
use WPMoo\CLI\Support\Base;
use WPMoo\CLI\Console;

class DistCommand extends Base implements CommandInterface {
	public function handle( array $args = array() ) {
		$options     = $this->parse_dist_options( $args );
		$source_root = $options['source'] ? $this->normalize_absolute_path( $options['source'] ) : $this->default_dist_source();
		if ( ! $source_root || ! is_dir( $source_root ) ) {
			Console::error( 'Unable to resolve source directory for distribution.' );
			return 1; }
		$base_path    = $this->framework_base_path();
		$is_framework = $this->paths_equal( $source_root, rtrim( $base_path, '/\\' ) );
		$metadata     = $is_framework ? array() : $this->detect_project_metadata( $source_root );
		$version      = $options['version'] ? $this->sanitize_version_input( $options['version'] ) : ( $is_framework ? $this->detect_current_version( $base_path ) : ( $metadata['version'] ?? $this->detect_current_version( $source_root ) ) );
		if ( ! $version ) {
			$version = '0.0.0'; }
		if ( $options['label'] ) {
			$slug = $this->sanitize_slug( $options['label'] ); } elseif ( $is_framework ) {
			$slug = $this->plugin_slug(); } elseif ( ! empty( $metadata['slug'] ) ) {
				$slug = $metadata['slug']; } else {
				$slug = $this->sanitize_slug( basename( $source_root ) ); }
				if ( '' === $slug ) {
					$slug = 'package'; }
				$label     = $slug . '-' . $version;
				$dist_root = $options['output'] ? $this->normalize_absolute_path( $options['output'] ) : dirname( $source_root ) . DIRECTORY_SEPARATOR . 'dist';
				if ( ! $dist_root ) {
					Console::error( 'Failed to resolve distribution output directory.' );
					return 1; }
				if ( ! $this->ensure_directory( rtrim( $dist_root, '/\\' ) . DIRECTORY_SEPARATOR ) ) {
					Console::error( 'Unable to create distribution output directory.' );
					return 1; }
				$temp_dir = $this->create_temp_directory( $slug . '-dist-' );
				if ( ! $temp_dir ) {
					Console::error( 'Unable to create temporary directory for distribution build.' );
					return 1; }
				$target_root = $temp_dir . DIRECTORY_SEPARATOR . $label;
				if ( ! @mkdir( $target_root, 0755, true ) ) {
					Console::error( 'Unable to prepare working directory for distribution.' );
					$this->delete_directory( $temp_dir );
					return 1; }
				Console::line();
				Console::comment( 'Preparing distribution: ' . $label );
				if ( $is_framework ) {
					foreach ( $this->default_dist_includes( $source_root ) as $entry ) {
						$source = $source_root . DIRECTORY_SEPARATOR . $entry;
						$target = $target_root . DIRECTORY_SEPARATOR . $entry;
						if ( $this->copy_within_dist( $source, $target ) && 'vendor' === $entry ) {
							$this->prune_vendor_tree( $target ); }
						if ( ! file_exists( $target ) ) {
							Console::warning( 'Failed to include ' . $entry . ' in distribution.' ); }
					}
					$this->ensure_minified_assets( $target_root . DIRECTORY_SEPARATOR . 'assets' );
					$this->prune_assets_tree( $target_root . DIRECTORY_SEPARATOR . 'assets' );
					$composer_binary = $this->locate_composer_binary( $target_root );
					if ( $composer_binary ) {
						Console::comment( '→ Installing production dependencies (--no-dev)' );
						$this->delete_directory( $target_root . DIRECTORY_SEPARATOR . 'vendor' );
						list($status, $output) = $this->execute_command( $composer_binary, array( 'install', '--no-dev', '--prefer-dist', '--no-interaction', '--no-progress', '--optimize-autoloader' ), $target_root );
						$this->output_command_lines( $output );
						if ( 0 !== $status ) {
							Console::warning( 'Composer install failed (exit code ' . $status . '). Reinstating bundled vendor directory.' );
							$this->copy_within_dist( $source_root . DIRECTORY_SEPARATOR . 'vendor', $target_root . DIRECTORY_SEPARATOR . 'vendor' );
						}
					} else {
						Console::comment( '→ Composer binary not found; reusing existing vendor directory.' ); }
					$this->remove_if_exists( $target_root . DIRECTORY_SEPARATOR . 'composer.json' );
					$this->remove_if_exists( $target_root . DIRECTORY_SEPARATOR . 'composer.lock' );
					$this->remove_if_exists( $target_root . DIRECTORY_SEPARATOR . 'package.json' );
					$this->remove_if_exists( $target_root . DIRECTORY_SEPARATOR . 'package-lock.json' );
					$this->remove_if_exists( $target_root . DIRECTORY_SEPARATOR . 'pnpm-lock.yaml' );
					$this->remove_if_exists( $target_root . DIRECTORY_SEPARATOR . 'yarn.lock' );
					$this->delete_directory( $target_root . DIRECTORY_SEPARATOR . 'bin' );
					$this->delete_directory( $target_root . DIRECTORY_SEPARATOR . 'node_modules' );
					$this->prune_vendor_tree( $target_root . DIRECTORY_SEPARATOR . 'vendor' );
				} else {
					$exclusions = $this->default_deploy_exclusions();
					if ( ! $this->copy_tree( $source_root, $target_root, $exclusions ) ) {
						Console::error( 'Failed to copy project files into working directory.' );
						$this->delete_directory( $temp_dir );
						return 1; }
					$this->post_process_deploy( $target_root, array() );
					$this->prune_vendor_tree( $target_root . DIRECTORY_SEPARATOR . 'vendor' );
				}
				$archive_path = rtrim( $dist_root, '/\\' ) . DIRECTORY_SEPARATOR . $label . '.zip';
				if ( ! $this->create_zip_archive( $target_root, $archive_path ) ) {
					Console::error( 'Failed to create distribution archive.' );
					$this->delete_directory( $temp_dir );
					return 1; }
				Console::info( 'Distribution archive created: ' . $this->relative_path( $archive_path ) );
				$this->do_action_safe(
					'wpmoo_cli_dist_completed',
					array(
						'label'   => $label,
						'version' => $version,
						'archive' => $archive_path,
						'path'    => $target_root,
						'source'  => $source_root,
						'options' => $options,
					)
				);
		if ( ! $options['keep'] ) {
			$this->delete_directory( $temp_dir );
		} else {
			Console::comment( 'Working directory preserved at ' . $this->relative_path( $temp_dir ) ); }
		Console::line();
		return 0;
	}
}

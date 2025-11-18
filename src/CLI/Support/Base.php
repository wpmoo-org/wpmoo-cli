<?php
/**
 * Base support class for WPMoo CLI commands.
 *
 * Provides common utilities for CLI commands without hard dependencies on the framework.
 *
 * @package WPMoo\CLI\Support
 * @since 0.1.0
 * @link  https://wpmoo.org WPMoo – WordPress Micro Object-Oriented Framework.
 * @link  https://github.com/wpmoo/wpmoo GitHub Repository.
 * @license https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 */

namespace WPMoo\CLI\Support;

use WPMoo\CLI\Console;

/**
 * Standalone helpers for WPMoo CLI commands.
 *
 * These utilities avoid hard dependencies on the framework so the CLI can be
 * used in any WordPress plugin/project or even outside WordPress for packaging
 * and checks.
 */
class Base {

	// ---------------------------------------------------------------------
	// Paths / environment.
	// ---------------------------------------------------------------------

	/**
	 * Resolve the framework base when embedded, else the current working dir.
	 *
	 * @return string Absolute path with trailing directory separator.
	 */
	protected static function framework_base_path() {
		if ( defined( 'WPMOO_PATH' ) ) {
			return rtrim( WPMOO_PATH, '/\\' ) . DIRECTORY_SEPARATOR;
		}
		return self::base_path();
	}

	/**
	 * Resolve the current working directory with trailing separator.
	 *
	 * @return string
	 */
	protected static function base_path() {
		$cwd = getcwd();
		return $cwd ? rtrim( $cwd, '/\\' ) . DIRECTORY_SEPARATOR : __DIR__ . DIRECTORY_SEPARATOR;
	}

	/**
	 * Build a short project environment summary for banner rendering.
	 *
	 * @return array<string, mixed>
	 */
	protected function environmentSummary() {
		$base_path  = self::framework_base_path();
		$metadata   = $this->detect_project_metadata( rtrim( $base_path, '/\\' ) );
		$namespace  = $this->detect_primary_namespace( $base_path );
		$version    = defined( 'WPMOO_VERSION' ) ? WPMOO_VERSION : $this->detect_current_version( $base_path );
		$wp_cli_ver = null;

		if ( ! $version && ! empty( $metadata['version'] ) ) {
			$version = $metadata['version'];
		}

		return array(
			'version'          => $version,
			'wp_cli_version'   => $wp_cli_ver,
			'plugin_file'      => ! empty( $metadata['main'] ) ? basename( (string) $metadata['main'] ) : null,
			'plugin_name'      => ! empty( $metadata['name'] ) ? $metadata['name'] : null,
			'plugin_version'   => ! empty( $metadata['version'] ) ? $metadata['version'] : null,
			'plugin_namespace' => $namespace,
		);
	}

	/**
	 * Detect plugin metadata from headers and composer.json.
	 *
	 * @param string $path Base path without trailing separator.
	 * @return array<string, mixed>
	 */
	protected function detect_project_metadata( $path ) {
		$metadata = array(
			'name'    => null,
			'version' => null,
			'main'    => null,
			'slug'    => null,
		);

		foreach ( glob( rtrim( $path, '/\\' ) . DIRECTORY_SEPARATOR . '*.php' ) as $file ) {
			$contents = @file_get_contents( $file );
			if ( false === $contents ) {
				continue;
			}
			if ( preg_match( '/^[ \t\/*#@]*Plugin Name:\s*(.*)$/mi', $contents ) ) {
				$metadata['main'] = $file;
				$metadata['slug'] = basename( $file, '.php' );
				break;
			}
		}

		$composer = rtrim( $path, '/\\' ) . DIRECTORY_SEPARATOR . 'composer.json';
		if ( file_exists( $composer ) ) {
			$raw = @file_get_contents( $composer );
			if ( false !== $raw ) {
				$data = json_decode( $raw, true );
				if ( is_array( $data ) ) {
					$metadata['name']    = $data['name'] ?? $metadata['name'];
					$metadata['version'] = $data['version'] ?? $metadata['version'];
				}
			}
		}

		if ( empty( $metadata['slug'] ) ) {
			$metadata['slug'] = basename( rtrim( $path, '/\\' ) );
		}

		return $metadata;
	}

	/**
	 * Detect the primary PSR-4 namespace from composer.json.
	 *
	 * @param string $base_path Base path with trailing separator.
	 * @return string|null
	 */
	protected function detect_primary_namespace( $base_path ) {
		$composer = rtrim( $base_path, '/\\' ) . DIRECTORY_SEPARATOR . 'composer.json';
		if ( ! file_exists( $composer ) ) {
			return null;
		}
		$raw = @file_get_contents( $composer );
		if ( false === $raw ) {
			return null;
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return null;
		}
		$psr4 = $data['autoload']['psr-4'] ?? ( $data['autoload-dev']['psr-4'] ?? array() );
		if ( ! is_array( $psr4 ) || empty( $psr4 ) ) {
			return null;
		}
		$keys = array_keys( $psr4 );
		return rtrim( (string) $keys[0], '\\' );
	}

	/**
	 * Read composer.json version.
	 *
	 * @param string $base_path Base path with trailing separator.
	 * @return string|null
	 */
	protected function detect_current_version( $base_path ) {
		$composer = rtrim( $base_path, '/\\' ) . DIRECTORY_SEPARATOR . 'composer.json';
		if ( file_exists( $composer ) ) {
			$raw = @file_get_contents( $composer );
			if ( false !== $raw ) {
				$data = json_decode( $raw, true );
				if ( is_array( $data ) && isset( $data['version'] ) ) {
					return (string) $data['version'];
				}
			}
		}
		return null;
	}

	/**
	 * Best-effort plugin slug.
	 *
	 * @return string
	 */
	protected function plugin_slug() {
		$meta = $this->detect_project_metadata( rtrim( self::base_path(), '/\\' ) );
		if ( ! empty( $meta['slug'] ) ) {
			return (string) $meta['slug'];
		}
		return 'plugin';
	}

	/**
	 * Compute a path relative to the working directory.
	 *
	 * @param string $path Absolute path.
	 * @return string
	 */
	protected function relative_path( $path ) {
		$cwd  = rtrim( (string) getcwd(), '/\\' ) . DIRECTORY_SEPARATOR;
		$norm = rtrim( (string) $path, '/\\' );
		if ( '' !== $cwd && 0 === strpos( $norm, rtrim( $cwd, '/\\' ) ) ) {
			return ltrim( substr( $norm, strlen( rtrim( $cwd, '/\\' ) ) ), '/\\' );
		}
		return $path;
	}

	/**
	 * Detect the execution context based on composer.json.
	 *
	 * @return string Context type: 'cli', 'framework', 'plugin', or 'other'
	 */
	protected function detect_context() {
		$composer_path = self::base_path() . 'composer.json';
		if ( ! file_exists( $composer_path ) ) {
			return 'other';
		}

		$raw = @file_get_contents( $composer_path );
		if ( false === $raw ) {
			return 'other';
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return 'other';
		}

		$package_name = isset( $data['name'] ) ? $data['name'] : null;

		if ( $package_name === 'wpmoo/wpmoo-cli' ) {
			return 'cli';
		} elseif ( $package_name === 'wpmoo/wpmoo' ) {
			return 'framework';
		}

		// Check if the project requires wpmoo/wpmoo as a dependency.
		$requires_wpmoo = false;
		$dep_types      = array( 'require', 'require-dev' );
		foreach ( $dep_types as $dep_type ) {
			if ( isset( $data[ $dep_type ] ) && is_array( $data[ $dep_type ] ) && isset( $data[ $dep_type ]['wpmoo/wpmoo'] ) ) {
				$requires_wpmoo = true;
				break;
			}
		}

		if ( $requires_wpmoo ) {
			return 'plugin'; // A project using WPMoo framework as dependency.
		}

		return 'other';
	}

	/**
	 * Get context-specific behavior configuration.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_context_config() {
		$context = $this->detect_context();
		$config  = array(
			'context' => $context,
			'name'    => $this->get_project_name(),
		);

		switch ( $context ) {
			case 'cli':
				$config['message']              = 'WPMoo CLI - This tool is designed for WPMoo framework and WPMoo-based plugins only.';
				$config['allow_info']           = true;
				$config['allow_version']        = true;
				$config['allow_check']          = true;
				$config['allow_basic_commands'] = true;
				$config['allow_deploy_dist']    = false; // Don't allow deployment/dist from CLI directory.
				break;

			case 'framework':
				$config['message']           = 'WPMoo Framework - Running in WPMoo framework directory.';
				$config['allow_info']        = true;
				$config['allow_version']     = true;
				$config['allow_check']       = true;
				$config['allow_deploy_dist'] = true; // Allow dist for framework distribution builds.
				$config['allow_rename']      = false; // Don't allow renaming the framework itself.
				$config['is_framework']      = true;
				break;

			case 'plugin':
				$config['message']           = 'WPMoo-Based Plugin - Running in WPMoo-based plugin directory.';
				$config['allow_info']        = true;
				$config['allow_version']     = true;
				$config['allow_check']       = true;
				$config['allow_deploy_dist'] = true;
				$config['allow_rename']      = true; // Allow renaming WPMoo-based plugins.
				$config['is_plugin']         = true;
				break;

			default:
				$config['message']           = 'Unknown Project - This tool is designed for WPMoo framework and WPMoo-based plugins.';
				$config['allow_info']        = true;
				$config['allow_version']     = true;
				$config['allow_check']       = true;
				$config['allow_deploy_dist'] = false;
				$config['is_supported']      = false;
				break;
		}

		return $config;
	}

	/**
	 * Get project name from composer.json.
	 *
	 * @return string|null
	 */
	protected function get_project_name() {
		$composer_path = self::base_path() . 'composer.json';
		if ( ! file_exists( $composer_path ) ) {
			return null;
		}

		$raw = @file_get_contents( $composer_path );
		if ( false === $raw ) {
			return null;
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || ! isset( $data['name'] ) ) {
			return null;
		}

		return $data['name'];
	}

	/**
	 * Public static method to get context configuration from anywhere.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_context_config_static() {
		// Create an instance of Base class to access context detection methods.
		$base = new self();
		return $base->get_context_config();
	}

	/**
	 * Compare two paths ignoring trailing separators.
	 *
	 * @param string $a Path A.
	 * @param string $b Path B.
	 * @return bool
	 */
	protected function paths_equal( $a, $b ) {
		return rtrim( (string) $a, '/\\' ) === rtrim( (string) $b, '/\\' );
	}

	// ---------------------------------------------------------------------
	// Build helpers.
	// ---------------------------------------------------------------------

	/**
	 * Parse build flags.
	 *
	 * @param array<int, mixed> $args Raw args.
	 * @return array<string, mixed>
	 */
	protected function parse_build_options( array $args ) {
		$options = array(
			'pm'            => null,
			'script'        => 'build',
			'force-install' => false,
			'skip-install'  => false,
			'allow-missing' => false,
		);

		foreach ( $args as $arg ) {
			$arg = (string) $arg;
			if ( 0 === strpos( $arg, '--pm=' ) ) {
				$options['pm'] = substr( $arg, 5 );
			} elseif ( 0 === strpos( $arg, '--pkgm=' ) ) {
				$options['pm'] = substr( $arg, 7 );
			} elseif ( '--install' === $arg || '--force-install' === $arg ) {
				$options['force-install'] = true;
			} elseif ( '--no-install' === $arg ) {
				$options['skip-install'] = true;
			} elseif ( 0 === strpos( $arg, '--script=' ) ) {
				$script = substr( $arg, 9 );
				if ( '' !== $script ) {
					$options['script'] = $script;
				}
			}
		}

		return $options;
	}

	/**
	 * Detect package manager.
	 *
	 * @param string         $base Base path.
	 * @param string|null    $hint Preferred manager.
	 * @return array<string, string>|null
	 */
	protected function detect_package_manager( $base, $hint = null ) {
		$managers = array(
			'pnpm' => array(
				'lock'   => 'pnpm-lock.yaml',
				'binary' => 'pnpm',
			),
			'yarn' => array(
				'lock'   => 'yarn.lock',
				'binary' => 'yarn',
			),
			'bun'  => array(
				'lock'   => 'bun.lockb',
				'binary' => 'bun',
			),
			'npm'  => array(
				'lock'   => 'package-lock.json',
				'binary' => 'npm',
			),
		);

		if ( $hint && isset( $managers[ $hint ] ) ) {
			return array(
				'name'   => $hint,
				'binary' => $managers[ $hint ]['binary'],
			);
		}

		foreach ( $managers as $name => $info ) {
			if ( file_exists( rtrim( $base, '/\\' ) . DIRECTORY_SEPARATOR . $info['lock'] ) ) {
				return array(
					'name'   => $name,
					'binary' => $info['binary'],
				);
			}
		}

		if ( file_exists( rtrim( $base, '/\\' ) . DIRECTORY_SEPARATOR . 'package.json' ) ) {
			return array(
				'name'   => 'npm',
				'binary' => 'npm',
			);
		}

		return null;
	}

	/**
	 * Arguments for install.
	 *
	 * @param string $name Manager.
	 * @return array<int, string>
	 */
	protected function install_arguments( $name ) {
		return array( 'install' );
	}

	/**
	 * Arguments for build.
	 *
	 * @param string $name   Manager.
	 * @param string $script Script name.
	 * @return array<int, string>
	 */
	protected function build_arguments( $name, $script ) {
		return array( 'run', $script );
	}

	/**
	 * Pretty run label.
	 *
	 * @param string $name   Manager.
	 * @param string $script Script name.
	 * @return string
	 */
	protected function format_run_command( $name, $script ) {
		return (string) $script;
	}

	/**
	 * Execute a command and capture output.
	 *
	 * @param string            $binary Command.
	 * @param array<int,string> $args   Arguments.
	 * @param string|null       $cwd    Working dir.
	 * @return array{0:int,1:array<int,string>}
	 */
	protected function execute_command( $binary, array $args, $cwd = null ) {
		$cmd = escapeshellcmd( (string) $binary );
		foreach ( $args as $a ) {
			$cmd .= ' ' . ( '' === $a ? "''" : escapeshellarg( (string) $a ) );
		}
		if ( $cwd ) {
			$cmd = 'cd ' . escapeshellarg( (string) $cwd ) . ' && ' . $cmd;
		}
		$cmd  .= ' 2>&1';
		$lines = array();
		$code  = 0;
		exec( $cmd, $lines, $code );
		return array( (int) $code, $lines );
	}

	/**
	 * Send command output to the console.
	 *
	 * @param array<int,string> $lines Lines.
	 * @return void
	 */
	protected function output_command_lines( array $lines ) {
		foreach ( $lines as $line ) {
			Console::line( '   • ' . $line );
		}
	}

	/**
	 * Perform a JS/CSS build.
	 *
	 * @param array<string,mixed> $options Options.
	 * @return bool
	 */
	protected function perform_build( array $options = array() ) {
		$defaults = array(
			'pm'            => null,
			'script'        => 'build',
			'force-install' => false,
			'skip-install'  => false,
			'allow-missing' => false,
		);
		$options  = array_merge( $defaults, $options );

		$base = self::base_path();
		$pkg  = $base . 'package.json';
		if ( ! file_exists( $pkg ) ) {
			if ( ! empty( $options['allow-missing'] ) ) {
				Console::comment( '→ No package.json detected; skipping asset build.' );
				return true;
			}
			Console::error( 'No package.json detected; cannot run build.' );
			return false;
		}

		$manager = $this->detect_package_manager( $base, $options['pm'] );
		if ( ! $manager ) {
			Console::error( 'Could not determine an available package manager. Install npm, yarn, pnpm, or bun (or pass --pm=<manager>).' );
			return false;
		}

		$name   = (string) $manager['name'];
		$binary = (string) $manager['binary'];
		Console::comment( '→ Using ' . $name . ' (' . $binary . ')' );

		$should_install = (bool) $options['force-install'];
		if ( ! $should_install && ! $options['skip-install'] && ! is_dir( $base . 'node_modules' ) ) {
			$should_install = true;
		}

		if ( $should_install ) {
			Console::comment( '   • Installing dependencies.' );
			list( $install_status, $install_output ) = $this->execute_command( $binary, $this->install_arguments( $name ), $base );
			$this->output_command_lines( $install_output );
			if ( 0 !== $install_status ) {
				Console::error( 'Dependency installation failed with status ' . $install_status . '.' );
				return false;
			}
		} elseif ( ! is_dir( $base . 'node_modules' ) ) {
			Console::warning( '   • node_modules missing; continuing without installation (build may fail).' );
		}

		Console::comment( '   • Running ' . $name . ' ' . $this->format_run_command( $name, (string) $options['script'] ) );
		list( $build_status, $build_output ) = $this->execute_command( $binary, $this->build_arguments( $name, (string) $options['script'] ), $base );
		$this->output_command_lines( $build_output );
		if ( 0 !== $build_status ) {
			Console::error( 'Build script exited with status ' . $build_status . '.' );
			return false;
		}

		Console::info( '→ Asset build completed.' );
		return true;
	}

	// ---------------------------------------------------------------------
	// Deploy helpers.
	// ---------------------------------------------------------------------

	/**
	 * Parse deploy flags.
	 *
	 * @param array<int, mixed> $args Raw args.
	 * @return array<string, mixed>
	 */
	protected function parse_deploy_options( array $args ) {
		$options                   = $this->parse_build_options( $args );
		$options['target']         = null;
		$options['zip']            = false;
		$options['zip-path']       = null;
		$options['no-build']       = false;
		$options['work-path']      = null;
		$options['keep-manifests'] = false;

		foreach ( $args as $arg ) {
			$arg = (string) $arg;
			if ( '--no-build' === $arg ) {
				$options['no-build'] = true;
			} elseif ( '--zip' === $arg || '--create-zip' === $arg ) {
				$options['zip'] = true;
			} elseif ( 0 === strpos( $arg, '--zip=' ) ) {
				$options['zip']      = true;
				$zip_value           = substr( $arg, 6 );
				$options['zip-path'] = '' !== $zip_value ? $zip_value : null;
			} elseif ( '--wp' === $arg || '--keep-manifests' === $arg ) {
				$options['keep-manifests'] = true;
			} elseif ( '-' !== substr( $arg, 0, 1 ) && null === $options['target'] ) {
				$options['target'] = $arg;
			}
		}

		if ( $options['target'] && $this->ends_with_zip( (string) $options['target'] ) ) {
			$options['zip'] = true;
			if ( ! $options['zip-path'] ) {
				$options['zip-path'] = $options['target'];
			}
		}

		return $options;
	}

	/**
	 * Does a path end with .zip?
	 *
	 * @param string $path Path.
	 * @return bool
	 */
	protected function ends_with_zip( $path ) {
		return (bool) preg_match( '/\.zip$/i', (string) $path );
	}

	/**
	 * Default deploy directory (dist/<slug> beside project root).
	 *
	 * @return string
	 */
	protected function default_deploy_directory() {
		return dirname( rtrim( self::base_path(), '/\\' ) ) . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . $this->plugin_slug();
	}

	/**
	 * Compute default zip output path.
	 *
	 * @param string $target_path Target.
	 * @param string $slug        Slug.
	 * @return string
	 */
	protected function default_deploy_zip_path( $target_path, $slug ) {
		$dir = is_dir( $target_path ) ? $target_path : dirname( $target_path );
		return rtrim( $dir, '/\\' ) . DIRECTORY_SEPARATOR . $slug . '.zip';
	}

	/**
	 * Normalize a possibly relative path to an absolute path.
	 *
	 * @param string $path Path.
	 * @return string|null
	 */
	protected function normalize_absolute_path( $path ) {
		$path = (string) $path;
		if ( '' === $path ) {
			return null;
		}
		if ( preg_match( '#^([a-zA-Z]:\\\\|/)#', $path ) ) {
			return $path;
		}
		return rtrim( self::base_path(), '/\\' ) . DIRECTORY_SEPARATOR . ltrim( $path, '/\\' );
	}

	/**
	 * Ensure a directory exists.
	 *
	 * @param string $path Path.
	 * @return bool
	 */
	protected function ensure_directory( $path ) {
		if ( is_dir( $path ) ) {
			return true;
		}
		return @mkdir( $path, 0755, true );
	}

	/**
	 * Create a temporary directory.
	 *
	 * @param string $prefix Prefix.
	 * @return string|null
	 */
	protected function create_temp_directory( $prefix ) {
		$base = rtrim( (string) sys_get_temp_dir(), '/\\' ) . DIRECTORY_SEPARATOR;
		$path = $base . $prefix . uniqid( '', true );
		return @mkdir( $path, 0755, true ) ? $path : null;
	}

	/**
	 * Recursively delete a directory.
	 *
	 * @param string $path Path.
	 * @return void
	 */
	protected function delete_directory( $path ) {
		if ( ! is_dir( $path ) ) {
			return;
		}
		$items = scandir( $path );
		if ( false === $items ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$full = $path . DIRECTORY_SEPARATOR . $item;
			if ( is_dir( $full ) ) {
				$this->delete_directory( $full );
			} else {
				@unlink( $full );
			}
		}
		@rmdir( $path );
	}

	/**
	 * Copy a directory tree with exclusions.
	 *
	 * @param string              $source     Source.
	 * @param string              $target     Target.
	 * @param array<int, string>  $exclusions Patterns.
	 * @return bool
	 */
	protected function copy_tree( $source, $target, array $exclusions = array() ) {
		if ( ! is_dir( $source ) ) {
			return false;
		}
		if ( ! $this->ensure_directory( $target ) ) {
			return false;
		}
		$dir = opendir( $source );
		if ( false === $dir ) {
			return false;
		}
		while ( false !== ( $file = readdir( $dir ) ) ) {
			if ( '.' === $file || '..' === $file ) {
				continue;
			}
			$src = $source . DIRECTORY_SEPARATOR . $file;
			$dst = $target . DIRECTORY_SEPARATOR . $file;
			$rel = $file;
			foreach ( $exclusions as $pattern ) {
				if ( fnmatch( $pattern, $rel ) || false !== strpos( $src, $pattern ) ) {
					continue 2;
				}
			}
			if ( is_dir( $src ) ) {
				if ( ! $this->copy_tree( $src, $dst, $exclusions ) ) {
					closedir( $dir );
					return false;
				}
			} elseif ( ! @copy( $src, $dst ) ) {
					closedir( $dir );
					return false;
			}
		}
		closedir( $dir );
		return true;
	}

	/**
	 * Default set of exclusions for deploy copy.
	 *
	 * @return array<int,string>
	 */
	protected function default_deploy_exclusions() {
		return array(
			// VCS / tooling.
			'.git',
			'.github',
			'.githooks',
			'.idea',
			'.vscode',
			'.gitignore',
			// Hidden files (dotfiles).
			'.*',
			// JS build & config.
			'node_modules',
			'bin',
			'dist',
			'gulpfile.js',
			'webpack.*',
			'rollup.*',
			'vite.*',
			'package.json',
			'package-lock.json',
			'yarn.lock',
			'pnpm-lock.yaml',
			'pnpm-workspace.yaml',
			// PHP / QA configs not needed at runtime.
			'phpunit.xml',
			'phpunit.xml.dist',
			'phpstan*',
			// Maps and other development artifacts.
			'*.map',
			// Tests.
			'tests',
		);
	}

	/**
	 * Whether a path is within a base path.
	 *
	 * @param string $path Path.
	 * @param string $base Base.
	 * @return bool
	 */
	protected function path_is_within( $path, $base ) {
		$path = realpath( (string) $path );
		$base = realpath( (string) $base );
		if ( false === $path || false === $base ) {
			return false;
		}
		return 0 === strpos( $path, $base . DIRECTORY_SEPARATOR );
	}

	/**
	 * Remove a file if present.
	 *
	 * @param string $path Path.
	 * @return void
	 */
	protected function remove_if_exists( $path ) {
		if ( file_exists( $path ) ) {
			@unlink( $path );
		}
	}

	/**
	 * Locate composer binary.
	 *
	 * @param string $cwd Working dir.
	 * @return string|null
	 */
	protected function locate_composer_binary( $cwd ) {
		list( $code ) = $this->execute_command( 'which', array( 'composer' ), $cwd );
		return 0 === $code ? 'composer' : null;
	}

	/**
	 * Copy within dist structure (file/dir).
	 *
	 * @param string $src Source.
	 * @param string $dst Destination.
	 * @return bool
	 */
	protected function copy_within_dist( $src, $dst ) {
		if ( is_dir( $src ) ) {
			return $this->copy_tree( $src, $dst, array() );
		}
		if ( is_file( $src ) ) {
			$this->ensure_directory( dirname( $dst ) . DIRECTORY_SEPARATOR );
			return @copy( $src, $dst );
		}
		return false;
	}

	/**
	 * Default include list for framework distribution builds.
	 *
	 * When packaging the WPMoo framework itself, we copy a curated set of
	 * top-level entries into the working directory before pruning.
	 *
	 * @param string $source_root Absolute path to the framework root.
	 * @return array<int,string>
	 */
	protected function default_dist_includes( $source_root ) {
		return array(
			'assets',
			'src',
			'vendor',
			'wpmoo.php',
			'live.php',
			'local.php',
			'index.php',
			'readme.txt',
			'languages',
		);
	}

	/**
	 * No-op vendor pruning for external CLI.
	 *
	 * @param string $path Vendor path.
	 * @return void
	 */
	protected function prune_vendor_tree( $path ) { }

	/**
	 * No-op asset helpers for external CLI.
	 *
	 * @param string $dir Assets path.
	 * @return void
	 */
	protected function ensure_minified_assets( $dir ) { }

	/**
	 * No-op asset prune for external CLI.
	 *
	 * @param string $dir Assets path.
	 * @return void
	 */
	protected function prune_assets_tree( $dir ) { }

	/**
	 * Create a zip archive of a directory.
	 *
	 * @param string $source_dir Source directory.
	 * @param string $zip_path   Archive path.
	 * @return bool
	 */
	protected function create_zip_archive( $source_dir, $zip_path ) {
		if ( class_exists( '\\ZipArchive' ) ) {
			$zip = new \ZipArchive();
			if ( true !== $zip->open( $zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
				return false;
			}
			$files = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $source_dir, \FilesystemIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::SELF_FIRST
			);
			$base  = rtrim( realpath( $source_dir ), '/\\' ) . DIRECTORY_SEPARATOR;
			foreach ( $files as $file ) {
				$path = realpath( (string) $file );
				if ( false === $path ) {
					continue;
				}
				$local = ltrim( substr( $path, strlen( $base ) ), '/\\' );
				if ( is_dir( $path ) ) {
					$zip->addEmptyDir( $local );
				} else {
					$zip->addFile( $path, $local );
				}
			}
			$zip->close();
			return true;
		}

		list( $code, $out ) = $this->execute_command( 'zip', array( '-r', $zip_path, '.' ), $source_dir );
		$this->output_command_lines( $out );
		return 0 === $code;
	}

	/**
	 * Basic cleanup for deploy output.
	 *
	 * @param string               $working_dir Path.
	 * @param array<string,mixed>  $options     Options.
	 * @return void
	 */
	protected function post_process_deploy( $working_dir, array $options ) {
		$keep = ! empty( $options['keep-manifests'] );
		// Keep composer.json only when requested; always drop JS build manifests and lockfiles.
		if ( ! $keep ) {
			$this->remove_if_exists( $working_dir . DIRECTORY_SEPARATOR . 'composer.json' );
		}
		// Always drop Composer lock (not needed at runtime).
		$this->remove_if_exists( $working_dir . DIRECTORY_SEPARATOR . 'composer.lock' );
		// Always drop JS build manifests and lockfiles.
		$this->remove_if_exists( $working_dir . DIRECTORY_SEPARATOR . 'package.json' );
		$this->remove_if_exists( $working_dir . DIRECTORY_SEPARATOR . 'package-lock.json' );
		$this->remove_if_exists( $working_dir . DIRECTORY_SEPARATOR . 'pnpm-lock.yaml' );
		$this->remove_if_exists( $working_dir . DIRECTORY_SEPARATOR . 'yarn.lock' );
	}

	// ---------------------------------------------------------------------
	// Dist helpers.
	// ---------------------------------------------------------------------

	/**
	 * Default dist source directory.
	 *
	 * @return string
	 */
	protected function default_dist_source() {
		return rtrim( self::framework_base_path(), '/\\' );
	}

	/**
	 * Sanitize a slug string.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	protected function sanitize_slug( $value ) {
		$value = strtolower( (string) $value );
		$value = preg_replace( '/[^a-z0-9\-]+/', '-', $value );
		return trim( (string) $value, '-' );
	}

	/**
	 * Parse dist flags.
	 *
	 * @param array<int,mixed> $args Args.
	 * @return array<string,mixed>
	 */
	protected function parse_dist_options( array $args ) {
		$options = array(
			'label'   => null,
			'output'  => null,
			'source'  => null,
			'version' => null,
			'keep'    => false,
		);

		$count = count( $args );
		for ( $i = 0; $i < $count; $i++ ) {
			$arg = (string) $args[ $i ];
			if ( '' === trim( $arg ) ) {
				continue;
			}
			if ( 0 === strpos( $arg, '--label=' ) ) {
				$options['label'] = substr( $arg, 8 );
			} elseif ( '--label' === $arg && isset( $args[ $i + 1 ] ) ) {
				$options['label'] = trim( (string) $args[ ++$i ] );
			} elseif ( 0 === strpos( $arg, '--output=' ) ) {
				$options['output'] = substr( $arg, 9 );
			} elseif ( '--output' === $arg && isset( $args[ $i + 1 ] ) ) {
				$options['output'] = trim( (string) $args[ ++$i ] );
			} elseif ( '--keep' === $arg ) {
				$options['keep'] = true;
			} elseif ( 0 === strpos( $arg, '--source=' ) ) {
				$options['source'] = substr( $arg, 9 );
			} elseif ( '--source' === $arg && isset( $args[ $i + 1 ] ) ) {
				$options['source'] = trim( (string) $args[ ++$i ] );
			} elseif ( 0 === strpos( $arg, '--version=' ) ) {
				$options['version'] = substr( $arg, 10 );
			} elseif ( '--version' === $arg && isset( $args[ $i + 1 ] ) ) {
				$options['version'] = trim( (string) $args[ ++$i ] );
			}
		}

		return $options;
	}

	// ---------------------------------------------------------------------
	// Version helpers.
	// ---------------------------------------------------------------------

	/**
	 * Normalize a version string like v1.2.3 → 1.2.3.
	 *
	 * @param string $value Raw.
	 * @return string
	 */
	protected function sanitize_version_input( $value ) {
		$value = trim( (string) $value );
		return preg_replace( '/^v/i', '', $value );
	}

	/**
	 * Validate semantic version.
	 *
	 * @param string $value Version.
	 * @return bool
	 */
	protected function is_valid_semver( $value ) {
		if ( '' === $value ) {
			return false;
		}
		return (bool) preg_match( '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$/', (string) $value );
	}

	/**
	 * Bump a version according to SemVer.
	 *
	 * @param string      $current     Current version.
	 * @param string      $type        major|minor|patch.
	 * @param string|null $pre_release Optional pre-release label.
	 * @return string|null
	 */
	protected function bump_semver( $current, $type, $pre_release = null ) {
		if ( ! preg_match( '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-.+)?$/', (string) $current, $matches ) ) {
			return null;
		}

		$major = (int) $matches[1];
		$minor = (int) $matches[2];
		$patch = (int) $matches[3];

		switch ( $type ) {
			case 'major':
				++$major;
				$minor = 0;
				$patch = 0;
				break;
			case 'minor':
				++$minor;
				$patch = 0;
				break;
			default:
				++$patch;
		}

		$new = $major . '.' . $minor . '.' . $patch;
		if ( $pre_release ) {
			$label = preg_replace( '/[^0-9A-Za-z\.-]/', '', (string) $pre_release );
			if ( '' !== $label ) {
				$new .= '-' . $label;
			}
		}

		return $new;
	}

	/**
	 * Parse version command flags.
	 *
	 * @param array<int,mixed> $args Args.
	 * @return array<string,mixed>
	 */
	protected function parse_version_arguments( array $args ) {
		$options = array(
			'bump'        => null,
			'explicit'    => null,
			'dry-run'     => false,
			'assume-yes'  => false,
			'pre-release' => null,
		);

		$map = array(
			'--major' => 'major',
			'--minor' => 'minor',
			'--patch' => 'patch',
		);

		$count = count( $args );
		for ( $i = 0; $i < $count; $i++ ) {
			$arg = (string) $args[ $i ];
			if ( '' === trim( $arg ) ) {
				continue;
			}
			if ( isset( $map[ $arg ] ) ) {
				$options['bump'] = $map[ $arg ];
				continue;
			}
			if ( '--dry-run' === $arg ) {
				$options['dry-run'] = true;
				continue;
			}
			if ( '--yes' === $arg || '--force' === $arg ) {
				$options['assume-yes'] = true;
				continue;
			}
			if ( 0 === strpos( $arg, '--pre=' ) ) {
				$options['pre-release'] = substr( $arg, 6 );
				continue;
			}
			if ( '--pre' === $arg && isset( $args[ $i + 1 ] ) ) {
				$options['pre-release'] = (string) $args[ ++$i ];
				continue;
			}
			if ( 0 === strpos( $arg, '--' ) ) {
				continue;
			}
			$options['explicit'] = $arg;
		}

		return $options;
	}

	/**
	 * Update version across common places.
	 *
	 * @param string $base_path       Base path with trailing separator.
	 * @param string $current_version Current version.
	 * @param string $new_version     New version.
	 * @param bool   $dry_run         If true, do not write changes.
	 * @return array<int,string>
	 */
	protected function update_version_files( $base_path, $current_version, $new_version, $dry_run = false ) {
		$updated  = array();
		$composer = rtrim( (string) $base_path, '/\\' ) . DIRECTORY_SEPARATOR . 'composer.json';
		if ( file_exists( $composer ) ) {
			if ( $dry_run ) {
				$updated[] = $composer;
			} else {
				$raw  = file_get_contents( $composer );
				$data = is_string( $raw ) ? json_decode( $raw, true ) : null;
				if ( is_array( $data ) ) {
					// Packagist best practice: omit composer.json version and rely on VCS tags.
					// Only update if a version key is already present or the environment explicitly opts-in.
					$should_update = array_key_exists( 'version', $data ) || (bool) getenv( 'WPMOO_UPDATE_COMPOSER_VERSION' );
					if ( $should_update ) {
						$data['version'] = $new_version;
						$this->write_json_file( $composer, $data );
						$updated[] = $composer;
					}
				}
			}
		}

		$meta = $this->detect_project_metadata( rtrim( (string) $base_path, '/\\' ) );
		if ( ! empty( $meta['main'] ) && file_exists( $meta['main'] ) && ! $dry_run ) {
			$contents = file_get_contents( $meta['main'] );
			if ( is_string( $contents ) ) {
				// Update plugin header Version: x.y.z.
				$replaced = preg_replace( '/^([ \t\/*#@]*Version:\s*)(.*)$/mi', '$1' . $new_version, $contents );
				// Update a common VERSION constant if present in the main file.
				$replaced = is_string( $replaced ) ? preg_replace( "/define\(\s*'[^']*_VERSION'\s*,\s*'[^']*'\s*\)\s*;/", "define( 'WPMOO_VERSION', '" . $new_version . "' );", $replaced ) : $replaced; // phpcs:ignore Generic.Files.LineLength
				if ( is_string( $replaced ) && $replaced !== $contents ) {
					file_put_contents( $meta['main'], $replaced );
					$updated[] = $meta['main'];
				}
			}
		}

		// Update readme Stable tag if present at project root.
		$readme = rtrim( (string) $base_path, '/\\' ) . DIRECTORY_SEPARATOR . 'readme.txt';
		if ( file_exists( $readme ) ) {
			if ( $dry_run ) {
				$updated[] = $readme;
			} else {
				$raw      = file_get_contents( $readme );
				$replaced = is_string( $raw ) ? preg_replace( '/^(Stable tag:\s*)(.*)$/mi', '$1' . $new_version, $raw ) : null;
				if ( is_string( $replaced ) && $replaced !== $raw ) {
					file_put_contents( $readme, $replaced );
					$updated[] = $readme;
				}
			}
		}

		// Update package.json version if present.
		$pkg = rtrim( (string) $base_path, '/\\' ) . DIRECTORY_SEPARATOR . 'package.json';
		if ( file_exists( $pkg ) ) {
			if ( $dry_run ) {
				$updated[] = $pkg;
			} else {
				$raw  = file_get_contents( $pkg );
				$data = is_string( $raw ) ? json_decode( $raw, true ) : null;
				if ( is_array( $data ) ) {
					$data['version'] = $new_version;
					$this->write_json_file( $pkg, $data );
					$updated[] = $pkg;
				}
			}
		}

		return $updated;
	}

	/**
	 * Write JSON with formatting.
	 *
	 * @param string               $path File path.
	 * @param array<string, mixed> $data Data.
	 * @return bool
	 */
	protected function write_json_file( $path, array $data ) {
		$json = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			return false;
		}
		$json .= PHP_EOL;
		return false !== file_put_contents( $path, $json );
	}

	// ---------------------------------------------------------------------
	// Update helpers (i18n).
	// ---------------------------------------------------------------------

	/**
	 * Parse update options (placeholder for future flags).
	 *
	 * @param array<int,mixed> $args Args.
	 * @return array<string,mixed>
	 */
	protected function parse_options( array $args ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName
		return array(
			'domain' => 'wpmoo',
			'output' => 'languages',
		);
	}

	/**
	 * Try to refresh translations using WP-CLI if available.
	 *
	 * @param array<string,mixed> $options Options.
	 * @return string|null Path to POT file when generated.
	 */
	protected function refresh_translations( array $options ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName
		$slug   = $this->plugin_slug();
		$outdir = rtrim( self::base_path(), '/\\' ) . DIRECTORY_SEPARATOR . (string) $options['output'];
		$this->ensure_directory( $outdir . DIRECTORY_SEPARATOR );
		$target = $outdir . DIRECTORY_SEPARATOR . $slug . '.pot';

		list( $code ) = $this->execute_command( 'which', array( 'wp' ) );
		if ( 0 === $code ) {
			list( $status, $lines ) = $this->execute_command( 'wp', array( 'i18n', 'make-pot', '.', $target ), self::base_path() );
			$this->output_command_lines( $lines );
			return 0 === $status ? $target : null;
		}

		Console::warning( 'WP-CLI not found; skipping POT generation.' );
		return null;
	}

	// ---------------------------------------------------------------------
	// Safe WordPress bridges.
	// ---------------------------------------------------------------------

	/**
	 * Safely invoke do_action when available.
	 *
	 * @param string $hook Hook.
	 * @param mixed  ...$args Args.
	 * @return void
	 */
	protected function do_action_safe( $hook, ...$args ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName
		if ( function_exists( 'do_action' ) ) {
			@do_action( $hook, ...$args );
		}
	}

	/**
	 * Safely invoke apply_filters when available.
	 *
	 * @param string $hook  Hook.
	 * @param mixed  $value Value.
	 * @param mixed  ...$args Args.
	 * @return mixed
	 */
	protected function apply_filters_safe( $hook, $value, ...$args ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName
		if ( function_exists( 'apply_filters' ) ) {
			return @apply_filters( $hook, $value, ...$args );
		}
		return $value;
	}
}

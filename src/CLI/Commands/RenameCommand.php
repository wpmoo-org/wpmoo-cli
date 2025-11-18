<?php

namespace WPMoo\CLI\Commands;

use WPMoo\CLI\Contracts\CommandInterface;
use WPMoo\CLI\Support\Base;
use WPMoo\CLI\Console;

/**
 * Rename command to rename plugins and update relevant files.
 *
 * @package WPMoo\CLI\Commands
 */
class RenameCommand extends Base implements CommandInterface {
	public function handle( array $args = array() ) {
		// Check if rename is allowed in current context.
		$config = self::get_context_config_static();
		if ( ! $config['allow_deploy_dist'] ) {
			Console::error( 'Rename commands are not allowed in this context.' );
			Console::line();
			Console::comment( 'Current context: ' . $config['message'] );
			if ( isset( $config['name'] ) ) {
				Console::comment( 'Project name: ' . $config['name'] );
			}
			Console::line();
			return 1;
		}

		$base_path = self::base_path();

		$opts  = $this->parseArgs( $args );
		$flags = $this->parseFlags( $args );
		if ( ! isset( $opts['name'] ) && ! isset( $opts['slug'] ) && ! isset( $opts['namespace'] ) ) {
			$positional = $this->parsePositionals( $args );
			$opts       = array_merge( $opts, $positional );
		}

		$meta      = $this->detect_project_metadata( rtrim( $base_path, '/\\' ) );
		$old_name  = isset( $meta['name'] ) ? (string) $meta['name'] : '';
		$old_slug  = isset( $meta['slug'] ) ? (string) $meta['slug'] : '';
		$main_file = isset( $meta['main'] ) ? (string) $meta['main'] : '';
		$detected  = $this->detect_primary_namespace( $base_path );
		$old_ns    = $detected ? (string) $detected : '';
		$old_ns    = rtrim( $old_ns, '\\' );

		$ns_file = rtrim( $base_path, '/\\' ) . DIRECTORY_SEPARATOR . 'namespace';
		$saved   = $this->readNamespaceFile( $ns_file );

		if ( $flags['update'] ) {
			if ( $old_name || $old_ns ) {
				$fallback_ns = $this->deriveNamespace( $old_name ? $old_name : $old_slug );
				$this->writeNamespaceFile( $ns_file, $old_name ? $old_name : $old_slug, $old_ns ? $old_ns : $fallback_ns );
				Console::info( 'Updated namespace file with current Name/Namespace.' );
				return 0;
			}
		}

		if ( $flags['reset'] && $saved ) {
			$opts['name']      = $saved['name'];
			$opts['namespace'] = $saved['namespace'];
		}

		$new_name          = isset( $opts['name'] ) && '' !== (string) $opts['name'] ? (string) $opts['name'] : ( $saved['name'] ?? $old_name );
		$derived_namespace = $new_name ? $this->deriveNamespace( $new_name ) : '';
		$new_ns            = isset( $opts['namespace'] ) && '' !== (string) $opts['namespace']
			? (string) $opts['namespace']
			: ( $saved['namespace'] ?? ( $derived_namespace ? $derived_namespace : $old_ns ) );
		$new_slug          = isset( $opts['slug'] ) && '' !== (string) $opts['slug']
			? (string) $opts['slug']
			: ( $new_name ? $this->deriveSlugHyphen( $new_name ) : $old_slug );

		if ( '' === $new_name && '' === $new_slug && '' === $new_ns ) {
			Console::error( 'Unable to determine new values. Provide at least --name, --slug, or --namespace.' );
			return 1;
		}

		$slug_underscore = str_replace( '-', '_', $new_slug );
		$vars_key        = $slug_underscore . '_vars';
		$hyphen_id       = $new_slug;

		Console::line();
		Console::comment( 'Renaming plugin...' );
		Console::line();
		if ( $new_name ) {
			Console::line( '  ' . $new_name . '          Name of plugin' );
		}
		if ( $new_ns ) {
			Console::line( '  ' . $new_ns . '            Namespace (PSR-4)' );
		}
		if ( $new_slug ) {
			Console::line( sprintf( '  %s_slug     Plugin slug', $slug_underscore ) );
			Console::line( '  ' . $vars_key . '     Plugin vars (CPT/Taxonomy)' );
			Console::line( '  ' . $hyphen_id . '          Internal ID (CSS/JS)' );
			Console::line( '  ' . $hyphen_id . '.php  Main plugin file' );
		}
		Console::line();

		$changed = array();

		// 1) Rename main plugin file to <slug>.php when possible.
		if ( $main_file && $new_slug ) {
			$dir      = dirname( $main_file );
			$new_main = $dir . DIRECTORY_SEPARATOR . $new_slug . '.php';
			if ( strcasecmp( basename( $main_file ), basename( $new_main ) ) !== 0 ) {
				if ( @rename( $main_file, $new_main ) ) {
					$changed[] = $new_main;
					$main_file = $new_main;
					Console::line( '   • Renamed main file to ' . $this->relative_path( $new_main ) );
				} else {
					Console::warning( 'Could not rename main plugin file. You may rename it manually to ' . basename( $new_main ) );
				}
			}
		}

		// 2) Update plugin header (Plugin Name, Text Domain) in main file.
		if ( $main_file && file_exists( $main_file ) ) {
			$contents = file_get_contents( $main_file );
			if ( false !== $contents ) {
				$updated = $contents;
				if ( $new_name ) {
					$updated = preg_replace( '/^([ \t\/*#@]*Plugin Name:\s*).*/mi', '$1' . addcslashes( $new_name, '\\$' ), $updated );
				}
				if ( $new_slug ) {
					if ( preg_match( '/^([ \t\/*#@]*Text Domain:\s*).*/mi', $updated ) ) {
						$updated = preg_replace( '/^([ \t\/*#@]*Text Domain:\s*).*/mi', '$1' . addcslashes( $new_slug, '\\$' ), $updated );
					} else {
						$updated = preg_replace( '/^([ \t\/*#@]*Plugin Name:.*)$/mi', "$1\n * Text Domain: " . addcslashes( $new_slug, '\\$' ), $updated, 1 );
					}
				}
				if ( $updated !== null && $updated !== $contents ) {
					if ( false !== file_put_contents( $main_file, $updated ) ) {
						$changed[] = $main_file;
						Console::line( '   • Updated plugin header in ' . $this->relative_path( $main_file ) );
					}
				}
			}
		}

		// 3) Rename POT file if languages directory exists.
		if ( $new_slug ) {
			$lang_dir = rtrim( $base_path, '/\\' ) . DIRECTORY_SEPARATOR . 'languages';
			$old_pot  = $lang_dir . DIRECTORY_SEPARATOR . $old_slug . '.pot';
			$new_pot  = $lang_dir . DIRECTORY_SEPARATOR . $new_slug . '.pot';
			if ( file_exists( $old_pot ) && strcasecmp( $old_pot, $new_pot ) !== 0 ) {
				if ( @rename( $old_pot, $new_pot ) ) {
					$changed[] = $new_pot;
					Console::line( '   • Renamed POT file to ' . $this->relative_path( $new_pot ) );
				}
			}
		}

		// 4) Replace occurrences in common files: composer.json, readme, PHP namespaces.
		$map = array();
		if ( $old_name && $new_name && $new_name !== $old_name ) {
			$map[ $old_name ] = $new_name;
		}
		if ( $old_slug && $new_slug && $new_slug !== $old_slug ) {
			// Hyphenated and underscored variants.
			$map[ $old_slug ]                          = $new_slug;
			$map[ str_replace( '-', '_', $old_slug ) ] = str_replace( '-', '_', $new_slug );
		}
		if ( $old_ns && $new_ns && $new_ns !== $old_ns ) {
			$map[ $old_ns . '\\' ] = $new_ns . '\\';
		}

		if ( ! empty( $map ) ) {
			$filter = function ( $path ) {
				$path = (string) $path;
				// Skip vendor, node_modules, dist build output.
				if ( strpos( $path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR ) !== false ) {
					return false;
				}
				if ( strpos( $path, DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR ) !== false ) {
					return false;
				}
				if ( strpos( $path, DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR ) !== false ) {
					return false;
				}
				return true;
			};
			$this->replaceInTree( rtrim( $base_path, '/\\' ), $map, $changed, $filter );
		}

		// 5) Update namespace declarations across src/ if namespace root changed.
		if ( $old_ns && $new_ns && $new_ns !== $old_ns ) {
			$src = rtrim( $base_path, '/\\' ) . DIRECTORY_SEPARATOR . 'src';
			$this->fixNamespaceDeclarations( $src, $new_ns, $changed );
		}

		// 6) Report
		if ( ! empty( $changed ) ) {
			Console::line();
			Console::info( 'Updated files:' );
			foreach ( $changed as $file ) {
				Console::line( '   • ' . $this->relative_path( $file ) );
			}
		}
		Console::line();
		Console::info( 'Rename completed.' );
		Console::line();
		Console::comment( 'Tip: run `php moo update` to refresh translation templates.' );
		Console::line();
		return 0;
	}

	protected function parseArgs( array $args ) {
		$out = array(
			'name'      => null,
			'slug'      => null,
			'namespace' => null,
		);
		foreach ( $args as $a ) {
			if ( ! is_string( $a ) || '' === trim( $a ) ) {
				continue;
			}
			if ( 0 === strpos( $a, '--name=' ) ) {
				$out['name'] = substr( $a, 7 );
			} elseif ( 0 === strpos( $a, '--slug=' ) ) {
				$out['slug'] = substr( $a, 7 );
			} elseif ( 0 === strpos( $a, '--namespace=' ) ) {
				$out['namespace'] = substr( $a, 12 );
			}
		}
		return $out;
	}

	protected function parseFlags( array $args ) {
		$out = array(
			'update' => false,
			'reset'  => false,
		);
		foreach ( $args as $a ) {
			if ( ! is_string( $a ) || '' === trim( $a ) ) {
				continue;
			}
			if ( '--update' === $a ) {
				$out['update'] = true;
			}
			if ( '--reset' === $a ) {
				$out['reset'] = true;
			}
		}
		return $out;
	}

	protected function parsePositionals( array $args ) {
		$out = array(
			'name'      => null,
			'namespace' => null,
		);
		$pos = array();
		foreach ( $args as $a ) {
			if ( ! is_string( $a ) || '' === trim( $a ) ) {
				continue;
			}
			if ( 0 === strpos( $a, '--' ) ) {
				continue;
			}
			$pos[] = $a;
		}
		if ( ! empty( $pos ) ) {
			$out['name'] = (string) $pos[0];
			if ( isset( $pos[1] ) ) {
				$out['namespace'] = (string) $pos[1];
			}
		}
		return $out;
	}

	protected function deriveNamespace( $name ) {
		$clean  = preg_replace( '/[^A-Za-z0-9]+/', ' ', (string) $name );
		$parts  = preg_split( '/\s+/', trim( (string) $clean ) );
		$studly = '';
		foreach ( $parts as $p ) {
			if ( $p === '' ) {
				continue;
			}
			if ( preg_match( '/[A-Z]/', $p ) ) {
				$studly .= $p;
			} elseif ( preg_match( '/^[a-z0-9]+$/', $p ) ) {
				$studly .= ucfirst( $p );
			} else {
				$studly .= ucfirst( strtolower( $p ) );
			}
		}
			return $studly ? $studly : '';
	}

	protected function deriveSlugHyphen( $name ) {
		$slug = strtolower( (string) $name );
		$slug = preg_replace( '/[^a-z0-9]+/', '-', $slug );
		$slug = trim( (string) $slug, '-' );
			return $slug ? $slug : 'plugin';
	}

	protected function readNamespaceFile( $path ) {
		if ( ! file_exists( $path ) ) {
			return null;
		}
		$raw = @file_get_contents( $path );
		if ( false === $raw ) {
			return null;
		}
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return null;
		}
		$parts = array_map( 'trim', explode( ',', $raw ) );
		if ( empty( $parts[0] ) ) {
			return null;
		}
		return array(
			'name'      => (string) $parts[0],
			'namespace' => isset( $parts[1] ) ? (string) $parts[1] : $this->deriveNamespace( $parts[0] ),
		);
	}

	protected function writeNamespaceFile( $path, $name, $namespace ) {
		$line = rtrim( (string) $name ) . ',' . rtrim( (string) $namespace ) . "\n";
		@file_put_contents( $path, $line );
	}

	protected function replaceInTree( $root, array $map, array &$changed, $filter = null ) {
		if ( ! is_dir( $root ) ) {
			return;
		}
		$it = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);
		foreach ( $it as $file ) {
			$path = $file->getPathname();
			if ( $filter && ! $filter( $path ) ) {
				continue;
			}
			if ( $file->isDir() ) {
				continue;
			}
			$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, array( 'php', 'json', 'md', 'txt' ), true ) ) {
				continue;
			}
			$contents = @file_get_contents( $path );
			if ( false === $contents ) {
				continue;
			}
			$updated = $contents;
			foreach ( $map as $search => $replace ) {
				$updated = str_replace( $search, $replace, $updated );
			}
			if ( $updated !== $contents ) {
				if ( false !== @file_put_contents( $path, $updated ) ) {
					$changed[] = $path;
				}
			}
		}
	}

	protected function fixNamespaceDeclarations( $root, $new_ns, array &$changed ) {
		if ( ! is_dir( $root ) ) {
			return;
		}
		$it = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);
		foreach ( $it as $file ) {
			if ( $file->isDir() ) {
				continue;
			}
			$path = $file->getPathname();
			if ( substr( $path, -4 ) !== '.php' ) {
				continue;
			}
			$contents = @file_get_contents( $path );
			if ( false === $contents ) {
				continue;
			}
			$updated = preg_replace_callback(
				'/^(\s*)namespace\s+([^;]+);/mi',
				function ( $m ) use ( $new_ns ) {
					$indent = (string) $m[1];
					$full   = trim( (string) $m[2] );
					$suffix = '';
					$pos    = strpos( $full, '\\' );
					if ( $pos !== false ) {
						$suffix = substr( $full, $pos );
					}
					return $indent . 'namespace ' . $new_ns . $suffix . ';';
				},
				$contents
			);
			if ( $updated !== null ) {
				$updated = preg_replace( '/(\*\/)(\r?\n)(?!\r?\n)(\s*namespace\s)/', "$1$2\n$3", $updated );
			}
			if ( $updated !== null && $updated !== $contents ) {
				if ( false !== @file_put_contents( $path, $updated ) ) {
					$changed[] = $path;
				}
			}
		}
	}
}

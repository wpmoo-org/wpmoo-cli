<?php

namespace WPMoo\CLI\Commands;

use WPMoo\CLI\Console;

class CheckCommand {
	/**
	 * Run local project checks: composer validate, PHPCBF, PHPCS, PHPStan, PHPUnit.
	 *
	 * @param array $args Optional flags: --no-fix, --quick.
	 * @return int Process exit status.
	 */
	public function handle( array $args ) {
		$opts = $this->parseOptions( $args );
		$cwd  = getcwd();
		$root = $cwd ? $cwd : '.';

		Console::line();
		Console::comment( 'WPMoo project checks' );
		Console::line();

		$failed = false;

		// 1) Composer validate (non-strict lock)
		if ( file_exists( $root . '/composer.json' ) ) {
			$composer = $this->findExecutable( array( 'composer' ) );
			if ( $composer ) {
				Console::comment( '→ Composer validate' );
				$status = $this->run( $composer, array( 'validate', '--no-check-lock', '--ansi' ) );
				$failed = $failed || ( 0 !== $status );
			} else {
				Console::warning( 'Composer not found; skipping validate' );
			}
		}

		// 2) PHPCBF (auto-fix) unless disabled
		if ( ! $opts['no-fix'] ) {
			$phpcbf = $this->localBin( 'phpcbf' );
			if ( $phpcbf ) {
				Console::comment( '→ PHPCBF (auto-fix safe issues)' );
				$standard = file_exists( $root . '/phpcs.xml' ) ? array( '--standard=phpcs.xml' ) : array();
				$this->run( $phpcbf, array_merge( $standard, array( '-p', 'src' ) ) );
			} else {
				Console::warning( 'phpcbf not found; skipping auto-fix' );
			}
		}

		// 3) PHPCS (errors only)
		$phpcs = $this->localBin( 'phpcs' );
		if ( $phpcs ) {
			Console::comment( '→ PHPCS (errors only)' );
			$standard = file_exists( $root . '/phpcs.xml' ) ? array( '--standard=phpcs.xml' ) : array();
			$status   = $this->run( $phpcs, array_merge( array( '-n' ), $standard, array( 'src' ) ) );
			$failed   = $failed || ( 0 !== $status );
		} else {
			Console::warning( 'phpcs not found; skipping' );
		}

		if ( ! $opts['quick'] ) {
			// 4) PHPStan (if configured)
			$phpstan = $this->localBin( 'phpstan' );
			if ( $phpstan && ( file_exists( $root . '/phpstan.neon' ) || file_exists( $root . '/phpstan.neon.dist' ) ) ) {
				Console::comment( '→ PHPStan' );
				$memory = getenv( 'WPMOO_PHPSTAN_MEMORY' );
				$limit  = $memory && '' !== $memory ? (string) $memory : '1G';
				$status = $this->run( $phpstan, array( 'analyse', '--no-progress', '--memory-limit=' . $limit ) );
				$failed = $failed || ( 0 !== $status );
			} else {
				Console::warning( 'PHPStan not configured; skipping' );
			}

			// 5) PHPUnit (if configured)
			$phpunit = $this->findExecutable( array( 'vendor/bin/phpunit', 'phpunit' ) );
			if ( $phpunit && ( file_exists( $root . '/phpunit.xml' ) || file_exists( $root . '/phpunit.xml.dist' ) ) ) {
				Console::comment( '→ PHPUnit' );
				$status = $this->run( $phpunit, array( '-v' ) );
				$failed = $failed || ( 0 !== $status );
			} else {
				Console::warning( 'PHPUnit not configured; skipping' );
			}
		}

		Console::line();
		if ( $failed ) {
			Console::error( 'Checks completed with failures' );
			return 1;
		}
		Console::info( 'All checks passed' );
		return 0;
	}

	private function parseOptions( array $args ) {
		$opts = array(
			'no-fix' => false,
			'quick'  => false,
		);
		foreach ( $args as $arg ) {
			if ( '--no-fix' === $arg ) {
				$opts['no-fix'] = true; }
			if ( '--quick' === $arg ) {
				$opts['quick'] = true; }
		}
		return $opts;
	}

	private function localBin( $name ) {
		$path = 'vendor/bin/' . $name;
		if ( file_exists( $path ) ) {
			return PHP_OS_FAMILY === 'Windows' ? $path . '.bat' : $path;
		}
		return $this->findExecutable( array( $name ) );
	}

	private function findExecutable( array $candidates ) {
		foreach ( $candidates as $candidate ) {
			if ( file_exists( $candidate ) && is_file( $candidate ) ) {
				return $candidate; }
		}
		$paths = explode( PATH_SEPARATOR, (string) getenv( 'PATH' ) );
		foreach ( $candidates as $candidate ) {
			foreach ( $paths as $p ) {
				$full = rtrim( $p, '/\\' ) . DIRECTORY_SEPARATOR . $candidate;
				if ( file_exists( $full ) ) {
					return $full; }
			}
		}
		return null;
	}

	private function run( $bin, array $args ) {
		$cmd = escapeshellcmd( $bin );
		foreach ( $args as $a ) {
			$cmd .= ' ' . ( '' === $a ? "''" : escapeshellarg( (string) $a ) );
		}
		$cmd  .= ' 2>&1';
		$lines = array();
		$code  = 0;
		exec( $cmd, $lines, $code );
		foreach ( $lines as $line ) {
			Console::line( '   • ' . $line );
		}
		return (int) $code;
	}
}

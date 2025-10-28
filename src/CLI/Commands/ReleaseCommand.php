<?php

namespace WPMoo\CLI\Commands;

use WPMoo\CLI\Contracts\CommandInterface;
use WPMoo\CLI\Console;

class ReleaseCommand implements CommandInterface {
	public function handle( array $args = array() ) {
		// Options.
		$opts = $this->parseOptions( $args );

		Console::line();
		Console::comment( 'Starting release flow…' );

		// 1) Optional version bump.
		if ( $opts['bump'] || $opts['version'] ) {
			$version_args = array();
			if ( $opts['version'] ) {
				$version_args[] = '--explicit=' . $opts['version'];
			} elseif ( $opts['bump'] ) {
				$version_args[] = '--bump=' . $opts['bump'];
			}
			if ( $opts['dry-run'] ) {
				$version_args[] = '--dry-run';
			}
			$status = ( new VersionCommand() )->handle( $version_args );
			if ( 0 !== $status ) {
				Console::error( 'Version step failed. Aborting release.' );
				return (int) $status;
			}
		}

		// 2) Update translations and housekeeping.
		if ( ! $opts['skip-update'] ) {
			$status = ( new UpdateCommand() )->handle( array() );
			if ( 0 !== $status ) {
				Console::warning( 'Update step reported issues.' );
			}
		}

		// 3) Build assets.
		if ( ! $opts['skip-build'] ) {
			$build_args = array();
			if ( $opts['pm'] ) {
				$build_args[] = '--pm=' . $opts['pm'];
			}
			if ( $opts['install'] ) {
				$build_args[] = '--install';
			}
			$status = ( new BuildCommand() )->handle( $build_args );
			if ( 0 !== $status ) {
				Console::warning( 'Build step reported issues.' );
			}
		}

		// 4) Package.
		$pack_status = 0;
		if ( $opts['deploy'] ) {
			$deploy_args = array();
			if ( $opts['zip'] ) {
				$deploy_args[] = '--zip';
			}
			$pack_status = ( new DeployCommand() )->handle( $deploy_args );
		} else {
			// Default to dist archive.
			$dist_args = array();
			if ( $opts['zip'] ) {
				$dist_args[] = '--zip';
			}
			$pack_status = ( new DistCommand() )->handle( $dist_args );
		}

		if ( 0 !== $pack_status ) {
			Console::warning( 'Packaging step reported issues.' );
		}

		Console::line();
		Console::info( 'Release flow finished.' );
		Console::line();
		return 0;
	}

	protected function parseOptions( array $args ) {
		$opts = array(
			'bump'        => null, // patch|minor|major.
			'version'     => null, // explicit x.y.z.
			'pm'          => null, // npm|yarn|pnpm|bun.
			'install'     => false,
			'skip-build'  => false,
			'skip-update' => false,
			'deploy'      => false,
			'zip'         => false,
			'dry-run'     => false,
		);
		foreach ( $args as $a ) {
			$a = (string) $a;
			if ( 0 === strpos( $a, '--bump=' ) ) {
				$opts['bump'] = substr( $a, 7 );
			} elseif ( 0 === strpos( $a, '--version=' ) || 0 === strpos( $a, '--explicit=' ) ) {
				$parts           = explode( '=', $a, 2 );
				$opts['version'] = $parts[1] ?? null;
			} elseif ( 0 === strpos( $a, '--pm=' ) ) {
				$opts['pm'] = substr( $a, 5 );
			} elseif ( '--install' === $a || '--force-install' === $a ) {
				$opts['install'] = true;
			} elseif ( '--skip-build' === $a ) {
				$opts['skip-build'] = true;
			} elseif ( '--skip-update' === $a ) {
				$opts['skip-update'] = true;
			} elseif ( '--deploy' === $a ) {
				$opts['deploy'] = true;
			} elseif ( '--zip' === $a ) {
				$opts['zip'] = true;
			} elseif ( '--dry-run' === $a ) {
				$opts['dry-run'] = true;
			}
		}
		return $opts;
	}
}

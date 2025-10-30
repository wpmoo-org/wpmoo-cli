<?php
/**
 * Runtime version helper for the wpmoo/wpmoo-cli package.
 *
 * Tries Composer's InstalledVersions API first; falls back to a dev marker.
 *
 * @package WPMoo\CLI
 * @since 0.1.0
 */

namespace WPMoo\CLI\Support;

class Version {
	/**
	 * Return the installed package version if available.
	 *
	 * @return string
	 */
	public static function current() {
		// Prefer Composer 2 runtime API when available.
		if ( class_exists( '\\Composer\\InstalledVersions' ) ) {
			try {
				/**
				 * Access Composer runtime metadata for this package.
				 *
				 * @psalm-suppress UndefinedClass
				 */
				$pretty = \Composer\InstalledVersions::getPrettyVersion( 'wpmoo/wpmoo-cli' );
				if ( is_string( $pretty ) && $pretty !== '' ) {
					return $pretty;
				}
			} catch ( \Throwable $e ) {
				// Fall through to the default return value below when Composer metadata
				// is not available or any runtime error is thrown. Assign to a dummy
				// variable to avoid empty-catch sniff without polluting output.
				$__ignored = $e; // phpcs:ignore Squiz.PHP.NonExecutableCode.Unreachable
			}
		}

		return 'dev';
	}
}

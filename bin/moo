#!/usr/bin/env php
<?php
/**
 * WPMoo CLI Entry Point.
 *
 * Bootstraps the WPMoo CLI application.
 *
 * @package WPMoo\CLI
 * @since 0.1.0
 * @link  https://wpmoo.org WPMoo – WordPress Micro Object-Oriented Framework.
 * @link  https://github.com/wpmoo/wpmoo GitHub Repository.
 * @license https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 */

// Set up error reporting.
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );

// Ensure we're running from the CLI.
if ( PHP_SAPI !== 'cli' ) {
	echo "This script must be run from the command line.\n";
	exit( 1 );
}

// Try to load the Composer autoloader.
$autoload_locations = array(
	// Project vendor directory (when run from project root).
	dirname( __DIR__ ) . '/vendor/autoload.php',
	// Package vendor directory (when installed as dependency).
	__DIR__ . '/../vendor/autoload.php',
	// Relative to current file (alternative location).
	dirname( __DIR__, 2 ) . '/vendor/autoload.php',
	// From the current working directory.
	getcwd() . '/vendor/autoload.php',
);

$autoload_loaded = false;
foreach ( $autoload_locations as $location ) {
	if ( file_exists( $location ) ) {
		require_once $location;
		$autoload_loaded = true;
		break;
	}
}

if ( ! $autoload_loaded ) {
	fwrite( STDERR, "Could not locate Composer autoloader. Run 'composer install'.\n" );
	exit( 1 );
}

// Run the CLI application.
use WPMoo\CLI\CLI;

CLI::run( $argv );
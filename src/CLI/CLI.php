<?php

namespace WPMoo\CLI;

use WPMoo\CLI\Contracts\CommandInterface;
use WPMoo\CLI\Commands\BuildCommand;
use WPMoo\CLI\Commands\DeployCommand;
use WPMoo\CLI\Commands\DistCommand;
use WPMoo\CLI\Commands\CheckCommand;
use WPMoo\CLI\Commands\InfoCommand;
use WPMoo\CLI\Commands\UpdateCommand;
use WPMoo\CLI\Commands\VersionCommand;
use WPMoo\CLI\Commands\ReleaseCommand;
use WPMoo\CLI\Commands\RenameCommand;
use WPMoo\CLI\Commands\PluginCheckCommand;
use WPMoo\CLI\Console;
use WPMoo\CLI\Support\Base;

class CLI extends Base {
	protected $commands = array();

	public function __construct() {
		$this->commands = array(
			'info'    => new InfoCommand(),
			'update'  => new UpdateCommand(),
			'version' => new VersionCommand(),
			'dist'    => new DistCommand(),
			'check'   => new CheckCommand(),
			'wp-check'=> new PluginCheckCommand(),
			'build'   => new BuildCommand(),
			'deploy'  => new DeployCommand(),
			'release' => new ReleaseCommand(),
			'rename'  => new RenameCommand(),
		);
	}

	public static function run( $argv ) {
		( new self() )->handle( $argv ); }

	public function handle( array $argv ) {
		$command = isset( $argv[1] ) ? (string) $argv[1] : 'help';
		if ( 'help' === $command || ! isset( $this->commands[ $command ] ) ) {
			$this->renderHelp();
			return 0; }
		$args = array_slice( $argv, 2 );
		return (int) $this->commands[ $command ]->handle( $args );
	}

	protected function renderHelp() {
		$this->renderWelcome();
		Console::line( 'Usage:' );
		Console::line( '  moo <command> [options]' );
		Console::line();
		Console::comment( 'Available commands:' );
		$definitions = $this->definitions();
		$width       = 0;
		foreach ( array_keys( $definitions ) as $cmd ) {
			$width = max( $width, strlen( $cmd ) ); }
		foreach ( $definitions as $cmd => $desc ) {
			$padding = str_repeat( ' ', $width - strlen( $cmd ) + 2 );
			Console::line( '  ' . $cmd . $padding . $desc ); }
		Console::line();
	}

	protected function definitions() {
		return array(
			'info'    => 'Show framework info',
			'update'  => 'Run maintenance tasks (translations, etc.)',
			'version' => 'Bump framework version across manifests',
			'build'   => 'Build front-end assets',
			'deploy'  => 'Create a deployable copy (optionally zipped)',
			'dist'    => 'Produce a distributable archive',
			'check'   => 'Composer validate, PHPCBF, PHPCS, PHPStan, PHPUnit',
			'wp-check'=> 'Run WordPress Plugin Check and pretty-print results',
			'release' => 'Run release flow (version, update, build, package)',
			'rename'  => 'Rename starter plugin (name/slug/namespace)',
		);
	}

	protected function renderWelcome() {
		$summary = $this->environmentSummary();
		Console::line();
		foreach ( $this->logoLines() as $line ) {
			Console::banner( $line ); }
		$version = $summary['version'] ? $summary['version'] : 'dev';
		Console::comment( 'WPMoo Version ' . $version );
		Console::line();
		$wp_cli = $summary['wp_cli_version'] ? $summary['wp_cli_version'] : 'not detected';
		Console::comment( '→ WP-CLI version: ' . $wp_cli );
		Console::comment( sprintf( '→ Current Plugin File, Name, Namespace: \'%s\', \'%s\', \'%s\'', $summary['plugin_file'] ? $summary['plugin_file'] : 'n/a', $summary['plugin_name'] ? $summary['plugin_name'] : 'n/a', $summary['plugin_namespace'] ? $summary['plugin_namespace'] : 'n/a' ) );
		if ( $summary['plugin_version'] ) {
			Console::comment( '→ Plugin version: ' . $summary['plugin_version'] ); }
		Console::line();
	}

	// Local implementations to avoid depending on Support\Base methods
	// that are not present in the framework version.
	protected function environmentSummary() {
		$base_path  = self::framework_base_path();
		$metadata   = self::detect_project_metadata( rtrim( $base_path, '/\\' ) );
		$namespace  = self::detect_primary_namespace( $base_path );
		$version    = defined( 'WPMOO_VERSION' ) ? WPMOO_VERSION : self::detect_current_version( $base_path );
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

	protected function logoLines() {
		return array(
			'░██       ░██ ░█████████  ░███     ░███                       ',
			'░██       ░██ ░██     ░██ ░████   ░████                       ',
			'░██  ░██  ░██ ░██     ░██ ░██░██ ░██░██  ░███████   ░███████  ',
			'░██ ░████ ░██ ░█████████  ░██ ░████ ░██ ░██    ░██ ░██    ░██ ',
			'░██░██ ░██░██ ░██         ░██  ░██  ░██ ░██    ░██ ░██    ░██ ',
			'░████   ░████ ░██         ░██       ░██ ░██    ░██ ░██    ░██ ',
			'░███     ░███ ░██         ░██       ░██  ░███████   ░███████  ',
		);
	}
}

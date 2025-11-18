<?php
/**
 * WPMoo CLI — Application entry point.
 *
 * Registers available commands and dispatches incoming argv to the relevant
 * command handler. Provides a small built‑in help/usage renderer and an
 * ASCII logo banner with environment details.
 *
 * @package WPMoo\CLI
 * @since 0.1.0
 * @link  https://wpmoo.org WPMoo – WordPress Micro Object-Oriented Framework.
 * @link  https://github.com/wpmoo/wpmoo GitHub Repository.
 * @license https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 */

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
use WPMoo\CLI\Support\Version;

/**
 * CLI kernel.
 *
 * Responsible for registering commands and routing argv.
 */
class CLI extends Base {
	/**
	 * Registered commands map (command => handler).
	 *
	 * @var array<string, CommandInterface>
	 */
	protected $commands = array();

	public function __construct() {
		// Register built‑in commands. Keys are CLI verbs; values are handlers.
		$this->commands = array(
			'info'     => new InfoCommand(),
			'update'   => new UpdateCommand(),
			'version'  => new VersionCommand(),
			'dist'     => new DistCommand(),
			'check'    => new CheckCommand(),
			'wp-check' => new PluginCheckCommand(),
			'build'    => new BuildCommand(),
			'deploy'   => new DeployCommand(),
			'release'  => new ReleaseCommand(),
			'rename'   => new RenameCommand(),
		);
	}

	/**
	 * Static bootstrap used by the bin script.
	 *
	 * @param array<int, string> $argv Raw argv vector (including program name).
	 * @return void
	 */
	public static function run( $argv ) {
		( new self() )->handle( $argv );
	}

	/**
	 * Dispatch argv to a command handler.
	 *
	 * @param array<int, string> $argv Raw argv vector.
	 * @return int Process exit status (0 on success).
	 */
	public function handle( array $argv ) {
		$command = isset( $argv[1] ) ? (string) $argv[1] : 'help';
		if ( 'help' === $command || ! isset( $this->commands[ $command ] ) ) {
			$this->renderHelp();
			return 0;
		}
		$args = array_slice( $argv, 2 );
		return (int) $this->commands[ $command ]->handle( $args );
	}

	/**
	 * Render usage and the list of available commands.
	 *
	 * @return void
	 */
	protected function renderHelp() {
		$this->renderWelcome();
		Console::line( 'Usage:' );
		Console::line( '  moo <command> [options]' );
		Console::line();
		Console::comment( 'Available commands:' );
		$definitions = $this->definitions();
		$width       = 0;
		foreach ( array_keys( $definitions ) as $cmd ) {
			$width = max( $width, strlen( $cmd ) );
		}
		foreach ( $definitions as $cmd => $desc ) {
			$padding = str_repeat( ' ', $width - strlen( $cmd ) + 2 );
			Console::line( '  ' . $cmd . $padding . $desc );
		}
		Console::line();
	}

	/**
	 * Command definitions used for help text.
	 *
	 * @return array<string, string> Map of command => description.
	 */
	protected function definitions() {
		$config   = $this->context_config();
		$commands = array(
			'info'     => 'Show framework info',
			'update'   => 'Run maintenance tasks (translations, etc.)',
			'version'  => 'Bump framework version across manifests',
			'build'    => 'Build front-end assets',
			'deploy'   => 'Create a deployable copy (optionally zipped)',
			'dist'     => 'Produce a distributable archive',
			'check'    => 'Composer validate, PHPCBF, PHPCS, PHPStan, PHPUnit',
			'wp-check' => 'Run WordPress Plugin Check and pretty-print results',
			'release'  => 'Run release flow (version, update, build, package)',
			'rename'   => 'Rename starter plugin (name/slug/namespace)',
		);

		// Conditionally hide commands based on context.
		if ( ! $config['allow_deploy_dist'] ) {
			unset( $commands['deploy'] );
			unset( $commands['dist'] );
			unset( $commands['release'] );
			unset( $commands['rename'] );
			// Add explanation for unsupported contexts.
			$commands['context-info'] = 'Show current execution context';
		}

		return $commands;
	}

	/**
	 * Get context configuration.
	 *
	 * @return array<string, mixed>
	 */
	protected function context_config() {
		return \WPMoo\CLI\Support\Base::get_context_config_static();
	}

	/**
	 * Render context-specific welcome message.
	 *
	 * @return void
	 */
	protected function renderWelcome() {
		$context_config = $this->context_config();
		$summary        = $this->environmentSummary();

		Console::line();
		foreach ( $this->logoLines() as $line ) {
			Console::banner( $line );
		}

		$version = $summary['version'] ? $summary['version'] : 'dev';
		Console::comment( 'WPMoo Version ' . $version );
		// Also surface the CLI package version if available.
		Console::comment( '→ CLI version: ' . \WPMoo\CLI\Support\Version::current() );
		Console::line();

		// Show context-specific message.
		Console::comment( $context_config['message'] );

		if ( $context_config['context'] !== 'cli' || $context_config['allow_deploy_dist'] ) {
			$wp_cli = $summary['wp_cli_version'] ? $summary['wp_cli_version'] : 'not detected';
			Console::comment( '→ WP-CLI version: ' . $wp_cli );
			Console::comment( sprintf( '→ Current Plugin File, Name, Namespace: \'%s\', \'%s\', \'%s\'', $summary['plugin_file'] ? $summary['plugin_file'] : 'n/a', $summary['plugin_name'] ? $summary['plugin_name'] : 'n/a', $summary['plugin_namespace'] ? $summary['plugin_namespace'] : 'n/a' ) );
			if ( $summary['plugin_version'] ) {
				Console::comment( '→ Plugin version: ' . $summary['plugin_version'] );
			}
		}

		Console::line();
	}


	/**
	 * Collect a shallow environment summary for the banner.
	 *
	 * Local implementation to avoid hard dependency on framework helpers.
	 *
	 * @return array<string, mixed>
	 */
	protected function environmentSummary() {
		$base_path  = self::framework_base_path();
		$metadata   = self::detect_project_metadata( rtrim( $base_path, '/\\' ) );
		$namespace  = self::detect_primary_namespace( $base_path );
		$version    = defined( 'WPMOO_VERSION' ) ? WPMOO_VERSION : self::detect_current_version( $base_path );
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
	 * ASCII logo lines for the banner.
	 *
	 * @return array<int, string>
	 */
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

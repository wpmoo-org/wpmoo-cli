<?php
/**
 * Dev command for the WPMoo CLI.
 *
 * Handles setting up and running the development environment with watch mode.
 *
 * @package WPMoo\CLI\Commands
 * @since 0.1.0
 * @link  https://wpmoo.org WPMoo – WordPress Micro Object-Oriented Framework.
 * @link  https://github.com/wpmoo/wpmoo GitHub Repository.
 * @license https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 */

namespace WPMoo\CLI\Commands;

use WPMoo\CLI\Support\Base;
use WPMoo\CLI\Console;

/**
 * Dev command to handle development environment setup and watch mode.
 */
class DevCommand extends Base {
	/**
	 * Handle the dev command.
	 *
	 * @param array<int, mixed> $args Command arguments.
	 * @return int Exit status (0 for success, non-zero for failure).
	 */
	public function handle( array $args = array() ) {
		$options = $this->parse_build_options( $args );
		Console::line();
		Console::comment( 'Setting up development environment.' );

		// Ensure dependencies are installed.
		$install_result = $this->ensure_dependencies_installed( $options );
		if ( ! $install_result || ! $install_result['success'] ) {
			Console::error( 'Failed to install dependencies.' );
			Console::line();
			return 1;
		}

		$manager = $install_result['manager'];

		// Run the dev/watch script.
		Console::comment( 'Starting development server with watch mode.' );
		$success = $this->perform_build(
			array_merge(
				$options,
				array(
					'script'        => 'watch',
					'allow-missing' => true,
					'pm'            => $manager['name'],
				)
			)
		);
		if ( ! $success ) {
			Console::error( 'Development server failed to start.' );
			Console::line();
			return 1;
		}
		Console::line();
		return 0;
	}
}

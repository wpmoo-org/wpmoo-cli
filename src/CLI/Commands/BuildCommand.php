<?php
/**
 * Build command for the WPMoo CLI.
 *
 * Handles building front-end assets using the configured package manager.
 *
 * @package WPMoo\CLI\Commands
 * @since 0.1.0
 * @link  https://wpmoo.org WPMoo – WordPress Micro Object-Oriented Framework.
 * @link  https://github.com/wpmoo/wpmoo GitHub Repository.
 * @license https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 */

namespace WPMoo\CLI\Commands;

use WPMoo\CLI\Contracts\CommandInterface;
use WPMoo\CLI\Support\Base;
use WPMoo\CLI\Console;

/**
 * Build command to handle front-end asset compilation.
 */
class BuildCommand extends Base implements CommandInterface {
	/**
	 * Handle the build command.
	 *
	 * @param array<int, mixed> $args Command arguments.
	 * @return int Exit status (0 for success, non-zero for failure).
	 */
	public function handle( array $args = array() ) {
		$options = $this->parse_build_options( $args );
		Console::line();
		Console::comment( 'Building assets…' );
		$success = $this->perform_build( array_merge( $options, array( 'allow-missing' => true ) ) );
		if ( ! $success ) {
			Console::error( 'Asset build failed.' );
			Console::line();
			return 1; }
		Console::line();
		return 0;
	}
}

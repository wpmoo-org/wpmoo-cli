<?php
/**
 * Info command for the WPMoo CLI.
 *
 * Provides information about the PHP and WordPress environment.
 *
 * @package WPMoo\CLI\Commands
 * @since 0.1.0
 * @link  https://wpmoo.org WPMoo – WordPress Micro Object-Oriented Framework.
 * @link  https://github.com/wpmoo/wpmoo GitHub Repository.
 * @license https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 */

namespace WPMoo\CLI\Commands;

use WPMoo\CLI\Contracts\CommandInterface;
use WPMoo\CLI\Console;

/**
 * Info command to provide environment information.
 */
class InfoCommand implements CommandInterface {
	/**
	 * Handle the info command.
	 *
	 * @param array<int, mixed> $args Command arguments.
	 * @return int Exit status (0 for success).
	 */
	public function handle( array $args = array() ) {
		$php = PHP_VERSION;
		$wp  = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : 'n/a (CLI)';
		Console::info( 'WPMoo — WordPress Micro OOP Framework' );
		Console::comment( 'PHP: ' . $php );
		Console::comment( 'WP : ' . $wp );
		Console::line();
		return 0;
	}
}

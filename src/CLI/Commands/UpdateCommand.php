<?php
/**
 * Update command for the WPMoo CLI.
 *
 * Runs maintenance tasks including translation updates.
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
 * Update command to run maintenance tasks.
 */
class UpdateCommand extends Base implements CommandInterface {
	/**
	 * Handle the update command.
	 *
	 * @param array<int, mixed> $args Command arguments.
	 * @return int Exit status (0 for success).
	 */
	public function handle( array $args = array() ) {
		$options = $this->parse_options( $args );
		Console::line();
		Console::comment( 'Running WPMoo maintenance tasks…' );
		$pot_path = $this->refresh_translations( $options );
		if ( $pot_path ) {
			Console::info( 'Translations refreshed at ' . $this->relative_path( $pot_path ) ); }
		Console::line();
		return 0;
	}
}

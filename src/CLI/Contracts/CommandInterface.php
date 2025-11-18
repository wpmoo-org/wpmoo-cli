<?php
/**
 * Command interface for the WPMoo CLI.
 *
 * Defines the contract for CLI commands.
 *
 * @package WPMoo\CLI\Contracts
 * @since 0.1.0
 * @link  https://wpmoo.org WPMoo – WordPress Micro Object-Oriented Framework.
 * @link  https://github.com/wpmoo/wpmoo GitHub Repository.
 * @license https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 */

namespace WPMoo\CLI\Contracts;

/**
 * Interface for CLI commands.
 */
interface CommandInterface {
	/**
	 * Handle the command.
	 *
	 * @param array<int,mixed> $args
	 */
	public function handle( array $args = array() );
}

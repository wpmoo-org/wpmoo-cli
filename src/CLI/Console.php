<?php
/**
 * Console helper functions for CLI output formatting.
 *
 * Provides methods for colored output in the command-line interface.
 *
 * @package WPMoo\CLI
 * @since 0.1.0
 * @link  https://wpmoo.org WPMoo – WordPress Micro Object-Oriented Framework.
 * @link  https://github.com/wpmoo/wpmoo GitHub Repository.
 * @license https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 */

namespace WPMoo\CLI;

/**
 * Console helper functions for CLI output formatting.
 *
 * Provides methods for colored output in the command-line interface.
 */
class Console {
	/**
	 * Output an info message in green color.
	 *
	 * @param string $message Message to output.
	 * @return void
	 */
	public static function info( $message ) {
		echo "\033[32m{$message}\033[0m" . PHP_EOL; }

	/**
	 * Output a banner message in magenta color.
	 *
	 * @param string $message Message to output.
	 * @return void
	 */
	public static function banner( $message ) {
		echo "\033[35;1m{$message}\033[0m" . PHP_EOL; }

	/**
	 * Output an error message in red color.
	 *
	 * @param string $message Message to output.
	 * @return void
	 */
	public static function error( $message ) {
		echo "\033[31m{$message}\033[0m" . PHP_EOL; }

	/**
	 * Output a warning message in yellow color.
	 *
	 * @param string $message Message to output.
	 * @return void
	 */
	public static function warning( $message ) {
		echo "\033[33m{$message}\033[0m" . PHP_EOL; }

	/**
	 * Output a comment message in cyan color.
	 *
	 * @param string $message Message to output.
	 * @return void
	 */
	public static function comment( $message ) {
		echo "\033[36m{$message}\033[0m" . PHP_EOL; }

	/**
	 * Output a plain message without color.
	 *
	 * @param string $message Message to output.
	 * @return void
	 */
	public static function line( $message = '' ) {
		echo $message . PHP_EOL; }
}

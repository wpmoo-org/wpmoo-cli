<?php

/**
 * Console helper for the WPMoo CLI.
 *
 * Provides console output methods.
 *
 * @package WPMoo\CLI
 * @since 0.1.0
 * @link  https://wpmoo.org WPMoo – WordPress Micro Object-Oriented Framework.
 * @link  https://github.com/wpmoo/wpmoo GitHub Repository.
 * @license https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 */

namespace WPMoo\CLI;

/**
 * Console helper to provide output methods.
 */
class Console
{
    /**
     * Write an info message.
     *
     * @param string $message Message to write.
     * @return void
     */
    public static function info(string $message)
    {
        self::write('<info>' . $message . '</info>');
    }

    /**
     * Write a comment message.
     *
     * @param string $message Message to write.
     * @return void
     */
    public static function comment(string $message)
    {
        self::write('<comment>' . $message . '</comment>');
    }

    /**
     * Write a warning message.
     *
     * @param string $message Message to write.
     * @return void
     */
    public static function warning(string $message)
    {
        self::write('<fg=yellow>' . $message . '</>');
    }

    /**
     * Write an error message.
     *
     * @param string $message Message to write.
     * @return void
     */
    public static function error(string $message)
    {
        self::write('<error>' . $message . '</error>');
    }

    /**
     * Write a line.
     *
     * @param string $message Message to write.
     * @return void
     */
    public static function line(string $message = '')
    {
        fwrite(STDOUT, $message . "\n");
    }

    /**
     * Write output.
     *
     * @param string $message Message to write.
     * @return void
     */
    private static function write(string $message)
    {
        fwrite(STDOUT, $message . "\n");
    }
}

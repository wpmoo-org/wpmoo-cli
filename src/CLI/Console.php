<?php

namespace WPMoo\CLI;

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
class Console
{
    /**
     * Write an info message.
     *
     * @param string $message Message to write.
     * @return void
     */
    public static function info_message(string $message)
    {
        self::write_output('<info>' . $message . '</info>');
    }

    /**
     * Write a comment message.
     *
     * @param string $message Message to write.
     * @return void
     */
    public static function comment_message(string $message)
    {
        self::write_output('<comment>' . $message . '</comment>');
    }

    /**
     * Write a warning message.
     *
     * @param string $message Message to write.
     * @return void
     */
    public static function warning_message(string $message)
    {
        self::write_output('<fg=yellow>' . $message . '</>');
    }

    /**
     * Write an error message.
     *
     * @param string $message Message to write.
     * @return void
     */
    public static function error_message(string $message)
    {
        self::write_output('<error>' . $message . '</error>');
    }

    /**
     * Write a line.
     *
     * @param string $message Message to write.
     * @return void
     */
    public static function write_line(string $message = '')
    {
        fwrite(STDOUT, $message . "\n");
    }

    /**
     * Write output.
     *
     * @param string $message Message to write.
     * @return void
     */
    private static function write_output(string $message)
    {
        fwrite(STDOUT, $message . "\n");
    }
}

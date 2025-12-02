<?php

namespace WPMoo\CLI;

use WPMoo\CLI\CLIApplication;

/**
 * WPMoo CLI bootstrap.
 *
 * Sets up and runs the CLI application.
 *
 * @package WPMoo\CLI
 * @since 0.1.0
 * @link  https://wpmoo.org WPMoo – WordPress Micro Object-Oriented Framework.
 * @link  https://github.com/wpmoo/wpmoo GitHub Repository.
 * @license https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 */
class CLI
{
    /**
     * Run the CLI application.
     *
     * @param array<int, string> $argv Raw command line arguments.
     * @return void
     */
    public static function run(array $argv)
    {
        $application = new CLIApplication();
        $exit_code = $application->run();
        exit($exit_code);
    }
}

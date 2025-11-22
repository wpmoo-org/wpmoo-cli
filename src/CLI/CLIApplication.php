<?php

/**
 * WPMoo CLI Application.
 *
 * Integrates Symfony Console with WPMoo CLI commands.
 *
 * @package WPMoo\CLI
 * @since 0.1.0
 * @link  https://wpmoo.org WPMoo – WordPress Micro Object-Oriented Framework.
 * @link  https://github.com/wpmoo/wpmoo GitHub Repository.
 * @license https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 */

namespace WPMoo\CLI;

use Symfony\Component\Console\Application;
use WPMoo\CLI\Commands\InfoCommand;

/**
 * CLI Application class to register and run commands.
 */
class CLIApplication extends Application
{
    /**
     * Constructor to register commands.
     */
    public function __construct()
    {
        parent::__construct('WPMoo CLI', 'dev-main');

        // Register built-in commands.
        $commands = array(
            new InfoCommand(),
        );

        foreach ($commands as $command) {
            $this->add($command);
        }
    }
}

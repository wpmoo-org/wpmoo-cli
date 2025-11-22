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
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Command\HelpCommand;
use WPMoo\CLI\Commands\InfoCommand;
use WPMoo\CLI\Support\Banner;

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

    public function getHelp(): string
    {
        // Banner sınıfından statik olarak çağırın
        $help = Banner::getAsciiArt() . "\n" . parent::getHelp();
        return $help;
    }

    /**
     * Configure the default command.
     */
    public function getDefaultCommand(): string
    {
        return 'list';
    }

    /**
     * Get default commands provided by the application.
     *
     * @return array
     */
    protected function getDefaultCommands(): array
    {
        // Return only essential commands (but without default options)
        $defaultCommands = parent::getDefaultCommands();

        // Filter to only include commands we want (ListCommand)
        $filteredCommands = array();
        foreach ($defaultCommands as $command) {
            // Sadece ListCommand ve HelpCommand'i tutuyoruz.
            if ($command instanceof ListCommand) {
                $filteredCommands[] = $command;
            } elseif ($command instanceof HelpCommand) {
                // HelpCommand'in çalışması için tutuyoruz, ancak gizli yapıyoruz.
                $command->setHidden(true);
                $filteredCommands[] = $command;
            }
        }

        return $filteredCommands;
    }

    /**
     * Get the default input definition for the application.
     *
     * @return InputDefinition An InputDefinition instance
     */
    protected function getDefaultInputDefinition(): InputDefinition
    {
        // Start with the parent's default definition
        // $inputDefinition = parent::getDefaultInputDefinition();

        // Clear all options and add only the ones we want
        $inputDefinition = new InputDefinition(array(
            // 1. Visible Option: The mandatory help option.
            new InputOption(
                'help',
                'h',
                InputOption::VALUE_NONE,
                'Display help for the given command. When no command is given display help for the list command'
            ),

            // 2. Hidden Options: --ansi and --no-ansi are now active but hidden from the list.
            new InputOption(
                'ansi',
                null,
                InputOption::VALUE_NONE,
                'Forces ANSI output (color).'
            ),
            new InputOption(
                'no-ansi',
                null,
                InputOption::VALUE_NONE,
                'Disables ANSI output (color).'
            ),
        ));

        return $inputDefinition;
    }
}

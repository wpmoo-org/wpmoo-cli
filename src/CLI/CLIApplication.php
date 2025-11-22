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
 **/

namespace WPMoo\CLI;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Command\HelpCommand;
use WPMoo\CLI\Commands\InfoCommand;
use WPMoo\CLI\Commands\VersionCommand;
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
        // Get version from composer.json or use a default
        $version = $this->getVersion();
        parent::__construct('WPMoo CLI', $version);

        // Register built-in commands.
        $commands = array(
            new InfoCommand(),
            new VersionCommand(),
        );

        foreach ($commands as $command) {
            $this->add($command);
        }
    }

    public function getHelp(): string
    {
        // Take the original help output (with banner)
        $help = Banner::getAsciiArt();

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
            // Keep only ListCommand and HelpCommand
            if ($command instanceof ListCommand) {
                $filteredCommands[] = $command;
            } elseif ($command instanceof HelpCommand) {
                // Keep HelpCommand for functionality, but hide it
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
        // Create the default input definition with standard Symfony Console options
        $inputDefinition = new InputDefinition(array(
            // 1. Command argument (optional - let Symfony handle this properly)
            new InputArgument(
                'command',
                InputArgument::OPTIONAL,
                'The command to execute'
            ),

            // 2. Standard options
            new InputOption(
                'help',
                'h',
                InputOption::VALUE_NONE,
                'Display help for the given command. When no command is given display help for the list command'
            ),
            new InputOption(
                'quiet',
                'q',
                InputOption::VALUE_NONE,
                'Do not output any message'
            ),
            new InputOption(
                'ansi',
                null,
                InputOption::VALUE_NONE,
                'Force ANSI output'
            ),
            new InputOption(
                'no-ansi',
                null,
                InputOption::VALUE_NONE,
                'Disable ANSI output'
            ),
        ));

        return $inputDefinition;
    }

    /**
     * Get the application version from composer.json or fallback to default
     *
     * @return string
     */
    public function getVersion(): string
    {
        $composerFile = dirname(__DIR__, 3) . '/composer.json';

        if (file_exists($composerFile)) {
            $composerData = json_decode(file_get_contents($composerFile), true);
            if (isset($composerData['version'])) {
                return $composerData['version'];
            }
        }

        // Default fallback version
        return 'dev-main';
    }
}

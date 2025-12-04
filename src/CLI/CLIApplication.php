<?php

namespace WPMoo\CLI;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Command\HelpCommand;
use WPMoo\CLI\Commands\InfoCommand;
use WPMoo\CLI\Commands\RenameCommand;
use WPMoo\CLI\Commands\DeployCommand;
use WPMoo\CLI\Commands\UpdateCommand;
use WPMoo\CLI\Commands\BuildThemesCommand;
use WPMoo\CLI\Support\Banner;
use WPMoo\CLI\Support\Filesystem;

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
class CLIApplication extends Application
{
    /**
     * @var Filesystem $file_system The filesystem abstraction layer.
     */
    private Filesystem $file_system;

    /**
     * Constructor to register commands.
     */
    public function __construct()
    {
        $this->file_system = new Filesystem();

        // Get version from composer.json or use a default.
        $version = $this->get_version();
        parent::__construct('WPMoo CLI', $version);

        // Register commands based on project context.
        $this->register_commands_by_context();
    }

    /**
     * Register commands based on the current project context.
     */
    private function register_commands_by_context(): void
    {
        $context = $this->identify_project_context();

        // Always register essential commands.
        $commands = array(
            new InfoCommand(),
        );

        // Add commands based on context.
        switch ($context) {
            case 'wpmoo-cli':
                // Only Info and List commands are active in wpmoo-cli.
                // List command is added by default by Symfony Console.
                break;
            case 'wpmoo-framework':
                // In wpmoo framework, add deploy commands.
                $commands[] = new DeployCommand();
                $commands[] = new BuildThemesCommand();
                break;
            case 'wpmoo-plugin':
            default:
                // For starter or other WPMoo-based plugins, add all commands.
                $commands[] = new DeployCommand();
                $commands[] = new RenameCommand();
                $commands[] = new UpdateCommand();
                $commands[] = new BuildThemesCommand();
                break;
        }

        foreach ($commands as $command) {
            $this->add($command);
        }
    }

    /**
     * Identify the project context based on current directory and composer.json.
     *
     * @return string Context: 'wpmoo-cli', 'wpmoo', or 'wpmoo-plugin'
     */
    private function identify_project_context(): string
    {
        $current_working_directory = getcwd();
        if (! $current_working_directory) {
            return 'wpmoo-plugin'; // Default to plugin behavior.
        }

        $composer_file = $current_working_directory . '/composer.json';

        if ($this->file_system->file_exists($composer_file)) {
            $composer_data = json_decode($this->file_system->get_file_contents($composer_file), true);
            if (isset($composer_data['name'])) {
                $package_name = $composer_data['name'];

                if ($package_name === 'wpmoo/wpmoo-cli') {
                    return 'wpmoo-cli';
                } elseif ($package_name === 'wpmoo/wpmoo') {
                    return 'wpmoo-framework';
                }
            }
        }

        // Check if this looks like a WPMoo-based plugin by looking for WPMoo usage.
        $php_files = $this->file_system->glob($current_working_directory . '/*.php');
        if ($php_files) {
            foreach ($php_files as $file) {
                $content = $this->file_system->get_file_contents($file);
                // Look for WPMoo in plugin header or usage.
                if (
                    preg_match('/(wpmoo|WPMoo)/i', $content) &&
                    ( preg_match('/^[ \t\/*#@]*Plugin Name:/im', $content) ||
                    preg_match('/^[ \t\/*#@]*Theme Name:/im', $content) )
                ) {
                    return 'wpmoo-plugin';
                }
            }
        }


        // Default to plugin behavior if we can't clearly determine.
        return 'wpmoo-plugin';
    }

    public function getHelp(): string
    {
        // Take the original help output (with banner).
        $help = Banner::get_ascii_art();

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
        // Return only essential commands (but without default options).
        $default_commands = parent::getDefaultCommands();

        // Filter to only include commands we want (ListCommand).
        $filtered_commands = array();
        foreach ($default_commands as $command) {
            // Keep only ListCommand and HelpCommand.
            if ($command instanceof ListCommand) {
                $filtered_commands[] = $command;
            } elseif ($command instanceof HelpCommand) {
                // Keep HelpCommand for functionality, but hide it.
                $filtered_commands[] = $command;
            }
        }

        return $filtered_commands;
    }

    /**
     * Get the default input definition for the application.
     *
     * @return InputDefinition An InputDefinition instance
     */
    protected function getDefaultInputDefinition(): InputDefinition
    {
        // Create the default input definition with standard Symfony Console options.
        $input_definition = new InputDefinition(
            array(
                // 1. Command argument (optional - let Symfony handle this properly).
                new InputArgument(
                    'command',
                    InputArgument::OPTIONAL,
                    'The command to execute'
                ),

                // 2. Standard options.
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
            )
        );

        return $input_definition;
    }

    /**
     * Get the application version from composer.json or fallback to default
     *
     * @return string
     */
    public function get_version(): string
    {
        $composer_file = dirname(__DIR__, 3) . '/composer.json';

        if ($this->file_system->file_exists($composer_file)) {
            $composer_data = json_decode($this->file_system->get_file_contents($composer_file), true);
            if (isset($composer_data['version'])) {
                return $composer_data['version'];
            }
        }

        // Default fallback version.
        return 'dev-main';
    }
}

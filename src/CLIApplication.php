<?php

namespace WPMoo\CLI;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Command\HelpCommand;
use WPMoo\CLI\Support\Banner;
use WPMoo\CLI\Support\Filesystem;
use WPMoo\CLI\Support\ProjectIdentifier; // Added
use Symfony\Component\Finder\Finder;

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
     * @var ProjectIdentifier The project identifier.
     */
    private ProjectIdentifier $project_identifier; // Added

    /**
     * Constructor to register commands.
     */
    public function __construct()
    {
        $this->file_system = new Filesystem();
        $this->project_identifier = new ProjectIdentifier($this->file_system); // Added

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
        $context = $this->project_identifier->identify_project_context(); // Modified
        $base_commands_path = __DIR__ . '/Commands'; // Corrected path
        $base_commands_namespace = 'WPMoo\\CLI\\Commands\\';

        // Always register common commands.
        $this->find_and_register_commands_in_directory($base_commands_path . '/Common', $base_commands_namespace . 'Common\\');

        // Register framework commands if in framework or plugin context.
        if (in_array($context, ['wpmoo-framework', 'wpmoo-plugin', 'wpmoo-theme'])) {
            $this->find_and_register_commands_in_directory($base_commands_path . '/Framework', $base_commands_namespace . 'Framework\\');
        }

        // Register plugin-specific commands only in plugin context.
        if (in_array($context, ['wpmoo-plugin', 'wpmoo-theme'])) {
            $this->find_and_register_commands_in_directory($base_commands_path . '/Plugin', $base_commands_namespace . 'Plugin\\');
        }
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
                    'Display help for the given command. '
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

    /**
     * Recursively finds and registers command classes from a given directory.
     *
     * @param string $directory The directory to scan for commands.
     * @param string $namespace The base namespace for the commands in the directory.
     */
    private function find_and_register_commands_in_directory(string $directory, string $namespace): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($directory)->name('*.php');

        foreach ($finder as $file) {
            $class = $namespace . str_replace('.php', '', $file->getFilename());

            if (class_exists($class) && is_subclass_of($class, \Symfony\Component\Console\Command\Command::class)) {
                $this->add(new $class());
            }
        }
    }
}

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
use WPMoo\CLI\Commands\BuildScriptsCommand;
use WPMoo\CLI\Commands\BuildCommand;
use WPMoo\CLI\Support\Banner;
use WPMoo\CLI\Support\Filesystem;
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
        $this->add(new InfoCommand());

        $commands_directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'CLI' . DIRECTORY_SEPARATOR . 'Commands';
        $commands_namespace = 'WPMoo\\CLI\\Commands\\';

        // Add commands based on context.
        switch ($context) {
            case 'wpmoo-cli':
                // No additional commands for wpmoo-cli beyond essential and default ListCommand.
                break;
            case 'wpmoo-framework':
                // Commands specific to the wpmoo framework.
                // Assuming all framework-specific commands are in the main Commands directory
                // or a dedicated subdirectory, we can register them.
                $this->find_and_register_commands_in_directory($commands_directory, $commands_namespace);
                break;
            case 'wpmoo-plugin':
            case 'wpmoo-theme':
                // For WPMoo-based plugins and themes, register all commands.
                $this->find_and_register_commands_in_directory($commands_directory, $commands_namespace);
                break;
            case 'unknown':
            default:
                // For unknown contexts, only essential commands are registered (InfoCommand is already there).
                // No additional commands are registered here to avoid unexpected behavior.
                break;
        }
    }

    /**
     * Identify the project context based on current directory and composer.json.
     *
     * @return string Context: 'wpmoo-cli', 'wpmoo-framework', 'wpmoo-plugin', 'wpmoo-theme' or 'unknown'.
     */
    private function identify_project_context(): string
    {
        $current_working_directory = getcwd();
        if (! $current_working_directory) {
            return 'unknown';
        }

        $composer_file_path = $this->find_composer_json_upwards($current_working_directory);

        if ($composer_file_path) {
            $composer_data = json_decode($this->file_system->get_file_contents($composer_file_path), true);

            if (isset($composer_data['name'])) {
                $package_name = $composer_data['name'];
                if ($package_name === 'wpmoo/wpmoo-cli') {
                    return 'wpmoo-cli';
                } elseif ($package_name === 'wpmoo/wpmoo') {
                    return 'wpmoo-framework';
                }
            }

            // Check composer type for more reliable plugin/theme detection.
            if (isset($composer_data['type'])) {
                if ($composer_data['type'] === 'wordpress-plugin') {
                    return 'wpmoo-plugin';
                } elseif ($composer_data['type'] === 'wordpress-theme') {
                    return 'wpmoo-theme';
                }
            }

            // If a project has wpmoo as a requirement, it's likely a wpmoo-plugin
            if (isset($composer_data['require']['wpmoo/wpmoo'])) {
                return 'wpmoo-plugin';
            }
        }

        // Fallback: Scan PHP files for WPMoo usage and WordPress plugin/theme headers.
        // This is less reliable but can catch projects not using Composer types.
        $php_files = $this->file_system->glob($current_working_directory . '/**/*.php'); // Recursive glob

        if ($php_files) {
            foreach ($php_files as $file) {
                $content = $this->file_system->get_file_contents($file);
                if (
                    preg_match('/(wpmoo|WPMoo)/i', $content) &&
                    ( preg_match('/^[ \t\/*#@]*Plugin Name:/im', $content) ||
                        preg_match('/^[ \t\/*#@]*Theme Name:/im', $content) )
                ) {
                    return 'wpmoo-plugin';
                }
            }
        }

        // Default to unknown if no clear context is found.
        return 'unknown';
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

    /**
     * Search for composer.json in the current directory or its parents.
     *
     * @param string $path Starting path for the search.
     * @return string|null Full path to composer.json if found, otherwise null.
     */
    private function find_composer_json_upwards(string $path): ?string
    {
        $current_path = rtrim($path, DIRECTORY_SEPARATOR);

        while ($current_path !== dirname($current_path)) {
            $composer_file = $current_path . DIRECTORY_SEPARATOR . 'composer.json';
            if ($this->file_system->file_exists($composer_file)) {
                return $composer_file;
            }
            $current_path = dirname($current_path);
        }

        return null;
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
            // Convert file path to class name
            // Example: /path/to/src/CLI/Commands/InfoCommand.php -> WPMoo\CLI\Commands\InfoCommand
            $class = $namespace . str_replace(
                [
                    realpath($directory) . DIRECTORY_SEPARATOR,
                    '.php',
                    '/'
                ],
                [
                    '',
                    '',
                    '\\'
                ],
                $file->getRealPath()
            );

            // Ensure the class exists and extends Symfony's Command class
            if (class_exists($class) && is_subclass_of($class, \Symfony\Component\Console\Command\Command::class)) {
                $this->add(new $class());
            }
        }
    }
}

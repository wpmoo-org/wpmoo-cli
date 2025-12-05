<?php

namespace WPMoo\CLI\Support;

use WPMoo\CLI\Support\Filesystem;

/**
 * Identifies the current project context (CLI, framework, plugin, etc.).
 *
 * @package WPMoo\CLI\Support
 * @since 0.1.0
 */
class ProjectIdentifier
{
    /**
     * @var Filesystem The filesystem abstraction layer.
     */
    private Filesystem $filesystem;

    /**
     * ProjectIdentifier constructor.
     *
     * @param Filesystem|null $filesystem The filesystem instance.
     */
    public function __construct(?Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?? new Filesystem();
    }

    /**
     * Identify the project context based on current directory and composer.json.
     * This method is adapted from CLIApplication.
     *
     * @return string Context: 'wpmoo-cli', 'wpmoo-framework', 'wpmoo-plugin', 'wpmoo-theme' or 'unknown'.
     */
    public function identify_project_context(): string
    {
        $current_working_directory = $this->filesystem->get_cwd();
        if (! $current_working_directory) {
            return 'unknown';
        }

        $composer_file_path = $this->find_composer_json_upwards($current_working_directory);

        if ($composer_file_path) {
            $composer_data = json_decode($this->filesystem->get_file_contents($composer_file_path), true);

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
        $php_files = $this->filesystem->glob($current_working_directory . '/**/*.php'); // Recursive glob

        if ($php_files) {
            foreach ($php_files as $file) {
                $content = $this->filesystem->get_file_contents($file);
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

    /**
     * Search for composer.json in the current directory or its parents.
     * This method is adapted from CLIApplication.
     *
     * @param string $path Starting path for the search.
     * @return string|null Full path to composer.json if found, otherwise null.
     */
    private function find_composer_json_upwards(string $path): ?string
    {
        $current_path = rtrim($path, DIRECTORY_SEPARATOR);

        while ($current_path !== dirname($current_path)) {
            $composer_file = $current_path . DIRECTORY_SEPARATOR . 'composer.json';
            if ($this->filesystem->file_exists($composer_file)) {
                return $composer_file;
            }
            $current_path = dirname($current_path);
        }

        return null;
    }

    /**
     * Identify the project type and location of version files.
     * This method is adapted from BaseCommand.
     *
     * @return array<string, mixed> Project information.
     */
    public function identify_project(): array
    {
        $cwd = $this->filesystem->get_cwd();

        // Check for wpmoo framework project.
        $wpmoo_root_path = $cwd . '/wpmoo.php'; // Adjusted to check for wpmoo.php at root for framework detection
        $is_wpmoo_framework = $this->filesystem->file_exists($wpmoo_root_path) &&
            strpos($this->filesystem->get_file_contents($wpmoo_root_path), 'Plugin Name: WPMoo Framework') !== false;

        if ($is_wpmoo_framework) {
            return [
                'found' => true,
                'type' => 'wpmoo-framework',
                'main_file' => $wpmoo_root_path,
                'readme_file' => $cwd . '/readme.txt', // Check if readme.txt exists.
            ];
        }

        // Check for wpmoo-starter or other wpmoo-based plugin.
        $php_files = $this->filesystem->glob($cwd . '/*.php');
        if ($php_files) {
            foreach ($php_files as $file) {
                $content = $this->filesystem->get_file_contents($file);
                // Look for WPMoo in plugin header.
                if (
                    preg_match('/(wpmoo|WPMoo)/i', $content) &&
                    ( preg_match('/^[ \t\/*#@]*Plugin Name:/im', $content) ||
                    preg_match('/^[ \t\/*#@]*Theme Name:/im', $content) )
                ) {
                    $readme_path = $cwd . '/readme.txt';
                    return [
                        'found' => true,
                        'type' => 'wpmoo-plugin',
                        'main_file' => $file,
                        'readme_file' => $this->filesystem->file_exists($readme_path) ? $readme_path : null,
                    ];
                }
            }
        }

        return [
            'found' => false,
            'type' => 'unknown',
            'main_file' => null,
            'readme_file' => null,
        ];
    }
}

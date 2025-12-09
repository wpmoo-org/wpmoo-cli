<?php

namespace WPMoo\CLI\Support;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
use WPMoo\CLI\Support\Filesystem;

// Added

/**
 * Manages project configuration from multiple config files.
 *
 * @package WPMoo\CLI\Support
 * @since 0.1.0
 */
class ConfigManager
{
    /**
     * @var array<string, mixed> The loaded configuration.
     */
    private array $config = [];

    /**
     * @var string|null The path to the project root where the config was found.
     */
    private ?string $project_root = null;

    /**
     * @var Filesystem The filesystem abstraction layer.
     */
    private Filesystem $filesystem; // Added

    /**
     * ConfigManager constructor.
     *
     * @param string|null $start_path The path to start searching from. Defaults to getcwd().
     * @param Filesystem|null $filesystem The filesystem instance.
     */
    public function __construct(?string $start_path = null, ?Filesystem $filesystem = null) // Modified
    {
        $this->filesystem = $filesystem ?? new Filesystem(); // Added
        $this->load_config($start_path ?? $this->filesystem->get_cwd()); // Modified
    }

    /**
     * Finds and loads configuration from multiple config files.
     *
     * @param string $start_path The directory to start searching from.
     * @return void
     */
    private function load_config(string $start_path): void
    {
        // Find project root first
        $this->project_root = $this->find_project_root($start_path);

        if (!$this->project_root) {
            $this->project_root = $start_path;
            return;
        }

        // 1. Try loading legacy wpmoo-config.yml
        $legacy_config_file = $this->project_root . '/wpmoo-config.yml';
        if ($this->filesystem->file_exists($legacy_config_file)) {
            $this->config = $this->load_yaml_file($legacy_config_file);
        }

        // 2. Load wpmoo-config directory files if they exist (merges with/overrides legacy)
        $wpmoo_config_dir = $this->project_root . '/wpmoo-config';
        if ($this->filesystem->file_exists($wpmoo_config_dir) && is_dir($wpmoo_config_dir)) {
            $this->load_config_directory($wpmoo_config_dir);
        }

        // 3. Also check for 'config' directory for potential future compatibility
        $config_dir = $this->project_root . '/config';
        if ($this->filesystem->file_exists($config_dir) && is_dir($config_dir)) {
            $this->load_config_directory($config_dir);
        }
    }

    /**
     * Finds the project root by looking for configuration markers.
     *
     * @param string $start_path
     * @return string|null The path to the project root or null if not found.
     */
    private function find_project_root(string $start_path): ?string
    {
        $current_dir = $start_path;
        while ($current_dir && $current_dir !== dirname($current_dir)) {
            // Check for legacy single file
            if ($this->filesystem->file_exists($current_dir . '/wpmoo-config.yml')) {
                return $current_dir;
            }

            // Check for new config directory structure
            if ($this->filesystem->file_exists($current_dir . '/wpmoo-config/wpmoo-settings.yml')) {
                return $current_dir;
            }

            $current_dir = dirname($current_dir);
        }

        // Check root one last time
        if ($this->filesystem->file_exists($current_dir . '/wpmoo-config.yml')) {
            return $current_dir;
        }
        if ($this->filesystem->file_exists($current_dir . '/wpmoo-config/wpmoo-settings.yml')) {
            return $current_dir;
        }

        return null;
    }

    /**
     * Loads configuration from the config directory.
     *
     * @param string $config_dir The config directory path.
     * @return void
     */
    private function load_config_directory(string $config_dir): void
    {
        $config_files = [
            'wpmoo-settings.yml', // Main settings file
            'deploy.yml',         // Deployment settings
        ];

        foreach ($config_files as $config_file) {
            $full_path = $config_dir . '/' . $config_file;
            if ($this->filesystem->file_exists($full_path)) {
                $file_config = $this->load_yaml_file($full_path);
                $this->config = $this->array_merge_recursive_distinct($this->config, $file_config);
            }
        }
    }

    /**
     * Loads a YAML file and returns its content as an array.
     *
     * @param string $file_path The path to the YAML file.
     * @return array The YAML content as an array.
     */
    private function load_yaml_file(string $file_path): array
    {
        try {
            $content = $this->filesystem->get_file_contents($file_path);
            return Yaml::parse($content) ?? [];
        } catch (ParseException $e) {
            // Handle error if YAML is invalid.
            return [];
        }
    }

    /**
     * Recursively merge arrays, with values from the right overriding values from the left.
     * This is similar to array_merge_recursive but preserves string values instead of merging them into arrays.
     *
     * @param array $array1 Left array to merge.
     * @param array $array2 Right array to merge.
     * @return array Merged array.
     */
    private function array_merge_recursive_distinct(array $array1, array $array2): array
    {
        $merged = $array1;

        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->array_merge_recursive_distinct($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * Get a configuration value using dot notation.
     *
     * @param string $key The key to retrieve (e.g., 'project.name').
     * @param mixed|null $default The default value to return if the key is not found.
     * @return mixed The configuration value or the default.
     */
    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Get the entire configuration array.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * Get the project root directory where the config file was found.
     *
     * @return string|null
     */
    public function get_project_root(): ?string
    {
        return $this->project_root;
    }

    /**
     * Checks if the configuration was successfully loaded.
     *
     * @return bool
     */
    public function isLoaded(): bool
    {
        return !empty($this->config);
    }

    /**
     * Saves the project configuration to wpmoo-config.yml.
     *
     * @param string $dir The directory to save the config in.
     * @param array<string, mixed> $data The configuration data to save.
     * @return bool True on success, false on failure.
     */
    public function save_config(string $dir, array $data): bool // New method
    {
        $config_file = $dir . '/wpmoo-config.yml';
        return $this->filesystem->put_file_contents($config_file, Yaml::dump($data, 2)); // Modified
    }
}

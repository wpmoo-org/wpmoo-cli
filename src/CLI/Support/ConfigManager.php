<?php

namespace WPMoo\CLI\Support;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Manages project configuration from a wpmoo-config.yml file.
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
     * ConfigManager constructor.
     *
     * @param string|null $start_path The path to start searching from. Defaults to getcwd().
     */
    public function __construct(?string $start_path = null)
    {
        $this->load_config($start_path ?? getcwd());
    }

    /**
     * Finds and loads the wpmoo-config.yml file.
     *
     * @param string $start_path The directory to start searching from.
     * @return void
     */
    private function load_config(string $start_path): void
    {
        $config_file = $this->find_config_file($start_path);

        if ($config_file) {
            $this->project_root = dirname($config_file);
            try {
                $this->config = Yaml::parseFile($config_file) ?? [];
            } catch (ParseException $e) {
                // Handle error if YAML is invalid.
                $this->config = [];
            }
        }
    }

    /**
     * Finds the wpmoo-config.yml file by traversing up from the start path.
     *
     * @param string $start_path
     * @return string|null The path to the config file or null if not found.
     */
    private function find_config_file(string $start_path): ?string
    {
        $current_dir = $start_path;
        while ($current_dir && $current_dir !== dirname($current_dir)) {
            $config_file = $current_dir . '/wpmoo-config.yml';
            if (file_exists($config_file)) {
                return $config_file;
            }
            $current_dir = dirname($current_dir);
        }

        // Check root one last time
        $config_file = $current_dir . '/wpmoo-config.yml';
        if (file_exists($config_file)) {
            return $config_file;
        }

        return null;
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
}

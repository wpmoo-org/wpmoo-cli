<?php

namespace WPMoo\CLI\Support;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * POT file generator service.
 *
 * Wraps WP-CLI i18n make-pot command to generate translation templates.
 *
 * @package WPMoo\CLI\Support
 * @since 0.1.0
 */
class PotGenerator
{
    /**
     * @var string The base working directory.
     */
    private $cwd;

    /**
     * Constructor.
     *
     * @param string|null $cwd Optional working directory. Defaults to current working directory.
     */
    public function __construct(?string $cwd = null)
    {
        $this->cwd = $cwd ?: getcwd();
    }

    /**
     * Generate POT files based on project type.
     *
     * @param array $project The project configuration array.
     * @param callable|null $outputCallback Optional callback for outputting progress.
     * @return bool True on success.
     * @throws ProcessFailedException If the WP-CLI command fails.
     */
    public function generate(array $project, ?callable $outputCallback = null): bool
    {
        $wp_bin = $this->cwd . '/vendor/bin/wp';

        if (!file_exists($wp_bin)) {
            // Fallback to global wp if local one doesn't exist
            $wp_bin = 'wp';
        }

        if ($project['type'] === 'wpmoo-framework') {
            // 1. Generate Core POT
            $this->run_make_pot(
                $wp_bin,
                $this->cwd . '/src',
                $this->cwd . '/languages/wpmoo.pot',
                'wpmoo',
                'vendor,node_modules',
                $outputCallback
            );

            // 2. Generate Samples POT
            $this->run_make_pot(
                $wp_bin,
                $this->cwd . '/samples',
                $this->cwd . '/languages/wpmoo-samples.pot',
                'wpmoo-samples',
                '',
                $outputCallback
            );
        } else {
            // Standard Plugin
            $domain = $project['name'] ?? 'plugin';
            $pot_file = $this->cwd . "/languages/{$domain}.pot";

            if (!is_dir(dirname($pot_file))) {
                mkdir(dirname($pot_file), 0755, true);
            }

            $this->run_make_pot(
                $wp_bin,
                $this->cwd,
                $pot_file,
                $domain,
                'vendor,node_modules,dist,tests',
                $outputCallback
            );
        }

        return true;
    }

    /**
     * Run the wp i18n make-pot command.
     */
    private function run_make_pot(string $wp_bin, string $source, string $dest, string $domain, string $exclude, ?callable $callback): void
    {
        if ($callback) {
            $callback('info', "> Generating {$domain}.pot from " . basename($source) . "...");
        }

        $command = [
            $wp_bin, 'i18n', 'make-pot',
            $source,
            $dest,
            "--domain={$domain}"
        ];

        if (!empty($exclude)) {
            $command[] = "--exclude={$exclude}";
        }

        $process = new Process($command);
        $process->mustRun(); // Throws ProcessFailedException on error
    }
}

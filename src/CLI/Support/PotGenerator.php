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
    private $cwd;
    private $projectRoot;

    public function __construct(string $projectRoot, ?string $cwd = null)
    {
        $this->projectRoot = $projectRoot;
        $this->cwd = $cwd ?: getcwd();
    }

    public function generate(array $project, ?callable $outputCallback = null): bool
    {
        // Look for wp binary in the project root vendor/bin
        $wp_bin = $this->projectRoot . '/vendor/bin/wp';

        if (!file_exists($wp_bin)) {
            // Fallback to global wp if local one doesn't exist
            $wp_bin = 'wp';
        }

        if ($project['type'] === 'wpmoo-framework') {
            $this->run_make_pot(
                $wp_bin,
                $this->projectRoot . '/src',
                $this->projectRoot . '/languages/wpmoo.pot',
                'wpmoo',
                'vendor,node_modules',
                $outputCallback
            );

            $this->run_make_pot(
                $wp_bin,
                $this->projectRoot . '/samples',
                $this->projectRoot . '/languages/wpmoo-samples.pot',
                'wpmoo-samples',
                '',
                $outputCallback
            );
        } else {
            $domain = $project['name'] ?? 'plugin';
            $pot_file = $this->projectRoot . "/languages/{$domain}.pot";

            if (!is_dir(dirname($pot_file))) {
                mkdir(dirname($pot_file), 0755, true);
            }

            $this->run_make_pot(
                $wp_bin,
                $this->projectRoot,
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

<?php

namespace WPMoo\CLI\Support;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Yaml\Yaml;

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

        // Set default headers to WPMoo's information.
        $headers = [
            'Language-Team' => 'WPMoo Team <hello@wpmoo.org>',
            'Last-Translator' => 'WPMoo <hello@wpmoo.org>',
            'Report-Msgid-Bugs-To' => 'https://github.com/wpmoo/wpmoo/issues',
        ];

        // Read and merge custom headers from wpmoo-config.yml if it exists.
        $config_file = $this->projectRoot . '/wpmoo-config.yml';
        if (file_exists($config_file)) {
            $config = Yaml::parseFile($config_file);
            if (isset($config['localization'])) {
                // Only override if the keys are set and not empty in the config.
                if (!empty($config['localization']['team'])) {
                    $headers['Language-Team'] = $config['localization']['team'];
                }
                if (!empty($config['localization']['translator'])) {
                    $headers['Last-Translator'] = $config['localization']['translator'];
                }
                if (!empty($config['localization']['bug_reports'])) {
                    $headers['Report-Msgid-Bugs-To'] = $config['localization']['bug_reports'];
                }
            }
        }

        if ($project['type'] === 'wpmoo-framework') {
            $this->run_make_pot(
                $wp_bin,
                $this->projectRoot . '/src',
                $this->projectRoot . '/languages/wpmoo.pot',
                'wpmoo',
                'vendor,node_modules',
                $headers,
                $outputCallback
            );

            $this->run_make_pot(
                $wp_bin,
                $this->projectRoot . '/samples',
                $this->projectRoot . '/languages/wpmoo-samples.pot',
                'wpmoo-samples',
                '',
                $headers,
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
                $headers,
                $outputCallback
            );
        }

        return true;
    }

    /**
     * Run the wp i18n make-pot command.
     */
    private function run_make_pot(string $wp_bin, string $source, string $dest, string $domain, string $exclude, array $headers, ?callable $callback): void
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

        if (!empty($headers)) {
            $command[] = '--headers=' . json_encode($headers);
        }

        $process = new Process($command);
        $process->mustRun(); // Throws ProcessFailedException on error
    }
}

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
    /**
     * @var ConfigManager The project configuration manager.
     */
    private ConfigManager $config_manager;

    /**
     * PotGenerator constructor.
     *
     * @param ConfigManager $config_manager The configuration manager instance.
     */
    public function __construct(ConfigManager $config_manager)
    {
        $this->config_manager = $config_manager;
    }

    public function generate(array $project, ?callable $outputCallback = null): bool
    {
        $project_root = $this->config_manager->get_project_root();
        if (!$project_root) {
            if ($outputCallback) {
                $outputCallback('error', 'Could not find project root.');
            }
            return false;
        }

        // Look for wp binary in the project root vendor/bin
        $wp_bin = $project_root . '/vendor/bin/wp';

        if (!file_exists($wp_bin)) {
            // Fallback to global wp if local one doesn't exist
            $wp_bin = 'wp';
        }

        // Set default headers to WPMoo's information.
        $headers = [
            'Language-Team' => $this->config_manager->get('localization.team', 'WPMoo Team <hello@wpmoo.org>'),
            'Last-Translator' => $this->config_manager->get('localization.translator', 'WPMoo <hello@wpmoo.org>'),
            'Report-Msgid-Bugs-To' => $this->config_manager->get('localization.bug_reports', 'https://github.com/wpmoo/wpmoo/issues'),
        ];

        if ($project['type'] === 'wpmoo-framework') {
            $this->run_make_pot(
                $wp_bin,
                $project_root, // Scan the entire project root
                $project_root . '/languages/wpmoo.pot',
                'wpmoo', // Use the single text domain
                'vendor,node_modules,dist,tests', // Exclude more directories
                $headers,
                $outputCallback
            );
        } else {
            $domain = $this->config_manager->get('project.text_domain', 'plugin');
            $pot_file = $project_root . "/languages/{$domain}.pot";

            if (!is_dir(dirname($pot_file))) {
                mkdir(dirname($pot_file), 0755, true);
            }

            $this->run_make_pot(
                $wp_bin,
                $project_root,
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

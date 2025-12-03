<?php

namespace WPMoo\CLI\Support;

use Gettext\Extractors\PhpCode;
use Gettext\Translations;
use Gettext\Generators\Po;

/**
 * POT file generator for WPMoo CLI.
 *
 * @package WPMoo\CLI\Support
 * @since 0.1.0
 * @link https://wpmoo.org   WPMoo – WordPress Micro Object-Oriented Framework.
 * @link https://github.com/wpmoo/wpmoo-cli   GitHub Repository.
 * @license https://spdx.org/licenses/GPL-2.0-or-later.html   GPL-2.0-or-later
 */
class PotGenerator
{
    /**
     * Generate a .pot file for the WPMoo framework.
     *
     * @param string $source_path The path to the source code to scan.
     * @param string $output_file The full path for the output .pot file.
     * @param array  $exclude    An array of directory names to exclude from the scan.
     * @return bool True on success, false on failure.
     */
    public function generate_pot_file(string $source_path, string $output_file, string $domain, array $exclude = []): bool
    {
        $translations = new Translations();
        $translations->setDomain($domain);

        // Define options for scanning.
        $options = [
            'excluded_directories' => $exclude,
            'extract_comments' => [ 'translators:' ],
            'functions' => [
                '__' => 'gettext',
                '_e' => 'gettext',
                '_x' => 'pgettext',
                'esc_html__' => 'gettext',
                'esc_html_e' => 'gettext',
                'esc_html_x' => 'pgettext',
                'esc_attr__' => 'gettext',
                'esc_attr_e' => 'gettext',
                'esc_attr_x' => 'pgettext',
                '_ex' => 'pgettext',
                '_n' => 'ngettext',
                '_nx' => 'npgettext',
                '_n_noop' => 'ngettext',
                '_nx_noop' => 'npgettext',
            ],
        ];

        // Extract translations from the source path - scan directory for PHP files.
        $this->extract_from_directory($source_path, $translations, $options);

        // Set headers.
        $translations->setHeader('Project-Id-Version', 'WPMoo Framework');
        $translations->setHeader('POT-Creation-Date', date('Y-m-d H:i:sO'));
        $translations->setHeader('Language', 'en_US');
        $translations->setHeader('Content-Type', 'text/plain; charset=UTF-8');

        // Generate the .pot file content.
        $pot_content = Po::toString($translations);

        // Ensure the output directory exists.
        $output_dir = dirname($output_file);
        if (! is_dir($output_dir)) {
            mkdir($output_dir, 0755, true);
        }

        // Save the file.
        return file_put_contents($output_file, $pot_content) !== false;
    }

    /**
     * Extract translations from all PHP files in a directory.
     *
     * @param string $directory The directory to scan for PHP files.
     * @param \Gettext\Translations $translations The translations collection to add to.
     * @param array $options Options for extraction.
     * @return void
     */
    private function extract_from_directory(string $directory, Translations $translations, array $options = []): void
    {
        $excluded_dirs = $options['excluded_directories'] ?? [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                // Check if file is in an excluded directory.
                $file_path = $file->getPathname();
                $should_skip = false;

                foreach ($excluded_dirs as $excluded_dir) {
                    if (strpos($file_path, $excluded_dir) !== false) {
                        $should_skip = true;
                        break;
                    }
                }

                if (! $should_skip) {
                    $translations->addFromPhpCodeFile($file_path, $options);
                }
            }
        }
    }
}

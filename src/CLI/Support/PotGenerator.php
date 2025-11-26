<?php

/**
 * POT file generator for WPMoo CLI.
 *
 * @package WPMoo\CLI\Support
 * @since 0.1.0
 */

namespace WPMoo\CLI\Support;

use Gettext\Extractors\PhpCode;
use Gettext\Translations;
use Gettext\Generator\PoGenerator;

class PotGenerator
{
    /**
     * Generate a .pot file for the WPMoo framework.
     *
     * @param string $sourcePath The path to the source code to scan.
     * @param string $outputFile The full path for the output .pot file.
     * @param array  $exclude    An array of directory names to exclude from the scan.
     * @return bool True on success, false on failure.
     */
    public function generate(string $sourcePath, string $outputFile, array $exclude = []): bool
    {
        $translations = new Translations();
        $translations->setDomain('wpmoo');

        // Define options for scanning
        $options = [
            'excluded_directories' => $exclude,
            'extract_comments' => ['translators:'],
        ];

        // Extract translations from the source path
        PhpCode::fromDirectory($sourcePath, $translations, $options);

        // Set headers
        $translations->setHeader('Project-Id-Version', 'WPMoo Framework');
        $translations->setHeader('POT-Creation-Date', date('Y-m-d H:i:sO'));
        $translations->setHeader('Language', 'en_US');
        $translations->setHeader('Content-Type', 'text/plain; charset=UTF-8');

        // Generate the .pot file content
        $generator = new PoGenerator();
        $potContent = $generator->generateString($translations);

        // Ensure the output directory exists
        $outputDir = dirname($outputFile);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Save the file
        return file_put_contents($outputFile, $potContent) !== false;
    }
}

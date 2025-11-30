<?php

/**
 * Base command class for the WPMoo CLI.
 *
 * Provides common functionality for all CLI commands.
 *
 * @package WPMoo\CLI\Support
 * @since 0.1.0
 * @link  https://wpmoo.org WPMoo – WordPress Micro Object-Oriented Framework.
 * @link  https://github.com/wpmoo/wpmoo GitHub Repository.
 * @license https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 */

namespace WPMoo\CLI\Support;

class Banner
{
    /**
     * WPMoo ASCII Banner with Gradient #e468a6
     */
    public static function getAsciiArt(): string
    {
        $asciiRaw = <<<EOT

        ░██       ░██ ░█████████  ░███     ░███                       
        ░██       ░██ ░██     ░██ ░████   ░████                       
        ░██  ░██  ░██ ░██     ░██ ░██░██ ░██░██  ░███████   ░███████  
        ░██ ░████ ░██ ░█████████  ░██ ░████ ░██ ░██    ░██ ░██    ░██ 
        ░██░██ ░██░██ ░██         ░██  ░██  ░██ ░██    ░██ ░██    ░██ 
        ░████   ░████ ░██         ░██       ░██ ░██    ░██ ░██    ░██ 
        ░███     ░███ ░██         ░██       ░██  ░███████   ░███████  
        
        EOT;

        return self::applyGradient($asciiRaw, [89, 149, 255], [228, 110, 150]);
    }

    /**
     * Applies a horizontal gradient to the given text.
     */
    private static function applyGradient(string $text, array $startColor, array $endColor): string
    {
        $lines = explode("\n", $text);
        $output = [];

        foreach ($lines as $line) {
            $coloredLine = '';
            $length = mb_strlen($line);

            // Skip empty lines.
            if ($length === 0) {
                $output[] = '';
                continue;
            }

            // For each character in the line.
            for ($i = 0; $i < $length; $i++) {
                $char = mb_substr($line, $i, 1);

                // Only color non-space characters.
                if (trim($char) === '') {
                    $coloredLine .= $char;
                    continue;
                }

                // Calculate the interpolation factor.
                $percent = $i / ($length - 1);

                // Interpolate RGB values.
                $r = (int) ($startColor[0] + ($endColor[0] - $startColor[0]) * $percent);
                $g = (int) ($startColor[1] + ($endColor[1] - $startColor[1]) * $percent);
                $b = (int) ($startColor[2] + ($endColor[2] - $startColor[2]) * $percent);

                // Hex color format.
                $hex = sprintf("#%02x%02x%02x", $r, $g, $b);

                // Symfony Console color tag.
                $coloredLine .= "<fg=$hex>$char</>";
            }
            $output[] = $coloredLine;
        }

        return implode("\n", $output);
    }
}

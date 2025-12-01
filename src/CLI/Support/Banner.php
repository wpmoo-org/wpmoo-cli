<?php

namespace WPMoo\CLI\Support;

/**
 * WPMoo ASCII Banner with Gradient for CLI output.
 *
 * @package WPMoo\CLI\Support
 * @since 0.1.0
 * @link  https://wpmoo.org WPMoo – WordPress Micro Object-Oriented Framework.
 * @link  https://github.com/wpmoo/wpmoo GitHub Repository.
 * @license https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 */
class Banner
{
    /**
     * WPMoo ASCII Banner with Gradient #e468a6
     *
     * @return string
     */
    public static function get_ascii_art(): string
    {
        $ascii_raw = <<<EOT

        ░██       ░██ ░█████████  ░███     ░███                       
        ░██       ░██ ░██     ░██ ░████   ░████                       
        ░██  ░██  ░██ ░██     ░██ ░██░██ ░██░██  ░███████   ░███████  
        ░██ ░████ ░██ ░█████████  ░██ ░████ ░██ ░██    ░██ ░██    ░██ 
        ░██░██ ░██░██ ░██         ░██  ░██  ░██ ░██    ░██ ░██    ░██ 
        ░████   ░████ ░██         ░██       ░██ ░██    ░██ ░██    ░██ 
        ░███     ░███ ░██         ░██       ░██  ░███████   ░███████  
        
        EOT;

        return self::apply_gradient($ascii_raw, [89, 149, 255], [228, 110, 150]);
    }

    /**
     * Applies a horizontal gradient to the given text.
     *
     * @param string $text The text to apply the gradient to.
     * @param array $start_color The RGB values for the starting color.
     * @param array $end_color The RGB values for the ending color.
     * @return string The text with the gradient applied.
     */
    private static function apply_gradient(string $text, array $start_color, array $end_color): string
    {
        $lines = explode("\n", $text);
        $output_lines = [];

        foreach ($lines as $line) {
            $colored_line = '';
            $line_length = mb_strlen($line);

            // Skip empty lines.
            if ($line_length === 0) {
                $output_lines[] = '';
                continue;
            }

            // For each character in the line.
            for ($i = 0; $i < $line_length; $i++) {
                $char = mb_substr($line, $i, 1);

                // Only color non-space characters.
                if (trim($char) === '') {
                    $colored_line .= $char;
                    continue;
                }

                // Calculate the interpolation factor.
                $percent = $i / ($line_length - 1);

                // Interpolate RGB values.
                $r = (int) ($start_color[0] + ($end_color[0] - $start_color[0]) * $percent);
                $g = (int) ($start_color[1] + ($end_color[1] - $start_color[1]) * $percent);
                $b = (int) ($start_color[2] + ($end_color[2] - $start_color[2]) * $percent);

                // Hex color format.
                $hex_color = sprintf("#%02x%02x%02x", $r, $g, $b);

                // Symfony Console color tag.
                $colored_line .= "<fg=$hex_color>$char</>";
            }
            $output_lines[] = $colored_line;
        }

        return implode("\n", $output_lines);
    }
}

<?php

namespace WPMoo\CLI\Support;

/**
 * POT file generator for WPMoo CLI (Custom Regex Implementation).
 *
 * @package WPMoo\CLI\Support
 * @since 0.1.0
 * @link https://wpmoo.org
 * @license GPL-2.0-or-later
 */
class PotGenerator
{
    /**
     * Generate a .pot file by scanning PHP files for WordPress translation functions.
     *
     * @param string $source_path The path to the source code to scan.
     * @param string $output_file The full path for the output .pot file.
     * @param string $domain      The text domain to filter for.
     * @param array  $exclude     An array of directory names to exclude from the scan.
     * @return bool True on success, false on failure.
     */
    public function generate_pot_file(string $source_path, string $output_file, string $domain, array $exclude = []): bool
    {
        $strings = [];
        
        // Recursively scan directory
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source_path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                
                // Check excludes
                $file_path = $file->getPathname();
                foreach ($exclude as $excluded_dir) {
                    if (strpos($file_path, $excluded_dir) !== false) {
                        continue 2;
                    }
                }

                // Read file content
                $content = file_get_contents($file_path);
                
                // Extract strings using regex
                $this->extract_strings($content, $domain, $file_path, $strings);
            }
        }

        // Build POT content
        $pot_content = $this->build_pot_content($strings, $domain);

        // Ensure output dir exists
        $output_dir = dirname($output_file);
        if (! is_dir($output_dir)) {
            mkdir($output_dir, 0755, true);
        }

        return file_put_contents($output_file, $pot_content) !== false;
    }

    /**
     * Extract translation strings from content using Regex.
     * 
     * Handles:
     * - __("text", "domain")
     * - _e("text", "domain")
     * - esc_html__("text", "domain")
     * - _x("text", "context", "domain")
     * - _n("single", "plural", $n, "domain")
     */
    private function extract_strings(string $content, string $domain, string $file_path, array &$strings): void
    {
        // Regex patterns for WP translation functions
        // Note: We use single quotes mostly in PHP, but support double quotes too.
        // Capture groups: 1=quote, 2=string, 3=context/plural (optional), 4=domain
        
        // Standard: __, _e, esc_attr__, esc_html__, etc.
        // Pattern: func ( 'string' , 'domain' )
        $pattern_standard = '/\b(?:__|_e|esc_attr__|esc_attr_e|esc_html__|esc_html_e)\s*\(\s*([\'"])(.*?)(?<!\\)\1\s*,\s*([\'"])(.*?)(?<!\\)\3\s*\)/s';
        
        if (preg_match_all($pattern_standard, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $found_domain = $match[4];
                if ($found_domain === $domain) {
                    $msgid = $match[2];
                    $this->add_string($strings, $msgid, $file_path);
                }
            }
        }

        // Context: _x, esc_attr_x, esc_html_x, _ex
        // Pattern: func ( 'string' , 'context' , 'domain' )
        $pattern_context = '/\b(?:_x|esc_attr_x|esc_html_x|_ex)\s*\(\s*([\'"])(.*?)(?<!\\)\1\s*,\s*([\'"])(.*?)(?<!\\)\3\s*,\s*([\'"])(.*?)(?<!\\)\5\s*\)/s';

        if (preg_match_all($pattern_context, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $found_domain = $match[6];
                if ($found_domain === $domain) {
                    $msgid = $match[2];
                    $context = $match[4];
                    $this->add_string($strings, $msgid, $file_path, $context);
                }
            }
        }

        // Plural: _n, _n_noop
        // Pattern: func ( 'single' , 'plural' , number , 'domain' )
        // This is harder because number can be a variable. We look for the last string argument.
        // Simplified regex for: _n ( 'single' , 'plural' , ... , 'domain' )
        $pattern_plural = '/\b(?:_n|_n_noop)\s*\(\s*([\'"])(.*?)(?<!\\)\1\s*,\s*([\'"])(.*?)(?<!\\)\3\s*,.*,\s*([\'"])(.*?)(?<!\\)\5\s*\)/s';
        
        if (preg_match_all($pattern_plural, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $found_domain = $match[6];
                if ($found_domain === $domain) {
                    $msgid = $match[2];
                    $plural = $match[4];
                    $this->add_string($strings, $msgid, $file_path, null, $plural);
                }
            }
        }
    }

    private function add_string(array &$strings, string $msgid, string $file, ?string $context = null, ?string $plural = null): void
    {
        // Create a unique key based on msgid and context
        $key = $context ? "{$msgid}\004{$context}" : $msgid;

        if (! isset($strings[$key])) {
            $strings[$key] = [
                'msgid' => $msgid,
                'context' => $context,
                'plural' => $plural,
                'files' => [],
            ];
        }

        // Store relative file path
        $relative_path = str_replace(getcwd() . '/', '', $file);
        if (! in_array($relative_path, $strings[$key]['files'])) {
            $strings[$key]['files'][] = $relative_path;
        }
    }

    private function build_pot_content(array $strings, string $domain): string
    {
        $output = "";
        $output .= "msgid \"\"\n";
        $output .= "msgstr \"\"\n";
        $output .= "\"Project-Id-Version: WPMoo Framework\\n\"\n";
        $output .= "\"Report-Msgid-Bugs-To: \\n\"\n";
        $output .= "\"POT-Creation-Date: " . date('Y-m-d H:i:sO') . "\n"
        $output .= "\"Language: en_US\n"
        $output .= "\"MIME-Version: 1.0\n"
        $output .= "\"Content-Type: text/plain; charset=UTF-8\n"
        $output .= "\"Content-Transfer-Encoding: 8bit\n"
        $output .= "\"X-Generator: WPMoo CLI\n"
        $output .= "\"X-Domain: {$domain}\n"
        $output .= "\n";

        foreach ($strings as $str) {
            // Comments (Files)
            foreach ($str['files'] as $file) {
                $output .= "#: $file\n";
            }

            // Context
            if ($str['context']) {
                $output .= "msgctxt \"{$this->escape($str['context'])}\"\n";
            }

            // Msgid
            $output .= "msgid \"{$this->escape($str['msgid'])}\"\n";

            // Plural
            if ($str['plural']) {
                $output .= "msgid_plural \"{$this->escape($str['plural'])}\"\n";
                $output .= "msgstr[0] \"\"\n";
                $output .= "msgstr[1] \"\"\n";
            } else {
                $output .= "msgstr \"\"\n";
            }

            $output .= "\n";
        }

        return $output;
    }

    private function escape(string $str): string
    {
        return str_replace('"', '\"', $str);
    }
}
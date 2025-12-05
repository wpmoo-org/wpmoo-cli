<?php

namespace WPMoo\CLI\Support;

/**
 * Filesystem Abstraction Layer.
 *
 * Provides a unified interface for file system operations,
 * attempting to use WP_Filesystem if available and falling back to
 * standard PHP functions otherwise.
 *
 * @package WPMoo\CLI\Support
 * @since 0.1.0
 */
class Filesystem
{
    /**
     * @var \WP_Filesystem_Base|null $wp_filesystem The WP_Filesystem instance if available.
     */
    private $wp_filesystem = null;

    /**
     * Filesystem constructor.
     * Attempts to initialize WP_Filesystem.
     */
    public function __construct()
    {
        $this->initialize_wp_filesystem();
    }

    /**
     * Attempts to initialize WP_Filesystem.
     *
     * @return bool True if WP_Filesystem was successfully initialized, false otherwise.
     */
    private function initialize_wp_filesystem(): bool
    {
        // Define ABSPATH if not already defined (e.g., in a non-WP context or early in CLI).
        // This is a heuristic and might need to be refined based on CLI's actual execution context.
        if (! defined('ABSPATH')) {
            // Attempt to find WordPress root by going up the directory tree.
            $path = getcwd();
            while ($path && $path !== '/' && ! file_exists($path . '/wp-load.php')) {
                $path = dirname($path);
            }
            if (file_exists($path . '/wp-load.php')) {
                define('ABSPATH', $path . '/');
            } else {
                // If wp-load.php not found, we cannot initialize WP_Filesystem.
                return false;
            }
        }

        // Only proceed if ABSPATH is defined and wp-load.php exists.
        if (defined('ABSPATH') && file_exists(ABSPATH . 'wp-load.php')) {
            require_once ABSPATH . 'wp-load.php';

            // Ensure the file.php is loaded, which contains WP_Filesystem().
            if (! function_exists('WP_Filesystem')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            global $wp_filesystem;

            // Initialize the WP_Filesystem.
            // Using `false` for connection details and `true` for relaxed ownership for CLI.
            if (WP_Filesystem(false, ABSPATH, true)) {
                $this->wp_filesystem = $wp_filesystem;
                return true;
            }
        }
        return false;
    }

        /**

         * Checks if a file or directory exists.

         *

         * @param string $path The path to check.

         * @return bool True if the path exists, false otherwise.

         */

    public function file_exists(string $path): bool
    {

        if ($this->wp_filesystem) {
            return $this->wp_filesystem->exists($path);
        }

        return file_exists($path);
    }



        /**

         * Gets the content of a file.

         *

         * @param string $path The path to the file.

         * @return string|false The file content or false on failure.

         */

    public function get_file_contents(string $path)
    {

        if ($this->wp_filesystem) {
            return $this->wp_filesystem->get_contents($path);
        }

        return file_get_contents($path);
    }



        /**

         * Writes content to a file.

         *

         * @param string $path The path to the file.

         * @param string $content The content to write.

         * @return bool True on success, false on failure.

         */

    public function put_file_contents(string $path, string $content): bool
    {

        if ($this->wp_filesystem) {
            return $this->wp_filesystem->put_contents($path, $content);
        }

        return (bool) file_put_contents($path, $content);
    }

    /**
     * Finds pathnames matching a pattern.
     *
     * Note: WP_Filesystem does not have a direct glob equivalent.
     * This method will always fall back to PHP's glob for now.
     *
     * @param string $pattern The pattern to search for.
     * @param int $flags Flags for glob().
     * @return array|false An array of matching files/directories or false on error.
     */
    public function glob(string $pattern, int $flags = 0)
    {
        // WP_Filesystem does not have a direct glob equivalent.
        // For now, we fall back to PHP's glob.
        return glob($pattern, $flags);
    }

    /**
     * Gets the current working directory.
     *
     * @return string The current working directory.
     */
    public function get_cwd(): string
    {
        $cwd = getcwd();
        return $cwd ?: '.';
    }

    /**
     * Renames a file or directory.
     *
     * @param string $oldname The old path.
     * @param string $newname The new path.
     * @param bool $overwrite Whether to overwrite the target if it already exists.
     * @return bool True on success, false on failure.
     */
    public function rename(string $oldname, string $newname, bool $overwrite = false): bool
    {
        if ($this->wp_filesystem) {
            // WP_Filesystem's move() function handles renaming and overwriting.
            return $this->wp_filesystem->move($oldname, $newname, $overwrite);
        }

        if (! $overwrite && $this->file_exists($newname)) {
            return false; // Target exists and overwrite is false.
        }

        return rename($oldname, $newname);
    }
}

<?php

/**
 * WP Check command for the WPMoo CLI.
 *
 * Provides functionality to check the WordPress environment.
 *
 * @package WPMoo\CLI\Commands
 * @since 0.1.0
 * @link  https://wpmoo.org WPMoo – WordPress Micro Object-Oriented Framework.
 * @link  https://github.com/wpmoo/wpmoo GitHub Repository.
 * @license https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 */

namespace WPMoo\CLI\Commands;

use WPMoo\CLI\Support\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;
use DirectoryIterator;

/**
 * WP Check command to check the WordPress environment.
 */
class WpCheckCommand extends BaseCommand
{
    /**
     * @var Filesystem
     */
    private $filesystem;

    public function __construct(?string $name = null)
    {
        parent::__construct($name);
        $this->filesystem = new Filesystem();
    }

    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this->setName('wp-check')
            ->setDescription('Check WordPress environment for WPMoo compatibility')
            ->setHelp('This command checks the WordPress environment for WPMoo compatibility, including plugin header and readme.txt.')
            ->addOption(
                'path',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to the WordPress installation. If not provided, it will be auto-detected.'
            )
            ->addOption(
                'no-strict',
                null,
                InputOption::VALUE_NONE,
                'Allow non-strict JSON parsing for certain checks.'
            )
            ->addOption(
                'ignore-codes',
                null,
                InputOption::VALUE_REQUIRED,
                'Comma-separated list of error codes to ignore (e.g., A,B).'
            );
    }

    /**
     * Execute the command.
     *
     * @param InputInterface $input Command input.
     * @param OutputInterface $output Command output.
     * @return int Exit status (0 for success, non-zero for failure).
     */
    public function handleExecute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Running WordPress compatibility checks...</info>');

        $path        = $input->getOption('path');
        $noStrict    = $input->getOption('no-strict');
        $ignoreCodes = $input->getOption('ignore-codes');

        $wpRoot = $this->detectWpRoot($path);

        if (!$wpRoot) {
            $helper   = $this->getHelper('question');
            $question = new Question('<question>Could not detect WordPress root. Please provide the path to your WordPress installation: </question>', null);

            while (true) {
                $wpRoot = $helper->ask($input, $output, $question);

                if (empty($wpRoot)) {
                    $output->writeln('<error>No path provided. Aborting.</error>');
                    return 1;
                }

                if ($this->isValidWpRoot($wpRoot)) {
                    break;
                }

                $output->writeln('<error>Invalid WordPress path. Please try again.</error>');
            }
        }

        $output->writeln('<comment>WordPress Root: ' . $wpRoot . '</comment>');
        $output->writeln('No Strict: ' . ($noStrict ? 'Yes' : 'No'));
        $output->writeln('Ignore Codes: ' . ($ignoreCodes ?: 'None'));

        $plugins = $this->findPlugins($wpRoot);

        if (empty($plugins)) {
            $output->writeln('<comment>No WPMoo-based plugins found in the WordPress installation.</comment>');
        } else {
            $output->writeln('<comment>Detected Plugins:</comment>');
            foreach ($plugins as $pluginDir) {
                $output->writeln(' - ' . $pluginDir);
                $mainPluginFile = $this->locateMainPluginFile($pluginDir);
                if ($mainPluginFile) {
                    $headers = $this->parsePluginHeader($mainPluginFile);
                    $output->writeln('   <comment>Plugin Headers:</comment>');
                    foreach ($headers as $key => $value) {
                        $output->writeln('     - ' . $key . ': ' . $value);
                    }
                } else {
                    $output->writeln('   <error>Main plugin file not found.</error>');
                    $headers = $this->getEmptyPluginHeaders();
                }

                $readmeFile = $pluginDir . '/readme.txt';
                if ($this->filesystem->exists($readmeFile)) {
                    $readmeData = $this->parseReadmeTxt($readmeFile);
                    $output->writeln('   <comment>Readme.txt Data:</comment>');
                    foreach ($readmeData as $key => $value) {
                        $output->writeln('     - ' . $key . ': ' . $value);
                    }
                } else {
                    $output->writeln('   <comment>Readme.txt not found.</comment>');
                    $readmeData = $this->getEmptyReadmeData();
                }

                // Run compatibility checks for the current plugin
                $this->runCompatibilityChecks($output, $pluginDir, $headers, $readmeData, $noStrict, $ignoreCodes);
            }
        }

        $output->writeln('<info>WordPress compatibility checks complete.</info>');

        return 0;
    }

    /**
     * Detects the WordPress root directory.
     *
     * @param string|null $cliPath Path provided via CLI option.
     * @return string|null Absolute path to WordPress root or null if not found.
     */
    private function detectWpRoot(?string $cliPath): ?string
    {
        // 1. Prioritize --path option
        if ($cliPath && $this->isValidWpRoot($cliPath)) {
            return $cliPath;
        }

        // 2. Check WP_PATH environment variable
        $envPath = getenv('WP_PATH');
        if ($envPath && $this->isValidWpRoot($envPath)) {
            return $envPath;
        }

        // 3. Auto-detection: walk up parent folders
        $currentDir = getcwd();
        while ($currentDir && $currentDir !== dirname($currentDir)) {
            if ($this->isValidWpRoot($currentDir)) {
                return $currentDir;
            }
            $currentDir = dirname($currentDir);
        }

        // 4. Auto-detection: check public_html relative to Git top-level directory
        $gitTopLevelDir = $this->getGitTopLevelDir();
        if ($gitTopLevelDir) {
            $potentialPath = $gitTopLevelDir . '/public_html';
            if ($this->filesystem->exists($potentialPath) && $this->isValidWpRoot($potentialPath)) {
                return $potentialPath;
            }
        }

        // 5. Auto-detection: check common web roots
        $commonWebRoots = [
            'public_html',
            'htdocs',
            'www',
        ];
        foreach ($commonWebRoots as $root) {
            $potentialPath = getcwd() . '/' . $root;
            if ($this->filesystem->exists($potentialPath) && $this->isValidWpRoot($potentialPath)) {
                return $potentialPath;
            }
        }

        return null;
    }

    /**
     * Checks if a given path is a valid WordPress root.
     *
     * A valid WordPress root contains wp-config.php.
     *
     * @param string $path The path to check.
     * @return bool
     */
    private function isValidWpRoot(string $path): bool
    {
        return $this->filesystem->exists($path . '/wp-config.php');
    }

    /**
     * Gets the Git top-level directory.
     *
     * @return string|null The absolute path to the Git top-level directory or null if not found.
     */
    private function getGitTopLevelDir(): ?string
    {
        // Use shell_exec for simplicity in this context,
        // or a more robust process execution library if available in Symfony Console.
        $command = 'git rev-parse --show-toplevel 2>/dev/null';
        $output = shell_exec($command);

        if ($output === null || trim($output) === '') {
            return null;
        }

        return trim($output);
    }

    /**
     * Finds all WPMoo-based plugin directories within the WordPress installation.
     *
     * @param string $wpRoot The absolute path to the WordPress root directory.
     * @return array<string> An array of absolute paths to WPMoo-based plugin directories.
     */
    private function findPlugins(string $wpRoot): array
    {
        $plugins = [];
        $pluginsDir = $wpRoot . '/wp-content/plugins';

        if (!$this->filesystem->exists($pluginsDir)) {
            return $plugins;
        }

        $iterator = new DirectoryIterator($pluginsDir);

        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isDir() && !$fileinfo->isDot()) {
                $pluginDir = $fileinfo->getPathname();
                // A plugin is typically identified by a PHP file directly inside its directory
                // with a plugin header. For WPMoo, we also expect a composer.json file.
                $hasPluginFile = false;
                $hasComposerJson = false;

                $dirIterator = new DirectoryIterator($pluginDir);
                foreach ($dirIterator as $pluginFileinfo) {
                    if ($pluginFileinfo->isFile() && $pluginFileinfo->getExtension() === 'php') {
                        $content = file_get_contents($pluginFileinfo->getPathname());
                        if (strpos($content, 'Plugin Name:') !== false) {
                            $hasPluginFile = true;
                        }
                    } elseif ($pluginFileinfo->isFile() && $pluginFileinfo->getFilename() === 'composer.json') {
                        $hasComposerJson = true;
                    }
                }

                // Consider it a WPMoo-based plugin if it has a plugin header PHP file and a composer.json
                if ($hasPluginFile && $hasComposerJson) {
                    $plugins[] = $pluginDir;
                }
            }
        }

        return $plugins;
    }

    /**
     * Locates the main plugin file within a given plugin directory.
     *
     * @param string $pluginDir The absolute path to the plugin directory.
     * @return string|null The absolute path to the main plugin file, or null if not found.
     */
    private function locateMainPluginFile(string $pluginDir): ?string
    {
        $mainPluginFile = null;
        $dirIterator = new DirectoryIterator($pluginDir);
        foreach ($dirIterator as $fileinfo) {
            if ($fileinfo->isFile() && $fileinfo->getExtension() === 'php') {
                $content = file_get_contents($fileinfo->getPathname());
                if (strpos($content, 'Plugin Name:') !== false) {
                    return $fileinfo->getPathname();
                }
            }
        }
        return null;
    }

    /**
     * Parses the WordPress plugin header from a given plugin file.
     *
     * @param string $pluginFile The absolute path to the main plugin file.
     * @return array<string, string> An associative array of plugin header data.
     */
    private function parsePluginHeader(string $pluginFile): array
    {
        $headers = [
            'Plugin Name' => '',
            'Plugin URI' => '',
            'Version' => '',
            'Description' => '',
            'Author' => '',
            'Author URI' => '',
            'Text Domain' => '',
            'Domain Path' => '',
            'Network' => '',
            'Requires WP' => '',
            'Requires PHP' => '',
            'License' => '',
            'License URI' => '',
        ];

        $fileContents = file_get_contents($pluginFile);
        if (!$fileContents) {
            return $headers;
        }

        // Only read the first 8 KB of the file to avoid performance issues with large files.
        $fileContents = substr($fileContents, 0, 8 * 1024);

        foreach ($headers as $header => $value) {
            $regex = '/^[ \t\/*#@]*' . preg_quote($header, '/') . ':(.*)$/mi';
            if (preg_match($regex, $fileContents, $matches)) {
                $headers[$header] = trim($matches[1]);
            }
        }

        return $headers;
    }

    /**
     * Parses the WordPress readme.txt file for relevant information.
     *
     * @param string $readmeFile The absolute path to the readme.txt file.
     * @return array<string, string> An associative array of readme data.
     */
    private function parseReadmeTxt(string $readmeFile): array
    {
        $readmeData = [
            'Stable tag' => '',
            'Requires at least' => '',
            'Tested up to' => '',
            'Requires PHP' => '',
            'License' => '',
            'License URI' => '',
        ];

        $fileContents = file_get_contents($readmeFile);
        if (!$fileContents) {
            return $readmeData;
        }

        // Read the file line by line to extract headers
        $lines = explode("\n", $fileContents);
        foreach ($lines as $line) {
            foreach ($readmeData as $header => $value) {
                if (strpos($line, $header . ':') === 0) {
                    $readmeData[$header] = trim(substr($line, strlen($header . ':')));
                }
            }
        }

        return $readmeData;
    }

    /**
     * Returns an empty array of plugin header data.
     *
     * @return array<string, string> An associative array of empty plugin header data.
     */
    private function getEmptyPluginHeaders(): array
    {
        return [
            'Plugin Name' => '',
            'Plugin URI' => '',
            'Version' => '',
            'Description' => '',
            'Author' => '',
            'Author URI' => '',
            'Text Domain' => '',
            'Domain Path' => '',
            'Network' => '',
            'Requires WP' => '',
            'Requires PHP' => '',
            'License' => '',
            'License URI' => '',
        ];
    }

    /**
     * Returns an empty array of readme.txt data.
     *
     * @return array<string, string> An associative array of empty readme.txt data.
     */
    private function getEmptyReadmeData(): array
    {
        return [
            'Stable tag' => '',
            'Requires at least' => '',
            'Tested up to' => '',
            'Requires PHP' => '',
            'License' => '',
            'License URI' => '',
        ];
    }

    /**
     * Runs WPMoo compatibility checks on a plugin.
     *
     * @param OutputInterface $output The console output interface.
     * @param string $pluginDir The plugin's directory.
     * @param array<string, string> $pluginHeaders Parsed plugin headers.
     * @param array<string, string> $readmeData Parsed readme.txt data.
     * @param bool $noStrict Whether to allow non-strict JSON parsing.
     * @param string|null $ignoreCodes Comma-separated list of error codes to ignore.
     */
    private function runCompatibilityChecks(
        OutputInterface $output,
        string $pluginDir,
        array $pluginHeaders,
        array $readmeData,
        bool $noStrict,
        ?string $ignoreCodes
    ): void {
        $output->writeln('   <comment>Running compatibility checks:</comment>');

        $ignoredCodesArray = $ignoreCodes ? explode(',', $ignoreCodes) : [];

        // Check 1: Plugin Name contains "WPMoo"
        if (isset($pluginHeaders['Plugin Name']) && strpos($pluginHeaders['Plugin Name'], 'WPMoo') === false) {
            $this->outputCheckResult($output, 'P001', 'Plugin name should contain "WPMoo".', 'warning', $ignoredCodesArray, $noStrict);
        } else {
            $output->writeln('     - <info>P001: Plugin name contains "WPMoo".</info>');
        }

        // Check 2: Composer.json exists and is valid
        $composerJsonPath = $pluginDir . '/composer.json';
        if ($this->filesystem->exists($composerJsonPath)) {
            $composerContent = file_get_contents($composerJsonPath);
            if ($composerContent === false) {
                $this->outputCheckResult($output, 'P002', 'Could not read composer.json.', 'error', $ignoredCodesArray, $noStrict);
            } else {
                $composerData = json_decode($composerContent, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->outputCheckResult($output, 'P003', 'Invalid composer.json: ' . json_last_error_msg(), 'error', $ignoredCodesArray, $noStrict, !$noStrict);
                } else {
                    $output->writeln('     - <info>P002: composer.json exists and is valid.</info>');
                }
            }
        } else {
            $this->outputCheckResult($output, 'P004', 'composer.json not found.', 'warning', $ignoredCodesArray, $noStrict);
        }

        // Add more checks here as needed
    }

    /**
     * Helper to output check results based on ignore codes and strictness.
     *
     * @param OutputInterface $output The console output interface.
     * @param string $code The unique code for the check.
     * @param string $message The message to display.
     * @param string $type The type of message (info, comment, question, error, warning).
     * @param array<string> $ignoredCodesArray Array of codes to ignore.
     * @param bool $noStrict Whether to allow non-strict parsing.
     * @param bool $alwaysShow If true, always show the message regardless of ignoreCodes or noStrict.
     */
    private function outputCheckResult(
        OutputInterface $output,
        string $code,
        string $message,
        string $type,
        array $ignoredCodesArray,
        bool $noStrict,
        bool $alwaysShow = false
    ): void {
        if ($alwaysShow || !in_array($code, $ignoredCodesArray, true) && !($noStrict && $type === 'warning')) {
            $output->writeln("     - <{$type}>{$code}: {$message}</{$type}>");
        } elseif ($noStrict && $type === 'warning') {
            $output->writeln("     - <comment>{$code}: {$message} (Ignored due to --no-strict)</comment>");
        } elseif (in_array($code, $ignoredCodesArray, true)) {
            $output->writeln("     - <comment>{$code}: {$message} (Ignored by --ignore-codes)</comment>");
        }
    }
}

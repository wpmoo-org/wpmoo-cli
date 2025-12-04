<?php

namespace WPMoo\CLI\Commands\Framework;

use WPMoo\CLI\Support\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * Development command for WPMoo projects.
 *
 * Sets up a development environment with live reloading and asset compilation.
 *
 * @package WPMoo\CLI\Commands\Framework
 * @since 0.1.0
 */
class DevCommand extends BaseCommand
{
    protected static $defaultName = 'dev';

    protected function configure()
    {
        $this->setDescription('Starts a development server with live reloading and asset compilation for WPMoo projects.')
            ->setHelp('This command watches for changes in SCSS, JS, PHP, and HTML files, compiles assets, and serves the project via BrowserSync.');
    }

    /**
     * Executes the dev command.
     *
     * @param InputInterface $input The input instance.
     * @param OutputInterface $output The output instance.
     * @return int The exit status code.
     */
    public function handle_execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('WPMoo Development Server');

        // 1. Identify Project Context
        $project = $this->identify_project();
        if ($project['type'] !== 'wpmoo-framework' && $project['type'] !== 'wpmoo-plugin' && $project['type'] !== 'wpmoo-theme') {
            $io->error('The "dev" command can only be used inside a WPMoo framework, plugin, or theme project.');
            return self::FAILURE;
        }

        $project_root = $project['root'] ?? $this->get_cwd();
        $io->writeln(sprintf('<info>Starting dev server for project:</info> %s', $project_root));

        // 2. Initial Build
        $io->section('Performing initial build...');
        $build_all_command = $this->getApplication()->find('build:all');
        $build_return_code = $build_all_command->run(new ArrayInput([]), $output);
        if ($build_return_code !== self::SUCCESS) {
            $io->error('Initial build failed. Aborting dev server.');
            return self::FAILURE;
        }
        $io->success('Initial build completed.');

        // 3. Setup BrowserSync config (from gulpfile.js)
        $browser_sync_config = $this->getBrowserSyncConfig($project_root);

        // 4. Start watchers and BrowserSync using concurrently
        $io->section('Starting watchers and BrowserSync...');

        $npm_exec = $this->getNpmExecutable($project_root);
        $concurrently_path = path_join(__DIR__, '../../../node_modules/.bin/concurrently'); // wpmoo-cli's node_modules
        $chokidar_path = path_join(__DIR__, '../../../node_modules/.bin/chokidar'); // wpmoo-cli's node_modules
        $scripts_path = path_join(__DIR__, '../../scripts'); // wpmoo-cli's scripts dir

        $target_dir_arg = escapeshellarg($project_root);

        $commands = [
            // Watch SCSS files and build styles
            sprintf(
                '%s %s "resources/scss/**/*.scss" -c "%s %s/build-styles.js %s && browser-sync reload --files \'**/*.css\'"',
                $npm_exec,
                $chokidar_path,
                $npm_exec,
                $scripts_path,
                $target_dir_arg
            ),
            // Watch JS files and build scripts
            sprintf(
                '%s %s "resources/js/**/*.js" -c "%s %s/build-scripts.js %s && browser-sync reload --files \'**/*.js\'"',
                $npm_exec,
                $chokidar_path,
                $npm_exec,
                $scripts_path,
                $target_dir_arg
            ),
            // Watch PHP and HTML files and reload browser-sync
            sprintf(
                '%s %s "**/*.php" "index.html" -c "browser-sync reload"',
                $npm_exec,
                $chokidar_path
            ),
            // Start BrowserSync
            sprintf(
                'browser-sync start %s',
                $this->buildBrowserSyncArgs($browser_sync_config)
            ),
        ];

        $concurrently_command = sprintf(
            '%s %s --kill-others --prefix "[{name}]" --names "Styles,Scripts,PHP/HTML,BrowserSync" -c "bgBlue.bold,bgGreen.bold,bgYellow.bold,bgMagenta.bold" -- %s',
            $npm_exec,
            $concurrently_path,
            implode(' ', array_map('escapeshellarg', $commands))
        );

        $io->writeln(sprintf('<comment>Running: %s</comment>', $concurrently_command));

        // Use a process that doesn't terminate the CLI command
        $process = Process::fromShellCommandline($concurrently_command, $project_root);
        $process->setTimeout(null); // No timeout
        $process->setIdleTimeout(null); // No idle timeout
        $process->setTty(Process::isTtySupported()); // Enable TTY for colored output

        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Gets the npm executable path.
     *
     * @param string $project_root The root directory of the project.
     * @return string The npm executable.
     */
    private function getNpmExecutable(string $project_root): string
    {
        // Try local node_modules first
        $local_npm = path_join($project_root, 'node_modules/.bin/npm');
        if (file_exists($local_npm)) {
            return $local_npm;
        }

        // Fallback to global npm
        return 'npm';
    }

    /**
     * Builds BrowserSync arguments from config.
     *
     * @param array $config BrowserSync configuration.
     * @return string Arguments string.
     */
    private function buildBrowserSyncArgs(array $config): string
    {
        $args = [];
        if (isset($config['proxy'])) {
            $args[] = sprintf('--proxy %s', escapeshellarg($config['proxy']));
        }
        if (isset($config['startPath'])) {
            $args[] = sprintf('--startPath %s', escapeshellarg($config['startPath']));
        }
        if (isset($config['https'])) {
            if (is_array($config['https'])) {
                $args[] = sprintf('--https.key %s', escapeshellarg($config['https']['key']));
                $args[] = sprintf('--https.cert %s', escapeshellarg($config['https']['cert']));
            } elseif ($config['https'] === true) {
                $args[] = '--https';
            }
        }
        if (isset($config['open'])) {
            $args[] = sprintf('--open %s', escapeshellarg($config['open']));
        }
        if (isset($config['notify'])) {
            $args[] = sprintf('--no-notify'); // BrowserSync uses --no-notify for false
        }

        // Always include files to watch for browser-sync reloads
        $files_to_watch = [
            'assets/css/*.css',
            'assets/js/*.js',
            '**/*.php',
            '*.html' // For direct HTML changes
        ];
        $args[] = sprintf('--files %s', escapeshellarg(implode(',', $files_to_watch)));

        // Inject auto-login snippet if configured
        if (isset($config['snippetOptions']['rule']['fn'])) {
            // Note: Directly injecting JS snippet like this can be tricky with CLI args.
            // For simplicity, we might need a dedicated Node.js script for BrowserSync config.
            // For now, let's just make sure the proxy works.
        }

        return implode(' ', $args);
    }


    /**
     * Gets BrowserSync configuration based on gulpfile.js logic.
     *
     * @param string $project_root The root directory of the WPMoo project.
     * @return array BrowserSync configuration.
     */
    private function getBrowserSyncConfig(string $project_root): array
    {
        $bsConfig = [
            'proxy' => 'https://wp-dev.local',
            'startPath' => '/wp-admin/admin.php?page=wpmoo-samples',
            'https' => true, // Default to self-signed
            'open' => 'https://wp-dev.local',
            'notify' => false,
        ];

        // HTTPS options from environment or .dev/certs
        $httpOff = getenv('BS_HTTP');
        if ($httpOff && in_array(strtolower($httpOff), ["1", "true", "on"])) {
            $bsConfig['https'] = false;
        } else {
            $keyEnv = getenv('BS_HTTPS_KEY');
            $certEnv = getenv('BS_HTTPS_CERT');
            if ($keyEnv && $certEnv && file_exists($keyEnv) && file_exists($certEnv)) {
                $bsConfig['https'] = ['key' => $keyEnv, 'cert' => $certEnv];
            } else {
                // Check .dev/certs relative to wp-dev root (monorepo root)
                $wp_dev_root = dirname(dirname(dirname(dirname(__DIR__)))); // Go up to /Users/cng/Sites/wp-dev
                $certDir = path_join($wp_dev_root, ".dev/certs");
                $keyPath = path_join($certDir, "localhost-key.pem");
                $certPath = path_join($certDir, "localhost.pem");
                if (file_exists($keyPath) && file_exists($certPath)) {
                    $bsConfig['https'] = ['key' => $keyPath, 'cert' => $certPath];
                }
            }
        }

        // Auto-login (simplified for direct CLI args)
        $loginUser = getenv('WP_DEV_USER') ?: "";
        $loginPass = getenv('WP_DEV_PASS') ?: "";
        $autoLoginOn = in_array(strtolower(getenv('BS_AUTO_LOGIN') ?: "1"), ["1", "true", "on", "yes"]);

        // BrowserSync CLI doesn't easily support snippet injection like Gulp's JS API.
        // If auto-login snippet is critical, we might need to wrap browser-sync in a Node.js script.
        // For now, we'll omit direct snippet injection via CLI args.
        // $bsConfig['injectLogin'] = (bool) ($loginUser && $loginPass && $autoLoginOn);

        return $bsConfig;
    }
}

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

        $project = $this->identify_project();
        if ($project['type'] !== 'wpmoo-framework' && $project['type'] !== 'wpmoo-plugin' && $project['type'] !== 'wpmoo-theme') {
            $io->error('The "dev" command can only be used inside a WPMoo framework, plugin, or theme project.');
            return self::FAILURE;
        }

        $project_root = $project['root'] ?? $this->get_cwd();
        $cli_root = dirname(__DIR__, 3);

        $io->writeln(sprintf('<info>Starting dev server for project:</info> %s', $project_root));

        // Initial Build
        $io->section('Performing initial build...');
        $build_process = new Process(['node', $cli_root . '/scripts/build-styles.js', $project_root]);
        $build_process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        $build_process = new Process(['node', $cli_root . '/scripts/build-scripts.js', $project_root]);
        $build_process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        $io->success('Initial build completed.');

        // Concurrently command setup
        $io->section('Starting watchers and BrowserSync...');

        $concurrently_path = $cli_root . '/node_modules/.bin/concurrently';
        $chokidar_path = $cli_root . '/node_modules/.bin/chokidar';
        $browser_sync_path = $cli_root . '/node_modules/.bin/browser-sync';
        $build_styles_script = $cli_root . '/scripts/build-styles.js';
        $build_scripts_script = $cli_root . '/scripts/build-scripts.js';

        $watch_styles = sprintf(
            "%s '%s/resources/scss/**/*.scss' --command 'node %s %s'",
            $chokidar_path,
            $project_root,
            $build_styles_script,
            $project_root
        );

        $watch_js = sprintf(
            "%s '%s/resources/js/**/*.js' --command 'node %s %s'",
            $chokidar_path,
            $project_root,
            $build_scripts_script,
            $project_root
        );

        $watch_php = sprintf(
            "%s '%s/**/*.php' '%s/index.html' --ignore '%s/node_modules/**' --ignore '%s/vendor/**' --ignore '%s/.git/**' --command 'node -e \"console.log(\\\"[PHP] Reloading...\\\")\" && %s reload'",
            $chokidar_path,
            $project_root,
            $project_root,
            $project_root,
            $project_root,
            $project_root,
            $browser_sync_path
        );

        $browser_sync_config = $this->getBrowserSyncConfig($project_root);
        $serve_command = sprintf(
            "%s start %s",
            $browser_sync_path,
            $this->buildBrowserSyncArgs($browser_sync_config)
        );

        $concurrently_command = sprintf(
            '%s --kill-others --prefix "[{name}]" --names "Styles,Scripts,PHP,Sync" -c "bgBlue.bold,bgGreen.bold,bgYellow.bold,bgMagenta.bold" %s %s %s %s',
            $concurrently_path,
            escapeshellarg($watch_styles),
            escapeshellarg($watch_js),
            escapeshellarg($watch_php),
            escapeshellarg($serve_command)
        );

        $io->writeln(sprintf('<comment>Running: %s</comment>', $concurrently_command));

        $process = Process::fromShellCommandline($concurrently_command, $project_root);
        $process->setTimeout(null)->setIdleTimeout(null)->setTty(Process::isTtySupported());

        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }

            /**
             * Gets BrowserSync configuration based on gulpfile.js logic.
             * @return array
             */
    private function getBrowserSyncConfig(string $project_root): array
    {
        $bsConfig = [
            'proxy' => 'https://wp-dev.local',
            'startPath' => '/wp-admin/admin.php?page=wpmoo-samples',
            'https' => true,
            'open' => 'https://wp-dev.local',
            'notify' => false,
        ];

        if (getenv('BS_HTTP') && in_array(strtolower(getenv('BS_HTTP')), ["1", "true", "on"])) {
            $bsConfig['https'] = false;
        } else {
            $wp_dev_root = dirname($project_root, 2);
            $certDir = wpmoo_path_join($wp_dev_root, ".dev/certs");
            $keyPath = wpmoo_path_join($certDir, "localhost-key.pem");
            $certPath = wpmoo_path_join($certDir, "localhost.pem");
            if (file_exists($keyPath) && file_exists($certPath)) {
                $bsConfig['https'] = ['key' => $keyPath, 'cert' => $certPath];
            }
        }
        return $bsConfig;
    }

            /**
             * Builds BrowserSync arguments from config.
             * @param array $config
             * @return string
             */
    private function buildBrowserSyncArgs(array $config): string
    {
        $args = [];
        if (!empty($config['proxy'])) {
            $args[] = sprintf('--proxy %s', escapeshellarg($config['proxy']));
        }
        if (!empty($config['startPath'])) {
            $args[] = sprintf('--startPath %s', escapeshellarg($config['startPath']));
        }
        if (isset($config['https'])) {
            if (is_array($config['https'])) {
                $args[] = sprintf('--https-key %s --https-cert %s', escapeshellarg($config['https']['key']), escapeshellarg($config['https']['cert']));
            } elseif ($config['https'] === true) {
                $args[] = '--https';
            }
        }
        if (!empty($config['open'])) {
            $args[] = sprintf('--open %s', escapeshellarg($config['open']));
        }
        if (isset($config['notify']) && $config['notify'] === false) {
            $args[] = '--no-notify';
        }

        $files_to_watch = ['assets/css/*.css', 'assets/js/*.js'];
        $args[] = sprintf('--files "%s"', implode(',', $files_to_watch));

        return implode(' ', $args);
    }
}

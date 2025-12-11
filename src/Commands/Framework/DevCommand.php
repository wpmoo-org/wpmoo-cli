<?php

namespace WPMoo\CLI\Commands\Framework;

use WPMoo\CLI\Support\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use WPMoo\CLI\Support\NodeEnvironment;

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
        $this->setDescription('Starts a development server with live reloading.')
            ->setAliases(['watch'])
            ->setHelp('This command watches for changes in SCSS, JS, and PHP files, compiles assets, and serves the project via BrowserSync.');
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

        $project_root = $project['main_file'] ? dirname($project['main_file']) : $this->get_cwd();
        $cli_root = dirname(__DIR__, 3);
        $bin_dir = $cli_root . '/node_modules/.bin';

        // Ensure internal Node.js environment is ready
        $node_env = new NodeEnvironment($this->filesystem);
        if (!$node_env->ensure_dependencies($io, $cli_root)) {
            return self::FAILURE;
        }

        $io->writeln(sprintf('<info>Starting dev server for project:</info> %s', $project_root));

        $config_manager = new \WPMoo\CLI\Support\ConfigManager($project_root, $this->filesystem);
        $dev_config = $config_manager->get('dev', []);

        $browser_sync_config = $dev_config['browser_sync'] ?? [];
        $dev_theme = $dev_config['theme'] ?? 'amber';

        // Define script paths based on context
        $context_dir = ($project['type'] === 'wpmoo-framework') ? 'framework' : 'plugin';
        $build_styles_script = $cli_root . "/scripts/{$context_dir}/build-styles.js";
        $build_scripts_script = $cli_root . '/scripts/common/build-scripts.js';

        $io->note('Initial build in progress...');

        // Initial Build: Styles
        $io->text(sprintf('Building styles (Dev Mode: %s only)...', $dev_theme));
        $style_process = new Process(
            ['node', $build_styles_script, $project_root],
            null,
            ['DEV_MODE' => 'true', 'WPMOO_DEV_THEME' => $dev_theme]
        );
        $style_process->run();
        if (!$style_process->isSuccessful()) {
            $io->error('Style build failed.');
            $io->writeln($style_process->getErrorOutput());
            return self::FAILURE;
        }

        // Initial Build: Scripts
        $io->text('Building scripts...');
        $script_process = new Process(['node', $build_scripts_script, $project_root]);
        $script_process->run();
        if (!$script_process->isSuccessful()) {
            $io->error('Script build failed.');
            $io->writeln($script_process->getErrorOutput());
            return self::FAILURE;
        }

        $io->success('Initial build completed.');
        $io->section('Starting watchers and BrowserSync...');

        // Get configuration
        $config_manager = new \WPMoo\CLI\Support\ConfigManager($project_root);
        $proxy_url = $config_manager->get('dev.proxy', 'https://wp-dev.local');

        // Construct Concurrent Commands

        // 1. Watch Styles
        // chokidar 'path/to/scss/**/*.scss' -c 'DEV_MODE=true WPMOO_QUIET_BUILD=true WPMOO_DEV_THEME=%s node build-styles.js path/to/project' > /dev/null
        $cmd_watch_styles = sprintf(
            '%s/chokidar "%s/resources/scss/**/*.scss" --quiet -c "DEV_MODE=true WPMOO_QUIET_BUILD=true WPMOO_DEV_THEME=%s node %s %s" > /dev/null',
            $bin_dir,
            $project_root,
            $dev_theme,
            $build_styles_script,
            $project_root
        );

        // 2. Watch Scripts
        // chokidar 'path/to/js/**/*.js' -c 'WPMOO_QUIET_BUILD=true node build-scripts.js path/to/project' > /dev/null
        $cmd_watch_js = sprintf(
            '%s/chokidar "%s/resources/js/**/*.js" --quiet -c "WPMOO_QUIET_BUILD=true node %s %s" > /dev/null',
            $bin_dir,
            $project_root,
            $build_scripts_script,
            $project_root
        );

        // 3. Serve (BrowserSync)
        $cmd_serve = null;
        $files_to_watch_array = $browser_sync_config['files'] ?? [
            "./**/*.php",
            "./assets/js/**/*.js",
            "./assets/css/**/*.css",
        ];
        $files_to_watch_string = implode(',', array_map(function ($file) use ($project_root) {
            return $project_root . '/' . ltrim($file, './');
        }, $files_to_watch_array));

        if (($browser_sync_config['enabled'] ?? true)) { // Default to true if not specified
            $browser_sync_args = [
                'start',
                '--proxy',
                sprintf('"%s"', $browser_sync_config['url'] ?? 'https://wp-dev.local'), // Use config URL
                '--https',
                '--startPath "/wp-admin"',
            ];

            // Add port if specified
            if (isset($browser_sync_config['port'])) {
                $browser_sync_args[] = '--port ' . $browser_sync_config['port'];
            } else {
                $browser_sync_args[] = '--port 3000'; // Default port
            }

            // Open browser automatically
            if (!($browser_sync_config['open'] ?? true)) { // Default to true, add --no-open if false
                $browser_sync_args[] = '--no-open';
            }

            // BrowserSync notification
            if (!($browser_sync_config['notify'] ?? false)) { // Default to false, add --no-notify if false
                $browser_sync_args[] = '--no-notify';
            }

            // Files to watch
            $browser_sync_args[] = sprintf('--files "%s"', $files_to_watch_string);

            $cmd_serve = sprintf(
                '%s/browser-sync %s',
                $bin_dir,
                implode(' ', $browser_sync_args)
            );
        }

        // Combine with concurrently
        $concurrently_cmd = [
            $bin_dir . '/concurrently',
            '--kill-others',
            '--raw',
            '--names', 'styles,scripts', // Names will be updated dynamically
            '--prefix-colors', 'magenta,blue', // Prefix colors will be updated dynamically
            $cmd_watch_styles,
            $cmd_watch_js,
        ];

        if ($cmd_serve) {
            $concurrently_cmd[4] .= ',serve'; // Add 'serve' to --names
            $concurrently_cmd[6] .= ',green'; // Add 'green' to --prefix-colors
            $concurrently_cmd[] = $cmd_serve; // Add the browser-sync command
        }


        $dev_process = new Process($concurrently_cmd);
        $dev_process->setTimeout(null)->setIdleTimeout(null)->setTty(Process::isTtySupported());

        return $dev_process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
    }
}

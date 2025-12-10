<?php

namespace WPMoo\CLI\Commands\Framework;

use WPMoo\CLI\Support\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Builds a clean, distributable package in the `dist` directory.
 *
 * @package WPMoo\CLI\Commands
 * @since 0.1.0
 * @link https://wpmoo.org   WPMoo – WordPress Micro Object-Oriented Framework.
 * @link https://github.com/wpmoo/wpmoo   GitHub Repository.
 * @license https://spdx.org/licenses/GPL-2.0-or-later.html   GPL-2.0-or-later
 */
class DeployDistCommand extends BaseCommand
{
    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('deploy:dist')
            ->setDescription('Package in the `dist` directory.')
            ->setAliases(['deploy']);
    }

    /**
     * Execute the command.
     *
     * @param InputInterface $input The input interface.
     * @param OutputInterface $output The output interface.
     * @return int The command exit status.
     */
    public function handle_execute(InputInterface $input, OutputInterface $output): int
    {
        $project = $this->identify_project();
        if ($project['type'] !== 'wpmoo-framework' && $project['type'] !== 'wpmoo-plugin') {
            $output->writeln('<error>This command can only be run from the root of a WPMoo framework or plugin project.</error>');
            return self::FAILURE;
        }

        // Determine project slug
        $slug = basename($this->get_cwd());
        if (file_exists($this->get_cwd() . '/composer.json')) {
            $composer_data = json_decode(file_get_contents($this->get_cwd() . '/composer.json'), true);
            if (isset($composer_data['name'])) {
                $parts = explode('/', $composer_data['name']);
                $slug = end($parts);
            }
        }

        // Build assets before packaging.
        $output->writeln('> Building assets...');
        try {
            $application = $this->getApplication();
            if (! $application) {
                $output->writeln('<error>Application instance not found to run build command.</error>');
                return self::FAILURE;
            }
            $buildCommand = $application->find('build');
            $buildInput = new ArrayInput([]);
            $buildReturnCode = $buildCommand->run($buildInput, $output);

            if ($buildReturnCode !== self::SUCCESS) {
                $output->writeln('<error>WPMoo build command failed. Aborting.</error>');
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $output->writeln("<error>Error running build command: {$e->getMessage()}</error>");
            return self::FAILURE;
        }

        $dist_root = $this->get_cwd() . '/dist';
        $build_path = $dist_root . '/' . $slug;

        $output->writeln("> Creating a clean build in {$build_path}...");

        // Clean dist root
        if (is_dir($dist_root)) {
            $this->run_process(['rm', '-rf', $dist_root], $output);
        }
        $this->run_process(['mkdir', '-p', $build_path], $output);

        $this->run_shell_command_wrapper('git archive HEAD | tar -x -C ' . escapeshellarg($build_path), $output, true);

        // Explicitly copy composer.json as git archive might ignore it due to .gitattributes
        if (file_exists($this->get_cwd() . '/composer.json')) {
            copy($this->get_cwd() . '/composer.json', $build_path . '/composer.json');
        }

        // After git archive, copy built assets if they exist
        $assetsPath = $this->get_cwd() . '/assets';
        if (is_dir($assetsPath)) {
            $this->run_process(['cp', '-r', $assetsPath, $build_path . '/'], $output);
        }

        // Logic for plugins
        if ($project['type'] === 'wpmoo-plugin') {
            $output->writeln('> Preparing production dependencies for plugin...');
            $build_composer_path = $build_path . '/composer.json';
            if (file_exists($build_composer_path)) {
                $composer_data = json_decode(file_get_contents($build_composer_path), true);
                unset($composer_data['require']['wpmoo/wpmoo'], $composer_data['repositories'], $composer_data['scripts']);
                file_put_contents($build_composer_path, json_encode($composer_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
            $this->run_process(['composer', 'install', '--no-dev', '--optimize-autoloader'], $output, false, $build_path);
            $this->cleanup_composer_files($build_path, $output);

            $output->writeln('> Bundling WPMoo framework into wpmoo-core...');
            $wpmoo_core_path = $build_path . '/wpmoo-core';
            $this->run_process(['mkdir', '-p', $wpmoo_core_path], $output);
            $source_wpmoo_path = $this->get_cwd() . '/vendor/wpmoo/wpmoo';
            if (is_dir($source_wpmoo_path)) {
                $this->run_shell_command_wrapper('cd ' . escapeshellarg($source_wpmoo_path) . ' && git archive HEAD | tar -x -C ' . escapeshellarg($wpmoo_core_path), $output, true);
                $core_composer_path = $wpmoo_core_path . '/composer.json';
                if (file_exists($core_composer_path)) {
                    $core_composer_data = json_decode(file_get_contents($core_composer_path), true);
                    unset($core_composer_data['require-dev'], $core_composer_data['repositories'], $core_composer_data['scripts']);
                    file_put_contents($core_composer_path, json_encode($core_composer_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                }
                $this->run_process(['composer', 'install', '--no-dev', '--optimize-autoloader'], $output, false, $wpmoo_core_path);
                $this->cleanup_composer_files($wpmoo_core_path, $output);
            }
        }

        // Logic for framework
        if ($project['type'] === 'wpmoo-framework') {
            $output->writeln('> Preparing production dependencies for framework...');
            $build_composer_path = $build_path . '/composer.json';
            if (file_exists($build_composer_path)) {
                $composer_data = json_decode(file_get_contents($build_composer_path), true);
                unset($composer_data['require-dev'], $composer_data['repositories'], $composer_data['scripts']);
                file_put_contents($build_composer_path, json_encode($composer_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
            $this->run_process(['composer', 'install', '--no-dev', '--optimize-autoloader'], $output, false, $build_path);
            $this->cleanup_composer_files($build_path, $output);
        }

        $output->writeln('');
        $output->writeln('<success>Build complete!</success>');
        $output->writeln("The distributable package is located in the <info>dist/{$slug}</info> directory.");
        return self::SUCCESS;
    }

    /**
     * Clean up Composer-related files from the distributable package.
     *
     * @param string $path The path to the distributable package.
     * @param OutputInterface $output The output interface.
     * @return void
     */
    private function cleanup_composer_files(string $path, OutputInterface $output)
    {
        $output->writeln('> Cleaning up Composer files from distributable...');
        if (file_exists($path . '/composer.json')) {
            unlink($path . '/composer.json');
        }
        if (file_exists($path . '/composer.lock')) {
            unlink($path . '/composer.lock');
        }
    }

    /**
     * Run a shell process.
     *
     * @param array $command The command to run as an array of arguments.
     * @param OutputInterface $output The output interface.
     * @param bool $quiet Whether to suppress output from the process.
     * @param string|null $cwd The current working directory for the process.
     * @param array $env Environment variables for the process.
     * @return void
     */
    private function run_process(array $command, OutputInterface $output, bool $quiet = false, ?string $cwd = null, array $env = []): void
    {
        $process = new Process($command, $cwd, $env);
        $process->mustRun(
            function ($type, $buffer) use ($output, $quiet) {
                if (! $quiet) {
                    $output->write($buffer);
                }
            }
        );
    }

    /**
     * Run a shell command via `Process::fromShellCommandline`.
     *
     * @param string $command The shell command string.
     * @param OutputInterface $output The output interface.
     * @param bool $quiet Whether to suppress output from the process.
     * @param string|null $cwd The current working directory for the process.
     * @return void
     */
    private function run_shell_command_wrapper(string $command, OutputInterface $output, bool $quiet = false, ?string $cwd = null)
    {
        $process = Process::fromShellCommandline($command, $cwd);
        $process->mustRun(
            function ($type, $buffer) use ($output, $quiet) {
                if (! $quiet) {
                    $output->write($buffer);
                }
            }
        );
    }
}

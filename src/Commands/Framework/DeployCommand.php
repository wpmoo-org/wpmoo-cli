<?php

namespace WPMoo\CLI\Commands\Framework;

use WPMoo\CLI\Support\BaseCommand;
use WPMoo\CLI\Support\VersionManager;
use WPMoo\CLI\Support\PotGenerator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Deploy command for the WPMoo CLI.
 *
 * Handles the deployment process of the WPMoo framework to the WordPress.org SVN repository.
 *
 * @package WPMoo\CLI\Commands
 * @since 0.1.0
 */
class DeployCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('deploy')
            ->setDescription('Prepares a new release: builds assets, bumps versions, and tags the release for deployment.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Perform a dry run without committing or tagging.')
            ->addOption('build-only', null, InputOption::VALUE_NONE, 'Only build the distributable package in a `dist` folder and exit.');
    }

    public function handle_execute(InputInterface $input, OutputInterface $output): int
    {
        $project = $this->identify_project();
        if ($project['type'] !== 'wpmoo-framework' && $project['type'] !== 'wpmoo-plugin') {
            $output->writeln('<error>The deploy command can only be run from the root of a WPMoo framework project or a WPMoo-based plugin.</error>');
            return self::FAILURE;
        }

        $output->writeln('<info>Preparing for deployment...</info>');

        $version_manager = new VersionManager($this);

        // 1. Get new version.
        $current_version = $version_manager->get_current_version($project);
        $output->writeln("<comment>Current version: {$current_version}</comment>");
        $new_version = $version_manager->interactive_version_selection($input, $output, $current_version);

        if (empty($new_version)) {
            $output->writeln('<comment>Operation cancelled.</comment>');
            return self::SUCCESS;
        }
        if (! $version_manager->is_valid_version($new_version)) {
            $output->writeln("<error>Invalid version format: {$new_version}</error>");
            return self::FAILURE;
        }

        $output->writeln("<comment>New version will be: {$new_version}</comment>");
        $confirmation = new ConfirmationQuestion("<question>Continue with operation for version {$new_version}? (y/N)</question> ", false);
        if (! $this->getHelper('question')->ask($input, $output, $confirmation)) {
            $output->writeln('<comment>Operation cancelled.</comment>');
            return self::SUCCESS;
        }

        // 2. Build assets using the internal 'build' command.
        $output->writeln('> Building assets using internal WPMoo build command...');
        try {
            $application = $this->getApplication();
            if (! $application) {
                $output->writeln('<error>Application instance not found to run build command.</error>');
                return self::FAILURE;
            }
            $buildCommand = $application->find('build');
            $buildInput = new ArrayInput([]); // No arguments needed for the build command itself.
            $buildReturnCode = $buildCommand->run($buildInput, $output);

            if ($buildReturnCode !== self::SUCCESS) {
                $output->writeln('<error>WPMoo build command failed. Aborting deployment.</error>');
                return self::FAILURE;
            }
        } catch (\Exception $e) { // Catch generic exception for clarity
            $output->writeln("<error>Error running WPMoo build command: {$e->getMessage()}</error>");
            return self::FAILURE;
        }

        // 3. Generate POT files.
        $output->writeln('> Generating POT files...');

        $pot_generator = new PotGenerator($this->config_manager);
        $pot_generator->generate($project, function ($type, $message) use ($output) {
            $output->writeln($message);
        });

        $output->writeln('<info>POT files updated successfully.</info>');

        // 4. Run checks.
        $output->writeln('> Running checks...');
        $this->run_process([ 'composer', 'check' ], $output);

        // 5. Update version numbers.
        $output->writeln("> Bumping version to {$new_version}...");
        $version_manager->update_version($project, $new_version, $output);

        // If --build-only is specified, create the distributable package and exit.
        if ($input->getOption('build-only')) {
            // 7. Create a clean build.
            $build_path = $this->get_cwd() . '/dist';
            $output->writeln("> Creating a clean build in {$build_path}...");
            if (is_dir($build_path)) {
                $this->run_process([ 'rm', '-rf', $build_path ], $output);
            }
            $this->run_process([ 'mkdir', $build_path ], $output);
            $this->run_shell_command_wrapper('git archive HEAD | tar -x -C ' . escapeshellarg($build_path), $output, true);

            // Explicitly copy composer.json as git archive might ignore it due to .gitattributes
            if (file_exists($this->get_cwd() . '/composer.json')) {
                copy($this->get_cwd() . '/composer.json', $build_path . '/composer.json');
            }

            // After git archive, copy built assets if they exist
            $assetsPath = $this->get_cwd() . '/assets';
            if (is_dir($assetsPath)) {
                $this->run_process([ 'cp', '-r', $assetsPath, $build_path . '/' ], $output);
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
            $output->writeln('The distributable package is located in the <info>dist</info> directory.');
            return self::SUCCESS;
        } else {
            // 6. Commit all changes.
            $output->writeln('> Committing all changes...');
            if (!$input->getOption('dry-run')) {
                $this->run_process([ 'git', 'add', '.' ], $output);
                $this->run_process([ 'git', 'commit', '-m', "chore(release): Build and bump version to {$new_version}" ], $output);
            } else {
                $output->writeln('<comment>DRY RUN: Skipping Git commit.</comment>');
            }

            // 7. Tag the release.
            $output->writeln("> Tagging release v{$new_version}...");
            if (!$input->getOption('dry-run')) {
                $this->run_process(['git', 'tag', 'v' . $new_version], $output);
            } else {
                $output->writeln('<comment>DRY RUN: Skipping Git tag.</comment>');
            }

            // 8. Push commit and tags to origin.
            $output->writeln('> Pushing Git commit and tags to origin...');
            if (! $input->getOption('dry-run')) {
                $this->run_process([ 'git', 'push', 'origin', 'HEAD', '--follow-tags' ], $output);
                $output->writeln('');
                $output->writeln('<success>Release preparation complete. Pushed to origin to trigger deployment workflow.</success>');
            } else {
                $output->writeln('<comment>DRY RUN: Skipping Git push.</comment>');
            }
        }

        return self::SUCCESS;
    }

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

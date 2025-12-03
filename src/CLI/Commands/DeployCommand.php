<?php

namespace WPMoo\CLI\Commands;

use WPMoo\CLI\Support\BaseCommand;
use WPMoo\CLI\Support\VersionManager;
use WPMoo\CLI\Support\PotGenerator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
    /**
     * @var string The local path for the SVN repository checkout.
     */
    private $svn_path;

    /**
     * @var string The SVN repository URL.
     */
    private $svn_url; // Will be set based on plugin name during initialization.

    protected function configure()
    {
        $this->setName('deploy')
            ->setDescription('Deploys the WPMoo plugin to the WordPress.org repository.')
            ->addOption('svn-url', null, InputOption::VALUE_OPTIONAL, 'The WordPress.org SVN repository URL.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Perform a dry run without committing to SVN.')
            ->addOption('build-only', null, InputOption::VALUE_NONE, 'Only build the distributable package in a `dist` folder and exit.');
    }

    public function handle_execute(InputInterface $input, OutputInterface $output): int
    {
        $project = $this->identify_project();
        if ($project['type'] !== 'wpmoo-framework' && $project['type'] !== 'wpmoo-plugin') {
            $output->writeln('<error>The deploy command can only be run from the root of a WPMoo framework project or a WPMoo-based plugin.</error>');
            return self::FAILURE;
        }

        $this->svn_path = sys_get_temp_dir() . '/wpmoo-svn';
        if ($input->getOption('svn-url')) {
            $this->svn_url = $input->getOption('svn-url');
        } else {
            // Set default SVN URL based on the plugin name if not provided
            $pluginName = basename($this->get_cwd());
            $this->svn_url = 'https://plugins.svn.wordpress.org/' . $pluginName . '/';
        }

        $output->writeln('<info>Preparing for deployment...</info>');

        $versionManager = new VersionManager($this);

        // 1. Get new version.
        $current_version = $versionManager->get_current_version($project);
        $output->writeln("<comment>Current version: {$current_version}</comment>");
        $new_version = $versionManager->interactive_version_selection($input, $output, $current_version);

        if (empty($new_version)) {
            $output->writeln('<comment>Operation cancelled.</comment>');
            return self::SUCCESS;
        }
        if (! $versionManager->is_valid_version($new_version)) {
            $output->writeln("<error>Invalid version format: {$new_version}</error>");
            return self::FAILURE;
        }

        $output->writeln("<comment>New version will be: {$new_version}</comment>");
        $confirmation = new ConfirmationQuestion("<question>Continue with operation for version {$new_version}? (y/N)</question> ", false);
        if (! $this->getHelper('question')->ask($input, $output, $confirmation)) {
            $output->writeln('<comment>Operation cancelled.</comment>');
            return self::SUCCESS;
        }

        // 2. Build assets.
        $output->writeln('> Building assets...');
        try {
            $this->run_process($this->get_build_command(), $output);
        } catch (ProcessFailedException $e) {
            throw $e;
        }

        // 3. Generate POT files.
        $output->writeln('> Generating POT files...');
        
        $cliRoot = dirname(__DIR__, 3);
        $potGenerator = new PotGenerator($this->get_cwd(), $cliRoot);
        $potGenerator->generate($project, function($type, $message) use ($output) {
            $output->writeln($message);
        });
        
        $output->writeln('<info>POT files updated successfully.</info>');

        // 4. Run checks.
        $output->writeln('> Running checks...');
        $this->run_process([ 'composer', 'check' ], $output);

        // 5. Update version numbers.
        $output->writeln("> Bumping version to {$new_version}...");
        $versionManager->update_version($project, $new_version, $output);

        // 6. Commit all changes.
        $output->writeln('> Committing all changes...');
        $this->run_process([ 'git', 'add', '.' ], $output);
        $this->run_process([ 'git', 'commit', '-m', "chore(release): Build and bump version to {$new_version}" ], $output);

        // 7. Create a clean build.
        $build_path = $this->get_cwd() . '/dist';
        $output->writeln("> Creating a clean build in {$build_path}...");
        if (is_dir($build_path)) {
            $this->run_process([ 'rm', '-rf', $build_path ], $output);
        }
        $this->run_process([ 'mkdir', $build_path ], $output);
        $this->run_shell_command_wrapper('git archive HEAD | tar -x -C ' . escapeshellarg($build_path), $output, true);

        // After git archive, copy built assets if they exist (e.g. from build processes)
        $assetsPath = $this->get_cwd() . '/assets';
        if (is_dir($assetsPath)) {
            $this->run_process([ 'cp', '-r', $assetsPath, $build_path . '/' ], $output);
        }

        // For WPMoo-based plugins, include the WPMoo framework separately to avoid dependency conflicts
        if ($project['type'] === 'wpmoo-plugin') {
            $output->writeln('> Including WPMoo framework separately for WPMoo-based plugin...');

            // Create the wpmoo-core directory in the build
            $wpmooCorePath = $build_path . '/wpmoo-core';
            $this->run_process([ 'mkdir', '-p', $wpmooCorePath ], $output);

            // Copy the WPMoo framework to the separate directory
            $sourceWpMooPath = $this->get_cwd() . '/vendor/wpmoo/wpmoo';
            if (is_dir($sourceWpMooPath)) {
                // Copy only the src and necessary files from the WPMoo framework
                $srcPath = $sourceWpMooPath . '/src';
                if (is_dir($srcPath)) {
                    $this->run_process([ 'cp', '-r', $srcPath, $wpmooCorePath . '/' ], $output);
                }

                $licensePath = $sourceWpMooPath . '/LICENSE';
                if (file_exists($licensePath)) {
                    $this->run_process([ 'cp', $licensePath, $wpmooCorePath . '/' ], $output);
                }

                $mainFrameworkFile = $sourceWpMooPath . '/wpmoo.php';
                if (file_exists($mainFrameworkFile)) {
                    $this->run_process([ 'cp', $mainFrameworkFile, $wpmooCorePath . '/' ], $output);
                }
            }

            // For the plugin itself, we only need its own autoloader dependencies (not framework ones)
            // We'll create a minimal vendor directory with only truly necessary dependencies
            $vendorPath = $build_path . '/vendor';
            $this->run_process([ 'mkdir', '-p', $vendorPath ], $output);

            // Copy only the autoloader files that the plugin itself needs
            $sourceAutoloadPath = $this->get_cwd() . '/vendor/autoload.php';
            if (file_exists($sourceAutoloadPath)) {
                $this->run_process([ 'cp', $sourceAutoloadPath, $vendorPath . '/' ], $output);
            }

            // Copy the composer directory with autoloader files
            $sourceComposerPath = $this->get_cwd() . '/vendor/composer';
            if (is_dir($sourceComposerPath)) {
                $this->run_process([ 'cp', '-r', $sourceComposerPath, $vendorPath . '/' ], $output);
            }
        }

        if (! $input->getOption('build-only')) {
            // 8. Handle SVN.
            $this->handle_svn($output);

            // 9. Copy files to trunk.
            $output->writeln('> Copying files to SVN trunk...');
            $this->rsync_wrapper("{$build_path}/", "{$this->svn_path}/trunk/", $output);

            // 10. Add/remove files in SVN.
            $output->writeln('> Staging SVN changes...');
            $this->svn_status();

            // 11. Commit to SVN trunk.
            if ($input->getOption('dry-run')) {
                $output->writeln('<comment>DRY RUN: Skipping SVN trunk commit.</comment>');
                $this->run_process([ 'svn', 'status' ], $output, false, $this->svn_path);
            } else {
                $commitQuestion = new ConfirmationQuestion('<question>Commit changes to SVN trunk? (y/N)</question> ', false);
                if ($this->getHelper('question')->ask($input, $output, $commitQuestion)) {
                    $this->run_process([ 'svn', 'commit', '-m', "Release version {$new_version}" ], $output, false, $this->svn_path);
                }
            }

            // 12. Create SVN tag.
            $output->writeln("> Handling SVN tag for version {$new_version}...");
            $this->svn_tag($new_version, $output, $input->getOption('dry-run'));

            // 13. Git push commit and tags.
            $output->writeln('> Pushing Git commit and tags...');
            if (! $input->getOption('dry-run')) {
                $this->run_process([ 'git', 'push', '--follow-tags' ], $output);
            }

            // 14. Cleanup.
            $output->writeln('> Cleaning up build files...');
            $this->run_process([ 'rm', '-rf', $build_path ], $output);

            $output->writeln('<info>Deployment process finished!</info>');
        } else {
            $output->writeln('');
            $output->writeln('<success>Build complete!</success>');
            $output->writeln('The distributable plugin is located in the <info>dist</info> directory.');
        }

        return self::SUCCESS;
    }

    private function run_process(array $command, OutputInterface $output, bool $quiet = false, ?string $cwd = null)
    {
        $process = new Process($command, $cwd);
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

    private function handle_svn(OutputInterface $output)
    {
        if (is_dir($this->svn_path)) {
            $output->writeln('> Updating existing SVN checkout...');
            $this->run_process([ 'svn', 'up' ], $output, false, $this->svn_path);
        } else {
            $output->writeln("> Checking out SVN repository to {$this->svn_path}...");
            $this->run_process([ 'svn', 'co', $this->svn_url, $this->svn_path ], $output);
        }
    }

    private function rsync_wrapper(string $source_path, string $destination_path, OutputInterface $output)
    {
        $command = [ 'rsync', '-r', '--delete', $source_path, $destination_path ];
        $this->run_process($command, $output);
    }

    private function svn_status()
    {
        $svn_process = new Process([ 'svn', 'status' ], $this->svn_path);
        $svn_process->run();
        $status_output = $svn_process->getOutput();

        foreach (explode("\n", $status_output) as $status_line) {
            if (empty($status_line)) {
                continue;
            }
            $line_parts = preg_split('/\s+/', $status_line);
            $action = $line_parts[0];
            $file = $line_parts[1];

            if ($action === '?') { // Not under version control.
                ( new Process([ 'svn', 'add', $file ], $this->svn_path) )->mustRun();
            } elseif ($action === '!') { // Missing
                ( new Process([ 'svn', 'rm', '--force', $file ], $this->svn_path) )->mustRun();
            }
        }
    }

    private function svn_tag(string $version_string, OutputInterface $output, bool $is_dry_run)
    {
        $tag_path = "{$this->svn_path}/tags/{$version_string}";
        if (is_dir($tag_path)) {
            $output->writeln("<comment>SVN tag for version {$version_string} already exists.</comment>");
            return;
        }

        $output->writeln("> Creating SVN tag for version {$version_string}...");
        $this->run_process([ 'svn', 'copy', "{$this->svn_path}/trunk", $tag_path ], $output);

        if ($is_dry_run) {
            $output->writeln('<comment>DRY RUN: Skipping SVN tag commit.</comment>');
        } else {
            $this->run_process([ 'svn', 'commit', '-m', "Tagging version {$version_string}" ], $output, false, $this->svn_path);
        }
    }

    private function get_build_command(): array
    {
        $cwd = $this->get_cwd();
        if (file_exists($cwd . '/yarn.lock')) {
            return [ 'yarn', 'build' ];
        }
        if (file_exists($cwd . '/pnpm-lock.yaml')) {
            return [ 'pnpm', 'build' ];
        }
        if (file_exists($cwd . '/bun.lockb')) {
            return [ 'bun', 'run', 'build' ];
        }
        return [ 'npm', 'run', 'build' ];
    }
}

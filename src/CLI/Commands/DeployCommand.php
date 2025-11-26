<?php

/**
 * Deploy command for the WPMoo CLI.
 *
 * Handles the deployment process of the WPMoo framework to the WordPress.org SVN repository.
 *
 * @package WPMoo\CLI\Commands
 * @since 0.1.0
 */

namespace WPMoo\CLI\Commands;

use WPMoo\CLI\Support\BaseCommand;
use WPMoo\CLI\Support\VersionManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Process\Process;

class DeployCommand extends BaseCommand
{
    /**
     * @var string The local path for the SVN repository checkout.
     */
    private $svn_path;

    /**
     * @var string The SVN repository URL.
     */
    private $svn_url = 'https://plugins.svn.wordpress.org/wpmoo/'; // This is a placeholder

    protected function configure()
    {
        $this->setName('deploy')
            ->setDescription('Deploys the WPMoo plugin to the WordPress.org repository.')
            ->addOption('svn-url', null, InputOption::VALUE_OPTIONAL, 'The WordPress.org SVN repository URL.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Perform a dry run without committing to SVN.')
            ->addOption('build-only', null, InputOption::VALUE_NONE, 'Only build the distributable package in a `dist` folder and exit.');
    }

    public function handleExecute(InputInterface $input, OutputInterface $output): int
    {
        $project = $this->identifyProject();
        if ($project['type'] !== 'wpmoo-framework') {
            $output->writeln('<error>The deploy command can only be run from the root of the WPMoo framework project.</error>');
            return self::FAILURE;
        }

        $this->svn_path = sys_get_temp_dir() . '/wpmoo-svn';
        if ($input->getOption('svn-url')) {
            $this->svn_url = $input->getOption('svn-url');
        }

        $output->writeln('<info>Preparing for deployment...</info>');

        $versionManager = new VersionManager($this);

        // 1. Get new version
        $current_version = $versionManager->getCurrentVersion($project);
        $output->writeln("<comment>Current version: {$current_version}</comment>");

        $new_version = $versionManager->interactiveVersionSelection($input, $output, $current_version);

        if (empty($new_version)) {
            $output->writeln('<comment>Operation cancelled.</comment>');
            return self::SUCCESS;
        }

        if (!$versionManager->isValidVersion($new_version)) {
            $output->writeln("<error>Invalid version format: {$new_version}</error>");
            return self::FAILURE;
        }

        $output->writeln("<comment>New version will be: {$new_version}</comment>");

        $confirmation = new ConfirmationQuestion("<question>Continue with operation for version {$new_version}? (y/N)</question> ", false);
        if (!$this->getHelper('question')->ask($input, $output, $confirmation)) {
            $output->writeln('<comment>Operation cancelled.</comment>');
            return self::SUCCESS;
        }

        // 2. Run checks
        $output->writeln('> Running checks...');
        $this->runProcess(['composer', 'check'], $output);

        // 3. Update version numbers in working directory
        $output->writeln("> Bumping version to {$new_version}...");
        $versionManager->updateVersion($project, $new_version, $output);

        // 4. Commit the version bump
        $output->writeln('> Committing version bump...');
        $files_to_add = [];
        if (isset($project['main_file']) && file_exists($project['main_file'])) {
            $files_to_add[] = $project['main_file'];
        }
        if (isset($project['readme_file']) && file_exists($project['readme_file'])) {
            $files_to_add[] = $project['readme_file'];
        }

        if (!empty($files_to_add)) {
            $this->runProcess(array_merge(['git', 'add'], $files_to_add), $output);
            $this->runProcess(['git', 'commit', '-m', "chore(release): Bump version to {$new_version}"], $output);
        } else {
            $output->writeln('<comment>No files found to commit for version bump.</comment>');
        }

        // 5. Create a clean build in the 'dist' directory
        $build_path = $this->getCwd() . '/dist';
        $output->writeln("> Creating a clean build in {$build_path}...");
        if (is_dir($build_path)) {
            $this->runProcess(['rm', '-rf', $build_path], $output);
        }
        $this->runProcess(['mkdir', $build_path], $output);

        $this->runShellCommand('git archive HEAD | tar -x -C ' . escapeshellarg($build_path), $output, true);


        if (!$input->getOption('build-only')) {
            // 6. Handle SVN
            $this->handleSvn($output);

            // 7. Copy files to trunk
            $output->writeln('> Copying files to SVN trunk...');
            $this->rsync("{$build_path}/", "{$this->svn_path}/trunk/", $output);

            // 8. Add/remove files in SVN
            $output->writeln('> Staging SVN changes...');
            $this->svnStatus();

            // 9. Commit to SVN trunk
            if ($input->getOption('dry-run')) {
                $output->writeln('<comment>DRY RUN: Skipping SVN trunk commit.</comment>');
                $this->runProcess(['svn', 'status'], $output, false, $this->svn_path);
            } else {
                $commitQuestion = new ConfirmationQuestion('<question>Commit changes to SVN trunk? (y/N)</question> ', false);
                if ($this->getHelper('question')->ask($input, $output, $commitQuestion)) {
                    $this->runProcess(['svn', 'commit', '-m', "Release version {$new_version}"], $output, false, $this->svn_path);
                }
            }

            // 10. Create SVN tag
            $output->writeln("> Handling SVN tag for version {$new_version}...");
            $this->svnTag($new_version, $output, $input->getOption('dry-run'));

            // 11. Git push commit and tags
            $output->writeln("> Pushing Git commit and tags...");
            if (!$input->getOption('dry-run')) {
                $this->runProcess(['git', 'tag', "-a", "v{$new_version}", "-m", "Version {$new_version}"], $output);
                $this->runProcess(['git', 'push', '--follow-tags'], $output);
            }

            // 12. Cleanup
            $output->writeln('> Cleaning up build files...');
            $this->runProcess(['rm', '-rf', $build_path], $output);

            $output->writeln('<info>Deployment process finished!</info>');
        } else {
            $output->writeln('');
            $output->writeln("<success>Build complete!</success>");
            $output->writeln("The distributable plugin is located in the <info>dist</info> directory.");
        }

        return self::SUCCESS;
    }

    private function runProcess(array $command, OutputInterface $output, bool $quiet = false, ?string $cwd = null)
    {
        $process = new Process($command, $cwd);
        $process->mustRun(function ($type, $buffer) use ($output, $quiet) {
            if (!$quiet) {
                $output->write($buffer);
            }
        });
    }

    private function runShellCommand(string $command, OutputInterface $output, bool $quiet = false, ?string $cwd = null)
    {
        $process = Process::fromShellCommandline($command, $cwd);
        $process->mustRun(function ($type, $buffer) use ($output, $quiet) {
            if (!$quiet) {
                $output->write($buffer);
            }
        });
    }

    private function handleSvn(OutputInterface $output)
    {
        if (is_dir($this->svn_path)) {
            $output->writeln('> Updating existing SVN checkout...');
            $this->runProcess(['svn', 'up'], $output, false, $this->svn_path);
        } else {
            $output->writeln("> Checking out SVN repository to {$this->svn_path}...");
            $this->runProcess(['svn', 'co', $this->svn_url, $this->svn_path], $output);
        }
    }

    private function rsync(string $source, string $destination, OutputInterface $output)
    {
        $command = ['rsync', '-r', '--delete', $source, $destination];
        $this->runProcess($command, $output);
    }

    private function svnStatus()
    {
        $process = new Process(['svn', 'status'], $this->svn_path);
        $process->run();
        $statusOutput = $process->getOutput();

        foreach (explode("\n", $statusOutput) as $line) {
            if (empty($line)) {
                continue;
            }
            $parts = preg_split('/\s+/', $line);
            $action = $parts[0];
            $file = $parts[1];

            if ($action === '?') { // Not under version control
                (new Process(['svn', 'add', $file], $this->svn_path))->mustRun();
            } elseif ($action === '!') { // Missing
                (new Process(['svn', 'rm', '--force', $file], $this->svn_path))->mustRun();
            }
        }
    }

    private function svnTag(string $version, OutputInterface $output, bool $isDryRun)
    {
        $tagPath = "{$this->svn_path}/tags/{$version}";
        if (is_dir($tagPath)) {
            $output->writeln("<comment>SVN tag for version {$version} already exists.</comment>");
            return;
        }

        $output->writeln("> Creating SVN tag for version {$version}...");
        $this->runProcess(['svn', 'copy', "{$this->svn_path}/trunk", $tagPath], $output);

        if ($isDryRun) {
            $output->writeln("<comment>DRY RUN: Skipping SVN tag commit.</comment>");
        } else {
            $this->runProcess(['svn', 'commit', '-m', "Tagging version {$version}"], $output, false, $this->svn_path);
        }
    }
}

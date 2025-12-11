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
 * Prepares and tags a new release for deployment via CI/CD.
 *
 * @package WPMoo\CLI\Commands
 * @since 0.1.0
 */
class DeployReleaseCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('deploy:release')
            ->setDescription('Prepares and tags a new release for deployment via CI/CD.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Perform a dry run without committing or tagging.');
    }

    public function handle_execute(InputInterface $input, OutputInterface $output): int
    {
        $project = $this->identify_project();
        if ($project['type'] !== 'wpmoo-framework' && $project['type'] !== 'wpmoo-plugin') {
            $output->writeln('<error>This command can only be run from the root of a WPMoo framework or plugin project.</error>');
            return self::FAILURE;
        }

        $output->writeln('<info>Preparing a new release...</info>');

        $version_manager = new VersionManager($this);

        // 1. Get new version.
        $current_version = $version_manager->get_current_version($project);
        $output->writeln("<comment>Current version: {$current_version}</comment>");
        $new_version = $version_manager->interactive_version_selection($input, $output, $current_version);

        if (empty($new_version)) {
            $output->writeln('<comment>Operation cancelled.</comment>');
            return self::SUCCESS;
        }
        if ($new_version === $current_version) {
            $output->writeln("<error>The new version ({$new_version}) cannot be the same as the current version ({$current_version}).</error>");
            return self::FAILURE;
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

        // 2. Build assets.
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

        return self::SUCCESS;
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
}

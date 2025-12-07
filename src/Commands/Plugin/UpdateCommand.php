<?php

namespace WPMoo\CLI\Commands\Plugin;

use WPMoo\CLI\Support\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Update command for WPMoo projects.
 *
 * Updates the WPMoo framework and re-scopes it.
 *
 * @package WPMoo\CLI\Commands\Framework
 * @since 0.1.0
 */
class UpdateCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('update')
            ->setDescription('Updates the WPMoo framework and re-scopes it for the project.');
    }

    public function handle_execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('WPMoo Framework Updater');

        $project = $this->identify_project();
        if ($project['type'] !== 'wpmoo-plugin' && $project['type'] !== 'wpmoo-theme') {
            $io->error('The "update" command can only be used inside a WPMoo-based plugin or theme.');
            return self::FAILURE;
        }

        // 1. Run composer update
        $io->section('Running composer update for wpmoo/wpmoo...');
        $composer_process = new Process(['composer', 'update', 'wpmoo/wpmoo']);
        $composer_process->run(function ($type, $buffer) use ($io) {
            $io->write($buffer);
        });

        if (!$composer_process->isSuccessful()) {
            $io->error('`composer update wpmoo/wpmoo` failed. Please check the output above.');
            return self::FAILURE;
        }
        $io->success('Composer update complete.');


        // 2. Copy framework
        $io->section('Copying updated framework files...');
        $project_root = $this->get_cwd();
        $source_dir = $project_root . '/vendor/wpmoo/wpmoo/framework';
        $dest_dir = $project_root . '/framework';

        if (!is_dir($source_dir)) {
            $io->error('Source framework directory not found in `vendor/wpmoo/wpmoo/framework`.');
            return self::FAILURE;
        }

        $filesystem = new Filesystem();
        if ($filesystem->exists($dest_dir)) {
            $filesystem->remove($dest_dir);
            $io->note('Removed existing framework directory.');
        }
        $filesystem->mirror($source_dir, $dest_dir);
        $io->success('Framework files copied successfully.');


        // 3. Run scope command
        $io->section('Scoping the new framework files...');
        try {
            $application = $this->getApplication();
            if (!$application) {
                $io->error('Application instance not found to run scope command.');
                return self::FAILURE;
            }
            $scope_command = $application->find('scope');
            $scope_input = new ArrayInput([]);
            $return_code = $scope_command->run($scope_input, $output);

            if ($return_code !== self::SUCCESS) {
                $io->error('`scope` command failed.');
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error("An error occurred while running the scope command: {$e->getMessage()}");
            return self::FAILURE;
        }

        $io->success('Framework update and scoping complete!');

        return self::SUCCESS;
    }
}

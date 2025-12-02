<?php

namespace WPMoo\CLI\Commands;

use WPMoo\CLI\Support\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Update command for the WPMoo CLI.
 *
 * Handles updating the WPMoo plugin with build processes and framework sync.
 *
 * @package WPMoo\CLI\Commands
 * @since 0.1.0
 * @link  https://wpmoo.org WPMoo – WordPress Micro Object-Oriented Framework.
 * @link  https://github.com/wpmoo/wpmoo GitHub Repository.
 * @license https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 */

class UpdateCommand extends BaseCommand
{
    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this->setName('update')
            ->setDescription('Update the WPMoo plugin with build processes and framework sync')
            ->setHelp('This command runs build processes, updates translations, and synchronizes the framework directory.');
    }

    /**
     * Execute the command.
     *
     * @param InputInterface $input Command input.
     * @param OutputInterface $output Command output.
     * @return int Exit status (0 for success, non-zero for failure).
     */
    public function handle_execute(InputInterface $input, OutputInterface $output): int
    {
        $symfony_io = new SymfonyStyle($input, $output);

        $symfony_io->title('WPMoo Plugin Update');

        $project_info = $this->identify_project();
        if ($project_info['type'] !== 'wpmoo-plugin') {
                $symfony_io->error('The "update" command can only be run from inside a WPMoo-based plugin.');
                return 1;
        }

        $symfony_io->section('Starting Update Process');

        // 1. Build assets with Gulp
        $symfony_io->writeln('> Building assets with Gulp...');
        if ($this->run_gulp_build($output)) {
            $symfony_io->success('Assets built successfully.');
        } else {
            $symfony_io->error('Asset building failed.');
            return 1;
        }

        // 2. Update translations
        $symfony_io->writeln('> Generating .pot file...');
        if ($this->run_pot_generation($output)) {
            $symfony_io->success('Translations updated successfully.');
        } else {
            $symfony_io->error('Translation generation failed.');
            return 1;
        }

        // 3. Copy framework files
        $symfony_io->writeln('> Copying WPMoo framework to framework directory...');
        if ($this->copy_framework_files($output)) {
            $symfony_io->success('WPMoo framework copied successfully.');
        } else {
            $symfony_io->error('Framework copying failed.');
            return 1;
        }

        $symfony_io->success('Update process completed successfully!');
        return 0;
    }

    /**
     * Run gulp build command.
     *
     * @param OutputInterface $output The output interface.
     * @return bool True on success, false on failure.
     */
    private function run_gulp_build(OutputInterface $output): bool
    {
        try {
            $process = new Process([ 'gulp', 'build' ]);
            $process->setTimeout(300); // 5 minutes timeout
            $process->mustRun();

            $output->writeln($process->getOutput());

            return true;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return false;
        }
    }

    /**
     * Run POT file generation command.
     *
     * @param OutputInterface $output The output interface.
     * @return bool True on success, false on failure.
     */
    private function run_pot_generation(OutputInterface $output): bool
    {
        try {
            // Using the PotGenerator class from the CLI support
            $pot_generator = new \WPMoo\CLI\Support\PotGenerator();
            $source_path = $this->get_cwd() . '/src';
            $output_path = $this->get_cwd() . '/languages/wpmoo.pot';
            $exclude = [ 'samples', 'vendor', 'node_modules' ];

            // The method generate has been renamed to generate_pot_file.
            $result = $pot_generator->generate_pot_file($source_path, $output_path, $exclude);

            return $result;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return false;
        }
    }

    /**
     * Copy WPMoo framework files to framework directory.
     *
     * @param OutputInterface $output The output interface.
     * @return bool True on success, false on failure.
     */
    private function copy_framework_files(OutputInterface $output): bool
    {
        try {
            // In the CLI context, we need to get the current working directory properly
            $current_working_directory = getcwd();
            $source_directory = $current_working_directory . '/vendor/wpmoo/wpmoo/src';
            $destination_directory = $current_working_directory . '/framework';

            if (! is_dir($source_directory)) {
                throw new \Exception("WPMoo source directory does not exist: {$source_directory}");
            }

            // Remove destination if it exists
            if (is_dir($destination_directory)) {
                $filesystem = new Filesystem();
                $filesystem->remove($destination_directory);
            }

            // Create destination directory
            if (! mkdir($destination_directory, 0755, true) && ! is_dir($destination_directory)) {
                throw new \Exception("Cannot create destination directory: {$destination_directory}");
            }

            // Use Symfony filesystem component for more reliable directory copying
            $filesystem = new Filesystem();
            $filesystem->mirror($source_directory, $destination_directory . '/src');

            // Copy the samples directory (needed for demo functionality when framework is loaded as plugin)
            $samples_source = $current_working_directory . '/vendor/wpmoo/wpmoo/samples';
            if (is_dir($samples_source)) {
                $filesystem->mirror($samples_source, $destination_directory . '/samples');
            }

            // Copy the LICENSE file
            $license_source = $current_working_directory . '/vendor/wpmoo/wpmoo/LICENSE';
            $license_destination = $destination_directory . '/LICENSE';
            if (file_exists($license_source)) {
                $filesystem->copy($license_source, $license_destination);
            }

            return true;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return false;
        }
    }
}

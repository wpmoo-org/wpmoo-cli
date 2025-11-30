<?php

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

namespace WPMoo\CLI\Commands;

use WPMoo\CLI\Support\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Update command to refresh the plugin with build processes.
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
    public function handleExecute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('WPMoo Plugin Update');

        $projectInfo = $this->identifyProject();
        if ($projectInfo['type'] !== 'wpmoo-plugin') {
            $io->error('The "update" command can only be run from inside a WPMoo-based plugin.');
            return 1;
        }

        $io->section('Starting Update Process');

        // 1. Build assets with Gulp
        $io->writeln('> Building assets with Gulp...');
        if ($this->runGulpBuild($output)) {
            $io->success('Assets built successfully.');
        } else {
            $io->error('Asset building failed.');
            return 1;
        }

        // 2. Update translations
        $io->writeln('> Generating .pot file...');
        if ($this->runPotGeneration($output)) {
            $io->success('Translations updated successfully.');
        } else {
            $io->error('Translation generation failed.');
            return 1;
        }

        // 3. Copy framework files
        $io->writeln('> Copying WPMoo framework to framework directory...');
        if ($this->copyFrameworkFiles($output)) {
            $io->success('WPMoo framework copied successfully.');
        } else {
            $io->error('Framework copying failed.');
            return 1;
        }

        $io->success('Update process completed successfully!');
        return 0;
    }

    /**
     * Run gulp build command.
     *
     * @param OutputInterface $output The output interface.
     * @return bool True on success, false on failure.
     */
    private function runGulpBuild(OutputInterface $output): bool
    {
        try {
            $process = new Process(['gulp', 'build']);
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
    private function runPotGeneration(OutputInterface $output): bool
    {
        try {
            // Using the PotGenerator class from the CLI support
            $potGenerator = new \WPMoo\CLI\Support\PotGenerator();
            $sourcePath = $this->getCwd() . '/src';
            $outputPath = $this->getCwd() . '/languages/wpmoo.pot';
            $exclude = ['samples', 'vendor', 'node_modules'];

            $result = $potGenerator->generate($sourcePath, $outputPath, $exclude);

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
    private function copyFrameworkFiles(OutputInterface $output): bool
    {
        try {
            // In the CLI context, we need to get the current working directory properly
            $cwd = getcwd();
            $sourceDir = $cwd . '/vendor/wpmoo/wpmoo/src';
            $destDir = $cwd . '/framework';

            if (!is_dir($sourceDir)) {
                throw new \Exception("WPMoo source directory does not exist: {$sourceDir}");
            }

            // Remove destination if it exists
            if (is_dir($destDir)) {
                $this->removeDirectoryRecursively($destDir);
            }

            // Create destination directory
            if (!mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                throw new \Exception("Cannot create destination directory: {$destDir}");
            }

            // Use Symfony filesystem component for more reliable directory copying
            $fs = new Filesystem();
            $fs->mirror($sourceDir, $destDir . '/src');

            // Copy the samples directory (needed for demo functionality when framework is loaded as plugin)
            $samplesSource = $cwd . '/vendor/wpmoo/wpmoo/samples';
            if (is_dir($samplesSource)) {
                $fs->mirror($samplesSource, $destDir . '/samples');
            }

            // Copy the LICENSE file
            $licenseSource = $cwd . '/vendor/wpmoo/wpmoo/LICENSE';
            $licenseDest = $destDir . '/LICENSE';
            if (file_exists($licenseSource)) {
                $fs->copy($licenseSource, $licenseDest);
            }

            return true;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return false;
        }
    }

    /**
     * Recursively copy a directory.
     *
     * @param string $source Source directory
     * @param string $dest Destination directory
     * @return void
     */
    private function copyDirectoryRecursively(string $source, string $dest): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            $destPath = $dest . '/' . $iterator->getSubPathname();

            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                copy($item->getPathname(), $destPath);
            }
        }
    }

    /**
     * Recursively remove a directory.
     *
     * @param string $dir Directory to remove
     * @return void
     */
    private function removeDirectoryRecursively(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}

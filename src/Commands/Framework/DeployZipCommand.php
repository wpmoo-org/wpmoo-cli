<?php

namespace WPMoo\CLI\Commands\Framework;

use WPMoo\CLI\Support\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use WPMoo\CLI\Support\VersionManager;

/**
 * Creates a zip archive of the distributable package.
 *
 * @package WPMoo\CLI\Commands
 * @since 0.1.0
 */
class DeployZipCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('deploy:zip')
            ->setDescription('Creates a zip archive of the distributable package.');
    }

    public function handle_execute(InputInterface $input, OutputInterface $output): int
    {
        $project = $this->identify_project();
        if ($project['type'] !== 'wpmoo-framework' && $project['type'] !== 'wpmoo-plugin') {
            $output->writeln('<error>This command can only be run from the root of a WPMoo framework or plugin project.</error>');
            return self::FAILURE;
        }

        $output->writeln('<info>Creating a distributable zip package...</info>');

        // 1. Run deploy:dist to create the dist folder.
        $application = $this->getApplication();
        if (! $application) {
            $output->writeln('<error>Application instance not found.</error>');
            return self::FAILURE;
        }

        try {
            $distCommand = $application->find('deploy:dist');
            $distInput = new ArrayInput([]);
            $distReturnCode = $distCommand->run($distInput, $output);

            if ($distReturnCode !== self::SUCCESS) {
                $output->writeln('<error>Distribution build failed. Aborting zip creation.</error>');
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $output->writeln("<error>Error running deploy:dist command: {$e->getMessage()}</error>");
            return self::FAILURE;
        }

        // 2. Zip the contents of the dist directory.
        $dist_path = $this->get_cwd() . '/dist';
        if (! is_dir($dist_path)) {
            $output->writeln('<error>Dist directory not found.</error>');
            return self::FAILURE;
        }

        // Determine Slug
        $project_slug = basename($this->get_cwd());
        if (file_exists($this->get_cwd() . '/composer.json')) {
            $composer_data = json_decode(file_get_contents($this->get_cwd() . '/composer.json'), true);
            if (isset($composer_data['name'])) {
                $parts = explode('/', $composer_data['name']);
                $project_slug = end($parts);
            }
        }

        // Determine Version
        $version_manager = new VersionManager($this);
        $version = $version_manager->get_current_version($project);
        
        $zip_filename = "{$project_slug}-{$version}.zip";

        $output->writeln("> Zipping files into {$zip_filename}...");

        $zip = new ZipArchive();
        if ($zip->open($this->get_cwd() . '/' . $zip_filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $output->writeln('<error>Failed to create zip file.</error>');
            return self::FAILURE;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dist_path),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            // Skip directories (they would be added automatically)
            if (!$file->isDir()) {
                // Get real and relative path for current file
                $filePath = $file->getRealPath();
                
                // Relative path inside dist (e.g. "slug/plugin.php")
                $relativePath = substr($filePath, strlen($dist_path) + 1);

                // Add file to zip archive using relative path
                // This ensures the zip structure matches dist structure (slug/...)
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();

        $output->writeln("<success>Zip package created: {$zip_filename}</success>");

        return self::SUCCESS;
    }
}

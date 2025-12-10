<?php

namespace WPMoo\CLI\Support;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use WPMoo\CLI\Support\Filesystem;

/**
 * Helper to manage the internal Node.js environment for the CLI.
 *
 * @package WPMoo\CLI\Support
 * @since 0.1.0
 */
class NodeEnvironment
{
    /**
     * @var Filesystem
     */
    private Filesystem $filesystem;

    /**
     * NodeEnvironment constructor.
     *
     * @param Filesystem|null $filesystem Optional filesystem instance.
     */
    public function __construct(?Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?? new Filesystem();
    }

    /**
     * Ensures that Node.js dependencies are installed in the Project directory.
     *
     * @param SymfonyStyle $io Output style interface.
     * @param string $project_root The root directory of the project.
     * @return bool True if environment is ready, false on failure.
     */
    public function ensure_dependencies(SymfonyStyle $io, string $project_root): bool
    {
        // 1. Check if critical dependencies exist (e.g. sass) in the project root.
        if ($this->filesystem->file_exists($project_root . '/node_modules/sass')) {
            return true;
        }

        // 2. Check if package.json exists.
        if (!$this->filesystem->file_exists($project_root . '/package.json')) {
            $io->error('No `package.json` found in project root. Run `npm init` or use `create-wpmoo` to set up a new project.');
            return false;
        }

        // 3. Prompt user to install dependencies.
        $io->warning('Node.js dependencies are missing or incomplete in the project root.');
        if ($io->confirm('Do you want to run `npm install` now?', true)) {
            $io->text('> Running `npm install`...');

            $process = new Process(['npm', 'install'], $project_root);
            $process->setTimeout(300); // 5 minutes timeout

            try {
                $process->mustRun(function ($type, $buffer) use ($io) {
                    $io->write($buffer);
                });
                $io->success('Node.js dependencies installed successfully.');
                return true; // Dependencies are now installed, proceed with the build.
            } catch (ProcessFailedException $e) {
                $io->error('`npm install` failed. Please run it manually to see the error.');
                $io->writeln($e->getMessage());
                return false;
            }
        } else {
            $io->text('Please run `npm install` manually before proceeding.');
            return false; // User chose not to install.
        }
    }
}

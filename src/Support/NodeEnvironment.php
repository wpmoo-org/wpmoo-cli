<?php

namespace WPMoo\CLI\Support;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
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
     * @var string The path to the CLI's root directory.
     */
    private string $cli_root;

    /**
     * NodeEnvironment constructor.
     *
     * @param Filesystem|null $filesystem Optional filesystem instance.
     */
    public function __construct(?Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?? new Filesystem();
        $this->cli_root = dirname(__DIR__, 2); // Points to wpmoo-cli root (containing package.json)
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
            $io->error('No `package.json` found in project root. Run `npm init` or `npm create wpmoo` to set up.');
            return false;
        }

        // 3. Prompt user to install dependencies.
        $io->warning('Node.js dependencies are missing in the project root.');
        $io->text('Please run the following command to install them:');
        $io->listing(['npm install']);

        return false;
    }
}

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
     * Ensures that Node.js dependencies are installed in the CLI directory.
     *
     * @param SymfonyStyle $io Output style interface.
     * @return bool True if environment is ready, false on failure.
     */
    public function ensure_dependencies(SymfonyStyle $io): bool
    {
        // 1. Check if node_modules exists.
        if ($this->filesystem->file_exists($this->cli_root . '/node_modules')) {
            return true;
        }

        // 2. Check if package.json exists (sanity check).
        if (!$this->filesystem->file_exists($this->cli_root . '/package.json')) {
            $io->error('WPMoo CLI internal error: package.json not found in ' . $this->cli_root);
            return false;
        }

        // 3. Attempt to install dependencies.
        $io->note('Installing internal WPMoo CLI build dependencies (one-time setup)...');

        $process = new Process(['npm', 'install', '--production', '--no-audit', '--no-fund'], $this->cli_root);
        $process->setTimeout(600); // 10 minutes

        try {
            $process->mustRun(function ($type, $buffer) use ($io) {
                if (Process::ERR === $type) {
                    // Optional: suppress npm warnings if desired, or show verbose output.
                    // $io->text($buffer); 
                } else {
                    // $io->text($buffer);
                }
            });
            
            $io->success('Dependencies installed successfully.');
            return true;

        } catch (\Exception $e) {
            $io->error('Failed to install internal dependencies.');
            $io->text($e->getMessage());
            return false;
        }
    }
}

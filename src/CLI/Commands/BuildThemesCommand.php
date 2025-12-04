<?php

namespace WPMoo\CLI\Commands;

use WPMoo\CLI\Support\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Build Themes command for WPMoo.
 *
 * Compiles SCSS themes using the Node.js build script.
 *
 * @package WPMoo\CLI\Commands
 * @since 0.1.0
 */
class BuildThemesCommand extends BaseCommand
{
    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this->setName('build:themes')
            ->setDescription('Compile WPMoo themes (SCSS to CSS)')
            ->setHelp('This command compiles the WPMoo SCSS themes into CSS assets using the bundled Node.js script.');
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
        $io = new SymfonyStyle($input, $output);
        $io->title('WPMoo Theme Builder');

        // 1. Check for Node.js
        $process = new Process(['node', '-v']);
        $process->run();

        if (!$process->isSuccessful()) {
            $io->error('Node.js is required to build themes but was not found in your PATH.');
            return 1;
        }

        // 2. Locate the build script
        // We assume we are running from src/CLI/Commands
        // Script is in scripts/build-themes.js (relative to project root)
        $script_path = dirname(__DIR__, 3) . '/scripts/build-themes.js';

        if (!file_exists($script_path)) {
            $io->error("Build script not found at: {$script_path}");
            return 1;
        }

        // 3. Determine Target Directory
        // For now, we assume the current working directory is the target
        $target_dir = getcwd();
        
        $io->text("Target: " . $target_dir);
        $io->text("Script: " . $script_path);
        $io->newLine();

        // 4. Run the script
        $command = ['node', $script_path, $target_dir];

        $process = new Process($command);
        $process->setTimeout(300); // 5 minutes timeout

        // Stream output to console
        $process->run(function ($type, $buffer) use ($io) {
            if (Process::ERR === $type) {
                // Determine if it's a real error or just stderr output (some tools use stderr for progress)
                // We'll just print it.
                echo $buffer;
            } else {
                echo $buffer;
            }
        });

        if (!$process->isSuccessful()) {
            $io->error('Theme build failed.');
            return 1;
        }

        $io->success('Theme build process completed.');
        return 0;
    }
}

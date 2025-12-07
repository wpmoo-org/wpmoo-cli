<?php

namespace WPMoo\CLI\Commands\Framework;

use WPMoo\CLI\Support\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use WPMoo\CLI\Support\NodeEnvironment;

/**
 * Build Themes command for WPMoo.
 *
 * Compiles SCSS themes using the Node.js build script.
 *
 * @package WPMoo\CLI\Commands
 * @since 0.1.0
 */
class BuildStylesCommand extends BaseCommand
{
    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this->setName('build:styles')
            ->setDescription('Compile WPMoo styles (SCSS to CSS)')
            ->setHelp('This command compiles the WPMoo SCSS styles into CSS assets using the bundled Node.js script.');
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
        $io->title('WPMoo Style Builder');

        // 1. Ensure internal Node.js environment is ready
        $node_env = new NodeEnvironment($this->filesystem);
        if (!$node_env->ensure_dependencies($io)) {
            return 1;
        }

        // 2. Locate the build script based on context
        $project = $this->identify_project();
        $context_dir = ($project['type'] === 'wpmoo-framework') ? 'framework' : 'plugin';
        $script_path = dirname(__DIR__, 3) . "/scripts/{$context_dir}/build-styles.js";

        if (!$this->filesystem->file_exists($script_path)) {
            $io->error("Build script not found at: {$script_path}");
            return 1;
        }

        // 3. Determine Target Directory
        // For now, we assume the current working directory is the target
        $target_dir = $this->get_cwd();

        $io->text("Target: " . $target_dir);
        $io->text("Script: " . $script_path);
        $io->newLine();

        // 4. Run the script
        $command = ['node', $script_path, $target_dir];

        $process = new Process($command);
        $process->setTimeout(300); // 5 minutes timeout

        // Stream output to console
        $process->run(function ($type, $buffer) use ($io) {
            $io->write($buffer);
        });

        if (!$process->isSuccessful()) {
            $io->error('Style build failed.');
            return 1;
        }

        $io->success('Style build process completed.');
        return 0;
    }
}

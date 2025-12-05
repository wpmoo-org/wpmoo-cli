<?php

namespace WPMoo\CLI\Commands\Framework;

use WPMoo\CLI\Support\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use WPMoo\CLI\Support\NodeEnvironment;

/**
 * Development command for WPMoo projects.
 *
 * Sets up a development environment with live reloading and asset compilation.
 *
 * @package WPMoo\CLI\Commands\Framework
 * @since 0.1.0
 */
class DevCommand extends BaseCommand
{
    protected static $defaultName = 'dev';

    protected function configure()
    {
        $this->setDescription('Starts a development server with live reloading and asset compilation for WPMoo projects.')
            ->setAliases(['watch'])
            ->setHelp('This command watches for changes in SCSS, JS, and PHP files, compiles assets, and serves the project via BrowserSync.');
    }

    /**
     * Executes the dev command.
     *
     * @param InputInterface $input The input instance.
     * @param OutputInterface $output The output instance.
     * @return int The exit status code.
     */
    public function handle_execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('WPMoo Development Server');

        $project = $this->identify_project();
        if ($project['type'] !== 'wpmoo-framework' && $project['type'] !== 'wpmoo-plugin' && $project['type'] !== 'wpmoo-theme') {
            $io->error('The "dev" command can only be used inside a WPMoo framework, plugin, or theme project.');
            return self::FAILURE;
        }

        $project_root = $project['root'] ?? $this->get_cwd();
        $cli_root = dirname(__DIR__, 3);

        // Ensure internal Node.js environment is ready
        $node_env = new NodeEnvironment($this->filesystem);
        if (!$node_env->ensure_dependencies($io)) {
            return self::FAILURE;
        }

        $io->writeln(sprintf('<info>Starting dev server for project:</info> %s', $project_root));
        $io->note('Initial build in progress...');

        // Initial Build
        $build_process = new Process(['npm', 'run', 'build', '--silent'], $cli_root, ['TARGET_DIR' => $project_root]);
        $build_process->run();
        if (!$build_process->isSuccessful()) {
            $io->error('Initial build failed.');
            $io->writeln($build_process->getErrorOutput());
            return self::FAILURE;
        }

        $io->success('Initial build completed.');
        $io->section('Starting watchers and BrowserSync...');

        // Start dev server
        $dev_process = new Process(['npm', 'run', 'dev'], $cli_root, ['TARGET_DIR' => $project_root]);
        $dev_process->setTimeout(null)->setIdleTimeout(null)->setTty(Process::isTtySupported());

        return $dev_process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
    }
}

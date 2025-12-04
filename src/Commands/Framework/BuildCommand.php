<?php

namespace WPMoo\CLI\Commands\Framework;

use WPMoo\CLI\Support\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * Main Build Command.
 *
 * Orchestrates the build process for all assets (Themes, Scripts, etc.).
 *
 * @package WPMoo\CLI\Commands
 * @since 0.1.0
 */
class BuildCommand extends BaseCommand
{
    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this->setName('build:all')
            ->setDescription('Build all project assets')
            ->setHelp('This command runs all build tasks (Themes, Scripts, etc.) sequentially.')
            ->setAliases(['build']);
    }

    /**
     * Execute the command.
     *
     * @param InputInterface $input Command input.
     * @param OutputInterface $output Command output.
     * @return int Exit status.
     */
    public function handle_execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('WPMoo Asset Build');

        $application = $this->getApplication();
        if (! $application) {
            $io->error('Application instance not found.');
            return 1;
        }

        // 1. Run Styles Build
        $io->section('Building Styles...');
        $command = $application->find('build:styles');

        // We pass the same arguments/options if needed, or an empty array
        $stylesInput = new ArrayInput([]);
        $returnCode = $command->run($stylesInput, $output);

        if ($returnCode !== 0) {
            $io->error('Style build failed. Aborting full build.');
            return $returnCode;
        }

        // 2. Run Scripts Build
        if ($application->has('build:scripts')) {
            $io->section('Building Scripts...');
            $scriptsCommand = $application->find('build:scripts');
            $scriptsInput = new ArrayInput([]);
            $returnCode = $scriptsCommand->run($scriptsInput, $output);

            if ($returnCode !== 0) {
                $io->error('Scripts build failed. Aborting full build.');
                return $returnCode;
            }
        }

        $io->newLine();
        $io->success('All build tasks completed successfully.');

        return 0;
    }
}

<?php

namespace WPMoo\CLI\Commands\Common;

use WPMoo\CLI\Support\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Runs PHPCBF to fix coding standards.
 *
 * @package WPMoo\CLI\Commands
 * @since 0.1.0
 * @link https://wpmoo.org   WPMoo – WordPress Micro Object-Oriented Framework.
 * @link https://github.com/wpmoo/wpmoo   GitHub Repository.
 * @license https://spdx.org/licenses/GPL-2.0-or-later.html   GPL-2.0-or-later
 */
class CheckCbfCommand extends BaseCommand
{
    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('check:phpcbf')
            ->setDescription('Runs PHPCBF to fix coding standards.');
    }

    /**
     * Execute the command.
     *
     * @param InputInterface $input The input interface.
     * @param OutputInterface $output The output interface.
     * @return int The command exit status.
     */
    public function handle_execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Running PHPCBF...</info>');

        $phpcbf = $this->get_cwd() . '/vendor/bin/phpcbf';
        if (!file_exists($phpcbf)) {
            $output->writeln('<error>phpcbf binary not found in vendor/bin. Run composer install.</error>');
            return self::FAILURE;
        }

        $process = new Process([$phpcbf], $this->get_cwd());
        $process->setTimeout(300);

        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        $exitCode = $process->getExitCode();

        if ($exitCode === 1) {
            $output->writeln('<info>PHPCBF fixed some errors.</info>');
            return self::SUCCESS;
        }

        if ($exitCode === 0) {
            $output->writeln('<success>No fixable errors found.</success>');
            return self::SUCCESS;
        }

        if ($exitCode === 2) {
            $output->writeln('<comment>PHPCBF failed to fix all errors.</comment>');
            return self::FAILURE;
        }

        if (!$process->isSuccessful()) {
            $output->writeln('<error>PHPCBF failed to run!</error>');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

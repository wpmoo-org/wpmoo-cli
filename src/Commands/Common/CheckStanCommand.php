<?php

namespace WPMoo\CLI\Commands\Common;

use WPMoo\CLI\Support\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Runs PHPStan.
 *
 * @package WPMoo\CLI\Commands
 * @since 0.1.0
 * @link https://wpmoo.org   WPMoo – WordPress Micro Object-Oriented Framework.
 * @link https://github.com/wpmoo/wpmoo   GitHub Repository.
 * @license https://spdx.org/licenses/GPL-2.0-or-later.html   GPL-2.0-or-later
 */
class CheckStanCommand extends BaseCommand
{
    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('check:phpstan')
            ->setDescription('Runs PHPStan static analysis.');
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
        $output->writeln('<info>Running PHPStan...</info>');

        $phpstan = $this->get_cwd() . '/vendor/bin/phpstan';
        if (!file_exists($phpstan)) {
            $output->writeln('<error>phpstan binary not found in vendor/bin. Run composer install.</error>');
            return self::FAILURE;
        }

        $args = [$phpstan, 'analyse', '--no-progress', '--memory-limit=512M'];

        $process = new Process($args, $this->get_cwd());
        $process->setTimeout(600);

        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            $output->writeln('<error>PHPStan checks failed!</error>');
            return self::FAILURE;
        }

        $output->writeln('<success>PHPStan checks passed!</success>');
        return self::SUCCESS;
    }
}

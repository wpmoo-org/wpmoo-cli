<?php

namespace WPMoo\CLI\Commands\Common;

use WPMoo\CLI\Support\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Runs all code quality checks (validate, lint, phpcs, phpcbf, phpstan).
 *
 * @package WPMoo\CLI\Commands
 * @since 0.1.0
 * @link https://wpmoo.org   WPMoo – WordPress Micro Object-Oriented Framework.
 * @link https://github.com/wpmoo/wpmoo   GitHub Repository.
 * @license https://spdx.org/licenses/GPL-2.0-or-later.html   GPL-2.0-or-later
 */
class CheckCommand extends BaseCommand
{
    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('check:all')
            ->setDescription('Runs all code quality checks (validate, lint, phpcs, phpcbf, phpstan).');
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
        $application = $this->getApplication();
        if (!$application) {
            $output->writeln('<error>Application not found.</error>');
            return self::FAILURE;
        }

        // 1. Composer Validate
        $output->writeln('<info>Running composer validate...</info>');
        $process = new Process(['composer', 'validate'], $this->get_cwd());
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        if (!$process->isSuccessful()) {
            $output->writeln('<error>Composer validation failed!</error>');
            return self::FAILURE;
        }

        // 2. check:lint
        if ($this->runSubCommand($application, 'check:lint', $output) !== self::SUCCESS) {
            return self::FAILURE;
        }

        // 3. check:phpcbf (Fixes code)
        // We ignore the result of phpcbf because we want to proceed to verification
        $this->runSubCommand($application, 'check:phpcbf', $output);

        // 4. check:phpcs (Verify code style)
        if ($this->runSubCommand($application, 'check:phpcs', $output) !== self::SUCCESS) {
            return self::FAILURE;
        }

        // 5. check:phpstan (Static analysis)
        if ($this->runSubCommand($application, 'check:phpstan', $output) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $output->writeln('');
        $output->writeln('<success>All checks passed successfully!</success>');
        return self::SUCCESS;
    }

    /**
     * Run a sub-command.
     *
     * @param mixed $application The application instance.
     * @param string $name The command name.
     * @param OutputInterface $output The output interface.
     * @return int The command exit status.
     */
    private function runSubCommand($application, $name, $output): int
    {
        try {
            $command = $application->find($name);
            return $command->run(new ArrayInput([]), $output);
        } catch (\Exception $e) {
            $output->writeln("<error>Failed to run {$name}: {$e->getMessage()}</error>");
            return self::FAILURE;
        }
    }
}

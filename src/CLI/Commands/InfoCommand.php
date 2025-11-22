<?php

/**
 * Info command for the WPMoo CLI.
 *
 * Provides information about the PHP and WordPress environment.
 *
 * @package WPMoo\CLI\Commands
 * @since 0.1.0
 * @link  https://wpmoo.org WPMoo – WordPress Micro Object-Oriented Framework.
 * @link  https://github.com/wpmoo/wpmoo GitHub Repository.
 * @license https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 */

namespace WPMoo\CLI\Commands;

use WPMoo\CLI\Support\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Info command to provide environment information.
 */
class InfoCommand extends BaseCommand
{
    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this->setName('info')
            ->setDescription('Show framework information')
            ->setHelp('This command shows information about the current PHP and WordPress environment.');
    }

    /**
     * Execute the command.
     *
     * @param InputInterface $input Command input.
     * @param OutputInterface $output Command output.
     * @return int Exit status (0 for success, non-zero for failure).
     */
    public function handleExecute(InputInterface $input, OutputInterface $output): int
    {
        $php = PHP_VERSION;
        $wp  = function_exists('get_bloginfo') ? get_bloginfo('version') : 'n/a (CLI)';

        $output->writeln('<info>WPMoo — WordPress Micro OOP Framework</info>');
        $output->writeln('<comment>PHP: ' . $php . '</comment>');
        $output->writeln('<comment>WP : ' . $wp . '</comment>');
        $output->writeln('');

        return 0;
    }
}

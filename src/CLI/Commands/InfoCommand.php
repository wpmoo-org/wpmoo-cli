<?php

namespace WPMoo\CLI\Commands;

use WPMoo\CLI\Support\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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
    public function handle_execute(InputInterface $input, OutputInterface $output): int
    {
        $symfony_io = new SymfonyStyle($input, $output);

        $php_version = PHP_VERSION;
        $wordpress_version  = function_exists('get_bloginfo') ? get_bloginfo('version') : 'n/a (CLI)';

        $symfony_io->title('WPMoo — WordPress Micro OOP Framework');

        $symfony_io->writeln("<info>PHP Version:</info>     {$php_version}");
        $symfony_io->writeln("<info>WordPress Version:</info> {$wordpress_version}");
        $symfony_io->newLine();

        return 0;
    }
}

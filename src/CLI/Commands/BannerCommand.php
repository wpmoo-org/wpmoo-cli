<?php

/**
 * Banner command for the WPMoo CLI.
 *
 * Displays the WPMoo ASCII art banner.
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
 * Banner command to display ASCII art.
 */
class BannerCommand extends BaseCommand
{
    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this->setName('banner')
            ->setDescription('Show WPMoo ASCII art banner')
            ->setHidden(true);  // Hide from command list since it's just for default display
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
        $banner = array(
            '░██       ░██ ░█████████  ░███     ░███                       ',
            '░██       ░██ ░██     ░██ ░████   ░████                       ',
            '░██  ░██  ░██ ░██     ░██ ░██░██ ░██░██  ░███████   ░███████  ',
            '░██ ░████ ░██ ░█████████  ░██ ░████ ░██ ░██    ░██ ░██    ░██ ',
            '░██░██ ░██░██ ░██         ░██  ░██  ░██ ░██    ░██ ░██    ░██ ',
            '░████   ░████ ░██         ░██       ░██ ░██    ░██ ░██    ░██ ',
            '░███     ░███ ░██         ░██       ░██  ░███████   ░███████  ',
        );

        foreach ($banner as $line) {
            $output->writeln($line);
        }

        // Add a blank line before showing available commands
        $output->writeln('');

        // Show available commands
        $application = $this->getApplication();
        if ($application) {
            $commands = $application->all();
            $visibleCommands = array();

            foreach ($commands as $name => $command) {
                if (! $command->isHidden() && $name !== 'banner') {
                    $visibleCommands[$name] = $command;
                }
            }

            if (! empty($visibleCommands)) {
                $output->writeln('<comment>Available commands:</comment>');
                foreach ($visibleCommands as $name => $command) {
                    $output->writeln(sprintf('  <info>%-15s</info> %s', $name, $command->getDescription()));
                }
            } else {
                $output->writeln('<comment>No commands available.</comment>');
            }
        }

        return 0;
    }
}

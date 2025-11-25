<?php

/**
 * Base command class for the WPMoo CLI.
 *
 * Provides common functionality for all CLI commands.
 *
 * @package WPMoo\CLI\Support
 * @since 0.1.0
 * @link  https://wpmoo.org WPMoo – WordPress Micro Object-Oriented Framework.
 * @link  https://github.com/wpmoo/wpmoo GitHub Repository.
 * @license https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 */

namespace WPMoo\CLI\Support;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WPMoo\CLI\Contracts\CommandInterface;

/**
 * Abstract base command class.
 */
abstract class BaseCommand extends Command implements CommandInterface
{
    /**
     * Execute the command.
     *
     * @param InputInterface $input Command input.
     * @param OutputInterface $output Command output.
     * @return int Exit status (0 for success, non-zero for failure).
     */
    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Define custom styles
        $formatter = $output->getFormatter();
        $formatter->setStyle('important', new OutputFormatterStyle('yellow', null, ['bold']));
        $formatter->setStyle('question', new OutputFormatterStyle('yellow'));


        return $this->handleExecute($input, $output);
    }

    /**
     * Handle the actual command execution.
     *
     * @param InputInterface $input Command input.
     * @param OutputInterface $output Command output.
     * @return int Exit status (0 for success, non-zero for failure).
     */
    abstract public function handleExecute(InputInterface $input, OutputInterface $output): int;

    /**
     * Get the current working directory.
     *
     * @return string Current working directory, or '.' if unable to determine.
     */
    protected function getCwd(): string
    {
        $cwd = getcwd();
        return $cwd ? $cwd : '.';
    }

    /**
     * Get the relative path from the current working directory.
     *
     * @param string $path Absolute path.
     * @return string Relative path.
     */
    protected function getRelativePath(string $path): string
    {
        $cwd  = $this->getCwd();
        $cwd  = rtrim($cwd, '/\\') . DIRECTORY_SEPARATOR;
        $path = (string) $path;

        if ('' !== $cwd && 0 === strpos($path, $cwd)) {
            return substr($path, strlen($cwd));
        }

        return $path;
    }

    /**
     * Format file size in human-readable format.
     *
     * @param int $size Size in bytes.
     * @param int $decimals Number of decimal places.
     * @return string Formatted size.
     */
    protected function formatFileSize(int $size, int $decimals = 2): string
    {
        $units = array( 'B', 'KB', 'MB', 'GB' );
        $units_count = count($units);
        for ($i = 0; $size > 1024 && $i < $units_count - 1; $i++) {
            $size /= 1024;
        }

        return round($size, $decimals) . ' ' . $units[ $i ];
    }
}

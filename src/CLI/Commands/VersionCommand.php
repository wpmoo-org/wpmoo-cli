<?php

/**
 * Version command for the WPMoo CLI.
 *
 * Handles version management for WPMoo-based projects using semantic versioning.
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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Version command to handle plugin version management.
 */
class VersionCommand extends BaseCommand
{
    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this->setName('version')
            ->setDescription('Update the plugin version')
            ->setHelp('This command updates version in plugin files and readme.txt according to semantic versioning.')
            ->addArgument(
                'version',
                InputArgument::OPTIONAL,
                'The version to set (e.g., 1.0.0, v1.2.3, 1.2.3-beta.1)'
            )
            ->addOption(
                'major',
                null,
                InputOption::VALUE_NONE,
                'Increment the major version (x.0.0)'
            )
            ->addOption(
                'minor',
                null,
                InputOption::VALUE_NONE,
                'Increment the minor version (x.y.0)'
            )
            ->addOption(
                'patch',
                null,
                InputOption::VALUE_NONE,
                'Increment the patch version (x.y.z)'
            )
            ->addOption(
                'pre-alpha',
                null,
                InputOption::VALUE_OPTIONAL,
                'Increment to pre-alpha version (x.y.z-alpha.1)',
                false
            )
            ->addOption(
                'pre-beta',
                null,
                InputOption::VALUE_OPTIONAL,
                'Increment to pre-beta version (x.y.z-beta.1)',
                false
            )
            ->addOption(
                'pre-rc',
                null,
                InputOption::VALUE_OPTIONAL,
                'Increment to pre-release candidate version (x.y.z-rc.1)',
                false
            )
            ->addOption(
                'no-interaction',
                'n',
                InputOption::VALUE_NONE,
                'Do not ask any interactive questions'
            );
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
        $version = $input->getArgument('version');
        $major = $input->getOption('major');
        $minor = $input->getOption('minor');
        $patch = $input->getOption('patch');
        $preAlpha = $input->getOption('pre-alpha');
        $preBeta = $input->getOption('pre-beta');
        $preRc = $input->getOption('pre-rc');

        $output->writeln('<info>WPMoo Version Manager</info>');
        $output->writeln('');

        // Determine the project type and get current version
        $projectInfo = $this->identifyProject();
        $output->writeln("<comment>Detected project: {$projectInfo['type']}</comment>");

        if (!$projectInfo['found']) {
            $output->writeln('<error>No valid WPMoo-based project detected in current directory.</error>');
            return 1;
        }

        $currentVersion = $this->getCurrentVersion($projectInfo);
        $output->writeln("<comment>Current version: {$currentVersion}</comment>");

        // Determine the new version
        $newVersion = $version;
        $incrementType = null;

        // If specific options are provided via command line, use them
        if ($major) {
            $newVersion = $this->incrementMajor($currentVersion);
            $incrementType = 'major';
        } elseif ($minor) {
            $newVersion = $this->incrementMinor($currentVersion);
            $incrementType = 'minor';
        } elseif ($patch) {
            $newVersion = $this->incrementPatch($currentVersion);
            $incrementType = 'patch';
        } elseif ($preAlpha !== false) {
            $prefix = $preAlpha ?: 'alpha';
            $newVersion = $this->incrementPreRelease($currentVersion, $prefix);
            $incrementType = "pre-{$prefix}";
        } elseif ($preBeta !== false) {
            $prefix = $preBeta ?: 'beta';
            $newVersion = $this->incrementPreRelease($currentVersion, $prefix);
            $incrementType = "pre-{$prefix}";
        } elseif (!$version) {
            $newVersion = $this->interactiveVersionSelection($input, $output, $currentVersion);

            if ($newVersion === null) {
                $output->writeln('<comment>Operation cancelled.</comment>');
                return 0;
            }
        } else {
            $newVersion = $version;
        }

        // Validate the new version
        if (!$this->isValidVersion($newVersion)) {
            $output->writeln("<error>Invalid version format: {$newVersion}</error>");
            return 1;
        }

        $output->writeln("<comment>New version: {$newVersion}</comment>");

        // Confirm with user
        $noInteraction = $input->getOption('no-interaction');

        if (!$noInteraction) {
            $question = new ConfirmationQuestion(
                "Are you sure you want to update from {$currentVersion} to {$newVersion}? (y/N) ",
                false
            );

            if (!$this->getHelper('question')->ask($input, $output, $question)) {
                $output->writeln('<comment>Operation cancelled.</comment>');
                return 0;
            }
        }

        // Update the version in files
        $success = $this->updateVersion($projectInfo, $currentVersion, $newVersion, $output);

        if ($success) {
            $output->writeln("<info>Version successfully updated from {$currentVersion} to {$newVersion}</info>");
            return 0;
        } else {
            $output->writeln("<error>Failed to update version from {$currentVersion} to {$newVersion}</error>");
            return 1;
        }
    }

    /**
     * Interactive version selection menu.
     *
     * @param InputInterface $input Input interface.
     * @param OutputInterface $output Output interface.
     * @param string $current Current version.
     * @return string|null New version or null if cancelled.
     */
    private function interactiveVersionSelection(InputInterface $input, OutputInterface $output, string $current): ?string
    {
        // Define version options with descriptions
        $options = [
            'patch'      => sprintf(
                "Patch     - Increment patch (x.y.z → x.y.z+1) [<comment>%s</comment>]",
                $this->incrementPatch($current)
            ),
            'minor'      => sprintf(
                "Minor     - Increment minor (x.y.z → x.y+1.0) [<comment>%s</comment>]",
                $this->incrementMinor($current)
            ),
            'major'      => sprintf(
                "Major     - Increment major (x.y.z → x+1.0.0) [<comment>%s</comment>]",
                $this->incrementMajor($current)
            ),
            'pre-alpha'  => sprintf(
                "Pre-alpha - Pre-alpha (x.y.z → x.y.z-alpha.1) [<comment>%s</comment>]",
                $this->incrementPreRelease($current, 'alpha')
            ),
            'pre-beta'   => sprintf(
                "Pre-beta  - Pre-beta (x.y.z → x.y.z-beta.1) [<comment>%s</comment>]",
                $this->incrementPreRelease($current, 'beta')
            ),
            'pre-rc'     => sprintf(
                "Pre-RC    - Pre-release candidate (x.y.z → x.y.z-rc.1) [<comment>%s</comment>]",
                $this->incrementPreRelease($current, 'rc')
            ),
            'custom'     => 'Custom    - Enter custom version',
            'cancel'     => 'Cancel    - Cancel operation'
        ];

        // Create choice question
        $question = new ChoiceQuestion(
            '<question>Select version increment type:</question>',
            $options,
            'patch' // Default to patch increment
        );

        $question->setErrorMessage('Option %s is invalid.');

        $helper = $this->getHelper('question');
        $choice = $helper->ask($input, $output, $question);

        // Process selection
        switch ($choice) {
            case 'cancel':
                return null; // Cancelled
            case 'patch':
                return $this->incrementPatch($current);
            case 'minor':
                return $this->incrementMinor($current);
            case 'major':
                return $this->incrementMajor($current);
            case 'pre-alpha':
                return $this->incrementPreRelease($current, 'alpha');
            case 'pre-beta':
                return $this->incrementPreRelease($current, 'beta');
            case 'pre-rc':
                return $this->incrementPreRelease($current, 'rc');
            case 'custom':
                $customQuestion = new \Symfony\Component\Console\Question\Question("Enter new version (current: {$current}): ");
                $customQuestion->setValidator(function ($answer) {
                    if (!empty($answer) && !$this->isValidVersion($answer)) {
                        throw new \RuntimeException("Invalid version format: {$answer}");
                    }
                    return $answer;
                });
                $customVersion = $helper->ask($input, $output, $customQuestion);
                return $customVersion;
            default:
                return $this->incrementPatch($current); // Default to patch
        }
    }

    /**
     * Identify the project type and location of version files.
     *
     * @return array Project information.
     */
    private function identifyProject(): array
    {
        $cwd = $this->getCwd();

        // Check for wpmoo framework project
        $wpmooSrcPath = $cwd . '/src/wpmoo.php';
        $isWPMooFramework = file_exists($wpmooSrcPath) &&
            strpos(file_get_contents($wpmooSrcPath), 'WPMoo Framework') !== false;

        if ($isWPMooFramework) {
            return [
                'found' => true,
                'type' => 'wpmoo-framework',
                'main_file' => $wpmooSrcPath,
                'readme_file' => $cwd . '/readme.txt' // Check if readme.txt exists
            ];
        }

        // Check for wpmoo-starter or other wpmoo-based plugin
        $phpFiles = glob($cwd . '/*.php');
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            // Look for WPMoo in plugin header
            if (
                preg_match('/(wpmoo|WPMoo)/i', $content) &&
                (preg_match('/^[ \t\/*#@]*Plugin Name:/im', $content) ||
                 preg_match('/^[ \t\/*#@]*Theme Name:/im', $content))
            ) {
                $readmePath = $cwd . '/readme.txt';
                return [
                    'found' => true,
                    'type' => 'wpmoo-plugin',
                    'main_file' => $file,
                    'readme_file' => file_exists($readmePath) ? $readmePath : null
                ];
            }
        }

        return [
            'found' => false,
            'type' => 'unknown',
            'main_file' => null,
            'readme_file' => null
        ];
    }

    /**
     * Get the current version from project files.
     *
     * @param array $projectInfo Project information.
     * @return string Current version.
     */
    private function getCurrentVersion(array $projectInfo): string
    {
        $mainFile = $projectInfo['main_file'];

        if (!$mainFile || !file_exists($mainFile)) {
            return '0.0.0';
        }

        $content = file_get_contents($mainFile);

        // Look for version in plugin header
        if (preg_match('/^[ \t\/*#@]*Version:\s*(.*)$/im', $content, $matches)) {
            return trim($matches[1]);
        }

        // If not found in main file, return default
        return '0.0.0';
    }

    /**
     * Check if version string is valid.
     *
     * @param string $version Version string.
     * @return bool True if valid.
     */
    private function isValidVersion(string $version): bool
    {
        // Basic semantic version validation: x.y.z[-prerelease][+build]
        $pattern = '/^v?\d+\.\d+\.\d+(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?(?:\+[0-9A-Za-z-]+)?$/';
        return (bool) preg_match($pattern, $version);
    }

    /**
     * Increment major version.
     *
     * @param string $version Current version.
     * @return string New version.
     */
    private function incrementMajor(string $version): string
    {
        // Extract major, minor, patch and any pre-release/build info
        $version = ltrim($version, 'v');
        preg_match('/^(\d+)\.(\d+)\.(\d+)(.*)?$/', $version, $matches);

        if (count($matches) < 4) {
            return '1.0.0';
        }

        $major = (int)$matches[1];
        return ($major + 1) . '.0.0';
    }

    /**
     * Increment minor version.
     *
     * @param string $version Current version.
     * @return string New version.
     */
    private function incrementMinor(string $version): string
    {
        // Extract major, minor, patch and any pre-release/build info
        $version = ltrim($version, 'v');
        preg_match('/^(\d+)\.(\d+)\.(\d+)(.*)?$/', $version, $matches);

        if (count($matches) < 4) {
            return '0.1.0';
        }

        $major = (int)$matches[1];
        $minor = (int)$matches[2];
        return $major . '.' . ($minor + 1) . '.0';
    }

    /**
     * Increment patch version.
     *
     * @param string $version Current version.
     * @return string New version.
     */
    private function incrementPatch(string $version): string
    {
        // Extract major, minor, patch and any pre-release/build info
        $version = ltrim($version, 'v');
        preg_match('/^(\d+)\.(\d+)\.(\d+)(.*)?$/', $version, $matches);

        if (count($matches) < 4) {
            return '0.0.1';
        }

        $major = (int)$matches[1];
        $minor = (int)$matches[2];
        $patch = (int)$matches[3];
        return $major . '.' . $minor . '.' . ($patch + 1);
    }

    /**
     * Increment to pre-release version.
     *
     * @param string $version Current version.
     * @param string $prefix Pre-release prefix (alpha, beta, rc, etc.).
     * @return string New version.
     */
    private function incrementPreRelease(string $version, string $prefix): string
    {
        // Extract major, minor, patch and any pre-release info
        $version = ltrim($version, 'v');
        preg_match('/^(\d+)\.(\d+)\.(\d+)(?:-([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?(?:\+[0-9A-Za-z-]+)?/', $version, $matches);

        if (count($matches) < 4) {
            // If version format is not valid, start with 0.1.0-alpha.1
            return "0.1.0-{$prefix}.1";
        }

        $major = $matches[1];
        $minor = $matches[2];
        $patch = $matches[3];
        $preRelease = $matches[4] ?? '';

        // If current version is already a pre-release of the same prefix, increment the number
        if ($preRelease && strpos($preRelease, $prefix) === 0) {
            // Extract the number and increment it
            $isPreRelease = preg_match("/{$prefix}\.(\d+)/", $preRelease, $numMatches);
            if ($isPreRelease) {
                $num = (int)$numMatches[1];
                return "{$major}.{$minor}.{$patch}-{$prefix}." . ($num + 1);
            }
        }

        // If not a pre-release of same type, start from 1
        return "{$major}.{$minor}.{$patch}-{$prefix}.1";
    }

    /**
     * Update version in project files.
     *
     * @param array $projectInfo Project information.
     * @param string $oldVersion Old version.
     * @param string $newVersion New version.
     * @param OutputInterface $output Output interface.
     * @return bool True on success.
     */
    private function updateVersion(array $projectInfo, string $oldVersion, string $newVersion, OutputInterface $output): bool
    {
        $mainFile = $projectInfo['main_file'];
        $readmeFile = $projectInfo['readme_file'];

        $success = true;

        // Update main plugin file
        if ($mainFile && file_exists($mainFile)) {
            $content = file_get_contents($mainFile);

            // Find the version line and replace it
            $pattern = '/^([ \t\/*#@]*Version:\s*)(.*)$/m';
            $replacement = '${1}' . $newVersion;
            $newContent = preg_replace($pattern, $replacement, $content);

            $contentChanged = $newContent !== $content;
            if ($contentChanged) {
                if (file_put_contents($mainFile, $newContent) !== false) {
                    $output->writeln("<info>Updated version in {$mainFile}</info>");
                } else {
                    $output->writeln("<error>Failed to update version in {$mainFile}</error>");
                    $success = false;
                }
            } else {
                $output->writeln("<error>Could not find version line in {$mainFile}</error>");
                $success = false;
            }
        }

        // Update readme.txt if it exists
        if ($readmeFile && file_exists($readmeFile)) {
            $content = file_get_contents($readmeFile);

            // Find the stable tag line and replace it
            $pattern = '/^(Stable tag:\s*)(.*)$/mi';
            $replacement = '${1}' . $newVersion;
            $newContent = preg_replace($pattern, $replacement, $content);

            $contentChanged = $newContent !== $content;
            if ($contentChanged) {
                if (file_put_contents($readmeFile, $newContent) !== false) {
                    $output->writeln("<info>Updated stable tag in {$readmeFile}</info>");
                } else {
                    $output->writeln("<error>Failed to update stable tag in {$readmeFile}</error>");
                    $success = false;
                }
            } else {
                $output->writeln("<error>Could not find stable tag line in {$readmeFile}</error>");
                $success = false;
            }
        }

        return $success;
    }
}

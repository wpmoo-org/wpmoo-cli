<?php

/**
 * Version management helper for WPMoo CLI.
 *
 * @package WPMoo\CLI\Support
 * @since 0.1.0
 */

namespace WPMoo\CLI\Support;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class VersionManager
{
    private $command;

    public function __construct($command)
    {
        $this->command = $command;
    }

    public function interactiveVersionSelection(InputInterface $input, OutputInterface $output, string $current): ?string
    {
        $options = [
            'patch'      => sprintf("Patch     - Increment patch (x.y.z → x.y.z+1) [<comment>%s</comment>]", $this->incrementPatch($current)),
            'minor'      => sprintf("Minor     - Increment minor (x.y.z → x.y+1.0) [<comment>%s</comment>]", $this->incrementMinor($current)),
            'major'      => sprintf("Major     - Increment major (x.y.z → x+1.0.0) [<comment>%s</comment>]", $this->incrementMajor($current)),
            'pre-alpha'  => sprintf("Pre-alpha - Pre-alpha (x.y.z → x.y.z-alpha.1) [<comment>%s</comment>]", $this->incrementPreRelease($current, 'alpha')),
            'pre-beta'   => sprintf("Pre-beta  - Pre-beta (x.y.z → x.y.z-beta.1) [<comment>%s</comment>]", $this->incrementPreRelease($current, 'beta')),
            'pre-rc'     => sprintf("Pre-RC    - Pre-release candidate (x.y.z → x.y.z-rc.1) [<comment>%s</comment>]", $this->incrementPreRelease($current, 'rc')),
            'custom'     => 'Custom    - Enter custom version',
            'cancel'     => 'Cancel    - Cancel operation'
        ];

        $question = new ChoiceQuestion('<question>Select version increment type:</question>', $options, 'patch');
        $question->setErrorMessage('Option %s is invalid.');

        $helper = $this->command->getHelper('question');
        $choice = $helper->ask($input, $output, $question);

        switch ($choice) {
            case 'cancel':
                return null;
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
                $customQuestion = new Question("Enter new version (current: {$current}): ");
                $customQuestion->setValidator(function ($answer) {
                    if (!empty($answer) && !$this->isValidVersion($answer)) {
                        throw new \RuntimeException("Invalid version format: {$answer}");
                    }
                    return $answer;
                });
                return $helper->ask($input, $output, $customQuestion);
            default:
                return $this->incrementPatch($current);
        }
    }

    public function getCurrentVersion(array $projectInfo): string
    {
        $mainFile = $projectInfo['main_file'];
        if (!$mainFile || !file_exists($mainFile)) {
            return '0.0.0';
        }
        $content = file_get_contents($mainFile);
        if (preg_match('/^[ \t\/*#@]*Version:\s*(.*)$/im', $content, $matches)) {
            return trim($matches[1]);
        }
        return '0.0.0';
    }

    public function updateVersion(array $projectInfo, string $newVersion, OutputInterface $output): bool
    {
        $mainFile = $projectInfo['main_file'];
        $readmeFile = $projectInfo['readme_file'];
        $success = true;

        if ($mainFile && file_exists($mainFile)) {
            $content = file_get_contents($mainFile);
            $pattern = '/^([ \t\/*#@]*Version:\s*)(.*)$/m';
            $replacement = '${1}' . $newVersion;
            $newContent = preg_replace($pattern, $replacement, $content);

            if ($newContent !== $content) {
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

        if ($readmeFile && file_exists($readmeFile)) {
            $content = file_get_contents($readmeFile);
            $pattern = '/^(Stable tag:\s*)(.*)$/mi';
            $replacement = '${1}' . $newVersion;
            $newContent = preg_replace($pattern, $replacement, $content);

            if ($newContent !== $content) {
                if (file_put_contents($readmeFile, $newContent) !== false) {
                    $output->writeln("<info>Updated stable tag in {$readmeFile}</info>");
                } else {
                    $output->writeln("<error>Failed to update stable tag in {$readmeFile}</error>");
                    $success = false;
                }
            } else {
                $output->writeln("<comment>Could not find stable tag line in {$readmeFile}, skipping.</comment>");
            }
        }
        return $success;
    }

    public function isValidVersion(string $version): bool
    {
        $pattern = '/^v?\d+\.\d+\.\d+(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?(?:\+[0-9A-Za-z-]+)?$/';
        return (bool) preg_match($pattern, $version);
    }

    private function incrementMajor(string $version): string
    {
        $version = ltrim($version, 'v');
        preg_match('/^(\d+)\.(\d+)\.(\d+)(.*)?$/', $version, $matches);
        if (count($matches) < 4) {
            return '1.0.0';
        }
        $major = (int)$matches[1];
        return ($major + 1) . '.0.0';
    }

    private function incrementMinor(string $version): string
    {
        $version = ltrim($version, 'v');
        preg_match('/^(\d+)\.(\d+)\.(\d+)(.*)?$/', $version, $matches);
        if (count($matches) < 4) {
            return '0.1.0';
        }
        $major = (int)$matches[1];
        $minor = (int)$matches[2];
        return $major . '.' . ($minor + 1) . '.0';
    }

    private function incrementPatch(string $version): string
    {
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

    private function incrementPreRelease(string $version, string $prefix): string
    {
        $version = ltrim($version, 'v');
        preg_match('/^(\d+)\.(\d+)\.(\d+)(?:-([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?(?:\+[0-9A-Za-z-]+)?/', $version, $matches);
        if (count($matches) < 4) {
            return "0.1.0-{$prefix}.1";
        }

        $major = $matches[1];
        $minor = $matches[2];
        $patch = $matches[3];
        $preRelease = $matches[4] ?? '';

        if ($preRelease && strpos($preRelease, $prefix) === 0) {
            $isPreRelease = preg_match("/{$prefix}\.(\d+)/", $preRelease, $numMatches);
            if ($isPreRelease) {
                $num = (int)$numMatches[1];
                return "{$major}.{$minor}.{$patch}-{$prefix}." . ($num + 1);
            }
        }
        return "{$major}.{$minor}.{$patch}-{$prefix}.1";
    }
}

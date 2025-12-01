<?php

/**
 * Version management helper for WPMoo CLI.
 *
 * @package WPMoo\CLI\Support
 * @since 0.1.0
 * @link  https://wpmoo.org WPMoo – WordPress Micro Object-Oriented Framework.
 * @link  https://github.com/wpmoo/wpmoo GitHub Repository.
 * @license https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
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

    public function interactive_version_selection(InputInterface $input, OutputInterface $output, string $current): ?string
    {
        $options = [
            'patch'      => sprintf("Patch     - Increment patch (x.y.z → x.y.z+1) [<comment>%s</comment>]", $this->increment_patch($current)),
            'minor'      => sprintf("Minor     - Increment minor (x.y.z → x.y+1.0) [<comment>%s</comment>]", $this->increment_minor($current)),
            'major'      => sprintf("Major     - Increment major (x.y.z → x+1.0.0) [<comment>%s</comment>]", $this->increment_major($current)),
            'pre-alpha'  => sprintf("Pre-alpha - Pre-alpha (x.y.z → x.y.z-alpha.1) [<comment>%s</comment>]", $this->increment_pre_release($current, 'alpha')),
            'pre-beta'   => sprintf("Pre-beta  - Pre-beta (x.y.z → x.y.z-beta.1) [<comment>%s</comment>]", $this->increment_pre_release($current, 'beta')),
            'pre-rc'     => sprintf("Pre-RC    - Pre-release candidate (x.y.z → x.y.z-rc.1) [<comment>%s</comment>]", $this->increment_pre_release($current, 'rc')),
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
                return $this->increment_patch($current);
            case 'minor':
                return $this->increment_minor($current);
            case 'major':
                return $this->increment_major($current);
            case 'pre-alpha':
                return $this->increment_pre_release($current, 'alpha');
            case 'pre-beta':
                return $this->increment_pre_release($current, 'beta');
            case 'pre-rc':
                return $this->increment_pre_release($current, 'rc');
            case 'custom':
                $customQuestion = new Question("Enter new version (current: {$current}): ");
                $customQuestion->setValidator(function ($answer) {
        if (!$version_manager->is_valid_version($new_version)) {
                        throw new \RuntimeException("Invalid version format: {$answer}");
                    }
                    return $answer;
                });
                return $helper->ask($input, $output, $customQuestion);
            default:
                return $this->increment_patch($current);
        }
    }

    public function get_current_version(array $project_info): string
    {
        $main_file = $project_info['main_file'];
        if (!$main_file || !file_exists($main_file)) {
            return '0.0.0';
        }
        $file_content = file_get_contents($main_file);
        if (preg_match('/^[ \t\/*#@]*Version:\s*(.*)$/im', $file_content, $matches)) {
            return trim($matches[1]);
        }
        return '0.0.0';
    }

    public function update_version(array $project_info, string $new_version_string, OutputInterface $output): bool
    {
        $main_file = $project_info['main_file'];
        $readme_file = $project_info['readme_file'];
        $update_success = true;

        if ($main_file && file_exists($main_file)) {
            $file_content = file_get_contents($main_file);
            $regex_pattern = '/^([ \t\/*#@]*Version:\s*)(.*)$/m';
            $replacement = '${1}' . $new_version_string;
            $new_file_content = preg_replace($regex_pattern, $replacement, $file_content);

            if ($new_file_content !== $file_content) {
                if (file_put_contents($main_file, $new_file_content) !== false) {
                    $output->writeln("<info>Updated version in {$main_file}</info>");
                } else {
                    $output->writeln("<error>Failed to update version in {$main_file}</error>");
                    $update_success = false;
                }
            } else {
                $output->writeln("<error>Could not find version line in {$main_file}</error>");
                $update_success = false;
            }
        }

        if ($readme_file && file_exists($readme_file)) {
            $file_content = file_get_contents($readme_file);
            $regex_pattern = '/^(Stable tag:\s*)(.*)$/mi';
            $replacement = '${1}' . $new_version_string;
            $new_file_content = preg_replace($regex_pattern, $replacement, $file_content);

            if ($new_file_content !== $file_content) {
                if (file_put_contents($readme_file, $new_file_content) !== false) {
                    $output->writeln("<info>Updated stable tag in {$readme_file}</info>");
                } else {
                    $output->writeln("<error>Failed to update stable tag in {$readme_file}</error>");
                    $update_success = false;
                }
            } else {
                $output->writeln("<comment>Could not find stable tag line in {$readme_file}, skipping.</comment>");
            }
        }
        return $update_success;
    }

    public function is_valid_version(string $version): bool
    {
        $pattern = '/^v?\d+\.\d+\.\d+(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?(?:\+[0-9A-Za-z-]+)?$/';
        return (bool) preg_match($pattern, $version);
    }

    private function increment_major(string $version): string
    {
        $version = ltrim($version, 'v');
        preg_match('/^(\d+)\.(\d+)\.(\d+)(.*)?$/', $version, $matches);
        if (count($matches) < 4) {
            return '1.0.0';
        }
        $major = (int)$matches[1];
        return ($major + 1) . '.0.0';
    }

    private function increment_minor(string $version): string
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

    private function increment_patch(string $version): string
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

    private function increment_pre_release(string $version, string $prefix): string
    {
        $version = ltrim($version, 'v');
        preg_match('/^(\d+)\.(\d+)\.(\d+)(?:-([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?(?:\+[0-9A-Za-z-]+)?/', $version, $matches);
        if (count($matches) < 4) {
            return "0.1.0-{$prefix}.1";
        }

        $major = $matches[1];
        $minor = $matches[2];
        $patch = $matches[3];
        $pre_release_string = $matches[4] ?? '';

        if ($pre_release_string && strpos($pre_release_string, $prefix) === 0) {
            $is_pre_release_match = preg_match("/{$prefix}\.(\d+)/", $pre_release_string, $number_matches);
            if ($is_pre_release_match) {
                $version_number = (int)$number_matches[1];
                return "{$major}.{$minor}.{$patch}-{$prefix}." . ($version_number + 1);
            }
        }
        return "{$major}.{$minor}.{$patch}-{$prefix}.1";
    }
}

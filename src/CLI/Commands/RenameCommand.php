<?php

/**
 * Rename command for the WPMoo CLI.
 *
 * Handles renaming of a WPMoo-based plugin.
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
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Yaml\Yaml;

/**
 * Rename command to handle plugin renaming.
 */
class RenameCommand extends BaseCommand
{
    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this->setName('rename')
            ->setDescription('Rename the WPMoo-based plugin')
            ->setHelp('This command allows you to rename the plugin name, filename, and namespace.');
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
        $output->writeln('');
        $output->writeln('<info>------------------------------------------------</info>');
        $output->writeln('<info>🙌 Rename WPMoo Plugin</info>');
        $output->writeln('<info>------------------------------------------------</info>');
        $output->writeln('');

        // 1. Check project context
        $projectInfo = $this->identifyProject();
        if ($projectInfo['type'] !== 'wpmoo-plugin') {
            $output->writeln('<error>The "rename" command can only be used inside a WPMoo-based plugin.</error>');
            return 1;
        }

        $oldDir = dirname($projectInfo['main_file']);
        if (!file_exists($oldDir . '/.wpmoo')) {
            $output->writeln('→ You are renaming your plugin for the first time.');
            $output->writeln('');
        }

        $output->writeln("🚦 ---------------------------------------------------------------------------------");
        $output->writeln("🚦 Remember the new plugin name and namespace must not contain \"WPMoo\"");
        $output->writeln("🚦 ---------------------------------------------------------------------------------");
        $output->writeln('');

        // 2. Ask for new names
        $helper = $this->getHelper('question');

        $pluginNameQuestion = new Question('❓ Plugin name: ');
        $pluginNameQuestion->setValidator(function ($answer) {
            if (empty($answer)) {
                throw new \RuntimeException('Plugin name cannot be empty.');
            }
            if (stripos($answer, 'WPMoo') !== false) {
                throw new \RuntimeException('Plugin name cannot contain "WPMoo".');
            }
            return $answer;
        });
        $newName = $helper->ask($input, $output, $pluginNameQuestion);

        $namespaceQuestion = new Question('❓ Namespace: ');
        $namespaceQuestion->setValidator(function ($answer) {
            if (empty($answer)) {
                throw new \RuntimeException('Namespace cannot be empty.');
            }
            if (stripos($answer, 'WPMoo') !== false) {
                throw new \RuntimeException('Namespace cannot contain "WPMoo".');
            }
            if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $answer)) {
                throw new \RuntimeException('Namespace is not valid.');
            }
            return $answer;
        });
        $newNamespace = $helper->ask($input, $output, $namespaceQuestion);

        // 3. Generate new filename and show confirmation
        $newFilename = strtolower(str_replace(' ', '-', $newName)) . '.php';

        $output->writeln('');
        $output->writeln("→ The new plugin filename, name and namespace will be '{$newFilename}', '{$newName}', '{$newNamespace}'");

        $confirmationQuestion = new ConfirmationQuestion('❓ Continue (y/n) (default: n): ', false);

        if (!$helper->ask($input, $output, $confirmationQuestion)) {
            $output->writeln('');
            $output->writeln('<comment>Operation cancelled.</comment>');
            return 0;
        }

        $output->writeln('');
        $output->writeln('<comment>Renaming plugin...</comment>');
        $output->writeln('');

        // 4. Perform renaming
        $this->renamePlugin($projectInfo, $newName, $newNamespace, $newFilename, $output);

        $output->writeln('');
        $output->writeln('<info>Plugin renamed successfully!</info>');

        return 0;
    }

    /**
     * Renames the plugin.
     *
     * @param array $projectInfo
     * @param string $newName
     * @param string $newNamespace
     * @param string $newFilename
     * @param OutputInterface $output
     */
    private function renamePlugin(array $projectInfo, string $newName, string $newNamespace, string $newFilename, OutputInterface $output)
    {
        $oldMainFile = $projectInfo['main_file'];
        $oldDir = dirname($oldMainFile);
        $newMainFile = $oldDir . '/' . $newFilename;

        // Get old namespace BEFORE any renaming
        $oldNamespace = $this->getOldNamespace($oldDir);
        if (!$oldNamespace) {
            $output->writeln('<error>Could not determine the old namespace. Aborting.</error>');
            return;
        }

        // Rename main plugin file
        rename($oldMainFile, $newMainFile);
        $output->writeln("✓ Renamed '{$oldMainFile}' to '{$newMainFile}'");

        // Update plugin name in main file
        $this->updatePluginName($newMainFile, $newName, $output);

        // Update namespaces
        $this->updateNamespaces($oldDir, $oldNamespace, $newNamespace, $output);

        // Store new namespace for next time
        file_put_contents($oldDir . '/.wpmoo', $newNamespace);
        $output->writeln("✓ Saved new namespace '{$newNamespace}' to .wpmoo file");
    }

    /**
     * Updates the plugin name in the main plugin file.
     *
     * @param string $file
     * @param string $newName
     * @param OutputInterface $output
     */
    private function updatePluginName(string $file, string $newName, OutputInterface $output)
    {
        $content = file_get_contents($file);
        $newContent = preg_replace('/^(Plugin Name: ).*$/m', '$1' . $newName, $content);
        file_put_contents($file, $newContent);
        $output->writeln("✓ Updated Plugin Name to '{$newName}' in '{$file}'");
    }

    /**
     * Updates the namespaces in all PHP files.
     *
     * @param string $dir
     * @param string $oldNamespace
     * @param string $newNamespace
     * @param OutputInterface $output
     */
    private function updateNamespaces(string $dir, string $oldNamespace, string $newNamespace, OutputInterface $output)
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getRealPath();
            $content = file_get_contents($path);
            // Replace both namespace declarations and fully qualified class names
            $newContent = str_replace(
                [$oldNamespace . '\\', 'namespace ' . $oldNamespace . ';'],
                [$newNamespace . '\\', 'namespace ' . $newNamespace . ';'],
                $content
            );

            if ($content !== $newContent) {
                file_put_contents($path, $newContent);
                $output->writeln("✓ Updated namespace in '{$path}'");
            }
        }
    }

    /**
     * Gets the old namespace from .wpmoo file or composer.json.
     *
     * @param string $dir
     * @return string|null
     */
    private function getOldNamespace(string $dir): ?string
    {
        $wpmooFile = $dir . '/.wpmoo';
        if (file_exists($wpmooFile)) {
            return trim(file_get_contents($wpmooFile));
        }

        $composerFile = $dir . '/composer.json';
        if (file_exists($composerFile)) {
            $composerData = json_decode(file_get_contents($composerFile), true);
            if (isset($composerData['autoload']['psr-4'])) {
                $namespaces = $composerData['autoload']['psr-4'];
                // Return the first namespace found (assuming one primary namespace)
                return rtrim(key($namespaces), '\\');
            }
        }

        return null;
    }

    /**
     * Identify the project type.
     *
     * @return array
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
}

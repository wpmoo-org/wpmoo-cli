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
        $output->writeln('<info> Rename WPMoo Plugin</info>');
        $output->writeln('<info>------------------------------------------------</info>');
        $output->writeln('');

        // 1. Check project context
        $projectInfo = $this->identifyProject();
        if ($projectInfo['type'] !== 'wpmoo-plugin') {
            $output->writeln('<error>The "rename" command can only be used inside a WPMoo-based plugin.</error>');
            return 1;
        }

        $oldDir = dirname($projectInfo['main_file']);
        $oldProjectConfig = $this->getProjectConfig($oldDir);
        $oldPluginFileHeaders = $this->getPluginFileHeaders($projectInfo['main_file']);

        // Merge config with actual file headers, prioritizing config if set
        $currentProjectInfo = array_merge($oldPluginFileHeaders, $oldProjectConfig);

        if (empty($currentProjectInfo['name'])) {
            $output->writeln('→ You are renaming your plugin for the first time.');
            $output->writeln('');
        }

        $output->writeln('<comment>Current Project Info:</comment>');
        $output->writeln("<comment>- Plugin Name:</comment>   " . ($currentProjectInfo['name'] ?: '<not set>'));
        $output->writeln("<comment>- Namespace:</comment>     " . ($currentProjectInfo['namespace'] ?: '<not set>'));
        $output->writeln("<comment>- Text Domain:</comment>   " . ($currentProjectInfo['text_domain'] ?: '<not set>'));
        $output->writeln('');

        $output->writeln("---------------------------------------------------------------------------------");
        $output->writeln("<comment> The new plugin name and namespace can not contain \"WPMoo\"</comment>");
        $output->writeln("---------------------------------------------------------------------------------");
        $output->writeln('');

        // 2. Ask for new names
        $helper = $this->getHelper('question');

        // Plugin Name
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

        // Namespace
        $recommendedNamespace = str_replace(' ', '', ucwords($newName));
        $namespaceConfirmationQuestion = new ConfirmationQuestion(
            "❓ Recommended Namespace: <comment>{$recommendedNamespace}</comment> (Accept Y/n) [default: Y]: ",
            true
        );
        if ($helper->ask($input, $output, $namespaceConfirmationQuestion)) {
            $newNamespace = $recommendedNamespace;
            $output->writeln("→ Using Namespace: <info>{$newNamespace}</info>");
        } else {
            $namespaceQuestion = new Question('❓ Enter custom Namespace: ');
            $namespaceQuestion->setValidator(function ($answer) {
                if (empty($answer)) {
                    throw new \RuntimeException('Namespace cannot be empty.');
                }
                if (stripos($answer, 'WPMoo') !== false) {
                    throw new \RuntimeException('Namespace cannot contain "WPMoo".');
                }
                // Allow namespaces with backslashes (sub-namespaces)
                // Each part should be a valid PHP identifier
                $parts = explode('\\', $answer);
                foreach ($parts as $part) {
                    if ($part !== '' && !preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $part)) {
                        throw new \RuntimeException('Namespace is not valid.');
                    }
                }
                return $answer;
            });
            $newNamespace = $helper->ask($input, $output, $namespaceQuestion);
        }

        // Text Domain
        $recommendedTextDomain = $this->slugify($newName);
        $textDomainConfirmationQuestion = new ConfirmationQuestion(
            "❓ Recommended Text Domain: <comment>{$recommendedTextDomain}</comment> (Accept Y/n) [default: Y]: ",
            true
        );
        if ($helper->ask($input, $output, $textDomainConfirmationQuestion)) {
            $newTextDomain = $recommendedTextDomain;
            $output->writeln("→ Using Text Domain: <info>{$newTextDomain}</info>");
        } else {
            $textDomainQuestion = new Question('❓ Enter custom Text Domain: ');
            $textDomainQuestion->setValidator(function ($answer) {
                if (empty($answer)) {
                    throw new \RuntimeException('Text Domain cannot be empty.');
                }
                if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $answer)) {
                    throw new \RuntimeException('Text Domain is not valid (must be lowercase, hyphen-separated).');
                }
                return $answer;
            });
            $newTextDomain = $helper->ask($input, $output, $textDomainQuestion);
        }

        // 3. Generate new filename and show confirmation
        $newFilename = $this->slugify($newName) . '.php';

        $output->writeln('');
        $output->writeln("→ The new plugin filename will be '<info>{$newFilename}</info>'");
        $output->writeln("→ The new plugin name will be '<info>{$newName}</info>'");
        $output->writeln("→ The new namespace will be '<info>{$newNamespace}</info>'");
        $output->writeln("→ The new text domain will be '<info>{$newTextDomain}</info>'");

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
        $this->renamePlugin(
            $projectInfo,
            $newName,
            $newNamespace,
            $newTextDomain,
            $newFilename,
            $output
        );

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
     * @param string $newTextDomain
     * @param string $newFilename
     * @param OutputInterface $output
     */
    private function renamePlugin(
        array $projectInfo,
        string $newName,
        string $newNamespace,
        string $newTextDomain,
        string $newFilename,
        OutputInterface $output
    ) {
        $oldMainFile = $projectInfo['main_file'];
        $oldDir = dirname($oldMainFile);
        $newMainFile = $oldDir . '/' . $newFilename;

        // Get old project config
        $oldProjectConfig = $this->getProjectConfig($oldDir);
        $oldPluginFileHeaders = $this->getPluginFileHeaders($oldMainFile);

        // Merge config with actual file headers, prioritizing config if set
        $currentProjectInfo = array_merge($oldPluginFileHeaders, $oldProjectConfig);

        $oldName = $currentProjectInfo['name'] ?? '';
        $oldNamespace = $currentProjectInfo['namespace'] ?? '';
        $oldTextDomain = $currentProjectInfo['text_domain'] ?? '';

        if (empty($oldNamespace)) {
            $output->writeln('<error>Could not determine the old namespace from wpmoo-config.yml or composer.json. Aborting.</error>');
            return;
        }
        // If oldTextDomain is not found in config, try to get it from the main plugin file header
        if (empty($oldTextDomain)) {
            $oldTextDomain = $this->getOldTextDomain($oldMainFile);
            if (empty($oldTextDomain)) {
                $output->writeln('<error>Could not determine the old text domain from wpmoo-config.yml or plugin header. Aborting.</error>');
                return;
            }
        }

        // $newTextDomain is now passed as an argument from handleExecute.

        // Rename main plugin file
        if (file_exists($oldMainFile)) {
            rename($oldMainFile, $newMainFile);
            $output->writeln("✓ Renamed '{$oldMainFile}' to '{$newMainFile}'");
        } else {
            $output->writeln("<error>Main plugin file '{$oldMainFile}' not found. Cannot rename.</error>");
            return;
        }


        // Update plugin name and text domain headers in main file
        $this->updatePluginFileHeaders(
            $newMainFile,
            $oldName,
            $newName,
            $oldTextDomain,
            $newTextDomain,
            $output
        );

        // Update namespaces
        $this->updateNamespaces($oldDir, $oldNamespace, $newNamespace, $output);

        // Update plugin name throughout the codebase
        $this->updatePluginNames($oldDir, $oldName, $newName, $output);

        // Update additional references throughout the codebase
        $this->updateGeneralReferences($oldDir, $oldName, $newName, $oldNamespace, $newNamespace, $output);

        // Update text domains
        $this->updateTextDomains($oldDir, $oldTextDomain, $newTextDomain, $output);

        // Update readme.txt
        $this->updateReadmeFile(
            $oldDir . '/readme.txt',
            $oldName,
            $newName,
            $oldTextDomain,
            $newTextDomain,
            $output
        );

        // Store new project config
        $this->saveProjectConfig(
            $oldDir,
            $newName,
            $newNamespace,
            $newTextDomain
        );
        $output->writeln("✓ Saved new project config to wpmoo-config.yml");
    }

    /**
     * Saves the new project configuration to wpmoo-config.yml.
     *
     * @param string $dir
     * @param string $newName
     * @param string $newNamespace
     * @param string $newTextDomain
     */
    private function saveProjectConfig(
        string $dir,
        string $newName,
        string $newNamespace,
        string $newTextDomain
    ) {
        $configFile = $dir . '/wpmoo-config.yml';
        $config = [];
        if (file_exists($configFile)) {
            $config = Yaml::parseFile($configFile);
        }

        $config['project']['name'] = $newName;
        $config['project']['namespace'] = $newNamespace;
        $config['project']['text_domain'] = $newTextDomain;

        file_put_contents($configFile, Yaml::dump($config, 2));
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
     * Updates the Plugin Name and Text Domain headers in the main plugin file.
     *
     * @param string $mainFile The path to the main plugin file.
     * @param string $oldPluginName The old plugin name.
     * @param string $newPluginName The new plugin name.
     * @param string $oldTextDomain The old text domain.
     * @param string $newTextDomain The new text domain.
     * @param OutputInterface $output The output interface.
     */
    private function updatePluginFileHeaders(
        string $mainFile,
        string $oldPluginName,
        string $newPluginName,
        string $oldTextDomain,
        string $newTextDomain,
        OutputInterface $output
    ) {
        $content = file_get_contents($mainFile);
        $originalContent = $content;

        // Update Plugin Name header
        if (!empty($oldPluginName) && $oldPluginName !== $newPluginName) {
            $content = preg_replace_callback('/^(Plugin Name:\s*)' . preg_quote($oldPluginName, '/') . '$/m', function ($matches) use ($newPluginName) {
                return $matches[1] . $newPluginName;
            }, $content);
            $output->writeln("✓ Updated Plugin Name header in '{$mainFile}'");
        } elseif (empty($oldPluginName) && preg_match('/^(Plugin Name:\s*)(.*)$/m', $content)) {
            $content = preg_replace_callback('/^(Plugin Name:\s*)(.*)$/m', function ($matches) use ($newPluginName) {
                return $matches[1] . $newPluginName;
            }, $content);
            $output->writeln("✓ Updated Plugin Name header in '{$mainFile}' (from undetermined to '{$newPluginName}')");
        }

        // Update Text Domain header - WordPress uses a specific format for plugin headers
        // Text Domain can appear with various spacing formats in the plugin header
        $textDomainPattern = '/^(\s*\*\s*Text Domain:\s*)' . preg_quote($oldTextDomain, '/') . '(\s*)$/m';
        if (!empty($oldTextDomain) && $oldTextDomain !== $newTextDomain) {
            $content = preg_replace_callback($textDomainPattern, function ($matches) use ($newTextDomain) {
                return $matches[1] . $newTextDomain . $matches[2];
            }, $content);
            $output->writeln("✓ Updated Text Domain header in '{$mainFile}'");
        } elseif (empty($oldTextDomain) && preg_match($textDomainPattern, $content)) {
            // This case shouldn't normally happen since oldTextDomain is fetched from the file,
            // but handling for completeness
            $content = preg_replace_callback($textDomainPattern, function ($matches) use ($newTextDomain) {
                return $matches[1] . $newTextDomain . $matches[2];
            }, $content);
            $output->writeln("✓ Updated Text Domain header in '{$mainFile}' (from undetermined to '{$newTextDomain}')");
        } elseif (!preg_match($textDomainPattern, $content) && !empty($newTextDomain)) {
            // If no Text Domain header exists, add it after Plugin Name in the header
            $pluginNamePattern = '/^(\s*\*\s*Plugin Name:\s*' . preg_quote($oldPluginName, '/') . ')(\s*)$/m';
            if (preg_match($pluginNamePattern, $content)) {
                $content = preg_replace_callback($pluginNamePattern, function ($matches) use ($newTextDomain) {
                    return $matches[1] . $matches[2] . "\n * Text Domain: " . $newTextDomain;
                }, $content);
                $output->writeln("✓ Added Text Domain header to '{$mainFile}'");
            } else {
                // Alternative: try to find the Plugin Name in the new format
                $pluginNamePattern = '/^(\s*\*\s*Plugin Name:\s*' . preg_quote($newPluginName, '/') . ')(\s*)$/m';
                if (preg_match($pluginNamePattern, $content)) {
                    $content = preg_replace_callback($pluginNamePattern, function ($matches) use ($newTextDomain) {
                        return $matches[1] . $matches[2] . "\n * Text Domain: " . $newTextDomain;
                    }, $content);
                    $output->writeln("✓ Added Text Domain header to '{$mainFile}'");
                }
            }
        }


        if ($content !== $originalContent) {
            file_put_contents($mainFile, $content);
        }
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

            // Also update namespace in docblocks (like @package WPMooStarter)
            $newContent = preg_replace_callback('/(@package\s+)' . preg_quote($oldNamespace, '/') . '/', function ($matches) use ($newNamespace) {
                return $matches[1] . $newNamespace;
            }, $newContent);

            if ($content !== $newContent) {
                file_put_contents($path, $newContent);
                $output->writeln("✓ Updated namespace in '{$path}'");
            }
        }
    }

    /**
     * Updates the text domains in all PHP files.
     *
     * @param string $dir
     * @param string $oldTextDomain
     * @param string $newTextDomain
     * @param OutputInterface $output
     */
    private function updateTextDomains(string $dir, string $oldTextDomain, string $newTextDomain, OutputInterface $output)
    {
        if (empty($oldTextDomain) || empty($newTextDomain) || $oldTextDomain === $newTextDomain) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getRealPath();
            $content = file_get_contents($path);

            // This regex will find strings that look like text domains within translation functions
            // and load_plugin_textdomain. It's a bit broad but should cover most cases.
            $pattern = '/(\'|\")' . preg_quote($oldTextDomain, '/') . '(\'|\")/';
            $replacement = '$1' . $newTextDomain . '$2';
            $newContent = preg_replace($pattern, $replacement, $content);

            if ($content !== $newContent) {
                file_put_contents($path, $newContent);
                $output->writeln("✓ Updated text domain in '{$path}' (from '{$oldTextDomain}' to '{$newTextDomain}')");
            }
        }
    }

    /**
     * Updates general references throughout the codebase.
     *
     * @param string $dir The directory to process.
     * @param string $oldPluginName The old plugin name.
     * @param string $newPluginName The new plugin name.
     * @param string $oldNamespace The old namespace.
     * @param string $newNamespace The new namespace.
     * @param OutputInterface $output The output interface.
     */
    private function updateGeneralReferences(string $dir, string $oldPluginName, string $newPluginName, string $oldNamespace, string $newNamespace, OutputInterface $output)
    {
        if (empty($oldPluginName) || empty($newPluginName) || $oldPluginName === $newPluginName) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isDir() || ($file->getExtension() !== 'php' && $file->getExtension() !== 'js' && $file->getExtension() !== 'txt' && $file->getExtension() !== 'html' && $file->getExtension() !== 'css' && $file->getExtension() !== 'md')) {
                continue;
            }

            $path = $file->getRealPath();
            $content = file_get_contents($path);
            $newContent = $content;

            // Update @package references (both old plugin name and old namespace)
            $newContent = preg_replace_callback('/(@package\s+)' . preg_quote($oldNamespace, '/') . '/', function ($matches) use ($newNamespace) {
                return $matches[1] . $newNamespace;
            }, $newContent);
            $newContent = preg_replace_callback('/(@package\s+)' . preg_quote($oldPluginName, '/') . '/', function ($matches) use ($newPluginName) {
                return $matches[1] . $newPluginName;
            }, $newContent);

            // Update @since, @version, etc. references if they contain the old plugin name
            $newContent = preg_replace_callback('/(@since\s+.*?)(?<!\w)' . preg_quote($oldPluginName, '/') . '(?!\w)/', function ($matches) use ($newPluginName) {
                return $matches[1] . $newPluginName;
            }, $newContent);
            $newContent = preg_replace_callback('/(@version\s+.*?)(?<!\w)' . preg_quote($oldPluginName, '/') . '(?!\w)/', function ($matches) use ($newPluginName) {
                return $matches[1] . $newPluginName;
            }, $newContent);

            // Update any other references to the old plugin name that might appear in comments/docblocks
            // Make sure we don't replace parts of other words
            $pattern = '/(?<!\w)' . preg_quote($oldPluginName, '/') . '(?!\w)/';
            $newContent = preg_replace($pattern, $newPluginName, $newContent);

            if ($content !== $newContent) {
                file_put_contents($path, $newContent);
                $output->writeln("✓ Updated general references in '{$path}'");
            }
        }
    }

    /**
     * Updates the plugin names in all PHP files.
     *
     * @param string $dir The directory to process.
     * @param string $oldPluginName The old plugin name.
     * @param string $newPluginName The new plugin name.
     * @param OutputInterface $output The output interface.
     */
    private function updatePluginNames(string $dir, string $oldPluginName, string $newPluginName, OutputInterface $output)
    {
        if (empty($oldPluginName) || empty($newPluginName) || $oldPluginName === $newPluginName) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isDir() || ($file->getExtension() !== 'php' && $file->getExtension() !== 'js' && $file->getExtension() !== 'txt' && $file->getExtension() !== 'html' && $file->getExtension() !== 'css')) {
                continue;
            }

            $path = $file->getRealPath();
            $content = file_get_contents($path);

            // Replace old plugin name with new plugin name, preserving case where appropriate
            $newContent = $content;

            // Replace in comments (like package, since, version tags)
            $newContent = preg_replace_callback('/(@package\s+)' . preg_quote($oldPluginName, '/') . '/', function ($matches) use ($newPluginName) {
                return $matches[1] . $newPluginName;
            }, $newContent);
            $newContent = preg_replace_callback('/(@subpackage\s+)' . preg_quote($oldPluginName, '/') . '/', function ($matches) use ($newPluginName) {
                return $matches[1] . $newPluginName;
            }, $newContent);

            // Also replace the "WPMoo Starter" style full name
            $newContent = str_replace($oldPluginName, $newPluginName, $newContent);

            if ($content !== $newContent) {
                file_put_contents($path, $newContent);
                $output->writeln("✓ Updated plugin name in '{$path}' (from '{$oldPluginName}' to '{$newPluginName}')");
            }
        }
    }

    /**
     * Updates the readme.txt file.
     *
     * @param string $readmeFilePath The path to the readme.txt file.
     * @param string $oldPluginName The old plugin name.
     * @param string $newPluginName The new plugin name.
     * @param string $oldTextDomain The old text domain.
     * @param string $newTextDomain The new text domain.
     * @param OutputInterface $output The output interface.
     */
    private function updateReadmeFile(string $readmeFilePath, string $oldPluginName, string $newPluginName, string $oldTextDomain, string $newTextDomain, OutputInterface $output)
    {
        if (!file_exists($readmeFilePath)) {
            return;
        }

        $content = file_get_contents($readmeFilePath);
        $originalContent = $content;

        // Update Plugin Name
        if (!empty($oldPluginName) && $oldPluginName !== $newPluginName) {
            $content = preg_replace_callback('/(=== )' . preg_quote($oldPluginName, '/') . '( ===)/', function ($matches) use ($newPluginName) {
                return $matches[1] . $newPluginName . $matches[2];
            }, $content);
            $content = preg_replace_callback('/(== )' . preg_quote($oldPluginName, '/') . '( ==)/', function ($matches) use ($newPluginName) {
                return $matches[1] . $newPluginName . $matches[2];
            }, $content);
        }

        // Update Stable tag
        if (!empty($oldTextDomain) && $oldTextDomain !== $newTextDomain) {
            $content = preg_replace_callback('/(Stable tag:\s*)' . preg_quote($oldTextDomain, '/') . '/', function ($matches) use ($newTextDomain) {
                return $matches[1] . $newTextDomain;
            }, $content);
        }

        // General replacement for old plugin name to new plugin name
        if (!empty($oldPluginName) && $oldPluginName !== $newPluginName) {
            $content = str_replace($oldPluginName, $newPluginName, $content);
        }

        // General replacement for old text domain to new text domain
        if (!empty($oldTextDomain) && $oldTextDomain !== $newTextDomain) {
            $content = str_replace($oldTextDomain, $newTextDomain, $content);
        }

        if ($content !== $originalContent) {
            file_put_contents($readmeFilePath, $content);
            $output->writeln("✓ Updated readme.txt");
        }
    }

    /**
     * Extracts the old text domain from the main plugin file.
     *
     * @param string $mainFile
     * @return string|null
     */
    private function getOldTextDomain(string $mainFile): ?string
    {
        $content = file_get_contents($mainFile);
        if (preg_match('/^[ \t\/*#@]*Text Domain:\s*(.*)$/im', $content, $matches)) {
            return trim($matches[1]);
        }

        // Fallback to slugified plugin name if Text Domain header is not found
        if (preg_match('/^[ \t\/*#@]*Plugin Name:\s*(.*)$/im', $content, $matches)) {
            // Need to slugify the plugin name to get the text domain
            return $this->slugify(trim($matches[1]));
        }

        return null;
    }

    /**
     * Extracts all relevant plugin header information from the main plugin file.
     *
     * @param string $mainFile The path to the main plugin file.
     * @return array An associative array of header fields.
     */
    private function getPluginFileHeaders(string $mainFile): array
    {
        if (!file_exists($mainFile)) {
            return [];
        }

        $headers = [];
        $content = file_get_contents($mainFile);

        $header_keys = [
            'name'        => 'Plugin Name',
            'description' => 'Description',
            'author'      => 'Author',
            'license'     => 'License',
            'license_uri' => 'License URI',
            'text_domain' => 'Text Domain',
        ];

        foreach ($header_keys as $key => $value) {
            if (preg_match('/^[ \t\/*#@]*' . preg_quote($value, '/') . ':\s*(.*)$/im', $content, $matches)) {
                $headers[$key] = trim($matches[1]);
            } else {
                $headers[$key] = ''; // Ensure all keys are present
            }
        }

        return $headers;
    }

    /**
     * Converts a string to a slug (lowercase, hyphens instead of spaces).
     * This is a simplified version of WordPress's sanitize_title.
     *
     * @param string $text
     * @return string
     */
    private function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text); // Replace non-alphanumeric with hyphen
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text); // Transliterate
        $text = strtolower($text); // Convert to lowercase
        $text = preg_replace('~[^-\w]+~', '', $text); // Remove unwanted characters
        $text = trim($text, '-'); // Trim hyphens from beginning and end
        $text = preg_replace('~-+~', '-', $text); // Replace multiple hyphens with a single one

        return $text;
    }

    /**
     * Gets the project configuration from wpmoo-config.yml or composer.json.
     *
     * @param string $dir
     * @return array
     */
    private function getProjectConfig(string $dir): array
    {
        $configFile = $dir . '/wpmoo-config.yml';
        if (file_exists($configFile)) {
            $config = Yaml::parseFile($configFile);
            if (isset($config['project'])) {
                // Ensure all expected keys are present, even if empty
                return array_merge([
                    'name' => '',
                    'namespace' => '',
                    'text_domain' => '',
                    'author' => '',
                    'description' => '',
                    'license' => '',
                    'license_uri' => '',
                ], $config['project']);
            }
        }

        $composerFile = $dir . '/composer.json';
        if (file_exists($composerFile)) {
            $composerData = json_decode(file_get_contents($composerFile), true);
            if (isset($composerData['autoload']['psr-4'])) {
                $namespaces = $composerData['autoload']['psr-4'];
                $namespace = rtrim(key($namespaces), '\\');
                $name = key($composerData['autoload']['psr-4']); // Assuming the namespace key is the project name
                $name = rtrim($name, '\\');
                $textDomain = $this->slugify($name);

                return [
                    'name' => $name,
                    'namespace' => $namespace,
                    'text_domain' => $textDomain,
                    'author' => '',
                    'description' => '',
                    'license' => '',
                    'license_uri' => '',
                ];
            }
        }

        return [
            'name' => '',
            'namespace' => '',
            'text_domain' => '',
            'author' => '',
            'description' => '',
            'license' => '',
            'license_uri' => '',
        ];
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

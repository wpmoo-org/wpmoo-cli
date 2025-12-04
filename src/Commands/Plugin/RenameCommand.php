<?php

namespace WPMoo\CLI\Commands\Plugin;

use WPMoo\CLI\Support\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

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
    public function handle_execute(InputInterface $input, OutputInterface $output): int
    {
        $symfony_io = new SymfonyStyle($input, $output);

        $symfony_io->title('Rename WPMoo Plugin');

        // 1. Check project context.
        $project_info = $this->identify_project();
        if ($project_info['type'] !== 'wpmoo-plugin') {
            $symfony_io->error('The "rename" command can only be used inside a WPMoo-based plugin.');
            return 1;
        }

        $old_dir = dirname($project_info['main_file']);
        $old_project_config = $this->get_project_config($old_dir);
        $old_plugin_file_headers = $this->get_plugin_file_headers($project_info['main_file']);

        // Merge config with actual file headers, prioritizing config if set.
        $current_project_info = array_merge($old_plugin_file_headers, $old_project_config);

        if (empty($current_project_info['name'])) {
            $symfony_io->note('You are renaming your plugin for the first time.');
        }

        $symfony_io->section('Current Project Info');
        $symfony_io->listing(
            [
                'Plugin Name:   ' . ( $current_project_info['name'] ?: '<not set>' ),
                'Namespace:     ' . ( $current_project_info['namespace'] ?: '<not set>' ),
                'Text Domain:   ' . ( $current_project_info['text_domain'] ?: '<not set>' ),
            ]
        );

        $symfony_io->warning('The new plugin name and namespace can not contain "WPMoo"');

        // 2. Ask for new names.
        $new_name = $symfony_io->ask(
            'Plugin name',
            null,
            function ($answer) {
                if (empty($answer)) {
                    throw new \RuntimeException('Plugin name cannot be empty.');
                }
                if (stripos($answer, 'WPMoo') !== false) {
                    throw new \RuntimeException('Plugin name cannot contain "WPMoo".');
                }
                return $answer;
            }
        );

        // Namespace.
        $recommended_namespace = str_replace(' ', '', ucwords($new_name));
        $new_namespace = $symfony_io->ask(
            'Namespace',
            $recommended_namespace,
            function ($answer) {
                if (empty($answer)) {
                    throw new \RuntimeException('Namespace cannot be empty.');
                }
                if (stripos($answer, 'WPMoo') !== false) {
                    throw new \RuntimeException('Namespace cannot contain "WPMoo".');
                }

                // Allow namespaces with backslashes (sub-namespaces).
                // Each part should be a valid PHP identifier.
                $parts = explode('\\', $answer);
                foreach ($parts as $part) {
                    if ($part !== '' && ! preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $part)) {
                        throw new \RuntimeException('Namespace is not valid: "' . $part . '" is not a valid PHP identifier.');
                    }

                    // Additional validation for WordPress/WPMoo conventions.
                    // Namespace parts should follow PascalCase (uppercase first letter, no underscores in middle).
                    if ($part !== '' && ! preg_match('/^[A-Z][a-zA-Z0-9]*$/', $part)) {
                        throw new \RuntimeException('Namespace part "' . $part . '" should follow PascalCase convention (start with uppercase letter, only letters and numbers allowed).');
                    }
                }
                return $answer;
            }
        );

        // Text Domain.
        $recommended_text_domain = $this->slugify($new_name);
        $new_text_domain = $symfony_io->ask(
            'Text Domain',
            $recommended_text_domain,
            function ($answer) {
                if (empty($answer)) {
                    throw new \RuntimeException('Text Domain cannot be empty.');
                }
                if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $answer)) {
                    throw new \RuntimeException('Text Domain is not valid (must be lowercase, hyphen-separated).');
                }
                return $answer;
            }
        );

        // 3. Generate new filename and show confirmation.
        $new_filename = $this->slugify($new_name) . '.php';

        $symfony_io->section('Summary of Changes');
        $symfony_io->listing(
            [
                "New plugin filename: <info>{$new_filename}</info>",
                "New plugin name:     <info>{$new_name}</info>",
                "New namespace:         <info>{$new_namespace}</info>",
                "New text domain:       <info>{$new_text_domain}</info>",
            ]
        );

        if ('yes' !== $symfony_io->ask('Continue with renaming? (yes/no)', 'no')) {
            $symfony_io->note('Operation cancelled.');
            return 0;
        }

        $symfony_io->info('Renaming plugin...');

        // 4. Perform renaming.
        $this->rename_plugin(
            $project_info,
            $new_name,
            $new_namespace,
            $new_text_domain,
            $new_filename,
            $symfony_io
        );

        $symfony_io->success('Plugin renamed successfully!');

        return 0;
    }

    /**
     * Renames the plugin.
     *
     * @param array $project_info
     * @param string $new_name
     * @param string $new_namespace
     * @param string $new_text_domain
     * @param string $new_filename
     * @param OutputInterface $output
     */
    private function rename_plugin(
        array $project_info,
        string $new_name,
        string $new_namespace,
        string $new_text_domain,
        string $new_filename,
        SymfonyStyle $io
    ) {
        $old_main_file = $project_info['main_file'];
        $old_dir = dirname($old_main_file);
        $newMainFile = $old_dir . '/' . $new_filename;

        // Get old project config.
        $old_project_config = $this->get_project_config($old_dir);
        $old_plugin_file_headers = $this->get_plugin_file_headers($old_main_file);

        // Merge config with actual file headers, prioritizing config if set.
        $current_project_info = array_merge($old_plugin_file_headers, $old_project_config);

        $old_name = $current_project_info['name'] ?? '';
        $old_namespace = $current_project_info['namespace'] ?? '';
        $old_text_domain = $current_project_info['text_domain'] ?? '';

        if (empty($old_namespace)) {
            $io->error('Could not determine the old namespace from wpmoo-config.yml or composer.json. Aborting.');
            return;
        }
        // If oldTextDomain is not found in config, try to get it from the main plugin file header.
        if (empty($old_text_domain)) {
            $old_text_domain = $this->get_old_text_domain($old_main_file);
            if (empty($old_text_domain)) {
                $io->error('Could not determine the old text domain from wpmoo-config.yml or plugin header. Aborting.');
                return;
            }
        }

        // Rename main plugin file.
        if (file_exists($old_main_file)) {
            rename($old_main_file, $newMainFile);
            $io->writeln("✓ Renamed '{$old_main_file}' to '{$newMainFile}'");
        } else {
            $io->error("Main plugin file '{$old_main_file}' not found. Cannot rename.");
            return;
        }

        // Update plugin name and text domain headers in main file.
        $this->update_plugin_file_headers(
            $newMainFile,
            $old_name,
            $new_name,
            $old_text_domain,
            $new_text_domain,
            $io
        );

        // Update namespaces.
        $this->update_namespaces($old_dir, $old_namespace, $new_namespace, $io);

        // Update plugin name throughout the codebase.
        $this->update_plugin_names($old_dir, $old_name, $new_name, $io);

        // Update additional references throughout the codebase.
        $this->update_general_references($old_dir, $old_name, $new_name, $old_namespace, $new_namespace, $io);

        // Update text domains.
        $this->update_text_domains($old_dir, $old_text_domain, $new_text_domain, $io);

        // Update readme.txt.
        $this->update_readme_file(
            $old_dir . '/readme.txt',
            $old_name,
            $new_name,
            $old_text_domain,
            $new_text_domain,
            $io
        );

        // Store new project config.
        $this->save_project_config(
            $old_dir,
            $new_name,
            $new_namespace,
            $new_text_domain
        );
        $io->writeln('✓ Saved new project config to wpmoo-config.yml');

        // Run composer dump-autoload to refresh the autoloader with new namespace.
        $this->run_composer_dump_autoload($old_dir, $io);

        // Inform the user about plugin reactivation if file was renamed.
        $this->inform_about_plugin_reactivation($io, $old_main_file, $newMainFile);
    }

    /**
     * Runs composer dump-autoload to refresh the autoloader after namespace changes.
     *
     * @param string $dir The project directory.
     * @param OutputInterface $output The output interface.
     */
    private function run_composer_dump_autoload(string $dir, SymfonyStyle $io): void
    {
        // Check if composer.json exists in the project directory.
        $composerJsonPath = $dir . '/composer.json';
        if (! file_exists($composerJsonPath)) {
            $io->note('No composer.json found, skipping autoload dump.');
            return;
        }

        $io->writeln('<success>Running composer dump-autoload...</success>');

        // Check if composer is available.
        $result_code = 0;
        $output_lines = [];

        // Using @ to suppress errors in case composer is not found.
        $hasComposer = ! empty(trim(shell_exec('command -v composer')));
        if ($hasComposer) {
            // Run composer dump-autoload in the project directory.
            $command = 'cd ' . escapeshellarg($dir) . ' && composer dump-autoload';
            exec($command, $output_lines, $result_code);
        } else {
            // Check if composer.phar exists in the project directory.
            $composerPharPath = $dir . '/composer.phar';
            if (file_exists($composerPharPath)) {
                $command = 'cd ' . escapeshellarg($dir) . ' && php composer.phar dump-autoload';
                exec($command, $output_lines, $result_code);
            } else {
                $io->error('Composer not found and no composer.phar in project directory. Please run composer dump-autoload manually.');
                return;
            }
        }

        if ($result_code === 0) {
            $io->writeln('<success>Successfully updated autoloader.</success>');
        } else {
            $io->error('Failed to run composer dump-autoload. Please run it manually.');
            if (! empty($output_lines)) {
                $io->listing($output_lines);
            }
        }
    }

    /**
     * Informs the user about plugin reactivation and directory renaming after renaming.
     *
     * @param SymfonyStyle $io The output interface.
     * @param string $old_main_file The old main plugin file path.
     * @param string $newMainFile The new main plugin file path.
     */
    private function inform_about_plugin_reactivation(SymfonyStyle $io, string $old_main_file, string $new_main_file): void
    {
        // Check if the main plugin file was actually renamed.
        $old_filename = basename($old_main_file);
        $new_filename = basename($new_main_file);

        if ($old_filename !== $new_filename) {
            $io->title('IMPORTANT NOTES ABOUT RENAME:');
            $io->listing(
                [
                    "Plugin file has been renamed from '{$old_filename}' to '{$new_filename}'.",
                    'If the plugin was active in WordPress, it may now show a fatal error.',
                    'WordPress caches plugin paths, so you must deactivate and reactivate the plugin in WordPress admin to update its internal references.',
                    "If activation fails after reactivation, you may need to manually update the wp_options table ('active_plugins' option) and wp_plugin_paths cache.",
                    'If you want the directory name to match the new plugin name, you should manually rename the plugin directory and update any references (e.g., in git, symlinks, etc.).',
                    'Remember to update any deployment configurations if the directory name changes.',
                ]
            );
        }
    }

    /**
     * Saves the new project configuration to wpmoo-config.yml.
     *
     * @param string $dir
     * @param string $new_name
     * @param string $new_namespace
     * @param string $new_text_domain
     */
    private function save_project_config(
        string $dir,
        string $new_name,
        string $new_namespace,
        string $new_text_domain
    ) {
        $config_file = $dir . '/wpmoo-config.yml';
        $config = [];
        if (file_exists($config_file)) {
            $config = Yaml::parseFile($config_file);
        }

        $config['project']['name'] = $new_name;
        $config['project']['namespace'] = $new_namespace;
        $config['project']['text_domain'] = $new_text_domain;

        file_put_contents($config_file, Yaml::dump($config, 2));
    }

    /**
     * Updates the plugin name in the main plugin file.
     *
     * @param string $file
     * @param string $new_name
     * @param OutputInterface $output
     */
    private function update_plugin_name(string $file, string $new_name, SymfonyStyle $io)
    {
        $content = file_get_contents($file);
        $new_content = preg_replace('/^(Plugin Name: ).*$/m', '$1' . $new_name, $content);
        file_put_contents($file, $new_content);
        $io->writeln("✓ Updated Plugin Name to '{$new_name}' in '{$file}'");
    }

    /**
     * Updates the Plugin Name and Text Domain headers in the main plugin file.
     *
     * @param string $main_file The path to the main plugin file.
     * @param string $old_plugin_name The old plugin name.
     * @param string $new_plugin_name The new plugin name.
     * @param string $old_text_domain The old text domain.
     * @param string $new_text_domain The new text domain.
     * @param SymfonyStyle $io The output interface.
     */
    private function update_plugin_file_headers(
        string $main_file,
        string $old_plugin_name,
        string $new_plugin_name,
        string $old_text_domain,
        string $new_text_domain,
        SymfonyStyle $io
    ) {
        $content = file_get_contents($main_file);
        $original_content = $content;

        // Update Plugin Name header.
        if (! empty($old_plugin_name) && $old_plugin_name !== $new_plugin_name) {
            $content = preg_replace_callback(
                '/^(Plugin Name:\s*)' . preg_quote($old_plugin_name, '/') . '$/m',
                function ($matches) use ($new_plugin_name) {
                    return $matches[1] . $new_plugin_name;
                },
                $content
            );
            $io->writeln("✓ Updated Plugin Name header in '{$main_file}'");
        } elseif (empty($old_plugin_name) && preg_match('/^(Plugin Name:\s*)(.*)$/m', $content)) {
            $content = preg_replace_callback(
                '/^(Plugin Name:\s*)(.*)$/m',
                function ($matches) use ($new_plugin_name) {
                    return $matches[1] . $new_plugin_name;
                },
                $content
            );
            $io->writeln("✓ Updated Plugin Name header in '{$main_file}' (from undetermined to '{$new_plugin_name}')");
        }

        // Update Text Domain header - WordPress uses a specific format for plugin headers.
        // Text Domain can appear with various spacing formats in the plugin header.
        $text_domain_pattern = '/^(\s*\*\s*Text Domain:\s*)' . preg_quote($old_text_domain, '/') . '(\s*)$/m';
        if (! empty($old_text_domain) && $old_text_domain !== $new_text_domain) {
            $content = preg_replace_callback(
                $text_domain_pattern,
                function ($matches) use ($new_text_domain) {
                    return $matches[1] . $new_text_domain . $matches[2];
                },
                $content
            );
            $io->writeln("✓ Updated Text Domain header in '{$main_file}'");
        } elseif (empty($old_text_domain) && preg_match($text_domain_pattern, $content)) {
            // This case shouldn't normally happen since oldTextDomain is fetched from the file, but handling for completeness.
            $content = preg_replace_callback(
                $text_domain_pattern,
                function ($matches) use ($new_text_domain) {
                    return $matches[1] . $new_text_domain . $matches[2];
                },
                $content
            );
            $io->writeln("✓ Updated Text Domain header in '{$main_file}' (from undetermined to '{$new_text_domain}')");
        } elseif (! preg_match($text_domain_pattern, $content) && ! empty($new_text_domain)) {
            // If no Text Domain header exists, add it after Plugin Name in the header.
            $plugin_name_pattern = '/^(\s*\*\s*Plugin Name:\s*' . preg_quote($old_plugin_name, '/') . ')(\s*)$/m';
            if (preg_match($plugin_name_pattern, $content)) {
                $content = preg_replace_callback(
                    $plugin_name_pattern,
                    function ($matches) use ($new_text_domain) {
                        return $matches[1] . $matches[2] . "\n * Text Domain: " . $new_text_domain;
                    },
                    $content
                );
                $io->writeln("✓ Added Text Domain header to '{$main_file}'");
            } else {
                // Alternative: try to find the Plugin Name in the new format.
                $plugin_name_pattern = '/^(\s*\*\s*Plugin Name:\s*' . preg_quote($new_plugin_name, '/') . ')(\s*)$/m';
                if (preg_match($plugin_name_pattern, $content)) {
                    $content = preg_replace_callback(
                        $plugin_name_pattern,
                        function ($matches) use ($new_text_domain) {
                            return $matches[1] . $matches[2] . "\n * Text Domain: " . $new_text_domain;
                        },
                        $content
                    );
                    $io->writeln("✓ Added Text Domain header to '{$main_file}'");
                }
            }
        }

        if ($content !== $original_content) {
            file_put_contents($main_file, $content);
        }
    }

    /**
     * Updates the namespaces in all PHP files.
     *
     * @param string $dir
     * @param string $old_namespace
     * @param string $new_namespace
     * @param SymfonyStyle $io
     */
    private function update_namespaces(string $dir, string $old_namespace, string $new_namespace, SymfonyStyle $io)
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getRealPath();
            $content = file_get_contents($path);
            // Replace both namespace declarations and fully qualified class names.
            $new_content = str_replace(
                [ $old_namespace . '\\', 'namespace ' . $old_namespace . ';' ],
                [ $new_namespace . '\\', 'namespace ' . $new_namespace . ';' ],
                $content
            );

            // Also update namespace in docblocks (like @package WPMooStarter).
            $new_content = preg_replace_callback(
                '/(@package\s+)' . preg_quote($old_namespace, '/') . '/',
                function ($matches) use ($new_namespace) {
                    return $matches[1] . $new_namespace;
                },
                $new_content
            );

            if ($content !== $new_content) {
                file_put_contents($path, $new_content);
                $io->writeln("✓ Updated namespace in '{$path}'");
            }
        }
    }

    /**
     * Updates the text domains in all PHP files.
     *
     * @param string $dir
     * @param string $old_text_domain
     * @param string $new_text_domain
     * @param OutputInterface $output
     */
    private function update_text_domains(string $dir, string $old_text_domain, string $new_text_domain, SymfonyStyle $io)
    {
        if (empty($old_text_domain) || empty($new_text_domain) || $old_text_domain === $new_text_domain) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getRealPath();
            $content = file_get_contents($path);

            // Pattern to match text domains in translation functions.
            $functions = [ '__', '_e', '_n', '_x', '_ex', '_nx', '_n_noop', '_nx_noop' ];
            $new_content = $content;

            foreach ($functions as $func) {
                $pattern = '/(\b' . preg_quote($func, '/') . '\s*\(\s*["\'][^"\']*["\']\s*,\s*["\'])' . preg_quote($old_text_domain, '/') . '(["\'])/';
                $new_content = preg_replace_callback(
                    $pattern,
                    function ($matches) use ($new_text_domain) {
                        return $matches[1] . $new_text_domain . $matches[2];
                    },
                    $new_content
                );
            }

            // Also update load_plugin_textdomain calls.
            $load_text_domain_pattern = '/(load_plugin_textdomain\s*\(\s*["\'])' . preg_quote($old_text_domain, '/') . '(["\'])/';
            $new_content = preg_replace_callback(
                $load_text_domain_pattern,
                function ($matches) use ($new_text_domain) {
                    return $matches[1] . $new_text_domain . $matches[2];
                },
                $new_content
            );

            // Also update other function calls that might contain the text domain.
            $boot_pattern = '/(WPMoo\\\WordPress\\\Bootstrap::instance\(\)->boot\(\s*[^,]*\s*,\s*["\'])' . preg_quote($old_text_domain, '/') . '(["\'])/';
            $new_content = preg_replace_callback(
                $boot_pattern,
                function ($matches) use ($new_text_domain) {
                    return $matches[1] . $new_text_domain . $matches[2];
                },
                $new_content
            );

            // Update any other direct text domain references that might exist in function calls.
            $generic_pattern = '/(\bboot\s*\(\s*[^,]*\s*,\s*["\'])' . preg_quote($old_text_domain, '/') . '(["\'])/';
            $new_content = preg_replace_callback(
                $generic_pattern,
                function ($matches) use ($new_text_domain) {
                    return $matches[1] . $new_text_domain . $matches[2];
                },
                $new_content
            );

            if ($content !== $new_content) {
                file_put_contents($path, $new_content);
                $io->writeln("✓ Updated text domain in '{$path}' (from '{$old_text_domain}' to '{$new_text_domain}')");
            }
        }
    }

    /**
     * Updates general references throughout the codebase.
     *
     * @param string $dir The directory to process.
     * @param string $old_plugin_name The old plugin name.
     * @param string $new_plugin_name The new plugin name.
     * @param string $old_namespace The old namespace.
     * @param string $new_namespace The new namespace.
     * @param SymfonyStyle $io The output interface.
     */
    private function update_general_references(string $dir, string $old_plugin_name, string $new_plugin_name, string $old_namespace, string $new_namespace, SymfonyStyle $io)
    {
        if (empty($old_plugin_name) || empty($new_plugin_name) || $old_plugin_name === $new_plugin_name) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isDir() || ( $file->getExtension() !== 'php' && $file->getExtension() !== 'js' && $file->getExtension() !== 'txt' && $file->getExtension() !== 'html' && $file->getExtension() !== 'css' && $file->getExtension() !== 'md' )) {
                continue;
            }

            $path = $file->getRealPath();
            $content = file_get_contents($path);
            $new_content = $content;

            // Update @package references (both old plugin name and old namespace).
            $new_content = preg_replace_callback(
                '/(@package\s+)' . preg_quote($old_namespace, '/') . '/',
                function ($matches) use ($new_namespace) {
                    return $matches[1] . $new_namespace;
                },
                $new_content
            );
            $new_content = preg_replace_callback(
                '/(@package\s+)' . preg_quote($old_plugin_name, '/') . '/',
                function ($matches) use ($new_plugin_name) {
                    return $matches[1] . $new_plugin_name;
                },
                $new_content
            );

            // Update @since, @version, etc. references if they contain the old plugin name.
            $new_content = preg_replace_callback(
                '/(@since\s+.*?)(?<!\w)' . preg_quote($old_plugin_name, '/') . '(?!\w)/',
                function ($matches) use ($new_plugin_name) {
                    return $matches[1] . $new_plugin_name;
                },
                $new_content
            );
            $new_content = preg_replace_callback(
                '/(@version\s+.*?)(?<!\w)' . preg_quote($old_plugin_name, '/') . '(?!\w)/',
                function ($matches) use ($new_plugin_name) {
                    return $matches[1] . $new_plugin_name;
                },
                $new_content
            );

            // Update any other references to the old plugin name that might appear in comments/docblocks.
            $pattern = '/(?<!\w)' . preg_quote($old_plugin_name, '/') . '(?!\w)/';
            $new_content = preg_replace($pattern, $new_plugin_name, $new_content);

            if ($content !== $new_content) {
                file_put_contents($path, $new_content);
                $io->writeln("✓ Updated general references in '{$path}'");
            }
        }
    }

    /**
     * Updates the plugin names in all PHP files.
     *
     * @param string $dir The directory to process.
     * @param string $old_plugin_name The old plugin name.
     * @param string $new_plugin_name The new plugin name.
     * @param SymfonyStyle $io The output interface.
     */
    private function update_plugin_names(string $dir, string $old_plugin_name, string $new_plugin_name, SymfonyStyle $io)
    {
        if (empty($old_plugin_name) || empty($new_plugin_name) || $old_plugin_name === $new_plugin_name) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isDir() || ( $file->getExtension() !== 'php' && $file->getExtension() !== 'js' && $file->getExtension() !== 'txt' && $file->getExtension() !== 'html' && $file->getExtension() !== 'css' )) {
                continue;
            }

            $path = $file->getRealPath();
            $content = file_get_contents($path);

            // Replace old plugin name with new plugin name, preserving case where appropriate.
            $new_content = $content;

            // Replace in comments (like package, since, version tags).
            $new_content = preg_replace_callback(
                '/(@package\s+)' . preg_quote($old_plugin_name, '/') . '/',
                function ($matches) use ($new_plugin_name) {
                    return $matches[1] . $new_plugin_name;
                },
                $new_content
            );
            $new_content = preg_replace_callback(
                '/(@subpackage\s+)' . preg_quote($old_plugin_name, '/') . '/',
                function ($matches) use ($new_plugin_name) {
                    return $matches[1] . $new_plugin_name;
                },
                $new_content
            );

            // For PHP files, we need to be careful about translation strings.
            if ($file->getExtension() === 'php') {
                // To protect translation strings, we'll only do simple replacement for now.
                // A fully robust solution would require a PHP parser, which is overkill.
                // For now, accept that some translation strings might be changed, but prioritize updating all other plugin name references.
                $new_content = str_replace($old_plugin_name, $new_plugin_name, $new_content);
            } else {
                // For non-PHP files, just do a simple replacement.
                $new_content = str_replace($old_plugin_name, $new_plugin_name, $new_content);
            }

            if ($content !== $new_content) {
                file_put_contents($path, $new_content);
                $io->writeln("✓ Updated plugin name in '{$path}' (from '{$old_plugin_name}' to '{$new_plugin_name}')");
            }
        }
    }

    /**
     * Updates the readme.txt file.
     *
     * @param string $readmeFilePath The path to the readme.txt file.
     * @param string $old_plugin_name The old plugin name.
     * @param string $new_plugin_name The new plugin name.
     * @param string $old_text_domain The old text domain.
     * @param string $new_text_domain The new text domain.
     * @param OutputInterface $output The output interface.
     */
    private function update_readme_file(string $readme_file_path, string $old_plugin_name, string $new_plugin_name, string $old_text_domain, string $new_text_domain, SymfonyStyle $io)
    {
        if (! file_exists($readme_file_path)) {
            return;
        }

        $content = file_get_contents($readme_file_path);
        $original_content = $content;

        // Update Plugin Name.
        if (! empty($old_plugin_name) && $old_plugin_name !== $new_plugin_name) {
            $content = preg_replace_callback(
                '/(=== )' . preg_quote($old_plugin_name, '/') . '( ===)/',
                function ($matches) use ($new_plugin_name) {
                    return $matches[1] . $new_plugin_name . $matches[2];
                },
                $content
            );
            $content = preg_replace_callback(
                '/(== )' . preg_quote($old_plugin_name, '/') . '( ==)/',
                function ($matches) use ($new_plugin_name) {
                    return $matches[1] . $new_plugin_name . $matches[2];
                },
                $content
            );
        }

        // Update Stable tag.
        if (! empty($old_text_domain) && $old_text_domain !== $new_text_domain) {
            $content = preg_replace_callback(
                '/(Stable tag:\s*)' . preg_quote($old_text_domain, '/') . '/',
                function ($matches) use ($new_text_domain) {
                    return $matches[1] . $new_text_domain;
                },
                $content
            );
        }

        // General replacement for old plugin name to new plugin name.
        if (! empty($old_plugin_name) && $old_plugin_name !== $new_plugin_name) {
            $content = str_replace($old_plugin_name, $new_plugin_name, $content);
        }

        // General replacement for old text domain to new text domain.
        if (! empty($old_text_domain) && $old_text_domain !== $new_text_domain) {
            $content = str_replace($old_text_domain, $new_text_domain, $content);
        }

        if ($content !== $original_content) {
            file_put_contents($readme_file_path, $content);
            $io->writeln('✓ Updated readme.txt');
        }
    }

    /**
     * Extracts the old text domain from the main plugin file.
     *
     * @param string $main_file
     * @return string|null
     */
    private function get_old_text_domain(string $main_file): ?string
    {
        $content = file_get_contents($main_file);
        if (preg_match('/^[ \t\/*#@]*Text Domain:\s*(.*)$/im', $content, $matches)) {
            return trim($matches[1]);
        }

        // Fallback to slugified plugin name if Text Domain header is not found.
        if (preg_match('/^[ \t\/*#@]*Plugin Name:\s*(.*)$/im', $content, $matches)) {
            // Need to slugify the plugin name to get the text domain.
            return $this->slugify(trim($matches[1]));
        }

        return null;
    }

    /**
     * Extracts all relevant plugin header information from the main plugin file.
     *
     * @param string $main_file The path to the main plugin file.
     * @return array An associative array of header fields.
     */
    private function get_plugin_file_headers(string $main_file): array
    {
        if (! file_exists($main_file)) {
            return [];
        }

        $headers = [];
        $content = file_get_contents($main_file);

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
                $headers[ $key ] = trim($matches[1]);
            } else {
                $headers[ $key ] = ''; // Ensure all keys are present.
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
        $text = preg_replace('~[^\pL\d]+~u', '-', $text); // Replace non-alphanumeric with hyphen.
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text); // Transliterate.
        $text = strtolower($text); // Convert to lowercase.
        $text = preg_replace('~[^-\w]+~', '', $text); // Remove unwanted characters.
        $text = trim($text, '-'); // Trim hyphens from beginning and end.
        $text = preg_replace('~-+~', '-', $text); // Replace multiple hyphens with a single one.

        return $text;
    }

    /**
     * Gets the project configuration from wpmoo-config.yml or composer.json.
     *
     * @param string $dir
     * @return array
     */
    private function get_project_config(string $dir): array
    {
        $config_file = $dir . '/wpmoo-config.yml';
        if (file_exists($config_file)) {
            $config = Yaml::parseFile($config_file);
            if (isset($config['project'])) {
                // Ensure all expected keys are present, even if empty.
                return array_merge(
                    [
                        'name' => '',
                        'namespace' => '',
                        'text_domain' => '',
                        'author' => '',
                        'description' => '',
                        'license' => '',
                        'license_uri' => '',
                    ],
                    $config['project']
                );
            }
        }

        $composer_file = $dir . '/composer.json';
        if (file_exists($composer_file)) {
            $composer_data = json_decode(file_get_contents($composer_file), true);
            if (isset($composer_data['autoload']['psr-4'])) {
                $namespaces = $composer_data['autoload']['psr-4'];
                $namespace = rtrim(key($namespaces), '\\');
                $name = key($composer_data['autoload']['psr-4']); // Assuming the namespace key is the project name.
                $name = rtrim($name, '\\');
                $text_domain = $this->slugify($name);

                return [
                    'name' => $name,
                    'namespace' => $namespace,
                    'text_domain' => $text_domain,
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
    protected function identify_project(): array
    {
        $cwd = $this->get_cwd();

        // Check for wpmoo framework project.
        $wpmoo_src_path = $cwd . '/src/wpmoo.php';
        $is_wpmoo_framework = file_exists($wpmoo_src_path) &&
            strpos(file_get_contents($wpmoo_src_path), 'WPMoo Framework') !== false;

        if ($is_wpmoo_framework) {
            return [
                'found' => true,
                'type' => 'wpmoo-framework',
                'main_file' => $wpmoo_src_path,
                'readme_file' => $cwd . '/readme.txt', // Check if readme.txt exists.
            ];
        }

        // Check for wpmoo-starter or other wpmoo-based plugin.
        $php_files = glob($cwd . '/*.php');
        foreach ($php_files as $file) {
            $content = file_get_contents($file);
            // Look for WPMoo in plugin header.
            if (
                preg_match('/(wpmoo|WPMoo)/i', $content) &&
                ( preg_match('/^[ \t\/*#@]*Plugin Name:/im', $content) ||
                preg_match('/^[ \t\/*#@]*Theme Name:/im', $content) )
            ) {
                $readme_path = $cwd . '/readme.txt';
                return [
                    'found' => true,
                    'type' => 'wpmoo-plugin',
                    'main_file' => $file,
                    'readme_file' => file_exists($readme_path) ? $readme_path : null,
                ];
            }
        }

        return [
            'found' => false,
            'type' => 'unknown',
            'main_file' => null,
            'readme_file' => null,
        ];
    }
}

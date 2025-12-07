<?php

namespace WPMoo\CLI\Commands\Plugin;

use WPMoo\CLI\Support\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

/**
 * Scope command for WPMoo projects.
 *
 * Scopes the embedded WPMoo framework with the project's namespace and text domain.
 *
 * @package WPMoo\CLI\Commands\Framework
 * @since 0.1.0
 */
class ScopeCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('scope')
            ->setDescription('Scopes the WPMoo framework within a project.');
    }

    public function handle_execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('WPMoo Framework Scoper');

        $project = $this->identify_project();
        if ($project['type'] !== 'wpmoo-plugin' && $project['type'] !== 'wpmoo-theme') {
            $io->error('The "scope" command can only be used inside a WPMoo-based plugin or theme.');
            return self::FAILURE;
        }

        $project_root = $this->get_cwd();
        $framework_dir = $project_root . '/framework';

        if (!is_dir($framework_dir)) {
            $io->error('A `framework` directory was not found in your project root. Nothing to scope.');
            return self::FAILURE;
        }

        $config = $this->config_manager->all();
        $new_namespace = $config['project']['namespace'] ?? null;
        $new_text_domain = $config['project']['text_domain'] ?? null;

        if (empty($new_namespace) || empty($new_text_domain)) {
            $io->error('`namespace` or `text_domain` not found in your `wpmoo-config.yml`.');
            return self::FAILURE;
        }

        if (strpos($new_namespace, 'WPMoo') !== false) {
            $io->error('The project namespace cannot contain "WPMoo".');
            return self::FAILURE;
        }

        $io->note("Scoping framework with Namespace `{$new_namespace}` and Text Domain `{$new_text_domain}`.");

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($framework_dir));
        $scoped_files = 0;

        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getRealPath();
            $content = file_get_contents($path);

            // Scope Namespaces and Use statements
            $new_content = str_replace(
                ['namespace WPMoo', 'use WPMoo'],
                ["namespace {$new_namespace}\\WPMoo", "use {$new_namespace}\\WPMoo"],
                $content
            );

            // Scope package tag
            $new_content = str_replace(
                '@package WPMoo',
                "@package {$new_namespace}",
                $new_content
            );

            // Scope text domain
            // This is a safer regex to avoid replacing 'wpmoo' in URLs, etc.
            // It looks for 'wpmoo' inside translation functions like __, _e, esc_html__
            $new_content = preg_replace(
                "/(, 'wpmoo'\))/",
                ", '{$new_text_domain}')",
                $new_content
            );

            if ($content !== $new_content) {
                file_put_contents($path, $new_content);
                $scoped_files++;
            }
        }

        $io->success("Scoping complete. {$scoped_files} files modified.");

        return self::SUCCESS;
    }
}

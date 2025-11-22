#!/usr/bin/env php
<?php

/**
 * WPMoo CLI wrapper generator
 *
 * This script creates a local CLI wrapper that calls the vendor version.
 */

// Check if the current moo file is the main entry point (not the wrapper)
$is_main_entry_point = false;
if (file_exists('moo')) {
    $moo_content = file_get_contents('moo');
    // Check if the existing file is the main entry point (contains the CLI bootstrap)
    $is_main_entry_point = strpos($moo_content, 'WPMoo CLI Entry Point') !== false ||
                               strpos($moo_content, 'WPMoo\CLI\CLI::run') !== false;
}

// Only create wrapper if no moo file exists, or if the existing one is the main entry point.
if (! file_exists('moo') || $is_main_entry_point) {
    $content  = "#!/usr/bin/env php\n";
    $content .= "<?php\n";
    $content .= "\n";
    $content .= "\$argv = array_slice(\$GLOBALS['argv'], 1);\n";
    $content .= "\$command = 'php vendor/bin/moo ' . implode(' ', array_map('escapeshellarg', \$argv)) . ' --ansi';\n";
    $content .= "\$exitCode = 0;\n";
    $content .= "passthru(\$command, \$exitCode);\n";
    $content .= "exit(\$exitCode);\n";

    file_put_contents('moo', $content);
    chmod('moo', 0755);

    if ($is_main_entry_point) {
        echo "CLI wrapper 'moo' created successfully, replacing the main entry point file.\n";
    } else {
        echo "CLI wrapper 'moo' created successfully.\n";
    }
}

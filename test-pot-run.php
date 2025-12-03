<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/CLI/Support/PotGenerator.php';

use WPMoo\CLI\Support\PotGenerator;

// Create a dummy source file
$sourceDir = __DIR__ . '/temp-source';
if (!is_dir($sourceDir)) mkdir($sourceDir);

$phpContent = <<<PHP
<?php
__('Hello World', 'wpmoo');
_e('Print me', 'wpmoo');
_x('Post', 'noun', 'wpmoo');
esc_html__('Escaped string', 'wpmoo');
__('Other domain string', 'other-domain');
PHP;

file_put_contents($sourceDir . '/test.php', $phpContent);

// Run generator
$generator = new PotGenerator();
$outputFile = __DIR__ . '/temp-output.pot';

echo "Generating POT for domain 'wpmoo'...
";
$generator->generate_pot_file($sourceDir, $outputFile, 'wpmoo');

// Read output
if (file_exists($outputFile)) {
    echo "POT Content:
";
    echo "--------------------------------------------------
";
    echo file_get_contents($outputFile);
    echo "--------------------------------------------------
";
} else {
    echo "Error: Output file not found.
";
}

// Cleanup
unlink($sourceDir . '/test.php');
rmdir($sourceDir);
if (file_exists($outputFile)) unlink($outputFile);


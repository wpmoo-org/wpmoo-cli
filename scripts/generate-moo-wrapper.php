#!/usr/bin/env php
<?php
/**
 * WPMoo CLI wrapper generator
 *
 * This script creates a local CLI wrapper that calls the vendor version.
 */

// Only create if moo file doesn't exist.
if ( ! file_exists( 'moo' ) ) {
	$content  = "#!/usr/bin/env php\n";
	$content .= "<?php\n";
	$content .= "\n";
	$content .= "\$argv = array_slice(\$GLOBALS['argv'], 1); // Remove script name from args\n";
	$content .= "\$command = 'php vendor/bin/moo ' . implode(' ', array_map('escapeshellarg', \$argv));\n";
	$content .= "exit(system(\$command));\n";

	file_put_contents( 'moo', $content );
	chmod( 'moo', 0755 );
	echo "CLI wrapper 'moo' created successfully.\n";
} else {
	echo "CLI wrapper 'moo' already exists. Skipping creation.\n";
}

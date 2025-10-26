<?php

namespace WPMoo\CLI\Commands;

use WPMoo\CLI\Contracts\CommandInterface;
use WPMoo\CLI\Console;

class InfoCommand implements CommandInterface {
    public function handle(array $args = array()) {
        $php = PHP_VERSION;
        $wp  = function_exists('get_bloginfo') ? get_bloginfo('version') : 'n/a (CLI)';
        Console::info('WPMoo — WordPress Micro OOP Framework');
        Console::comment('PHP: ' . $php);
        Console::comment('WP : ' . $wp);
        Console::line();
        return 0;
    }
}


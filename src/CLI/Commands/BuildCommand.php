<?php

namespace WPMoo\CLI\Commands;

use WPMoo\CLI\Contracts\CommandInterface;
use WPMoo\CLI\Support\Base;
use WPMoo\CLI\Console;

class BuildCommand extends Base implements CommandInterface {
    public function handle(array $args = array()) {
        $options = $this->parse_build_options($args);
        Console::line(); Console::comment('Building assets…');
        $success = $this->perform_build(array_merge($options, array('allow-missing' => true)));
        if (!$success) { Console::error('Asset build failed.'); Console::line(); return 1; }
        Console::line(); return 0;
    }
}


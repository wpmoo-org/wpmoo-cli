<?php

namespace WPMoo\CLI\Commands;

use WPMoo\CLI\Contracts\CommandInterface;
use WPMoo\CLI\Support\Base;
use WPMoo\CLI\Console;

class UpdateCommand extends Base implements CommandInterface {
    public function handle(array $args = array()) {
        $options = $this->parse_options($args);
        Console::line(); Console::comment('Running WPMoo maintenance tasks…');
        $pot_path = $this->refresh_translations($options);
        if ($pot_path) { Console::info('Translations refreshed at ' . $this->relative_path($pot_path)); }
        Console::line(); return 0;
    }
}


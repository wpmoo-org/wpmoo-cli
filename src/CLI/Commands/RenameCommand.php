<?php

namespace WPMoo\CLI\Commands;

use WPMoo\CLI\Contracts\CommandInterface;
use WPMoo\CLI\Support\Base;
use WPMoo\CLI\Console;

class RenameCommand extends Base implements CommandInterface {
	public function handle( array $args = array() ) {
		Console::warning( 'Rename command is only available when using the framework-integrated CLI.' );
		Console::comment( 'This external package will include rename in a future release.' );
		return 0;
	}
}

<?php

/**
 * Base command class for the WPMoo CLI.
 *
 * Provides common functionality for all CLI commands.
 *
 * @package WPMoo\CLI\Support
 * @since 0.1.0
 * @link  https://wpmoo.org WPMoo – WordPress Micro Object-Oriented Framework.
 * @link  https://github.com/wpmoo/wpmoo GitHub Repository.
 * @license https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 */

namespace WPMoo\CLI\Support;

class Banner
{
    /**
     * WPMoo ASCII Banner
     */
    public static function getAsciiArt(): string
    {
        return <<<EOT
        <fg=green>
        ░██       ░██ ░█████████  ░███     ░███                       
        ░██       ░██ ░██     ░██ ░████   ░████                       
        ░██  ░██  ░██ ░██     ░██ ░██░██ ░██░██  ░███████   ░███████  
        ░██ ░████ ░██ ░█████████  ░██ ░████ ░██ ░██    ░██ ░██    ░██ 
        ░██░██ ░██░██ ░██         ░██  ░██  ░██ ░██    ░██ ░██    ░██ 
        ░████   ░████ ░██         ░██       ░██ ░██    ░██ ░██    ░██ 
        ░███     ░███ ░██         ░██       ░██  ░███████   ░███████  
        </>
        EOT;
    }
}

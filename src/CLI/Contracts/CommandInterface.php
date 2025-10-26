<?php

namespace WPMoo\CLI\Contracts;

interface CommandInterface {
    /**
     * Handle the command.
     * @param array<int,mixed> $args
     */
    public function handle(array $args = array());
}


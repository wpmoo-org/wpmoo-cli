<?php

/**
 * Joins path segments, handling directory separators.
 *
 * @param string ...$segments Path segments to join.
 * @return string Joined path.
 */
function wpmoo_path_join(string ...$segments): string
{
    $filtered = array_filter($segments, function ($value) {
        return (string) $value !== '';
    });

    $path = implode(DIRECTORY_SEPARATOR, $filtered);

    // Normalize multiple separators to single, and handle Windows backslashes.
    $path = str_replace(['/', '\\\\'], DIRECTORY_SEPARATOR, $path);
    $path = preg_replace('/' . preg_quote(DIRECTORY_SEPARATOR, '/') . '{2,}/', DIRECTORY_SEPARATOR, $path);

    return $path;
}

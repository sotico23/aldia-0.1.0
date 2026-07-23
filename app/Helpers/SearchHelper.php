<?php

namespace App\Helpers;

class SearchHelper
{
    /**
     * Escape LIKE wildcards to prevent search scope manipulation.
     * Escapes %, \, and _ characters.
     */
    public static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}

<?php

namespace App\Config;

final class Branding
{
    public static function get(): array
    {
        $file = __DIR__ . DIRECTORY_SEPARATOR . 'branding.php';
        return \file_exists($file) ? require $file : [];
    }
}

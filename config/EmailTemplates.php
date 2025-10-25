<?php

namespace App\Config;

final class EmailTemplates
{
    public static function get(): array
    {
        $file = __DIR__ . DIRECTORY_SEPARATOR . 'email_templates.php';
        return \file_exists($file) ? require_once $file : [];
    }
}
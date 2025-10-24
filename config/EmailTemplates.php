<?php

namespace App\Config;

final class EmailTemplates
{
    public static function get(): array
    {
         = __DIR__ . DIRECTORY_SEPARATOR . 'email_templates.php';
        return \file_exists() ? require  : [];
    }
}

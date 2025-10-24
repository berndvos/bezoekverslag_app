<?php

namespace App\Config;

final class MailSettings
{
    public static function get(): array
    {
         = __DIR__ . DIRECTORY_SEPARATOR . 'mail_settings.php';
        return \file_exists() ? require  : [];
    }
}

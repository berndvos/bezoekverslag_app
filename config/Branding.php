<?php
namespace App\Config;

final class Branding
{
    private static ?array $settings = null;

    public static function get(): array
    {
        if (self::$settings === null) {
            $file = __DIR__ . DIRECTORY_SEPARATOR . 'branding-settings.php';
            self::$settings = \file_exists($file) ? require_once $file : [];
        }
        return self::$settings;
    }
}

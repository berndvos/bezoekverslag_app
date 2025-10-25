<?php
namespace App\Config;

final class Branding
{
    private static ?array $settings = null;

    public static function get(): array
    {
        if (self::$settings === null) {
            $file = __DIR__ . DIRECTORY_SEPARATOR . 'branding-settings.php';
<<<<<<< HEAD
            self::$settings = \file_exists($file) ? require_once $file : [];
=======
            self::$settings = \file_exists($file) ? require $file : [];
>>>>>>> 8965271899ca851575432c3bc48d70da2dc83957
        }
        return self::$settings;
    }
}
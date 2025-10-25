<?php

namespace App\Config;

class ConfigLoader
{
    public static function load(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }

        $configPath = __DIR__ . '/config.php';
        if (!is_readable($configPath)) {
            throw new \RuntimeException('Could not read config.php');
        }

        require_once $configPath;
        $loaded = true;
    }
}

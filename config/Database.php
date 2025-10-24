<?php

namespace App\Config;

use Dotenv\Dotenv;
use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;
    private static bool $bootstrapped = false;

    private static function bootstrap(): void
    {
        if (self::$bootstrapped) {
            return;
        }

        // Zorg dat config.php blijft functioneren
        $configFile = __DIR__ . '/config.php';
        if (!is_readable($configFile)) {
            throw new RuntimeException('config.php kon niet worden geladen.');
        }
        require_once $configFile;

        // Laad .env variabelen
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();

        self::$bootstrapped = true;
    }

    public static function getConnection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        self::bootstrap();

        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $db   = $_ENV['DB_NAME'] ?? 'bezoekverslag';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? '';
        $dsn  = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $db);

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            return self::$pdo;
        } catch (PDOException $e) {
            throw new RuntimeException('Databaseverbinding mislukt: ' . $e->getMessage(), 0, $e);
        }
    }
}

<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Config\Database;

try {
    $pdo = Database::getConnection();

    // Add status column
    $pdo->exec("ALTER TABLE `users` ADD `status` ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending';");
    echo "Column 'status' added successfully.<br>";

    // Add company column
    $pdo->exec("ALTER TABLE `users` ADD `company` VARCHAR(255) NULL;");
    echo "Column 'company' added successfully.<br>";

} catch (\PDOException $e) {
    die("DB ERROR: " . $e->getMessage());
}

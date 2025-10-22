<?php
require_once 'database.php';

try {
    $pdoconn = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    // Add status column
    $pdoconn->exec("ALTER TABLE `users` ADD `status` ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending';");
    echo "Column 'status' added successfully.<br>";

    // Add company column
    $pdoconn->exec("ALTER TABLE `users` ADD `company` VARCHAR(255) NULL;");
    echo "Column 'company' added successfully.<br>";

} catch (PDOException $e) {
    die("DB ERROR: ". $e->getMessage());
}

<?php
// Idempotente schema-updater om de DB bij te werken naar de huidige applicatieverwachtingen.

require_once __DIR__ . '/vendor/autoload.php';

use App\Config\Database;

function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

function indexExists(PDO $pdo, string $table, string $index): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
    $stmt->execute([$table, $index]);
    return (bool)$stmt->fetchColumn();
}

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function apply(PDO $pdo, string $sql, string $label): void {
    try {
        $pdo->exec($sql);
        echo "[OK] $label\n";
    } catch (Throwable $e) {
        echo "[SKIP/ERR] $label: " . $e->getMessage() . "\n";
    }
}

try {
    $pdo = Database::getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Starting schema update...\n";

    // USERS
    if (tableExists($pdo, 'users')) {
        // Ensure email width
        apply($pdo, "ALTER TABLE `users` MODIFY `email` VARCHAR(255) NOT NULL", "users.email -> VARCHAR(255)");

        // status enum to desired values
        apply($pdo, "ALTER TABLE `users` MODIFY `status` ENUM('pending','active','denied') NOT NULL DEFAULT 'pending'", "users.status enum sync");

        // remember_token/reset_token widths
        if (columnExists($pdo, 'users', 'remember_token')) {
            apply($pdo, "ALTER TABLE `users` MODIFY `remember_token` VARCHAR(255) NULL", "users.remember_token -> VARCHAR(255)");
        }
        if (columnExists($pdo, 'users', 'reset_token')) {
            apply($pdo, "ALTER TABLE `users` MODIFY `reset_token` VARCHAR(255) NULL", "users.reset_token -> VARCHAR(255)");
        }

        // Add two-factor fields
        if (!columnExists($pdo, 'users', 'two_factor_code')) {
            apply($pdo, "ALTER TABLE `users` ADD `two_factor_code` VARCHAR(255) NULL AFTER `remember_token`", "users.add two_factor_code");
        }
        if (!columnExists($pdo, 'users', 'two_factor_expires_at')) {
            apply($pdo, "ALTER TABLE `users` ADD `two_factor_expires_at` DATETIME NULL AFTER `two_factor_code`", "users.add two_factor_expires_at");
        }

        // Add force_password_change
        if (!columnExists($pdo, 'users', 'force_password_change')) {
            apply($pdo, "ALTER TABLE `users` ADD `force_password_change` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`", "users.add force_password_change");
        }

        // Unique index on email
        if (!indexExists($pdo, 'users', 'email')) {
            apply($pdo, "ALTER TABLE `users` ADD UNIQUE KEY `email` (`email`)", "users.add unique index email");
        }
    }

    // BEZOEKVERSLAG
    if (tableExists($pdo, 'bezoekverslag')) {
        $addCols = [
            "straatnaam VARCHAR(255) NULL AFTER `plaats`",
            "huisnummer VARCHAR(10) NULL AFTER `straatnaam`",
            "huisnummer_toevoeging VARCHAR(10) NULL AFTER `huisnummer`",
            "doel TEXT NULL AFTER `situatie`",
            "leverancier_ict VARCHAR(255) NULL AFTER `uitbreiding`",
            "leverancier_telecom VARCHAR(255) NULL AFTER `leverancier_ict`",
            "leverancier_av VARCHAR(255) NULL AFTER `leverancier_telecom`",
            "installatie_adres_huisnummer VARCHAR(10) NULL AFTER `installatie_adres_straat`",
            "installatie_adres_huisnummer_toevoeging VARCHAR(10) NULL AFTER `installatie_adres_huisnummer`",
        ];
        foreach ($addCols as $def) {
            $col = trim(strtok($def, ' '));
            if (!columnExists($pdo, 'bezoekverslag', $col)) {
                apply($pdo, "ALTER TABLE `bezoekverslag` ADD $def", "bezoekverslag.add $col");
            }
        }
        // Widen postcode
        if (columnExists($pdo, 'bezoekverslag', 'installatie_adres_postcode')) {
            apply($pdo, "ALTER TABLE `bezoekverslag` MODIFY `installatie_adres_postcode` VARCHAR(20) NULL", "bezoekverslag.installatie_adres_postcode -> VARCHAR(20)");
        }
    }

    // RUIMTE
    if (tableExists($pdo, 'ruimte')) {
        $ruimteAdd = [
            'schema_version INT(11) NOT NULL DEFAULT 1 AFTER `updated_at`',
            'lengte_ruimte VARCHAR(255) NULL AFTER `schema_version`',
            'breedte_ruimte VARCHAR(255) NULL AFTER `lengte_ruimte`',
            'hoogte_plafond VARCHAR(255) NULL AFTER `breedte_ruimte`',
            'type_plafond VARCHAR(255) NULL AFTER `hoogte_plafond`',
            'ruimte_boven_plafond VARCHAR(255) NULL AFTER `type_plafond`',
            'huidige_situatie_v2 TEXT NULL AFTER `ruimte_boven_plafond`',
            'type_wand VARCHAR(255) NULL AFTER `huidige_situatie_v2`',
            'netwerk_aanwezig VARCHAR(3) NULL AFTER `type_wand`',
            'netwerk_extra VARCHAR(255) NULL AFTER `netwerk_aanwezig`',
            'netwerk_afstand VARCHAR(255) NULL AFTER `netwerk_extra`',
            'stroom_aanwezig VARCHAR(3) NULL AFTER `netwerk_afstand`',
            'stroom_extra_v2 VARCHAR(255) NULL AFTER `stroom_aanwezig`',
            'stroom_afstand VARCHAR(255) NULL AFTER `stroom_extra_v2`',
        ];
        foreach ($ruimteAdd as $def) {
            $col = trim(strtok($def, ' '));
            if (!columnExists($pdo, 'ruimte', $col)) {
                apply($pdo, "ALTER TABLE `ruimte` ADD $def", "ruimte.add $col");
            }
        }
    }

    // FOTO: ensure table exists (some older DBs used ruimte_foto only)
    if (!tableExists($pdo, 'foto') && tableExists($pdo, 'ruimte_foto')) {
        // Create foto table and migrate data
        apply($pdo, "CREATE TABLE `foto` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `ruimte_id` int(11) NOT NULL,\n  `pad` varchar(255) NOT NULL,\n  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id`),\n  KEY `ruimte_id` (`ruimte_id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;", "create foto table");
        apply($pdo, "INSERT INTO foto (id, ruimte_id, pad, created_at) SELECT id, ruimte_id, pad, NOW() FROM ruimte_foto", "migrate ruimte_foto -> foto");
    }

    // CLIENT_ACCESS
    if (tableExists($pdo, 'client_access')) {
        if (!columnExists($pdo, 'client_access', 'last_modified_by')) {
            apply($pdo, "ALTER TABLE `client_access` ADD `last_modified_by` VARCHAR(255) NULL AFTER `last_login`", "client_access.add last_modified_by");
        }
        // Ensure unique composite index
        if (!indexExists($pdo, 'client_access', 'email')) {
            apply($pdo, "ALTER TABLE `client_access` ADD UNIQUE KEY `email` (`email`,`bezoekverslag_id`)", "client_access.add unique (email, verslag)");
        }
    }

    echo "Schema update finished.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Schema update failed: ' . $e->getMessage();
}

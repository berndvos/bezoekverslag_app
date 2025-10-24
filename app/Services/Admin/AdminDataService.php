<?php

namespace App\Services\Admin;

use App\Config\Database;
use PDO;
use PDOException;

class AdminDataService
{
    private string $storageDir;
    private string $uploadsDir;

    public function __construct(?string $storageDir = null, ?string $uploadsDir = null)
    {
        $projectRoot = \dirname(__DIR__, 3);
        $this->storageDir = $storageDir ?? $projectRoot . DIRECTORY_SEPARATOR . 'storage';
        $this->uploadsDir = $uploadsDir ?? $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads';
    }

    public function getLogEntries(PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 100");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSystemStatus(): array
    {
        $status = [];
        $status['storage_writable'] = \is_writable($this->storageDir);
        $status['uploads_writable'] = \is_writable($this->uploadsDir);

        try {
            Database::getConnection();
            $status['db_connection'] = true;
        } catch (PDOException $e) {
            $status['db_connection'] = false;
        }

        return $status;
    }

    public function getDeletedVerslagen(PDO $pdo): array
    {
        $stmt = $pdo->query("
            SELECT id, klantnaam, projecttitel, deleted_at,
                   (deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)) AS is_older_than_30_days
            FROM bezoekverslag
            WHERE deleted_at IS NOT NULL
            ORDER BY deleted_at DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClientPortals(PDO $pdo, bool $restrictToUser, int $userId): array
    {
        $sql = "
            SELECT
                ca.bezoekverslag_id AS verslag_id,
                ca.email AS cp_email,
                ca.expires_at,
                (ca.expires_at < NOW()) AS is_expired,
                b.klantnaam,
                b.projecttitel,
                b.created_by
            FROM client_access ca
            JOIN bezoekverslag b ON ca.bezoekverslag_id = b.id
        ";

        if ($restrictToUser) {
            $sql .= " WHERE b.created_by = :user_id";
            $stmt = $pdo->prepare($sql . " ORDER BY ca.expires_at ASC");
            $stmt->execute(['user_id' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = $pdo->query($sql . " ORDER BY ca.expires_at ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMyClientPortals(PDO $pdo, int $userId): array
    {
        $sql = "
            SELECT
                ca.bezoekverslag_id AS verslag_id,
                ca.email AS cp_email,
                ca.expires_at,
                (ca.expires_at < NOW()) AS is_expired,
                b.klantnaam,
                b.projecttitel
            FROM client_access ca
            JOIN bezoekverslag b ON ca.bezoekverslag_id = b.id
            WHERE b.created_by = :user_id
            ORDER BY ca.expires_at ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

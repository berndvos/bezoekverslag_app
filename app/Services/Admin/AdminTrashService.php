<?php

namespace App\Services\Admin;

use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class AdminTrashService
{
    private string $uploadsPath;

    public function __construct(?string $uploadsPath = null)
    {
        $projectRoot = \dirname(__DIR__, 3);
        $this->uploadsPath = $uploadsPath ?? $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads';
    }

    public function restoreVerslag(PDO $pdo, int $id): AdminServiceResponse
    {
        $stmt = $pdo->prepare('UPDATE bezoekverslag SET deleted_at = NULL WHERE id = ?');
        $stmt->execute([$id]);

        if (function_exists('log_action')) {
            log_action('verslag_restored', "Bezoekverslag #{$id} is hersteld uit de prullenbak.");
        }

        return new AdminServiceResponse(true, 'Bezoekverslag is succesvol hersteld.', 'success');
    }

    public function permanentlyDeleteVerslag(PDO $pdo, int $id): AdminServiceResponse
    {
        $this->removeVerslag($pdo, $id);

        if (function_exists('log_action')) {
            log_action('verslag_permanently_deleted', "Bezoekverslag #{$id} is permanent verwijderd.");
        }

        return new AdminServiceResponse(true, 'Bezoekverslag is permanent verwijderd.', 'success');
    }

    public function emptyTrash(PDO $pdo): AdminServiceResponse
    {
        $stmt = $pdo->query('SELECT id FROM bezoekverslag WHERE deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
        $verslagenToDelete = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $count = 0;
        foreach ($verslagenToDelete as $verslag) {
            $this->removeVerslag($pdo, (int) $verslag['id']);
            $count++;
        }

        if (function_exists('log_action')) {
            log_action('trash_emptied', $count . ' oude verslagen zijn permanent verwijderd uit de prullenbak.');
        }

        $message = $count . ' oude verslagen zijn permanent verwijderd.';

        return new AdminServiceResponse(true, $message, 'success', [
            'removed_count' => $count,
        ]);
    }

    private function removeVerslag(PDO $pdo, int $verslagId): void
    {
        $stmt = $pdo->prepare('SELECT id FROM ruimte WHERE verslag_id = ?');
        $stmt->execute([$verslagId]);
        $ruimtes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($ruimtes as $ruimte) {
            $dir = $this->uploadsPath . DIRECTORY_SEPARATOR . 'ruimte_' . $ruimte['id'];
            if (!is_dir($dir)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $fileInfo) {
                $path = $fileInfo->getRealPath();
                if ($fileInfo->isDir()) {
                    @rmdir($path);
                } else {
                    @unlink($path);
                }
            }

            @rmdir($dir);
        }

        $stmt = $pdo->prepare('DELETE FROM bezoekverslag WHERE id = ?');
        $stmt->execute([$verslagId]);
    }
}

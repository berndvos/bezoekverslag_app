<?php

namespace App\Services\Bezoekverslag;

use App\Config\Database;
use PDO;
use ZipArchive;

class FileService
{
    private const SANITIZE_FILENAME_PART_REGEX = '/[^a-zA-Z0-9_-]/';

    private function publicBasePath(): string
    {
        // app/Services/Bezoekverslag -> up 3 => project root, then /public/
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR;
    }

    public function downloadProjectFilesAsZip(int $verslagId): void
    {
        requireLogin();

        if (!class_exists('ZipArchive')) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Fout: De ZipArchive PHP-extensie is niet ingeschakeld op de server.'];
            header('Location: ?page=bewerk&id=' . $verslagId);
            exit;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT bestandsnaam, pad FROM project_bestanden WHERE verslag_id = ?");
        $stmt->execute([$verslagId]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($files)) {
            $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Er zijn geen projectbestanden om te downloaden.'];
            header('Location: ?page=bewerk&id=' . $verslagId);
            exit;
        }

        $verslagStmt = $pdo->prepare("SELECT klantnaam FROM bezoekverslag WHERE id = ?");
        $verslagStmt->execute([$verslagId]);
        $verslag = $verslagStmt->fetch(PDO::FETCH_ASSOC);
        $safeKlantnaam = preg_replace(self::SANITIZE_FILENAME_PART_REGEX, '_', $verslag['klantnaam'] ?? 'project');
        $zipFilename = 'Projectbestanden_' . $safeKlantnaam . '.zip';

        $zip = new ZipArchive();
        $tempZipFile = tempnam(sys_get_temp_dir(), 'zip');

        if ($zip->open($tempZipFile, ZipArchive::CREATE) !== true) {
            die('Kan geen tijdelijk ZIP-bestand aanmaken.');
        }

        $publicBase = $this->publicBasePath();
        foreach ($files as $file) {
            $fullPath = $publicBase . $file['pad'];
            if (file_exists($fullPath)) {
                $zip->addFile($fullPath, $file['bestandsnaam']);
            }
        }

        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
        header('Content-Length: ' . filesize($tempZipFile));
        readfile($tempZipFile);
        unlink($tempZipFile);
        exit;
    }

    public function downloadPhotosAsZip(int $verslagId): void
    {
        requireLogin();

        if (!class_exists('ZipArchive')) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Fout: De ZipArchive PHP-extensie is niet ingeschakeld op de server.'];
            header('Location: ?page=dashboard');
            exit;
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("\n            SELECT r.naam AS ruimte_naam, f.pad AS foto_pad\n            FROM ruimte r\n            JOIN foto f ON r.id = f.ruimte_id\n            WHERE r.verslag_id = ?\n            ORDER BY r.naam, f.id\n        ");
        $stmt->execute([$verslagId]);
        $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($photos)) {
            $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Er zijn geen foto\'s beschikbaar om te downloaden voor dit verslag.'];
            header('Location: ?page=dashboard');
            exit;
        }

        $verslagStmt = $pdo->prepare("SELECT klantnaam, projecttitel FROM bezoekverslag WHERE id = ?");
        $verslagStmt->execute([$verslagId]);
        $verslag = $verslagStmt->fetch(PDO::FETCH_ASSOC);
        $safeKlantnaam = preg_replace(self::SANITIZE_FILENAME_PART_REGEX, '_', $verslag['klantnaam'] ?? 'verslag');
        $zipFilename = 'Fotos_' . $safeKlantnaam . '.zip';

        $zip = new ZipArchive();
        $tempZipFile = tempnam(sys_get_temp_dir(), 'zip');

        if ($zip->open($tempZipFile, ZipArchive::CREATE) !== true) {
            die('Kan geen tijdelijk ZIP-bestand aanmaken.');
        }

        $publicBase = $this->publicBasePath();
        $photoCounters = [];
        foreach ($photos as $photo) {
            $ruimteNaam = preg_replace(self::SANITIZE_FILENAME_PART_REGEX, '_', $photo['ruimte_naam']);
            $fullPhotoPath = $publicBase . $photo['foto_pad'];

            if (file_exists($fullPhotoPath)) {
                if (!isset($photoCounters[$ruimteNaam])) {
                    $photoCounters[$ruimteNaam] = 1;
                } else {
                    $photoCounters[$ruimteNaam]++;
                }
                $nummer = $photoCounters[$ruimteNaam];
                $ext = pathinfo($fullPhotoPath, PATHINFO_EXTENSION);
                $newFilename = sprintf('%s_%d.%s', $ruimteNaam, $nummer, $ext);

                $zip->addFile($fullPhotoPath, $newFilename);
            }
        }

        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
        header('Content-Length: ' . filesize($tempZipFile));
        readfile($tempZipFile);
        unlink($tempZipFile);
        exit;
    }

    public function handleProjectFileUploads(PDO $pdo, int $verslagId): array
    {
        $errors = [];
        $successCount = 0;
        $uploadDir = $this->publicBasePath() . 'uploads/project_' . $verslagId . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        foreach ($_FILES['project_files']['name'] as $i => $name) {
            $errorCode = $_FILES['project_files']['error'][$i];
            if ($errorCode === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['project_files']['tmp_name'][$i];
                $originalName = basename($name);
                $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
                $filePath = $uploadDir . $safeName;

                if (move_uploaded_file($tmpName, $filePath)) {
                    $dbPath = 'uploads/project_' . $verslagId . '/' . $safeName;
                    $stmt = $pdo->prepare("INSERT INTO project_bestanden (verslag_id, bestandsnaam, pad) VALUES (?, ?, ?)");
                    $stmt->execute([$verslagId, $originalName, $dbPath]);
                    $successCount++;
                } else {
                    $errors[] = "Kon bestand '{$originalName}' niet verplaatsen.";
                }
            } elseif ($errorCode !== UPLOAD_ERR_NO_FILE) {
                $originalName = basename($name);
                switch ($errorCode) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $errors[] = "Bestand '{$originalName}' is te groot.";
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $errors[] = "Bestand '{$originalName}' is slechts gedeeltelijk geüpload.";
                        break;
                    default:
                        $errors[] = "Onbekende fout bij uploaden van '{$originalName}' (Error code: {$errorCode}).";
                        break;
                }
            }
        }
        $this->markPdfAsOutdated($pdo, $verslagId);
        return ['success' => $successCount, 'errors' => $errors];
    }

    public function deleteProjectFile(int $fileId, int $verslagId): bool
    {
        requireLogin();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT pb.pad FROM project_bestanden pb JOIN bezoekverslag b ON pb.verslag_id = b.id WHERE pb.id = ? AND b.id = ?");
        $stmt->execute([$fileId, $verslagId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($file) {
            $fullPath = $this->publicBasePath() . $file['pad'];
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
            $pdo->prepare("DELETE FROM project_bestanden WHERE id = ?")->execute([$fileId]);
            $this->markPdfAsOutdated($pdo, $verslagId);
            return true;
        }
        return false;
    }

    public function markPdfAsOutdated(PDO $pdo, int $verslagId): void
    {
        $stmt = $pdo->prepare("UPDATE bezoekverslag SET pdf_up_to_date = 0 WHERE id = ?");
        $stmt->execute([$verslagId]);
    }
}
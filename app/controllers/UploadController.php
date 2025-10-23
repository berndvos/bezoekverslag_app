<?php
require_once __DIR__ . '/../models/Foto.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../helpers/auth_helpers.php';
require_once __DIR__ . '/../../config/database.php';


class UploadController {
    private const MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    private const REDIRECT_DASHBOARD = 'Location: ?page=dashboard';
    private const REDIRECT_RUIMTE_TEMPLATE = '?page=ruimte&id=%d&verslag=%d';

    public function uploadFile() {
        requireLogin();

        $ruimteId = (int)($_POST['ruimte_id'] ?? 0);
        $verslagId = (int)($_POST['verslag_id'] ?? 0);
        require_valid_csrf_token($_POST['csrf_token'] ?? null);

        $this->validateIdentifiers($ruimteId, $verslagId);

        $pdo = Database::getConnection();
        $this->assertUserCanEdit($verslagId);
        $this->assertRuimteExists($pdo, $ruimteId, $verslagId);

        $targetDir = $this->buildTargetDirectory($verslagId, $ruimteId);
        $this->ensureUploadDirectory($targetDir, $ruimteId, $verslagId);
        $this->assertFileInfoExtensionLoaded($ruimteId, $verslagId);

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $errors = $this->processUploadedFiles($finfo, $ruimteId, $verslagId, $targetDir);

        finfo_close($finfo);

        if ($errors) {
            $_SESSION['flash_message'] = ['type' => 'warning', 'text' => implode('<br>', $errors)];
        }

        $this->redirectToRuimte($ruimteId, $verslagId);
    }

    private function validateIdentifiers(int $ruimteId, int $verslagId): void {
        if ($ruimteId && $verslagId) {
            return;
        }
        $this->redirectToDashboard();
    }

    private function assertUserCanEdit(int $verslagId): void {
        if (canEditVerslag($verslagId)) {
            return;
        }

        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'U heeft geen rechten om bestanden voor dit verslag te uploaden.'];
        $this->redirectToDashboard();
    }

    private function assertRuimteExists(PDO $pdo, int $ruimteId, int $verslagId): void {
        $stmt = $pdo->prepare("SELECT id FROM ruimte WHERE id = ? AND verslag_id = ?");
        $stmt->execute([$ruimteId, $verslagId]);
        if ($stmt->fetch()) {
            return;
        }

        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Ruimte is niet gevonden voor dit verslag.'];
        $this->redirectToDashboard();
    }

    private function buildTargetDirectory(int $verslagId, int $ruimteId): string {
        return rtrim(UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $verslagId . DIRECTORY_SEPARATOR . $ruimteId . DIRECTORY_SEPARATOR;
    }

    private function ensureUploadDirectory(string $targetDir, int $ruimteId, int $verslagId): void {
        if (is_dir($targetDir) || mkdir($targetDir, 0755, true) || is_dir($targetDir)) {
            return;
        }

        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Uploadmap kon niet worden aangemaakt.'];
        $this->redirectToRuimte($ruimteId, $verslagId);
    }

    private function assertFileInfoExtensionLoaded(int $ruimteId, int $verslagId): void {
        if (extension_loaded('fileinfo')) {
            return;
        }

        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'text' => 'De server ondersteunt momenteel geen veilige bestandscontrole. Uploads zijn uitgeschakeld.'
        ];
        $this->redirectToRuimte($ruimteId, $verslagId);
    }

    private function processUploadedFiles($finfo, int $ruimteId, int $verslagId, string $targetDir): array {
        $errors = [];
        foreach ($_FILES['files']['tmp_name'] as $idx => $tmp) {
            if (!is_uploaded_file($tmp)) {
                continue;
            }

            $origName = $_FILES['files']['name'][$idx] ?? 'bestand';
            $size = $_FILES['files']['size'][$idx] ?? 0;
            if ($size <= 0 || $size > self::MAX_FILE_SIZE_BYTES) {
                $errors[] = "Bestand '{$origName}' is ongeldig of te groot (max 10MB).";
                continue;
            }

            $mimeType = finfo_file($finfo, $tmp);
            if (!$mimeType || !array_key_exists($mimeType, self::ALLOWED_MIME_TYPES)) {
                $errors[] = "Bestand '{$origName}' is geen toegestaan afbeeldingstype.";
                continue;
            }

            $safeName = $this->generateSafeFilename(self::ALLOWED_MIME_TYPES[$mimeType]);
            $destination = $targetDir . $safeName;

            if (!move_uploaded_file($tmp, $destination)) {
                $errors[] = "Bestand '{$origName}' kon niet worden opgeslagen.";
                continue;
            }

            Foto::add($ruimteId, $verslagId . '/' . $ruimteId . '/' . $safeName);
        }

        return $errors;
    }

    private function generateSafeFilename(string $extension): string {
        return bin2hex(random_bytes(16)) . '.' . $extension;
    }

    private function redirectToDashboard(): void {
        header(self::REDIRECT_DASHBOARD);
        exit;
    }

    private function redirectToRuimte(int $ruimteId, int $verslagId): void {
        header(sprintf(self::REDIRECT_RUIMTE_TEMPLATE, $ruimteId, $verslagId));
        exit;
    }
}


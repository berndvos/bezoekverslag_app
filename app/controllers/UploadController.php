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

    public function uploadFile() {
        requireLogin();

        $ruimte_id = (int)($_POST['ruimte_id'] ?? 0);
        $verslag_id = (int)($_POST['verslag_id'] ?? 0);
        if (!$ruimte_id || !$verslag_id) {
            header("Location: ?page=dashboard");
            exit;
        }
        require_valid_csrf_token($_POST['csrf_token'] ?? null);

        if (!canEditVerslag($verslag_id)) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'U heeft geen rechten om bestanden voor dit verslag te uploaden.'];
            header("Location: ?page=dashboard");
            exit;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id FROM ruimte WHERE id = ? AND verslag_id = ?");
        $stmt->execute([$ruimte_id, $verslag_id]);
        if (!$stmt->fetch()) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Ruimte is niet gevonden voor dit verslag.'];
            header("Location: ?page=dashboard");
            exit;
        }

        $targetDir = rtrim(UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $verslag_id . DIRECTORY_SEPARATOR . $ruimte_id . DIRECTORY_SEPARATOR;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Uploadmap kon niet worden aangemaakt.'];
            header("Location: ?page=ruimte&id=$ruimte_id&verslag=$verslag_id");
            exit;
        }

        if (!extension_loaded('fileinfo')) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'De server ondersteunt momenteel geen veilige bestandscontrole. Uploads zijn uitgeschakeld.'];
            header("Location: ?page=ruimte&id=$ruimte_id&verslag=$verslag_id");
            exit;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
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

            $extension = self::ALLOWED_MIME_TYPES[$mimeType];
            $safeName = bin2hex(random_bytes(16)) . '.' . $extension;
            $destination = $targetDir . $safeName;

            if (!move_uploaded_file($tmp, $destination)) {
                $errors[] = "Bestand '{$origName}' kon niet worden opgeslagen.";
                continue;
            }

            Foto::add($ruimte_id, $verslag_id . '/' . $ruimte_id . '/' . $safeName);
        }

        finfo_close($finfo);

        if ($errors) {
            $_SESSION['flash_message'] = ['type' => 'warning', 'text' => implode('<br>', $errors)];
        }

        header("Location: ?page=ruimte&id=$ruimte_id&verslag=$verslag_id");
    }
}

<?php
require_once __DIR__ . '/../models/Foto.php';
require_once __DIR__ . '/../../config/config.php';

class UploadController {
    public function uploadFile() {
        $ruimte_id = intval($_POST['ruimte_id'] ?? 0);
        $verslag_id = intval($_POST['verslag_id'] ?? 0);
        if (!$ruimte_id || !$verslag_id) { header("Location: ?page=dashboard"); exit; }

        $targetDir = UPLOAD_DIR . "$verslag_id/$ruimte_id/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        foreach ($_FILES['files']['tmp_name'] as $idx => $tmp) {
            if (!is_uploaded_file($tmp)) continue;
            $name = basename($_FILES['files']['name'][$idx]);
            $safe = preg_replace('~[^a-zA-Z0-9._-]~', '_', $name);
            $dest = $targetDir . $safe;
            if (move_uploaded_file($tmp, $dest)) {
                $relPath = "$verslag_id/$ruimte_id/$safe";
                Foto::add($ruimte_id, $relPath);
            }
        }
        header("Location: ?page=ruimte&id=$ruimte_id&verslag=$verslag_id");
    }
}

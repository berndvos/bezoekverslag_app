<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../helpers/auth_helpers.php';
require_once __DIR__ . '/AdminController.php';

class UpdateController {

    // VERANDER DIT NAAR JE EIGEN GITHUB REPOSITORY
    private const GITHUB_REPO = 'berndvos/bezoekverslag_app'; // Voorbeeld: 'gebruikersnaam/repository-naam'
    private const GITHUB_API_URL = 'https://api.github.com/repos/';

    /**
     * AJAX endpoint om te controleren op updates.
     */
    public function check() {
        header('Content-Type: application/json');
        requireRole(['admin', 'poweruser']);

        if (empty(self::GITHUB_REPO)) {
            echo json_encode(['error' => 'GitHub repository is niet geconfigureerd in UpdateController.php']);
            exit;
        }

        $currentVersion = trim(file_get_contents(__DIR__ . '/../../config/version.php'));
        $latestVersionData = $this->getLatestVersionFromGitHub();

        if (isset($latestVersionData['error'])) {
            echo json_encode($latestVersionData);
            exit;
        }

        $latestVersion = $latestVersionData['version'];

        $response = [
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'update_available' => version_compare($latestVersion, $currentVersion, ' > '),
            'release_info' => $latestVersionData
        ];

        echo json_encode($response);
        exit;
    }

    /**
     * Haalt de laatste release-informatie op van GitHub.
     * @return array
     */
    private function getLatestVersionFromGitHub() {
        $url = self::GITHUB_API_URL . self::GITHUB_REPO . '/releases/latest';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Belangrijk voor veiligheid
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: Bezoekverslag-App-Updater',
            'Accept: application/vnd.github.v3+json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            return ['error' => "cURL Fout bij het verbinden met GitHub API: " . $error_msg];
        }
        if ($httpCode !== 200) {
            return ['error' => "GitHub API gaf een onverwachte statuscode: {$httpCode}. Controleer of de repository publiek is en de naam correct is."];
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['tag_name'])) {
            return ['error' => 'Ongeldig antwoord van de GitHub API.', 'details' => $data];
        }

        curl_close($ch);

        return [
            'version' => preg_replace('/^v/i', '', $data['tag_name']),
            'name' => $data['name'],
            'published_at' => $data['published_at'],
            'body' => $data['body'], // Release notes
            'zip_url' => $data['zipball_url']
        ];
    }

    /**
     * AJAX endpoint om de update uit te voeren.
     */
    public function performUpdate() {
        header('Content-Type: application/json');
        requireRole(['admin', 'poweruser']);
        set_time_limit(300); // Verhoog de executietijd voor het downloaden en uitpakken

        $backupDir = __DIR__ . '/../../storage/backups/update_' . date('Y-m-d_H-i-s');
        $tempDir = __DIR__ . '/../../storage/temp_update';
        $rootDir = __DIR__ . '/../../';

        try {
            // 1. Maak back-up
            if (!$this->createBackup($backupDir)) {
                throw new Exception("Kon geen back-up maken. Update afgebroken.");
            }

            // 2. Download de laatste release
            $latestVersionData = $this->getLatestVersionFromGitHub();
            if (isset($latestVersionData['error'])) {
                throw new Exception("Kon release-informatie niet ophalen: " . $latestVersionData['error']);
            }
            $zipUrl = $latestVersionData['zip_url'];
            $zipFile = $tempDir . '/update.zip';

            if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);
            if (!$this->downloadFile($zipUrl, $zipFile)) {
                throw new Exception("Downloaden van de update mislukt.");
            }

            // 3. Pak het ZIP-bestand uit
            $unzipDir = $tempDir . '/unzipped';
            if (!$this->unzipFile($zipFile, $unzipDir)) {
                throw new Exception("Uitpakken van het ZIP-bestand mislukt.");
            }

            // 4. Installeer de update
            // De bestanden staan in een submap, vind deze.
            $sourceDir = glob($unzipDir . '/*')[0] ?? null;
            if (!$sourceDir || !is_dir($sourceDir)) {
                throw new Exception("Kon de bronmap van de update niet vinden.");
            }

            $this->copyFiles($sourceDir, $rootDir);

            // 5. Ruim op
            $this->cleanup($tempDir);

            echo json_encode([
                'success' => true,
                'message' => "Update succesvol geïnstalleerd! De pagina wordt over 5 seconden herladen.",
                'new_version' => $latestVersionData['version']
            ]);

        } catch (Exception $e) {
            // Ruim op bij fout
            if (isset($tempDir) && is_dir($tempDir)) {
                $this->cleanup($tempDir);
            }
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Maakt een back-up van de applicatiebestanden en database.
     */
    private function createBackup($backupDir) {
        if (!mkdir($backupDir, 0777, true)) return false;

        // Database back-up
        $adminController = new AdminController();
        $dbBackupContent = $adminController->getDatabaseBackupContent();
        file_put_contents($backupDir . '/db_backup.sql', $dbBackupContent);

        // Bestanden back-up (vereenvoudigd, kopieert de hele app)
        $zip = new ZipArchive();
        $zipFile = $backupDir . '/files_backup.zip';
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            return false;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(__DIR__ . '/../../', RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                // Excludeer de backup- en temp mappen zelf
                if (strpos($filePath, 'storage/backups') === false && strpos($filePath, 'storage/temp_update') === false) {
                    $relativePath = substr($filePath, strlen(__DIR__ . '/../../') + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
        }
        $zip->close();
        return true;
    }

    /**
     * Download een bestand van een URL.
     */
    private function downloadFile($url, $destination) {
        $fp = fopen($destination, 'w+');
        if ($fp === false) {
            return false;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Volg redirects, belangrijk voor GitHub downloads
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Bezoekverslag-App-Updater']);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $success = curl_exec($ch);

        if(curl_errno($ch)){
            // Optioneel: log de curl error voor debugging
            // error_log('cURL download error: ' . curl_error($ch));
        }

        curl_close($ch);
        fclose($fp);
        return $success;
    }
    /**
     * Pakt een ZIP-bestand uit.
     */
    private function unzipFile($zipFile, $destination) {
        $zip = new ZipArchive;
        if ($zip->open($zipFile) === TRUE) {
            $zip->extractTo($destination);
            $zip->close();
            return true;
        }
        return false;
    }

    /**
     * Kopieert bestanden van bron naar bestemming, met uitzonderingen.
     */
    private function copyFiles($source, $destination) {
        $exclude = ['.env', 'storage', 'public/uploads', '.git', '.github'];

        $dir = opendir($source);
        @mkdir($destination);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;

            $sourcePath = $source . '/' . $file;
            $destPath = $destination . '/' . $file;
            $relativePath = str_replace($source . '/', '', $sourcePath);

            if (in_array($relativePath, $exclude)) continue;

            if (is_dir($sourcePath)) {
                $this->copyFiles($sourcePath, $destPath);
            } else {
                copy($sourcePath, $destPath);
            }
        }
        closedir($dir);
    }

    /**
     * Ruimt tijdelijke update-mappen op.
     */
    private function cleanup($dir) {
        if (!is_dir($dir)) return;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            @$todo($fileinfo->getRealPath());
        }
        @rmdir($dir);
    }
}
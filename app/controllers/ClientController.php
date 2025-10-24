<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../models/Bezoekverslag.php';
require_once __DIR__ . '/../models/Ruimte.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * ClientController
 * -----------------
 * Beperkte toegang voor klant (contactpersoon) tot een specifiek bezoekverslag.
 * - Login op basis van client_access (email + wachtwoord)
 * - Kan slechts één verslag zien (id in sessie)
 * - Optioneel bewerken van: Wensen & eisen / Installatie / bestaande Ruimtes
 * - Geen nieuwe ruimtes, geen PDF-generatie
 */
class ClientController {
    private const REDIRECT_CLIENT_VIEW_PREFIX = 'Location: ?page=client_view&id=';
    private const REDIRECT_CLIENT_LOGIN = 'Location: ?page=client_login';
    private const PUBLIC_UPLOAD_BASE = '/../../public/';

    /** LOGIN (publiek) */
    public function login() {
        // eigen client-sessie naast interne sessie toegestaan
        if (!empty($_SESSION['client_id'])) {
            $vid = $_SESSION['bezoekverslag_id'] ?? null;
            header(self::REDIRECT_CLIENT_VIEW_PREFIX . urlencode((string)$vid));
            exit;
        }

        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_valid_csrf_token($_POST['csrf_token'] ?? null);
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM client_access WHERE email = ?");
            $stmt->execute([$_POST['email']]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($client && !empty($client['expires_at']) && new DateTime($client['expires_at']) < new DateTime()) {
                $error = "De toegang voor dit account is verlopen. Neem contact op met uw accountmanager.";
            }
            elseif ($client && password_verify($_POST['password'], $client['password'])) {
                $_SESSION['client_id'] = $client['id'];
                $_SESSION['client_name'] = $client['fullname'];
                $_SESSION['bezoekverslag_id'] = $client['bezoekverslag_id'];
                $_SESSION['client_can_edit'] = (bool)$client['can_edit'];

                // last_login
                $pdo->prepare("UPDATE client_access SET last_login = NOW() WHERE id = ?")
                    ->execute([$client['id']]);

                header(self::REDIRECT_CLIENT_VIEW_PREFIX . $client['bezoekverslag_id']);
                exit;
            } else {
                $error = "Ongeldig e-mailadres of wachtwoord.";
            }
        }

        include __DIR__ . '/../views/client_login.php';
    }

    /** LOGOUT (publiek) */
    public function logout() {
        unset($_SESSION['client_id'], $_SESSION['client_name'], $_SESSION['bezoekverslag_id'], $_SESSION['client_can_edit']);
        header(self::REDIRECT_CLIENT_LOGIN);
        exit;
    }

    /** VIEW (publiek) – id = verslag_id */
    public function view($verslag_id) {
        if (empty($_SESSION['client_id'])) {
            header(self::REDIRECT_CLIENT_LOGIN);
            exit;
        }
        if ((int)$verslag_id !== (int)($_SESSION['bezoekverslag_id'] ?? 0)) {
            die("<div style='padding:20px;font-family:sans-serif;color:#b00;'>Geen toegang tot dit verslag.</div>");
        }

        require_valid_csrf_token($_POST['csrf_token'] ?? null);
        $pdo = Database::getConnection();

        // verslag
        $stmt = $pdo->prepare("SELECT * FROM bezoekverslag WHERE id = ?");
        $stmt->execute([$verslag_id]);
        $verslag = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$verslag) {
            die("<div style='padding:20px;font-family:sans-serif;color:#b00;'>Verslag niet gevonden.</div>");
        }

        // ruimtes (bestaande)
        $stmt = $pdo->prepare("SELECT * FROM ruimte WHERE verslag_id = ? ORDER BY id ASC");
        $stmt->execute([$verslag_id]);
        $ruimtes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        include __DIR__ . '/../views/client_view.php';
    }

    /** UPDATE (publiek) – beperkte updates door klant */
    /** UPDATE (publiek) � beperkte updates door klant */
    public function update($verslag_id) {
        $verslag_id = (int)$verslag_id;
        $this->ensureClientEditAccess($verslag_id);

        $pdo = Database::getConnection();
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'save_wensen_installatie':
                $this->updateClientPreferences($pdo, $verslag_id);
                break;
            case 'save_ruimte':
                $this->updateClientRoom($pdo, $verslag_id);
                break;
            default:
                break;
        }

        $this->markClientModification($pdo, $verslag_id);
        $this->sendUpdateNotification($pdo, $verslag_id);

        header(self::REDIRECT_CLIENT_VIEW_PREFIX . $verslag_id);
        exit;
    }

    private function ensureClientEditAccess(int $verslagId): void {
        if (empty($_SESSION['client_id'])) {
            header(self::REDIRECT_CLIENT_LOGIN);
            exit;
        }
        if ($verslagId !== (int)($_SESSION['bezoekverslag_id'] ?? 0)) {
            die("<div style='padding:20px;font-family:sans-serif;color:#b00;'>Geen toegang tot dit verslag.</div>");
        }
        if (empty($_SESSION['client_can_edit'])) {
            die("<div style='padding:20px;font-family:sans-serif;color:#b00;'>Je hebt alleen-lezen rechten.</div>");
        }
    }

    private function updateClientPreferences(PDO $pdo, int $verslagId): void {
        if (($_POST['action'] ?? '') !== 'save_wensen_installatie') {
            return;
        }

        $stmt = $pdo->prepare("UPDATE bezoekverslag SET wensen = :wensen, eisen = :eisen, installatie = :installatie, last_modified_at = NOW(), last_modified_by = 'client' WHERE id = :id");
        $stmt->execute([
            ':wensen' => $_POST['wensen'] ?? '',
            ':eisen' => $_POST['eisen'] ?? '',
            ':installatie' => $_POST['installatie'] ?? '',
            ':id' => $verslagId
        ]);
    }

    private function updateClientRoom(PDO $pdo, int $verslagId): void {
        if (($_POST['action'] ?? '') !== 'save_ruimte') {
            return;
        }

        $ruimteId = (int)($_POST['ruimte_id'] ?? 0);
        $this->ensureRuimteBelongsToVerslag($pdo, $ruimteId, $verslagId);
        $this->persistClientRoom($pdo, $ruimteId);
        $this->handleClientUploads($pdo, $verslagId, $ruimteId);
    }

    private function ensureRuimteBelongsToVerslag(PDO $pdo, int $ruimteId, int $verslagId): void {
        $chk = $pdo->prepare('SELECT id FROM ruimte WHERE id = ? AND verslag_id = ?');
        $chk->execute([$ruimteId, $verslagId]);
        if (!$chk->fetchColumn()) {
            die("<div style='padding:20px;font-family:sans-serif;color:#b00;'>Ongeldige ruimte.</div>");
        }
    }

    private function persistClientRoom(PDO $pdo, int $ruimteId): void {
        $stmt = $pdo->prepare('
            UPDATE ruimte SET
                etage = :etage,
                bereikbaarheid = :bereikbaarheid,
                lift = :lift,
                afm_lift = :afm_lift,
                voorzieningen = :voorzieningen,
                bereikb_voorzieningen = :bereikb_voorzieningen,
                kabellengte = :kabellengte,
                netwerkintegratie = :netwerkintegratie,
                afmetingen = :afmetingen,
                plafond = :plafond,
                wand = :wand,
                vloer = :vloer,
                beperkingen = :beperkingen,
                opmerkingen = :opmerkingen
            WHERE id = :id
        ');
        $stmt->execute([
            ':etage' => $_POST['etage'] ?? '',
            ':bereikbaarheid' => $_POST['bereikbaarheid'] ?? '',
            ':lift' => $_POST['lift'] ?? '',
            ':afm_lift' => $_POST['afm_lift'] ?? '',
            ':voorzieningen' => $_POST['voorzieningen'] ?? '',
            ':bereikb_voorzieningen' => $_POST['bereikb_voorzieningen'] ?? '',
            ':kabellengte' => $_POST['kabellengte'] ?? '',
            ':netwerkintegratie' => $_POST['netwerkintegratie'] ?? '',
            ':afmetingen' => $_POST['afmetingen'] ?? '',
            ':plafond' => $_POST['plafond'] ?? '',
            ':wand' => $_POST['wand'] ?? '',
            ':vloer' => $_POST['vloer'] ?? '',
            ':beperkingen' => $_POST['beperkingen'] ?? '',
            ':opmerkingen' => $_POST['opmerkingen'] ?? '',
            ':id' => $ruimteId
        ]);
    }

    private function handleClientUploads(PDO $pdo, int $verslagId, int $ruimteId): void {
        $files = $_FILES['files'] ?? null;
        if (!$files || empty($files['name'][0])) {
            return;
        }

        $uploadDir = __DIR__ . self::PUBLIC_UPLOAD_BASE . 'uploads/client/' . $verslagId . '/' . $ruimteId . '/';
        if (!$this->ensureClientUploadPreconditions($uploadDir)) {
            return;
        }

        $allowedMime = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        $maxSize = 10 * 1024 * 1024;
        $candidates = $this->collectClientUploadCandidates($files, $allowedMime, $maxSize);

        foreach ($candidates as $candidate) {
            $filename = bin2hex(random_bytes(16)) . '.' . $candidate['extension'];
            $destination = $uploadDir . $filename;
            if (!move_uploaded_file($candidate['tmp'], $destination)) {
                continue;
            }

            $pdo->prepare('INSERT INTO foto (ruimte_id, pad, created_at) VALUES (?, ?, NOW())')
                ->execute([$ruimteId, 'client/' . $verslagId . '/' . $ruimteId . '/' . $filename]);
        }
    }

    private function ensureClientUploadPreconditions(string $uploadDir): bool {
        if (!extension_loaded('fileinfo')) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Uploads zijn momenteel niet beschikbaar.'];
            return false;
        }

        if (is_dir($uploadDir) || mkdir($uploadDir, 0755, true) || is_dir($uploadDir)) {
            return true;
        }

        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Uploadmap kon niet worden aangemaakt. Probeer het later opnieuw of neem contact op met uw accountmanager.'];
        return false;
    }

    private function collectClientUploadCandidates(array $files, array $allowedMime, int $maxSize): array {
        $candidates = [];
        $total = count($files['name']);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if (!$finfo) {
            return $candidates;
        }

        for ($i = 0; $i < $total; $i++) {
            $error = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($error !== UPLOAD_ERR_OK) {
                continue;
            }

            $tmpName = $files['tmp_name'][$i] ?? '';
            if (!is_uploaded_file($tmpName)) {
                continue;
            }

            $size = $files['size'][$i] ?? 0;
            if ($size <= 0 || $size > $maxSize) {
                continue;
            }

            $mime = finfo_file($finfo, $tmpName);
            if (!$mime || !isset($allowedMime[$mime])) {
                continue;
            }

            $candidates[] = [
                'tmp' => $tmpName,
                'extension' => $allowedMime[$mime],
            ];
        }

        finfo_close($finfo);
        return $candidates;
    }

    private function markClientModification(PDO $pdo, int $verslagId): void {
        $pdo->prepare('UPDATE client_access SET last_modified_at = NOW() WHERE bezoekverslag_id = ?')
            ->execute([$verslagId]);
    }
    /**
     * Verstuurt een notificatie naar de accountmanager na een update door de klant.
     */
    private function sendUpdateNotification($pdo, $verslag_id) {
        // Haal verslag- en accountmanagergegevens op
        $stmt = $pdo->prepare("
            SELECT 
                b.klantnaam, b.projecttitel,
                u.fullname AS am_fullname, u.email AS am_email
            FROM bezoekverslag b
            JOIN users u ON b.created_by = u.id
            WHERE b.id = ?
        ");
        $stmt->execute([$verslag_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data || empty($data['am_email'])) {
            return; // Geen accountmanager gevonden of geen e-mailadres
        }

        // Gebruik de AdminController om de SMTP-instellingen uit .env te halen
        $adminController = new AdminController();
        $mailSettings = $adminController->getSmtpSettings();
        $emailTemplate = require __DIR__ . '/../../config/email_templates.php';

        $mail = new PHPMailer(true);
        try {
            // SMTP Instellingen (hetzelfde als in AuthController)
            $mail->isSMTP();
            $mail->setLanguage('nl');
            $mail->Host       = $mailSettings['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $mailSettings['username'];
            $mail->Password   = $mailSettings['password'];
            if (!empty($mailSettings['encryption'])) {
                $mail->SMTPSecure = ($mailSettings['encryption'] === 'ssl') 
                    ? PHPMailer::ENCRYPTION_SMTPS
                    : PHPMailer::ENCRYPTION_STARTTLS;
            }
            $mail->Port       = $mailSettings['port'];

            $mail->setFrom($mailSettings['from_address'], $mailSettings['from_name'] . ' Notificatie');
            $mail->addAddress($data['am_email'], $data['am_fullname']);

            $placeholders = ['{am_fullname}', '{klantnaam}', '{project_title}'];
            $values = [$data['am_fullname'], $data['klantnaam'], $data['projecttitel']];
            $subject = str_replace($placeholders, $values, $emailTemplate['client_update']['subject']);
            $body = str_replace($placeholders, $values, $emailTemplate['client_update']['body']);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->send();
        } catch (Exception $e) {
            // Optioneel: log de fout, maar stop de applicatie niet.
            // error_log("Mailer Error: " . $mail->ErrorInfo);
        }
    }
}




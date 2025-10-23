<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../helpers/log_helpers.php';
require_once __DIR__ . '/../helpers/auth_helpers.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


class AdminController {

    /** Gebruikersoverzicht */
    public function users() {
        requireRole(['admin', 'poweruser']);
        // Start de sessie als dat nog niet gebeurd is, voor flash messages.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $pdo = Database::getConnection();

        // Nieuwe gebruiker toevoegen
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
            require_valid_csrf_token($_POST['csrf_token'] ?? null);
            if ($this->createUser($pdo)) {
                log_action('user_created', "Gebruiker '{$_POST['email']}' aangemaakt.");
            }
            header("Location: ?page=admin");
            exit;
        }

        // Gebruiker bewerken
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
            require_valid_csrf_token($_POST['csrf_token'] ?? null);
            if ($this->updateUser($pdo)) {
                $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Gebruiker succesvol bijgewerkt.'];
                log_action('user_updated', "Gebruiker '{$_POST['email']}' bijgewerkt.");
            }
            header("Location: ?page=admin");
            exit;
        }

        // Logo uploaden
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_logo'])) {
            require_valid_csrf_token($_POST['csrf_token'] ?? null);
            if ($this->handleLogoUpload()) {
                $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Logo succesvol geüpload.'];
            }
            header("Location: ?page=admin#onderhoud");
            exit;
        }

        // Branding instellingen bijwerken
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_branding'])) {
            // AJAX afhandeling
            header('Content-Type: application/json');
            require_valid_csrf_token($_POST['csrf_token'] ?? null);
            if ($this->saveBrandingSettings()) {
                echo json_encode(['success' => true, 'message' => 'Huisstijl-instellingen succesvol opgeslagen.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Kon de instellingen niet opslaan.']);
            }
            // Stop de uitvoering na het versturen van de JSON-response.
            exit;
        }

        // E-mail sjablonen bijwerken
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_email_templates'])) {
            // AJAX afhandeling
            header('Content-Type: application/json');
            require_valid_csrf_token($_POST['csrf_token'] ?? null);
            if ($this->saveEmailTemplates()) {
                echo json_encode(['success' => true, 'message' => 'E-mail sjablonen succesvol opgeslagen.']);
            } else {
                // De saveEmailTemplates methode zet zelf al een flash message, maar we sturen hier een generieke fout.
                echo json_encode(['success' => false, 'message' => 'Kon de e-mail sjablonen niet opslaan. Controleer de schrijfrechten.']);
            }
            exit;
        }

        // Gebruikersregistratie goedkeuren/afwijzen
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manage_registration'])) {
            require_valid_csrf_token($_POST['csrf_token'] ?? null);
            $this->manageRegistration($pdo);
            header("Location: ?page=admin#registraties");
            exit;
        }

        // Gebruikers ophalen
        $stmt = $pdo->query("SELECT id, email, fullname, role, status, created_at, last_login FROM users WHERE status = 'active' ORDER BY id ASC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Nieuwe registraties ophalen
        $stmtPending = $pdo->query("SELECT id, email, fullname, created_at FROM users WHERE status = 'pending' ORDER BY created_at ASC");
        $pendingUsers = $stmtPending->fetchAll(PDO::FETCH_ASSOC);

        // Logboek en Systeemstatus data ophalen
        $logEntries = $this->getLogEntries($pdo);
        $systemStatus = $this->getSystemStatus();
        $clientPortals = $this->getClientPortals($pdo);
        $deletedVerslagen = $this->getDeletedVerslagen($pdo);
        $emailTemplates = $this->getEmailTemplates();
        $smtpSettings = $this->getSmtpSettings();
        $brandingSettings = $this->getBrandingSettings();

        include __DIR__ . '/../views/admin.php';
    }

    // Public gemaakt zodat andere controllers het ook kunnen gebruiken
    public function getSmtpSettings() {
        // Lees instellingen nu uit de environment variables
        return [
            'host' => $_ENV['SMTP_HOST'] ?? 'smtp.example.com',
            'port' => (int)($_ENV['SMTP_PORT'] ?? 587),
            'username' => $_ENV['SMTP_USER'] ?? '',
            'password' => $_ENV['SMTP_PASS'] ?? '',
            'encryption' => $_ENV['SMTP_ENCRYPTION'] ?? 'tls', // 'ssl' or 'tls'
            'from_address' => $_ENV['SMTP_FROM_ADDRESS'] ?? 'noreply@example.com',
            'from_name' => $_ENV['SMTP_FROM_NAME'] ?? 'Bezoekverslag App'
        ];
    }

    private function getBrandingSettings() {
        $configFile = __DIR__ . '/../../config/branding.php';
        return file_exists($configFile) ? require $configFile : [];
    }
    // Public gemaakt zodat andere controllers het ook kunnen gebruiken
    public function getEmailTemplates() {
        $configFile = __DIR__ . '/../../config/email_templates.php';
        return file_exists($configFile) ? require $configFile : [];
    }

    /**
     * Nieuwe gebruiker aanmaken
     * @return bool True on success, false on failure
     */
    private function createUser($pdo) {
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';

        if (!$fullname || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$role) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Vul alle velden correct in.'];
            return false;
        }

        // Controleer of e-mail al bestaat
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Dit e-mailadres is al in gebruik.'];
            return false;
        }

        $temporaryPassword = bin2hex(random_bytes(16));
        $passwordHash = password_hash($temporaryPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password, role, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
        if (!$stmt->execute([$fullname, $email, $passwordHash, $role])) {
            return false;
        }

        $newUserId = (int)$pdo->lastInsertId();
        $stmtUser = $pdo->prepare("SELECT id, email, fullname FROM users WHERE id = ?");
        $stmtUser->execute([$newUserId]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            if ($this->sendPasswordSetupLink($pdo, $user)) {
                $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Gebruiker aangemaakt. Er is een e-mail verstuurd met instructies om een wachtwoord in te stellen.'];
            } else {
                $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Gebruiker aangemaakt, maar het versturen van de wachtwoord e-mail is mislukt. Laat de gebruiker handmatig een reset aanvragen.'];
            }
        } else {
            $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Gebruiker aangemaakt, maar kon de gebruikersgegevens niet ophalen voor het versturen van een resetlink.'];
        }
        return true;
    }

    /**
     * Genereert een reset-token en verstuurt een e-mail zodat de gebruiker een wachtwoord kan instellen.
     */
    private function sendPasswordSetupLink(PDO $pdo, array $user): bool {
        if (empty($user['id']) || empty($user['email'])) {
            return false;
        }

        $plainToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $plainToken);

        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?");
        if (!$stmt->execute([$hashedToken, $user['id']])) {
            return false;
        }

        return (new AuthController())->sendPasswordResetEmail($user, $plainToken);
    }

    /** Registratie goedkeuren of afwijzen */
    private function manageRegistration($pdo) {
        requireRole(['admin', 'poweruser']);
        $userId = (int)($_POST['user_id'] ?? 0);
        $action = $_POST['action'] ?? '';

        if (!$userId || !in_array($action, ['approve', 'deny'])) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Ongeldige actie.'];
            return;
        }

        $stmt = $pdo->prepare("SELECT id, email, fullname FROM users WHERE id = ? AND status = 'pending'");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Gebruiker niet gevonden of al verwerkt.'];
            return;
        }

        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE users SET status = 'active', approved_at = NOW(), approved_by = ? WHERE id = ?");
            $stmt->execute([$_SESSION['user_id'], $userId]);
            log_action('user_approved', "Gebruiker '{$user['email']}' (ID: {$userId}) goedgekeurd door '{$_SESSION['email']}'.");
            (new AuthController())->sendApprovalEmail($user);
            $_SESSION['flash_message'] = ['type' => 'success', 'text' => "Gebruiker {$user['email']} is goedgekeurd en kan nu inloggen."];
        } elseif ($action === 'deny') {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            log_action('user_denied', "Registratie voor '{$user['email']}' (ID: {$userId}) afgewezen en verwijderd door '{$_SESSION['email']}'.");
            $_SESSION['flash_message'] = ['type' => 'info', 'text' => "Registratie voor {$user['email']} is afgewezen en verwijderd."];
        }
    }

    /** Gebruiker bijwerken (naam, rol, wachtwoord) */
    private function updateUser($pdo) {
        $id = (int)($_POST['user_id'] ?? 0);
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $newPasswordRepeat = $_POST['new_password_repeat'] ?? '';

        if (!$id || !$fullname || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$role) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Onvolledige of ongeldige invoer.'];
            return false;
        }

        $params = [
            'fullname' => $fullname,
            'email' => $email,
            'role' => $role,
            'id' => $id
        ];

        $sql = "UPDATE users SET fullname=:fullname, email=:email, role=:role, updated_at=NOW()";

        if (!empty($newPassword)) {
            if ($newPassword !== $newPasswordRepeat) {
                $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'De nieuwe wachtwoorden komen niet overeen.'];
                return false;
            }
            if (strlen($newPassword) < 8) {
                $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Nieuw wachtwoord moet minimaal 8 tekens bevatten.'];
                return false;
            }
            $params['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
            $sql .= ", password=:password";
        }

        $sql .= " WHERE id=:id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /** Gebruiker verwijderen */
    public function deleteUser($id) {
        requireRole(['admin', 'poweruser']);
        require_valid_csrf_token($_GET['csrf_token'] ?? null);
        $pdo = Database::getConnection();

        if (!$id || $id === $_SESSION['user_id']) {
             $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Ongeldige actie. U kunt uzelf niet verwijderen.'];
             header("Location: ?page=admin");
             exit;
        }

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);

        // Log de actie
        $userEmail = $pdo->query("SELECT email FROM users WHERE id = $id")->fetchColumn(); // Tijdelijk om email te krijgen
        log_action('user_deleted', "Gebruiker #{$id} ('{$userEmail}') is verwijderd.");

        header("Location: ?page=admin");
        exit;
    }

    /**
     * Login overnemen van een andere gebruiker.
     * @param int $id De ID van de gebruiker om over te nemen.
     */
    public function impersonateUser($id) {
        requireRole(['admin', 'poweruser']);
        require_valid_csrf_token($_GET['csrf_token'] ?? null);
        $pdo = Database::getConnection();

        // Voorkom dat je jezelf overneemt of een niet-bestaande gebruiker
        if ($id === $_SESSION['user_id']) {
             $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'U kunt uzelf niet overnemen.'];
             header("Location: ?page=admin");
             exit;
        }

        // Haal de doelgebruiker op
        $stmt = $pdo->prepare("SELECT id, email, fullname, role FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$targetUser) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Gebruiker niet gevonden.'];
            header("Location: ?page=admin");
            exit;
        }

        // Sla de originele admin-sessie op
        $_SESSION['original_user'] = [
            'user_id' => $_SESSION['user_id'],
            'email' => $_SESSION['email'],
            'fullname' => $_SESSION['fullname'],
            'role' => $_SESSION['role']
        ];

        // Overschrijf de huidige sessie met de gegevens van de doelgebruiker
        $_SESSION['user_id'] = $targetUser['id'];
        $_SESSION['email'] = $targetUser['email'];
        $_SESSION['fullname'] = $targetUser['fullname'];
        $_SESSION['role'] = $targetUser['role'];

        log_action('impersonate_start', "Admin '{$_SESSION['original_user']['email']}' heeft login overgenomen van '{$targetUser['email']}'.");

        header("Location: ?page=dashboard");
        exit;
    }

    /** Klantportaal toegang intrekken */
    public function revokeClientAccess($verslag_id) {
        requireRole(['admin', 'poweruser', 'accountmanager']);
        require_valid_csrf_token($_GET['csrf_token'] ?? null);
        $pdo = Database::getConnection();

        // Ownership check for accountmanager
        if (!isAdmin()) {
            $stmt = $pdo->prepare("SELECT id FROM bezoekverslag WHERE id = ? AND created_by = ?");
            $stmt->execute([$verslag_id, $_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                die('Geen toegang.');
            }
        }

        $stmt = $pdo->prepare("DELETE FROM client_access WHERE bezoekverslag_id = ?");
        $stmt->execute([$verslag_id]);
        log_action('client_access_revoked', "Toegang voor verslag #{$verslag_id} ingetrokken.");
        $_SESSION['flash_message'] = ['type' => 'info', 'text' => 'Klanttoegang is ingetrokken.'];
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '?page=dashboard'));
        exit;
    }

    /** Klantportaal toegang verlengen */
    public function extendClientAccess($verslag_id) {
        requireRole(['admin', 'poweruser', 'accountmanager']);
        require_valid_csrf_token($_GET['csrf_token'] ?? null);
        $pdo = Database::getConnection();

        // Ownership check for accountmanager
        if (!isAdmin()) {
            $stmt = $pdo->prepare("SELECT id FROM bezoekverslag WHERE id = ? AND created_by = ?");
            $stmt->execute([$verslag_id, $_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                die('Geen toegang.');
            }
        }

        $stmt = $pdo->prepare("UPDATE client_access SET expires_at = DATE_ADD(NOW(), INTERVAL 14 DAY) WHERE bezoekverslag_id = ?");
        $stmt->execute([$verslag_id]);
        log_action('client_access_extended', "Toegang voor verslag #{$verslag_id} verlengd.");

        // Stuur een e-mail naar de klant
        $this->sendClientExtendedEmail($pdo, $verslag_id, date('Y-m-d H:i:s', strtotime('+14 days')), $this->getSmtpSettings());

        $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Klanttoegang is met 14 dagen verlengd.'];
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '?page=dashboard'));
        exit;
    }

    /** Klantwachtwoord resetten vanuit admin/dashboard */
    public function resetClientPassword($verslag_id) {
        // De logica wordt al afgehandeld door de BezoekverslagController, we roepen die hier aan.
        // Dit voorkomt dubbele code. De rechten worden daar al gecheckt.
        require_valid_csrf_token($_GET['csrf_token'] ?? null);
        (new BezoekverslagController())->resetClientPassword($verslag_id, true);
        exit;
    }

    private function saveEmailTemplates() {
        $configFile = __DIR__ . '/../../config/email_templates.php';

        $newTemplates = [
            'password_reset' => [
                'subject' => $_POST['password_reset_subject'] ?? 'Wachtwoord resetten',
                'body' => $_POST['password_reset_body'] ?? '',
            ],
            'client_update' => [
                'subject' => $_POST['client_update_subject'] ?? 'Update van klant: {project_title}',
                'body' => $_POST['client_update_body'] ?? '',
            ],
            'new_user_created' => [
                'subject' => $_POST['new_user_created_subject'] ?? 'Welkom bij de Bezoekverslag App',
                'body' => $_POST['new_user_created_body'] ?? '',
            ],
            'new_client_login' => [
                'subject' => $_POST['new_client_login_subject'] ?? 'Toegang tot het klantportaal voor {project_title}',
                'body' => $_POST['new_client_login_body'] ?? '',
            ],
            'client_portal_extended' => [
                'subject' => $_POST['client_portal_extended_subject'] ?? 'Uw toegang tot het klantportaal is verlengd',
                'body' => $_POST['client_portal_extended_body'] ?? '',
            ],
        ];

        $content = "<?php\n// config/email_templates.php\nreturn " . var_export($newTemplates, true) . ";\n";

        if (file_put_contents($configFile, $content) === false) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Kon e-mail sjabloonbestand niet wegschrijven. Controleer de schrijfrechten.'];
            return false;
        }
        return true;
    }

    private function getLogEntries($pdo) {
        $stmt = $pdo->query("SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 100");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getSystemStatus() {
        $status = [];
        $storageDir = __DIR__ . '/../../storage';
        $uploadsDir = __DIR__ . '/../../public/uploads';

        // Check schrijfbaarheid
        $status['storage_writable'] = is_writable($storageDir);
        $status['uploads_writable'] = is_writable($uploadsDir);

        // Check database connectie
        try {
            Database::getConnection();
            $status['db_connection'] = true;
        } catch (PDOException $e) {
            $status['db_connection'] = false;
        }
        return $status;
    }

    private function saveBrandingSettings() {
        $configFile = __DIR__ . '/../../config/branding.php';
        $currentSettings = $this->getBrandingSettings();

        // Behoud het logo-pad, update alleen de kleuren
        $newSettings = [
            'logo_path' => $currentSettings['logo_path'] ?? '',
            'primary_color' => $_POST['primary_color'] ?? '#FFD200',
            'primary_color_contrast' => $_POST['primary_color_contrast'] ?? '#111111',
        ];

        $content = "<?php\nreturn " . var_export($newSettings, true) . ";\n";

        if (file_put_contents($configFile, $content) === false) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Kon configuratiebestand niet wegschrijven. Controleer de schrijfrechten.'];
            return false;
        }
        return true;
    }

    private function getDeletedVerslagen($pdo) {
        $stmt = $pdo->query("
            SELECT id, klantnaam, projecttitel, deleted_at, 
                   (deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)) as is_older_than_30_days
            FROM bezoekverslag 
            WHERE deleted_at IS NOT NULL 
            ORDER BY deleted_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function restoreVerslag($id) {
        requireRole(['admin', 'poweruser']);
        require_valid_csrf_token($_GET['csrf_token'] ?? null);
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE bezoekverslag SET deleted_at = NULL WHERE id = ?");
        $stmt->execute([$id]);
        log_action('verslag_restored', "Bezoekverslag #{$id} is hersteld uit de prullenbak.");
        $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Bezoekverslag is succesvol hersteld.'];
        header("Location: ?page=admin#prullenbak");
        exit;
    }

    public function permanentDeleteVerslag($id) {
        requireRole(['admin', 'poweruser']);
        require_valid_csrf_token($_GET['csrf_token'] ?? null);
        $pdo = Database::getConnection();

        // Haal alle ruimtes op die bij dit verslag horen voor het verwijderen van bestanden
        $stmt = $pdo->prepare("SELECT id FROM ruimte WHERE verslag_id = ?");
        $stmt->execute([$id]);
        $ruimtes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Verwijder de foto-mappen van de server
        foreach ($ruimtes as $ruimte) {
            $dir = __DIR__ . '/../../public/uploads/ruimte_' . $ruimte['id'];
            if (is_dir($dir)) {
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

        // Verwijder het bezoekverslag definitief uit de database (cascade doet de rest)
        $pdo->prepare("DELETE FROM bezoekverslag WHERE id = ?")->execute([$id]);
        log_action('verslag_permanently_deleted', "Bezoekverslag #{$id} is permanent verwijderd.");
        $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Bezoekverslag is permanent verwijderd.'];
        header("Location: ?page=admin#prullenbak");
        exit;
    }

    public function emptyTrash() {
        requireRole(['admin', 'poweruser']);
        require_valid_csrf_token($_GET['csrf_token'] ?? null);
        $pdo = Database::getConnection();
    
        // Haal eerst de IDs op van de te verwijderen verslagen om de bestanden op te ruimen
        $stmt = $pdo->query("SELECT id FROM bezoekverslag WHERE deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $verslagenToDelete = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        foreach ($verslagenToDelete as $verslag) {
            $this->deleteVerslagFiles($pdo, $verslag['id']);
        }
        log_action('trash_emptied', count($verslagenToDelete) . ' oude verslagen zijn permanent verwijderd uit de prullenbak.');
        $_SESSION['flash_message'] = ['type' => 'success', 'text' => count($verslagenToDelete) . ' oude verslagen zijn permanent verwijderd.'];
        header("Location: ?page=admin#prullenbak");
        exit;
    }

    /**
     * Haalt ALLEEN de klantportalen op die door de HUIDIGE gebruiker zijn aangemaakt.
     * Ongeacht de rol (admin/poweruser).
     */
    private function getMyClientPortals($pdo) {
        $userId = (int)($_SESSION['user_id'] ?? 0);
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
            WHERE b.created_by = ? ORDER BY ca.expires_at ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClientPortals($pdo) {
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

        if (!isAdmin()) {
            $sql .= " WHERE b.created_by = " . (int)($_SESSION['user_id'] ?? 0);
        }

        $sql .= " ORDER BY ca.expires_at ASC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function backupDatabase() {
        requireRole(['admin', 'poweruser']);
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null;
        require_valid_csrf_token($token);
        $pdo = Database::getConnection();
        $backup = "-- Bezoekverslag App SQL Backup\n-- Generation Time: " . date('Y-m-d H:i:s') . "\n\n";

        try {
            $tables_result = $pdo->query("SHOW TABLES");
            while ($row = $tables_result->fetch(PDO::FETCH_NUM)) {
                $table = $row[0];
                $backup .= "--\n-- Table structure for table `$table`\n--\n\n";
                $backup .= "DROP TABLE IF EXISTS `$table`;\n";
                $create_table_result = $pdo->query("SHOW CREATE TABLE `$table`");
                $create_table_row = $create_table_result->fetch(PDO::FETCH_ASSOC);
                $backup .= $create_table_row['Create Table'] . ";\n\n";

                $backup .= "--\n-- Dumping data for table `$table`\n--\n\n";
                $data_result = $pdo->query("SELECT * FROM `$table`");
                while ($data_row = $data_result->fetch(PDO::FETCH_ASSOC)) {
                    $keys = array_map([$pdo, 'quote'], array_keys($data_row));
                    $values = array_map([$pdo, 'quote'], array_values($data_row));
                    $backup .= "INSERT INTO `$table` (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ");\n";
                }
                $backup .= "\n";
            }

            log_action('db_backup', 'Database back-up gedownload.');

            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="backup-' . date('Y-m-d') . '.sql"');
            header('Content-Length: ' . strlen($backup));
            echo $backup;
            exit;

        } catch (Exception $e) {
            die("Fout bij het maken van de back-up: " . $e->getMessage());
        }
    }

    /**
     * Helper functie om bestanden van een verslag te verwijderen.
     * Wordt aangeroepen door permanentDeleteVerslag en emptyTrash.
     */
    private function deleteVerslagFiles($pdo, $verslagId) {
        // Verwijder de foto-mappen van de server
        $stmt = $pdo->prepare("SELECT id FROM ruimte WHERE verslag_id = ?");
        $stmt->execute([$verslagId]);
        $ruimtes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($ruimtes as $ruimte) {
            $dir = __DIR__ . '/../../public/uploads/ruimte_' . $ruimte['id'];
            if (is_dir($dir)) {
                // Deze code is vereenvoudigd; een robuustere functie zou recursief bestanden verwijderen.
                // Voor nu gaan we ervan uit dat dit voldoende is.
                array_map('unlink', glob("$dir/*.*"));
                @rmdir($dir);
            }
        }
        // Verwijder het bezoekverslag definitief uit de database (cascade doet de rest)
        $pdo->prepare("DELETE FROM bezoekverslag WHERE id = ?")->execute([$verslagId]);
    }

    private function handleLogoUpload() {
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../public/uploads/branding/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $allowedTypes = ['image/png', 'image/jpeg', 'image/svg+xml'];
            if (!in_array($_FILES['company_logo']['type'], $allowedTypes)) {
                $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Ongeldig bestandstype. Alleen PNG, JPG en SVG zijn toegestaan.'];
                return false;
            }

            $safeName = 'logo.' . pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION);
            $filePath = $uploadDir . $safeName;

            if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $filePath)) {
                $dbPath = 'uploads/branding/' . $safeName;
                
                $configFile = __DIR__ . '/../../config/branding.php';
                $currentSettings = $this->getBrandingSettings();
                $newSettings = $currentSettings;
                $newSettings['logo_path'] = $dbPath;

                $content = "<?php\nreturn " . var_export($newSettings, true) . ";\n";
                file_put_contents($configFile, $content);
                log_action('logo_updated', 'Bedrijfslogo is bijgewerkt.');
                return true;
            }
        }
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Fout bij het uploaden van het logo.'];
        return false;
    }

    private function sendClientExtendedEmail($pdo, $verslag_id, $newExpiryDate, $mailSettings) {
        $stmt = $pdo->prepare("
            SELECT ca.email, ca.fullname, b.projecttitel
            FROM client_access ca
            JOIN bezoekverslag b ON ca.bezoekverslag_id = b.id
            WHERE b.id = ?
        ");
        $stmt->execute([$verslag_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) return;

        $emailTemplates = $this->getEmailTemplates();
        $emailTemplate = $emailTemplates['client_portal_extended'] ?? null;

        $loginLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) . '/?page=client_login';
        $formattedDate = date('d-m-Y', strtotime($newExpiryDate));

        $placeholders = ['{client_name}', '{project_title}', '{login_link}', '{expiry_date}'];
        $values = [$data['fullname'], $data['projecttitel'], $loginLink, $formattedDate];

        // Voorkom fout als template niet bestaat
        if (!$emailTemplate) return;

        $subject = str_replace($placeholders, $values, $emailTemplate['subject'] ?? '');
        $body = str_replace($placeholders, $values, $emailTemplate['body'] ?? '');

        $this->sendEmail($data['email'], $data['fullname'], $subject, $body, $mailSettings);
    }

    private function sendEmail($toAddress, $toName, $subject, $body, $mailSettings) {
        if (empty($mailSettings['host']) || $mailSettings['host'] === 'smtp.example.com') {
            log_action('email_failed', "SMTP niet geconfigureerd. E-mail naar {$toAddress} niet verzonden.");
            return false;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
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

            $mail->setFrom($mailSettings['from_address'], $mailSettings['from_name']);
            $mail->addAddress($toAddress, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
            log_action('email_sent', "E-mail '{$subject}' verzonden naar {$toAddress}.");
            return true;
        } catch (Exception $e) {
            log_action('email_failed', "Fout bij verzenden naar {$toAddress}: " . $mail->ErrorInfo);
            // Toon de foutmelding alleen als we in een dev-omgeving zijn (optioneel)
            // $_SESSION['flash_message'] = ['type' => 'danger', 'text' => '<strong>Fout bij verzenden:</strong> ' . htmlspecialchars($mail->ErrorInfo)];
            return false;
        }
    }

    /**
     * Public wrapper voor de private sendEmail methode.
     * Dit is een tijdelijke oplossing. Een dedicated MailService zou beter zijn.
     */
    public function sendPublicEmail($toAddress, $toName, $subject, $body, $mailSettings) {
        return $this->sendEmail($toAddress, $toName, $subject, $body, $mailSettings);
    }

    public function sendCollaborationEmail($collaborator, $verslagInfo, $verslagId, $ownerName, $mailSettings) {
        $emailTemplate = $this->getEmailTemplates()['collaboration_invite'] ?? null;
        if (empty($emailTemplate)) {
            log_action('email_failed', "Samenwerking-mail niet verstuurd: template niet gevonden.");
            return false;
        }

        $verslagLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF'], 2) . '/public/?page=bewerk&id=' . $verslagId;

        $placeholders = [
            '{collaborator_name}',
            '{project_title}',
            '{owner_name}',
            '{verslag_link}'
        ];
        $values = [
            $collaborator['fullname'],
            $verslagInfo['projecttitel'],
            $ownerName,
            $verslagLink
        ];

        $subject = str_replace($placeholders, $values, $emailTemplate['subject']);
        $body = str_replace($placeholders, $values, $emailTemplate['body']);

        return $this->sendEmail($collaborator['email'], $collaborator['fullname'], $subject, $body, $mailSettings);
    }

    /**
     * Verstuurt een testmail en geeft een flash message array terug.
     * @return array De flash message voor de sessie.
     */
    public function testSmtp() {
        requireRole(['admin', 'poweruser']);
        require_valid_csrf_token($_GET['csrf_token'] ?? null);
        $mailSettings = $this->getSmtpSettings();
        $userEmail = $_SESSION['email'] ?? null;
        $userName = $_SESSION['fullname'] ?? 'Test Gebruiker';

        if (!$userEmail) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Geen e-mailadres gevonden voor de ingelogde gebruiker.'];
        } else {
            $subject = "SMTP Testmail van Bezoekverslag App";
            $body = "<p>Hallo {$userName},</p><p>Dit is een testmail om te controleren of de SMTP-instellingen correct zijn geconfigureerd.</p><p>Als je deze e-mail ontvangt, werkt alles naar behoren!</p>";
            $sendResult = $this->sendEmail($userEmail, $userName, $subject, $body, $mailSettings);

            $_SESSION['flash_message'] = ($sendResult === true)
                ? ['type' => 'info', 'text' => "Testmail is verstuurd naar {$userEmail}. Controleer je inbox en het logboek."]
                : ['type' => 'danger', 'text' => "Testmail kon niet worden verstuurd. Fout: " . $sendResult];
        }
        header("Location: ?page=admin#smtp");
        exit;
    }

    public function profile() {
        requireLogin();
        $pdo = Database::getConnection();

        // Gegevens bijwerken
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['update_profile']) || isset($_POST['change_password']))) {
            header('Content-Type: application/json');
            $response = ['success' => false, 'message' => 'Onbekende fout.'];

            if (isset($_POST['update_profile'])) {
                $dataUpdated = false;
                
                // Handel de profielgegevens af
                $fullname = trim($_POST['fullname'] ?? '');
                $email = trim($_POST['email'] ?? '');

                if ($fullname && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $stmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ? WHERE id = ?");
                    $stmt->execute([$fullname, $email, $_SESSION['user_id']]);
                    $dataUpdated = true;
                }

                if ($dataUpdated) {
                    $response = ['success' => true, 'message' => 'Profielgegevens bijgewerkt.'];
                } else {
                    $response['message'] = 'Ongeldige invoer voor naam of e-mailadres.';
                }
            }

            if (isset($_POST['change_password'])) {
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $newPasswordRepeat = $_POST['new_password_repeat'] ?? '';

                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($currentPassword, $user['password']) && $newPassword === $newPasswordRepeat && strlen($newPassword) >= 8) {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$newHash, $_SESSION['user_id']]);
                    $response = ['success' => true, 'message' => 'Wachtwoord succesvol gewijzigd.'];
                } else {
                    $response = ['success' => false, 'message' => 'Wachtwoord wijzigen mislukt. Controleer uw huidige wachtwoord en of de nieuwe wachtwoorden overeenkomen (min. 8 tekens).'];
                }
            }

            echo json_encode($response);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            session_destroy();
            header("Location: ?page=login");
            exit;
        }

        // Haal ook de verslagen op die door de gebruiker zijn aangemaakt
        $stmt = $pdo->prepare("SELECT id, projecttitel, klantnaam, created_at, pdf_version, pdf_up_to_date FROM bezoekverslag WHERE created_by = ? AND deleted_at IS NULL ORDER BY created_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
        $mijnVerslagen = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Haal verslagen op waar de gebruiker een collaborator is (en niet de eigenaar)
        $stmtCollab = $pdo->prepare("
            SELECT 
                b.id, b.projecttitel, b.klantnaam, b.created_at, u.fullname as owner_name
            FROM verslag_collaborators vc
            JOIN bezoekverslag b ON vc.verslag_id = b.id
            JOIN users u ON b.created_by = u.id
            WHERE vc.user_id = ? AND b.created_by != ? AND b.deleted_at IS NULL
            ORDER BY b.created_at DESC
        ");
        $stmtCollab->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
        $samenwerkingen = $stmtCollab->fetchAll(PDO::FETCH_ASSOC);

        // Haal verslagen op waar de gebruiker een collaborator is (en niet de eigenaar)
        $stmtCollab = $pdo->prepare("
            SELECT 
                b.id, b.projecttitel, b.klantnaam, b.created_at, u.fullname as owner_name
            FROM verslag_collaborators vc
            JOIN bezoekverslag b ON vc.verslag_id = b.id
            JOIN users u ON b.created_by = u.id
            WHERE vc.user_id = ? AND b.created_by != ? AND b.deleted_at IS NULL
            ORDER BY b.created_at DESC
        ");
        $stmtCollab->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
        $samenwerkingen = $stmtCollab->fetchAll(PDO::FETCH_ASSOC);

        // Haal de klantportalen op voor deze gebruiker
        $clientPortals = $this->getMyClientPortals($pdo);

        $msg = ''; // Voor eventuele feedback

        include __DIR__ . '/../views/profile.php';
    }



    /**
     * Stop de "login overnemen" sessie en herstel de originele admin.
     */
    public function stopImpersonation() {
        // Zorg ervoor dat er een actieve sessie is voordat we proberen te herstellen
        requireLogin();
        require_valid_csrf_token($_GET['csrf_token'] ?? null);
        if (isset($_SESSION['original_user'])) {
            $originalUser = $_SESSION['original_user'];
            $impersonatedEmail = $_SESSION['email']; // Voor de log

            // Vernietig de huidige (overgenomen) sessie volledig
            session_destroy();

            // Start een schone, nieuwe sessie
            session_start();

            // Herstel de originele sessie
            $_SESSION['user_id'] = $originalUser['user_id'];
            $_SESSION['email'] = $originalUser['email'];
            $_SESSION['fullname'] = $originalUser['fullname'];
            $_SESSION['role'] = $originalUser['role'];
            
            // Log de actie nu de originele sessie hersteld is
            log_action('impersonate_stop', "Admin '{$_SESSION['email']}' heeft de overname van '{$impersonatedEmail}' beëindigd.");
        }
        header("Location: ?page=admin");
        exit;
    }

    /**
     * Admin-initiated password reset.
     * @param int $id The ID of the user to send a reset link to.
     */
    public function adminResetPassword($id) {
        requireRole(['admin', 'poweruser']);
        require_valid_csrf_token($_GET['csrf_token'] ?? null);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT id, email, fullname FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if ($this->sendPasswordSetupLink($pdo, $user)) {
                $_SESSION['flash_message'] = ['type' => 'success', 'text' => "Een wachtwoord-reset e-mail is verstuurd naar {$user['email']}."];
            } else {
                $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Het versturen van de reset e-mail is mislukt.'];
            }
        } else {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Gebruiker niet gevonden.'];
        }
        header("Location: ?page=admin");
        exit;
    }
}

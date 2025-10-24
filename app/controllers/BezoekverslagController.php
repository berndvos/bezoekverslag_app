<?php

namespace App\Controllers;

use App\Config\Database;
use PDO;
use PDOException;
use Dompdf\Dompdf;
use Dompdf\Options;

class BezoekverslagController {
    private const REDIRECT_EDIT_PREFIX = 'Location: ?page=bewerk&id=';
    private const REDIRECT_DASHBOARD = 'Location: ?page=dashboard';
    private const PUBLIC_PATH = '/../../public/';
    private const SAFE_FILENAME_PATTERN = '/[^a-zA-Z0-9_-]/';
    private $sectionFields = [
        'relatie' => ['klantnaam', 'projecttitel', 'straatnaam', 'huisnummer', 'huisnummer_toevoeging', 'postcode', 'plaats', 'kvk', 'btw'],
        'contact' => ['contact_naam', 'contact_functie', 'contact_email', 'contact_tel'],
        'leveranciers' => ['situatie', 'functioneel', 'uitbreiding'],
        'wensen' => ['gewenste_offertedatum', 'indicatief_budget', 'wensen'],
        'eisen' => ['beeldkwaliteitseisen', 'geluidseisen', 'bedieningseisen', 'beveiligingseisen', 'netwerkeisen', 'garantie'],
        'installatie' => [
            'installatie_adres_afwijkend', 'installatie_adres_straat', 'installatie_adres_huisnummer', 'installatie_adres_huisnummer_toevoeging', 'installatie_adres_postcode', 'installatie_adres_plaats',
            'cp_locatie_afwijkend', 'cp_locatie_naam', 'cp_locatie_functie', 'cp_locatie_email', 'cp_locatie_tel',
            'afvoer', 'afvoer_omschrijving', 'installatiedatum', 'locatie_apparatuur', 'aantal_installaties',
            'parkeren', 'toegang', 'boortijden', 'opleverdatum'
        ],
    ];

    /* ================= DASHBOARD ================= */
    public function index() {
        requireLogin();
        $pdo = Database::getConnection();

        $search = $_GET['search'] ?? '';
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $isAdmin = isAdmin();
        $params = [
            ':userId' => $userId,
            ':userIdCollab' => $userId,
            ':userIdEdit' => $userId,
            ':isAdmin' => $isAdmin ? 1 : 0,
        ];

        $sql = "
            SELECT 
                b.id, b.klantnaam, b.projecttitel, b.created_at, b.pdf_version, b.pdf_generated_at, b.pdf_up_to_date,
                u.id AS created_by, u.fullname AS created_by_name,
                (SELECT COUNT(*) 
                 FROM foto f 
                 JOIN ruimte r ON f.ruimte_id = r.id 
                 WHERE r.verslag_id = b.id) AS photo_count,
                CASE WHEN b.created_by = :userId THEN 1 ELSE 0 END AS is_owner,
                CASE WHEN EXISTS (
                    SELECT 1 FROM verslag_collaborators vc WHERE vc.verslag_id = b.id AND vc.user_id = :userIdCollab
                ) THEN 1 ELSE 0 END AS is_collaborator,
                CASE WHEN :isAdmin = 1 
                    OR b.created_by = :userId 
                    OR EXISTS (
                        SELECT 1 FROM verslag_collaborators vc2 WHERE vc2.verslag_id = b.id AND vc2.user_id = :userIdEdit
                    )
                THEN 1 ELSE 0 END AS can_edit
            FROM bezoekverslag b
            LEFT JOIN users u ON b.created_by = u.id 
            WHERE b.deleted_at IS NULL";
        
        if (!empty($search)) {
            $sql .= " AND (b.klantnaam LIKE :search OR b.projecttitel LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY b.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $bezoekverslagen = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['bezoekverslagen' => $bezoekverslagen, 'search' => $search];
    }

    public function showDashboard($data) {
        requireLogin();
        extract($data); // Maakt $bezoekverslagen en $clientPortals beschikbaar in de view
        include_once __DIR__ . '/../views/dashboard.php';
    }

    /* ================= NIEUW / BEWERK ================= */
    public function nieuw() {
        requireLogin();
        $pdo = Database::getConnection();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_valid_csrf_token($_POST['csrf_token'] ?? null);
            $stmt = $pdo->prepare("
                INSERT INTO bezoekverslag (klantnaam, projecttitel, created_by, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([
                $_POST['klantnaam'],
                $_POST['projecttitel'],
                $_SESSION['user_id']
            ]);

            header(self::REDIRECT_EDIT_PREFIX . $pdo->lastInsertId());
            exit;
        }

        include_once __DIR__ . '/../views/bezoekverslag_new.php';
    }

    public function bewerk($id) {
        requireLogin();
        $pdo = Database::getConnection();

        $verslagOwner = $this->fetchVerslagOwner($pdo, (int)$id);
        $isOwner = $this->isVerslagOwner($verslagOwner);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleBewerkPost((int)$id, $pdo, $verslagOwner, $isOwner);
        }

        $this->ensureVerslagEditable((int)$id);

        $viewData = $this->prepareBewerkViewData($pdo, (int)$id, $isOwner, $verslagOwner);
        extract($viewData);

        include_once __DIR__ . '/../views/verslag_detail.php';
    }

    private function fetchVerslagOwner(PDO $pdo, int $id): ?array {
        $stmt = $pdo->prepare("SELECT created_by FROM bezoekverslag WHERE id = ?");
        $stmt->execute([$id]);
        $owner = $stmt->fetch(PDO::FETCH_ASSOC);

        return $owner ?: null;
    }

    private function isVerslagOwner(?array $verslagOwner): bool {
        if (!$verslagOwner) {
            return false;
        }

        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        return isAdmin() || (int)$verslagOwner['created_by'] === $currentUserId;
    }

    private function handleBewerkPost(int $id, PDO $pdo, ?array $verslagOwner, bool $isOwner): void {
        require_valid_csrf_token($_POST['csrf_token'] ?? null);
        header('Content-Type: application/json');

        if (!$this->userMayEditVerslag($verslagOwner)) {
            $this->jsonExit([
                'success' => false,
                'message' => 'Opslaan mislukt: U heeft geen rechten om dit verslag te bewerken omdat u niet de eigenaar bent.'
            ]);
        }

        if (isset($_POST['manage_collaboration'])) {
            $this->jsonExit($this->handleCollaborationUpdate($id, $pdo, $isOwner));
        }

        if (isset($_POST['manage_client_access'])) {
            $this->jsonExit($this->handleClientAccessUpdate($id, $pdo));
        }

        if (isset($_POST['upload_project_files'])) {
            $this->jsonExit($this->handleProjectFileUploadAction($pdo, $id));
        }

        if (isset($_POST['delete_project_file'])) {
            $this->jsonExit($this->handleProjectFileDeletion($id));
        }

        if (isset($_POST['save_section'])) {
            $this->jsonExit($this->handleSectionSave($pdo, $id));
        }

        $this->jsonExit(['success' => false, 'message' => 'Geen geldige actie ontvangen.']);
    }

    private function userMayEditVerslag(?array $verslagOwner): bool {
        if (!$verslagOwner) {
            return false;
        }

        if (isAdmin()) {
            return true;
        }

        return (int)($verslagOwner['created_by'] ?? 0) === (int)($_SESSION['user_id'] ?? 0);
    }

    private function handleCollaborationUpdate(int $id, PDO $pdo, bool $isOwner): array {
        if (!$isOwner) {
            return ['success' => false, 'message' => 'Alleen de eigenaar kan collaborators beheren.'];
        }

        if (isset($_POST['add_collaborator'])) {
            $collabId = (int)($_POST['collaborator_id'] ?? 0);
            if ($collabId <= 0) {
                return ['success' => false, 'message' => 'Ongeldige gebruiker geselecteerd.'];
            }

            $stmt = $pdo->prepare("INSERT IGNORE INTO verslag_collaborators (verslag_id, user_id, granted_by) VALUES (?, ?, ?)");
            $stmt->execute([$id, $collabId, $_SESSION['user_id']]);

            $stmtUser = $pdo->prepare("SELECT email, fullname FROM users WHERE id = ?");
            $stmtUser->execute([$collabId]);
            $collaborator = $stmtUser->fetch(PDO::FETCH_ASSOC);

            $stmtVerslag = $pdo->prepare("SELECT projecttitel FROM bezoekverslag WHERE id = ?");
            $stmtVerslag->execute([$id]);
            $verslagInfo = $stmtVerslag->fetch(PDO::FETCH_ASSOC);

            if ($collaborator && $verslagInfo) {
                $adminController = new AdminController();
                $mailSettings = $adminController->getSmtpSettings();
                $adminController->sendCollaborationEmail($collaborator, $verslagInfo, $id, $_SESSION['fullname'], $mailSettings);
            }

            return ['success' => true, 'message' => 'Collega toegevoegd. De pagina wordt herladen.'];
        }

        if (isset($_POST['remove_collaborator'])) {
            $collabId = (int)($_POST['collaborator_id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM verslag_collaborators WHERE verslag_id = ? AND user_id = ?");
            $stmt->execute([$id, $collabId]);

            return ['success' => true, 'message' => 'Collega verwijderd. De pagina wordt herladen.'];
        }

        return ['success' => false, 'message' => 'Ongeldige actie.'];
    }

    private function handleClientAccessUpdate(int $id, PDO $pdo): array {
        $contactEmail = $_POST['contact_email'] ?? '';
        $contactNaam = $_POST['contact_naam'] ?? 'Klant';
        $clientAccessEnabled = isset($_POST['client_access_enabled']);
        $clientCanEdit = isset($_POST['client_can_edit']);

        $stmtClient = $pdo->prepare("SELECT id FROM client_access WHERE bezoekverslag_id = ?");
        $stmtClient->execute([$id]);
        $existingClientAccess = $stmtClient->fetch();

        if ($clientAccessEnabled) {
            if (!$contactEmail || !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Een geldig e-mailadres voor de contactpersoon is vereist om portaaltoegang te geven.'];
            }

            if (!$existingClientAccess) {
                $password = bin2hex(random_bytes(8));
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("INSERT INTO client_access (bezoekverslag_id, email, password, fullname, can_edit, expires_at) VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 14 DAY))");
                $stmt->execute([$id, $contactEmail, $hashedPassword, $contactNaam, $clientCanEdit]);

                return ['success' => true, 'message' => "Klantaccount aangemaakt. Het eenmalige wachtwoord is: $password. Geef dit door aan de klant. De toegang is 14 dagen geldig."];
            }

            $stmt = $pdo->prepare("UPDATE client_access SET email = ?, fullname = ?, can_edit = ?, expires_at = DATE_ADD(NOW(), INTERVAL 14 DAY) WHERE bezoekverslag_id = ?");
            $stmt->execute([$contactEmail, $contactNaam, $clientCanEdit, $id]);

            return ['success' => true, 'message' => 'Klanttoegang bijgewerkt. De toegang is opnieuw 14 dagen geldig.'];
        }

        if ($existingClientAccess) {
            $pdo->prepare("DELETE FROM client_access WHERE bezoekverslag_id = ?")->execute([$id]);
            return ['success' => true, 'message' => 'Klanttoegang is ingetrokken.'];
        }

        return ['success' => false, 'message' => 'Geen bestaande klanttoegang gevonden.'];
    }

    private function handleProjectFileUploadAction(PDO $pdo, int $id): array {
        if (empty($_FILES['project_files']['name'][0])) {
            return ['success' => false, 'message' => 'Geen bestanden geselecteerd om te uploaden.'];
        }

        $result = $this->handleProjectFileUploads($pdo, $id);
        $message = "{$result['success']} bestand(en) succesvol geupload.";

        if (!empty($result['errors'])) {
            $message .= "<br>Fouten: <ul><li>" . implode("</li><li>", $result['errors']) . "</li></ul>";
            return ['success' => false, 'message' => $message];
        }

        return ['success' => true, 'message' => $message . ' De pagina wordt herladen.'];
    }

    private function handleProjectFileDeletion(int $verslagId): array {
        $fileId = (int)($_POST['file_id'] ?? 0);
        if ($this->deleteProjectFile($fileId, $verslagId)) {
            return ['success' => true, 'message' => 'Bestand verwijderd.'];
        }

        return ['success' => false, 'message' => 'Kon het bestand niet verwijderen.'];
    }

    private function handleSectionSave(PDO $pdo, int $id): array {
        $fieldsToUpdate = [];
        foreach ($this->sectionFields as $fields) {
            $fieldsToUpdate = array_merge($fieldsToUpdate, $fields);
        }

        if (empty($fieldsToUpdate)) {
            return ['success' => false, 'message' => 'Ongeldige sectie opgegeven.'];
        }

        $setClauses = [];
        $data = ['user' => $_SESSION['fullname'] ?? 'Onbekend', 'id' => $id];

        foreach ($fieldsToUpdate as $field) {
            $setClauses[] = "`$field` = :$field";
            if (in_array($field, ['installatiedatum', 'opleverdatum', 'gewenste_offertedatum'], true)) {
                $data[$field] = empty($_POST[$field]) ? null : $_POST[$field];
                continue;
            }
            $data[$field] = $_POST[$field] ?? null;
        }

        $setClauses[] = "pdf_up_to_date = 0";
        $setClauses[] = "last_modified_at = NOW()";
        $setClauses[] = "last_modified_by = :user";

        $sql = "UPDATE bezoekverslag SET " . implode(', ', $setClauses) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);

        try {
            $stmt->execute($data);
            return ['success' => true, 'message' => 'Alle gegevens succesvol opgeslagen.'];
        } catch (PDOException $e) {
            error_log("Save error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Databasefout bij opslaan: ' . $e->getMessage()];
        }
    }

    private function ensureVerslagEditable(int $id): void {
        if (canEditVerslag($id)) {
            return;
        }

        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'U heeft geen rechten om dit verslag te bewerken.'];
        header(self::REDIRECT_DASHBOARD);
        exit;
    }

    private function prepareBewerkViewData(PDO $pdo, int $id, bool $isOwner, ?array $verslagOwner): array {
        $stmt = $pdo->prepare("SELECT * FROM bezoekverslag WHERE id = ?");
        $stmt->execute([$id]);
        $verslag = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT r.*, (SELECT COUNT(*) FROM foto f WHERE f.ruimte_id = r.id) as photo_count
            FROM ruimte r 
            WHERE r.verslag_id = ?
        ");
        $stmt->execute([$id]);
        $ruimtes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmtClient = $pdo->prepare("SELECT * FROM client_access WHERE bezoekverslag_id = ?");
        $stmtClient->execute([$id]);
        $clientAccess = $stmtClient->fetch(PDO::FETCH_ASSOC);

        $stmtBestanden = $pdo->prepare("SELECT id, bestandsnaam FROM project_bestanden WHERE verslag_id = ? ORDER BY bestandsnaam ASC");
        $stmtBestanden->execute([$id]);
        $projectBestanden = $stmtBestanden->fetchAll(PDO::FETCH_ASSOC);

        [$collaborators, $colleagues] = $this->loadCollaborationViewData($pdo, $id, $isOwner, $verslagOwner);

        return [
            'verslag' => $verslag,
            'ruimtes' => $ruimtes,
            'clientAccess' => $clientAccess,
            'projectBestanden' => $projectBestanden,
            'collaborators' => $collaborators,
            'colleagues' => $colleagues,
            'isOwner' => $isOwner
        ];
    }

    private function loadCollaborationViewData(PDO $pdo, int $id, bool $isOwner, ?array $verslagOwner): array {
        if (!$isOwner) {
            return [[], []];
        }

        $stmtCollabs = $pdo->prepare("SELECT u.id, u.fullname, u.email FROM verslag_collaborators vc JOIN users u ON vc.user_id = u.id WHERE vc.verslag_id = ?");
        $stmtCollabs->execute([$id]);
        $collaborators = $stmtCollabs->fetchAll(PDO::FETCH_ASSOC);

        $ownerId = $verslagOwner['created_by'] ?? null;
        $excludeIds = array_filter(
            array_merge([$ownerId], array_column($collaborators, 'id')),
            function ($value) {
                return $value !== null;
            }
        );

        if (empty($excludeIds)) {
            return [$collaborators, []];
        }

        $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
        $stmtColleagues = $pdo->prepare("SELECT id, fullname FROM users WHERE id NOT IN ($placeholders) AND role != 'viewer'");
        $stmtColleagues->execute($excludeIds);
        $colleagues = $stmtColleagues->fetchAll(PDO::FETCH_ASSOC);

        return [$collaborators, $colleagues];
    }

    private function jsonExit(array $payload): void {
        echo json_encode($payload);
        exit;
    }
    /* ================= VERWIJDEREN ================= */
    public function delete($id) {
        requireRole(['admin', 'poweruser']);
        require_valid_csrf_token($_GET['csrf_token'] ?? null);
        $pdo = Database::getConnection();

        // Soft delete: zet de deleted_at timestamp
        $stmt = $pdo->prepare("UPDATE bezoekverslag SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);

        log_action('verslag_soft_deleted', "Bezoekverslag #{$id} is naar de prullenbak verplaatst.");
        $_SESSION['flash_message'] = ['type' => 'info', 'text' => 'Bezoekverslag is naar de prullenbak verplaatst.'];
        header(self::REDIRECT_DASHBOARD);
        exit;
    }

    /* ================= PDF GENERATIE ================= */
    public function generatePdf($id) {
        requireLogin();

        $this->ensureGdExtensionAvailable((int)$id);

        $pdo = Database::getConnection();
        $verslag = $this->fetchVerslagForPdf($pdo, (int)$id);

        $errors = $this->validatePdfData($verslag);
        if (!empty($errors)) {
            $this->redirectWithPdfErrors((int)$id, $errors);
        }

        $pdfDir = $this->ensurePdfDirectory();
        $this->streamExistingPdfIfAvailable($verslag, $pdfDir);
        $this->removeExistingPdf($verslag, $pdfDir);

        [$ruimtes, $projectBestanden] = $this->loadPdfRelatedData($pdo, (int)$id);
        $html = $this->renderPdfTemplate($verslag, $ruimtes, $projectBestanden);
        $dompdf = $this->createPdfGenerator($html);

        $pdfFilename = $this->storePdfOutput($dompdf, $verslag, $pdfDir);
        $this->updatePdfMetadata($pdo, (int)$id, $verslag, $pdfFilename);

        $dompdf->stream($pdfFilename, ['Attachment' => 0]);
    }

    private function ensureGdExtensionAvailable(int $verslagId): void {
        if (extension_loaded('gd')) {
            return;
        }

        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Fout: De GD PHP-extensie is niet ingeschakeld op de server. Deze is nodig voor het verwerken van afbeeldingen in de PDF.'];
        header(self::REDIRECT_EDIT_PREFIX . $verslagId);
        exit;
    }

    private function fetchVerslagForPdf(PDO $pdo, int $id): array {
        $stmt = $pdo->prepare('
            SELECT b.*, u.fullname AS accountmanager_naam
            FROM bezoekverslag b
            LEFT JOIN users u ON b.created_by = u.id
            WHERE b.id = ?
        ');
        $stmt->execute([$id]);
        $verslag = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$verslag) {
            die('Verslag niet gevonden.');
        }

        return $verslag;
    }

    private function validatePdfData(array $verslag): array {
        $errors = [];
        if (($verslag['installatie_adres_afwijkend'] ?? 'Nee') === 'Ja') {
            if (empty($verslag['installatie_adres_straat'])) {
                $errors[] = 'Afwijkend installatieadres: "Adres" is verplicht.';
            }
            if (empty($verslag['installatie_adres_postcode'])) {
                $errors[] = 'Afwijkend installatieadres: "Postcode" is verplicht.';
            }
            if (empty($verslag['installatie_adres_plaats'])) {
                $errors[] = 'Afwijkend installatieadres: "Plaats" is verplicht.';
            }
        }

        if (($verslag['cp_locatie_afwijkend'] ?? 'Nee') === 'Ja') {
            if (empty($verslag['cp_locatie_naam'])) {
                $errors[] = 'Contactpersoon op locatie: "Naam" is verplicht.';
            }
            if (empty($verslag['cp_locatie_email']) && empty($verslag['cp_locatie_tel'])) {
                $errors[] = 'Contactpersoon op locatie: "E-mailadres" of "Telefoonnummer" is verplicht.';
            }
        }

        return $errors;
    }

    private function redirectWithPdfErrors(int $id, array $errors): void {
        $errorHtml = '<ul><li>' . implode('</li><li>', $errors) . '</li></ul>';
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => '<strong>PDF niet gegenereerd.</strong> De volgende velden zijn verplicht:<br>' . $errorHtml];
        header(self::REDIRECT_EDIT_PREFIX . $id);
        exit;
    }

    private function ensurePdfDirectory(): string {
        $pdfDir = __DIR__ . '/../../storage/pdfs/';
        if (!is_dir($pdfDir)) {
            mkdir($pdfDir, 0777, true);
        }

        return $pdfDir;
    }

    private function streamExistingPdfIfAvailable(array $verslag, string $pdfDir): void {
        if (empty($verslag['pdf_up_to_date']) || empty($verslag['pdf_path'])) {
            return;
        }

        $fullPath = $pdfDir . $verslag['pdf_path'];
        if (!file_exists($fullPath)) {
            return;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($verslag['pdf_path']) . '"');
        readfile($fullPath);
        exit;
    }

    private function removeExistingPdf(array $verslag, string $pdfDir): void {
        if (empty($verslag['pdf_path'])) {
            return;
        }

        $fullPath = $pdfDir . $verslag['pdf_path'];
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }

    private function loadPdfRelatedData(PDO $pdo, int $id): array {
        $stmt = $pdo->prepare('SELECT * FROM ruimte WHERE verslag_id = ?');
        $stmt->execute([$id]);
        $ruimtes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $fotoStmt = $pdo->prepare('SELECT pad FROM foto WHERE ruimte_id = ?');
        foreach ($ruimtes as $index => $ruimte) {
            $fotoStmt->execute([$ruimte['id']]);
            $ruimtes[$index]['fotos'] = $fotoStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmtBestanden = $pdo->prepare('SELECT bestandsnaam FROM project_bestanden WHERE verslag_id = ? ORDER BY bestandsnaam ASC');
        $stmtBestanden->execute([$id]);
        $projectBestanden = $stmtBestanden->fetchAll(PDO::FETCH_ASSOC);

        return [$ruimtes, $projectBestanden];
    }

    private function renderPdfTemplate(array $verslag, array $ruimtes, array $projectBestanden): string {
        ob_start();
        $data = compact('verslag', 'ruimtes', 'projectBestanden');
        extract($data);
        include_once __DIR__ . '/../views/pdf_template.php';
        return (string)ob_get_clean();
    }

    private function createPdfGenerator(string $html): Dompdf {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf;
    }

    private function storePdfOutput(Dompdf $dompdf, array $verslag, string $pdfDir): string {
        $sanitize = static function ($value) {
            $value = (string)($value ?? '');
            $value = preg_replace('/[^\pL\pN _-]+/u', ' ', $value);
            $value = preg_replace('/\s+/', ' ', $value);
            $value = trim($value, ' .-_');
            return $value !== '' ? $value : 'naamloos';
        };

        $safeKlantnaam = $sanitize($verslag['klantnaam'] ?? '');
        $safeProjecttitel = $sanitize($verslag['projecttitel'] ?? '');
        $pdfFilename = sprintf('Bezoekverslag - %s - %s.pdf', $safeKlantnaam, $safeProjecttitel);

        file_put_contents($pdfDir . $pdfFilename, $dompdf->output());

        return $pdfFilename;
    }

    private function updatePdfMetadata(PDO $pdo, int $id, array $verslag, string $pdfFilename): void {
        $newVersion = (int)($verslag['pdf_version'] ?? 0) + 1;
        $stmt = $pdo->prepare('
            UPDATE bezoekverslag
            SET pdf_version = ?, pdf_path = ?, pdf_up_to_date = 1, pdf_generated_at = NOW()
            WHERE id = ?
        ');
        $stmt->execute([$newVersion, $pdfFilename, $id]);
    }
    /* ================= CLIENT PASSWORD RESET ================= */
    public function resetClientPassword($id) {
        requireRole(['admin', 'poweruser', 'accountmanager']);
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null;
        require_valid_csrf_token($token);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT id FROM client_access WHERE bezoekverslag_id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Geen klantaccount gevonden om te resetten.'];
        } else {
            $password = bin2hex(random_bytes(8));
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("UPDATE client_access SET password = ? WHERE bezoekverslag_id = ?");
            $stmt->execute([$hashedPassword, $id]);

            $_SESSION['flash_message'] = ['type' => 'warning', 'text' => "Wachtwoord gereset. Het nieuwe eenmalige wachtwoord is: <strong>$password</strong>. Geef dit door aan de klant."];
        }
        header(self::REDIRECT_EDIT_PREFIX . $id);
        exit;
    }

    public function downloadProjectFilesAsZip($verslag_id) {
        requireLogin();

        if (!class_exists('ZipArchive')) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Fout: De ZipArchive PHP-extensie is niet ingeschakeld op de server.'];
            header(self::REDIRECT_EDIT_PREFIX . $verslag_id);
            exit;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT bestandsnaam, pad FROM project_bestanden WHERE verslag_id = ?");
        $stmt->execute([$verslag_id]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($files)) {
            $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Er zijn geen projectbestanden om te downloaden.'];
            header(self::REDIRECT_EDIT_PREFIX . $verslag_id);
            exit;
        }

        $verslagStmt = $pdo->prepare("SELECT klantnaam FROM bezoekverslag WHERE id = ?");
        $verslagStmt->execute([$verslag_id]);
        $verslag = $verslagStmt->fetch(PDO::FETCH_ASSOC);
        $safeKlantnaam = preg_replace(self::SAFE_FILENAME_PATTERN, '_', $verslag['klantnaam'] ?? 'project');
        $zipFilename = 'Projectbestanden_' . $safeKlantnaam . '.zip';

        $zip = new ZipArchive();
        $tempZipFile = tempnam(sys_get_temp_dir(), 'zip');

        if ($zip->open($tempZipFile, ZipArchive::CREATE) !== TRUE) {
            die("Kan geen tijdelijk ZIP-bestand aanmaken.");
        }

        foreach ($files as $file) {
            $fullPath = __DIR__ . self::PUBLIC_PATH . $file['pad'];
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

    private function handleProjectFileUploads($pdo, $verslag_id) {
        $errors = [];
        $successCount = 0;
        $uploadDir = __DIR__ . self::PUBLIC_PATH . 'uploads/project_' . $verslag_id . '/';
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
                    $dbPath = 'uploads/project_' . $verslag_id . '/' . $safeName;
                    $stmt = $pdo->prepare("INSERT INTO project_bestanden (verslag_id, bestandsnaam, pad) VALUES (?, ?, ?)");
                    $stmt->execute([$verslag_id, $originalName, $dbPath]);
                    $successCount++;
                } else {
                    $errors[] = "Kon bestand '{$originalName}' niet verplaatsen.";
                }
            } elseif ($errorCode !== UPLOAD_ERR_NO_FILE) {
                // Geef een duidelijke foutmelding op basis van de error code
                $originalName = basename($name);
                switch ($errorCode) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $errors[] = "Bestand '{$originalName}' is te groot.";
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $errors[] = "Bestand '{$originalName}' is slechts gedeeltelijk geÃƒÂ¼pload.";
                        break;
                    default:
                        $errors[] = "Onbekende fout bij uploaden van '{$originalName}' (Error code: {$errorCode}).";
                        break;
                }
            }
        }
        $this->markPdfAsOutdated($pdo, $verslag_id);
        return ['success' => $successCount, 'errors' => $errors];
    }

    public function deleteProjectFile($file_id, $verslag_id) {
        requireLogin();
        $pdo = Database::getConnection();

        // Controleer eigenaarschap
        $stmt = $pdo->prepare("SELECT pb.pad FROM project_bestanden pb JOIN bezoekverslag b ON pb.verslag_id = b.id WHERE pb.id = ? AND b.id = ?");
        $stmt->execute([$file_id, $verslag_id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($file) {
            // Verwijder bestand van server
            $fullPath = __DIR__ . self::PUBLIC_PATH . $file['pad'];
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
            // Verwijder record uit database
            $pdo->prepare("DELETE FROM project_bestanden WHERE id = ?")->execute([$file_id]);
            $this->markPdfAsOutdated($pdo, $verslag_id);
            return true;
        }
        return false;
    }

    public function downloadPhotosAsZip($verslag_id) {
        requireLogin();

        // Controleer of de Zip-extensie is ingeschakeld
        if (!class_exists('ZipArchive')) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Fout: De ZipArchive PHP-extensie is niet ingeschakeld op de server.'];
            header(self::REDIRECT_DASHBOARD);
            exit;
        }

        $pdo = Database::getConnection();

        // Haal alle ruimtes en hun foto's op voor dit verslag
        $stmt = $pdo->prepare("
            SELECT r.naam AS ruimte_naam, f.pad AS foto_pad
            FROM ruimte r
            JOIN foto f ON r.id = f.ruimte_id
            WHERE r.verslag_id = ?
            ORDER BY r.naam, f.id
        ");
        $stmt->execute([$verslag_id]);
        $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($photos)) {
            $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Er zijn geen foto\'s beschikbaar om te downloaden voor dit verslag.'];
            header(self::REDIRECT_DASHBOARD);
            exit;
        }

        // Haal verslaggegevens op voor de bestandsnaam
        $verslagStmt = $pdo->prepare("SELECT klantnaam, projecttitel FROM bezoekverslag WHERE id = ?");
        $verslagStmt->execute([$verslag_id]);
        $verslag = $verslagStmt->fetch(PDO::FETCH_ASSOC);
        $safeKlantnaam = preg_replace(self::SAFE_FILENAME_PATTERN, '_', $verslag['klantnaam'] ?? 'verslag');
        $zipFilename = 'Fotos_' . $safeKlantnaam . '.zip';

        $zip = new ZipArchive();
        $tempZipFile = tempnam(sys_get_temp_dir(), 'zip');

        if ($zip->open($tempZipFile, ZipArchive::CREATE) !== TRUE) {
            die("Kan geen tijdelijk ZIP-bestand aanmaken.");
        }

        $photoCounters = [];
        foreach ($photos as $photo) {
            $ruimteNaam = preg_replace(self::SAFE_FILENAME_PATTERN, '_', $photo['ruimte_naam']);
            $fullPhotoPath = __DIR__ . self::PUBLIC_PATH . $photo['foto_pad'];

            if (file_exists($fullPhotoPath)) {
                // Bepaal het volgnummer voor de foto binnen de ruimte
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
        unlink($tempZipFile); // Verwijder het tijdelijke bestand
        exit;
    }

    private function markPdfAsOutdated($pdo, $verslag_id) {
        $stmt = $pdo->prepare("UPDATE bezoekverslag SET pdf_up_to_date = 0 WHERE id = ?");
        $stmt->execute([$verslag_id]);
    }
}





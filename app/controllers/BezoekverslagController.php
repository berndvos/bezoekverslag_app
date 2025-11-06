<?php

namespace App\Controllers;

use App\Config\Database;
use App\Services\ViewRenderer;
use App\Services\Bezoekverslag\PdfService;
use App\Services\Bezoekverslag\FileService;
use App\Services\Bezoekverslag\EditorService;
use PDO;
use PDOException;

class BezoekverslagController {
    private const REDIRECT_EDIT_PREFIX = 'Location: ?page=bewerk&id=';
    private const REDIRECT_DASHBOARD = 'Location: ?page=dashboard';
    // Removed file/path constants; file ops handled by services.
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

    private ViewRenderer $view;
    private PdfService $pdfService;
    private FileService $fileService;
    private EditorService $editorService;

    public function __construct()
    {
        $this->view = new ViewRenderer();
        $this->fileService = new FileService();
        $this->editorService = new EditorService($this->fileService);
        $this->pdfService = new PdfService($this->view);
    }

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
        $this->view->render('dashboard', $data);
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

        $this->view->render('bezoekverslag_new');
    }

    public function bewerk($id) {
        requireLogin();
        $pdo = Database::getConnection();

        $verslagOwner = $this->editorService->fetchVerslagOwner($pdo, (int)$id);
        $isOwner = $this->editorService->isVerslagOwner($verslagOwner);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleBewerkPost((int)$id, $pdo, $verslagOwner, $isOwner);
        }

        $this->editorService->ensureVerslagEditable((int)$id);

        $viewData = $this->editorService->prepareBewerkViewData($pdo, (int)$id, $isOwner, $verslagOwner);
        $this->view->render('verslag_detail', $viewData);
    }

    

    private function handleBewerkPost(int $id, PDO $pdo, ?array $verslagOwner, bool $isOwner): void {
        require_valid_csrf_token($_POST['csrf_token'] ?? null);
        header('Content-Type: application/json');

        if (!$this->editorService->userMayEditVerslag($id)) {
            $this->jsonExit([
                'success' => false,
                'message' => 'Opslaan mislukt: U heeft geen rechten om dit verslag te bewerken.'
            ]);
        }

        if (isset($_POST['manage_collaboration'])) {
            $this->jsonExit($this->editorService->handleCollaborationUpdate($id, $pdo, $isOwner));
        }

        if (isset($_POST['manage_client_access'])) {
            $this->jsonExit($this->editorService->handleClientAccessUpdate($id, $pdo));
        }

        if (isset($_POST['upload_project_files'])) {
            $this->jsonExit($this->editorService->handleProjectFileUploadAction($pdo, $id));
        }

        if (isset($_POST['delete_project_file'])) {
            $this->jsonExit($this->editorService->handleProjectFileDeletion($id));
        }

        if (isset($_POST['save_section'])) {
            $this->jsonExit($this->editorService->handleSectionSave($pdo, $id, $this->sectionFields));
        }

        $this->jsonExit(['success' => false, 'message' => 'Geen geldige actie ontvangen.']);
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
        $this->pdfService->generate((int)$id);
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
        $this->fileService->downloadProjectFilesAsZip((int)$verslag_id);
    }

    public function deleteProjectFile($file_id, $verslag_id) {
        return $this->fileService->deleteProjectFile((int)$file_id, (int)$verslag_id);
    }

    public function downloadPhotosAsZip($verslag_id) {
        $this->fileService->downloadPhotosAsZip((int)$verslag_id);
    }

    
}

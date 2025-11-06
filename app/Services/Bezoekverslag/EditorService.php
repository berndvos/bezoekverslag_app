<?php

namespace App\Services\Bezoekverslag;

use App\Controllers\AdminController;
use PDO;
use PDOException;

class EditorService
{
    private FileService $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    public function fetchVerslagOwner(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare("SELECT created_by FROM bezoekverslag WHERE id = ?");
        $stmt->execute([$id]);
        $owner = $stmt->fetch(PDO::FETCH_ASSOC);
        return $owner ?: null;
    }

    public function isVerslagOwner(?array $verslagOwner): bool
    {
        if (!$verslagOwner) {
            return false;
        }
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        return isAdmin() || (int)$verslagOwner['created_by'] === $currentUserId;
    }

    public function userMayEditVerslag(int $id): bool
    {
        return canEditVerslag($id);
    }

    public function ensureVerslagEditable(int $id): void
    {
        if (canEditVerslag($id)) {
            return;
        }
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'U heeft geen rechten om dit verslag te bewerken.'];
        header('Location: ?page=dashboard');
        exit;
    }

    public function handleCollaborationUpdate(int $id, PDO $pdo, bool $isOwner): array
    {
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

    public function handleClientAccessUpdate(int $id, PDO $pdo): array
    {
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

    public function handleProjectFileUploadAction(PDO $pdo, int $id): array
    {
        if (empty($_FILES['project_files']['name'][0])) {
            return ['success' => false, 'message' => 'Geen bestanden geselecteerd om te uploaden.'];
        }

        $result = $this->fileService->handleProjectFileUploads($pdo, $id);
        $message = "{$result['success']} bestand(en) succesvol geupload.";
        if (!empty($result['errors'])) {
            $message .= "<br>Fouten: <ul><li>" . implode("</li><li>", $result['errors']) . "</li></ul>";
            return ['success' => false, 'message' => $message];
        }
        return ['success' => true, 'message' => $message . ' De pagina wordt herladen.'];
    }

    public function handleProjectFileDeletion(int $verslagId): array
    {
        $fileId = (int)($_POST['file_id'] ?? 0);
        if ($this->fileService->deleteProjectFile($fileId, $verslagId)) {
            return ['success' => true, 'message' => 'Bestand verwijderd.'];
        }
        return ['success' => false, 'message' => 'Kon het bestand niet verwijderen.'];
    }

    public function handleSectionSave(PDO $pdo, int $id, array $sectionFields): array
    {
        $fieldsToUpdate = [];
        foreach ($sectionFields as $fields) {
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

    public function prepareBewerkViewData(PDO $pdo, int $id, bool $isOwner, ?array $verslagOwner): array
    {
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

        $allUsersStmt = $pdo->query("SELECT id, fullname, email FROM users WHERE role != 'viewer' ORDER BY fullname");
        $allUsers = $allUsersStmt->fetchAll(PDO::FETCH_ASSOC);

        return compact('verslag', 'ruimtes', 'clientAccess', 'projectBestanden', 'collaborators', 'colleagues', 'isOwner', 'allUsers');
    }

    public function loadCollaborationViewData(PDO $pdo, int $id, bool $isOwner, ?array $verslagOwner): array
    {
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
}

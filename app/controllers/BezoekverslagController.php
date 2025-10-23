<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class BezoekverslagController {
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
        $params = [];

        $userId = (int)($_SESSION['user_id'] ?? 0);
        $params[':userId'] = $userId;

        $sql = "
            SELECT 
                b.id, b.klantnaam, b.projecttitel, b.created_at, b.pdf_version, b.pdf_generated_at, b.pdf_up_to_date,
                u.id AS created_by, u.fullname AS created_by_name,
                (SELECT COUNT(*) 
                 FROM foto f 
                 JOIN ruimte r ON f.ruimte_id = r.id 
                 WHERE r.verslag_id = b.id) AS photo_count,
                (CASE WHEN b.created_by = :userId THEN 1 ELSE 0 END) as is_owner
            FROM bezoekverslag b
            LEFT JOIN users u ON b.created_by = u.id 
            LEFT JOIN verslag_collaborators vc ON b.id = vc.verslag_id
            WHERE b.deleted_at IS NULL
            AND (b.created_by = :userId OR vc.user_id = :userId)";
        
        if (!empty($search)) {
            $sql .= " AND (b.klantnaam LIKE :search OR b.projecttitel LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= " GROUP BY b.id ORDER BY b.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $bezoekverslagen = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['bezoekverslagen' => $bezoekverslagen, 'search' => $search];
    }

    public function showDashboard($data) {
        requireLogin();
        extract($data); // Maakt $bezoekverslagen en $clientPortals beschikbaar in de view
        include __DIR__ . '/../views/dashboard.php';
    }

    /* ================= NIEUW / BEWERK ================= */
    public function nieuw() {
        requireLogin();
        $pdo = Database::getConnection();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $stmt = $pdo->prepare("
                INSERT INTO bezoekverslag (klantnaam, projecttitel, created_by, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([
                $_POST['klantnaam'],
                $_POST['projecttitel'],
                $_SESSION['user_id']
            ]);

            header("Location: ?page=bewerk&id=" . $pdo->lastInsertId());
            exit;
        }

        include __DIR__ . '/../views/bezoekverslag_new.php';
    }

    public function bewerk($id) {
        requireLogin();
        $pdo = Database::getConnection();

        // Haal eerst het verslag op om de eigenaar te controleren
        $stmt = $pdo->prepare("SELECT created_by FROM bezoekverslag WHERE id = ?");
        $stmt->execute([$id]);
        $verslagOwner = $stmt->fetch(PDO::FETCH_ASSOC);
        $isOwner = ($verslagOwner && $verslagOwner['created_by'] == $_SESSION['user_id']) || isAdmin();

        // Als er een POST request is, sla de data op
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');
            
            // Toegangscontrole voor POST requests: alleen de eigenaar of een admin mag opslaan.
            if (!$verslagOwner || (!isAdmin() && $verslagOwner['created_by'] != $_SESSION['user_id'])) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Opslaan mislukt: U heeft geen rechten om dit verslag te bewerken omdat u niet de eigenaar bent.'
                ]);
                exit;
            }
            
            // --- Samenwerking beheren ---
            if (isset($_POST['manage_collaboration'])) {
                if (!$isOwner) {
                    echo json_encode(['success' => false, 'message' => 'Alleen de eigenaar kan collaborators beheren.']);
                    exit;
                }
                if (isset($_POST['add_collaborator'])) {
                    $collabId = (int)$_POST['collaborator_id'];
                    $stmt = $pdo->prepare("INSERT IGNORE INTO verslag_collaborators (verslag_id, user_id, granted_by) VALUES (?, ?, ?)");
                    $stmt->execute([$id, $collabId, $_SESSION['user_id']]);

                    // E-mailnotificatie versturen
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

                    $response = ['success' => true, 'message' => 'Collega toegevoegd. De pagina wordt herladen.'];
                } elseif (isset($_POST['remove_collaborator'])) {
                    $collabId = (int)$_POST['collaborator_id'];
                    $stmt = $pdo->prepare("DELETE FROM verslag_collaborators WHERE verslag_id = ? AND user_id = ?");
                    $stmt->execute([$id, $collabId]);
                    $response = ['success' => true, 'message' => 'Collega verwijderd. De pagina wordt herladen.'];
                } else {
                    $response = ['success' => false, 'message' => 'Ongeldige actie.'];
                }
                
                echo json_encode($response);
                exit;
            }

            $response = ['success' => false, 'message' => 'Onbekende fout opgetreden.'];

            // --- Klantportaal Toegang Beheren ---
            if (isset($_POST['manage_client_access'])) {
                $contactEmail = $_POST['contact_email'] ?? '';
                $contactNaam = $_POST['contact_naam'] ?? 'Klant';
                $clientAccessEnabled = isset($_POST['client_access_enabled']);
                $clientCanEdit = isset($_POST['client_can_edit']);

                $stmtClient = $pdo->prepare("SELECT id FROM client_access WHERE bezoekverslag_id = ?");
                $stmtClient->execute([$id]);
                $existingClientAccess = $stmtClient->fetch();

                // Logica voor het aan- of uitzetten van de toegang
                if ($clientAccessEnabled) {
                    if (!$contactEmail || !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
                        $response = ['success' => false, 'message' => 'Een geldig e-mailadres voor de contactpersoon is vereist om portaaltoegang te geven.'];
                    } elseif (!$existingClientAccess) {
                        // --- NIEUW ACCOUNT ---
                        // Maak nieuw client account aan
                        $password = bin2hex(random_bytes(8)); // Genereer een 16-karakter wachtwoord
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                        $stmt = $pdo->prepare("INSERT INTO client_access (bezoekverslag_id, email, password, fullname, can_edit, expires_at) VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 14 DAY))");
                        $stmt->execute([$id, $contactEmail, $hashedPassword, $contactNaam, $clientCanEdit]);

                        $response = ['success' => true, 'message' => "Klantaccount aangemaakt. Het eenmalige wachtwoord is: $password. Geef dit door aan de klant. De toegang is 14 dagen geldig."];
                    } else {
                        // --- BESTAAND ACCOUNT UPDATEN ---
                        // Update bestaand account
                        $stmt = $pdo->prepare("UPDATE client_access SET email = ?, fullname = ?, can_edit = ?, expires_at = DATE_ADD(NOW(), INTERVAL 14 DAY) WHERE bezoekverslag_id = ?");
                        $stmt->execute([$contactEmail, $contactNaam, $clientCanEdit, $id]);
                        $response = ['success' => true, 'message' => 'Klanttoegang bijgewerkt. De toegang is opnieuw 14 dagen geldig.'];
                    }
                } elseif ($existingClientAccess) {
                    // --- TOEGANG INTREKKEN ---
                    // Verwijder toegang
                    $pdo->prepare("DELETE FROM client_access WHERE bezoekverslag_id = ?")->execute([$id]);
                    $response = ['success' => true, 'message' => 'Klanttoegang is ingetrokken.'];
                }

                // Stop hier na het afhandelen van de klanttoegang
                echo json_encode($response);
                exit;
            }

            // --- Projectbestanden uploaden ---
            if (isset($_POST['upload_project_files'])) {
                if (!empty($_FILES['project_files']['name'][0])) {
                    $result = $this->handleProjectFileUploads($pdo, $id);
                    $message = "{$result['success']} bestand(en) succesvol geüpload.";
                    if (!empty($result['errors'])) {
                        $message .= "<br>Fouten: <ul><li>" . implode("</li><li>", $result['errors']) . "</li></ul>";
                        $response = ['success' => false, 'message' => $message];
                    } else {
                        $response = ['success' => true, 'message' => $message . ' De pagina wordt herladen.'];
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Geen bestanden geselecteerd om te uploaden.'];
                    echo json_encode($response);
                    exit;
                }
                echo json_encode($response);
                exit;
            }

            // --- Individueel projectbestand verwijderen ---
            if (isset($_POST['delete_project_file'])) {
                $fileId = (int)($_POST['file_id'] ?? 0);
                if ($this->deleteProjectFile($fileId, $id)) {
                    $response = ['success' => true, 'message' => 'Bestand verwijderd.'];
                } else {
                    $response = ['success' => false, 'message' => 'Kon het bestand niet verwijderen.'];
                }
                echo json_encode($response);
                exit;
            }

            // --- Hybride Opslagactie ---
            if (isset($_POST['save_section'])) {
                $section = $_POST['save_section'];

                // Bepaal welke velden geüpdatet moeten worden
                $fieldsToUpdate = [];
                // Verzamel altijd alle velden uit alle secties
                foreach ($this->sectionFields as $sectionName => $fields) {
                    $fieldsToUpdate = array_merge($fieldsToUpdate, $fields);
                }

                if (empty($fieldsToUpdate)) {
                    $response = ['success' => false, 'message' => 'Ongeldige sectie opgegeven.'];
                } else {
                    $data = [];
                    $setClauses = [];

                    foreach ($fieldsToUpdate as $field) {
                        $setClauses[] = "`$field` = :$field";
                        // Speciale behandeling voor velden die NULL kunnen zijn (zoals datums)
                        if (in_array($field, ['installatiedatum', 'opleverdatum', 'gewenste_offertedatum'])) {
                            $data[$field] = empty($_POST[$field]) ? null : $_POST[$field];
                        }
                        else {
                            $data[$field] = $_POST[$field] ?? null;
                        }
                    }

                    // Voeg altijd de metadata-updates toe
                    $setClauses[] = "pdf_up_to_date = 0";
                    $setClauses[] = "last_modified_at = NOW()";
                    $setClauses[] = "last_modified_by = :user";
                    $data['user'] = $_SESSION['fullname'] ?? 'Onbekend';
                    $data['id'] = $id;

                    $sql = "UPDATE bezoekverslag SET " . implode(', ', $setClauses) . " WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    
                    try {
                        $stmt->execute($data);
                        $response = ['success' => true, 'message' => 'Alle gegevens succesvol opgeslagen.'];
                    } catch (PDOException $e) {
                        // Vang databasefouten af voor betere feedback
                        error_log("Save error: " . $e->getMessage()); // Log de daadwerkelijke fout
                        $response = ['success' => false, 'message' => 'Databasefout bij opslaan: ' . $e->getMessage()];
                    }
                }
            }

            echo json_encode($response);
            exit;
        }

        // Toegangscontrole voor het laden van de pagina (GET request)
        if (!canEditVerslag($id)) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'U heeft geen rechten om dit verslag te bewerken.'];
            header("Location: ?page=dashboard");
            exit;
        }

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

        // Haal client access data op
        $stmtClient = $pdo->prepare("SELECT * FROM client_access WHERE bezoekverslag_id = ?");
        $stmtClient->execute([$id]);
        $clientAccess = $stmtClient->fetch(PDO::FETCH_ASSOC);

        // Haal projectbestanden op
        $stmtBestanden = $pdo->prepare("SELECT id, bestandsnaam FROM project_bestanden WHERE verslag_id = ? ORDER BY bestandsnaam ASC");
        $stmtBestanden->execute([$id]);
        $projectBestanden = $stmtBestanden->fetchAll(PDO::FETCH_ASSOC);

        // Haal collaborators en mogelijke collega's op (alleen voor eigenaar/admin)
        $collaborators = [];
        $colleagues = [];
        if ($isOwner) {
            $stmtCollabs = $pdo->prepare("SELECT u.id, u.fullname, u.email FROM verslag_collaborators vc JOIN users u ON vc.user_id = u.id WHERE vc.verslag_id = ?");
            $stmtCollabs->execute([$id]);
            $collaborators = $stmtCollabs->fetchAll(PDO::FETCH_ASSOC);
            $collaboratorIds = array_column($collaborators, 'id');
            $excludeIds = array_merge([$verslagOwner['created_by']], $collaboratorIds);
            $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));

            $stmtColleagues = $pdo->prepare("SELECT id, fullname FROM users WHERE id NOT IN ($placeholders) AND role != 'viewer'");
            $stmtColleagues->execute($excludeIds);
            $colleagues = $stmtColleagues->fetchAll(PDO::FETCH_ASSOC);
        }


        include __DIR__ . '/../views/verslag_detail.php';
    }

    /* ================= VERWIJDEREN ================= */
    public function delete($id) {
        requireRole(['admin', 'poweruser', 'accountmanager']);
        $pdo = Database::getConnection();

        // Een accountmanager mag alleen zijn eigen verslagen verwijderen
        if (!isAdmin()) {
            $stmt = $pdo->prepare("SELECT id FROM bezoekverslag WHERE id = ? AND created_by = ?");
            $stmt->execute([$id, $_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'U heeft geen rechten om dit verslag te verwijderen.'];
                header("Location: ?page=dashboard");
                exit;
            }
        }

        // Soft delete: zet de deleted_at timestamp
        $stmt = $pdo->prepare("UPDATE bezoekverslag SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);

        log_action('verslag_soft_deleted', "Bezoekverslag #{$id} is naar de prullenbak verplaatst.");
        $_SESSION['flash_message'] = ['type' => 'info', 'text' => 'Bezoekverslag is naar de prullenbak verplaatst.'];
        header("Location: ?page=dashboard");
        exit;
    }

    /* ================= PDF GENERATIE ================= */
    public function generatePdf($id) {
        requireLogin();

        // Controleer of de GD-extensie is ingeschakeld, nodig voor afbeeldingen
        if (!extension_loaded('gd')) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Fout: De GD PHP-extensie is niet ingeschakeld op de server. Deze is nodig voor het verwerken van afbeeldingen in de PDF.'];
            header("Location: ?page=bewerk&id=" . $id);
            exit;
        }

        $pdo = Database::getConnection();

        // Haal verslag data op
        $stmt = $pdo->prepare("
            SELECT b.*, u.fullname AS accountmanager_naam
            FROM bezoekverslag b
            LEFT JOIN users u ON b.created_by = u.id
            WHERE b.id = ?
        ");
        $stmt->execute([$id]);
        $verslag = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$verslag) die("Verslag niet gevonden.");

        // --- VALIDATIE VOOR PDF GENERATIE ---
        $errors = [];
        if (($verslag['installatie_adres_afwijkend'] ?? 'Nee') === 'Ja') {
            if (empty($verslag['installatie_adres_straat'])) $errors[] = 'Afwijkend installatieadres: "Adres" is verplicht.';
            if (empty($verslag['installatie_adres_postcode'])) $errors[] = 'Afwijkend installatieadres: "Postcode" is verplicht.';
            if (empty($verslag['installatie_adres_plaats'])) $errors[] = 'Afwijkend installatieadres: "Plaats" is verplicht.';
        }
        if (($verslag['cp_locatie_afwijkend'] ?? 'Nee') === 'Ja') {
            if (empty($verslag['cp_locatie_naam'])) $errors[] = 'Contactpersoon op locatie: "Naam" is verplicht.';
            if (empty($verslag['cp_locatie_email']) && empty($verslag['cp_locatie_tel'])) {
                $errors[] = 'Contactpersoon op locatie: "E-mailadres" of "Telefoonnummer" is verplicht.';
            }
        }

        if (!empty($errors)) {
            $errorHtml = '<ul><li>' . implode('</li><li>', $errors) . '</li></ul>';
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => '<strong>PDF niet gegenereerd.</strong> De volgende velden zijn verplicht:<br>' . $errorHtml];
            header("Location: ?page=bewerk&id=" . $id);
            exit;
        }

        $pdfDir = __DIR__ . '/../../storage/pdfs/';
        if (!is_dir($pdfDir)) mkdir($pdfDir, 0777, true);

        // Als PDF up-to-date is en bestaat, toon de bestaande
        if (!empty($verslag['pdf_up_to_date']) && !empty($verslag['pdf_path']) && file_exists($pdfDir . $verslag['pdf_path'])) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . basename($verslag['pdf_path']) . '"');
            readfile($pdfDir . $verslag['pdf_path']);
            exit;
        }

        // --- Verwijder de oude PDF als die bestaat ---
        if (!empty($verslag['pdf_path']) && file_exists($pdfDir . $verslag['pdf_path'])) {
            @unlink($pdfDir . $verslag['pdf_path']);
        }

        // --- Genereer een nieuwe PDF ---

        // Haal gerelateerde ruimtes op
        $stmt = $pdo->prepare("SELECT * FROM ruimte WHERE verslag_id = ?");
        $stmt->execute([$id]);
        $ruimtes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Haal foto's op voor elke ruimte
        $fotoStmt = $pdo->prepare("SELECT pad FROM foto WHERE ruimte_id = ?");
        foreach ($ruimtes as $key => $ruimte) {
            $fotoStmt->execute([$ruimte['id']]);
            $ruimtes[$key]['fotos'] = $fotoStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Haal projectbestanden op
        $stmtBestanden = $pdo->prepare("SELECT bestandsnaam FROM project_bestanden WHERE verslag_id = ? ORDER BY bestandsnaam ASC");
        $stmtBestanden->execute([$id]);
        $projectBestanden = $stmtBestanden->fetchAll(PDO::FETCH_ASSOC);

        // Start output buffering om de HTML op te vangen
        ob_start();
        include __DIR__ . '/../views/pdf_template.php';
        $html = ob_get_clean();

        // Configureer Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Sla de PDF op
        $newVersion = (int)($verslag['pdf_version'] ?? 0) + 1;
        // Maak veilige en nette bestandsnamen met spaties en koppeltekens
        $sanitize = function ($value) {
            $value = (string)($value ?? '');
            // Verwijder ongeldige tekens, behoud letters/cijfers/spaties/_/-
            $value = preg_replace('/[^\pL\pN _-]+/u', ' ', $value);
            // Vervang meerdere spaties door enkele spatie
            $value = preg_replace('/\s+/', ' ', $value);
            // Trim ongewenste tekens aan randen
            $value = trim($value, " .-_");
            return $value !== '' ? $value : 'naamloos';
        };
        $safeKlantnaam = $sanitize($verslag['klantnaam'] ?? '');
        $safeProjecttitel = $sanitize($verslag['projecttitel'] ?? '');
        $pdfFilename = sprintf('Bezoekverslag - %s - %s.pdf', $safeKlantnaam, $safeProjecttitel);
        file_put_contents($pdfDir . $pdfFilename, $dompdf->output());

        // Update de database
        $stmt = $pdo->prepare("
            UPDATE bezoekverslag 
            SET pdf_version = ?, pdf_path = ?, pdf_up_to_date = 1, pdf_generated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$newVersion, $pdfFilename, $id]);

        // Toon de zojuist gegenereerde PDF in de browser
        $dompdf->stream($pdfFilename, ["Attachment" => 0]);
    }

    /* ================= CLIENT PASSWORD RESET ================= */
    public function resetClientPassword($id) {
        requireRole(['admin', 'poweruser', 'accountmanager']);
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
        header("Location: ?page=bewerk&id=" . $id);
        exit;
    }

    public function downloadProjectFilesAsZip($verslag_id) {
        requireLogin();

        if (!class_exists('ZipArchive')) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Fout: De ZipArchive PHP-extensie is niet ingeschakeld op de server.'];
            header("Location: ?page=bewerk&id=" . $verslag_id);
            exit;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT bestandsnaam, pad FROM project_bestanden WHERE verslag_id = ?");
        $stmt->execute([$verslag_id]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($files)) {
            $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Er zijn geen projectbestanden om te downloaden.'];
            header("Location: ?page=bewerk&id=" . $verslag_id);
            exit;
        }

        $verslagStmt = $pdo->prepare("SELECT klantnaam FROM bezoekverslag WHERE id = ?");
        $verslagStmt->execute([$verslag_id]);
        $verslag = $verslagStmt->fetch(PDO::FETCH_ASSOC);
        $safeKlantnaam = preg_replace('/[^a-zA-Z0-9_-]/', '_', $verslag['klantnaam'] ?? 'project');
        $zipFilename = 'Projectbestanden_' . $safeKlantnaam . '.zip';

        $zip = new ZipArchive();
        $tempZipFile = tempnam(sys_get_temp_dir(), 'zip');

        if ($zip->open($tempZipFile, ZipArchive::CREATE) !== TRUE) {
            die("Kan geen tijdelijk ZIP-bestand aanmaken.");
        }

        foreach ($files as $file) {
            $fullPath = __DIR__ . '/../../public/' . $file['pad'];
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
        $uploadDir = __DIR__ . '/../../public/uploads/project_' . $verslag_id . '/';
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
                        $errors[] = "Bestand '{$originalName}' is slechts gedeeltelijk geüpload.";
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
            $fullPath = __DIR__ . '/../../public/' . $file['pad'];
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
            header("Location: ?page=dashboard");
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
            header("Location: ?page=dashboard");
            exit;
        }

        // Haal verslaggegevens op voor de bestandsnaam
        $verslagStmt = $pdo->prepare("SELECT klantnaam, projecttitel FROM bezoekverslag WHERE id = ?");
        $verslagStmt->execute([$verslag_id]);
        $verslag = $verslagStmt->fetch(PDO::FETCH_ASSOC);
        $safeKlantnaam = preg_replace('/[^a-zA-Z0-9_-]/', '_', $verslag['klantnaam'] ?? 'verslag');
        $zipFilename = 'Fotos_' . $safeKlantnaam . '.zip';

        $zip = new ZipArchive();
        $tempZipFile = tempnam(sys_get_temp_dir(), 'zip');

        if ($zip->open($tempZipFile, ZipArchive::CREATE) !== TRUE) {
            die("Kan geen tijdelijk ZIP-bestand aanmaken.");
        }

        $photoCounters = [];
        foreach ($photos as $photo) {
            $ruimteNaam = preg_replace('/[^a-zA-Z0-9_-]/', '_', $photo['ruimte_naam']);
            $fullPhotoPath = __DIR__ . '/../../public/' . $photo['foto_pad'];

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

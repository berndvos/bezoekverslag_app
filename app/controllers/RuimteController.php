<?php

namespace App\Controllers;

use App\Config\Database;
use PDO;
use App\Models\Ruimte;

class RuimteController {
    private const HEADER_JSON = 'Content-Type: application/json';
    private const PUBLIC_PATH = '/../../public/';
    private const REDIRECT_DASHBOARD = 'Location: ?page=dashboard';
    private const REDIRECT_EDIT_PREFIX = 'Location: ?page=bewerk&id=';

    /** Nieuwe ruimte toevoegen */
    public function create($verslag_id) {
        requireRole(['accountmanager', 'admin', 'poweruser']);
        $pdo = Database::getConnection();
        
        // Zorg dat de view altijd een complete (lege) array heeft om mee te werken
        $ruimte = [
            'id' => null,
            'verslag_id' => $verslag_id,
            'naam' => '',
            'etage' => '',
            'opmerkingen' => '',
            'aantal_aansluitingen' => '',
            'type_aansluitingen' => '',
            'huidig_scherm' => '',
            'audio_aanwezig' => '',
            'beeldkwaliteit' => '',
            'gewenst_scherm' => '',
            'gewenst_aansluitingen' => '',
            'presentatie_methode' => '',
            'geluid_gewenst' => '',
            'overige_wensen' => '',
            'kabeltraject_mogelijk' => '',
            'beperkingen' => '',
            'ophanging' => '',
            'montage_extra' => '',
            'stroom_voldoende' => '',
            'stroom_extra' => '',
            'schema_version' => 2, // Nieuwe ruimtes krijgen de laatste versie
            // V2 Velden
            'lengte_ruimte' => '',
            'breedte_ruimte' => '',
            'hoogte_plafond' => '',
            'type_plafond' => '',
            'ruimte_boven_plafond' => '',
            'huidige_situatie_v2' => '',
            'type_wand' => '',
            'netwerk_aanwezig' => '',
            'netwerk_extra' => '',
            'netwerk_afstand' => '',
            'stroom_aanwezig' => '',
            'stroom_extra_v2' => '',
            'stroom_afstand' => ''
        ];
        include __DIR__ . '/../views/ruimte_form.php';
    }

    /** Nieuwe ruimte opslaan */
    public function save() {
        requireRole(['accountmanager', 'admin', 'poweruser']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header(self::REDIRECT_DASHBOARD);
            exit;
        }

        require_valid_csrf_token($_POST['csrf_token'] ?? null);
        $verslag_id = (int)($_GET['verslag_id'] ?? 0);
        if (!$verslag_id) {
            die("Geen verslag ID opgegeven.");
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            "INSERT INTO ruimte (verslag_id, naam, etage, opmerkingen, aantal_aansluitingen, type_aansluitingen, huidig_scherm, audio_aanwezig, beeldkwaliteit, gewenst_scherm, gewenst_aansluitingen, presentatie_methode, geluid_gewenst, overige_wensen, kabeltraject_mogelijk, beperkingen, ophanging, montage_extra, stroom_voldoende, stroom_extra, created_at, schema_version, lengte_ruimte, breedte_ruimte, hoogte_plafond, type_plafond, ruimte_boven_plafond, huidige_situatie_v2, type_wand, netwerk_aanwezig, netwerk_extra, netwerk_afstand, stroom_aanwezig, stroom_extra_v2, stroom_afstand)"
            ."VALUES (:verslag_id, :naam, :etage, :opmerkingen, :aantal_aansluitingen, :type_aansluitingen, :huidig_scherm, :audio_aanwezig, :beeldkwaliteit, :gewenst_scherm, :gewenst_aansluitingen, :presentatie_methode, :geluid_gewenst, :overige_wensen, :kabeltraject_mogelijk, :beperkingen, :ophanging, :montage_extra, :stroom_voldoende, :stroom_extra, NOW(), :schema_version, :lengte_ruimte, :breedte_ruimte, :hoogte_plafond, :type_plafond, :ruimte_boven_plafond, :huidige_situatie_v2, :type_wand, :netwerk_aanwezig, :netwerk_extra, :netwerk_afstand, :stroom_aanwezig, :stroom_extra_v2, :stroom_afstand)"
        );
        $stmt->execute($this->getPostDataForRuimte(['verslag_id' => $verslag_id, 'schema_version' => 2]));

        $ruimteId = $pdo->lastInsertId();

        // Foto's uploaden en koppelen
        $this->handleFileUploads($pdo, $ruimteId);

        // PDF verouderd markeren
        $this->markPdfOutdated($verslag_id);

        // Bepaal de redirect op basis van de geklikte knop
        $action = $_POST['submit_action'] ?? 'save_and_back';
        $redirectUrl = '?page=bewerk&id=' . $verslag_id; // Default: terug naar verslag

        if ($action === 'save_and_new') {
            $redirectUrl = '?page=ruimte_new&verslag_id=' . $verslag_id;
        }

        header(self::HEADER_JSON);
        echo json_encode(['success' => true, 'message' => 'Ruimte succesvol aangemaakt.', 'redirect' => $redirectUrl]);
        exit;
    }

    /** Ruimte bewerken */
    public function edit($id) {
        requireRole(['accountmanager', 'admin', 'poweruser']);
        $pdo = Database::getConnection();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_valid_csrf_token($_POST['csrf_token'] ?? null);
            // Haal verslag_id op voor de redirect
            $stmt_verslag = $pdo->prepare("SELECT verslag_id FROM ruimte WHERE id = ?");
            $stmt_verslag->execute([$id]);
            $verslag_id = $stmt_verslag->fetchColumn();

            // De 'save' logica voor een bestaande ruimte
            $stmt = $pdo->prepare("UPDATE ruimte SET naam=:naam, etage=:etage, opmerkingen=:opmerkingen, aantal_aansluitingen=:aantal_aansluitingen, type_aansluitingen=:type_aansluitingen, huidig_scherm=:huidig_scherm, audio_aanwezig=:audio_aanwezig, beeldkwaliteit=:beeldkwaliteit, gewenst_scherm=:gewenst_scherm, gewenst_aansluitingen=:gewenst_aansluitingen, presentatie_methode=:presentatie_methode, geluid_gewenst=:geluid_gewenst, overige_wensen=:overige_wensen, kabeltraject_mogelijk=:kabeltraject_mogelijk, beperkingen=:beperkingen, ophanging=:ophanging, montage_extra=:montage_extra, stroom_voldoende=:stroom_voldoende, stroom_extra=:stroom_extra, lengte_ruimte=:lengte_ruimte, breedte_ruimte=:breedte_ruimte, hoogte_plafond=:hoogte_plafond, type_plafond=:type_plafond, ruimte_boven_plafond=:ruimte_boven_plafond, huidige_situatie_v2=:huidige_situatie_v2, type_wand=:type_wand, netwerk_aanwezig=:netwerk_aanwezig, netwerk_extra=:netwerk_extra, netwerk_afstand=:netwerk_afstand, stroom_aanwezig=:stroom_aanwezig, stroom_extra_v2=:stroom_extra_v2, stroom_afstand=:stroom_afstand, updated_at=NOW() WHERE id=:id");
            $stmt->execute($this->getPostDataForRuimte(['id' => $id]));

            // Foto's uploaden en koppelen
            $this->handleFileUploads($pdo, $id);
            $this->markPdfOutdated($verslag_id);

            // Bepaal de redirect op basis van de geklikte knop
            $action = $_POST['submit_action'] ?? 'save_and_back';
            $redirectUrl = '?page=bewerk&id=' . $verslag_id; // Default: terug naar verslag

            if ($action === 'save_and_new') {
                $redirectUrl = '?page=ruimte_new&verslag_id=' . $verslag_id;
            }

            header(self::HEADER_JSON);
            echo json_encode(['success' => true, 'message' => 'Wijzigingen opgeslagen.', 'redirect' => $redirectUrl]);
            exit;
        }

        $stmt = $pdo->prepare(
            "SELECT r.*, b.klantnaam\n            FROM ruimte r\n            JOIN bezoekverslag b ON b.id = r.verslag_id\n            WHERE r.id = ?"
        );
        $stmt->execute([$id]);
        $ruimte = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ruimte) {
            die("Ruimte niet gevonden.");
        }

        // Haal bestaande foto's op voor de view
        $fotoStmt = $pdo->prepare("SELECT id, pad FROM foto WHERE ruimte_id = ?");
        $fotoStmt->execute([$id]);
        $fotos = $fotoStmt->fetchAll(PDO::FETCH_ASSOC);


        include __DIR__ . '/../views/ruimte_form.php';
    }

    /** Ruimte verwijderen */
    public function delete($id) {
        requireRole(['accountmanager', 'admin', 'poweruser']);
        require_valid_csrf_token($_GET['csrf_token'] ?? null);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT verslag_id FROM ruimte WHERE id=?");
        $stmt->execute([$id]);
        $ruimte = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ruimte) {
            die("Ruimte niet gevonden.");
        }

        $verslag_id = $ruimte['verslag_id'];

        $pdo->prepare("DELETE FROM ruimte WHERE id=?")->execute([$id]);

        // PDF verouderd markeren
        $this->markPdfOutdated($verslag_id);

        header(self::REDIRECT_EDIT_PREFIX . (int)$verslag_id);
        exit;
    }

    /** Foto verwijderen */
    public function deleteFoto($foto_id) {
        requireRole(['accountmanager', 'admin', 'poweruser']);
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null;
        if (!verify_csrf_token($token)) {
            echo json_encode(['success' => false, 'message' => 'Ongeldig verzoek.']);
            exit;
        }
        header(self::HEADER_JSON);

        $pdo = Database::getConnection();

        // 1. Haal foto-informatie op (pad en ruimte_id)
        $stmt = $pdo->prepare("SELECT pad, ruimte_id FROM foto WHERE id = ?");
        $stmt->execute([$foto_id]);
        $foto = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$foto) {
            echo json_encode(['success' => false, 'message' => 'Foto niet gevonden.']);
            exit;
        }

        // 2. Verwijder het fysieke bestand
        $fullPath = __DIR__ . self::PUBLIC_PATH . $foto['pad'];
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        // 3. Verwijder de foto uit de database
        $stmt = $pdo->prepare("DELETE FROM foto WHERE id = ?");
        $deleteSuccess = $stmt->execute([$foto_id]);

        if ($deleteSuccess) {
            // 4. Markeer PDF als verouderd
            $ruimteStmt = $pdo->prepare("SELECT verslag_id FROM ruimte WHERE id = ?");
            $ruimteStmt->execute([$foto['ruimte_id']]);
            $verslag_id = $ruimteStmt->fetchColumn();
            if ($verslag_id) {
                $this->markPdfOutdated($verslag_id);
            }
            echo json_encode(['success' => true, 'message' => 'Foto succesvol verwijderd.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Kon foto niet uit de database verwijderen.']);
        }
        exit;
    }

    /** Hulpfunctie: markeer PDF van verslag als verouderd */
    private function markPdfOutdated($verslag_id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "UPDATE bezoekverslag "
            ." SET pdf_up_to_date = 0,"
            ." last_modified_at = NOW(),"
            ." last_modified_by = :user"
            ." WHERE id = :id"
        );
        $stmt->execute([
            ':user' => $_SESSION['fullname'] ?? 'Onbekend',
            ':id' => $verslag_id
        ]);
    }

    /** Hulpfunctie: haal POST data op voor een ruimte */
    private function getPostDataForRuimte($initialData = []) {
        return array_merge($initialData, [
            'naam' => $_POST['naam'] ?? '',
            'etage' => $_POST['etage'] ?? '',
            'opmerkingen' => $_POST['opmerkingen'] ?? '',
            'aantal_aansluitingen' => $_POST['aantal_aansluitingen'] ?? null,
            'type_aansluitingen' => $_POST['type_aansluitingen'] ?? '',
            'huidig_scherm' => $_POST['huidig_scherm'] ?? '',
            'audio_aanwezig' => $_POST['audio_aanwezig'] ?? null,
            'beeldkwaliteit' => $_POST['beeldkwaliteit'] ?? '',
            'gewenst_scherm' => $_POST['gewenst_scherm'] ?? '',
            'gewenst_aansluitingen' => $_POST['gewenst_aansluitingen'] ?? '',
            'presentatie_methode' => $_POST['presentatie_methode'] ?? null,
            'geluid_gewenst' => $_POST['geluid_gewenst'] ?? null,
            'overige_wensen' => $_POST['overige_wensen'] ?? '',
            'kabeltraject_mogelijk' => $_POST['kabeltraject_mogelijk'] ?? null,
            'beperkingen' => $_POST['beperkingen'] ?? '',
            'ophanging' => $_POST['ophanging'] ?? null,
            'montage_extra' => $_POST['montage_extra'] ?? '',
            'stroom_voldoende' => $_POST['stroom_voldoende'] ?? null,
            'stroom_extra' => $_POST['stroom_extra'] ?? '',
            // V2 Velden
            'lengte_ruimte' => $_POST['lengte_ruimte'] ?? '',
            'breedte_ruimte' => $_POST['breedte_ruimte'] ?? '',
            'hoogte_plafond' => $_POST['hoogte_plafond'] ?? '',
            'type_plafond' => $_POST['type_plafond'] ?? '',
            'ruimte_boven_plafond' => $_POST['ruimte_boven_plafond'] ?? '',
            'huidige_situatie_v2' => $_POST['huidige_situatie_v2'] ?? '',
            'type_wand' => $_POST['type_wand'] ?? '',
            'netwerk_aanwezig' => $_POST['netwerk_aanwezig'] ?? null,
            'netwerk_extra' => $_POST['netwerk_extra'] ?? '',
            'netwerk_afstand' => $_POST['netwerk_afstand'] ?? '',
            'stroom_aanwezig' => $_POST['stroom_aanwezig'] ?? null,
            'stroom_extra_v2' => $_POST['stroom_extra_v2'] ?? '',
            'stroom_afstand' => $_POST['stroom_afstand'] ?? ''
        ]);
    }

    /** Hulpfunctie: verwerk foto uploads */
    private function handleFileUploads($pdo, $ruimte_id) {
        if (empty($_FILES['foto']['name'][0])) {
            return;
        }

        $uploadDir = __DIR__ . '/../../public/uploads/ruimte_' . $ruimte_id . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        foreach ($_FILES['foto']['name'] as $i => $name) {
            if ($_FILES['foto']['error'][$i] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['foto']['tmp_name'][$i];

                $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
                $filePath = $uploadDir . $safeName;

                 if (move_uploaded_file($tmpName, $filePath)) {
                     // Sla relatief pad op in de database
                     $dbPath = 'uploads/ruimte_' . $ruimte_id . '/' . $safeName;
                     $pdo->prepare("INSERT INTO foto (ruimte_id, pad, created_at) VALUES (?, ?, NOW())")->execute([$ruimte_id, $dbPath]);
                 }
            }
        }
    }
}





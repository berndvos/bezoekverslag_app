<?php

namespace App\Services\Bezoekverslag;

use App\Config\Database;
use App\Services\ViewRenderer;
use Dompdf\Dompdf;
use Dompdf\Options;
use PDO;

class PdfService
{
    private ViewRenderer $view;

    public function __construct(ViewRenderer $view)
    {
        $this->view = $view;
    }

    public function generate(int $id): void
    {
        set_time_limit(300); // 5 minutes
        requireLogin();

        if (!extension_loaded('gd')) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Fout: De GD PHP-extensie is niet ingeschakeld op de server. Deze is nodig voor het verwerken van afbeeldingen in de PDF.'];
            header('Location: ?page=bewerk&id=' . $id);
            exit;
        }

        $pdo = Database::getConnection();
        $verslag = $this->fetchVerslagForPdf($pdo, $id);

        $errors = $this->validatePdfData($verslag);
        if (!empty($errors)) {
            $errorHtml = '<ul><li>' . implode('</li><li>', $errors) . '</li></ul>';
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => '<strong>PDF niet gegenereerd.</strong> De volgende velden zijn verplicht:<br>' . $errorHtml];
            header('Location: ?page=bewerk&id=' . $id);
            exit;
        }

        $pdfDir = $this->ensurePdfDirectory();

        if (!empty($verslag['pdf_up_to_date']) && !empty($verslag['pdf_path'])) {
            $fullPath = $pdfDir . $verslag['pdf_path'];
            if (file_exists($fullPath)) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="' . basename($verslag['pdf_path']) . '"');
                readfile($fullPath);
                exit;
            }
        }

        if (!empty($verslag['pdf_path'])) {
            $fullPath = $pdfDir . $verslag['pdf_path'];
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }

        [$ruimtes, $projectBestanden] = $this->loadPdfRelatedData($pdo, $id);
        $html = $this->view->renderToString('pdf_template', compact('verslag', 'ruimtes', 'projectBestanden'));
        $dompdf = $this->createPdfGenerator($html);

        $pdfFilename = $this->storePdfOutput($dompdf, $verslag, $pdfDir);
        $this->updatePdfMetadata($pdo, $id, $verslag, $pdfFilename);

        $dompdf->stream($pdfFilename, ['Attachment' => 0]);
    }

    private function fetchVerslagForPdf(PDO $pdo, int $id): array
    {
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

    private function validatePdfData(array $verslag): array
    {
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

    private function ensurePdfDirectory(): string
    {
        // app/Services/Bezoekverslag -> up 3 => project root
        $base = dirname(__DIR__, 3);
        $pdfDir = $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'pdfs' . DIRECTORY_SEPARATOR;
        if (!is_dir($pdfDir)) {
            mkdir($pdfDir, 0777, true);
        }

        return $pdfDir;
    }

    private function loadPdfRelatedData(PDO $pdo, int $id): array
    {
        // Fetch all rooms
        $stmtRuimtes = $pdo->prepare('SELECT * FROM ruimte WHERE verslag_id = ? ORDER BY id');
        $stmtRuimtes->execute([$id]);
        $ruimtes = $stmtRuimtes->fetchAll(PDO::FETCH_ASSOC);

        $ruimteIds = array_map(fn($r) => $r['id'], $ruimtes);
        $fotosByRuimte = [];

        if (!empty($ruimteIds)) {
            // Fetch all photos for all rooms in one go
            $in = str_repeat('?,', count($ruimteIds) - 1) . '?';
            $stmtFotos = $pdo->prepare("SELECT pad, ruimte_id FROM foto WHERE ruimte_id IN ($in)");
            $stmtFotos->execute($ruimteIds);
            $allFotos = $stmtFotos->fetchAll(PDO::FETCH_ASSOC);

            // Group photos by room
            foreach ($allFotos as $foto) {
                $fotosByRuimte[$foto['ruimte_id']][] = $foto;
            }
        }

        // Attach photos to their rooms
        foreach ($ruimtes as $i => $ruimte) {
            $ruimtes[$i]['fotos'] = $fotosByRuimte[$ruimte['id']] ?? [];
        }

        // Fetch project files
        $stmtBestanden = $pdo->prepare('SELECT bestandsnaam FROM project_bestanden WHERE verslag_id = ? ORDER BY bestandsnaam ASC');
        $stmtBestanden->execute([$id]);
        $projectBestanden = $stmtBestanden->fetchAll(PDO::FETCH_ASSOC);

        return [$ruimtes, $projectBestanden];
    }

    private function createPdfGenerator(string $html): Dompdf
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf;
    }

    private function storePdfOutput(Dompdf $dompdf, array $verslag, string $pdfDir): string
    {
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

    private function updatePdfMetadata(PDO $pdo, int $id, array $verslag, string $pdfFilename): void
    {
        $newVersion = (int)($verslag['pdf_version'] ?? 0) + 1;
        $stmt = $pdo->prepare('
            UPDATE bezoekverslag
            SET pdf_version = ?, pdf_path = ?, pdf_up_to_date = 1, pdf_generated_at = NOW()
            WHERE id = ?
        ');
        $stmt->execute([$newVersion, $pdfFilename, $id]);
    }
}


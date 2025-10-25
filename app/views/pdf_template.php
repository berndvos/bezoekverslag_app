<?php
/**
 * Helper functie om een afbeelding te comprimeren en als base64 string terug te geven.
 * Verkleint de afbeelding en past JPEG-compressie toe.
 *
 * @param string $imagePath Het pad naar de originele afbeelding.
 * @param int $quality De JPEG-kwaliteit (0-100).
 * @param int $maxWidth De maximale breedte van de afbeelding in de PDF.
 * @return string|null De base64-gecodeerde afbeelding of null bij een fout.
 */
function getCompressedImageBase64($imagePath, $quality = 75, $maxWidth = 800) {
    if (!file_exists($imagePath) || !extension_loaded('gd')) {
        return null;
    }

    try {
        $imageInfo = getimagesize($imagePath);
        if ($imageInfo === false) {
            return null;
        }

        $mime = $imageInfo['mime'];
        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];

        switch ($mime) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($imagePath);
                break;
            case 'image/png':
                $image = imagecreatefrompng($imagePath);
                // Behoud transparantie voor PNG
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
                break;
            default:
                // Voor niet-ondersteunde types (zoals SVG), laad de originele data.
                $data = file_get_contents($imagePath);
                return 'data:' . $mime . ';base64,' . base64_encode($data);
        }

        // Als de afbeelding al kleiner is dan de max breedte, gebruik de originele breedte
        $newWidth = min($originalWidth, $maxWidth);
        $newHeight = ($newWidth / $originalWidth) * $originalHeight;

        $resizedImage = imagescale($image, $newWidth, $newHeight, IMG_BICUBIC);

        ob_start();
        imagejpeg($resizedImage, null, $quality);
        $imageData = ob_get_clean();

        imagedestroy($image);
        imagedestroy($resizedImage);

        return 'data:image/jpeg;base64,' . base64_encode($imageData);
    } catch (Exception $e) {
        // Log de fout of geef null terug
        return null;
    }
}

function hval($arr, $key) {
    $val = trim($arr[$key] ?? '');
    return $val !== '' ? nl2br(htmlspecialchars($val)) : '-';
}
$pdfPublicPath = __DIR__ . '/../../public/';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Bezoekverslag: <?= htmlspecialchars($verslag['klantnaam'] ?? '') ?> - <?= htmlspecialchars($verslag['projecttitel'] ?? '') ?></title>
    <?php
      $brandingConfig = \App\Config\Branding::get();
      $primaryColor = $brandingConfig['primary_color'] ?? '#FFD200';
    ?>
    <style>
        @page { margin: 100px 50px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #333; }
        header { position: fixed; top: -70px; left: 0px; right: 0px; height: 50px; }
        footer { position: fixed; bottom: -60px; left: 0px; right: 0px; height: 50px; text-align: center; font-size: 8pt; color: #888; }
        footer .page-number:before { content: "Pagina " counter(page); }
        h1, h2, h3 { color: #333; }
        h1 { font-size: 18pt; border-bottom: 2px solid <?= $primaryColor ?>; padding-bottom: 5px; color: <?= $primaryColor ?>; }
        h2 { font-size: 14pt; border-bottom: 1px solid #ccc; padding-bottom: 3px; margin-top: 25px; }
        h3 { font-size: 11pt; margin-top: 20px; color: <?= $primaryColor ?>; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        td { padding: 4px; vertical-align: top; }
        .page-break { page-break-after: always; }
        .section-table td { border: 1px solid #ddd; }
        .section-table th { background-color: #f2f2f2; padding: 5px; text-align: left; font-size: 8pt; }
        .info-table { margin-bottom: 20px; }
        .info-table td { padding: 2px 0; }
        .info-table .label { font-weight: bold; width: 150px; }
        .text-block { margin-top: 10px; }
        .text-block b { display: block; margin-bottom: 3px; }
        .cover-page { text-align: center; margin-top: 150px; }
        .cover-page h1 { font-size: 24pt; border: none; color: #333; }
        .cover-page h2 { font-size: 16pt; border: none; color: #555; margin-top: 10px; }
        .cover-details { margin-top: 80px; text-align: left; display: inline-block; }
        .cover-details table { border-collapse: collapse; }
        .cover-details td { padding: 8px; font-size: 11pt; }
        .cover-details .label { font-weight: bold; padding-right: 20px; }
        .cover-page .version-info { margin-top: 100px; font-size: 10pt; color: #777; }
        .photo-gallery { margin-top: 15px; }
        .ruimte-foto { max-width: 400px; height: auto; margin-bottom: 10px; display: block; }
    </style>
</head>
<body>
    <header>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <!-- Logo links -->
                <td style="width: 50%; vertical-align: middle; padding: 0; border: 0;">
                    <?php $logoPath = $brandingConfig['logo_path'] ?? ''; ?>
                    <?php if (!empty($logoPath) && file_exists($pdfPublicPath . $logoPath)): ?>
                        <?php
                            $type = pathinfo($logoPath, PATHINFO_EXTENSION);
                            $data = file_get_contents($pdfPublicPath . $logoPath);
                            $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
                            echo '<img src="' . $base64 . '" style="max-height: 40px; width: auto;">';
                        ?>
                    <?php endif; ?>
                </td>
                <!-- Tekst rechts -->
                <td style="width: 50%; text-align: right; vertical-align: middle; padding: 0; border: 0;">
                    <h1 style="margin: 0; padding: 0; border-bottom: none; font-size: 18pt;">Bezoekverslag</h1>
                    <div style="font-size: 9pt; color: #555; margin-top: 4px;"><?= htmlspecialchars($verslag['klantnaam'] ?? '') ?> - <?= htmlspecialchars($verslag['projecttitel'] ?? '') ?></div>
                </td>
            </tr>
        </table>
    </header>

    <footer>
        <div class="page-number"></div>
    </footer>

    <main>
        <!-- VOORBLAD -->
        <div class="cover-page">
            <h1>Bezoekverslag</h1>
            <h2><?= hval($verslag, 'projecttitel') ?></h2>

            <div class="cover-details">
                <table>
                    <tr><th scope="row" class="label">Klant:</th><td><?= hval($verslag, 'klantnaam') ?></td></tr>
                    <tr><th scope="row" class="label">Project:</th><td><?= hval($verslag, 'projecttitel') ?></td></tr>
                    <tr><th scope="row" class="label">Accountmanager:</th><td><?= hval($verslag, 'accountmanager_naam') ?></td></tr>
                </table>
            </div>

            <div class="version-info">
                Datum: <?= date('d-m-Y') ?><br>
                Versie: V<?= (int)($verslag['pdf_version'] ?? 0) + 1 ?>
            </div>
        </div>

        <div class="page-break"></div>

        <!-- INHOUD VANAF PAGINA 2 -->
        <table class="info-table" style="margin-bottom: 30px;">
            <tr><th scope="row" class="label">Klant:</th><td><?= hval($verslag, 'klantnaam') ?></td></tr>
            <tr><th scope="row" class="label">Project:</th><td><?= hval($verslag, 'projecttitel') ?></td></tr>
            <tr><th scope="row" class="label">Datum generatie:</th><td><?= date('d-m-Y') ?></td></tr>
            <tr><th scope="row" class="label">Versie:</th><td>V<?= (int)($verslag['pdf_version'] ?? 0) + 1 ?></td></tr>
        </table>

        <h2>Relatiegegevens</h2>
        <table class="info-table">
            <tr><th scope="row" class="label">Adres:</th><td><?= htmlspecialchars(trim(hval($verslag, 'straatnaam') . ' ' . hval($verslag, 'huisnummer') . ' ' . hval($verslag, 'huisnummer_toevoeging'))) ?></td></tr>
            <tr><th scope="row" class="label">Postcode/Plaats:</th><td><?= hval($verslag, 'postcode') ?> <?= hval($verslag, 'plaats') ?></td></tr>
            <tr><th scope="row" class="label">KvK / BTW:</th><td><?= hval($verslag, 'kvk') ?> / <?= hval($verslag, 'btw') ?></td></tr>
        </table>

        <h2>Contactpersoon</h2>
        <table class="info-table">
            <tr><th scope="row" class="label">Naam:</th><td><?= hval($verslag, 'contact_naam') ?></td></tr>
            <tr><th scope="row" class="label">Functie:</th><td><?= hval($verslag, 'contact_functie') ?></td></tr>
            <tr><th scope="row" class="label">E-mail / Telefoon:</th><td><?= hval($verslag, 'contact_email') ?> / <?= hval($verslag, 'contact_tel') ?></td></tr>
        </table>

        <h2>Wensen</h2>
        <div class="text-block"><b>Gewenste offertedatum:</b><?= hval($verslag, 'gewenste_offertedatum') ?></div>
        <div class="text-block"><b>Indicatief budget:</b><?= hval($verslag, 'indicatief_budget') ?></div>
        <div class="text-block"><b>Situatieomschrijving:</b><?= hval($verslag, 'situatie') ?></div>
        <div class="text-block"><b>Gewenste functionaliteiten:</b><?= hval($verslag, 'functioneel') ?></div>
        <div class="text-block"><b>Toekomstige uitbreidingen:</b><?= hval($verslag, 'uitbreiding') ?></div>
        <div class="text-block"><b>Overige wensen:</b><?= hval($verslag, 'wensen') ?></div>

        <h2>Eisen</h2>
        <table class="info-table">
            <tr><th scope="row" class="label">Beeldkwaliteit:</th><td><?= hval($verslag, 'beeldkwaliteitseisen') ?></td></tr>
            <tr><th scope="row" class="label">Geluid:</th><td><?= hval($verslag, 'geluidseisen') ?></td></tr>
            <tr><th scope="row" class="label">Bediening:</th><td><?= hval($verslag, 'bedieningseisen') ?></td></tr>
            <tr><th scope="row" class="label">Beveiliging:</th><td><?= hval($verslag, 'beveiligingseisen') ?></td></tr>
            <tr><th scope="row" class="label">Netwerk:</th><td><?= hval($verslag, 'netwerkeisen') ?></td></tr>
            <tr><th scope="row" class="label">Garantie/onderhoud:</th><td><?= hval($verslag, 'garantie') ?></td></tr>
        </table>

        <h2>Installatie</h2>
        <?php if (($verslag['installatie_adres_afwijkend'] ?? 'Nee') === 'Ja'): ?>
            <h3>Afwijkend Installatieadres</h3>
            <table class="info-table">
                <tr><th scope="row" class="label">Adres:</th><td><?= htmlspecialchars(trim(hval($verslag, 'installatie_adres_straat') . ' ' . hval($verslag, 'installatie_adres_huisnummer') . ' ' . hval($verslag, 'installatie_adres_huisnummer_toevoeging'))) ?></td></tr>
                <tr><th scope="row" class="label">Postcode/Plaats:</th><td><?= hval($verslag, 'installatie_adres_postcode') ?> <?= hval($verslag, 'installatie_adres_plaats') ?></td></tr>
            </table>
        <?php endif; ?>
        <?php if (($verslag['cp_locatie_afwijkend'] ?? 'Nee') === 'Ja'): ?>
            <h3>Contactpersoon op Locatie</h3>
            <table class="info-table">
                <tr><th scope="row" class="label">Naam:</th><td><?= hval($verslag, 'cp_locatie_naam') ?></td></tr>
                <tr><th scope="row" class="label">E-mail / Telefoon:</th><td><?= hval($verslag, 'cp_locatie_email') ?> / <?= hval($verslag, 'cp_locatie_tel') ?></td></tr>
            </table>
        <?php endif; ?>
        <table class="info-table">
            <tr><th scope="row" class="label">Afvoer oude apparatuur:</th><td><?= hval($verslag, 'afvoer') ?></td></tr>
            <tr><th scope="row" class="label">Gewenste installatiedatum:</th><td><?= hval($verslag, 'installatiedatum') ?></td></tr>
            <tr><th scope="row" class="label">Locatie apparatuur:</th><td><?= hval($verslag, 'locatie_apparatuur') ?></td></tr>
            <tr><th scope="row" class="label">Aantal installaties:</th><td><?= hval($verslag, 'aantal_installaties') ?></td></tr>
            <tr><th scope="row" class="label">Parkeerrestricties:</th><td><?= hval($verslag, 'parkeren') ?></td></tr>
            <tr><th scope="row" class="label">Toegangsprocedures:</th><td><?= hval($verslag, 'toegang') ?></td></tr>
            <tr><th scope="row" class="label">Boortijden / geluidsrestricties:</th><td><?= hval($verslag, 'boortijden') ?></td></tr>
            <tr><th scope="row" class="label">Gewenste opleverdatum:</th><td><?= hval($verslag, 'opleverdatum') ?></td></tr>
        </table>

        <?php if (!empty($ruimtes)): ?>
            <?php foreach ($ruimtes as $ruimte): ?>
                <div class="page-break"></div>
                <h2>Ruimte</h2>
                <h3><?= htmlspecialchars($ruimte['naam'] ?? 'Onbekende ruimte') ?></h3>
                <table class="section-table">
                    <tr>
                        <th style="width: 25%;">Ruimtegegevens</th>
                        <td style="width: 75%;">
                            <b>Etage:</b> <?= hval($ruimte, 'etage') ?><br>
                            <b>Opmerkingen:</b> <?= hval($ruimte, 'opmerkingen') ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Huidige situatie</th>
                        <td>
                            <b>Aantal/type aansluitingen:</b> <?= hval($ruimte, 'aantal_aansluitingen') ?> / <?= hval($ruimte, 'type_aansluitingen') ?><br>
                            <b>Huidig scherm:</b> <?= hval($ruimte, 'huidig_scherm') ?><br>
                            <b>Audio aanwezig:</b> <?= hval($ruimte, 'audio_aanwezig') ?><br>
                            <b>Opmerkingen beeldkwaliteit:</b> <?= hval($ruimte, 'beeldkwaliteit') ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Wensen nieuwe situatie</th>
                        <td>
                            <b>Gewenst scherm:</b> <?= hval($ruimte, 'gewenst_scherm') ?><br>
                            <b>Gewenste aansluitingen:</b> <?= hval($ruimte, 'gewenst_aansluitingen') ?><br>
                            <b>Presentatiemethode:</b> <?= hval($ruimte, 'presentatie_methode') ?><br>
                            <b>Geluid gewenst:</b> <?= hval($ruimte, 'geluid_gewenst') ?><br>
                            <b>Overige wensen:</b> <?= hval($ruimte, 'overige_wensen') ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Bekabeling & Montage</th>
                        <td>
                            <b>Kabeltraject mogelijk:</b> <?= hval($ruimte, 'kabeltraject_mogelijk') ?><br>
                            <b>Beperkingen/obstakels:</b> <?= hval($ruimte, 'beperkingen') ?><br>
                            <b>Ophanging scherm:</b> <?= hval($ruimte, 'ophanging') ?><br>
                            <b>Extra montagewensen:</b> <?= hval($ruimte, 'montage_extra') ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Stroomvoorziening</th>
                        <td>
                            <b>Voldoende stroompunten:</b> <?= hval($ruimte, 'stroom_voldoende') ?><br>
                            <b>Extra nodig:</b> <?= hval($ruimte, 'stroom_extra') ?>
                        </td>
                    </tr>
                </table>

                <?php if (!empty($ruimte['fotos'])): ?>
                    <div class="photo-gallery">
                        <h3>Foto's</h3>
                        <?php foreach ($ruimte['fotos'] as $foto): ?>
                            <?php $imagePath = $pdfPublicPath . $foto['pad']; ?>
                            <?php
                            // Gebruik de nieuwe compressiefunctie
                            $base64 = getCompressedImageBase64($imagePath, 75, 800); // Kwaliteit 75, max 800px breed
                            if ($base64) {
                                echo '<img src="' . $base64 . '" class="ruimte-foto" style="max-width: 100%; height: auto;">';
                            }
                            ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</body>
</html>




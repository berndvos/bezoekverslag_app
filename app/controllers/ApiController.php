<?php

require_once __DIR__ . '/../helpers/auth_helpers.php';

class ApiController {

    /**
     * Vraagt adresgegevens op basis van postcode en huisnummer.
     * Fungeert als een proxy om de API-sleutel geheim te houden.
     */
    public function postcodeLookup() {
        error_log('[INFO] ApiController::postcodeLookup gestart.');

        requireLogin();
        header('Content-Type: application/json');

        if (!extension_loaded('curl')) {
            echo json_encode(['error' => 'cURL extensie is niet geladen op de server.']);
            exit;
        }

        $postcode = preg_replace('/[^0-9a-zA-Z]/', '', $_GET['postcode'] ?? '');
        $huisnummer = (int)($_GET['huisnummer'] ?? 0);
        $toevoeging = urlencode($_GET['toevoeging'] ?? '');
        
        // Probeer meerdere publieke bronnen (zonder API key)
        // 1) OpenPostcode.nl varianten
        $urls = [];
        $urls[] = "https://openpostcode.nl/api/v1/postcode/{$postcode}/{$huisnummer}" . (!empty($toevoeging) ? "/{$toevoeging}" : '');
        $urls[] = "https://openpostcode.nl/api/lookup/{$postcode}/{$huisnummer}" . (!empty($toevoeging) ? "/{$toevoeging}" : '');
        $urls[] = "https://openpostcode.nl/api/v1/lookup?postcode={$postcode}&number={$huisnummer}" . (!empty($toevoeging) ? "&addition={$toevoeging}" : '');
        // 2) PDOK Locatieserver (officiële BAG, vrije toegang)
        // Oude endpoint
        $urls[] = "https://geodata.nationaalgeoregister.nl/locatieserver/v3/free?fq=type:adres&fq=postcode:{$postcode}&fq=huisnummer:{$huisnummer}";
        // Nieuwe endpoint (v3.1)
        $q = rawurlencode("type:adres AND postcode:{$postcode} AND huisnummer:{$huisnummer}");
        $urls[] = "https://api.pdok.nl/bzk/locatieserver/search/v3_1/free?q={$q}";

        $lastError = null;
        $response = null;
        $httpCode = null;
        foreach ($urls as $url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'User-Agent: Bezoekverslag-App-OpenPostcode'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            error_log(sprintf('[DEBUG] OpenPostcode call -> %s | HTTP: %s | cURLError: %s | CT: %s | Body: %s',
                $url, (string)$httpCode, $curlError ?: 'none', $contentType ?: 'unknown', $response ?: 'empty'));

            if ($response !== false && $httpCode === 200) {
                // Succesvol antwoord, stop met proberen
                break;
            }
            $lastError = $curlError ?: (is_string($response) ? $response : 'Onbekende fout');
            $response = null; // reset zodat we weten dat deze poging faalde
        }

        if ($response === null) {
            echo json_encode(['error' => 'Kon geen geldig antwoord krijgen van OpenPostcode. ' . ($lastError ? ('Details: ' . substr($lastError, 0, 200)) : '')]);
            exit;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || $decoded === null) {
            echo json_encode(['error' => 'Ongeldig JSON-antwoord van OpenPostcode.']);
            exit;
        }

        // Probeer verschillende vormen van de payload te begrijpen en normaliseer naar street/city/postcode
        $candidates = [];
        if (isset($decoded[0]) && is_array($decoded[0])) { $candidates[] = $decoded[0]; }
        if (isset($decoded['result'])) { $candidates[] = $decoded['result']; }
        if (isset($decoded['results']) && is_array($decoded['results'])) {
            $candidates[] = $decoded['results'][0] ?? [];
        }
        if (isset($decoded['data'])) { $candidates[] = $decoded['data']; }
        // PDOK Locatieserver structuur
        if (isset($decoded['response']['docs']) && is_array($decoded['response']['docs'])) {
            $candidates[] = $decoded['response']['docs'][0] ?? [];
        }
        $candidates[] = $decoded; // ook top-level proberen

        $street = $city = $pc = null;
        $streetKeys = ['street','straat','streetname','straatnaam','thoroughfare','openbareruimte','public_space'];
        $cityKeys   = ['city','plaats','woonplaats','woonplaatsnaam','locality','municipality','gemeente','citylabel'];
        $pcKeys     = ['postcode','postalcode','zip','zip_code','post_code'];

        foreach ($candidates as $cand) {
            if (!is_array($cand)) {
                continue;
            }
            foreach ($streetKeys as $k) {
                if (isset($cand[$k]) && is_string($cand[$k]) && $cand[$k] !== '') {
                    $street = $cand[$k];
                    break;
                }
            }
            foreach ($cityKeys as $k) {
                if (isset($cand[$k]) && is_string($cand[$k]) && $cand[$k] !== '') {
                    $city = $cand[$k];
                    break;
                }
            }
            foreach ($pcKeys as $k) {
                if (isset($cand[$k]) && is_string($cand[$k]) && $cand[$k] !== '') {
                    $pc = strtoupper(str_replace(' ', '', $cand[$k]));
                    break;
                }
            }
            if ($street && $city) {
                break;
            }
        }

        if (!$street || !$city) {
            echo json_encode(['error' => 'Adres niet gevonden bij OpenPostcode.']);
            exit;
        }

        echo json_encode([
            'street' => $street,
            'city' => $city,
            'postcode' => $pc ?: $postcode,
        ]);
        exit;
    }
}

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

        $postcode = preg_replace('/[^0-9a-zA-Z]/', '', $_GET['postcode'] ?? '');
        $huisnummer = (int)($_GET['huisnummer'] ?? 0);
        $toevoeging = urlencode($_GET['toevoeging'] ?? '');
        
        $apiKey = trim($_ENV['POSTCODE_API_KEY'] ?? '', ' \t\n\r\0\x0B"');

        if (empty($apiKey) || $apiKey === 'UW_API_SLEUTEL') {
            error_log('[ERROR] Postcode API sleutel is niet geconfigureerd.');
            echo json_encode(['error' => 'Postcode API sleutel is niet geconfigureerd in het .env bestand.']);
            exit;
        }

        $url = "https://api-postcode.nl/v1/postcode/{$postcode}/{$huisnummer}";
        if (!empty($toevoeging)) {
            $url .= "/{$toevoeging}";
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        error_log(sprintf(
            '[DEBUG] Postcode API cURL Resultaat: HTTP Code: %d, cURL Error: %s, Reactie: %s',
            $httpCode,
            $curlError,
            $response
        ));

        if ($httpCode !== 200) {
            $responseData = json_decode($response, true);
            $errorMessage = $responseData['error'] ?? 'Fout bij het ophalen van het adres.';
            echo json_encode(['error' => $errorMessage]);
            exit;
        }

        echo $response;
        exit;
    }
}
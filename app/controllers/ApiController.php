<?php

namespace App\Controllers;



class ApiController {
    private const HEADER_JSON = 'Content-Type: application/json';
    private const USER_AGENT = 'Bezoekverslag-App-OpenPostcode';
    private const CURL_TIMEOUT = 10;
    private const CURL_CONNECT_TIMEOUT = 5;

    /**
     * Vraagt adresgegevens op basis van postcode en huisnummer.
     * Fungeert als een proxy om de API-sleutel geheim te houden.
     */
    public function postcodeLookup() {
        error_log('[INFO] ApiController::postcodeLookup gestart.');

        requireLogin();
        header(self::HEADER_JSON);

        if (!$this->isCurlAvailable()) {
            $this->respondWithError('cURL extensie is niet geladen op de server.');
        }

        $postcode = preg_replace('/[^0-9a-zA-Z]/', '', $_GET['postcode'] ?? '');
        $huisnummer = (int)($_GET['huisnummer'] ?? 0);
        $toevoeging = urlencode($_GET['toevoeging'] ?? '');

        [$response, $lastError] = $this->fetchFirstSuccessfulResponse(
            $this->buildLookupUrls($postcode, $huisnummer, $toevoeging)
        );

        if ($response === null) {
            $details = $lastError ? ' Details: ' . substr($lastError, 0, 200) : '';
            $this->respondWithError('Kon geen geldig antwoord krijgen van OpenPostcode.' . $details);
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || $decoded === null) {
            $this->respondWithError('Ongeldig JSON-antwoord van OpenPostcode.');
        }

        $address = $this->extractAddress($decoded, $postcode);
        if ($address === null) {
            $this->respondWithError('Adres niet gevonden bij OpenPostcode.');
        }

        echo json_encode($address);
        exit;
    }

    private function isCurlAvailable(): bool {
        return extension_loaded('curl');
    }

    /**
     * @return array{0:string|null,1:string|null}
     */
    private function fetchFirstSuccessfulResponse(array $urls): array {
        $lastError = null;

        foreach ($urls as $url) {
            [$response, $httpCode, $curlError, $contentType] = $this->performCurlRequest($url);

            error_log(sprintf(
                '[DEBUG] OpenPostcode call -> %s | HTTP: %s | cURLError: %s | CT: %s | Body: %s',
                $url,
                (string)($httpCode ?? 'unknown'),
                $curlError ?? 'none',
                $contentType ?? 'unknown',
                $response !== false ? ($response ?: 'empty') : 'false'
            ));

            if ($response !== false && $httpCode === 200) {
                return [$response, null];
            }

            $lastError = $curlError ?? (is_string($response) ? $response : 'Onbekende fout');
        }

        return [null, $lastError];
    }

    /**
     * @return array{0:string|false,1:int|null,2:string|null,3:string|null}
     */
    private function performCurlRequest(string $url): array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CURL_CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::CURL_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: ' . self::USER_AGENT,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: null;
        $curlError = curl_error($ch) ?: null;
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: null;
        curl_close($ch);

        return [$response, $httpCode, $curlError, $contentType];
    }

    private function buildLookupUrls(string $postcode, int $huisnummer, string $toevoeging): array {
        $additionSegment = $toevoeging !== '' ? "/{$toevoeging}" : '';
        $additionQuery = $toevoeging !== '' ? "&addition={$toevoeging}" : '';

        $urls = [
            "https://openpostcode.nl/api/v1/postcode/{$postcode}/{$huisnummer}{$additionSegment}",
            "https://openpostcode.nl/api/lookup/{$postcode}/{$huisnummer}{$additionSegment}",
            "https://openpostcode.nl/api/v1/lookup?postcode={$postcode}&number={$huisnummer}{$additionQuery}",
        ];

        $urls[] = "https://geodata.nationaalgeoregister.nl/locatieserver/v3/free?fq=type:adres&fq=postcode:{$postcode}&fq=huisnummer:{$huisnummer}";
        $q = rawurlencode("type:adres AND postcode:{$postcode} AND huisnummer:{$huisnummer}");
        $urls[] = "https://api.pdok.nl/bzk/locatieserver/search/v3_1/free?q={$q}";

        return $urls;
    }

    private function extractAddress(array $decoded, string $fallbackPostcode): ?array {
        $candidates = $this->buildCandidateList($decoded);
        $streetKeys = ['street','straat','streetname','straatnaam','thoroughfare','openbareruimte','public_space'];
        $cityKeys   = ['city','plaats','woonplaats','woonplaatsnaam','locality','municipality','gemeente','citylabel'];
        $pcKeys     = ['postcode','postalcode','zip','zip_code','post_code'];

        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $street = $this->firstNonEmptyString($candidate, $streetKeys);
            $city = $this->firstNonEmptyString($candidate, $cityKeys);
            $postcode = $this->firstNonEmptyString($candidate, $pcKeys);

            if ($street && $city) {
                return [
                    'street' => $street,
                    'city' => $city,
                    'postcode' => $postcode ? strtoupper(str_replace(' ', '', $postcode)) : $fallbackPostcode,
                ];
            }
        }

        return null;
    }

    private function buildCandidateList(array $decoded): array {
        $candidates = [];

        if (isset($decoded[0]) && is_array($decoded[0])) {
            $candidates[] = $decoded[0];
        }
        if (isset($decoded['result'])) {
            $candidates[] = $decoded['result'];
        }
        if (isset($decoded['results']) && is_array($decoded['results'])) {
            $candidates[] = $decoded['results'][0] ?? [];
        }
        if (isset($decoded['data'])) {
            $candidates[] = $decoded['data'];
        }
        if (isset($decoded['response']['docs']) && is_array($decoded['response']['docs'])) {
            $candidates[] = $decoded['response']['docs'][0] ?? [];
        }

        $candidates[] = $decoded;
        return $candidates;
    }

    private function firstNonEmptyString(array $source, array $keys): ?string {
        foreach ($keys as $key) {
            if (isset($source[$key]) && is_string($source[$key]) && $source[$key] !== '') {
                return $source[$key];
            }
        }
        return null;
    }

    private function respondWithError(string $message): void {
        echo json_encode(['error' => $message]);
        exit;
    }
}




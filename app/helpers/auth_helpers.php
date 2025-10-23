<?php
/**
 * Algemene authenticatie helpers. Bevat guards om dubbele declaraties te voorkomen.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('csrf_url')) {
    function csrf_url(string $url): string {
        $separator = (strpos($url, '?') !== false) ? '&' : '?';
        return $url . $separator . 'csrf_token=' . urlencode(csrf_token());
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token(?string $token): bool {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        return is_string($token) && $sessionToken !== '' && hash_equals($sessionToken, $token);
    }
}

if (!function_exists('require_valid_csrf_token')) {
    function require_valid_csrf_token(?string $token): void {
        if (!verify_csrf_token($token)) {
            http_response_code(419);
            exit('Ongeldig CSRF token. Vernieuw de pagina en probeer opnieuw.');
        }
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (empty($_SESSION['user_id'])) {
            header("Location: ?page=login");
            exit;
        }

        // Als een wachtwoordwijziging geforceerd is, stuur door naar de reset-pagina.
        // Sta toegang tot de logout-pagina wel toe.
        if (($_SESSION['force_password_change'] ?? 0) == 1 && $_GET['page'] !== 'logout' && $_GET['page'] !== 'force_reset_password') {
            header("Location: ?page=force_reset_password");
            exit;
        }
    }
}

if (!function_exists('requireRole')) {
    /**
     * Controleer of de huidige gebruiker een van de rollen heeft.
     * Voorbeeld: requireRole(['admin', 'accountmanager']);
     */
    function requireRole(array $allowedRoles) {
        if (empty($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles, true)) {
            // Als het een AJAX-request is, stuur een JSON-error. Anders, toon HTML.
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                http_response_code(403); // Forbidden
                echo json_encode(['error' => 'Toegang geweigerd. Je hebt onvoldoende rechten.']);
            } else {
                echo "<div style='padding:2rem; font-family:system-ui,sans-serif'>
                        <h3>Toegang geweigerd</h3>
                        <p>Je hebt geen rechten om deze pagina te bekijken.</p>
                        <a href='?page=dashboard'>Terug naar dashboard</a>
                      </div>";
            }
            exit;
        }
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin(): bool {
        return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'poweruser']);
    }
}

if (!function_exists('canEditVerslag')) {
    /**
     * Controleert of de huidige gebruiker een specifiek verslag mag bewerken.
     * Dit is waar als de gebruiker een admin is, de eigenaar is, of een collaborator is.
     * @param int $verslag_id De ID van het bezoekverslag.
     * @return bool True als de gebruiker mag bewerken, anders false.
     */
    function canEditVerslag(int $verslag_id): bool {
        if (isAdmin()) {
            return true;
        }

        $pdo = Database::getConnection();
        $userId = (int)($_SESSION['user_id'] ?? 0);
    
        // 1. Controleer of de gebruiker de eigenaar is
        $stmtOwner = $pdo->prepare("SELECT created_by FROM bezoekverslag WHERE id = ?");
        $stmtOwner->execute([$verslag_id]);
        $verslag = $stmtOwner->fetch(PDO::FETCH_ASSOC);
        if ($verslag && (int)$verslag['created_by'] === $userId) {
            return true;
        }
    
        // 2. Controleer of de gebruiker een collaborator is
        $stmtCollab = $pdo->prepare("SELECT user_id FROM verslag_collaborators WHERE verslag_id = ? AND user_id = ?");
        $stmtCollab->execute([$verslag_id, $userId]);
    
        // Return true als er een record is gevonden in de collaborators tabel
        return $stmtCollab->fetch() !== false;
    }
}

<?php
// --- Sessiebeveiliging ---
// Forceer het gebruik van cookies voor sessies, niet via URL's.
ini_set('session.use_only_cookies', 1);
// HttpOnly: De cookie is niet toegankelijk via client-side scripts (beschermt tegen XSS).
ini_set('session.cookie_httponly', 1);
// Secure: Verstuur de cookie alleen over een beveiligde (HTTPS) verbinding.
ini_set('session.cookie_secure', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 1 : 0);
// SameSite: Voorkomt dat de browser de cookie meestuurt met cross-site requests (beschermt tegen CSRF).
ini_set('session.cookie_samesite', 'Strict');
// Use Strict Mode: De server accepteert alleen sessie-ID's die door de server zelf zijn gegenereerd.
ini_set('session.use_strict_mode', 1);

session_start();

require_once __DIR__ . '/../vendor/autoload.php';

// Basis logging voor fouten wanneer serverlogs niet beschikbaar zijn.
$logDirectory = __DIR__ . '/../storage/logs';
$logFile = $logDirectory . '/runtime.log';
if (!is_dir($logDirectory) && !mkdir($logDirectory, 0775, true) && !is_dir($logDirectory)) {
    // Geef in noodgevallen een simpele melding terug zonder directory-informatie te lekken.
    http_response_code(500);
    exit('Applicatiefout: logmap kan niet worden aangemaakt.');
}

ini_set('log_errors', '1');
ini_set('error_log', $logFile);

$writeLog = static function (string $level, string $message, string $file, int $line) use ($logFile): void {
    $entry = sprintf(
        "[%s] %s: %s in %s on line %d%s",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $message,
        $file,
        $line,
        PHP_EOL
    );
    file_put_contents($logFile, $entry, FILE_APPEND);
};

set_error_handler(static function (int $severity, string $message, string $file, int $line) use ($writeLog): bool {
    $writeLog('error', $message, $file, $line);
    return false; // Laat PHP de standaard error-handler ook nog uitvoeren.
});

set_exception_handler(static function (\Throwable $throwable) use ($writeLog): void {
    $writeLog('exception', $throwable->getMessage(), $throwable->getFile(), $throwable->getLine());
    http_response_code(500);
    echo 'Er is een fout opgetreden. Controleer de runtime-log in storage/logs.';
});

register_shutdown_function(static function () use ($writeLog): void {
    $error = error_get_last();
    if ($error !== null) {
        $writeLog('shutdown', $error['message'], $error['file'], $error['line']);
    }
});

use App\Controllers\AdminController;
use App\Controllers\ApiController;
use App\Controllers\AuthController;
use App\Controllers\BezoekverslagController;
use App\Controllers\ClientController;
use App\Controllers\RuimteController;
use App\Controllers\UpdateController;
use App\Config\Database;

const HEADER_LOCATION = 'Location: ';
const ROUTE_DASHBOARD = '?page=dashboard';
const ROUTE_ADMIN = '?page=admin';
const ROUTE_CLIENT_LOGIN = '?page=client_login';

$page = $_GET['page'] ?? 'dashboard';

// Instantieer controllers
$bezoekverslagController = new BezoekverslagController();
$adminController         = new AdminController();
$authController          = new AuthController();
$ruimteController        = new RuimteController();
$clientController        = new ClientController();
$apiController           = new ApiController();
$updateController        = new UpdateController();
$_SESSION['flash_message'] = $_SESSION['flash_message'] ?? null;

switch ($page) {
    /* -------- AUTH -------- */
    case 'login':   $authController->login();   break;
    case 'logout':  $authController->logout();  break;
    case 'reset':   $authController->reset();   break;
    case 'forgot':  $authController->forgot();  break;
    // case 'force_reset_password': $authController->forceResetPassword(); break; // This method does not exist
    case 'register': $authController->register(); break;
    case '2fa_verify': $authController->verify2FA(); break;

    /* ----- BEZOEKVERSLAGEN ----- */
    case 'dashboard':
        requireLogin();
        $data = $bezoekverslagController->index(); // Haalt alleen de verslagen op
        // Voeg client portals data toe voor de dashboard view
        $data['clientPortals'] = $adminController->getClientPortals(Database::getConnection());
        $bezoekverslagController->showDashboard($data); // Toont de view met alle data
        break;

    case 'nieuw':
        requireLogin();
        $bezoekverslagController->nieuw();
        break;

    case 'bewerk':
        requireLogin();
        if (!isset($_GET['id'])) { header(HEADER_LOCATION . ROUTE_DASHBOARD); exit; }
        $bezoekverslagController->bewerk((int)$_GET['id']);
        break;

    case 'submit':
        requireLogin();
        if (!isset($_GET['id'])) { header(HEADER_LOCATION . ROUTE_DASHBOARD); exit; }
        $bezoekverslagController->generatePdf((int)$_GET['id']);
        break;

    case 'reset_client_password':
        requireLogin();
        if (!isset($_GET['id'])) { header(HEADER_LOCATION . ROUTE_DASHBOARD); exit; }
        $bezoekverslagController->resetClientPassword((int)$_GET['id']);
        break;

    case 'delete_verslag':
        requireLogin();
        if (!isset($_GET['id'])) { header(HEADER_LOCATION . ROUTE_DASHBOARD); exit; }
        $bezoekverslagController->delete((int)$_GET['id']);
        break;
    case 'download_project_files':
        requireLogin();
        if (!isset($_GET['id'])) { header(HEADER_LOCATION . ROUTE_DASHBOARD); exit; }
        $bezoekverslagController->downloadProjectFilesAsZip((int)$_GET['id']);
        break;

    case 'download_photos':
        requireLogin();
        if (!isset($_GET['id'])) { header(HEADER_LOCATION . ROUTE_DASHBOARD); exit; }
        $bezoekverslagController->downloadPhotosAsZip((int)$_GET['id']);
        break;

    /* -------- RUIMTES -------- */
    case 'ruimte_new':
        requireLogin();
        if (!isset($_GET['verslag_id'])) { header(HEADER_LOCATION . ROUTE_DASHBOARD); exit; }
        $ruimteController->create((int)$_GET['verslag_id']);
        break;

    case 'ruimte_edit':
        requireLogin();
        if (!isset($_GET['id'])) { header(HEADER_LOCATION . ROUTE_DASHBOARD); exit; }
        $ruimteController->edit((int)$_GET['id']);
        break;

    case 'ruimte_save':
        requireLogin();
        // Deze route is specifiek voor het aanmaken van een nieuwe ruimte
        $ruimteController->save();
        break;

    case 'ruimte_delete':
        requireLogin();
        if (!isset($_GET['id'])) { header(HEADER_LOCATION . ROUTE_DASHBOARD); exit; }
        $ruimteController->delete((int)$_GET['id']);
        break;

    case 'foto_delete':
        requireLogin();
        if (!isset($_GET['id'])) { header(HEADER_LOCATION . ROUTE_DASHBOARD); exit; }
        $ruimteController->deleteFoto((int)$_GET['id']);
        break;

    /* -------- ADMIN -------- */
    case 'admin':
        requireLogin();
        $adminController->users();
        break;

    case 'admin_delete_user':
        requireLogin();
        if (!isset($_GET['id'])) { header(HEADER_LOCATION . ROUTE_ADMIN); exit; }
        $adminController->deleteUser((int)$_GET['id']);
        break;
    
    case 'admin_reset_password':
        requireLogin();
        if (!isset($_GET['id'])) { header(HEADER_LOCATION . ROUTE_ADMIN); exit; }
        $adminController->adminResetPassword((int)$_GET['id']);
        break;

    case 'admin_impersonate':
        requireLogin();
        if (!isset($_GET['id'])) { header(HEADER_LOCATION . ROUTE_ADMIN); exit; }
        $adminController->impersonateUser((int)$_GET['id']);
        break;

    case 'admin_stop_impersonate':
        $adminController->stopImpersonation();
        break;

    case 'admin_restore_verslag':
        requireLogin();
        if (!isset($_GET['id'])) { header(HEADER_LOCATION . ROUTE_ADMIN); exit; }
        $adminController->restoreVerslag((int)$_GET['id']);
        break;

    case 'admin_permanent_delete_verslag':
        requireLogin();
        if (!isset($_GET['id'])) { header(HEADER_LOCATION . ROUTE_ADMIN); exit; }
        $adminController->permanentDeleteVerslag((int)$_GET['id']);
        break;

    case 'admin_reset_client_password':
        requireLogin();
        if (!isset($_GET['id'])) { header(HEADER_LOCATION . ROUTE_ADMIN); exit; }
        $adminController->resetClientPassword((int)$_GET['id']);
        break;

    case 'admin_backup_db':
        requireLogin();
        $adminController->backupDatabase();
        break;

    case 'admin_revoke_client':
        requireLogin();
        if (!isset($_GET['id'])) { header(HEADER_LOCATION . ROUTE_ADMIN); exit; }
        $adminController->revokeClientAccess((int)$_GET['id']);
        break;

    case 'admin_extend_client':
        requireLogin();
        if (!isset($_GET['id'])) { header(HEADER_LOCATION . ROUTE_ADMIN); exit; }
        $adminController->extendClientAccess((int)$_GET['id']);
        break;

    case 'admin_test_smtp':
        requireLogin();
        $adminController->testSmtp();
        break;

    case 'profile':
        requireLogin();
        $adminController->profile();
        break;

    /* -------- CLIENT PORTAL -------- */
    case 'client_login':
        $clientController->login();
        break;

    case 'client_logout':
        $clientController->logout();
        break;

    case 'client_view':
        if (!isset($_GET['id'])) { header(HEADER_LOCATION . ROUTE_CLIENT_LOGIN); exit; }
        $clientController->view((int)$_GET['id']);
        break;

    /* -------- UPDATER -------- */
    case 'admin_check_updates':
        $updateController->check(); // Route voor de AJAX call vanuit admin.php
        break;
    case 'admin_perform_update':
        $updateController->performUpdate(); // Route voor het uitvoeren van de update
        break;

    /* -------- API (intern) -------- */
    case 'api_postcode_lookup':
        $apiController->postcodeLookup();
        break;
    default:
        header(HEADER_LOCATION . ROUTE_DASHBOARD);
        exit;
}







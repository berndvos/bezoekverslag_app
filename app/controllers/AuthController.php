<?php

namespace App\Controllers;

use App\Config\Database;
use PDO;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AuthController {

    private const REMEMBER_COOKIE_NAME = 'remember_token';
    private const REMEMBER_COOKIE_LIFETIME = 86400 * 30; // 30 dagen
    private const REDIRECT_DASHBOARD = 'Location: ?page=dashboard';
    private const PLACEHOLDER_USER_FULLNAME = '{user_fullname}';

    private function isSecureRequest(): bool {
        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    }

    private function generateTokenPair(): array {
        $token = bin2hex(random_bytes(32));
        return [$token, hash('sha256', $token)];
    }

    private function setRememberMeCookie(string $token): void {
        $options = [
            'expires' => time() + self::REMEMBER_COOKIE_LIFETIME,
            'path' => '/',
            'secure' => $this->isSecureRequest(),
            'httponly' => true,
            'samesite' => 'Strict',
        ];

        if (PHP_VERSION_ID >= 70300) {
            setcookie(self::REMEMBER_COOKIE_NAME, $token, $options);
        } else {
            setcookie(
                self::REMEMBER_COOKIE_NAME,
                $token,
                $options['expires'],
                $options['path'] . '; SameSite=' . $options['samesite'],
                '',
                $options['secure'],
                $options['httponly']
            );
        }
    }

    private function clearRememberMeCookie(): void {
        $options = [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $this->isSecureRequest(),
            'httponly' => true,
            'samesite' => 'Strict',
        ];

        if (PHP_VERSION_ID >= 70300) {
            setcookie(self::REMEMBER_COOKIE_NAME, '', $options);
        } else {
            setcookie(
                self::REMEMBER_COOKIE_NAME,
                '',
                $options['expires'],
                $options['path'] . '; SameSite=' . $options['samesite'],
                '',
                $options['secure'],
                $options['httponly']
            );
        }
    }

    /** LOGIN **/
    public function login() {
        if ($this->shouldRedirectAuthenticatedSession()) {
            $this->redirectToDashboard();
        }

        $pdo = Database::getConnection();
        if ($this->attemptRememberMeLogin($pdo)) {
            return;
        }

        $error = '';
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_valid_csrf_token($_POST['csrf_token'] ?? null);
            [$success, $error] = $this->processLoginSubmission($pdo, $_POST);
            if ($success) {
                return;
            }
        }

        include __DIR__ . '/../views/login.php';
    }

    /** LOGOUT **/
    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $pdo = Database::getConnection();
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId) {
            $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?")->execute([$userId]);
        }
        $this->clearRememberMeCookie();
        session_destroy();
        log_action('logout', "Gebruiker is uitgelogd.");
        header("Location: ?page=login");
        exit;
    }

    /** REGISTRATIE **/
    public function register() {
        $error = '';
        $success = '';
        $pdo = Database::getConnection();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_valid_csrf_token($_POST['csrf_token'] ?? null);
            [$error, $success] = $this->handleRegistrationSubmission($pdo, $_POST);
        }

        include __DIR__ . '/../views/register.php';
        exit;
    }

    /** WACHTWOORD VERGETEN **/
    public function forgot() {
        $error = '';
        $msg = '';
        $pdo = Database::getConnection();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_valid_csrf_token($_POST['csrf_token'] ?? null);
            [$error, $msg] = $this->handleForgotPasswordSubmission($pdo, $_POST);
        }

        include __DIR__ . '/../views/forgot.php';
    }

    /** RESET WACHTWOORD **/
    public function reset() {
        $pdo = Database::getConnection();
        $token = $_GET['token'] ?? '';
        $error = '';
        $msg = '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_valid_csrf_token($_POST['csrf_token'] ?? null);
            $token = $_POST['token'] ?? '';
            [$error, $msg] = $this->handleResetSubmission($pdo, $token, $_POST);
        }

        include __DIR__ . '/../views/reset.php';
    }

    private function shouldRedirectAuthenticatedSession(): bool {
        return !empty($_SESSION['user_id']) && empty($_SESSION['original_user']);
    }

    private function redirectToDashboard(): void {
        header(self::REDIRECT_DASHBOARD);
        exit;
    }

    private function attemptRememberMeLogin(\PDO $pdo): bool {
        if (!empty($_SESSION['user_id']) || !empty($_SESSION['original_user'])) {
            return false;
        }

        $cookieToken = $_COOKIE[self::REMEMBER_COOKIE_NAME] ?? '';
        if ($cookieToken === '') {
            return false;
        }

        if (!preg_match('/^[a-f0-9]{64}$/', $cookieToken)) {
            $this->clearRememberMeCookie();
            return false;
        }

        $hashedToken = hash('sha256', $cookieToken);
        $user = $this->fetchUserByRememberToken($pdo, $hashedToken, $cookieToken);
        if (!$user) {
            $this->clearRememberMeCookie();
            return false;
        }

        $this->finalizeSuccessfulLogin($pdo, $user, true);
        return true;
    }

    private function processLoginSubmission(\PDO $pdo, array $data): array {
        $email = trim($data['email'] ?? '');
        $password = (string)($data['password'] ?? '');
        $user = $email !== '' ? $this->fetchUserByEmail($pdo, $email) : null;

        if ($this->userIsInactive($user)) {
            log_action('login_failed_inactive', "Mislukte inlogpoging voor inactieve gebruiker '{$email}'.");
            return [false, "Uw account is nog niet geactiveerd of is geblokkeerd. Neem contact op met de beheerder."];
        }

        if ($user && password_verify($password, $user['password'])) {
            $remember = !empty($data['remember']);
            $this->finalizeSuccessfulLogin($pdo, $user, $remember);
            return [true, ''];
        }

        if ($email !== '') {
            log_action('login_failed', "Mislukte inlogpoging voor '{$email}'.");
        }

        return [false, "Ongeldig e-mailadres of wachtwoord."];
    }

    private function finalizeSuccessfulLogin(\PDO $pdo, array $user, bool $remember): void {
        session_regenerate_id(true);
        $this->establishSessionFromUser($user);
        $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
        $this->handleRememberMePreference($pdo, $user['id'], $remember);
        log_action('login_success', "Gebruiker '{$user['email']}' is ingelogd.");
        $this->redirectToDashboard();
    }

    private function establishSessionFromUser(array $user): void {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['role'] = $user['role'];
    }

    private function handleRememberMePreference(\PDO $pdo, int $userId, bool $remember): void {
        if ($remember) {
            [$tokenPlain, $tokenHash] = $this->generateTokenPair();
            $this->setRememberMeCookie($tokenPlain);
            $pdo->prepare("UPDATE users SET remember_token=? WHERE id=?")->execute([$tokenHash, $userId]);
            return;
        }

        $this->clearRememberMeCookie();
        $pdo->prepare("UPDATE users SET remember_token=NULL WHERE id=?")->execute([$userId]);
    }

    private function fetchUserByEmail(\PDO $pdo, string $email): ?array {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    private function fetchUserByRememberToken(\PDO $pdo, string $hashedToken, string $plainToken): ?array {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE remember_token = ?");
        $stmt->execute([$hashedToken]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($user) {
            return $user;
        }

        // Legacy ondersteuning: tokens die nog niet gehasht waren
        $stmtLegacy = $pdo->prepare("SELECT * FROM users WHERE remember_token = ?");
        $stmtLegacy->execute([$plainToken]);
        $legacyUser = $stmtLegacy->fetch(\PDO::FETCH_ASSOC);
        if ($legacyUser) {
            $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?")->execute([$hashedToken, $legacyUser['id']]);
        }
        return $legacyUser ?: null;
    }

    private function userIsInactive(?array $user): bool {
        return $user && ($user['status'] ?? 'pending') !== 'active';
    }

    private function handleRegistrationSubmission(\PDO $pdo, array $input): array {
        $fullname = trim($input['fullname'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = (string)($input['password'] ?? '');
        $passwordConfirm = (string)($input['password_confirm'] ?? '');

        if (!$fullname || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            return ['Vul alle velden correct in.', ''];
        }
        if ($password !== $passwordConfirm) {
            return ['De wachtwoorden komen niet overeen.', ''];
        }
        if (strlen($password) < 8) {
            return ['Wachtwoord moet minimaal 8 tekens bevatten.', ''];
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['Dit e-mailadres is al in gebruik.', ''];
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password, role, status, created_at) VALUES (?, ?, ?, 'viewer', 'pending', NOW())");
        if (!$stmt->execute([$fullname, $email, $passwordHash])) {
            log_action('registration_failed', "Databasefout bij registratie voor '{$email}'.");
            return ['Er is een fout opgetreden bij het aanmaken van uw account.', ''];
        }

        $newUserId = $pdo->lastInsertId();
        log_action('user_registered', "Nieuwe gebruiker '{$email}' heeft zich geregistreerd (ID: {$newUserId}).");
        $this->sendAdminRegistrationNotification($fullname, $email, $newUserId);

        return ['', 'Uw registratie is ontvangen. Een beheerder zal uw account beoordelen. U ontvangt een e-mail wanneer uw account is goedgekeurd.'];
    }

    private function handleForgotPasswordSubmission(\PDO $pdo, array $input): array {
        $email = trim($input['email'] ?? '');
        if ($email === '') {
            return ['Geen gebruiker gevonden met dit e-mailadres.', ''];
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user) {
            return ["Geen gebruiker gevonden met dit e-mailadres.", ''];
        }

        [$plainToken, $hashedToken] = $this->generateTokenPair();
        $pdo->prepare("UPDATE users SET reset_token=?, reset_expires=DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id=?")
            ->execute([$hashedToken, $user['id']]);

        if ($this->sendPasswordResetEmail($user, $plainToken)) {
            return ['', "Er is een e-mail verstuurd met instructies voor het resetten van je wachtwoord."];
        }

        return ["Er ging iets mis bij het versturen van de e-mail.", ''];
    }

    private function handleResetSubmission(\PDO $pdo, string $token, array $input): array {
        $password = (string)($input['password'] ?? '');
        $passwordRepeat = (string)($input['password_repeat'] ?? '');
        $hashedToken = preg_match('/^[a-f0-9]{64}$/', $token) ? hash('sha256', $token) : '';

        $user = $this->fetchUserByResetToken($pdo, $token, $hashedToken);
        if (!$user) {
            return ["Ongeldige of verlopen resetlink.", ''];
        }

        if ($password !== $passwordRepeat) {
            return ["De ingevoerde wachtwoorden komen niet overeen.", ''];
        }

        if (strlen($password) < 8) {
            return ["Het wachtwoord moet minimaal 8 tekens lang zijn.", ''];
        }

        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password=?, reset_token=NULL, reset_expires=NULL WHERE id=?")
            ->execute([$newHash, $user['id']]);

        return ['', "Wachtwoord succesvol gewijzigd. Je kunt nu inloggen."];
    }

    private function fetchUserByResetToken(\PDO $pdo, string $plainToken, string $hashedToken): ?array {
        if ($hashedToken !== '') {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token=? AND reset_expires > NOW()");
            $stmt->execute([$hashedToken]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($user) {
                return $user;
            }
        }

        if ($plainToken === '') {
            return null;
        }

        $stmtLegacy = $pdo->prepare("SELECT * FROM users WHERE reset_token=? AND reset_expires > NOW()");
        $stmtLegacy->execute([$plainToken]);
        $legacyUser = $stmtLegacy->fetch(\PDO::FETCH_ASSOC);

        if ($legacyUser && $hashedToken !== '') {
            $pdo->prepare("UPDATE users SET reset_token=? WHERE id=?")->execute([$hashedToken, $legacyUser['id']]);
        }

        return $legacyUser ?: null;
    }

    /** 2FA VERIFICATIEPAGINA **/
    public function verify2FA() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Als er geen gebruiker is die moet verifiÃ«ren, terug naar login
        if (empty($_SESSION['pending_2fa_user_id'])) {
            header('Location: ?page=login');
            exit;
        }

        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_valid_csrf_token($_POST['csrf_token'] ?? null);
            $code = $_POST['2fa_code'] ?? '';
            $userId = $_SESSION['pending_2fa_user_id'];

            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND two_factor_expires_at > NOW()");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Controleer of de code overeenkomt
            if ($user && password_verify($code, $user['two_factor_code'])) {
                // Code is correct, log de gebruiker in
                session_regenerate_id(true); // Voorkom session fixation

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['role'] = $user['role'];

                // Reset de 2FA velden in de database
                $pdo->prepare("UPDATE users SET two_factor_code = NULL, two_factor_expires_at = NULL, last_login = NOW() WHERE id = ?")
                    ->execute([$user['id']]);

                // Remember-me cookie instellen indien aangevinkt op de login pagina
                if (isset($_SESSION['pending_2fa_remember']) && $_SESSION['pending_2fa_remember']) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + (86400 * 30), "/"); // 30 dagen
                    $pdo->prepare("UPDATE users SET remember_token=? WHERE id=?")->execute([$token, $user['id']]);
                }

                // Ruim tijdelijke sessievariabelen op
                unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_remember']);

                log_action('login_success_2fa', "Gebruiker '{$user['email']}' is ingelogd via 2FA.");
                header(self::REDIRECT_DASHBOARD);
                exit;
            } else {
                $error = "Ongeldige of verlopen code. Probeer het opnieuw.";
                log_action('login_failed_2fa', "Mislukte 2FA poging voor gebruiker ID {$userId}.");
            }
        }

        include __DIR__ . '/../views/2fa_verify.php';
    }

    /** Hulpfunctie om de 2FA code e-mail te versturen */
    private function send2FACodeEmail($userEmail, $code) {
        // We gebruiken de bestaande e-mailfunctionaliteit uit de AdminController
        // Dit is niet ideaal, een aparte MailService klasse zou beter zijn, maar dit werkt voor nu.
        $adminController = new AdminController();
        $mailSettings = $adminController->getSmtpSettings();
        $emailTemplates = $adminController->getEmailTemplates();
        $template = $emailTemplates['2fa_code'] ?? [
            'subject' => 'Uw verificatiecode',
            'body' => '<p>Uw verificatiecode is: <strong>{2fa_code}</strong></p><p>Deze code is 15 minuten geldig.</p>'
        ];

        $subject = str_replace('{2fa_code}', $code, $template['subject']);
        $body = str_replace('{2fa_code}', $code, $template['body']);

        // We moeten de private sendEmail methode public maken of een wrapper gebruiken.
        // Voor nu maken we een tijdelijke public wrapper in AdminController.
        $adminController->sendPublicEmail($userEmail, '', $subject, $body, $mailSettings);
    }

    /** Hulpfunctie om de wachtwoord reset e-mail te versturen */
    public function sendPasswordResetEmail($user, $token) {
        $adminController = new AdminController();
        $mailSettings = $adminController->getSmtpSettings();
        $emailTemplates = $adminController->getEmailTemplates();
        $emailTemplate = $emailTemplates['password_reset'] ?? null;

        if (!$emailTemplate) {
            log_action('email_failed', "Wachtwoord reset mail niet verstuurd: template niet gevonden.");
            return false;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->setLanguage('nl');
            $mail->Host       = $mailSettings['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $mailSettings['username'];
            $mail->Password   = $mailSettings['password'];
            if (!empty($mailSettings['encryption'])) {
                $mail->SMTPSecure = ($mailSettings['encryption'] === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            }
            $mail->Port       = $mailSettings['port'];

            $mail->setFrom($mailSettings['from_address'], $mailSettings['from_name']);
            $mail->addAddress($user['email'], $user['fullname']);

            $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) . '/?page=reset&token=' . $token;
            $subject = str_replace(self::PLACEHOLDER_USER_FULLNAME, $user['fullname'], $emailTemplate['subject']);
            $body = str_replace([self::PLACEHOLDER_USER_FULLNAME, '{reset_link}'], [$user['fullname'], $resetLink], $emailTemplate['body']);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            return $mail->send();
        } catch (Exception $e) {
            log_action('email_failed', "Fout bij versturen wachtwoord reset naar {$user['email']}: " . $mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Stuurt een notificatie naar alle admins over een nieuwe registratie.
     */
    private function sendAdminRegistrationNotification($newUserName, $newUserEmail, $newUserId) {
        $adminController = new AdminController();
        $mailSettings = $adminController->getSmtpSettings();
        $emailTemplates = $adminController->getEmailTemplates();
        $template = $emailTemplates['admin_new_user_notification'] ?? null;

        if (!$template) {
            log_action('email_failed', "Admin notificatie mail niet verstuurd: template 'admin_new_user_notification' niet gevonden.");
            return;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT email, fullname FROM users WHERE role IN ('admin', 'poweruser') AND status = 'active'");
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($admins)) {
            log_action('email_failed', "Admin notificatie mail niet verstuurd: geen actieve admins gevonden.");
            return;
        }

        $approvalLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF'], 2) . '/public/?page=admin#registraties';

        foreach ($admins as $admin) {
            $placeholders = ['{admin_name}', self::PLACEHOLDER_USER_FULLNAME, '{user_email}', '{approval_link}'];
            $values = [$admin['fullname'], $newUserName, $newUserEmail, $approvalLink];

            $subject = str_replace($placeholders, $values, $template['subject']);
            $body = str_replace($placeholders, $values, $template['body']);

            $adminController->sendPublicEmail($admin['email'], $admin['fullname'], $subject, $body, $mailSettings);
        }
    }

    /**
     * Stuurt een e-mail naar de gebruiker dat zijn account is goedgekeurd.
     */
    public function sendApprovalEmail($user) {
        $adminController = new AdminController();
        $mailSettings = $adminController->getSmtpSettings();
        $emailTemplates = $adminController->getEmailTemplates();
        $template = $emailTemplates['user_approved_notification'] ?? null;

        if (!$template) {
            log_action('email_failed', "Goedkeuringsmail niet verstuurd naar {$user['email']}: template niet gevonden.");
            return false;
        }

        $loginLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF'], 2) . '/public/?page=login';
        $placeholders = [self::PLACEHOLDER_USER_FULLNAME, '{login_link}'];
        $values = [$user['fullname'], $loginLink];
        $subject = str_replace($placeholders, $values, $template['subject']);
        $body = str_replace($placeholders, $values, $template['body']);

        return $adminController->sendPublicEmail($user['email'], $user['fullname'], $subject, $body, $mailSettings);
    }
}

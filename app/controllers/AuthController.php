<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../helpers/log_helpers.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AuthController {

    /** LOGIN **/
    public function login() {
        // Als al ingelogd
        // Stuur alleen door als we NIET een gebruiker overnemen
        if (!empty($_SESSION['user_id']) && empty($_SESSION['original_user'])) {
            header("Location: ?page=dashboard");
            exit;
        }

        // Check remember-me cookie, maar niet als we een gebruiker overnemen
        if (empty($_SESSION['user_id']) && !empty($_COOKIE['remember_token']) && empty($_SESSION['original_user'])) {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE remember_token = ?");
            $stmt->execute([$_COOKIE['remember_token']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['role'] = $user['role'];
                header("Location: ?page=dashboard");
                exit;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$_POST['email']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Stap 1: Controleer e-mail en wachtwoord
            if ($user && ($user['status'] ?? 'pending') !== 'active') {
                $error = "Uw account is nog niet geactiveerd of is geblokkeerd. Neem contact op met de beheerder.";
                log_action('login_failed_inactive', "Mislukte inlogpoging voor inactieve gebruiker '{$_POST['email']}'.");
                include __DIR__ . '/../views/login.php';
                return;
            }
            if ($user && password_verify($_POST['password'], $user['password'])) {
                // 2FA is nu uitgeschakeld. Log de gebruiker direct in.
                session_regenerate_id(true); // Voorkom session fixation

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['role'] = $user['role'];

                $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

                // Remember-me cookie instellen indien aangevinkt
                if (!empty($_POST['remember'])) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + (86400 * 30), "/"); // 30 dagen
                    $pdo->prepare("UPDATE users SET remember_token=? WHERE id=?")->execute([$token, $user['id']]);
                }

                log_action('login_success', "Gebruiker '{$user['email']}' is ingelogd.");
                header("Location: ?page=dashboard");
                exit;
            } else {
                $error = "Ongeldig e-mailadres of wachtwoord.";
                log_action('login_failed', "Mislukte inlogpoging voor '{$_POST['email']}'.");
            }
        }

        include __DIR__ . '/../views/login.php';
    }

    /** LOGOUT **/
    public function logout() {
        session_start();
        session_destroy();
        log_action('logout', "Gebruiker is uitgelogd.");
        setcookie('remember_token', '', time() - 3600, "/");
        header("Location: ?page=login");
        exit;
    }

    /** REGISTRATIE **/
    public function register() {
        $error = '';
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $pdo = Database::getConnection();
            $fullname = trim($_POST['fullname'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $password_confirm = $_POST['password_confirm'] ?? '';

            if (!$fullname || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$password) {
                $error = 'Vul alle velden correct in.';
            } elseif ($password !== $password_confirm) {
                $error = 'De wachtwoorden komen niet overeen.';
            } elseif (strlen($password) < 8) {
                $error = 'Wachtwoord moet minimaal 8 tekens bevatten.';
            } else {
                // Controleer of e-mail al bestaat
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'Dit e-mailadres is al in gebruik.';
                } else {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password, role, status, created_at) VALUES (?, ?, ?, 'viewer', 'pending', NOW())");
                    if ($stmt->execute([$fullname, $email, $password_hash])) {
                        $newUserId = $pdo->lastInsertId();
                        log_action('user_registered', "Nieuwe gebruiker '{$email}' heeft zich geregistreerd (ID: {$newUserId}).");
                        $this->sendAdminRegistrationNotification($fullname, $email, $newUserId);
                        $success = 'Uw registratie is ontvangen. Een beheerder zal uw account beoordelen. U ontvangt een e-mail wanneer uw account is goedgekeurd.';
                    } else {
                        $error = 'Er is een fout opgetreden bij het aanmaken van uw account.';
                        log_action('registration_failed', "Databasefout bij registratie voor '{$email}'.");
                    }
                }
            }
        }

        include __DIR__ . '/../views/register.php';
        exit;
    }

    /** WACHTWOORD VERGETEN **/
    public function forgot() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$_POST['email']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $pdo->prepare("UPDATE users SET reset_token=?, reset_expires=DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id=?")
                    ->execute([$token, $user['id']]);

                if ($this->sendPasswordResetEmail($user, $token)) {
                    $msg = "Er is een e-mail verstuurd met instructies voor het resetten van je wachtwoord.";
                } else {
                    $error = "Er ging iets mis bij het versturen van de e-mail.";
                }
            } else {
                $error = "Geen gebruiker gevonden met dit e-mailadres.";
            }
        }

        include __DIR__ . '/../views/forgot.php';
    }

    /** RESET WACHTWOORD **/
    public function reset() {
        $pdo = Database::getConnection();
        $token = $_GET['token'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['token'] ?? '';
            $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token=? AND reset_expires > NOW()");
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $password = $_POST['password'] ?? '';
            $passwordRepeat = $_POST['password_repeat'] ?? '';

            if (!$user) {
                $error = "Ongeldige of verlopen resetlink.";
            } elseif ($password !== $passwordRepeat) {
                $error = "De ingevoerde wachtwoorden komen niet overeen.";
            } elseif (strlen($password) < 8) {
                $error = "Het wachtwoord moet minimaal 8 tekens lang zijn.";
            } else {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password=?, reset_token=NULL, reset_expires=NULL WHERE id=?")
                    ->execute([$newHash, $user['id']]);
                $msg = "Wachtwoord succesvol gewijzigd. Je kunt nu inloggen.";
            }
        }

        include __DIR__ . '/../views/reset.php';
    }

    /** 2FA VERIFICATIEPAGINA **/
    public function verify2FA() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Als er geen gebruiker is die moet verifiëren, terug naar login
        if (empty($_SESSION['pending_2fa_user_id'])) {
            header('Location: ?page=login');
            exit;
        }

        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                header("Location: ?page=dashboard");
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
        $mailSettings = $adminController->getSmtpSettings(true);
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
            $subject = str_replace('{user_fullname}', $user['fullname'], $emailTemplate['subject']);
            $body = str_replace(['{user_fullname}', '{reset_link}'], [$user['fullname'], $resetLink], $emailTemplate['body']);

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
            $placeholders = ['{admin_name}', '{user_fullname}', '{user_email}', '{approval_link}'];
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
        $placeholders = ['{user_fullname}', '{login_link}'];
        $values = [$user['fullname'], $loginLink];
        $subject = str_replace($placeholders, $values, $template['subject']);
        $body = str_replace($placeholders, $values, $template['body']);

        return $adminController->sendPublicEmail($user['email'], $user['fullname'], $subject, $body, $mailSettings);
    }
}

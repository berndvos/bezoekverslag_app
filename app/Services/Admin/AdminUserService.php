<?php

namespace App\Services\Admin;

use App\Controllers\AuthController;
use PDO;

class AdminUserService
{
    public function createUser(PDO $pdo, array $input): AdminServiceResponse
    {
        $fullname = trim($input['fullname'] ?? '');
        $email = trim($input['email'] ?? '');
        $role = $input['role'] ?? '';

        if ($fullname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $role === '') {
            return new AdminServiceResponse(false, 'Vul alle velden correct in.', 'danger');
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return new AdminServiceResponse(false, 'Dit e-mailadres is al in gebruik.', 'danger');
        }

        $temporaryPassword = bin2hex(random_bytes(16));
        $passwordHash = password_hash($temporaryPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO users (fullname, email, password, role, status, created_at)
            VALUES (?, ?, ?, ?, 'active', NOW())
        ");

        if (!$stmt->execute([$fullname, $email, $passwordHash, $role])) {
            return new AdminServiceResponse(false, 'Kon gebruiker niet opslaan.', 'danger');
        }

        $newUserId = (int) $pdo->lastInsertId();
        $stmtUser = $pdo->prepare('SELECT id, email, fullname FROM users WHERE id = ?');
        $stmtUser->execute([$newUserId]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if ($user && $this->sendPasswordSetupLink($pdo, $user)) {
            $message = 'Gebruiker aangemaakt. Er is een e-mail verstuurd met instructies om een wachtwoord in te stellen.';
            $type = 'success';
        } elseif ($user) {
            $message = 'Gebruiker aangemaakt, maar versturen van de wachtwoord e-mail is mislukt. Laat de gebruiker handmatig een reset aanvragen.';
            $type = 'warning';
        } else {
            $message = 'Gebruiker aangemaakt, maar kon geen resetlink versturen.';
            $type = 'warning';
        }

        if (function_exists('log_action')) {
            log_action('user_created', "Gebruiker '{$email}' aangemaakt.");
        }

        return new AdminServiceResponse(true, $message, $type);
    }

    public function manageRegistration(PDO $pdo, array $input, array $session): AdminServiceResponse
    {
        $userId = (int) ($input['user_id'] ?? 0);
        $action = $input['action'] ?? '';

        if ($userId <= 0 || !in_array($action, ['approve', 'deny'], true)) {
            return new AdminServiceResponse(false, 'Ongeldige actie.', 'danger');
        }

        $stmt = $pdo->prepare("SELECT id, email, fullname FROM users WHERE id = ? AND status = 'pending'");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return new AdminServiceResponse(false, 'Gebruiker niet gevonden of al verwerkt.', 'warning');
        }

        if ($action === 'approve') {
            $stmt = $pdo->prepare("
                UPDATE users
                SET status = 'active', approved_at = NOW(), approved_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$session['user_id'] ?? null, $userId]);

            if (function_exists('log_action')) {
                log_action('user_approved', "Gebruiker '{$user['email']}' (ID: {$userId}) goedgekeurd door '{$session['email'] ?? 'onbekend'}'.");
            }

            (new AuthController())->sendApprovalEmail($user);
            return new AdminServiceResponse(true, "Gebruiker {$user['email']} is goedgekeurd en kan nu inloggen.", 'success');
        }

        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$userId]);

        if (function_exists('log_action')) {
            log_action('user_denied', "Registratie voor '{$user['email']}' (ID: {$userId}) afgewezen door '{$session['email'] ?? 'onbekend'}'.");
        }

        return new AdminServiceResponse(true, "Registratie voor {$user['email']} is afgewezen en verwijderd.", 'info');
    }

    public function updateUser(PDO $pdo, array $input): AdminServiceResponse
    {
        $id = (int) ($input['user_id'] ?? 0);
        $fullname = trim($input['fullname'] ?? '');
        $email = trim($input['email'] ?? '');
        $role = $input['role'] ?? '';
        $newPassword = $input['new_password'] ?? '';
        $newPasswordRepeat = $input['new_password_repeat'] ?? '';

        if ($id <= 0 || $fullname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $role === '') {
            return new AdminServiceResponse(false, 'Onvolledige of ongeldige invoer.', 'danger');
        }

        $params = [
            'fullname' => $fullname,
            'email' => $email,
            'role' => $role,
            'id' => $id,
        ];

        $sql = "UPDATE users SET fullname = :fullname, email = :email, role = :role, updated_at = NOW()";

        if ($newPassword !== '') {
            if ($newPassword !== $newPasswordRepeat) {
                return new AdminServiceResponse(false, 'De nieuwe wachtwoorden komen niet overeen.', 'danger');
            }
            if (\strlen($newPassword) < 8) {
                return new AdminServiceResponse(false, 'Nieuw wachtwoord moet minimaal 8 tekens bevatten.', 'danger');
            }
            $params['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
            $sql .= ', password = :password';
        }

        $sql .= ' WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if (function_exists('log_action')) {
            log_action('user_updated', "Gebruiker '{$email}' bijgewerkt.");
        }

        return new AdminServiceResponse(true, 'Gebruiker succesvol bijgewerkt.', 'success');
    }

    public function sendPasswordSetupLink(PDO $pdo, array $user): bool
    {
        if (empty($user['id']) || empty($user['email'])) {
            return false;
        }

        $plainToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $plainToken);

        $stmt = $pdo->prepare('UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?');
        if (!$stmt->execute([$hashedToken, $user['id']])) {
            return false;
        }

        return (new AuthController())->sendPasswordResetEmail($user, $plainToken);
    }
}

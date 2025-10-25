<?php

namespace App\Services\Admin;

use App\Controllers\AuthController;
use PDO;

class AdminUserService
{
    public function createUser(PDO $pdo, array $input): AdminServiceResponse
    {
        $validation = $this->validateCreateInput($input);
        if (!$validation->success) {
            return $validation;
        }

        $fullname = $validation->data['fullname'];
        $email = $validation->data['email'];
        $role = $validation->data['role'];

        if ($this->emailExists($pdo, $email)) {
            return new AdminServiceResponse(false, 'Dit e-mailadres is al in gebruik.', 'danger');
        }

        $userId = $this->insertNewUser($pdo, $fullname, $email, $role);
        if ($userId === 0) {
            return new AdminServiceResponse(false, 'Kon gebruiker niet opslaan.', 'danger');
        }

        $user = $this->findUserById($pdo, $userId);
        list($type, $message) = $this->buildCreateUserMessage($pdo, $user);

        if (function_exists('log_action')) {
            log_action('user_created', "Gebruiker '{$email}' aangemaakt.");
        }

        return new AdminServiceResponse(true, $message, $type);
    }

    private function validateCreateInput(array $input): AdminServiceResponse
    {
        $fullname = trim($input['fullname'] ?? '');
        $email = trim($input['email'] ?? '');
        $role = $input['role'] ?? '';

        if ($fullname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $role === '') {
            return new AdminServiceResponse(false, 'Vul alle velden correct in.', 'danger');
        }

        return new AdminServiceResponse(true, '', 'info', [
            'fullname' => $fullname,
            'email' => $email,
            'role' => $role,
        ]);
    }

    private function emailExists(PDO $pdo, string $email): bool
    {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function insertNewUser(PDO $pdo, string $fullname, string $email, string $role): int
    {
        $temporaryPassword = bin2hex(random_bytes(16));
        $passwordHash = password_hash($temporaryPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            "INSERT INTO users (fullname, email, password, role, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())"
        );

        if (!$stmt->execute([$fullname, $email, $passwordHash, $role])) {
            return 0;
        }

        return (int)$pdo->lastInsertId();
    }

    private function findUserById(PDO $pdo, int $id): ?array
    {
        $stmtUser = $pdo->prepare('SELECT id, email, fullname FROM users WHERE id = ?');
        $stmtUser->execute([$id]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    private function buildCreateUserMessage(PDO $pdo, ?array $user): array
    {
        if ($user && $this->sendPasswordSetupLink($pdo, $user)) {
            return ['success', 'Gebruiker aangemaakt. Er is een e-mail verstuurd met instructies om een wachtwoord in te stellen.'];
        }
        if ($user) {
            return ['warning', 'Gebruiker aangemaakt, maar versturen van de wachtwoord e-mail is mislukt. Laat de gebruiker handmatig een reset aanvragen.'];
        }
        return ['warning', 'Gebruiker aangemaakt, maar kon geen resetlink versturen.'];
    }

    public function manageRegistration(PDO $pdo, array $input, array $session): AdminServiceResponse
    {
        $userId = (int)($input['user_id'] ?? 0);
        $action = $input['action'] ?? '';

        if ($userId <= 0 || !in_array($action, ['approve', 'deny'], true)) {
            return new AdminServiceResponse(false, 'Ongeldige actie.', 'danger');
        }

        $user = $this->findPendingUserById($pdo, $userId);

        if (!$user) {
            return new AdminServiceResponse(false, 'Gebruiker niet gevonden of al verwerkt.', 'warning');
        }

        if ($action === 'approve') {
            return $this->approveUser($pdo, $user, $session);
        }

        // The only other valid action is 'deny'
        return $this->denyUser($pdo, $user, $session);
    }

    private function findPendingUserById(PDO $pdo, int $userId): ?array
    {
        $stmt = $pdo->prepare("SELECT id, email, fullname FROM users WHERE id = ? AND status = 'pending'");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    private function approveUser(PDO $pdo, array $user, array $session): AdminServiceResponse
    {
        $stmt = $pdo->prepare(
            "UPDATE users SET status = 'active', approved_at = NOW(), approved_by = ? WHERE id = ?"
        );
        $stmt->execute([$session['user_id'] ?? null, $user['id']]);

        if (function_exists('log_action')) {
            $approver = $session['email'] ?? 'onbekend';
            log_action(
                'user_approved',
                "Gebruiker '{$user['email']}' (ID: {$user['id']}) goedgekeurd door '{$approver}'."
            );
        }

        (new AuthController())->sendApprovalEmail($user);
        return new AdminServiceResponse(true, "Gebruiker {$user['email']} is goedgekeurd en kan nu inloggen.", 'success');
    }

    private function denyUser(PDO $pdo, array $user, array $session): AdminServiceResponse
    {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);

        if (function_exists('log_action')) {
            $approver = $session['email'] ?? 'onbekend';
            log_action(
                'user_denied',
                "Registratie voor '{$user['email']}' (ID: {$user['id']}) afgewezen door '{$approver}'."
            );
        }

        return new AdminServiceResponse(true, "Registratie voor {$user['email']} is afgewezen en verwijderd.", 'info');
    }

    public function updateUser(PDO $pdo, array $input): AdminServiceResponse
    {
        $validation = $this->validateUpdateInput($input);
        if (!$validation->success) {
            return $validation;
        }
        $validatedData = $validation->data;

        $passwordResponse = $this->handlePasswordUpdate($validatedData['new_password'], $validatedData['new_password_repeat']);
        if (!$passwordResponse->success) {
            return $passwordResponse;
        }
        $passwordData = $passwordResponse->data;

        $params = [
            'fullname' => $validatedData['fullname'],
            'email' => $validatedData['email'],
            'role' => $validatedData['role'],
            'id' => $validatedData['id'],
        ];
        $sql = "UPDATE users SET fullname = :fullname, email = :email, role = :role, updated_at = NOW()";

        if ($passwordData['password_param'] !== null) {
            $sql .= $passwordData['password_sql'];
            $params['password'] = $passwordData['password_param'];
        }

        $sql .= ' WHERE id = :id';
        $stmt = $pdo->prepare($sql);

        if (!$stmt->execute($params)) {
            return new AdminServiceResponse(false, 'Bijwerken van gebruiker is mislukt.', 'danger');
        }

        if (function_exists('log_action')) {
            log_action('user_updated', "Gebruiker '{$validatedData['email']}' bijgewerkt.");
        }

        return new AdminServiceResponse(true, 'Gebruiker succesvol bijgewerkt.', 'success');
    }

    private function validateUpdateInput(array $input): AdminServiceResponse
    {
        $id = (int)($input['user_id'] ?? 0);
        $fullname = trim($input['fullname'] ?? '');
        $email = trim($input['email'] ?? '');
        $role = $input['role'] ?? '';

        if ($id <= 0 || $fullname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $role === '') {
            return new AdminServiceResponse(false, 'Onvolledige of ongeldige invoer.', 'danger');
        }

        return new AdminServiceResponse(true, '', 'info', [
            'id' => $id,
            'fullname' => $fullname,
            'email' => $email,
            'role' => $role,
            'new_password' => $input['new_password'] ?? '',
            'new_password_repeat' => $input['new_password_repeat'] ?? '',
        ]);
    }

    private function handlePasswordUpdate(string $newPassword, string $newPasswordRepeat): AdminServiceResponse
    {
        if ($newPassword === '') {
            return new AdminServiceResponse(true, '', 'info', ['password_sql' => '', 'password_param' => null]);
        }

        if ($newPassword !== $newPasswordRepeat) {
            return new AdminServiceResponse(false, 'De nieuwe wachtwoorden komen niet overeen.', 'danger');
        }

        if (strlen($newPassword) < 8) {
            return new AdminServiceResponse(false, 'Nieuw wachtwoord moet minimaal 8 tekens bevatten.', 'danger');
        }

        return new AdminServiceResponse(true, '', 'info', [
            'password_sql' => ', password = :password',
            'password_param' => password_hash($newPassword, PASSWORD_DEFAULT),
        ]);
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
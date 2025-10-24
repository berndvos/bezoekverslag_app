<?php

namespace App\Services\Admin;

use PDO;

class AdminProfileService
{
    public function isAjaxRequest(array $server, array $post): bool
    {
        return ($server['REQUEST_METHOD'] ?? 'GET') === 'POST'
            && (isset($post['update_profile']) || isset($post['change_password']));
    }

    public function handleAjax(PDO $pdo, array $post, int $userId): array
    {
        if (isset($post['update_profile'])) {
            return $this->processProfileUpdate($pdo, $post, $userId);
        }

        if (isset($post['change_password'])) {
            return $this->processPasswordChange($pdo, $post, $userId);
        }

        return ['success' => false, 'message' => 'Onbekende actie.'];
    }

    public function fetchProfileUser(PDO $pdo, int $userId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    public function fetchOwnReports(PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare("
            SELECT id, projecttitel, klantnaam, created_at, pdf_version, pdf_up_to_date
            FROM bezoekverslag
            WHERE created_by = ? AND deleted_at IS NULL
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchCollaborations(PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare("
            SELECT
                b.id,
                b.projecttitel,
                b.klantnaam,
                b.created_at,
                u.fullname as owner_name
            FROM verslag_collaborators vc
            JOIN bezoekverslag b ON vc.verslag_id = b.id
            JOIN users u ON b.created_by = u.id
            WHERE vc.user_id = ? AND b.created_by != ? AND b.deleted_at IS NULL
            ORDER BY b.created_at DESC
        ");
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function processProfileUpdate(PDO $pdo, array $post, int $userId): array
    {
        $fullname = trim($post['fullname'] ?? '');
        $email = trim($post['email'] ?? '');

        if ($fullname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Ongeldige invoer voor naam of e-mailadres.'];
        }

        $stmt = $pdo->prepare('UPDATE users SET fullname = ?, email = ? WHERE id = ?');
        $stmt->execute([$fullname, $email, $userId]);

        $_SESSION['fullname'] = $fullname;
        $_SESSION['email'] = $email;

        return ['success' => true, 'message' => 'Profielgegevens bijgewerkt.'];
    }

    private function processPasswordChange(PDO $pdo, array $post, int $userId): array
    {
        $currentPassword = $post['current_password'] ?? '';
        $newPassword = $post['new_password'] ?? '';
        $newPasswordRepeat = $post['new_password_repeat'] ?? '';

        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $passwordsValid = $newPassword === $newPasswordRepeat && \strlen($newPassword) >= 8;
        if ($user && password_verify($currentPassword, $user['password']) && $passwordsValid) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([$newHash, $userId]);

            return ['success' => true, 'message' => 'Wachtwoord succesvol gewijzigd.'];
        }

        return [
            'success' => false,
            'message' => 'Wachtwoord wijzigen mislukt. Controleer uw huidige wachtwoord en of de nieuwe wachtwoorden overeenkomen (min. 8 tekens).',
        ];
    }
}

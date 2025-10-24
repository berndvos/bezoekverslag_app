<?php

namespace App\Services\Admin;

use PDO;

class AdminClientAccessService
{
    private AdminSettingsService $settingsService;
    private AdminMailer $mailer;

    public function __construct(
        ?AdminSettingsService $settingsService = null,
        ?AdminMailer $mailer = null
    ) {
        $this->settingsService = $settingsService ?? new AdminSettingsService();
        $this->mailer = $mailer ?? new AdminMailer();
    }

    public function revokeAccess(PDO $pdo, int $verslagId, int $currentUserId, bool $isAdmin): AdminServiceResponse
    {
        if (!$this->canManageVerslag($pdo, $verslagId, $currentUserId, $isAdmin)) {
            return new AdminServiceResponse(false, 'Geen toegang.', 'danger');
        }

        $stmt = $pdo->prepare('DELETE FROM client_access WHERE bezoekverslag_id = ?');
        $stmt->execute([$verslagId]);

        if (function_exists('log_action')) {
            log_action('client_access_revoked', "Toegang voor verslag #{$verslagId} ingetrokken.");
        }

        return new AdminServiceResponse(true, 'Klanttoegang is ingetrokken.', 'info');
    }

    public function extendAccess(PDO $pdo, int $verslagId, int $currentUserId, bool $isAdmin): AdminServiceResponse
    {
        if (!$this->canManageVerslag($pdo, $verslagId, $currentUserId, $isAdmin)) {
            return new AdminServiceResponse(false, 'Geen toegang.', 'danger');
        }

        $stmt = $pdo->prepare('UPDATE client_access SET expires_at = DATE_ADD(NOW(), INTERVAL 14 DAY) WHERE bezoekverslag_id = ?');
        $stmt->execute([$verslagId]);

        if (function_exists('log_action')) {
            log_action('client_access_extended', "Toegang voor verslag #{$verslagId} verlengd.");
        }

        $mailSettings = $this->settingsService->getSmtpSettings();
        $emailTemplates = $this->settingsService->getEmailTemplates();
        $newExpiry = date('Y-m-d H:i:s', strtotime('+14 days'));

        $this->mailer->sendClientExtendedEmail(
            $pdo,
            $verslagId,
            $newExpiry,
            $mailSettings,
            $emailTemplates
        );

        return new AdminServiceResponse(true, 'Klanttoegang is met 14 dagen verlengd.', 'success', [
            'new_expiry' => $newExpiry,
        ]);
    }

    private function canManageVerslag(PDO $pdo, int $verslagId, int $currentUserId, bool $isAdmin): bool
    {
        if ($isAdmin) {
            return true;
        }

        $stmt = $pdo->prepare('SELECT id FROM bezoekverslag WHERE id = ? AND created_by = ?');
        $stmt->execute([$verslagId, $currentUserId]);

        return (bool) $stmt->fetch();
    }
}

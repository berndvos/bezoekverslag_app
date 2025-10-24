<?php

namespace App\Services\Admin;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PDO;

class AdminMailer
{
    public function sendEmail(array $mailSettings, string $toAddress, string $toName, string $subject, string $body): bool
    {
        if (empty($mailSettings['host']) || $mailSettings['host'] === 'smtp.example.com') {
            if (function_exists('log_action')) {
                log_action('email_failed', "SMTP niet geconfigureerd. E-mail naar {$toAddress} niet verzonden.");
            }
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $mailSettings['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $mailSettings['username'];
            $mail->Password = $mailSettings['password'];

            if (!empty($mailSettings['encryption'])) {
                $mail->SMTPSecure = $mailSettings['encryption'] === 'ssl'
                    ? PHPMailer::ENCRYPTION_SMTPS
                    : PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->Port = $mailSettings['port'];
            $mail->setFrom($mailSettings['from_address'], $mailSettings['from_name']);
            $mail->addAddress($toAddress, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();

            if (function_exists('log_action')) {
                log_action('email_sent', "E-mail '{$subject}' verzonden naar {$toAddress}.");
            }

            return true;
        } catch (Exception $e) {
            if (function_exists('log_action')) {
                log_action('email_failed', "Fout bij verzenden naar {$toAddress}: " . $mail->ErrorInfo);
            }
            return false;
        }
    }

    public function sendCollaborationEmail(
        array $collaborator,
        array $verslagInfo,
        int $verslagId,
        string $ownerName,
        array $mailSettings,
        array $emailTemplates
    ): bool {
        $emailTemplate = $emailTemplates['collaboration_invite'] ?? null;
        if (empty($emailTemplate)) {
            if (function_exists('log_action')) {
                log_action('email_failed', 'Samenwerking-mail niet verstuurd: template niet gevonden.');
            }
            return false;
        }

        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $verslagLink = $protocol . '://' . $_SERVER['HTTP_HOST'] . \dirname($_SERVER['PHP_SELF'], 2) . '/public/?page=bewerk&id=' . $verslagId;

        $placeholders = [
            '{collaborator_name}',
            '{project_title}',
            '{owner_name}',
            '{verslag_link}',
        ];

        $values = [
            $collaborator['fullname'],
            $verslagInfo['projecttitel'],
            $ownerName,
            $verslagLink,
        ];

        $subject = str_replace($placeholders, $values, $emailTemplate['subject'] ?? '');
        $body = str_replace($placeholders, $values, $emailTemplate['body'] ?? '');

        return $this->sendEmail($mailSettings, $collaborator['email'], $collaborator['fullname'], $subject, $body);
    }

    public function sendClientExtendedEmail(
        PDO $pdo,
        int $verslagId,
        string $newExpiryDate,
        array $mailSettings,
        array $emailTemplates
    ): void {
        $stmt = $pdo->prepare("
            SELECT ca.email, ca.fullname, b.projecttitel
            FROM client_access ca
            JOIN bezoekverslag b ON ca.bezoekverslag_id = b.id
            WHERE b.id = ?
        ");
        $stmt->execute([$verslagId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return;
        }

        $emailTemplate = $emailTemplates['client_portal_extended'] ?? null;
        if (!$emailTemplate) {
            return;
        }

        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $loginLink = $protocol . '://' . $_SERVER['HTTP_HOST'] . \dirname($_SERVER['PHP_SELF']) . '/?page=client_login';
        $formattedDate = date('d-m-Y', strtotime($newExpiryDate));

        $placeholders = ['{client_name}', '{project_title}', '{login_link}', '{expiry_date}'];
        $values = [$data['fullname'], $data['projecttitel'], $loginLink, $formattedDate];

        $subject = str_replace($placeholders, $values, $emailTemplate['subject'] ?? '');
        $body = str_replace($placeholders, $values, $emailTemplate['body'] ?? '');

        $this->sendEmail($mailSettings, $data['email'], $data['fullname'], $subject, $body);
    }
}

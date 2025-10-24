<?php

namespace App\Services\Admin;

use App\Services\Admin\AdminServiceResponse;

class AdminSettingsService
{
    private string $configDir;
    private string $uploadsDir;

    public function __construct(?string $configDir = null, ?string $uploadsDir = null)
    {
        $projectRoot = \dirname(__DIR__, 3);
        $this->configDir = $configDir ?? $projectRoot . DIRECTORY_SEPARATOR . 'config';
        $this->uploadsDir = $uploadsDir ?? $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads';
    }

    public function getBrandingSettings(): array
    {
        $configFile = $this->configDir . DIRECTORY_SEPARATOR . 'branding.php';
        return \file_exists($configFile) ? require_once $configFile : [];
    }

    public function getSmtpSettings(): array
    {
        $configFile = $this->configDir . DIRECTORY_SEPARATOR . 'mail_settings.php';
        return \file_exists($configFile) ? require_once $configFile : [];
    }

    public function getEmailTemplates(): array
    {
        $configFile = $this->configDir . DIRECTORY_SEPARATOR . 'email_templates.php';
        return \file_exists($configFile) ? require_once $configFile : [];
    }

    public function saveBrandingSettings(array $input): AdminServiceResponse
    {
        $currentSettings = $this->getBrandingSettings();
        $configFile = $this->configDir . DIRECTORY_SEPARATOR . 'branding.php';

        $newSettings = [
            'logo_path' => $currentSettings['logo_path'] ?? '',
            'primary_color' => $input['primary_color'] ?? '#FFD200',
            'primary_color_contrast' => $input['primary_color_contrast'] ?? '#111111',
        ];

        $content = "<?php\nreturn " . \var_export($newSettings, true) . ";\n";

        if (\file_put_contents($configFile, $content) === false) {
            return new AdminServiceResponse(false, 'Kon configuratiebestand niet wegschrijven. Controleer de schrijfrechten.', 'danger');
        }

        return new AdminServiceResponse(true, 'Huisstijl-instellingen succesvol opgeslagen.', 'success');
    }

    public function handleLogoUpload(?array $file, array $currentSettings): AdminServiceResponse
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return new AdminServiceResponse(false, 'Upload mislukt: geen geldig bestand ontvangen.', 'danger');
        }

        $uploadDir = $this->uploadsDir . DIRECTORY_SEPARATOR . 'branding';
        if (!\is_dir($uploadDir) && !\mkdir($uploadDir, 0777, true) && !\is_dir($uploadDir)) {
            return new AdminServiceResponse(false, 'Kon map voor upload niet aanmaken.', 'danger');
        }

        $allowedTypes = ['image/png', 'image/jpeg', 'image/svg+xml'];
        if (!\in_array($file['type'] ?? '', $allowedTypes, true)) {
            return new AdminServiceResponse(false, 'Ongeldig bestandstype. Alleen PNG, JPG en SVG zijn toegestaan.', 'danger');
        }

        $extension = \pathinfo($file['name'] ?? '', PATHINFO_EXTENSION);
        $safeName = 'logo.' . $extension;
        $filePath = $uploadDir . DIRECTORY_SEPARATOR . $safeName;

        if (!\move_uploaded_file($file['tmp_name'], $filePath)) {
            return new AdminServiceResponse(false, 'Fout bij het uploaden van het logo.', 'danger');
        }

        $configFile = $this->configDir . DIRECTORY_SEPARATOR . 'branding.php';
        $newSettings = $currentSettings;
        $newSettings['logo_path'] = 'uploads/branding/' . $safeName;
        $content = "<?php\nreturn " . \var_export($newSettings, true) . ";\n";
        \file_put_contents($configFile, $content);

        if (function_exists('log_action')) {
            log_action('logo_updated', 'Bedrijfslogo is bijgewerkt.');
        }

        return new AdminServiceResponse(true, 'Logo succesvol geupload.', 'success', [
            'logo_path' => $newSettings['logo_path'],
        ]);
    }

    public function saveEmailTemplates(array $input): AdminServiceResponse
    {
        $configFile = $this->configDir . DIRECTORY_SEPARATOR . 'email_templates.php';

        $newTemplates = [
            'password_reset' => [
                'subject' => $input['password_reset_subject'] ?? 'Wachtwoord resetten',
                'body' => $input['password_reset_body'] ?? '',
            ],
            'client_update' => [
                'subject' => $input['client_update_subject'] ?? 'Update van klant: {project_title}',
                'body' => $input['client_update_body'] ?? '',
            ],
            'new_user_created' => [
                'subject' => $input['new_user_created_subject'] ?? 'Welkom bij de Bezoekverslag App',
                'body' => $input['new_user_created_body'] ?? '',
            ],
            'new_client_login' => [
                'subject' => $input['new_client_login_subject'] ?? 'Toegang tot het klantportaal voor {project_title}',
                'body' => $input['new_client_login_body'] ?? '',
            ],
            'client_portal_extended' => [
                'subject' => $input['client_portal_extended_subject'] ?? 'Uw toegang tot het klantportaal is verlengd',
                'body' => $input['client_portal_extended_body'] ?? '',
            ],
            'collaboration_invite' => [
                'subject' => $input['collaboration_invite_subject'] ?? '',
                'body' => $input['collaboration_invite_body'] ?? '',
            ],
        ];

        $content = "<?php\nreturn " . \var_export($newTemplates, true) . ";\n";

        if (\file_put_contents($configFile, $content) === false) {
            return new AdminServiceResponse(false, 'Kon e-mail sjablonen niet wegschrijven. Controleer de schrijfrechten.', 'danger');
        }

        return new AdminServiceResponse(true, 'E-mail sjablonen opgeslagen.', 'success');
    }
}

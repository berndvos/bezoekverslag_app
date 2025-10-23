<?php
// Bezoekverslag App Installer
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

session_start();

// --- CONFIGURATION ---
$requiredPhpVersion = '7.4.0';
$requiredExtensions = ['pdo_mysql', 'mbstring', 'gd', 'json'];
$writablePaths = [
    'c:\\xampp\\htdocs\\bezoekverslag_app\\public\\uploads',
    'c:\\xampp\\htdocs\\bezoekverslag_app\\storage',
    'c:\\xampp\\htdocs\\bezoekverslag_app' // For .env file
];
$configDir = 'c:\\xampp\\htdocs\\bezoekverslag_app\\config';
define('INSTALLER_ENV_PATH', __DIR__ . '/.env');


// --- STATE & STEP MANAGEMENT ---
$step = $_GET['step'] ?? '1';
$error_message = '';
$success_message = '';

// Prevent re-installation
if (file_exists(INSTALLER_ENV_PATH)) {
    $step = 'already_installed';
}


// --- STEP 2: PROCESS INSTALLATION ---
if ($step === '2' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- 1. VALIDATE INPUT ---
    $db_host = $_POST['db_host'] ?? '';
    $db_name = $_POST['db_name'] ?? '';
    $db_user = $_POST['db_user'] ?? '';
    $db_pass = $_POST['db_pass'] ?? '';
    $admin_fullname = $_POST['admin_fullname'] ?? '';
    $admin_email = $_POST['admin_email'] ?? '';
    $admin_password = $_POST['admin_password'] ?? '';

    if (empty($db_host) || empty($db_name) || empty($db_user) || empty($admin_fullname) || empty($admin_email) || empty($admin_password)) {
        $error_message = "Vul alstublieft alle verplichte velden in.";
    } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Voer een geldig e-mailadres in voor de administrator.";
    } elseif (strlen($admin_password) < 8) {
        $error_message = "Het wachtwoord moet minimaal 8 tekens lang zijn.";
    } else {
        // --- 2. CREATE .ENV FILE ---
        $env_content = <<<EOT
# Database
DB_HOST={$db_host}
DB_NAME={$db_name}
DB_USER={$db_user}
DB_PASS={$db_pass}

# Mail (configure later in admin panel)
SMTP_HOST=
SMTP_PORT=587
SMTP_USER=
SMTP_PASS=
SMTP_ENCRYPTION=tls
SMTP_FROM_ADDRESS=
SMTP_FROM_NAME=
EOT;

        if (file_put_contents(INSTALLER_ENV_PATH, $env_content) === false) {
            $error_message = "Kon het .env bestand niet aanmaken. Controleer de schrijfrechten van de root directory.";
        } else {
            // --- 3. TEST DATABASE CONNECTION ---
            try {
                $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
                $pdo = new PDO($dsn, $db_user, $db_pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // --- 4. CREATE DATABASE TABLES ---
                $sql_commands = get_sql_schema();
                foreach ($sql_commands as $command) {
                    $pdo->exec($command);
                }

                // --- 5. CREATE ADMIN USER ---
                $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password, role, created_at) VALUES (?, ?, ?, 'poweruser', NOW())");
                $stmt->execute([$admin_fullname, $admin_email, $password_hash]);

                // --- 6. CREATE CONFIG FILES ---
                create_config_files($configDir);

                $success_message = "Installatie succesvol voltooid!";
                $step = '3';

            } catch (PDOException $e) {
                $error_message = "Databasefout: " . $e->getMessage() . ". Controleer de databasegegevens en probeer het opnieuw. Het kan zijn dat de database nog niet bestaat.";
                // Clean up failed install
                if (file_exists(INSTALLER_ENV_PATH)) {
                    unlink(INSTALLER_ENV_PATH);
                }
            } catch (Exception $e) {
                $error_message = "Er is een onbekende fout opgetreden: " . $e->getMessage();
            }
        }
    }
}


// --- HELPER FUNCTIONS ---

function check_php_version($required) {
    return version_compare(PHP_VERSION, $required, ' >= ');
}

function check_extensions($required) {
    $missing = [];
    foreach ($required as $ext) {
        if (!extension_loaded($ext)) {
            $missing[] = $ext;
        }
    }
    return $missing;
}

function check_paths($paths) {
    $unwritable = [];
    foreach ($paths as $path) {
        if (!is_writable($path)) {
            $unwritable[] = $path;
        }
    }
    return $unwritable;
}

function create_config_files($configDir) {
    // branding.php
    $branding_content = '<?php return [ "logo_path" => "", "primary_color" => "#FFD200", "primary_color_contrast" => "#111111" ];';
    @file_put_contents($configDir . '/branding.php', $branding_content);

    // email_templates.php
    $email_templates_content = '<?php return [];'; // Start with empty templates
    @file_put_contents($configDir . '/email_templates.php', $email_templates_content);
}

function get_sql_schema() {
    $sql = [];

    $sql[] = "CREATE TABLE `users` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `fullname` varchar(255) NOT NULL,
        `email` varchar(255) NOT NULL,
        `password` varchar(255) NOT NULL,
        `role` varchar(50) NOT NULL,
        `remember_token` varchar(255) DEFAULT NULL,
        `reset_token` varchar(64) DEFAULT NULL,
        `reset_expires` datetime DEFAULT NULL,
        `two_factor_code` varchar(255) DEFAULT NULL,
        `two_factor_expires_at` datetime DEFAULT NULL,
        `created_at` datetime NOT NULL,
        `updated_at` datetime DEFAULT NULL,
        `last_login` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `email` (`email`),
        KEY `reset_token` (`reset_token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $sql[] = "CREATE TABLE `bezoekverslag` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `created_by` int(11) DEFAULT NULL,
        `klantnaam` varchar(255) DEFAULT NULL,
        `projecttitel` varchar(255) DEFAULT NULL,
        `straatnaam` varchar(255) DEFAULT NULL,
        `huisnummer` varchar(50) DEFAULT NULL,
        `huisnummer_toevoeging` varchar(50) DEFAULT NULL,
        `postcode` varchar(255) DEFAULT NULL,
        `plaats` varchar(255) DEFAULT NULL,
        `kvk` varchar(255) DEFAULT NULL,
        `btw` varchar(255) DEFAULT NULL,
        `contact_naam` varchar(255) DEFAULT NULL,
        `contact_functie` varchar(255) DEFAULT NULL,
        `contact_email` varchar(255) DEFAULT NULL,
        `contact_tel` varchar(255) DEFAULT NULL,
        `gewenste_offertedatum` date DEFAULT NULL,
        `indicatief_budget` varchar(255) DEFAULT NULL,
        `situatie` text,
        `functioneel` text,
        `uitbreiding` text,
        `wensen` text,
        `beeldkwaliteitseisen` text,
        `geluidseisen` text,
        `bedieningseisen` text,
        `beveiligingseisen` text,
        `netwerkeisen` text,
        `garantie` text,
        `installatie_adres_afwijkend` varchar(3) DEFAULT 'Nee',
        `installatie_adres_straat` varchar(255) DEFAULT NULL,
        `installatie_adres_huisnummer` varchar(50) DEFAULT NULL,
        `installatie_adres_huisnummer_toevoeging` varchar(50) DEFAULT NULL,
        `installatie_adres_postcode` varchar(255) DEFAULT NULL,
        `installatie_adres_plaats` varchar(255) DEFAULT NULL,
        `cp_locatie_afwijkend` varchar(3) DEFAULT 'Nee',
        `cp_locatie_naam` varchar(255) DEFAULT NULL,
        `cp_locatie_functie` varchar(255) DEFAULT NULL,
        `cp_locatie_email` varchar(255) DEFAULT NULL,
        `cp_locatie_tel` varchar(255) DEFAULT NULL,
        `afvoer` varchar(255) DEFAULT NULL,
        `afvoer_omschrijving` text,
        `installatiedatum` date DEFAULT NULL,
        `locatie_apparatuur` varchar(255) DEFAULT NULL,
        `aantal_installaties` varchar(255) DEFAULT NULL,
        `parkeren` varchar(255) DEFAULT NULL,
        `toegang` varchar(255) DEFAULT NULL,
        `boortijden` varchar(255) DEFAULT NULL,
        `opleverdatum` date DEFAULT NULL,
        `created_at` datetime DEFAULT NULL,
        `updated_at` datetime DEFAULT NULL,
        `deleted_at` datetime DEFAULT NULL,
        `last_modified_at` datetime DEFAULT NULL,
        `last_modified_by` varchar(50) DEFAULT NULL,
        `pdf_version` int(11) DEFAULT '0',
        `pdf_path` varchar(255) DEFAULT NULL,
        `pdf_generated_at` datetime DEFAULT NULL,
        `pdf_up_to_date` tinyint(1) DEFAULT '0',
        PRIMARY KEY (`id`),
        KEY `created_by` (`created_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $sql[] = "CREATE TABLE `ruimte` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `verslag_id` int(11) NOT NULL,
        `naam` varchar(255) NOT NULL,
        `etage` varchar(255) DEFAULT NULL,
        `opmerkingen` text,
        `aantal_aansluitingen` varchar(255) DEFAULT NULL,
        `type_aansluitingen` varchar(255) DEFAULT NULL,
        `huidig_scherm` varchar(255) DEFAULT NULL,
        `audio_aanwezig` varchar(255) DEFAULT NULL,
        `beeldkwaliteit` text,
        `gewenst_scherm` varchar(255) DEFAULT NULL,
        `gewenst_aansluitingen` varchar(255) DEFAULT NULL,
        `presentatie_methode` varchar(255) DEFAULT NULL,
        `geluid_gewenst` varchar(255) DEFAULT NULL,
        `overige_wensen` text,
        `kabeltraject_mogelijk` varchar(255) DEFAULT NULL,
        `beperkingen` text,
        `ophanging` varchar(255) DEFAULT NULL,
        `montage_extra` text,
        `stroom_voldoende` varchar(255) DEFAULT NULL,
        `stroom_extra` text,
        PRIMARY KEY (`id`),
        KEY `verslag_id` (`verslag_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $sql[] = "CREATE TABLE `foto` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `ruimte_id` int(11) NOT NULL,
        `pad` varchar(255) NOT NULL,
        `created_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `ruimte_id` (`ruimte_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $sql[] = "CREATE TABLE `project_bestanden` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `verslag_id` int(11) NOT NULL,
        `bestandsnaam` varchar(255) NOT NULL,
        `pad` varchar(255) NOT NULL,
        `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `verslag_id` (`verslag_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $sql[] = "CREATE TABLE `audit_log` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) DEFAULT NULL,
        `user_fullname` varchar(255) NOT NULL,
        `action` varchar(255) NOT NULL,
        `details` text,
        `created_at` datetime NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $sql[] = "CREATE TABLE `client_access` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `bezoekverslag_id` int(11) NOT NULL,
        `email` varchar(255) NOT NULL,
        `password` varchar(255) NOT NULL,
        `fullname` varchar(255) DEFAULT NULL,
        `can_edit` tinyint(1) DEFAULT '0',
        `expires_at` datetime DEFAULT NULL,
        `last_login` datetime DEFAULT NULL,
        `last_modified_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `bezoekverslag_id` (`bezoekverslag_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $sql[] = "CREATE TABLE `verslag_collaborators` (
        `verslag_id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        `granted_by` int(11) DEFAULT NULL,
        `granted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`verslag_id`,`user_id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    // Add foreign key constraints
    $sql[] = "ALTER TABLE `bezoekverslag` ADD CONSTRAINT `bezoekverslag_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;";
    $sql[] = "ALTER TABLE `ruimte` ADD CONSTRAINT `ruimte_ibfk_1` FOREIGN KEY (`verslag_id`) REFERENCES `bezoekverslag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;";
    $sql[] = "ALTER TABLE `foto` ADD CONSTRAINT `foto_ibfk_1` FOREIGN KEY (`ruimte_id`) REFERENCES `ruimte` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;";
    $sql[] = "ALTER TABLE `client_access` ADD CONSTRAINT `client_access_ibfk_1` FOREIGN KEY (`bezoekverslag_id`) REFERENCES `bezoekverslag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;";
    $sql[] = "ALTER TABLE `project_bestanden` ADD CONSTRAINT `project_bestanden_ibfk_1` FOREIGN KEY (`verslag_id`) REFERENCES `bezoekverslag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;";

    return $sql;
}

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bezoekverslag App - Installatie</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 20px; }
        .container { max-width: 700px; margin: 20px auto; background: #fff; padding: 20px 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1, h2 { color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        h1 { font-size: 24px; }
        h2 { font-size: 20px; margin-top: 30px; }
        .step-indicator { text-align: center; margin-bottom: 20px; }
        .step-indicator span { display: inline-block; padding: 10px 20px; background: #eee; border-radius: 4px; margin: 0 5px; color: #888; }
        .step-indicator span.active { background: #007bff; color: #fff; }
        .check-list { list-style: none; padding: 0; }
        .check-list li { padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        .check-list li:before { content: '\2714'; color: #28a745; margin-right: 10px; }
        .check-list li.fail:before { content: '\2718'; color: #dc3545; margin-right: 10px; }
        .check-list code { background: #e9ecef; padding: 2px 5px; border-radius: 3px; }
        .btn { display: inline-block; background: #007bff; color: #fff; padding: 12px 25px; border: none; border-radius: 5px; text-decoration: none; font-size: 16px; cursor: pointer; transition: background 0.3s; }
        .btn:hover { background: #0056b3; }
        .btn.disabled { background: #ccc; cursor: not-allowed; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .text-center { text-align: center; }
        .mt-20 { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Bezoekverslag App Installatie</h1>

        <?php if ($step === '1'): ?>
            <div class="step-indicator">
                <span class="active">Stap 1: Systeemcheck</span>
                <span>Stap 2: Configuratie</span>
                <span>Stap 3: Voltooid</span>
            </div>
            <h2>Welkom!</h2>
            <p>Deze wizard helpt je bij het installeren van de Bezoekverslag App. Voordat we beginnen, controleren we of je server aan de vereisten voldoet.</p>

            <?php
                $phpVersionOk = check_php_version($requiredPhpVersion);
                $missingExtensions = check_extensions($requiredExtensions);
                $unwritablePaths = check_paths($writablePaths);
                $allOk = $phpVersionOk && empty($missingExtensions) && empty($unwritablePaths);
            ?>

            <h2>Serververeisten</h2>
            <ul class="check-list">
                <li class="<?= $phpVersionOk ? '' : 'fail' ?>">PHP versie <?= $requiredPhpVersion ?> of hoger (huidig: <?= PHP_VERSION ?>)</li>
                <?php foreach ($requiredExtensions as $ext):
                    $isMissing = in_array($ext, $missingExtensions);
                ?>
                    <li class="<?= $isMissing ? 'fail' : '' ?>">PHP extensie: <code><?= $ext ?></code></li>
                <?php endforeach; ?>
            </ul>

            <?php if (!empty($missingExtensions)):
            ?>
                <div class="alert alert-danger">De volgende PHP extensies zijn vereist maar niet gevonden: <?= implode(', ', $missingExtensions) ?>.</div>
            <?php endif; ?>

            <h2>Bestandsrechten</h2>
            <ul class="check-list">
                <?php foreach ($writablePaths as $path):
                    $isUnwritable = in_array($path, $unwritablePaths);
                ?>
                    <li class="<?= $isUnwritable ? 'fail' : '' ?>">Schrijfbaar: <code><?= $path ?></code></li>
                <?php endforeach; ?>
            </ul>

            <?php if (!empty($unwritablePaths)):
            ?>
                <div class="alert alert-danger">De volgende mappen/bestanden zijn niet schrijfbaar. Zorg ervoor dat de webserver schrijfrechten heeft.</div>
            <?php endif; ?>

            <div class="text-center mt-20">
                <?php if ($allOk):
                ?>
                    <a href="?step=2" class="btn">Volgende stap</a>
                <?php else:
                ?>
                    <a href="#" class="btn disabled">Volgende stap</a>
                    <p class="mt-20">Los de bovenstaande problemen op en herlaad de pagina om verder te gaan.</p>
                <?php endif; ?>
            </div>

        <?php elseif ($step === '2'): ?>
            <div class="step-indicator">
                <span>Stap 1: Systeemcheck</span>
                <span class="active">Stap 2: Configuratie</span>
                <span>Stap 3: Voltooid</span>
            </div>
            <h2>Database & Administrator</h2>
            <p>Vul de gegevens in om de database te configureren en de eerste administrator-account aan te maken.</p>

            <?php if ($error_message):
            ?>
                <div class="alert alert-danger"><?= $error_message ?></div>
            <?php endif; ?>

            <form action="?step=2" method="POST">
                <h2>Database Instellingen</h2>
                <div class="form-group">
                    <label for="db_host">Database Host</label>
                    <input type="text" id="db_host" name="db_host" value="localhost" required>
                </div>
                <div class="form-group">
                    <label for="db_name">Database Naam</label>
                    <input type="text" id="db_name" name="db_name" required>
                </div>
                <div class="form-group">
                    <label for="db_user">Database Gebruiker</label>
                    <input type="text" id="db_user" name="db_user" required>
                </div>
                <div class="form-group">
                    <label for="db_pass">Database Wachtwoord</label>
                    <input type="password" id="db_pass" name="db_pass">
                </div>

                <h2>Administrator Account</h2>
                <div class="form-group">
                    <label for="admin_fullname">Volledige Naam</label>
                    <input type="text" id="admin_fullname" name="admin_fullname" required>
                </div>
                <div class="form-group">
                    <label for="admin_email">E-mailadres</label>
                    <input type="email" id="admin_email" name="admin_email" required>
                </div>
                <div class="form-group">
                    <label for="admin_password">Wachtwoord (min. 8 tekens)</label>
                    <input type="password" id="admin_password" name="admin_password" required>
                </div>

                <div class="text-center mt-20">
                    <button type="submit" class="btn">Installeer nu</button>
                </div>
            </form>

        <?php elseif ($step === '3'): ?>
            <div class="step-indicator">
                <span>Stap 1: Systeemcheck</span>
                <span>Stap 2: Configuratie</span>
                <span class="active">Stap 3: Voltooid</span>
            </div>
            <h2>Installatie Voltooid!</h2>
            
            <?php if ($success_message):
            ?>
                <div class="alert alert-success"><?= $success_message ?></div>
            <?php endif; ?>

            <div class="alert alert-warning">
                <strong>BELANGRIJK:</strong> Verwijder het bestand <code>install.php</code> uit de root van je project om veiligheidsredenen!
            </div>

            <p>Je kunt nu inloggen op de applicatie met de administrator-account die je zojuist hebt aangemaakt.</p>
            <div class="text-center mt-20">
                <a href="public/?page=login" class="btn">Naar de Login Pagina</a>
            </div>

        <?php elseif ($step === 'already_installed'): ?>
            <h2>Al Geïnstalleerd</h2>
            <div class="alert alert-warning">
                De applicatie lijkt al geïnstalleerd te zijn omdat er een <code>.env</code> bestand is gevonden.
                <br><br>
                Om de installatie opnieuw uit te voeren, moet je handmatig het <code>.env</code> bestand uit de root van het project verwijderen. Let op: dit zal je huidige configuratie verwijderen.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

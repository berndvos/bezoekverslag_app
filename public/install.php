<?php

/**
 * ==================================================================
 * Installer voor de Bezoekverslag App
 * ==================================================================
 * Dit script helpt bij het opzetten van de applicatie.
 * 1. Controleert of de app al geïnstalleerd is.
 * 2. Vraagt om database- en admin-gegevens.
 * 3. Maakt het .env configuratiebestand aan.
 * 4. Maakt de databasetabellen aan.
 * 5. Maakt de eerste admin gebruiker aan.
 * 6. Maakt een lock-bestand aan om herinstallatie te voorkomen.
 *
 * BELANGRIJK: Verwijder dit bestand na een succesvolle installatie!
 * ==================================================================
 */


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$basePath = __DIR__ . '/../';
$envFile = $basePath . '.env';
$lockFile = $basePath . 'storage/install.lock';
$schemaFile = $basePath . 'config/schema.sql';
$errors = [];
$successMessage = '';

// --- Stap 1: Controleer of de installatie al is uitgevoerd ---
if (file_exists($lockFile) || file_exists($envFile)) {
    die("<div style='font-family: sans-serif; padding: 20px; background-color: #ffdddd; border: 1px solid #ffaaaa;'>
            <h2>Installatie geblokkeerd</h2>
            <p>De applicatie lijkt al geïnstalleerd te zijn omdat een <code>.env</code> of <code>install.lock</code> bestand is gevonden.</p>
            <p>Om opnieuw te installeren, moet u handmatig de volgende bestanden verwijderen:</p>
            <ul>
                <li><code>.env</code></li>
                <li><code>storage/install.lock</code></li>
            </ul>
            <p><strong>Let op:</strong> Herinstallatie kan leiden tot dataverlies.</p>
        </div>");
}

// --- Stap 2: Verwerk het formulier ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Gegevens ophalen en valideren
    $db_host = trim($_POST['db_host'] ?? 'localhost');
    $db_name = trim($_POST['db_name'] ?? '');
    $db_user = trim($_POST['db_user'] ?? '');
    $db_pass = trim($_POST['db_pass'] ?? '');

    $admin_fullname = trim($_POST['admin_fullname'] ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $admin_password = $_POST['admin_password'] ?? '';

    if (empty($db_host) || empty($db_name) || empty($db_user)) {
        $errors[] = 'Databasegegevens zijn onvolledig.';
    }
    if (empty($admin_fullname) || !filter_var($admin_email, FILTER_VALIDATE_EMAIL) || strlen($admin_password) < 8) {
        $errors[] = 'Admin-gegevens zijn onvolledig of het wachtwoord is te kort (min. 8 tekens).';
    }

    if (empty($errors)) {
        try {
            // --- Stap 3: Maak .env bestand ---
            $envContent = <<<EOT
APP_ENV=development

# Database Settings
DB_HOST={$db_host}
DB_NAME={$db_name}
DB_USER={$db_user}
DB_PASS={$db_pass}

# SMTP Settings
SMTP_HOST=
SMTP_PORT=587
SMTP_USER=
SMTP_PASS=
SMTP_ENCRYPTION=tls
SMTP_FROM_ADDRESS=
SMTP_FROM_NAME="Bezoekverslag App"
EOT;
            if (file_put_contents($envFile, $envContent) === false) {
                throw new Exception('Kon het .env bestand niet aanmaken. Controleer de schrijfrechten in de hoofdmap.');
            }

            // --- Stap 4: Maak databasetabellen aan ---
            $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
            $pdo = new PDO($dsn, $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            if (!file_exists($schemaFile)) {
                throw new Exception('Bestand `config/schema.sql` niet gevonden.');
            }
            $sql = file_get_contents($schemaFile);
            $pdo->exec($sql);

            // --- Stap 5: Maak admin gebruiker aan ---
            $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password, role, created_at) VALUES (?, ?, ?, 'admin', NOW())");
            $stmt->execute([$admin_fullname, $admin_email, $password_hash]);

            // --- Stap 6: Maak lock-bestand aan ---
            if (!is_dir($basePath . 'storage')) {
                mkdir($basePath . 'storage', 0775, true);
            }
            file_put_contents($lockFile, date('Y-m-d H:i:s'));

            $successMessage = "Installatie succesvol voltooid!";

        } catch (PDOException $e) {
            $errors[] = "Databasefout: " . $e->getMessage() . ". Controleer of de database '{$db_name}' bestaat en of de inloggegevens correct zijn.";
            // Verwijder het .env bestand bij een fout
            if (file_exists($envFile)) {
                unlink($envFile);
            }
        } catch (Exception $e) {
            $errors[] = "Algemene fout: " . $e->getMessage();
            if (file_exists($envFile)) {
                unlink($envFile);
            }
        }
    }
}

?>
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Installatie - Bezoekverslag App</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #f8f9fa; }
    .container { max-width: 700px; }
    .card { margin-top: 2rem; }
  </style>
</head>
<body>

<div class="container">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h3 class="mb-0">Installatie Bezoekverslag App</h3>
        </div>
        <div class="card-body p-4">

            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success">
                    <h4><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($successMessage) ?></h4>
                    <p>De applicatie is nu klaar voor gebruik. U kunt inloggen met de zojuist aangemaakte admin-gegevens.</p>
                    <hr>
                    <p class="fw-bold text-danger">BELANGRIJK: Verwijder nu het bestand <code>public/install.php</code> van uw server om veiligheidsredenen!</p>
                    <a href="index.php?page=login" class="btn btn-primary mt-3">Naar de loginpagina</a>
                </div>
            <?php else: ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <strong>Installatie mislukt:</strong>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <p>Welkom! Vul de onderstaande gegevens in om de applicatie te installeren. Zorg ervoor dat u een lege database heeft aangemaakt voordat u begint.</p>

                <form method="post">
                    <h5 class="mt-4 border-bottom pb-2 mb-3">Database Instellingen</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Database Host</label>
                            <input type="text" name="db_host" class="form-control" value="localhost" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Database Naam</label>
                            <input type="text" name="db_name" class="form-control" placeholder="bezoekverslag_db" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Database Gebruiker</label>
                            <input type="text" name="db_user" class="form-control" placeholder="root" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Database Wachtwoord</label>
                            <input type="password" name="db_pass" class="form-control">
                        </div>
                    </div>

                    <h5 class="mt-5 border-bottom pb-2 mb-3">Admin Account Aanmaken</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Volledige Naam</label>
                            <input type="text" name="admin_fullname" class="form-control" placeholder="Admin Gebruiker" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">E-mailadres</label>
                            <input type="email" name="admin_email" class="form-control" placeholder="admin@example.com" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Wachtwoord</label>
                            <input type="password" name="admin_password" class="form-control" required minlength="8">
                            <small class="form-text text-muted">Minimaal 8 tekens.</small>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Installeer Applicatie</button>
                    </div>
                </form>

            <?php endif; ?>

        </div>
    </div>
    <footer class="py-3 my-4">
        <p class="text-center text-muted">© <?= date('Y') ?> Bezoekverslag App</p>
    </footer>
</div>

</body>
</html>
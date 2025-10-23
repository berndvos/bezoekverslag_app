<?php
// app/views/layout/header.php

// Haal de branding-instellingen op
$brandingConfig = require __DIR__ . '/../../../config/branding.php';
$primaryColor = $brandingConfig['primary_color'] ?? '#FFD200';
$primaryColorContrast = $brandingConfig['primary_color_contrast'] ?? '#111111';
$logoPath = $brandingConfig['logo_path'] ?? '';

// Genereer de custom CSS voor de huisstijl
$customBrandingStyles = "
  :root {
    --yld-primary: {$primaryColor};
    --yld-primary-contrast: {$primaryColorContrast};
  }
";
?>
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
  <title>Bezoekverslag App</title>
  
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <!-- Custom CSS -->
  <link rel="stylesheet" href="css/style.css">
  
  <!-- Custom Branding -->
  <style>
    <?= $customBrandingStyles ?>
  </style>
  <script>
    window.getCsrfToken = function () {
      const meta = document.querySelector('meta[name="csrf-token"]');
      return meta ? meta.getAttribute('content') : '';
    };
  </script>
</head>
<body>

<main class="d-flex flex-column min-vh-100">
<?php if (isset($_SESSION['original_user'])): ?>
  <div class="text-center p-2" role="alert" style="
    position: sticky; 
    top: 0; 
    z-index: 1050; 
    background-color: #ffffff; 
    color: #1a2e47; 
    font-family: 'D-DIN', sans-serif; 
    font-weight: bold; 
    border-bottom: 1px solid #dee2e6;">
    <i class="bi bi-person-fill-gear"></i>
    U bent ingelogd als <strong><?= htmlspecialchars($_SESSION['fullname']) ?></strong>.
    <a href="<?= csrf_url('?page=admin_stop_impersonate') ?>" class="alert-link ms-3">Terugkeren naar uw eigen account</a>.
  </div>
<?php endif; ?>

  <nav class="navbar navbar-expand-lg yld-navbar shadow-sm">
    <div class="container">
      <a class="navbar-brand" href="?page=dashboard">
        <?php if (!empty($logoPath) && file_exists(__DIR__ . '/../../../public/' . $logoPath)): ?>
            <img src="<?= htmlspecialchars($logoPath) ?>" alt="Logo" style="max-height: 40px; width: auto;">
        <?php else: ?>
            Bezoekverslag App
        <?php endif; ?>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
          <?php if (isset($_SESSION['user_id'])): ?>
            <li class="nav-item"><a class="nav-link" href="?page=dashboard">Dashboard</a></li>
            <?php if (isAdmin()): ?>
              <li class="nav-item"><a class="nav-link" href="?page=admin">Admin</a></li>
            <?php endif; ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['fullname']) ?>
              </a>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="?page=profile">Mijn profiel</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="?page=logout">Uitloggen</a></li>
              </ul>
            </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>

  <?php if (isset($_SESSION['flash_message']) && $_SESSION['flash_message']): ?>
    <div class="container mt-3">
        <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_message']['type']) ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['flash_message']['text']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php $_SESSION['flash_message'] = null; ?>
  <?php endif; ?>

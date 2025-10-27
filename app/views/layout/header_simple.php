<?php
// app/views/layout/header_simple.php

$brandingConfig = \App\Config\Branding::get();
$primaryColor = $brandingConfig['primary_color'] ?? '#FFD200';
$primaryColorContrast = $brandingConfig['primary_color_contrast'] ?? '#111111';

$customBrandingStyles = ":root { --yld-primary: {$primaryColor}; --yld-primary-contrast: {$primaryColorContrast}; }";
?>
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
  <title>Bezoekverslag App</title>
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
  <link rel="stylesheet" href="css/style.css">
  
  <style>
    <?= $customBrandingStyles ?>
  </style>
  <script>
    window.getCsrfToken = function () {
        const meta = document.querySelector('meta[name=\"csrf-token\"]');
        return meta ? meta.getAttribute('content') : '';
    };
  </script>
</head>
<body>

<main class="d-flex flex-column min-vh-100">




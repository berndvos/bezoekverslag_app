<?php
// app/views/layout/header_simple.php

$brandingConfig = require __DIR__ . '/../../../config/branding.php';
$primaryColor = $brandingConfig['primary_color'] ?? '#FFD200';
$primaryColorContrast = $brandingConfig['primary_color_contrast'] ?? '#111111';

$customBrandingStyles = ":root { --yld-primary: {$primaryColor}; --yld-primary-contrast: {$primaryColorContrast}; }";
?>
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Bezoekverslag App</title>
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/style.css">
  
  <style>
    <?= $customBrandingStyles ?>
  </style>
</head>
<body>

<main class="d-flex flex-column min-vh-100">
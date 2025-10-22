<?php include 'layout/header.php'; ?>

<div class="container mt-4" style="max-width: 1200px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-journal-text"></i> Bezoekverslag: <?= htmlspecialchars($verslag['klantnaam'] ?? '-') ?></h2>
        <div>
            <a href="?page=submit&id=<?= $verslag['id'] ?>" class="btn btn-success">
                <i class="bi bi-file-earmark-pdf"></i> Indienen / PDF bekijken
            </a>
            <a href="?page=dashboard" class="btn btn-outline-secondary">Terug</a>
        </div>
    </div>

    <form method="POST" action="?page=update&id=<?= $verslag['id'] ?>" class="card p-4 shadow-sm mb-5">
        <!-- Relatiegegevens -->
        <h4 class="text-primary"><i class="bi bi-building"></i> Relatiegegevens</h4>
        <div class="row g-3 mt-1 mb-4">
            <div class="col-md-6">
                <label class="form-label">Klantnaam *</label>
                <input type="text" name="klantnaam" value="<?= htmlspecialchars($verslag['klantnaam'] ?? '') ?>" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Projecttitel</label>
                <input type="text" name="projecttitel" value="<?= htmlspecialchars($

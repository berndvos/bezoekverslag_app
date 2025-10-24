<?php include_once 'layout/header.php'; ?>

<div class="container mt-4" style="max-width: 900px;">
    <h2><i class="bi bi-plus-circle"></i> Nieuw bezoekverslag</h2>
    <p class="text-muted">Vul hieronder de basisinformatie in om een nieuw bezoekverslag aan te maken.</p>

    <form method="POST" action="?page=nieuw" class="card shadow-sm p-4 mt-3">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-12">
                <label class="form-label">Naam bezoekverslag <span class="text-danger">*</span></label>
                <input type="text" name="naam" class="form-control" placeholder="Bijv. Bezoek Gemeente Utrecht" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Klantnaam <span class="text-danger">*</span></label>
                <input type="text" name="klantnaam" class="form-control" placeholder="Bijv. Gemeente Utrecht" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Projecttitel</label>
                <input type="text" name="projecttitel" class="form-control" placeholder="Bijv. Raadszaal AV-installatie">
            </div>
        </div>

        <div class="mt-4 text-end">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> Verder
            </button>
            <a href="?page=dashboard" class="btn btn-outline-secondary">Annuleren</a>
        </div>
    </form>
</div>

<?php include_once 'layout/footer.php'; ?>


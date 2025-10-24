<h3>Nieuw bezoekverslag</h3>
<?php include_once 'layout/header.php'; ?>
<form method="post">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Naam bezoekverslag *</label>
      <input name="naam" class="form-control" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Klantnaam</label>
      <input name="klantnaam" class="form-control">
    </div>
    <div class="col-md-6">
      <label class="form-label">Projecttitel</label>
      <input name="projecttitel" class="form-control">
    </div>
  </div>
  <div class="mt-3">
    <button class="btn btn-primary">Opslaan</button>
    <a href="?page=dashboard" class="btn btn-outline-secondary">Annuleren</a>
  </div>
</form>
<?php include_once 'layout/footer.php'; ?>


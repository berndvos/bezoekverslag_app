<?php include 'layout/header_simple.php'; ?>

<div class="d-flex justify-content-center align-items-center" style="min-height:80vh;">
  <div class="card shadow-sm p-4 w-100" style="max-width:420px;">
    <h4 class="mb-3 text-center"><i class="bi bi-person-plus-fill"></i> Registreren</h4>

    <?php if (!empty($success)): ?>
      <div class="alert alert-success">
        <h5 class="alert-heading"><i class="bi bi-check-circle-fill me-2"></i>Registratie ontvangen!</h5>
        <p class="lead"><?= htmlspecialchars($success) ?></p>
        <p class="mb-0 mt-3"><strong>Belangrijk:</strong> Uw account is in afwachting van goedkeuring door een beheerder. U ontvangt een e-mail wanneer uw account is geactiveerd.</p>
        <hr class="my-3">
        <p class="mb-0 small text-muted">U kunt deze pagina nu sluiten.</p>
      </div>
    <?php else: ?>
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" action="?page=register" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label">Volledige naam</label>
          <input type="text" name="fullname" class="form-control" required placeholder="Uw volledige naam" value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">E-mailadres</label>
          <input type="email" name="email" class="form-control" required placeholder="naam@bedrijf.nl" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Wachtwoord</label>
          <input type="password" name="password" class="form-control" required placeholder="Minimaal 8 tekens">
        </div>
        <div class="mb-3">
          <label class="form-label">Bevestig Wachtwoord</label>
          <input type="password" name="password_confirm" class="form-control" required placeholder="Herhaal wachtwoord">
        </div>
        <button type="submit" class="btn btn-primary w-100 mb-2"><i class="bi bi-check-circle"></i> Registreren</button>
      </form>
    <?php endif; ?>
    <div class="text-center mt-2 small"><a href="?page=login" class="text-decoration-none">Terug naar inloggen</a></div>
  </div>
</div>

<?php include 'layout/footer.php'; ?>

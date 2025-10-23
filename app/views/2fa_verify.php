<?php include 'layout/header.php'; ?>

<div class="d-flex justify-content-center align-items-center" style="min-height:80vh;">
  <div class="card shadow-sm p-4 w-100" style="max-width:420px;">
    <h4 class="mb-3 text-center"><i class="bi bi-shield-lock"></i> Twee-staps Verificatie</h4>

    <div class="alert alert-info small">
      Er is een verificatiecode naar uw e-mailadres gestuurd. Voer deze code hieronder in om verder te gaan. De code is 15 minuten geldig.
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
        <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">Verificatiecode</label>
        <input type="text" name="2fa_code" class="form-control form-control-lg text-center" required autofocus
               inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code"
               placeholder="______">
      </div>

      <button type="submit" class="btn btn-primary w-100 mb-2">
        <i class="bi bi-check-circle"></i> Verifiëren en Inloggen
      </button>
    </form>

    <div class="text-center mt-2 small">
      <a href="?page=login" class="text-decoration-none">Terug naar inloggen</a>
    </div>
  </div>
</div>

<?php include 'layout/footer.php'; ?>

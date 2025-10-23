<?php include 'layout/header.php'; ?>

<div class="d-flex justify-content-center align-items-center" style="min-height:80vh;">
  <div class="card shadow-sm p-4 w-100" style="max-width:420px;">
    <h4 class="mb-3 text-center">Wachtwoord vergeten</h4>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php elseif (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">E-mailadres</label>
        <input type="email" name="email" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">Resetlink versturen</button>
    </form>

    <div class="text-center mt-3 small">
      <a href="?page=login" class="text-decoration-none">Terug naar inloggen</a>
    </div>
  </div>
</div>

<?php include 'layout/footer.php'; ?>

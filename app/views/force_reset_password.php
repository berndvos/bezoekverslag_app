<?php include 'layout/header_simple.php'; // Een header zonder navigatiemenu ?>

<div class="d-flex justify-content-center align-items-center" style="min-height:80vh;">
  <div class="card shadow-sm p-4 w-100" style="max-width:420px;">
    <h4 class="mb-3 text-center">Wachtwoord instellen</h4>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="mb-3">
        <label class="form-label">Nieuw wachtwoord</label>
        <input type="password" name="password" class="form-control" required minlength="8" autofocus>
        <small class="text-muted">Minimaal 8 tekens.</small>
      </div>
      <div class="mb-3">
        <label class="form-label">Herhaal nieuw wachtwoord</label>
        <input type="password" name="password_repeat" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">Wachtwoord opslaan en doorgaan</button>
    </form>

    <div class="text-center mt-3 small">
      <a href="?page=logout" class="text-decoration-none">Uitloggen</a>
    </div>
  </div>
</div>

<?php include 'layout/footer.php'; ?>
<?php include_once 'layout/header.php'; ?>

<div class="d-flex justify-content-center align-items-center" style="min-height:80vh;">
  <div class="card shadow-sm p-4 w-100" style="max-width:420px;">
    <h4 class="mb-3 text-center"><i class="bi bi-person-circle"></i> Inloggen</h4>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">E-mailadres</label>
        <input type="email" name="email" class="form-control" required placeholder="naam@bedrijf.nl">
      </div>

      <div class="mb-3">
        <label class="form-label">Wachtwoord</label>
        <input type="password" name="password" class="form-control" required placeholder="â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢">
      </div>

      <div class="form-check mb-3">
        <input type="checkbox" class="form-check-input" name="remember" id="remember">
        <label class="form-check-label" for="remember">Onthoud mij</label>
      </div>

      <button type="submit" class="btn btn-primary w-100 mb-2">
        <i class="bi bi-box-arrow-in-right"></i> Inloggen
      </button>
    </form>

    <div class="text-center mt-2">
      <a href="?page=forgot" class="small text-decoration-none">Wachtwoord vergeten?</a>
      <p class="mt-3">Nog geen account? <a href="?page=register">Meld je hier aan</a></p>
    </div>
  </div>
</div>

<?php include_once 'layout/footer.php'; ?>


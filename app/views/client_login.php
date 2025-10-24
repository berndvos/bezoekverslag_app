<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Klantportaal Login</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background-color: #f8f9fa; display: flex; align-items: center; justify-content: center; height: 100vh; }
  .login-card { max-width: 400px; width: 100%; }
</style>
</head>
<body>

<div class="card shadow-sm login-card">
  <div class="card-body p-4">
    <h3 class="card-title text-center mb-4">Klantportaal</h3>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label for="email" class="form-label">E-mailadres</label>
        <input type="email" class="form-control" id="email" name="email" required autofocus>
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Wachtwoord</label>
        <input type="password" class="form-control" id="password" name="password" required>
      </div>
      <div class="d-grid">
        <button type="submit" class="btn btn-primary">Inloggen</button>
      </div>
    </form>
  </div>
</div>

</body>
</html>

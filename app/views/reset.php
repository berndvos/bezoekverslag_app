<?php include_once 'layout/header.php'; ?>

<div class="container" style="max-width: 500px; margin-top: 5rem;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h3 class="card-title text-center mb-4">Wachtwoord opnieuw instellen</h3>

            <?php if (isset($msg)): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($msg) ?>
                    <a href="?page=login" class="alert-link">Klik hier om in te loggen.</a>
                </div>
            <?php else: ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token'] ?? '') ?>">
                    <div class="mb-3">
                        <label for="password" class="form-label">Nieuw wachtwoord</label>
                        <input type="password" class="form-control" id="password" name="password" required minlength="8">
                    </div>
                    <div class="mb-3">
                        <label for="password_repeat" class="form-label">Herhaal nieuw wachtwoord</label>
                        <input type="password" class="form-control" id="password_repeat" name="password_repeat" required minlength="8">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Wachtwoord instellen</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once 'layout/footer.php'; ?>


<?php include 'layout/header.php'; ?>

<div class="container mt-4" style="max-width: 1100px;">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-people"></i> Gebruikersbeheer</h2>
    <a href="?page=dashboard" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Terug naar dashboard</a>
  </div>

  <p class="text-muted">Alleen admins kunnen gebruikers aanmaken, bewerken of verwijderen.</p>

  <!-- Gebruikerslijst -->
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-primary text-white"><i class="bi bi-list"></i> Bestaande gebruikers</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>E-mailadres</th>
              <th>Naam</th>
              <th>Rol</th>
              <th>Aangemaakt</th>
              <th>Laatst actief</th>
              <th class="text-center" style="width:220px;">Acties</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($users)): ?>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                  <td><?= htmlspecialchars($u['fullname'] ?? '-') ?></td>
                  <td><?= htmlspecialchars(ucfirst($u['role']) ?? '-') ?></td>
                  <td><?= !empty($u['created_at']) ? date('d-m-Y', strtotime($u['created_at'])) : '-' ?></td>
                  <td><?= htmlspecialchars($u['last_login'] ?? '-') ?></td>
                  <td class="text-center">
                    <!-- Bewerken knop -->
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $u['id'] ?>">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <!-- Verwijderen knop -->
                    <?php if (($u['id'] ?? 0) !== ($_SESSION['user_id'] ?? -1)): ?>
                      <a href="?page=admin_delete_user&id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-danger"
                         onclick="return confirm('Weet je zeker dat je deze gebruiker wilt verwijderen?')">
                        <i class="bi bi-trash"></i>
                      </a>
                    <?php endif; ?>
                  </td>
                </tr>

                <!-- Modal voor bewerken -->
                <div class="modal fade" id="editUserModal<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                      <form method="post">
                        <div class="modal-header bg-primary text-white">
                          <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Gebruiker bewerken</h5>
                          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                          <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                          <div class="mb-3">
                            <label class="form-label">Volledige naam</label>
                            <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($u['fullname']) ?>" required>
                          </div>
                          <div class="mb-3">
                            <label class="form-label">E-mailadres</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($u['email']) ?>" required>
                          </div>
                          <div class="mb-3">
                            <label class="form-label">Rol</label>
                            <select name="role" class="form-select">
                              <option value="viewer" <?= $u['role'] === 'viewer' ? 'selected' : '' ?>>Viewer</option>
                              <option value="accountmanager" <?= $u['role'] === 'accountmanager' ? 'selected' : '' ?>>Accountmanager</option>
                              <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                          </div>
                          <div class="mb-3">
                            <label class="form-label">Nieuw wachtwoord (optioneel)</label>
                            <input type="password" name="new_password" class="form-control" placeholder="Laat leeg om niet te wijzigen" minlength="8">
                          </div>
                          <div class="mb-3">
                            <label class="form-label">Herhaal nieuw wachtwoord</label>
                            <input type="password" name="new_password_repeat" class="form-control" placeholder="Herhaal wachtwoord">
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                          <button type="submit" name="update_user" class="btn btn-primary">Opslaan</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="6" class="text-center py-4">Geen gebruikers gevonden.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Nieuwe gebruiker -->
  <div class="card shadow-sm mb-5">
    <div class="card-header bg-success text-white"><i class="bi bi-person-plus"></i> Nieuwe gebruiker aanmaken</div>
    <div class="card-body">
      <form method="post">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Volledige naam</label>
            <input type="text" name="fullname" class="form-control" placeholder="Bijv. Jan Jansen" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">E-mailadres</label>
            <input type="email" name="email" class="form-control" placeholder="naam@bedrijf.nl" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Wachtwoord</label>
            <input type="password" name="password" class="form-control" placeholder="Minimaal 8 tekens" minlength="8" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Herhaal wachtwoord</label>
            <input type="password" name="password_repeat" class="form-control" placeholder="Herhaal wachtwoord" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Rol</label>
            <select name="role" class="form-select" required>
              <option value="">Kies rol...</option>
              <option value="viewer">Viewer (alleen lezen/download)</option>
              <option value="accountmanager">Accountmanager</option>
              <option value="admin">Admin</option>
            </select>
          </div>
        </div>
        <div class="text-end mt-4">
          <button type="submit" name="create_user" class="btn btn-success">
            <i class="bi bi-person-plus"></i> Gebruiker toevoegen
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include 'layout/footer.php'; ?>

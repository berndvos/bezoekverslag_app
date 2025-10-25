<?php include_once 'layout/header.php'; ?>

<div class="container mt-4" style="max-width: 1000px;">
    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-person-circle"></i> Mijn Profiel</h4>
            <a href="?page=dashboard" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Terug naar dashboard</a>
        </div>
        <div class="card-body p-4">
            <div class="row g-5">
                <!-- Profielgegevens -->
                <div class="col-md-6">
                    <h5>Gegevens</h5>
                    <form id="profile-form" method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="update_profile" value="1">

                        <div class="mb-3">
                            <label for="fullname" class="form-label">Volledige naam</label>
                            <input type="text" class="form-control" id="fullname" name="fullname" value="<?= htmlspecialchars($user['fullname']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">E-mailadres</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Gegevens opslaan</button>
                    </form>
                </div>

                <!-- Wachtwoord wijzigen -->
                <div class="col-md-6">
                    <h5>Wachtwoord wijzigen</h5>
                    <form id="password-form" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="change_password" value="1">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Huidig wachtwoord</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Nieuw wachtwoord</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                        </div>
                        <div class="mb-3">
                            <label for="new_password_repeat" class="form-label">Herhaal nieuw wachtwoord</label>
                            <input type="password" class="form-control" id="new_password_repeat" name="new_password_repeat" required minlength="8">
                        </div>
                        <button type="submit" class="btn btn-secondary"><i class="bi bi-key-fill"></i> Wachtwoord wijzigen</button>
                    </form>
                </div>
            </div>

            <hr class="my-5">

            <!-- Mijn Bezoekverslagen -->
            <div>
                <h5 class="mb-3"><i class="bi bi-journal-text"></i> Mijn Bezoekverslagen</h5>
                <?php if (empty($mijnVerslagen)): ?>
                    <p class="text-muted">U heeft nog geen bezoekverslagen aangemaakt.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Projecttitel</th>
                                    <th>Klantnaam</th>
                                    <th>Aangemaakt op</th>
                                    <th class="text-center">PDF Status</th>
                                    <th>Acties</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mijnVerslagen as $verslag): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($verslag['projecttitel']) ?></td>
                                        <td><?= htmlspecialchars($verslag['klantnaam']) ?></td>
                                        <td><?= date('d-m-Y', strtotime($verslag['created_at'])) ?></td>
                                        <td class="text-center">
                                            <?php if (!empty($verslag['pdf_version'])): ?>
                                                <span class="badge bg-<?= !empty($verslag['pdf_up_to_date']) ? 'success' : 'danger' ?>">
                                                    V<?= (int)$verslag['pdf_version'] ?>
                                                </span>
                                            <?php else: echo '-'; endif; ?>
                                        </td>
                                        <td><a href="?page=bewerk&id=<?= $verslag['id'] ?>" class="btn btn-sm btn-outline-primary">Bewerken</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <hr class="my-5">

            <!-- Samenwerkingen -->
            <div>
                <h5 class="mb-3"><i class="bi bi-people"></i> Samenwerkingen</h5>
                <?php if (empty($samenwerkingen)): ?>
                    <p class="text-muted">U bent niet als collaborator toegevoegd aan andere verslagen.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Projecttitel</th>
                                    <th>Klantnaam</th>
                                    <th>Eigenaar</th>
                                    <th>Acties</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($samenwerkingen as $verslag): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($verslag['projecttitel']) ?></td>
                                        <td><?= htmlspecialchars($verslag['klantnaam']) ?></td>
                                        <td><?= htmlspecialchars($verslag['owner_name']) ?></td>
                                        <td><a href="?page=bewerk&id=<?= $verslag['id'] ?>" class="btn btn-sm btn-outline-primary">Bewerken</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <hr class="my-5">

            <!-- Actieve Klantportalen -->
            <div>
                <h5 class="mb-3"><i class="bi bi-person-badge"></i> Actieve Klantportalen van mijn projecten</h5>
                <?php if (empty($clientPortals)): ?>
                    <div class="alert alert-info">Er zijn geen actieve klantportalen voor uw projecten.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Project</th>
                                    <th>Klant</th>
                                    <th>Contactpersoon</th>
                                    <th>Verloopt op</th>
                                    <th class="text-center">Acties</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clientPortals as $portal): ?>
                                    <tr>
                                        <td><a href="?page=bewerk&id=<?= $portal['verslag_id'] ?>"><?= htmlspecialchars($portal['projecttitel']) ?></a></td>
                                        <td><?= htmlspecialchars($portal['klantnaam']) ?></td>
                                        <td><?= htmlspecialchars($portal['cp_email']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $portal['is_expired'] ? 'danger' : 'light text-dark' ?>">
                                                <?= $portal['is_expired'] ? 'Verlopen' : htmlspecialchars(date('d-m-Y', strtotime($portal['expires_at']))) ?>
                                            </span>
                                        </td>
                                        <td class="text-center d-flex justify-content-center gap-1">
                                        <a href="<?= csrf_url('?page=admin_reset_client_password&id=' . (int)$portal['verslag_id']) ?>" class="btn btn-sm btn-outline-warning" title="Wachtwoord resetten" aria-label="Reset klantwachtwoord voor verslag <?= (int)$portal['verslag_id'] ?>" onclick="return confirm('Weet je zeker dat je het wachtwoord voor deze klant wilt resetten?')"><i class="bi bi-key-fill"></i></a>
                                            <a href="<?= csrf_url('?page=admin_extend_client&id=' . (int)$portal['verslag_id']) ?>" class="btn btn-sm btn-outline-success" title="Verleng met 14 dagen" aria-label="Verleng klantportaal voor verslag <?= (int)$portal['verslag_id'] ?> met 14 dagen"><i class="bi bi-calendar-plus"></i></a>
                                            <a href="<?= csrf_url('?page=admin_revoke_client&id=' . (int)$portal['verslag_id']) ?>" class="btn btn-sm btn-outline-danger" title="Toegang intrekken" aria-label="Trek klanttoegang in voor verslag <?= (int)$portal['verslag_id'] ?>" onclick="return confirm('Weet je zeker dat je de toegang voor deze klant wilt intrekken?')"><i class="bi bi-x-circle"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<!-- Toast en AJAX script hier (niet getoond voor beknoptheid, maar kan gekopieerd worden van admin.php) -->

<?php include_once 'layout/footer.php'; ?>


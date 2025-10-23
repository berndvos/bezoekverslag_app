<?php include __DIR__ . '/layout/header.php'; ?>

<div class="container mt-4">
    <h1 class="mb-3"><i class="bi bi-grid-1x2"></i> Dashboard</h1>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5>Overzicht van bezoekverslagen</h5>
        <a href="?page=nieuw" class="btn btn-primary">
            <i class="fa fa-plus"></i> Nieuw verslag
        </a>
    </div>

    <!-- Zoekformulier -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="get" action="">
                <input type="hidden" name="page" value="dashboard">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Zoek op klantnaam of projecttitel..." value="<?= htmlspecialchars($search ?? '') ?>">
                    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i> Zoeken</button>
                    <?php if (!empty($search)): ?>
                        <a href="?page=dashboard" class="btn btn-outline-danger" title="Filter wissen"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($bezoekverslagen)): ?>
        <div class="alert alert-info">Er zijn nog geen bezoekverslagen aangemaakt.</div>
    <?php else: ?>
        <table class="table table-striped table-bordered">
            <thead class="table-light">
                <tr>
                    <th>Klantnaam</th>
                    <th>Projecttitel</th>
                    <th>Aangemaakt door</th>
                    <th>Door</th>
                    <th class="text-center">PDF Versie</th>
                    <th>PDF Datum</th>
                    <th style="width: 200px;">Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bezoekverslagen as $v): ?>
                    <tr>
                        <td><?= htmlspecialchars($v['klantnaam'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($v['projecttitel'] ?? '-') ?></td>
                        <td>
                            <?php if ($v['is_owner']): ?>
                                <span class="badge bg-success-subtle text-success-emphasis rounded-pill">Eigenaar</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill">Collaborator</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($v['created_by_name'] ?? 'Onbekend') ?></td>
                        <td class="text-center">
                            <?php if (!empty($v['pdf_version'])): ?>
                                <?php if (!empty($v['pdf_up_to_date'])): ?>
                                    <span class="badge bg-success" title="De PDF is up-to-date">V<?= (int)$v['pdf_version'] ?></span>
                                <?php else: ?>
                                    <span class="badge bg-danger" title="De PDF bevat niet de laatste wijzigingen. Genereer een nieuwe PDF.">
                                        <i class="bi bi-exclamation-triangle-fill"></i> V<?= (int)$v['pdf_version'] ?> Verouderd
                                    </span>
                                <?php endif; ?>
                            <?php else: echo '-'; endif; ?>
                        </td>
                        <td><?= !empty($v['pdf_generated_at']) ? date('d-m-Y H:i', strtotime($v['pdf_generated_at'])) : '-' ?></td>
                        <td class="text-nowrap">
                            <?php if (isAdmin() || $v['created_by'] == $_SESSION['user_id']): ?>
                                <a href="?page=bewerk&id=<?= $v['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i> Bewerken
                                </a>
                            <?php endif; ?>
                            <a href="?page=submit&id=<?= $v['id'] ?>" class="btn btn-sm btn-outline-success" target="_blank" title="PDF openen">
                                <i class="bi bi-file-earmark-pdf"></i> PDF
                            </a>
                            <?php if (($v['photo_count'] ?? 0) > 0): ?>
                                <a href="?page=download_photos&id=<?= $v['id'] ?>" class="btn btn-sm btn-outline-info" title="Download alle foto's (<?= $v['photo_count'] ?>)">
                                    <i class="bi bi-images"></i> Foto's
                                </a>
                            <?php else: ?>
                                <a href="#" class="btn btn-sm btn-outline-secondary disabled" title="Geen foto's beschikbaar om te downloaden">
                                    <i class="bi bi-images"></i> Foto's
                                </a>
                            <?php endif; ?>
                            <?php if (isAdmin()): ?>
                                <a href="<?= csrf_url('?page=delete_verslag&id=' . (int)$v['id']) ?>" class="btn btn-sm btn-outline-danger" title="Verplaats naar prullenbak" onclick="return confirm('Weet je zeker dat je dit verslag naar de prullenbak wilt verplaatsen?')">
                                <i class="bi bi-trash"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if (in_array($_SESSION['role'], ['admin', 'accountmanager'])): ?>
<div class="container mt-5">
    <h5 class="mb-3"><i class="bi bi-person-badge"></i> Actieve Klantportalen <?= !isAdmin() ? 'van mijn projecten' : '' ?></h5>
    <?php if (empty($clientPortals)): ?>
        <div class="alert alert-info">Er zijn geen actieve klantportalen.</div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0 align-middle">
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
                                        <a href="<?= csrf_url('?page=admin_reset_client_password&id=' . (int)$portal['verslag_id']) ?>" class="btn btn-sm btn-outline-warning" title="Wachtwoord resetten" onclick="return confirm('Weet je zeker dat je het wachtwoord voor deze klant wilt resetten?')"><i class="bi bi-key-fill"></i></a>
                                        <a href="<?= csrf_url('?page=admin_extend_client&id=' . (int)$portal['verslag_id']) ?>" class="btn btn-sm btn-outline-success" title="Verleng met 14 dagen"><i class="bi bi-calendar-plus"></i></a>
                                        <a href="<?= csrf_url('?page=admin_revoke_client&id=' . (int)$portal['verslag_id']) ?>" class="btn btn-sm btn-outline-danger" title="Toegang intrekken" onclick="return confirm('Weet je zeker dat je de toegang voor deze klant wilt intrekken?')"><i class="bi bi-x-circle"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/layout/footer.php'; ?>

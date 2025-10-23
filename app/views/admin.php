<?php include 'layout/header.php'; ?>

<style>
  /* Zorgt ervoor dat de ankerpunten niet achter de header vallen */
  .section-anchor { scroll-margin-top: 90px; }
</style>

<div class="container">
  <div class="row">
    <!-- Linker menu -->
    <div class="col-md-3 col-lg-2">
      <div id="sideMenu" class="position-sticky" style="top: 80px;">
        <div class="card shadow-sm">
          <div class="card-header bg-light fw-semibold">Admin Menu</div>
          <div class="list-group list-group-flush">
            <a href="#gebruikers" class="list-group-item list-group-item-action active">Gebruikers</a>
            <a href="#registraties" class="list-group-item list-group-item-action">Registraties <span class="badge bg-warning float-end"><?= count($pendingUsers) ?></span></a>
            <a href="#nieuw_gebruiker" class="list-group-item list-group-item-action">Nieuwe gebruiker</a>
            <a href="#prullenbak" class="list-group-item list-group-item-action">Prullenbak <span class="badge bg-secondary float-end"><?= count($deletedVerslagen) ?></span></a>
            <a href="#klantportalen" class="list-group-item list-group-item-action">Klantportalen</a>
            <a href="#onderhoud" class="list-group-item list-group-item-action">Onderhoud</a>
            <a href="#logboek" class="list-group-item list-group-item-action">Logboek</a>
            <a href="#systeem" class="list-group-item list-group-item-action">Systeemstatus</a>
            <a href="#updates" class="list-group-item list-group-item-action">Updates</a>
            <a href="#smtp" class="list-group-item list-group-item-action">SMTP Instellingen</a>
            <a href="#email_templates" class="list-group-item list-group-item-action">E-mail Sjablonen</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Rechter content -->
    <div class="col-md-9 col-lg-10">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-gear-wide-connected"></i> Admin Panel</h2>
        <a href="?page=dashboard" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Terug naar dashboard</a>
      </div>

      <?php if (isAdmin()): // Deze tekst is alleen voor admins relevant ?>
      <p class="text-muted">Alleen admins kunnen gebruikers aanmaken, bewerken of verwijderen.</p>
      <?php endif; ?>

      <!-- Gebruikerslijst -->
      <div id="gebruikers" class="section-anchor card shadow-sm mb-4">
        <div class="card-header bg-primary text-white"><i class="bi bi-people"></i> Gebruikers</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th>E-mailadres</th>
                  <th>Naam</th>
                  <th>Rol</th>
                  <th>Aangemaakt</th>
                  <th>Status</th>
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
                      <td>
                        <span class="badge bg-<?= $u['status'] === 'active' ? 'success' : 'secondary' ?>"><?= htmlspecialchars(ucfirst($u['status'])) ?></span>
                      </td>
                      <td><?= !empty($u['created_at']) ? date('d-m-Y', strtotime($u['created_at'])) : '-' ?></td>
                      <td><?= htmlspecialchars($u['last_login'] ?? '-') ?></td>
                      <td class="text-center">
                        <!-- Bewerken knop -->
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $u['id'] ?>">
                          <i class="bi bi-pencil"></i>
                        </button>
                        <!-- Overnemen knop -->
                        <?php if (isAdmin() && ($u['id'] ?? 0) !== ($_SESSION['user_id'] ?? -1)): ?>
                          <a href="?page=admin_impersonate&id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-warning" title="Login overnemen">
                            <i class="bi bi-person-fill-gear"></i>
                          </a>
                        <?php endif; ?>
                        <!-- Wachtwoord reset knop -->
                        <?php if (isAdmin() && ($u['id'] ?? 0) !== ($_SESSION['user_id'] ?? -1)): ?>
                          <a href="?page=admin_reset_password&id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Stuur wachtwoord reset" onclick="return confirm('Weet je zeker dat je een wachtwoord-reset mail wilt sturen naar deze gebruiker?')">
                            <i class="bi bi-envelope-at"></i>
                          </a>
                        <?php endif; ?>
                        <!-- Verwijderen knop -->
                        <?php if (isAdmin() && ($u['id'] ?? 0) !== ($_SESSION['user_id'] ?? -1)): ?>
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
                                  <option value="poweruser" <?= $u['role'] === 'poweruser' ? 'selected' : '' ?>>Poweruser</option>
                                  <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                  <option value="accountmanager" <?= $u['role'] === 'accountmanager' ? 'selected' : '' ?>>Accountmanager</option>
                                  <option value="viewer" <?= $u['role'] === 'viewer' ? 'selected' : '' ?>>Viewer</option>
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
                          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuleren</button>
                              <button type="submit" name="update_user" class="btn btn-primary">Opslaan</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="7" class="text-center py-4">Geen actieve gebruikers gevonden.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Nieuwe Registraties -->
      <div id="registraties" class="section-anchor card shadow-sm mb-4">
        <div class="card-header bg-warning text-dark"><i class="bi bi-person-plus-fill"></i> Nieuwe Registraties in afwachting</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th>E-mailadres</th>
                  <th>Naam</th>
                  <th>Geregistreerd op</th>
                  <th class="text-center">Acties</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($pendingUsers)): ?>
                  <?php foreach ($pendingUsers as $pUser): ?>
                    <tr>
                      <td><?= htmlspecialchars($pUser['email']) ?></td>
                      <td><?= htmlspecialchars($pUser['fullname']) ?></td>
                      <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($pUser['created_at']))) ?></td>
                      <td class="text-center">
                        <form method="post" class="d-inline">
                          <input type="hidden" name="manage_registration" value="1">
                          <input type="hidden" name="user_id" value="<?= $pUser['id'] ?>">
                          <button type="submit" name="action" value="approve" class="btn btn-sm btn-success" title="Goedkeuren"><i class="bi bi-check-lg"></i></button>
                          <button type="submit" name="action" value="deny" class="btn btn-sm btn-danger" title="Afwijzen" onclick="return confirm('Weet je zeker dat je deze registratie wilt afwijzen en verwijderen?')"><i class="bi bi-x-lg"></i></button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="4" class="text-center p-3">Geen nieuwe registraties.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <?php if (!empty($pendingUsers)): ?>
          <div class="card-footer small text-muted">Gebruikers krijgen na goedkeuring automatisch de rol 'Viewer'. U kunt hun rol aanpassen in de gebruikerslijst hierboven.</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Nieuwe gebruiker -->
      <div id="nieuw_gebruiker" class="section-anchor card shadow-sm mb-5">
        <div class="card-header bg-primary text-white"><i class="bi bi-person-plus"></i> Nieuwe gebruiker aanmaken</div>
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
                <label class="form-label">Rol</label>
                <select name="role" class="form-select" required>
                  <option value="">Kies rol...</option>
                  <option value="poweruser">Poweruser</option>
                  <option value="admin">Admin</option>
                  <option value="accountmanager">Accountmanager</option>
                  <option value="viewer">Viewer</option>
                </select>
              </div>
            </div>
            <div class="text-end mt-4">
              <button type="submit" name="create_user" class="btn btn-primary">
                <i class="bi bi-person-plus"></i> Gebruiker toevoegen
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Prullenbak -->
      <div id="prullenbak" class="section-anchor card shadow-sm mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
          <span><i class="bi bi-trash"></i> Prullenbak</span>
          <?php if (!empty($deletedVerslagen)): ?>
            <a href="?page=admin_empty_trash" class="btn btn-sm btn-outline-light" onclick="return confirm('Weet je zeker dat je alle verslagen ouder dan 30 dagen permanent wilt verwijderen? Deze actie kan niet ongedaan worden gemaakt.')">
              <i class="bi bi-trash2-fill"></i> Leeg items ouder dan 30 dagen
            </a>
          <?php endif; ?>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th>Project</th>
                  <th>Klant</th>
                  <th>Verwijderd op</th>
                  <th class="text-center">Acties</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($deletedVerslagen)): ?>
                  <?php foreach ($deletedVerslagen as $verslag): ?>
                    <tr class="<?= $verslag['is_older_than_30_days'] ? 'table-danger' : '' ?>">
                      <td><?= htmlspecialchars($verslag['projecttitel']) ?></td>
                      <td><?= htmlspecialchars($verslag['klantnaam']) ?></td>
                      <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($verslag['deleted_at']))) ?></td>
                      <td class="text-center d-flex justify-content-center gap-1">
                        <a href="?page=admin_restore_verslag&id=<?= $verslag['id'] ?>" class="btn btn-sm btn-outline-success" title="Herstellen"><i class="bi bi-arrow-counterclockwise"></i></a>
                        <a href="?page=admin_permanent_delete_verslag&id=<?= $verslag['id'] ?>" class="btn btn-sm btn-outline-danger" title="Permanent verwijderen" onclick="return confirm('Weet je zeker dat je dit verslag permanent wilt verwijderen? Alle bijbehorende ruimtes en foto\'s worden ook verwijderd. Deze actie kan niet ongedaan worden gemaakt.')"><i class="bi bi-x-octagon-fill"></i></a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="4" class="text-center p-3">De prullenbak is leeg.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Klantportalen -->
      <div id="klantportalen" class="section-anchor card shadow-sm mb-4">
        <div class="card-header bg-primary text-white"><i class="bi bi-person-badge"></i> Actieve Klantportalen</div>
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
                <?php if (!empty($clientPortals)): ?>
                  <?php foreach ($clientPortals as $portal): ?>
                    <tr>
                      <td><a href="?page=bewerk&id=<?= $portal['verslag_id'] ?>"><?= htmlspecialchars($portal['projecttitel']) ?></a></td>
                      <td><?= htmlspecialchars($portal['klantnaam']) ?></td>
                      <td><?= htmlspecialchars($portal['cp_email']) ?></td>
                      <td>
                        <?php if ($portal['is_expired']): ?>
                          <span class="badge bg-danger">Verlopen</span>
                        <?php else: ?>
                          <?= htmlspecialchars(date('d-m-Y', strtotime($portal['expires_at']))) ?>
                        <?php endif; ?>
                      </td>
                      <td class="text-center d-flex justify-content-center gap-1">
                        <a href="?page=admin_reset_client_password&id=<?= $portal['verslag_id'] ?>" class="btn btn-sm btn-outline-warning" title="Wachtwoord resetten" onclick="return confirm('Weet je zeker dat je het wachtwoord voor deze klant wilt resetten?')"><i class="bi bi-key-fill"></i></a>
                        <a href="?page=admin_extend_client&id=<?= $portal['verslag_id'] ?>" class="btn btn-sm btn-outline-success" title="Verleng met 14 dagen"><i class="bi bi-calendar-plus"></i></a>
                        <a href="?page=admin_revoke_client&id=<?= $portal['verslag_id'] ?>" class="btn btn-sm btn-outline-danger" title="Toegang intrekken" onclick="return confirm('Weet je zeker dat je de toegang voor deze klant wilt intrekken?')"><i class="bi bi-x-circle"></i></a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="5" class="text-center p-3">Geen actieve klantportalen gevonden.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Onderhoud -->
      <div id="onderhoud" class="section-anchor card shadow-sm mb-4">
        <div class="card-header bg-primary text-white"><i class="bi bi-tools"></i> Onderhoud & Huisstijl</div>
        <div class="card-body">
          <div class="row g-4">
            <!-- Database Backup -->
            <div class="col-lg-6">
              <h6>Database Back-up</h6>
              <p class="text-muted small">Download een .sql-bestand van de volledige database.</p>
              <a href="?page=admin_backup_db" class="btn btn-secondary"><i class="bi bi-database-down"></i> Back-up downloaden</a>
            </div>
            <!-- Logo Upload -->
            <div class="col-lg-6">
              <h6>Logo uploaden</h6>
              <p class="text-muted small">Upload een bedrijfslogo (.png, .jpg, .svg) voor in de header en PDF's.</p>
              <form method="post" enctype="multipart/form-data">
                <div class="input-group">
                  <input type="file" name="company_logo" class="form-control" accept="image/png, image/jpeg, image/svg+xml">
                  <button class="btn btn-secondary" type="submit" name="upload_logo"><i class="bi bi-upload"></i> Uploaden</button>
                </div>
                <?php if (!empty($brandingSettings['logo_path'])): ?>
                  <div class="mt-2"><img src="<?= htmlspecialchars($brandingSettings['logo_path']) ?>" alt="Huidig logo" style="max-height: 40px; background: #f1f1f1; padding: 5px; border-radius: 4px;"></div>
                <?php endif; ?>
              </form>
            </div>
            <!-- Kleurinstellingen -->
            <div class="col-lg-12 mt-4 pt-4 border-top">
                <h6>Huisstijlkleuren</h6>
                <p class="text-muted small">Pas de primaire kleur van de applicatie aan. Kies ook een contrasterende tekstkleur (donker of licht) voor op de knoppen.</p>
                <form method="post" id="branding-form">
                    <input type="hidden" name="update_branding" value="1">
                    <div class="row align-items-end">
                        <div class="col-auto">
                            <label class="form-label">Primaire kleur</label>
                            <input type="color" name="primary_color" class="form-control form-control-color" value="<?= htmlspecialchars($brandingSettings['primary_color'] ?? '#FFD200') ?>">
                        </div>
                        <div class="col-auto">
                            <label class="form-label">Tekstkleur op knoppen</label>
                            <input type="color" name="primary_color_contrast" class="form-control form-control-color" value="<?= htmlspecialchars($brandingSettings['primary_color_contrast'] ?? '#111111') ?>">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-palette"></i> Kleuren opslaan</button>
                        </div>
                    </div>
                </form>
            </div>
          </div>
        </div>
      </div>

      <!-- Logboek -->
      <div id="logboek" class="section-anchor card shadow-sm mb-4">
        <div class="card-header bg-primary text-white"><i class="bi bi-clock-history"></i> Logboek (laatste 100 acties)</div>
        <div class="card-body p-0">
          <div class="table-responsive" style="max-height: 400px;">
            <table class="table table-sm table-striped mb-0 small">
              <thead class="table-light">
                <tr>
                  <th>Datum</th>
                  <th>Gebruiker</th>
                  <th>Actie</th>
                  <th>Details</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($logEntries)): ?>
                  <?php foreach ($logEntries as $log): ?>
                    <tr>
                      <td class="text-nowrap"><?= htmlspecialchars(date('d-m-Y H:i', strtotime($log['created_at']))) ?></td>
                      <td><?= htmlspecialchars($log['user_fullname']) ?></td>
                      <td><span class="badge bg-light text-dark"><?= htmlspecialchars($log['action']) ?></span></td>
                      <td><?= htmlspecialchars($log['details']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="4" class="text-center p-3">Logboek is leeg.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Systeemstatus -->
      <div id="systeem" class="section-anchor card shadow-sm mb-4">
        <div class="card-header bg-primary text-white"><i class="bi bi-hdd-stack"></i> Systeemstatus</div>
        <div class="card-body">
          <ul class="list-group">
            <li class="list-group-item d-flex justify-content-between align-items-center">
              Databaseverbinding
              <span class="badge bg-<?= $systemStatus['db_connection'] ? 'success' : 'danger' ?>"><?= $systemStatus['db_connection'] ? 'OK' : 'Mislukt' ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              Schrijfrechten <code>/storage</code> map
              <span class="badge bg-<?= $systemStatus['storage_writable'] ? 'success' : 'danger' ?>"><?= $systemStatus['storage_writable'] ? 'OK' : 'Mislukt' ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              Schrijfrechten <code>/public/uploads</code> map
              <span class="badge bg-<?= $systemStatus['uploads_writable'] ? 'success' : 'danger' ?>"><?= $systemStatus['uploads_writable'] ? 'OK' : 'Mislukt' ?></span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Updates -->
      <div id="updates" class="section-anchor card shadow-sm mb-4">
        <div class="card-header bg-primary text-white"><i class="bi bi-cloud-arrow-down"></i> Applicatie Updates</div>
        <div class="card-body">
            <div id="update-checker">
                <p>Controleer of er een nieuwe versie van de applicatie beschikbaar is via GitHub.</p>
                <button id="check-for-updates" class="btn btn-secondary"><i class="bi bi-arrow-repeat"></i> Controleren op updates</button>
            </div>
            <div id="update-result" class="mt-3" style="display: none;"></div>
        </div>
      </div>

      <!-- SMTP Instellingen -->
      <div id="smtp" class="section-anchor card shadow-sm mb-5">
        <div class="card-header bg-primary text-white"><i class="bi bi-envelope-at"></i> SMTP Instellingen</div>
        <div class="card-body">
          <p class="text-muted small">Deze instellingen worden gelezen uit het <code>.env</code> bestand. Om ze te wijzigen, moet u dat bestand aanpassen. Opslaan via deze interface is uitgeschakeld voor de veiligheid.</p>
          <form id="smtp-form">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">SMTP Host</label>
                <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars($smtpSettings['host'] ?? 'smtp.strato.de') ?>" placeholder="smtp.strato.de">
              </div>
              <div class="col-md-3">
                <label class="form-label">SMTP Port</label>
                <input type="number" name="smtp_port" class="form-control" value="<?= htmlspecialchars($smtpSettings['port'] ?? '465') ?>" placeholder="465">
              </div>
              <div class="col-md-3">
                <label class="form-label">Encryptie</label>
                <select name="smtp_encryption" class="form-select">
                    <option value="" <?= empty($smtpSettings['encryption']) ? 'selected' : '' ?>>Geen</option>
                    <option value="tls" <?= (($smtpSettings['encryption'] ?? '') === 'tls') ? 'selected' : '' ?>>TLS</option>
                    <option value="ssl" <?= (($smtpSettings['encryption'] ?? 'ssl') === 'ssl') ? 'selected' : '' ?>>SSL</option>
                </select>
              </div>
              <div class="col-md-6 mt-3">
                <label class="form-label">Gebruikersnaam</label>
                <input type="text" name="smtp_username" class="form-control" value="<?= htmlspecialchars($smtpSettings['username'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Wachtwoord</label>
                <input type="password" name="smtp_password" class="form-control" placeholder="Laat leeg om niet te wijzigen">
              </div>
              <div class="col-md-6 mt-3">
                <label class="form-label">Afzender e-mail</label>
                <input type="email" name="smtp_from_address" class="form-control" value="<?= htmlspecialchars($smtpSettings['from_address'] ?? '') ?>">
              </div>
              <div class="col-md-6 mt-3">
                <label class="form-label">Afzender naam</label>
                <input type="text" name="smtp_from_name" class="form-control" value="<?= htmlspecialchars($smtpSettings['from_name'] ?? '') ?>">
              </div>
            </div>
            <div class="text-end mt-4 d-flex justify-content-end gap-2">
                <a href="?page=admin_test_smtp" class="btn btn-outline-secondary" onclick="return confirm('Let op: de testmail wordt verstuurd met de laatst OPGESLAGEN instellingen. Weet je het zeker?')">
                    <i class="bi bi-send"></i> Testmail versturen
                </a>
                <button type="submit" name="update_smtp" value="1" class="btn btn-primary" disabled>
                    <i class="bi bi-save"></i> SMTP Opslaan
                </button>
            </div>
          </form>
        </div>
      </div>

      <!-- E-mail Sjablonen -->
      <div id="email_templates" class="section-anchor card shadow-sm mb-5">
        <div class="card-header bg-primary text-white"><i class="bi bi-envelope-paper"></i> E-mail Sjablonen</div>
        <div class="card-body">
          <form method="post" id="email-templates-form">
            <p class="text-muted small">Pas hier de teksten aan voor de e-mails die automatisch worden verstuurd. Gebruik de variabelen (bijv. <code>{user_fullname}</code>) om dynamische informatie in te voegen.</p>

            <div class="accordion" id="emailTemplateAccordion">
              <!-- Wachtwoord reset -->
              <div class="accordion-item">
                <h2 class="accordion-header" id="headingOne">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">Wachtwoord reset (voor gebruikers)</button>
                </h2>
                <div id="collapseOne" class="accordion-collapse collapse" data-bs-parent="#emailTemplateAccordion">
                  <div class="accordion-body">
                    <div class="mb-3"><label class="form-label small">Onderwerp</label><input type="text" name="password_reset_subject" class="form-control form-control-sm" value="<?= htmlspecialchars($emailTemplates['password_reset']['subject'] ?? '') ?>"></div>
                    <div class="mb-2"><label class="form-label small">Inhoud</label><textarea name="password_reset_body" class="form-control tinymce-editor" rows="6"><?= htmlspecialchars($emailTemplates['password_reset']['body'] ?? '') ?></textarea><small class="text-muted">Variabelen: <code>{user_fullname}</code>, <code>{reset_link}</code></small></div>
                  </div>
                </div>
              </div>

              <!-- Notificatie na update door klant -->
              <div class="accordion-item">
                <h2 class="accordion-header" id="headingTwo">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">Notificatie na update door klant</button>
                </h2>
                <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#emailTemplateAccordion">
                  <div class="accordion-body">
                    <div class="mb-3"><label class="form-label small">Onderwerp</label><input type="text" name="client_update_subject" class="form-control form-control-sm" value="<?= htmlspecialchars($emailTemplates['client_update']['subject'] ?? '') ?>"></div>
                    <div class="mb-2"><label class="form-label small">Inhoud</label><textarea name="client_update_body" class="form-control tinymce-editor" rows="5"><?= htmlspecialchars($emailTemplates['client_update']['body'] ?? '') ?></textarea><small class="text-muted">Variabelen: <code>{am_fullname}</code>, <code>{klantnaam}</code>, <code>{project_title}</code></small></div>
                  </div>
                </div>
              </div>

              <!-- Nieuwe klantlogin -->
              <div class="accordion-item">
                <h2 class="accordion-header" id="headingThree">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">Nieuwe klantlogin aangemaakt</button>
                </h2>
                <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#emailTemplateAccordion">
                  <div class="accordion-body">
                    <div class="mb-3"><label class="form-label small">Onderwerp</label><input type="text" name="new_client_login_subject" class="form-control form-control-sm" value="<?= htmlspecialchars($emailTemplates['new_client_login']['subject'] ?? '') ?>"></div>
                    <div class="mb-2"><label class="form-label small">Inhoud</label><textarea name="new_client_login_body" class="form-control tinymce-editor" rows="8"><?= htmlspecialchars($emailTemplates['new_client_login']['body'] ?? '') ?></textarea><small class="text-muted">Variabelen: <code>{client_name}</code>, <code>{project_title}</code>, <code>{login_email}</code>, <code>{login_password}</code>, <code>{login_link}</code>, <code>{am_name}</code></small></div>
                  </div>
                </div>
              </div>

              <!-- Klantportaal verlengd -->
              <div class="accordion-item">
                <h2 class="accordion-header" id="headingFour">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour">Klantportaal verlengd</button>
                </h2>
                <div id="collapseFour" class="accordion-collapse collapse" data-bs-parent="#emailTemplateAccordion">
                  <div class="accordion-body">
                    <div class="mb-3"><label class="form-label small">Onderwerp</label><input type="text" name="client_portal_extended_subject" class="form-control form-control-sm" value="<?= htmlspecialchars($emailTemplates['client_portal_extended']['subject'] ?? '') ?>"></div>
                    <div class="mb-2"><label class="form-label small">Inhoud</label><textarea name="client_portal_extended_body" class="form-control tinymce-editor" rows="4"><?= htmlspecialchars($emailTemplates['client_portal_extended']['body'] ?? '') ?></textarea><small class="text-muted">Variabelen: <code>{client_name}</code>, <code>{project_title}</code>, <code>{login_link}</code>, <code>{expiry_date}</code></small></div>
                  </div>
                </div>
              </div>

              <!-- Nieuwe gebruiker aangemaakt -->
              <div class="accordion-item">
                <h2 class="accordion-header" id="headingFive">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive">Nieuwe gebruiker aangemaakt</button>
                </h2>
                <div id="collapseFive" class="accordion-collapse collapse" data-bs-parent="#emailTemplateAccordion">
                  <div class="accordion-body">
                    <div class="mb-3"><label class="form-label small">Onderwerp</label><input type="text" name="new_user_created_subject" class="form-control form-control-sm" value="<?= htmlspecialchars($emailTemplates['new_user_created']['subject'] ?? '') ?>"></div>
                    <div class="mb-2"><label class="form-label small">Inhoud</label><textarea name="new_user_created_body" class="form-control tinymce-editor" rows="8"><?= htmlspecialchars($emailTemplates['new_user_created']['body'] ?? '') ?></textarea><small class="text-muted">Variabelen: <code>{user_fullname}</code>, <code>{user_email}</code>, <code>{user_password}</code>, <code>{login_link}</code></small></div>
                  </div>
                </div>
              </div>

            </div>
            <div class="text-end mt-4">
              <button type="submit" name="update_email_templates" value="1" class="btn btn-primary"><i class="bi bi-save"></i> Sjablonen Opslaan</button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
// Scrollspy voor het zijmenu
document.addEventListener('DOMContentLoaded', () => {
  const links = document.querySelectorAll('#sideMenu a');
  const sections = Array.from(links).map(a => document.querySelector(a.getAttribute('href')));

  function onScroll() {
    const scrollPosition = window.scrollY + 100; // Offset voor header
    let activeIndex = 0;
    sections.forEach((sec, i) => { if (sec && sec.offsetTop <= scrollPosition) { activeIndex = i; } });
    links.forEach((link, i) => { link.classList.toggle('active', i === activeIndex); });
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll(); // Eerste check bij laden
});
</script>

<!-- TinyMCE Editor -->
<script src="https://cdn.tiny.cloud/1/1g4hajwtgqfgp0t1tzkbnz67qqjf98lz6h7jannzutwlnznp/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    tinymce.init({
      selector: 'textarea.tinymce-editor',
      plugins: 'lists link autolink',
      toolbar: 'undo redo | bold italic | bullist numlist | link',
      menubar: false,
      height: 250,
      content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 14px; }',
      setup: function (editor) {
        // Zorgt ervoor dat de AJAX-submit de laatste content uit de editor meeneemt
        editor.on('change', function () {
          editor.save();
        });
      }
    });
  });
</script>



<!-- Toast container voor AJAX meldingen -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="ajax-toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
      <strong class="me-auto" id="toast-title"></strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body" id="toast-body"></div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toastElement = document.getElementById('ajax-toast');
    const toast = new bootstrap.Toast(toastElement);

    function showToast(title, message, isSuccess) {
        document.getElementById('toast-title').textContent = title;
        document.getElementById('toast-body').textContent = message;
        toastElement.classList.remove('bg-success-subtle', 'bg-danger-subtle');
        toastElement.classList.add(isSuccess ? 'bg-success-subtle' : 'bg-danger-subtle');
        toast.show();
    }

    function handleFormSubmit(formId) {
        const form = document.getElementById(formId);
        if (!form) return;

        form.addEventListener('submit', function(event) {
            event.preventDefault();

            const saveButton = this.querySelector('button[type="submit"]');
            const originalButtonText = saveButton.innerHTML;
            saveButton.disabled = true;
            saveButton.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Opslaan...`;

            const formData = new FormData(this);

            fetch('?page=admin', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Succes', data.message, true);
                } else {
                    showToast('Fout', data.message, false);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Fout', 'Er is een onverwachte fout opgetreden.', false);
            })
            .finally(() => {
                saveButton.disabled = false;
                saveButton.innerHTML = originalButtonText;
            });
        });
    }

    // Koppel de functie aan alle formulieren die we via AJAX willen afhandelen
    handleFormSubmit('branding-form');
    handleFormSubmit('email-templates-form');
    // Het SMTP formulier is 'disabled' en hoeft niet afgehandeld te worden.
});
</script>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkUpdatesButton = document.getElementById('check-for-updates');
    const updateResultDiv = document.getElementById('update-result');

    if (checkUpdatesButton && updateResultDiv) {
        checkUpdatesButton.addEventListener('click', function() {
            this.disabled = true;
            const originalButtonText = this.innerHTML;
            this.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Controleren...`;
            updateResultDiv.style.display = 'block';
            updateResultDiv.innerHTML = '<p class="text-info"><i class="bi bi-info-circle"></i> Bezig met controleren op updates...</p>';

      fetch('?page=admin_check_updates', {
        method: 'GET',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
      .then(response => {
        // If the server didn't return JSON (or returned an empty body),
        // avoid calling response.json() directly to prevent "Unexpected end of JSON input".
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
          return response.text().then(text => {
            // Surface the raw server response as an error so the catch block can show it.
            throw new Error(text || ('HTTP ' + response.status));
          });
        }
        return response.json();
      })
      .then(data => {
                if (data.error) {
                    updateResultDiv.innerHTML = `<div class="alert alert-danger" role="alert"><strong>Fout:</strong> ${data.error}</div>`;
                    return;
                }

                let resultHtml = `
                    <p>Huidige versie: <strong>${data.current_version}</strong><br>
                       Laatste versie: <strong>${data.latest_version}</strong></p>
                `;

                if (data.update_available) {
                    resultHtml += `
                        <div class="alert alert-success">
                            <h5 class="alert-heading"><i class="bi bi-gift-fill"></i> Er is een update beschikbaar!</h5>
                            <p>Versie <strong>${data.release_info.version}</strong> (${data.release_info.name}) is beschikbaar.</p>
                            <hr>
                            <h6>Release notes:</h6>
                            <div class="small">${data.release_info.body.replace(/\r\n/g, '<br>')}</div>
                            <hr>
                            <p class="mt-3"><strong>Belangrijk:</strong> Maak een back-up van uw bestanden en database voordat u de update start. De updater probeert dit automatisch te doen, maar een handmatige back-up wordt aangeraden.</p>
                            <button id="perform-update-btn" class="btn btn-success">
                                <i class="bi bi-cloud-arrow-down-fill"></i> Nu updaten naar versie ${data.release_info.version}
                            </button>
                        </div>
                    `;
                } else {
                    resultHtml += `<div class="alert alert-info"><i class="bi bi-check-circle-fill"></i> U gebruikt de meest recente versie.</div>`;
                }
                updateResultDiv.innerHTML = resultHtml;
            })
            .catch(error => {
                console.error('Error checking for updates:', error);
                updateResultDiv.innerHTML = `<div class="alert alert-danger" role="alert">Fout bij het controleren op updates. Controleer de console voor details.</div>`;
            })
            .finally(() => {
                checkUpdatesButton.disabled = false;
                checkUpdatesButton.innerHTML = originalButtonText;

                // Voeg event listener toe aan de nieuwe update knop
                const performUpdateButton = document.getElementById('perform-update-btn');
                if (performUpdateButton) {
                    performUpdateButton.addEventListener('click', function() {
                        if (!confirm('Weet u zeker dat u de update wilt starten? De applicatie is tijdelijk niet beschikbaar.')) {
                            return;
                        }
                        this.disabled = true;
                        this.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Update wordt uitgevoerd... (dit kan enkele minuten duren)`;
                        updateResultDiv.innerHTML += '<div id="update-progress" class="alert alert-warning mt-3"><p>Update gestart. Sluit dit venster niet.</p><p>Stap 1/5: Back-up maken...</p></div>';

                        fetch('?page=admin_perform_update', { method: 'POST' })
                            .then(response => response.json())
                            .then(updateData => {
                                const progressDiv = document.getElementById('update-progress');
                                if (updateData.success) {
                                    progressDiv.classList.remove('alert-warning');
                                    progressDiv.classList.add('alert-success');
                                    progressDiv.innerHTML = `<p>${updateData.message}</p>`;
                                    setTimeout(() => window.location.reload(), 5000);
                                } else {
                                    progressDiv.classList.remove('alert-warning');
                                    progressDiv.classList.add('alert-danger');
                                    progressDiv.innerHTML = `<p><strong>Update mislukt:</strong> ${updateData.error}</p>`;
                                }
                            });
                    });
                }
            });
        });
    }
});
</script>
<?php include 'layout/footer.php'; ?>

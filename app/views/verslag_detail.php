<?php include_once 'layout/header.php'; ?>

<div class="container mt-4" style="max-width: 1200px;">
    <div class="row">
        <!-- Zijmenu -->
        <div class="col-md-3 col-lg-2">
            <div id="sideMenu" class="position-sticky" style="top: 80px; z-index: 1000;">
                <div class="list-group">
                    <a href="#relatie" class="list-group-item list-group-item-action active">Relatie</a>
                    <a href="#contact" class="list-group-item list-group-item-action">Contactpersoon</a>
                    <?php if (isAdmin()): ?><a href="#klantportaal" class="list-group-item list-group-item-action">Klantportaal</a><?php endif; ?>                    
                    <a href="#wensen" class="list-group-item list-group-item-action">Wensen</a>
                    <a href="#eisen" class="list-group-item list-group-item-action">Eisen</a>
                    <a href="#installatie" class="list-group-item list-group-item-action">Installatie</a>
                    <?php if ($isOwner): ?><a href="#samenwerking" class="list-group-item list-group-item-action">Samenwerking</a><?php endif; ?>
                    <a href="#ruimtes" class="list-group-item list-group-item-action">Ruimtes</a>
                    <a href="#projectbestanden" class="list-group-item list-group-item-action">Projectbestanden</a>
                </div>
            </div>
        </div>

    <div class="col-md-9 col-lg-10">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">        <h2 class="mb-0"><i class="bi bi-journal-text"></i> Bezoekverslag: <?= htmlspecialchars($verslag['klantnaam'] ?? '') ?></h2>
        <div>
          <a href="?page=download_project_files&id=<?= (int)($verslag['id'] ?? 0) ?>" class="btn btn-secondary"><i class="bi bi-paperclip"></i> Projectbestanden</a>          <a href="?page=download_photos&id=<?= (int)($verslag['id'] ?? 0) ?>" class="btn btn-info"><i class="bi bi-images"></i> Foto's</a>
          <a href="?page=submit&id=<?= (int)($verslag['id'] ?? 0) ?>" class="btn btn-success" target="_blank">            
            <i class="bi bi-file-earmark-pdf"></i> Indienen / PDF bekijken
          </a>
          <a href="?page=dashboard" class="btn btn-outline-secondary">Terug</a>
        </div>
      </div>
      <hr>

      <!-- RELATIE -->
      <form method="post" id="verslag-form" action="?page=bewerk&id=<?= (int)($verslag['id'] ?? 0) ?>">        
        <?= csrf_field() ?>
        <input type="hidden" name="update" value="1">
        <div id="relatie" class="card mb-4 shadow-sm section-anchor" data-form-group="relatie">
          <div class="card-header bg-primary text-white"><i class="bi bi-building"></i> Relatiegegevens</div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label for="klantnaam" class="form-label">Klantnaam *</label>
                <input id="klantnaam" type="text" name="klantnaam" class="form-control" required value="<?= htmlspecialchars($verslag['klantnaam'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label for="projecttitel" class="form-label">Projecttitel</label>
                <input id="projecttitel" type="text" name="projecttitel" class="form-control" value="<?= htmlspecialchars($verslag['projecttitel'] ?? '') ?>">
              </div>
              <div class="col-md-3">
                <label for="postcode" class="form-label">Postcode <span id="postcode-spinner-relatie" class="spinner-border spinner-border-sm text-primary ms-1" role="status" style="display: none;"></span></label>
                <input id="postcode" type="text" name="postcode" class="form-control postcode-lookup" 
                       value="<?= htmlspecialchars($verslag['postcode'] ?? '') ?>"
                       data-prefix="relatie"
                       placeholder="1234AB">
              </div>
              <div class="col-md-3">
                <label for="huisnummer" class="form-label">Huisnummer</label>
                <input id="huisnummer" type="text" name="huisnummer" class="form-control" value="<?= htmlspecialchars($verslag['huisnummer'] ?? '') ?>">
              </div>
              <div class="col-md-3">
                <label for="huisnummer_toevoeging" class="form-label">Toevoeging</label>
                <input id="huisnummer_toevoeging" type="text" name="huisnummer_toevoeging" class="form-control" value="<?= htmlspecialchars($verslag['huisnummer_toevoeging'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label for="straatnaam" class="form-label">Straatnaam</label>
                <input id="straatnaam" type="text" name="straatnaam" class="form-control" value="<?= htmlspecialchars($verslag['straatnaam'] ?? '') ?>">
              </div>
              <div class="col-md-3">
                <label for="plaats" class="form-label">Plaats</label>
                <input id="plaats" type="text" name="plaats" class="form-control" value="<?= htmlspecialchars($verslag['plaats'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label for="kvk" class="form-label">KvK-nummer</label>
                <input id="kvk" type="text" name="kvk" class="form-control" value="<?= htmlspecialchars($verslag['kvk'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label for="btw" class="form-label">BTW-nummer</label>
                <input id="btw" type="text" name="btw" class="form-control" value="<?= htmlspecialchars($verslag['btw'] ?? '') ?>">
              </div>
            </div>
            <div class="mt-3 text-end"><button type="submit" name="save_section" value="relatie" class="btn btn-primary"><i class="bi bi-save"></i> Opslaan</button></div>
          </div>
        </div>

      <!-- CONTACT -->
        <!-- Dit was een apart formulier, nu onderdeel van het hoofdformulier -->
        <div id="contact" class="card mb-4 shadow-sm section-anchor" data-form-group="contact">
          <div class="card-header bg-primary text-white"><i class="bi bi-person-lines-fill"></i> Contactpersoon</div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label for="contact_naam" class="form-label">Naam</label>
                <input id="contact_naam" type="text" name="contact_naam" class="form-control" value="<?= htmlspecialchars($verslag['contact_naam'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label for="contact_functie" class="form-label">Functie</label>
                <input id="contact_functie" type="text" name="contact_functie" class="form-control" value="<?= htmlspecialchars($verslag['contact_functie'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label for="contact_email" class="form-label">E-mailadres</label>
                <input id="contact_email" type="email" name="contact_email" class="form-control" value="<?= htmlspecialchars($verslag['contact_email'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label for="contact_tel" class="form-label">Telefoonnummer</label>
                <input id="contact_tel" type="text" name="contact_tel" class="form-control" value="<?= htmlspecialchars($verslag['contact_tel'] ?? '') ?>">
              </div>
            </div>
            <div class="mt-3 text-end"><button type="submit" name="save_section" value="contact" class="btn btn-primary"><i class="bi bi-save"></i> Opslaan</button></div>
          </div>
        </div>

      <!-- WENSEN -->
        <!-- Dit was een apart formulier, nu onderdeel van het hoofdformulier -->
        <div id="wensen" class="card mb-4 shadow-sm section-anchor" data-form-group="wensen">
          <div class="card-header bg-primary text-white"><i class="bi bi-list-check"></i> Wensen</div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                  <label for="gewenste_offertedatum" class="form-label">Gewenste offertedatum</label>
                  <?php
                    $today = date('Y-m-d');
                    $defaultOfferteDatum = date('Y-m-d', strtotime('+10 days'));
                    $offerteDatumValue = !empty($verslag['gewenste_offertedatum']) ? $verslag['gewenste_offertedatum'] : $defaultOfferteDatum;
                  ?>
                  <input id="gewenste_offertedatum" type="date" name="gewenste_offertedatum" class="form-control" value="<?= htmlspecialchars($offerteDatumValue) ?>" min="<?= $today ?>">
              </div>
              <div class="col-md-6">
                <label for="indicatief_budget" class="form-label">Indicatief budget</label>
                <input id="indicatief_budget" type="text" name="indicatief_budget" class="form-control" value="<?= htmlspecialchars($verslag['indicatief_budget'] ?? '') ?>" placeholder="Bijv. â‚¬ 5.000 - â‚¬ 10.000">
              </div>
              <!-- De velden 'situatie', 'functioneel', 'uitbreiding' worden nu beheerd via het 'Leveranciers' blok -->
              <input type="hidden" name="situatie" id="hidden_situatie" value="<?= htmlspecialchars($verslag['situatie'] ?? '') ?>">
              <input type="hidden" name="functioneel" id="hidden_functioneel" value="<?= htmlspecialchars($verslag['functioneel'] ?? '') ?>">
              <input type="hidden" name="uitbreiding" id="hidden_uitbreiding" value="<?= htmlspecialchars($verslag['uitbreiding'] ?? '') ?>">
              <div class="col-md-12"><label for="wensen_overige" class="form-label">Overige wensen</label><textarea name="wensen" id="wensen_overige" class="form-control" rows="3"><?= htmlspecialchars($verslag['wensen'] ?? '') ?></textarea></div>
            </div>
            <div class="mt-3 text-end"><button type="submit" name="save_section" value="wensen" class="btn btn-primary"><i class="bi bi-save"></i> Opslaan</button></div>
          </div>
        </div>

      <!-- EISEN -->
        <div id="eisen" class="card mb-4 shadow-sm section-anchor" data-form-group="eisen">
          <div class="card-header bg-primary text-white"><i class="bi bi-shield-check"></i> Eisen</div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6"><label for="beeldkwaliteitseisen" class="form-label">Beeldkwaliteit</label><input id="beeldkwaliteitseisen" type="text" name="beeldkwaliteitseisen" class="form-control" value="<?= htmlspecialchars($verslag['beeldkwaliteitseisen'] ?? '') ?>"></div>
              <div class="col-md-6"><label for="geluidseisen" class="form-label">Geluidseisen</label><input id="geluidseisen" type="text" name="geluidseisen" class="form-control" value="<?= htmlspecialchars($verslag['geluidseisen'] ?? '') ?>"></div>
              <div class="col-md-6"><label for="bedieningseisen" class="form-label">Bedieningseisen</label><input id="bedieningseisen" type="text" name="bedieningseisen" class="form-control" value="<?= htmlspecialchars($verslag['bedieningseisen'] ?? '') ?>"></div>
              <div class="col-md-6"><label for="beveiligingseisen" class="form-label">Beveiligingseisen</label><input id="beveiligingseisen" type="text" name="beveiligingseisen" class="form-control" value="<?= htmlspecialchars($verslag['beveiligingseisen'] ?? '') ?>"></div>
              <div class="col-md-6"><label for="netwerkeisen" class="form-label">Netwerkeisen</label><input id="netwerkeisen" type="text" name="netwerkeisen" class="form-control" value="<?= htmlspecialchars($verslag['netwerkeisen'] ?? '') ?>"></div>
              <div class="col-md-6"><label for="garantie" class="form-label">Garantie / onderhoud</label><input id="garantie" type="text" name="garantie" class="form-control" value="<?= htmlspecialchars($verslag['garantie'] ?? '') ?>"></div>
            </div>
            <div class="mt-3 text-end"><button type="submit" name="save_section" value="eisen" class="btn btn-primary"><i class="bi bi-save"></i> Opslaan</button></div>
          </div>
        </div>

      <!-- INSTALLATIE -->
        <div id="installatie" class="card mb-4 shadow-sm section-anchor" data-form-group="installatie">
          <div class="card-header bg-primary text-white"><i class="bi bi-gear"></i> Installatie</div>
          <div class="card-body">
            <div class="row g-3 mb-4">
              <div class="col-md-6"><label for="installatieAdresAfwijkend" class="form-label">Installatieadres afwijkend van relatieadres?</label><select name="installatie_adres_afwijkend" id="installatieAdresAfwijkend" class="form-select"><option value="Nee" <?= (($verslag['installatie_adres_afwijkend'] ?? 'Nee') === 'Nee') ? 'selected' : '' ?>>Nee</option><option value="Ja" <?= (($verslag['installatie_adres_afwijkend'] ?? '') === 'Ja') ? 'selected' : '' ?>>Ja</option></select></div>
            </div>
            <div id="afwijkendAdresContainer" class="row g-3 p-3 mb-4 border rounded bg-light" style="display: none;">
              <h6 class="mb-0 text-muted">Afwijkend installatieadres</h6>
              <div class="col-md-3">
                <label for="installatie_adres_postcode" class="form-label">Postcode <span id="postcode-spinner-installatie" class="spinner-border spinner-border-sm text-primary ms-1" role/="status" style="display: none;"></span></label>
                <input id="installatie_adres_postcode" type="text" name="installatie_adres_postcode" class="form-control postcode-lookup" 
                       value="<?= htmlspecialchars($verslag['installatie_adres_postcode'] ?? '') ?>" data-prefix="installatie" placeholder="1234AB">
              </div>
              <div class="col-md-3">
                  <label for="installatie_adres_huisnummer" class="form-label">Huisnummer</label>
                  <input id="installatie_adres_huisnummer" type="text" name="installatie_adres_huisnummer" class="form-control" value="<?= htmlspecialchars($verslag['installatie_adres_huisnummer'] ?? '') ?>">
              </div>
              <div class="col-md-3">
                  <label for="installatie_adres_huisnummer_toevoeging" class="form-label">Toevoeging</label>
                  <input id="installatie_adres_huisnummer_toevoeging" type="text" name="installatie_adres_huisnummer_toevoeging" class="form-control" value="<?= htmlspecialchars($verslag['installatie_adres_huisnummer_toevoeging'] ?? '') ?>">
              </div>
              <div class="col-md-6"><label for="installatie_adres_straat" class="form-label">Straatnaam</label><input id="installatie_adres_straat" type="text" name="installatie_adres_straat" class="form-control" value="<?= htmlspecialchars($verslag['installatie_adres_straat'] ?? '') ?>"></div>
              <div class="col-md-3"><label for="installatie_adres_plaats" class="form-label">Plaats</label><input id="installatie_adres_plaats" type="text" name="installatie_adres_plaats" class="form-control" value="<?= htmlspecialchars($verslag['installatie_adres_plaats'] ?? '') ?>"></div>
            </div>

            <div class="row g-3 mb-4">
              <div class="col-md-6"><label for="cpLocatieAfwijkend" class="form-label">Contactpersoon op locatie afwijkend?</label><select name="cp_locatie_afwijkend" id="cpLocatieAfwijkend" class="form-select"><option value="Nee" <?= (($verslag['cp_locatie_afwijkend'] ?? 'Nee') === 'Nee') ? 'selected' : '' ?>>Nee</option><option value="Ja" <?= (($verslag['cp_locatie_afwijkend'] ?? '') === 'Ja') ? 'selected' : '' ?>>Ja</option></select></div>
            </div>
            <div id="afwijkendeCpContainer" class="row g-3 p-3 mb-4 border rounded bg-light" style="display: none;">
              <h6 class="mb-0 text-muted">Contactpersoon op locatie</h6>
              <div class="col-md-6"><label for="cp_locatie_naam" class="form-label">Naam</label><input id="cp_locatie_naam" type="text" name="cp_locatie_naam" class="form-control" value="<?= htmlspecialchars($verslag['cp_locatie_naam'] ?? '') ?>"></div>
              <div class="col-md-6"><label for="cp_locatie_functie" class="form-label">Functie</label><input id="cp_locatie_functie" type="text" name="cp_locatie_functie" class="form-control" value="<?= htmlspecialchars($verslag['cp_locatie_functie'] ?? '') ?>"></div>
              <div class="col-md-6"><label for="cp_locatie_email" class="form-label">E-mailadres</label><input id="cp_locatie_email" type="email" name="cp_locatie_email" class="form-control" value="<?= htmlspecialchars($verslag['cp_locatie_email'] ?? '') ?>"></div>
              <div class="col-md-6"><label for="cp_locatie_tel" class="form-label">Telefoonnummer</label><input id="cp_locatie_tel" type="text" name="cp_locatie_tel" class="form-control" value="<?= htmlspecialchars($verslag['cp_locatie_tel'] ?? '') ?>"></div>
            </div>

            <hr>
            <h6 class="text-muted mt-4">Overige installatiegegevens</h6>
            <div class="row g-3 mt-1">
                <div class="col-md-6"><label for="afvoerSelect" class="form-label">Afvoer oude apparatuur</label><select name="afvoer" id="afvoerSelect" class="form-select"><option value="">Kies...</option><option value="Ja" <?= (($verslag['afvoer'] ?? '') === 'Ja') ? 'selected' : '' ?>>Ja</option><option value="Nee" <?= (($verslag['afvoer'] ?? '') === 'Nee') ? 'selected' : '' ?>>Nee</option></select></div>
                <div class="col-md-12" id="afvoerOmschrijvingContainer" style="display: none;">
                    <label for="afvoer_omschrijving" class="form-label">Omschrijving af te voeren apparatuur</label><textarea id="afvoer_omschrijving" name="afvoer_omschrijving" class="form-control" rows="2" placeholder="Bijv. 1x 55 inch TV, 2x speakers..."><?= htmlspecialchars($verslag['afvoer_omschrijving'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6"><label for="installatiedatum" class="form-label">Gewenste installatiedatum</label><input id="installatiedatum" type="date" name="installatiedatum" class="form-control" value="<?= htmlspecialchars($verslag['installatiedatum'] ?? '') ?>" min="<?= $today ?>"></div>
                <div class="col-md-6"><label for="locatie_apparatuur" class="form-label">Locatie apparatuur</label><input id="locatie_apparatuur" type="text" name="locatie_apparatuur" class="form-control" value="<?= htmlspecialchars($verslag['locatie_apparatuur'] ?? '') ?>"></div>
                <div class="col-md-6"><label for="aantal_installaties" class="form-label">Aantal installaties</label><input id="aantal_installaties" type="number" name="aantal_installaties" class="form-control" value="<?= htmlspecialchars($verslag['aantal_installaties'] ?? '') ?>"></div>
                <div class="col-md-6"><label for="parkeren" class="form-label">Parkeerrestricties</label><input id="parkeren" type="text" name="parkeren" class="form-control" value="<?= htmlspecialchars($verslag['parkeren'] ?? '') ?>"></div>
                <div class="col-md-6"><label for="toegang" class="form-label">Toegangsprocedures</label><input id="toegang" type="text" name="toegang" class="form-control" value="<?= htmlspecialchars($verslag['toegang'] ?? '') ?>"></div>
                <div class="col-md-6"><label for="boortijden" class="form-label">Boortijden / geluidsrestricties</label><input id="boortijden" type="text" name="boortijden" class="form-control" value="<?= htmlspecialchars($verslag['boortijden'] ?? '') ?>"></div>
                <div class="col-md-6"><label for="opleverdatum" class="form-label">Gewenste opleverdatum</label><input id="opleverdatum" type="date" name="opleverdatum" class="form-control" value="<?= htmlspecialchars($verslag['opleverdatum'] ?? '') ?>" min="<?= $today ?>"></div>
            </div>
            <div class="mt-3 text-end"><button type="submit" name="save_section" value="installatie" class="btn btn-primary"><i class="bi bi-save"></i> Opslaan</button></div>
          </div>
        </div>
      </form>

      <!-- SAMENWERKING -->
      <?php if ($isOwner): ?>
      <div id="samenwerking" class="card mb-4 shadow-sm section-anchor">
          <div class="card-header bg-primary text-white"><i class="bi bi-people-fill"></i> Samenwerking</div>
          <div class="card-body">
              <p class="text-muted small">Geef collega's toegang om dit verslag te bekijken en te bewerken. Zij zullen dit verslag ook op hun dashboard zien.</p>
              
              <!-- Bestaande collaborators -->
              <h6>Huidige collaborators</h6>
              <?php if (empty($collaborators)): ?>
                  <p>Er zijn nog geen collega's toegevoegd aan dit verslag.</p>
              <?php else: ?>
                  <ul class="list-group mb-4">
                      <?php foreach ($collaborators as $collaborator): ?>
                          <li class="list-group-item d-flex justify-content-between align-items-center">
                              <?= htmlspecialchars($collaborator['fullname']) ?> (<?= htmlspecialchars($collaborator['email']) ?>)
                              <form method="post" class="collaboration-form d-inline">
                                <?= csrf_field() ?>
                                  <input type="hidden" name="manage_collaboration" value="1">
                                  <input type="hidden" name="collaborator_id" value="<?= $collaborator['id'] ?>">
                                  <button type="submit" name="remove_collaborator" class="btn btn-sm btn-outline-danger" title="Verwijder toegang">
                                      <i class="bi bi-trash"></i>
                                  </button>
                              </form>
                          </li>
                      <?php endforeach; ?>
                  </ul>
              <?php endif; ?>

              <!-- Nieuwe collaborator toevoegen -->
              <hr>
              <h6 class="mt-4">Collega toevoegen</h6>
      <form method="post" class="collaboration-form">
        <?= csrf_field() ?>
                  <input type="hidden" name="manage_collaboration" value="1">
                  <div class="input-group">
                      <select name="collaborator_id" class="form-select" <?= empty($colleagues) ? 'disabled' : '' ?>>
                          <option value="">Kies een collega...</option>
                          <?php foreach ($colleagues as $colleague): ?>
                              <option value="<?= $colleague['id'] ?>"><?= htmlspecialchars($colleague['fullname']) ?></option>
                          <?php endforeach; ?>
                      </select>
                      <button type="submit" name="add_collaborator" class="btn btn-primary" <?= empty($colleagues) ? 'disabled' : '' ?>>
                          <i class="bi bi-plus-lg"></i> Toevoegen
                      </button>
                  </div>
              </form>
          </div>
      </div>
      <?php endif; ?>

      <!-- KLANTPORTAAL -->
      <?php if (isAdmin()): ?>
      <!-- Dit formulier blijft apart omdat het een andere actie heeft ('manage_client_access') -->
      <form method="post" id="klantportaal-form" action="?page=bewerk&id=<?= (int)($verslag['id'] ?? 0) ?>">
        <?= csrf_field() ?>
        <div id="klantportaal" class="card mb-4 shadow-sm section-anchor">
          <div class="card-header bg-primary text-white"><i class="bi bi-person-badge"></i> Klantportaal Toegang</div>
          <div class="card-body">
            <p class="text-muted small">Geef de contactpersoon toegang tot een vereenvoudigde weergave van dit verslag. De contactpersoon kan inloggen met zijn/haar e-mailadres.</p>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" role="switch" id="clientAccessEnabled" name="client_access_enabled" <?= !empty($clientAccess) ? 'checked' : '' ?> aria-checked="<?= !empty($clientAccess) ? 'true' : 'false' ?>">
              <label class="form-check-label" for="clientAccessEnabled">
                Toegang tot klantportaal inschakelen
              </label>
            </div>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" role="switch" id="clientCanEdit" name="client_can_edit" <?= !empty($clientAccess['can_edit']) ? 'checked' : '' ?> aria-checked="<?= !empty($clientAccess['can_edit']) ? 'true' : 'false' ?>">
              <label class="form-check-label" for="clientCanEdit">
                Klant toestaan om velden aan te vullen (Wensen, Eisen, Installatie, Ruimtes)
              </label>
            </div>
            <div class="mt-3 text-end">
              <button type="submit" name="manage_client_access" value="1" class="btn btn-primary"><i class="bi bi-save"></i> Toegang beheren</button>
            </div>
          </div>
        </div>
      </form>
      <?php endif; ?>

      <!-- RUIMTES (dit is geen formulier, alleen een lijst met links) -->
        <div id="ruimtes" class="card mb-5 shadow-sm section-anchor">
          <div class="card-header bg-primary text-white"><i class="bi bi-door-open"></i> Ruimtes</div>
          <div class="card-body">
            <a href="?page=ruimte_new&verslag_id=<?= (int)($verslag['id'] ?? 0) ?>" id="add-ruimte-button" class="btn btn-primary mb-3"><i class="bi bi-plus-lg"></i> Nieuwe ruimte</a>
            <?php if (!empty($ruimtes)): ?>
            <table class="table table-striped">
              <thead><tr><th>Naam</th><th>Etage</th><th>Opmerkingen</th><th class="text-center">Foto's</th><th>Acties</th></tr></thead>
              <tbody>
                <?php foreach ($ruimtes as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['naam'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['etage'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['opmerkingen'] ?? '') ?></td>
                  <td class="text-center">
                    <span class="badge rounded-pill text-bg-secondary">
                      <?= (int)($r['photo_count'] ?? 0) ?>
                    </span>
                  </td>
                  <td>
                    <a href="?page=ruimte_edit&id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary">Bewerken</a>
                    <a href="<?= csrf_url('?page=ruimte_delete&id=' . (int)$r['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Ruimte verwijderen?')">Verwijderen</a>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php else: ?>
              <p>Er zijn nog geen ruimtes toegevoegd.</p>
            <?php endif; ?>
          </div>
        </div>

      <!-- PROJECTBESTANDEN -->
      <div id="projectbestanden" class="card mb-5 shadow-sm section-anchor">
        <div class="card-header bg-primary text-white"><i class="bi bi-paperclip"></i> Projectbestanden</div>
        <div class="card-body">
            <p class="text-muted small">Voeg hier projectbrede bestanden toe, zoals plattegronden, offertes van derden, etc.</p>
            <form id="project-files-form" method="post" enctype="multipart/form-data" action="?page=bewerk&id=<?= (int)($verslag['id'] ?? 0) ?>">
              <?= csrf_field() ?>
                <div class="input-group">
                    <input type="file" name="project_files[]" class="form-control" multiple>
                    <button class="btn btn-primary" type="submit" name="upload_project_files" value="1"><i class="bi bi-upload"></i> Uploaden</button>
                </div>
                <div class="form-text">
                    Max. bestandsgrootte: <?= ini_get('upload_max_filesize') ?>, Max. aantal bestanden: <?= ini_get('max_file_uploads') ?>
                </div>
            </form>

            <?php if (!empty($projectBestanden)): ?>
            <hr>
            <h6>GeÃ¼ploade bestanden:</h6>
            <ul class="list-group">
                <?php foreach ($projectBestanden as $bestand): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi bi-file-earmark-text me-2"></i>
                            <?= htmlspecialchars($bestand['bestandsnaam']) ?>
                        </span>
                        <button class="btn btn-sm btn-outline-danger delete-project-file" data-file-id="<?= $bestand['id'] ?>" title="Bestand verwijderen">
                            <i class="bi bi-trash"></i>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
              <p class="mt-3">Er zijn nog geen projectbestanden toegevoegd.</p>
            <?php endif; ?>
        </div>
      </div>


    </div>
  </div>
</div>

<!-- Toast container voor AJAX meldingen -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100">
  <div id="ajax-toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
      <strong class="me-auto" id="toast-title"></strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body" id="toast-body"></div>
  </div>
</div>

<script>
// Smooth scroll voor zijmenu
document.querySelectorAll('#sideMenu a').forEach(anchor => {
  anchor.addEventListener('click', function(e) {
    e.preventDefault();
    const target = document.querySelector(this.getAttribute('href'));
    if (target) { // Zorg ervoor dat het ankerpunt bestaat
      window.scrollTo({ top: target.offsetTop - 70, behavior: 'smooth' });
    }
  });
});

// Highlight active menu item on scroll
document.addEventListener('DOMContentLoaded', () => {
  const links = document.querySelectorAll('#sideMenu a');
  const sections = Array.from(links).map(a => document.querySelector(a.getAttribute('href')));

  function onScroll() {
    const scrollPosition = window.scrollY + 100; // Offset for header
    let activeIndex = 0;
    sections.forEach((sec, i) => { if (sec && sec.offsetTop <= scrollPosition) { activeIndex = i; } }); // Vind de laatste sectie die in beeld is
    links.forEach((link, i) => { link.classList.toggle('active', i === activeIndex); });
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll(); // Initial check
});

// Conditionele velden voor installatie
document.addEventListener('DOMContentLoaded', () => {
  const toggleField = (selectId, containerId) => {
    const select = document.getElementById(selectId);
    const container = document.getElementById(containerId);
    const check = () => {
      container.style.display = select.value === 'Ja' ? 'flex' : 'none';
    };
    select.addEventListener('change', check);
    check(); // Initial check on page load
  };

  toggleField('installatieAdresAfwijkend', 'afwijkendAdresContainer');
  toggleField('cpLocatieAfwijkend', 'afwijkendeCpContainer');

  // Conditioneel veld voor afvoer omschrijving
  const afvoerSelect = document.getElementById('afvoerSelect');
  const afvoerOmschrijvingContainer = document.getElementById('afvoerOmschrijvingContainer');
  const checkAfvoer = () => {
      afvoerOmschrijvingContainer.style.display = afvoerSelect.value === 'Ja' ? 'block' : 'none';
  };
  if (afvoerSelect) {
    afvoerSelect.addEventListener('change', checkAfvoer);
    checkAfvoer(); // Check on page load
  }

});

// AJAX Form Submission
document.addEventListener('DOMContentLoaded', function() {
    const toastElement = document.getElementById('ajax-toast');
    const toast = new bootstrap.Toast(toastElement);

    function showToast(title, message, isSuccess) {
        document.getElementById('toast-title').textContent = title;
        document.getElementById('toast-body').innerHTML = message; // Gebruik innerHTML voor eventuele <strong> tags
        toastElement.classList.remove('bg-success-subtle', 'bg-danger-subtle');
        toastElement.classList.add(isSuccess ? 'bg-success-subtle' : 'bg-danger-subtle');
        toast.show();
    }

    function handleFormSubmit(formId) {
        const form = document.getElementById(formId);
        if (!form) return;

        let clickedButton = null;
        form.querySelectorAll('button[type="submit"]').forEach(button => {
            button.addEventListener('click', function() {
                clickedButton = this;
            });
        });

        form.addEventListener('submit', function(event) {
            event.preventDefault();

            // Gebruik de opgeslagen geklikte knop, of val terug op event.submitter of de eerste knop in het formulier.
            const saveButton = clickedButton || (event.submitter instanceof HTMLElement ? event.submitter : this.querySelector('button[type="submit"]'));
            if (!saveButton) return; // Voorkom fouten als het formulier anders wordt gesubmit

            const originalButtonText = saveButton.innerHTML;
            saveButton.disabled = true;
            saveButton.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Opslaan...`;

            const formData = new FormData(form);

            // Voeg de geklikte knop toe aan de formulierdata, zodat de controller weet welke actie het is
            if (saveButton.name) {
                formData.append(saveButton.name, saveButton.value || '1'); // bv. 'manage_client_access' of 'save_section'
            }
            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showToast(data.success ? 'Succes' : 'Fout', data.message, data.success);
                // Als de upload succesvol was, herlaad de pagina na een korte vertraging
                // om de nieuwe bestandenlijst te tonen.
                if (data.success && form.id === 'project-files-form') {
                    setTimeout(() => { window.location.reload(); }, 2000); // 2 seconden vertraging
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Fout', 'Er is een onverwachte fout opgetreden.', false);
            })
            .finally(() => {
                saveButton.disabled = false;
                saveButton.innerHTML = originalButtonText;
                clickedButton = null; // Reset voor de volgende klik
            });
        });
    }

    // Koppel de AJAX handler aan alle formulieren op de pagina
    handleFormSubmit('verslag-form'); // Het nieuwe, gecombineerde formulier
    handleFormSubmit('klantportaal-form');
    handleFormSubmit('collaboration-form');
    handleFormSubmit('project-files-form');

    // Speciale handler voor de 'Nieuwe ruimte' knop: eerst opslaan, dan doorsturen.
    const addRuimteButton = document.getElementById('add-ruimte-button');
    if (addRuimteButton) {
        addRuimteButton.addEventListener('click', function(event) {
            event.preventDefault(); // Voorkom de standaard navigatie

            const form = document.getElementById('verslag-form');
            const redirectUrl = this.href;
            const originalButtonText = this.innerHTML;

            this.disabled = true;
            this.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Opslaan...`;

            const formData = new FormData(form);
            formData.append('save_section', 'all'); // Zorg ervoor dat de controller de update herkent

            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Als opslaan succesvol is, ga naar de 'nieuwe ruimte' pagina
                    window.location.href = redirectUrl;
                } else {
                    let errorMessage = data.message || 'Kon de wijzigingen niet opslaan.';
                    showToast('Fout bij opslaan', errorMessage, false);
                    this.disabled = false;
                    this.innerHTML = originalButtonText;
                }
            }).catch(error => {
                showToast('Fout', 'Er is een onverwachte fout opgetreden bij het opslaan.', false);
            });
        });
    }
    document.querySelectorAll('.collaboration-form').forEach(form => {
        form.addEventListener('submit', function(event) {
            event.preventDefault();

            const formData = new FormData(this);
            const button = this.querySelector('button[type="submit"]');
            if (button) {
                formData.append(button.name, button.value || '1');
            }

            fetch(this.action || window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showToast(data.success ? 'Succes' : 'Fout', data.message, data.success);
                if (data.success) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Fout', 'Er is een onverwachte fout opgetreden.', false);
            });
        });
    });

    // --- Postcode API Lookup ---
    // Functie om de lookup uit te voeren
    const handlePostcodeLookup = (prefix) => {
        const postcodeEl = document.querySelector(`input[name="${prefix === 'relatie' ? 'postcode' : 'installatie_adres_postcode'}"]`);
        const huisnummerEl = document.querySelector(`input[name="${prefix === 'relatie' ? 'huisnummer' : 'installatie_adres_huisnummer'}"]`);
        const toevoegingEl = document.querySelector(`input[name="${prefix === 'relatie' ? 'huisnummer_toevoeging' : 'installatie_adres_huisnummer_toevoeging'}"]`);
        const straatEl = document.querySelector(`input[name="${prefix === 'relatie' ? 'straatnaam' : 'installatie_adres_straat'}"]`);
        const plaatsEl = document.querySelector(`input[name="${prefix === 'relatie' ? 'plaats' : 'installatie_adres_plaats'}"]`);
        const spinnerEl = document.getElementById(`postcode-spinner-${prefix}`);

        if (!postcodeEl || !huisnummerEl || !straatEl || !plaatsEl || !spinnerEl) return;

        // Haal de waarden op
        let postcode = postcodeEl.value.replace(/\s/g, '').toUpperCase();
        let huisnummer = parseInt(huisnummerEl.value, 10);

        // Valideer Nederlandse postcode en huisnummer
        const postcodeRegex = /^[1-9][0-9]{3}[A-Z]{2}$/;
        if (!postcodeRegex.test(postcode) || !(huisnummer > 0)) {
            return; // Geen geldige combinatie, doe niets
        }

        spinnerEl.style.display = 'inline-block';

        fetch(`?page=api_postcode_lookup&postcode=${postcode}&huisnummer=${huisnummer}&toevoeging=${toevoegingEl.value}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    showToast('Postcode Fout', data.error, false);
                    return;
                }
                if (data.street && data.city) {
                    straatEl.value = data.street;
                    plaatsEl.value = data.city;
                    showToast('Adres gevonden', `${data.street}, ${data.city}`, true);
                } else {
                    showToast('Adres niet gevonden', 'De combinatie van postcode en huisnummer is onbekend.', false);
                }
            })
            .catch(error => {
                console.error('Postcode API Error:', error);
                showToast('Fout', 'Kon de postcode-service niet bereiken.', false);
            })
            .finally(() => {
                spinnerEl.style.display = 'none';
            });
    };

    // Koppel de event listeners
    document.querySelectorAll('.postcode-lookup').forEach(el => {
        const prefix = el.dataset.prefix;
        const huisnummerEl = document.querySelector(`input[name="${prefix === 'relatie' ? 'huisnummer' : 'installatie_adres_huisnummer'}"]`);
        const toevoegingEl = document.querySelector(`input[name="${prefix === 'relatie' ? 'huisnummer_toevoeging' : 'installatie_adres_huisnummer_toevoeging'}"]`);

        // Zorg dat een reeds ingevulde postcode meteen in hoofdletters staat
        if (el.value) {
            el.value = el.value.toUpperCase();
        }

        let debounceTimer;
        const triggerLookup = () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => handlePostcodeLookup(prefix), 500); // Wacht 500ms na de laatste input
        };

        // Uppercase live tijdens het typen en start lookup met debounce
        el.addEventListener('input', () => {
            const start = el.selectionStart;
            const end = el.selectionEnd;
            const upper = (el.value || '').toUpperCase();
            if (el.value !== upper) {
                el.value = upper;
                try { if (start != null && end != null) el.setSelectionRange(start, end); } catch (e) {}
            }
            triggerLookup();
        });

        // Nogmaals normaliseren bij verlaten van het veld
        el.addEventListener('blur', () => { el.value = (el.value || '').toUpperCase(); });

        if (huisnummerEl) {
            huisnummerEl.addEventListener('input', triggerLookup);
        }
        if (toevoegingEl) {
            toevoegingEl.addEventListener('input', triggerLookup);
        }
    });

    // AJAX voor het verwijderen van een projectbestand
    document.querySelectorAll('.delete-project-file').forEach(button => {
        button.addEventListener('click', function(event) {
            event.preventDefault();
            if (!confirm('Weet je zeker dat je dit bestand wilt verwijderen?')) {
                return;
            }

            const fileId = this.dataset.fileId;
            const formData = new FormData();
            formData.append('delete_project_file', '1');
            formData.append('file_id', fileId);

            fetch('?page=bewerk&id=<?= (int)($verslag['id'] ?? 0) ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showToast(data.success ? 'Succes' : 'Fout', data.message, data.success);
                if (data.success) {
                    // Verwijder het list item uit de DOM
                    this.closest('li').remove();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Fout', 'Er is een onverwachte fout opgetreden.', false);
            });
        });
    });
});

</script>

<?php include_once 'layout/footer.php'; ?>


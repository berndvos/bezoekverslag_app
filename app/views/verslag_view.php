<?php include 'layout/header.php'; ?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Bezoekverslag: <?= htmlspecialchars($verslag['klantnaam'] ?? '') ?></h3>
    <a href="?page=dashboard" class="btn btn-outline-secondary">Terug</a>
  </div>
  <hr>

  <div class="card mb-4">
    <div class="card-header bg-primary text-white"><i class="bi bi-building"></i> Relatiegegevens</div>
    <div class="card-body">
      <p><b>Klantnaam:</b> <?= htmlspecialchars($verslag['klantnaam'] ?? '') ?></p>
      <p><b>Projecttitel:</b> <?= htmlspecialchars($verslag['projecttitel'] ?? '') ?></p>
      <p><b>Adres:</b> <?= htmlspecialchars($verslag['adres'] ?? '') ?></p>
      <p><b>Postcode/Plaats:</b> <?= htmlspecialchars($verslag['postcode'] ?? '') ?> <?= htmlspecialchars($verslag['plaats'] ?? '') ?></p>
      <p><b>KvK:</b> <?= htmlspecialchars($verslag['kvk'] ?? '') ?> &nbsp; <b>BTW:</b> <?= htmlspecialchars($verslag['btw'] ?? '') ?></p>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header bg-primary text-white"><i class="bi bi-person-lines-fill"></i> Contactpersoon</div>
    <div class="card-body">
      <p><b>Naam:</b> <?= htmlspecialchars($verslag['contact_naam'] ?? '') ?></p>
      <p><b>Functie:</b> <?= htmlspecialchars($verslag['contact_functie'] ?? '') ?></p>
      <p><b>E-mail:</b> <?= htmlspecialchars($verslag['contact_email'] ?? '') ?></p>
      <p><b>Telefoon:</b> <?= htmlspecialchars($verslag['contact_tel'] ?? '') ?></p>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header bg-primary text-white"><i class="bi bi-gear"></i> Huidige leveranciers</div>
    <div class="card-body">
      <p><b>ICT:</b> <?= htmlspecialchars($verslag['leverancier_ict'] ?? '') ?></p>
      <p><b>Telecom:</b> <?= htmlspecialchars($verslag['leverancier_telecom'] ?? '') ?></p>
      <p><b>AV:</b> <?= htmlspecialchars($verslag['leverancier_av'] ?? '') ?></p>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header bg-success text-white"><i class="bi bi-list-check"></i> Wensen</div>
    <div class="card-body">
      <p><b>Situatie:</b> <?= nl2br(htmlspecialchars($verslag['situatie'] ?? '')) ?></p>
      <p><b>Doel:</b> <?= nl2br(htmlspecialchars($verslag['doel'] ?? '')) ?></p>
      <p><b>Functioneel:</b> <?= nl2br(htmlspecialchars($verslag['functioneel'] ?? '')) ?></p>
      <p><b>Uitbreiding:</b> <?= nl2br(htmlspecialchars($verslag['uitbreiding'] ?? '')) ?></p>
      <p><b>Overige wensen:</b> <?= nl2br(htmlspecialchars($verslag['wensen'] ?? '')) ?></p>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header bg-success text-white"><i class="bi bi-shield-check"></i> Eisen</div>
    <div class="card-body">
      <p><b>Beeldkwaliteit:</b> <?= htmlspecialchars($verslag['beeldkwaliteitseisen'] ?? '') ?></p>
      <p><b>Geluidseisen:</b> <?= htmlspecialchars($verslag['geluidseisen'] ?? '') ?></p>
      <p><b>Bediening:</b> <?= htmlspecialchars($verslag['bedieningseisen'] ?? '') ?></p>
      <p><b>Beveiliging:</b> <?= htmlspecialchars($verslag['beveiligingseisen'] ?? '') ?></p>
      <p><b>Netwerk:</b> <?= htmlspecialchars($verslag['netwerkeisen'] ?? '') ?></p>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header bg-info"><i class="bi bi-door-open"></i> Ruimtes</div>
    <div class="card-body">
      <?php if ($ruimtes): ?>
        <table class="table table-striped">
          <thead><tr><th>Naam</th><th>Etage</th><th>Opmerkingen</th></tr></thead>
          <tbody>
            <?php foreach ($ruimtes as $r): ?>
              <tr>
                <td><?= htmlspecialchars($r['naam'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['etage'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['opmerkingen'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>Geen ruimtes geregistreerd.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include 'layout/footer.php'; ?>

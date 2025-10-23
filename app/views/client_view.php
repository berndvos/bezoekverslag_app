<?php
// Publieke clientweergave van één verslag – beperkte rechten
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Klant – Bezoekverslag</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container" style="max-width:1100px; margin-top:20px;">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Bezoekverslag – <?= htmlspecialchars($verslag['projecttitel'] ?? '—') ?></h3>
    <div>
      <a href="?page=client_logout" class="btn btn-outline-secondary btn-sm">Uitloggen (<?= htmlspecialchars($_SESSION['client_name'] ?? 'klant') ?>)</a>
    </div>
  </div>

  <div class="alert alert-info">
    <b>Klanttoegang:</b>
    Je kunt <?= !empty($_SESSION['client_can_edit']) ? 'bepaalde onderdelen bewerken' : 'alleen bekijken' ?>.
    Geen PDF-generatie en geen nieuwe ruimtes toevoegen.
  </div>

  <div class="card mb-4">
    <div class="card-header bg-primary text-white">Samenvatting</div>
    <div class="card-body">
      <p><b>Klant:</b> <?= htmlspecialchars($verslag['klantnaam'] ?? '-') ?><br>
      <b>Project:</b> <?= htmlspecialchars($verslag['projecttitel'] ?? '-') ?><br>
      <b>Laatste wijziging:</b> <?= htmlspecialchars($verslag['last_modified_at'] ?? $verslag['created_at'] ?? '-') ?> door
      <?= htmlspecialchars($verslag['last_modified_by'] === 'client' ? 'klant' : 'accountmanager') ?></p>
    </div>
  </div>

  <!-- Wensen & Installatie -->
  <div class="card mb-4">
    <div class="card-header bg-success text-white">Wensen & eisen + Installatie</div>
    <div class="card-body">
      <?php if (!empty($_SESSION['client_can_edit'])): ?>
        <form method="post" action="?page=client_update&id=<?= (int)$verslag['id'] ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_wensen_installatie">
          <div class="mb-3">
            <label class="form-label">Wensen</label>
            <textarea name="wensen" class="form-control" rows="3"><?= htmlspecialchars($verslag['wensen'] ?? '') ?></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Eisen</label>
            <textarea name="eisen" class="form-control" rows="3"><?= htmlspecialchars($verslag['eisen'] ?? '') ?></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Installatie</label>
            <textarea name="installatie" class="form-control" rows="3"><?= htmlspecialchars($verslag['installatie'] ?? '') ?></textarea>
          </div>
          <button class="btn btn-success">Opslaan</button>
        </form>
      <?php else: ?>
        <p><b>Wensen</b><br><?= nl2br(htmlspecialchars($verslag['wensen'] ?? '-')) ?></p>
        <p><b>Eisen</b><br><?= nl2br(htmlspecialchars($verslag['eisen'] ?? '-')) ?></p>
        <p><b>Installatie</b><br><?= nl2br(htmlspecialchars($verslag['installatie'] ?? '-')) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Bestaande ruimtes -->
  <div class="card mb-5">
    <div class="card-header bg-secondary text-white">Ruimtes (aanvullen toegestaan, geen nieuwe toevoegen)</div>
    <div class="card-body">
      <?php if (empty($ruimtes)): ?>
        <p class="text-muted">Er zijn nog geen ruimtes geregistreerd.</p>
      <?php else: ?>
        <?php foreach ($ruimtes as $r): ?>
          <div class="border rounded p-3 mb-4 bg-white">
            <h6 class="mb-3">Ruimte: <?= htmlspecialchars($r['naam'] ?? ('#'.$r['id'])) ?></h6>

            <?php if (!empty($_SESSION['client_can_edit'])): ?>
              <form method="post" action="?page=client_update&id=<?= (int)$verslag['id'] ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_ruimte">
                <input type="hidden" name="ruimte_id" value="<?= (int)$r['id'] ?>">

                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label">Etage</label>
                    <input class="form-control" name="etage" value="<?= htmlspecialchars($r['etage'] ?? '') ?>">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Bereikbaarheid</label>
                    <input class="form-control" name="bereikbaarheid" value="<?= htmlspecialchars($r['bereikbaarheid'] ?? '') ?>">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Lift</label>
                    <input class="form-control" name="lift" value="<?= htmlspecialchars($r['lift'] ?? '') ?>">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Afm. lift</label>
                    <input class="form-control" name="afm_lift" value="<?= htmlspecialchars($r['afm_lift'] ?? '') ?>">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Voorzieningen</label>
                    <input class="form-control" name="voorzieningen" value="<?= htmlspecialchars($r['voorzieningen'] ?? '') ?>">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Bereikb. voorzieningen</label>
                    <input class="form-control" name="bereikb_voorzieningen" value="<?= htmlspecialchars($r['bereikb_voorzieningen'] ?? '') ?>">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Kabellengte</label>
                    <input class="form-control" name="kabellengte" value="<?= htmlspecialchars($r['kabellengte'] ?? '') ?>">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Netwerkintegratie</label>
                    <input class="form-control" name="netwerkintegratie" value="<?= htmlspecialchars($r['netwerkintegratie'] ?? '') ?>">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Afmetingen</label>
                    <input class="form-control" name="afmetingen" value="<?= htmlspecialchars($r['afmetingen'] ?? '') ?>">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Plafond</label>
                    <input class="form-control" name="plafond" value="<?= htmlspecialchars($r['plafond'] ?? '') ?>">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Wand</label>
                    <input class="form-control" name="wand" value="<?= htmlspecialchars($r['wand'] ?? '') ?>">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Vloer</label>
                    <input class="form-control" name="vloer" value="<?= htmlspecialchars($r['vloer'] ?? '') ?>">
                  </div>
                  <div class="col-12">
                    <label class="form-label">Beperkingen</label>
                    <input class="form-control" name="beperkingen" value="<?= htmlspecialchars($r['beperkingen'] ?? '') ?>">
                  </div>
                  <div class="col-12">
                    <label class="form-label">Opmerkingen</label>
                    <textarea class="form-control" name="opmerkingen" rows="2"><?= htmlspecialchars($r['opmerkingen'] ?? '') ?></textarea>
                  </div>
                </div>

                <div class="mt-3">
                  <label class="form-label">Foto’s toevoegen (optioneel)</label>
                  <input class="form-control" type="file" name="files[]" accept="image/*" capture="environment" multiple>
                </div>

                <div class="mt-3">
                  <button class="btn btn-success btn-sm">Opslaan</button>
                </div>
              </form>
            <?php else: ?>
              <div class="row g-2">
                <div class="col-md-4"><b>Etage:</b> <?= htmlspecialchars($r['etage'] ?? '-') ?></div>
                <div class="col-md-4"><b>Bereikbaarheid:</b> <?= htmlspecialchars($r['bereikbaarheid'] ?? '-') ?></div>
                <div class="col-md-4"><b>Lift:</b> <?= htmlspecialchars($r['lift'] ?? '-') ?></div>
              </div>
              <!-- etc. toon alleen waarden -->
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>
</body>
</html>

<?php include_once 'layout/header.php'; ?>

<?php
// Zorg dat variabelen altijd bestaan
$ruimte = $ruimte ?? [];
$isEdit = !empty($ruimte['id']);
$fotos = $fotos ?? [];

// Bepaal de schema versie. Nieuwe ruimtes zijn v2, bestaande zonder versie zijn v1.
$schema_version = $ruimte['schema_version'] ?? 1;

// Definieer een standaard lege ruimte structuur voor v2 om fouten te voorkomen
$v2_defaults = [
    'lengte_ruimte' => '',
    'breedte_ruimte' => '',
    'hoogte_plafond' => '',
    'type_plafond' => '',
    'ruimte_boven_plafond' => '',
    'huidige_situatie_v2' => '',
    'type_wand' => '',
    'netwerk_aanwezig' => '',
    'netwerk_extra' => '',
    'netwerk_afstand' => '',
    'stroom_aanwezig' => '',
    'stroom_extra_v2' => '',
    'stroom_afstand' => ''
];

// Voeg de v2 defaults toe aan de $ruimte array als het een v2 schema is
if ($schema_version == 2) {
    $ruimte = array_merge($v2_defaults, $ruimte);
}

?>

<style>
  /* Zorgt ervoor dat de ankerpunten niet achter de header vallen */
  .section-anchor {
    scroll-margin-top: 90px;
  }
</style>

<div class="container">
  <div class="row">
    <!-- Linker menu -->
    <div class="col-md-3 col-lg-2">
      <div id="sideMenu" class="position-sticky" style="top: 80px;">
        <div class="card shadow-sm">
          <div class="card-header bg-light fw-semibold">Ruimteonderdelen</div>
          <div class="list-group list-group-flush">
            <a href="#ruimte" class="list-group-item list-group-item-action">Ruimtegegevens</a>
            <a href="#huidige" class="list-group-item list-group-item-action">Huidige situatie</a>
            <a href="#wensen" class="list-group-item list-group-item-action">Wensen</a>
            <a href="#bekabeling" class="list-group-item list-group-item-action">Bekabeling & montage</a>
            <?php if ($schema_version == 1): ?>
              <a href="#stroom" class="list-group-item list-group-item-action">Stroomvoorziening</a>
            <?php else: ?>
              <a href="#voorzieningen" class="list-group-item list-group-item-action">Aanwezige voorzieningen</a>
            <?php endif; ?>
            <a href="#fotos" class="list-group-item list-group-item-action">Fotoâ€™s</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Rechter content -->
    <div class="col-md-9 col-lg-10">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><i class="bi bi-building"></i> <?= $isEdit ? 'Ruimte bewerken' : 'Nieuwe ruimte toevoegen' ?></h3>
        <a href="?page=bewerk&id=<?= (int)($ruimte['verslag_id'] ?? 0) ?>" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-arrow-left"></i> Terug naar verslag
        </a>
      </div>

      <?php
        $formAction = $isEdit ? "?page=ruimte_edit&id=" . (int)$ruimte['id'] : "?page=ruimte_save&verslag_id=" . (int)($ruimte['verslag_id'] ?? 0);
      ?> 
      <form method="post" action="<?= $formAction ?>" enctype="multipart/form-data" id="ruimte-form">
        <?= csrf_field() ?>
        <!-- RUIMTEGEGEVENS -->
        <div id="ruimte" class="section-anchor card shadow-sm mb-4">
          <div class="card-header bg-success text-white">
            <i class="bi bi-info-circle"></i> Ruimtegegevens
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label for="naam" class="form-label">Ruimte</label>
                <input type="text" name="naam" id="naam" class="form-control" value="<?= htmlspecialchars($ruimte['naam'] ?? '') ?>" required>
              </div>
              <div class="col-md-6">
                <label for="etage" class="form-label">Etage</label>
                <input type="text" name="etage" id="etage" class="form-control" value="<?= htmlspecialchars($ruimte['etage'] ?? '') ?>">
              </div>

              <?php if ($schema_version == 2): ?>
                <div class="col-md-4">
                    <label for="lengte_ruimte" class="form-label">Lengte ruimte (m)</label>
                    <input type="text" name="lengte_ruimte" id="lengte_ruimte" class="form-control" value="<?= htmlspecialchars($ruimte['lengte_ruimte']) ?>">
                </div>
                <div class="col-md-4">
                    <label for="breedte_ruimte" class="form-label">Breedte ruimte (m)</label>
                    <input type="text" name="breedte_ruimte" id="breedte_ruimte" class="form-control" value="<?= htmlspecialchars($ruimte['breedte_ruimte']) ?>">
                </div>
                <div class="col-md-4">
                    <label for="hoogte_plafond" class="form-label">Hoogte plafond (m)</label>
                    <input type="text" name="hoogte_plafond" id="hoogte_plafond" class="form-control" value="<?= htmlspecialchars($ruimte['hoogte_plafond']) ?>">
                </div>
                <div class="col-md-6">
                    <label for="type_plafond" class="form-label">Type plafond</label>
                    <input type="text" name="type_plafond" id="type_plafond" class="form-control" value="<?= htmlspecialchars($ruimte['type_plafond']) ?>">
                </div>
                <div class="col-md-6">
                    <label for="ruimte_boven_plafond" class="form-label">Ruimte boven plafond (cm)</label>
                    <input type="text" name="ruimte_boven_plafond" id="ruimte_boven_plafond" class="form-control" value="<?= htmlspecialchars($ruimte['ruimte_boven_plafond']) ?>">
                </div>
              <?php endif; ?>

              <div class="col-12">
                <label for="opmerkingen" class="form-label">Opmerkingen</label>
                <textarea name="opmerkingen" id="opmerkingen" class="form-control" rows="3"><?= htmlspecialchars($ruimte['opmerkingen'] ?? '') ?></textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- HUIDIGE SITUATIE -->
        <?php if ($schema_version == 1): ?>
        <div id="huidige" class="section-anchor card shadow-sm mb-4">
          <div class="card-header bg-success text-white">
            <i class="bi bi-eye"></i> Huidige situatie
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-4">
                <label for="aantal_aansluitingen" class="form-label">Aantal aansluitingen</label>
                <input type="number" name="aantal_aansluitingen" id="aantal_aansluitingen" class="form-control"
                       value="<?= htmlspecialchars($ruimte['aantal_aansluitingen'] ?? '') ?>">
              </div>
              <div class="col-md-8">
                <label for="type_aansluitingen" class="form-label">Type aansluitingen</label>
                <input type="text" name="type_aansluitingen" id="type_aansluitingen" class="form-control"
                       placeholder="Bijv. HDMI, DisplayPort, USB-C..."
                       value="<?= htmlspecialchars($ruimte['type_aansluitingen'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label for="huidig_scherm" class="form-label">Huidig schermtype / grootte</label>
                <input type="text" name="huidig_scherm" id="huidig_scherm" class="form-control"
                       value="<?= htmlspecialchars($ruimte['huidig_scherm'] ?? '') ?>">
              </div>
              <div class="col-md-3">
                <label for="audio_aanwezig" class="form-label">Audio aanwezig</label>
                <select name="audio_aanwezig" id="audio_aanwezig" class="form-select">
                  <option value="">Kies...</option>
                  <option value="Ja" <?= ($ruimte['audio_aanwezig'] ?? '') == 'Ja' ? 'selected' : '' ?>>Ja</option>
                  <option value="Nee" <?= ($ruimte['audio_aanwezig'] ?? '') == 'Nee' ? 'selected' : '' ?>>Nee</option>
                </select>
              </div>
              <div class="col-md-12">
                <label for="beeldkwaliteit" class="form-label">Beeldkwaliteit opmerkingen</label>
                <textarea name="beeldkwaliteit" id="beeldkwaliteit" class="form-control" rows="2"><?= htmlspecialchars($ruimte['beeldkwaliteit'] ?? '') ?></textarea>
              </div>
            </div>
          </div>
        </div>
        <?php else: ?>
        <!-- HUIDIGE SITUATIE (V2) -->
        <div id="huidige" class="section-anchor card shadow-sm mb-4">
          <div class="card-header bg-success text-white">
            <i class="bi bi-eye"></i> Huidige situatie
          </div>
          <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label for="huidige_situatie_v2" class="form-label">Beschrijving huidige situatie</label>
                    <textarea name="huidige_situatie_v2" id="huidige_situatie_v2" class="form-control" rows="5"><?= htmlspecialchars($ruimte['huidige_situatie_v2']) ?></textarea>
                </div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- WENSEN -->
        <div id="wensen" class="section-anchor card shadow-sm mb-4">
          <div class="card-header bg-success text-white">
            <i class="bi bi-lightbulb"></i> Wensen voor nieuwe situatie
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label for="gewenst_scherm" class="form-label">Gewenste schermgrootte</label>
                <input type="text" name="gewenst_scherm" id="gewenst_scherm" class="form-control" value="<?= htmlspecialchars($ruimte['gewenst_scherm'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label for="gewenst_aansluitingen" class="form-label">Gewenste aansluitingen</label>
                <input type="text" name="gewenst_aansluitingen" id="gewenst_aansluitingen" class="form-control"
                       placeholder="Bijv. HDMI, USB-C..." value="<?= htmlspecialchars($ruimte['gewenst_aansluitingen'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label for="presentatie_methode" class="form-label">Presentatiemethode</label>
                <select name="presentatie_methode" id="presentatie_methode" class="form-select">
                  <option value="">Kies...</option>
                  <option value="Vast" <?= ($ruimte['presentatie_methode'] ?? '') == 'Vast' ? 'selected' : '' ?>>Vast</option>
                  <option value="Draadloos" <?= ($ruimte['presentatie_methode'] ?? '') == 'Draadloos' ? 'selected' : '' ?>>Draadloos</option>
                  <option value="Beide" <?= ($ruimte['presentatie_methode'] ?? '') == 'Beide' ? 'selected' : '' ?>>Beide</option>
                </select>
              </div>
              <div class="col-md-3">
                <label for="geluid_gewenst" class="form-label">Geluid gewenst</label>
                <select name="geluid_gewenst" id="geluid_gewenst" class="form-select">
                  <option value="">Kies...</option>
                  <option value="Ja" <?= ($ruimte['geluid_gewenst'] ?? '') == 'Ja' ? 'selected' : '' ?>>Ja</option>
                  <option value="Nee" <?= ($ruimte['geluid_gewenst'] ?? '') == 'Nee' ? 'selected' : '' ?>>Nee</option>
                </select>
              </div>
              <div class="col-md-12">
                <label for="overige_wensen" class="form-label">Overige wensen</label>
                <textarea name="overige_wensen" id="overige_wensen" class="form-control" rows="3"><?= htmlspecialchars($ruimte['overige_wensen'] ?? '') ?></textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- BEKABELING & MONTAGE -->
        <div id="bekabeling" class="section-anchor card shadow-sm mb-4">
          <div class="card-header bg-success text-white">
            <i class="bi bi-plug"></i> Bekabeling & montage
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-3">
                <label for="kabeltraject_mogelijk" class="form-label">Kabeltraject mogelijk</label>
                <select name="kabeltraject_mogelijk" id="kabeltraject_mogelijk" class="form-select">
                  <option value="">Kies...</option>
                  <option value="Ja" <?= ($ruimte['kabeltraject_mogelijk'] ?? '') == 'Ja' ? 'selected' : '' ?>>Ja</option>
                  <option value="Nee" <?= ($ruimte['kabeltraject_mogelijk'] ?? '') == 'Nee' ? 'selected' : '' ?>>Nee</option>
                </select>
              </div>
              <div class="col-md-9">
                <label for="beperkingen" class="form-label">Beperkingen / obstakels</label>
                <input type="text" name="beperkingen" id="beperkingen" class="form-control" value="<?= htmlspecialchars($ruimte['beperkingen'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label for="ophanging" class="form-label">Ophanging scherm</label>
                <select name="ophanging" id="ophanging" class="form-select">
                  <option value="">Kies...</option>
                  <option value="Wand" <?= ($ruimte['ophanging'] ?? '') == 'Wand' ? 'selected' : '' ?>>Wand</option>
                  <option value="Plafond" <?= ($ruimte['ophanging'] ?? '') == 'Plafond' ? 'selected' : '' ?>>Plafond</option>
                  <option value="Mobiel" <?= ($ruimte['ophanging'] ?? '') == 'Mobiel' ? 'selected' : '' ?>>Mobiel</option>
                </select>
              </div>
              <?php if ($schema_version == 2): ?>
                <div class="col-md-6">
                    <label for="type_wand" class="form-label">Type wand</label>
                    <input type="text" name="type_wand" id="type_wand" class="form-control" value="<?= htmlspecialchars($ruimte['type_wand']) ?>">
                </div>
              <?php endif; ?>
              <div class="col-md-12">
                <label for="montage_extra" class="form-label">Extra montagewensen</label>
                <input type="text" name="montage_extra" id="montage_extra" class="form-control" value="<?= htmlspecialchars($ruimte['montage_extra'] ?? '') ?>">
              </div>
            </div>
          </div>
        </div>

        <?php if ($schema_version == 1): ?>
        <!-- STROOM (V1) -->
        <div id="stroom" class="section-anchor card shadow-sm mb-4">
          <div class="card-header bg-success text-white">
            <i class="bi bi-lightning-charge"></i> Stroomvoorziening
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-4">
                <label for="stroom_voldoende" class="form-label">Voldoende stroompunten aanwezig?</label>
                <select name="stroom_voldoende" id="stroom_voldoende" class="form-select">
                  <option value="">Kies...</option>
                  <option value="Ja" <?= ($ruimte['stroom_voldoende'] ?? '') == 'Ja' ? 'selected' : '' ?>>Ja</option>
                  <option value="Nee" <?= ($ruimte['stroom_voldoende'] ?? '') == 'Nee' ? 'selected' : '' ?>>Nee</option>
                </select>
              </div>
              <div class="col-md-8">
                <label for="stroom_extra" class="form-label">Extra nodig</label>
                <input type="text" name="stroom_extra" id="stroom_extra" class="form-control" value="<?= htmlspecialchars($ruimte['stroom_extra'] ?? '') ?>">
              </div>
            </div>
          </div>
        </div>
        <?php else: ?>
        <!-- AANWEZIGE VOORZIENINGEN (V2) -->
        <div id="voorzieningen" class="section-anchor card shadow-sm mb-4">
            <div class="card-header bg-success text-white">
                <i class="bi bi-lightning-charge"></i> Aanwezige voorzieningen
            </div>
            <div class="card-body">
                <h6 class="text-muted">Stroom</h6>
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label for="stroom_aanwezig" class="form-label">Aanwezig?</label>
                        <select name="stroom_aanwezig" id="stroom_aanwezig" class="form-select">
                            <option value="">Kies...</option>
                            <option value="Ja" <?= ($ruimte['stroom_aanwezig'] ?? '') == 'Ja' ? 'selected' : '' ?>>Ja</option>
                            <option value="Nee" <?= ($ruimte['stroom_aanwezig'] ?? '') == 'Nee' ? 'selected' : '' ?>>Nee</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="stroom_extra_v2" class="form-label">Extra nodig</label>
                        <input type="text" name="stroom_extra_v2" id="stroom_extra_v2" class="form-control" value="<?= htmlspecialchars($ruimte['stroom_extra_v2']) ?>">
                    </div>
                    <div class="col-md-5">
                        <label for="stroom_afstand" class="form-label">Geschatte afstand (m)</label>
                        <input type="text" name="stroom_afstand" id="stroom_afstand" class="form-control" value="<?= htmlspecialchars($ruimte['stroom_afstand']) ?>">
                    </div>
                </div>
                <hr>
                <h6 class="text-muted">Netwerk</h6>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="netwerk_aanwezig" class="form-label">Aanwezig?</label>
                        <select name="netwerk_aanwezig" id="netwerk_aanwezig" class="form-select">
                            <option value="">Kies...</option>
                            <option value="Ja" <?= ($ruimte['netwerk_aanwezig'] ?? '') == 'Ja' ? 'selected' : '' ?>>Ja</option>
                            <option value="Nee" <?= ($ruimte['netwerk_aanwezig'] ?? '') == 'Nee' ? 'selected' : '' ?>>Nee</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="netwerk_extra" class="form-label">Extra nodig</label>
                        <input type="text" name="netwerk_extra" id="netwerk_extra" class="form-control" value="<?= htmlspecialchars($ruimte['netwerk_extra']) ?>">
                    </div>
                    <div class="col-md-5">
                        <label for="netwerk_afstand" class="form-label">Geschatte afstand (m)</label>
                        <input type="text" name="netwerk_afstand" id="netwerk_afstand" class="form-control" value="<?= htmlspecialchars($ruimte['netwerk_afstand']) ?>">
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- FOTOâ€™S -->
        <div id="fotos" class="section-anchor card shadow-sm mb-4">
          <div class="card-header bg-success text-white">
            <i class="bi bi-camera"></i> Fotoâ€™s van de ruimte
          </div>
          <div class="card-body">
            <input type="file" name="foto[]" class="form-control" accept="image/*" capture="environment" multiple onchange="previewImages(event)">
            <div id="preview" class="d-flex flex-wrap mt-3 gap-2"></div>

            <?php if ($isEdit && !empty($fotos)): ?>
                <div class="mt-4">
                  <h6>Bestaande fotoâ€™s</h6>
                  <div class="d-flex flex-wrap gap-2" id="bestaande-fotos-container">
                    <?php foreach ($fotos as $foto): ?>
                      <div class="position-relative d-inline-block" id="foto-container-<?= $foto['id'] ?>">
                        <img src="<?= htmlspecialchars($foto['pad']) ?>" alt="Foto"
                             style="height:100px;width:auto;border-radius:4px;" class="border p-1">
                        <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1 delete-foto-btn"
                                data-foto-id="<?= $foto['id'] ?>" style="line-height: 1;">
                          <i class="bi bi-x"></i>
                        </button>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Knoppenbalk -->
        <div class="d-flex justify-content-between align-items-center mb-5 mt-4 pt-3 border-top">
            <div>
                <button type="submit" name="submit_action" value="save_and_back" class="btn btn-primary"><i class="bi bi-save"></i> Opslaan en Terug</button>
                <button type="submit" name="submit_action" value="save_and_new" class="btn btn-secondary"><i class="bi bi-plus-circle"></i> Opslaan en Nieuwe Ruimte</button>
            </div>
            <a href="?page=bewerk&id=<?= (int)($ruimte['verslag_id'] ?? 0) ?>" class="btn btn-outline-secondary">
                Terug zonder Opslaan
            </a>
        </div>
      </form>
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
  // Foto-preview met verwijder-optie
  const fileInput = document.querySelector('input[type="file"][name="foto[]"]');
  const previewContainer = document.getElementById('preview');
  const dataTransfer = new DataTransfer();

  fileInput.addEventListener('change', (event) => {
    // Voeg nieuwe bestanden toe aan de DataTransfer
    for (const file of event.target.files) {
      dataTransfer.items.add(file);
    }
    // Update de bestanden van de input en render de previews
    fileInput.files = dataTransfer.files;
    renderPreviews();
  });

  function renderPreviews() {
    previewContainer.innerHTML = '';
    Array.from(dataTransfer.files).forEach((file, index) => {
      const reader = new FileReader();
      reader.onload = (e) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'position-relative d-inline-block';

        const img = document.createElement('img');
        img.src = e.target.result;
        img.style.height = '100px';
        img.style.borderRadius = '4px';
        img.className = 'border p-1';

        const removeBtn = document.createElement('button');
        removeBtn.className = 'btn btn-danger btn-sm position-absolute top-0 end-0 m-1';
        removeBtn.innerHTML = '<i class="bi bi-x"></i>';
        removeBtn.style.lineHeight = '1';
        removeBtn.onclick = () => {
          dataTransfer.items.remove(index);
          fileInput.files = dataTransfer.files; // Update de input
          renderPreviews(); // Her-render de previews
        };

        wrapper.appendChild(img);
        wrapper.appendChild(removeBtn);
        previewContainer.appendChild(wrapper);
      };
      reader.readAsDataURL(file);
    });
  }
</script>

<script>
// Smooth scroll en scrollspy voor het zijmenu
document.addEventListener('DOMContentLoaded', () => {
  const links = document.querySelectorAll('#sideMenu a');
  const sections = Array.from(links).map(a => document.querySelector(a.getAttribute('href')));

  // Scrollspy
  function onScroll() {
    const scrollPosition = window.scrollY + 100; // Offset voor header
    let activeIndex = 0;
    sections.forEach((sec, i) => { if (sec && sec.offsetTop <= scrollPosition) { activeIndex = i; } });
    links.forEach((link, i) => { link.classList.toggle('active', i === activeIndex); });
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll(); // Eerste check bij laden
});

// AJAX Form Submission
document.addEventListener('DOMContentLoaded', function() {
    const toastElement = document.getElementById('ajax-toast');
    const toast = new bootstrap.Toast(toastElement);

    function showToast(title, message, isSuccess) {
        document.getElementById('toast-title').textContent = title;
        document.getElementById('toast-body').innerHTML = message;
        toastElement.classList.remove('bg-success-subtle', 'bg-danger-subtle');
        toastElement.classList.add(isSuccess ? 'bg-success-subtle' : 'bg-danger-subtle');
        toast.show();
    }

    const form = document.getElementById('ruimte-form');
    if (form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();

            // Bepaal welke knop is geklikt. event.submitter is de moderne manier.
            const saveButton = event.submitter || this.querySelector('button[type="submit"]');
            const originalButtonText = saveButton.innerHTML;
            saveButton.disabled = true;
            saveButton.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Opslaan...`;

            // Schakel andere submit knoppen uit om dubbele submits te voorkomen
            form.querySelectorAll('button[type="submit"]').forEach(btn => {
                if (btn !== saveButton) {
                    btn.disabled = true;
                }
            });

            const formData = new FormData(form);

            let isSuccess = false;

            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                isSuccess = data.success;
                showToast(data.success ? 'Succes' : 'Fout', data.message, data.success);
                
                if (data.redirect) {
                    form.dataset.redirecting = true; // Markeer dat we gaan redirecten
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1500); 
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Fout', 'Er is een onverwachte fout opgetreden.', false);
            })
            .finally(() => {
                if (!form.dataset.redirecting) {
                    saveButton.disabled = false;
                    saveButton.innerHTML = originalButtonText;
                    form.querySelectorAll('button[type="submit"]').forEach(btn => btn.disabled = false);
                }

                if(isSuccess) {
                    fileInput.value = null;
                    dataTransfer.items.clear();
                    renderPreviews();
                }
            });
        });
    }

    // Delegated event listener for deleting existing photos
    document.addEventListener('click', function(event) {
        const deleteButton = event.target.closest('.delete-foto-btn');
        if (deleteButton) {
            const fotoId = deleteButton.dataset.fotoId;
            if (!confirm('Weet je zeker dat je deze foto wilt verwijderen?')) {
                return;
            }

            fetch(`?page=foto_delete&id=${fotoId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: new URLSearchParams({ csrf_token: window.getCsrfToken() })
            })
            .then(response => response.json())
            .then(data => {
                showToast(data.success ? 'Succes' : 'Fout', data.message, data.success);
                if (data.success) {
                    const container = document.getElementById(`foto-container-${fotoId}`);
                    if (container) {
                        container.remove();
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Fout', 'Kon de foto niet verwijderen.', false);
            });
        }
    });
});
</script>


<?php include_once 'layout/footer.php'; ?>


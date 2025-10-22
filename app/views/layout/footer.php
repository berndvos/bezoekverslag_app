  <!-- EINDE PAGINA-INHOUD -->
  <footer class="py-3 my-4">
    <p class="text-center text-muted">
      © 2025 Bezoekverslag App
    </p>
  </footer>
</div>

<!-- Cookie Banner -->
<div id="cookie-banner" class="alert alert-light alert-dismissible fade show fixed-bottom m-3 border shadow-sm" role="alert" style="display: none; z-index: 1050;">
  <div class="d-flex justify-content-between align-items-center">
    <p class="mb-0 me-3 small">
      <i class="bi bi-info-circle"></i> Wij gebruiken cookies om uw gebruikerservaring te verbeteren, bijvoorbeeld door uw inloggegevens te onthouden.
    </p>
    <button type="button" class="btn btn-primary btn-sm" id="accept-cookies">Akkoord</button>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const cookieBanner = document.getElementById('cookie-banner');
  const acceptButton = document.getElementById('accept-cookies');

  if (!localStorage.getItem('cookies_accepted')) { cookieBanner.style.display = 'block'; }
  acceptButton.addEventListener('click', () => { localStorage.setItem('cookies_accepted', 'true'); cookieBanner.remove(); });
});
</script>
</body>
</html>

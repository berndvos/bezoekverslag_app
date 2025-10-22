# Bezoekverslag Webapp (Skelet)

Dit is een minimalistisch PHP-projectskelet voor de Bezoekverslag-app.

## Installatie
1. Maak een database `bezoekverslag` aan en voer `schema.sql` uit.
2. Pas `config/config.php` en `config/database.php` aan (DB en SMTP).
3. Plaats de map in je webroot en navigeer naar `/bezoekverslag_app/public/`.
4. Zorg dat `public/uploads/` schrijfbaar is (chmod 0777 in dev).

## Composer (optioneel)
Gebruik Composer voor libs zoals PHPMailer of DomPDF.

## Routing
Eenvoudige GET-parameter `page`:
- `dashboard` (default)
- `nieuw`
- `bewerk&id={verslag_id}`
- `ruimte&id={ruimte_id}&verslag={verslag_id}`
- `ruimte_new&verslag={verslag_id}`
- `upload` (POST, multipart)

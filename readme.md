# Bezoekverslag App

Een webapplicatie voor het digitaal aanmaken, beheren en delen van bezoekverslagen. Deze tool is ontworpen om het proces van klantbezoeken en technische opnames te stroomlijnen, van het eerste contact tot de definitieve PDF.

## Belangrijkste Features

*   **Gebruikersbeheer:** Meerdere rollen (Admin, Poweruser, Accountmanager, Viewer) met verschillende rechten.
*   **Verslagbeheer:** Creëer, bewerk en verwijder bezoekverslagen via een gebruiksvriendelijke interface.
*   **Dynamische Velden:** Uitgebreide formulieren voor relatiegegevens, contactpersonen, wensen, eisen en installatiedetails.
*   **Ruimtes & Foto's:** Voeg per verslag meerdere ruimtes toe met specifieke details en upload foto's per ruimte.
*   **PDF Generatie:** Genereer met één klik een professioneel PDF-document van het volledige bezoekverslag.
*   **Klantportaal:** Geef klanten beveiligde, beperkte toegang tot hun eigen verslag om mee te lezen of specifieke velden aan te vullen.
*   **Huisstijl:** Pas het logo en de primaire kleuren van de applicatie aan via het admin-paneel.
*   **Onderhoud & Updates:** Ingebouwde tools voor database back-ups, logs en een updater die nieuwe versies van GitHub kan installeren.

## Technische Vereisten

*   Webserver (Apache, Nginx, of vergelijkbaar)
*   PHP 7.4 of hoger
*   MySQL / MariaDB database
*   Composer voor het beheren van PHP-dependencies
*   PHP extensies: `pdo_mysql`, `gd`, `mbstring`, `zip` (voor updates/downloads)

---

## Installatie Instructies

Volg deze stappen om de applicatie op een nieuwe server te installeren.

### 1. Repository & Dependencies

1.  Clone de repository naar je webserver:
    ```bash
    git clone https://github.com/yielder-bv/bezoekverslag-app.git
    cd bezoekverslag-app
    ```
2.  Installeer de PHP dependencies met Composer. Dit installeert o.a. DomPDF en PHPMailer.
    ```bash
    composer install --no-dev --optimize-autoloader
    ```

### 2. Webserver Configuratie

Configureer je webserver (Apache of Nginx) om de `/public` map als de **Document Root** te gebruiken. Dit is een cruciale beveiligingsstap om te voorkomen dat gevoelige bestanden (zoals `.env` en de `vendor` map) direct via het web toegankelijk zijn.

**Voorbeeld Apache Virtual Host:**
```apache
<VirtualHost *:80>
    ServerName bezoekverslag.local
    DocumentRoot "/pad/naar/je/project/bezoekverslag-app/public"

    <Directory "/pad/naar/je/project/bezoekverslag-app/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 3. Database Voorbereiden

Maak een lege MySQL/MariaDB database aan voor de applicatie. Onthoud de databasenaam, gebruikersnaam en het wachtwoord.

### 4. Web-Installer Uitvoeren

1.  Navigeer in je browser naar de URL van je applicatie, gevolgd door `/install.php`. Bijvoorbeeld: `http://bezoekverslag.local/install.php`.
2.  Je wordt begroet door het installatiescherm. Vul hier de volgende gegevens in:
    *   **Database Instellingen:** De gegevens van de zojuist aangemaakte database.
    *   **Admin Account:** De gegevens voor het eerste beheeraccount.
3.  Klik op "Installeer Applicatie". Het script zal nu:
    *   Het `.env` configuratiebestand aanmaken.
    *   De databasetabellen importeren.
    *   Je admin-account aanmaken.
    *   Een `install.lock` bestand plaatsen om herinstallatie te blokkeren.

### 5. Installatie Afronden (Belangrijk!)

Na een succesvolle installatie krijg je een melding. **Verwijder nu direct het `public/install.php` bestand van je server.**

```bash
# Vanuit de hoofdmap van je project
rm public/install.php
```

Dit is een essentiële stap om te voorkomen dat anderen de installatie opnieuw kunnen uitvoeren.

### 6. Maprechten Controleren

Zorg ervoor dat de webserver schrijfrechten heeft op de volgende mappen. Deze mappen worden gebruikt voor het opslaan van uploads, PDF's, back-ups en andere tijdelijke bestanden.

*   `storage/`
*   `public/uploads/`

Op een Linux-server kun je dit meestal instellen met:
```bash
sudo chown -R www-data:www-data storage public/uploads
sudo chmod -R 775 storage public/uploads
```
*(Vervang `www-data` door de juiste gebruiker van je webserver, zoals `apache` of `nginx`)*.

---

De installatie is nu voltooid! Je kunt inloggen op de applicatie met het admin-account dat je tijdens de installatie hebt aangemaakt.


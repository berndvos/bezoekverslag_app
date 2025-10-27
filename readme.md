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

<div style="background-color: #ffdddd; border-left: 6px solid #f44336; padding: 15px; margin-bottom: 15px;">
    <h3><strong>WAARSCHUWING: Kritieke Configuratieproblemen</strong></h3>
    <p>De huidige configuratie bevat problemen die de installatie en werking van de applicatie beïnvloeden:</p>
    <ol>
        <li><strong>Installatie is defect:</strong> Het benodigde database-schemabestand <code>config/schema.sql</code> ontbreekt, waardoor de web-installer zal falen bij het aanmaken van de databasetabellen.</li>
        <li><strong>Vaste `BASE_URL`:</strong> Het configuratiebestand <code>config/config.php</code> bevat een vastgelegde <code>BASE_URL</code> (<code>/yielder/public/</code>). Als de applicatie niet in deze specifieke submap draait, zullen links en resources niet correct werken. Dit moet handmatig aangepast worden.</li>
    </ol>
    <p>De onderstaande stappen zijn de bedoeling, maar zullen pas volledig werken nadat bovenstaande problemen zijn opgelost.</p>
</div>

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

Configureer je webserver (Apache of Nginx) om de `/public` map als de **Document Root** te gebruiken. Dit is een cruciale beveiligingsstap.

**Belangrijk:** Controleer na de installatie het bestand `config/config.php`. De `BASE_URL` is hier vastgelegd op `/yielder/public/`. Pas deze waarde aan naar de correcte submap van jouw installatie, of naar `/` als de app in de root van een domein draait.

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
2.  Vul de gevraagde database- en admin-gegevens in.
3.  Klik op "Installeer Applicatie". Het script zal proberen het volgende te doen:
    *   Het `.env` configuratiebestand aanmaken met je databasegegevens.
    *   De databasetabellen importeren (deze stap zal **falen** omdat `config/schema.sql` ontbreekt).
    *   Je admin-account aanmaken.
    *   Een `install.lock` bestand aanmaken in de `storage/` map om herinstallatie te blokkeren.

### 5. Configuratie na Installatie

Na het uitvoeren van de installer (ook als deze faalt), is er een `.env` bestand aangemaakt in de hoofdmap van het project.
*   **E-mail (SMTP):** Open het `.env` bestand. De SMTP-instellingen voor het versturen van e-mail zijn aanwezig maar leeg. Vul deze gegevens in om e-mailfunctionaliteit (zoals wachtwoord-resets) te activeren.
*   **BASE_URL:** Vergeet niet de `BASE_URL` in `config/config.php` te controleren en aan te passen.

### 6. Installatie Afronden (Belangrijk!)

Na een succesvolle installatie (wanneer de problemen zijn opgelost), **verwijder direct het `public/install.php` bestand van je server.**

```bash
# Vanuit de hoofdmap van je project
rm public/install.php
```

### 7. Maprechten Controleren

Zorg ervoor dat de webserver schrijfrechten heeft op de volgende mappen:
*   `storage/`
*   `public/uploads/`

Op een Linux-server kun je dit meestal instellen met:
```bash
sudo chown -R www-data:www-data storage public/uploads
sudo chmod -R 775 storage public/uploads
```
*(Vervang `www-data` door de juiste gebruiker van je webserver, zoals `apache` of `nginx`)*.


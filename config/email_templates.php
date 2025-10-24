<?php
// config/email_templates.php
return array (
  'password_reset' => 
  array (
    'subject' => 'Wachtwoord resetten',
    'body' => '<p>Beste {user_fullname},</p>
<p>Klik op de onderstaande link om je wachtwoord te resetten (1 uur geldig):</p>
<p><a href="{reset_link}">{reset_link}</a></p>
<p>Met vriendelijke groet,<br>Bezoekverslag App</p>',
  ),
  'client_update' => 
  array (
    'subject' => 'Update van klant: {project_title}',
    'body' => '<p>Beste {am_fullname},</p>
<p>De klant <strong>{klantnaam}</strong> heeft zojuist wijzigingen doorgevoerd in het bezoekverslag voor het project \'<strong>{project_title}</strong>\'.</p>
<p>Log in op de Bezoekverslag App om de wijzigingen te bekijken.</p>',
  ),
  'new_user_created' => 
  array (
    'subject' => 'Welkom bij de Bezoekverslag App',
    'body' => '<p>Beste {user_fullname},</p>
<p>Er is een account voor u aangemaakt. U kunt inloggen met de volgende gegevens:</p>
<ul>
    <li><strong>Login pagina:</strong> <a href="{login_link}">{login_link}</a></li>
    <li><strong>E-mailadres:</strong> {user_email}</li>
    <li><strong>Wachtwoord:</strong> {user_password}</li>
</ul>
<p>Het wordt sterk aangeraden om dit wachtwoord zo snel mogelijk te wijzigen via uw profielpagina na het inloggen.</p>
<p>Met vriendelijke groet,<br>De Beheerder</p>',
  ),
  'new_client_login' => 
  array (
    'subject' => 'Toegang tot het klantportaal voor {project_title}',
    'body' => '<p>Beste {client_name},</p>
<p>Er is een account voor u aangemaakt voor het project \'<strong>{project_title}</strong>\'.</p>
<p>U kunt inloggen met de volgende gegevens:</p>
<ul>
    <li><strong>Login pagina:</strong> <a href="{login_link}">{login_link}</a></li>
    <li><strong>E-mailadres:</strong> {login_email}</li>
    <li><strong>Eenmalig wachtwoord:</strong> {login_password}</li>
</ul>
<p>Met vriendelijke groet,<br>{am_name}</p>',
  ),
  'client_portal_extended' => 
  array (
    'subject' => 'Uw toegang tot het klantportaal is verlengd',
    'body' => '<p>Beste {client_name},</p>
<p>Uw toegang tot het klantportaal voor het project \'<strong>{project_title}</strong>\' is verlengd en is nu geldig tot {expiry_date}.</p>
<p>U kunt inloggen via: <a href="{login_link}">{login_link}</a></p>',
  ),
  '2fa_code' =>
  array (
    'subject' => 'Uw verificatiecode voor de Bezoekverslag App',
    'body' => '<p>Hallo,</p><p>Uw verificatiecode is: <strong>{2fa_code}</strong></p><p>Deze code is 15 minuten geldig. Gebruik deze code om het inloggen te voltooien.</p><p>Als u niet heeft geprobeerd in te loggen, kunt u deze e-mail negeren.</p>',
  ),
  'collaboration_invite' => 
  array (
    'subject' => 'Uitnodiging om samen te werken aan: {project_title}',
    'body' => '<p>Beste {collaborator_name},</p>
<p>U bent door <strong>{owner_name}</strong> uitgenodigd om samen te werken aan het bezoekverslag voor het project \'<strong>{project_title}</strong>\'.</p>
<p>U kunt het verslag nu bekijken en bewerken via uw dashboard.</p>
<p><a href="{verslag_link}">Klik hier om direct naar het verslag te gaan.</a></p>
<p>Met vriendelijke groet,<br>Bezoekverslag App</p>',
  ),
  'admin_new_user_notification' => 
  array (
    'subject' => 'Nieuwe gebruiker registratie',
    'body' => '<p>Beste {admin_name},</p>
<p>Er is een nieuwe registratie voor de Bezoekverslag App:</p>
<ul>
    <li><strong>Naam:</strong> {user_fullname}</li>
    <li><strong>E-mail:</strong> {user_email}</li>
</ul>
<p>U kunt deze registratie goedkeuren of afwijzen via het admin panel: <a href="{approval_link}">{approval_link}</a></p>',
  ),
  'user_approved_notification' => 
  array (
    'subject' => 'Uw account is goedgekeurd',
    'body' => '<p>Beste {user_fullname},</p><p>Uw account voor de Bezoekverslag App is goedgekeurd. U kunt nu inloggen via de onderstaande link:</p><p><a href="{login_link}">{login_link}</a></p><p>Met vriendelijke groet,<br>De Beheerder</p>',
  ),
);
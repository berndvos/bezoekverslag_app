<?php
// config/session.php

// Forceer het gebruik van cookies voor sessies, niet via URL's.
ini_set('session.use_only_cookies', 1);

// Stel security flags in voor de sessiecookie.
// HttpOnly: De cookie is niet toegankelijk via client-side scripts (beschermt tegen XSS).
ini_set('session.cookie_httponly', 1);

// Secure: Verstuur de cookie alleen over een beveiligde (HTTPS) verbinding.
// Zet dit op 1 in productie. Voor een lokale 'http://' omgeving moet dit 0 zijn.
ini_set('session.cookie_secure', ($_ENV['APP_ENV'] ?? 'development') === 'production' ? 1 : 0);

// SameSite: Voorkomt dat de browser de cookie meestuurt met cross-site requests (beschermt tegen CSRF).
ini_set('session.cookie_samesite', 'Strict');

// Use Strict Mode: De server accepteert alleen sessie-ID's die door de server zelf zijn gegenereerd.
ini_set('session.use_strict_mode', 1);

session_start();
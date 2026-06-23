<?php
// Configuration SMTP pour CourtLinker (Hostinger)
// Recopiez ce fichier sous le nom 'smtp-config.php' et mettez-y votre mot de passe.
// Ne validez jamais le fichier 'smtp-config.php' contenant votre mot de passe sur Git/GitHub.

define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 465); // Port recommandé pour SSL. Si échec, essayez 587 (TLS).
define('SMTP_SECURE', 'ssl'); // 'ssl' pour port 465, 'tls' pour port 587.
define('SMTP_USER', 'contact@courtlinker.com');
define('SMTP_PASS', 'VOTRE_MOT_DE_PASSE_ICI'); // Remplacez par le vrai mot de passe e-mail

define('MAIL_FROM', 'contact@courtlinker.com');
define('MAIL_FROM_NAME', 'CourtLinker');
define('MAIL_TO', 'contact@courtlinker.com');

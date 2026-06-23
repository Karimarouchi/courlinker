<?php
/**
 * Backend d'envoi SMTP pour le formulaire d'accès anticipé.
 * Utilise PHPMailer et la configuration SMTP Hostinger.
 */

// Permettre les requêtes CORS si nécessaire (facultatif mais propre)
header('Content-Type: application/json; charset=UTF-8');

// Empêcher l'accès direct en GET
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée. Veuillez utiliser POST.']);
    exit;
}

// Protection honeypot contre le spam
if (!empty($_POST['_honey'])) {
    // Répondre un faux succès pour tromper le bot
    echo json_encode(['status' => 'success', 'message' => 'Merci, votre inscription a bien été enregistrée.']);
    exit;
}

// Charger la configuration SMTP
$config_path = __DIR__ . '/smtp-config.php';
if (!file_exists($config_path)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Fichier de configuration SMTP manquant.']);
    exit;
}
require_once $config_path;

// Inclure PHPMailer
require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Récupérer et assainir les champs
$prenom = isset($_POST['prenom']) ? htmlspecialchars(trim($_POST['prenom'])) : '';
$nom = isset($_POST['nom']) ? htmlspecialchars(trim($_POST['nom'])) : '';
$email = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL) : '';
$ville = isset($_POST['ville']) ? htmlspecialchars(trim($_POST['ville'])) : '';

// Validation des champs obligatoires
if (empty($prenom) || empty($nom) || empty($email) || empty($ville)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Veuillez remplir tous les champs obligatoires.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Adresse e-mail invalide.']);
    exit;
}

// ---- EMAIL 1 : ADMIN NOTIFICATION ----
$admin_body = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Nouvel accès anticipé - CourtLinker</title>
</head>
<body style="margin:0;padding:0;background-color:#0e0e0e;font-family:\'Manrope\', \'Helvetica Neue\', Helvetica, Arial, sans-serif;color:#ffffff;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color:#0e0e0e;padding:40px 20px;">
        <tr>
            <td align="center">
                <table width="600" border="0" cellspacing="0" cellpadding="0" style="background-color:#131313;border:1px solid #262626;border-radius:16px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.5);">
                    <!-- Header -->
                    <tr>
                        <td align="center" style="padding:40px 40px 20px 40px;border-bottom:1px solid #262626;background-color:#191919;">
                            <img src="https://courtlinker.com/LOGOG%20COPIE.png" alt="CourtLinker" width="80" style="display:block;margin-bottom:15px;border-radius:10px;">
                            <h1 style="margin:0;font-size:22px;color:#EDAB18;font-family:\'Epilogue\', Arial, sans-serif;font-weight:800;letter-spacing:-0.02em;">COURTLINKER</h1>
                            <p style="margin:5px 0 0 0;font-size:14px;color:#ababab;text-transform:uppercase;letter-spacing:0.1em;">Nouvel accès anticipé</p>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding:40px;">
                            <p style="margin:0 0 25px 0;font-size:16px;line-height:1.6;color:#ffffff;">
                                Un nouvel utilisateur vient de s\'inscrire pour recevoir un accès anticipé à CourtLinker :
                            </p>
                            
                            <!-- Table of details -->
                            <table width="100%" border="0" cellspacing="0" cellpadding="12" style="border-collapse:collapse;margin-bottom:30px;background-color:#191919;border-radius:12px;overflow:hidden;">
                                <tr style="border-bottom:1px solid #262626;">
                                    <td width="40%" style="font-size:13px;font-weight:bold;color:#EDAB18;text-transform:uppercase;letter-spacing:0.05em;padding-left:15px;">Prénom &amp; Nom</td>
                                    <td width="60%" style="font-size:15px;color:#ffffff;font-weight:600;padding-right:15px;">' . $prenom . ' ' . $nom . '</td>
                                </tr>
                                <tr style="border-bottom:1px solid #262626;">
                                    <td style="font-size:13px;font-weight:bold;color:#EDAB18;text-transform:uppercase;letter-spacing:0.05em;padding-left:15px;">Email</td>
                                    <td style="font-size:15px;color:#ffffff;padding-right:15px;"><a href="mailto:' . $email . '" style="color:#EDAB18;text-decoration:none;">' . $email . '</a></td>
                                </tr>
                                <tr style="border-bottom:1px solid #262626;">
                                    <td style="font-size:13px;font-weight:bold;color:#EDAB18;text-transform:uppercase;letter-spacing:0.05em;padding-left:15px;">Ville</td>
                                    <td style="font-size:15px;color:#ffffff;padding-right:15px;">' . $ville . '</td>
                                </tr>
                            </table>

                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="center">
                                        <a href="mailto:' . $email . '" style="display:inline-block;padding:14px 30px;background:linear-gradient(135deg, #F5C842, #EDAB18);color:#000000;text-decoration:none;border-radius:10px;font-weight:bold;font-size:15px;box-shadow:0 4px 15px rgba(237,171,24,0.3);">Écrire un e-mail</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding:25px 40px;border-top:1px solid #262626;background-color:#0b0b0c;font-size:12px;color:#71717a;">
                            Ce message a été envoyé automatiquement depuis le formulaire d\'accès anticipé de CourtLinker.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
';

// ---- EMAIL 2 : USER WELCOME EMAIL ----
$user_body = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bienvenue dans l\'accès anticipé - CourtLinker</title>
</head>
<body style="margin:0;padding:0;background-color:#0e0e0e;font-family:\'Manrope\', \'Helvetica Neue\', Helvetica, Arial, sans-serif;color:#ffffff;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color:#0e0e0e;padding:40px 20px;">
        <tr>
            <td align="center">
                <table width="600" border="0" cellspacing="0" cellpadding="0" style="background-color:#131313;border:1px solid #262626;border-radius:16px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.5);">
                    <!-- Header -->
                    <tr>
                        <td align="center" style="padding:40px 40px 20px 40px;border-bottom:1px solid #262626;background-color:#191919;">
                            <img src="https://courtlinker.com/LOGOG%20COPIE.png" alt="CourtLinker" width="80" style="display:block;margin-bottom:15px;border-radius:10px;">
                            <h1 style="margin:0;font-size:22px;color:#EDAB18;font-family:\'Epilogue\', Arial, sans-serif;font-weight:800;letter-spacing:-0.02em;">COURTLINKER</h1>
                            <p style="margin:5px 0 0 0;font-size:14px;color:#ababab;text-transform:uppercase;letter-spacing:0.1em;">Accès anticipé validé</p>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding:40px;">
                            <h2 style="margin:0 0 15px 0;font-size:20px;color:#ffffff;font-family:\'Epilogue\', Arial, sans-serif;">Bonjour ' . $prenom . ',</h2>
                            <p style="margin:0 0 20px 0;font-size:15px;line-height:1.65;color:#d4d4d8;">
                                Félicitations ! Votre demande d\'accès anticipé pour <strong>CourtLinker</strong> a bien été enregistrée. Vous faites désormais partie de la liste exclusive de nos membres fondateurs.
                            </p>
                            <p style="margin:0 0 20px 0;font-size:15px;line-height:1.65;color:#d4d4d8;">
                                <strong>Ce que cela signifie pour vous :</strong>
                            </p>
                            <ul style="margin:0 0 25px 0;padding-left:20px;font-size:14px;line-height:1.6;color:#d4d4d8;">
                                <li>Vous recevrez une notification privée dès le lancement officiel de l\'application dans votre ville (' . $ville . ').</li>
                                <li>Vous bénéficierez de fonctionnalités premium gratuites réservées aux premiers inscrits.</li>
                                <li>Vous pourrez créer votre profil de joueur en priorité et réserver vos terrains et cours préférés de Tennis &amp; Padel.</li>
                            </ul>
                            
                            <p style="margin:0 0 30px 0;font-size:15px;line-height:1.65;color:#d4d4d8;">
                                Nous travaillons dur pour finaliser l\'application et nous avons hâte de vous retrouver sur les courts !
                            </p>

                            <p style="margin:0 0 30px 0;font-size:15px;line-height:1.65;color:#d4d4d8;">
                                À très bientôt,<br>
                                <strong>L\'équipe CourtLinker</strong>
                            </p>

                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="center">
                                        <a href="https://courtlinker.com" style="display:inline-block;padding:14px 30px;background:linear-gradient(135deg, #F5C842, #EDAB18);color:#000000;text-decoration:none;border-radius:10px;font-weight:bold;font-size:15px;box-shadow:0 4px 15px rgba(237,171,24,0.3);">Visiter notre site web</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding:25px 40px;border-top:1px solid #262626;background-color:#0b0b0c;font-size:12px;color:#71717a;">
                            Vous recevez cet e-mail suite à votre inscription sur CourtLinker.<br>
                            © 2026 CourtLinker. Tous droits réservés.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
';

// Initialisation et envoi via PHPMailer
try {
    // 1. Envoyer le mail à l'ADMIN de CourtLinker
    $mailAdmin = new PHPMailer(true);
    $mailAdmin->CharSet = 'UTF-8';
    
    // Config SMTP
    $mailAdmin->isSMTP();
    $mailAdmin->Host       = SMTP_HOST;
    $mailAdmin->SMTPAuth   = true;
    $mailAdmin->Username   = SMTP_USER;
    $mailAdmin->Password   = SMTP_PASS;
    
    if (SMTP_SECURE === 'ssl') {
        $mailAdmin->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else {
        $mailAdmin->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }
    $mailAdmin->Port = SMTP_PORT;

    // Destinataires
    $mailAdmin->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mailAdmin->addAddress(MAIL_TO);
    $mailAdmin->addReplyTo($email, $prenom . ' ' . $nom);

    // Contenu
    $mailAdmin->isHTML(true);
    $mailAdmin->Subject = 'Nouvel acces anticipe - CourtLinker';
    $mailAdmin->Body    = $admin_body;
    $mailAdmin->AltBody = "Nouvel acces anticipe - CourtLinker\n\nNom: $prenom $nom\nEmail: $email\nVille: $ville";

    $mailAdmin->send();

    // 2. Envoyer le mail de bienvenue à l'UTILISATEUR
    $mailUser = new PHPMailer(true);
    $mailUser->CharSet = 'UTF-8';
    
    // Config SMTP
    $mailUser->isSMTP();
    $mailUser->Host       = SMTP_HOST;
    $mailUser->SMTPAuth   = true;
    $mailUser->Username   = SMTP_USER;
    $mailUser->Password   = SMTP_PASS;
    
    if (SMTP_SECURE === 'ssl') {
        $mailUser->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else {
        $mailUser->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }
    $mailUser->Port = SMTP_PORT;

    // Destinataires
    $mailUser->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mailUser->addAddress($email, $prenom . ' ' . $nom);

    // Contenu
    $mailUser->isHTML(true);
    $mailUser->Subject = 'Acces anticipe valide - CourtLinker';
    $mailUser->Body    = $user_body;
    $mailUser->AltBody = "Bonjour $prenom,\n\nFélicitations ! Votre demande d'accès anticipé pour CourtLinker a bien été enregistrée. Vous recevrez une notification privée dès le lancement officiel dans votre ville ($ville).\n\nL'équipe CourtLinker";

    $mailUser->send();

    // Réponse JSON de succès
    echo json_encode(['status' => 'success', 'message' => 'Merci, votre inscription a bien été enregistrée.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Une erreur est survenue lors de l\'envoi de l\'e-mail.',
        'debug' => $mailAdmin->ErrorInfo
    ]);
}

<?php
/**
 * Backend d'envoi SMTP pour le formulaire de candidature coach.
 * Champs : prenom, nom, ville, email
 * Utilise PHPMailer et la configuration SMTP Hostinger.
 */

header('Content-Type: application/json; charset=UTF-8');

// Empêcher l'accès direct en GET
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée. Veuillez utiliser POST.']);
    exit;
}

// Protection honeypot contre le spam
if (!empty($_POST['_honey'])) {
    echo json_encode(['status' => 'success', 'message' => 'Merci, votre candidature a bien été envoyée.']);
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
$nom    = isset($_POST['nom'])    ? htmlspecialchars(trim($_POST['nom']))    : '';
$ville  = isset($_POST['ville'])  ? htmlspecialchars(trim($_POST['ville']))  : '';
$email  = isset($_POST['email'])  ? filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL) : '';

// Validation des champs obligatoires
if (empty($prenom) || empty($nom) || empty($ville) || empty($email)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Veuillez remplir tous les champs obligatoires.']);
    exit;
}

// Validation longueur minimale
if (mb_strlen($prenom) < 2 || mb_strlen($nom) < 2 || mb_strlen($ville) < 2) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Certains champs sont trop courts (minimum 2 caractères).']);
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
    <title>Nouvelle candidature coach - CourtLinker</title>
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
                            <p style="margin:5px 0 0 0;font-size:14px;color:#ababab;text-transform:uppercase;letter-spacing:0.1em;">Nouvelle Candidature Coach</p>
                        </td>
                    </tr>
                    <!-- Badge -->
                    <tr>
                        <td align="center" style="padding:20px 40px 0 40px;">
                            <span style="display:inline-block;padding:6px 18px;background:linear-gradient(135deg,#F5C842,#EDAB18);border-radius:99px;font-size:12px;font-weight:800;color:#000;text-transform:uppercase;letter-spacing:0.1em;">🎾 Candidature reçue</span>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding:30px 40px 40px 40px;">
                            <p style="margin:0 0 25px 0;font-size:16px;line-height:1.6;color:#ffffff;">
                                Un nouveau coach souhaite rejoindre la communauté CourtLinker. Voici ses coordonnées :
                            </p>

                            <!-- Table of details -->
                            <table width="100%" border="0" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin-bottom:30px;border-radius:12px;overflow:hidden;">
                                <tr style="background-color:#1a1a1a;border-bottom:1px solid #2a2a2a;">
                                    <td width="40%" style="padding:14px 16px;font-size:12px;font-weight:700;color:#EDAB18;text-transform:uppercase;letter-spacing:0.08em;">Prénom &amp; Nom</td>
                                    <td width="60%" style="padding:14px 16px;font-size:15px;color:#ffffff;font-weight:600;">' . $prenom . ' ' . $nom . '</td>
                                </tr>
                                <tr style="background-color:#161616;border-bottom:1px solid #2a2a2a;">
                                    <td style="padding:14px 16px;font-size:12px;font-weight:700;color:#EDAB18;text-transform:uppercase;letter-spacing:0.08em;">Email</td>
                                    <td style="padding:14px 16px;font-size:15px;color:#ffffff;"><a href="mailto:' . $email . '" style="color:#EDAB18;text-decoration:none;">' . $email . '</a></td>
                                </tr>
                                <tr style="background-color:#1a1a1a;">
                                    <td style="padding:14px 16px;font-size:12px;font-weight:700;color:#EDAB18;text-transform:uppercase;letter-spacing:0.08em;">Ville</td>
                                    <td style="padding:14px 16px;font-size:15px;color:#ffffff;">' . $ville . '</td>
                                </tr>
                            </table>

                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="center">
                                        <a href="mailto:' . $email . '" style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg, #F5C842, #EDAB18);color:#000000;text-decoration:none;border-radius:10px;font-weight:800;font-size:15px;box-shadow:0 4px 20px rgba(237,171,24,0.35);letter-spacing:0.01em;">Répondre au coach</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding:20px 40px;border-top:1px solid #262626;background-color:#0b0b0c;font-size:12px;color:#71717a;">
                            Message envoyé automatiquement depuis le formulaire coach de CourtLinker.<br>
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

// ---- EMAIL 2 : CANDIDATE AUTO-RESPONSE ----
$candidate_body = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Candidature reçue - CourtLinker</title>
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
                            <p style="margin:5px 0 0 0;font-size:14px;color:#ababab;text-transform:uppercase;letter-spacing:0.1em;">Confirmation de candidature</p>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding:40px;">
                            <h2 style="margin:0 0 16px 0;font-size:22px;color:#ffffff;font-family:\'Epilogue\', Arial, sans-serif;font-weight:700;">Bonjour ' . $prenom . ' 👋</h2>
                            <p style="margin:0 0 20px 0;font-size:15px;line-height:1.7;color:#d4d4d8;">
                                Merci pour votre intérêt envers <strong style="color:#ffffff;">CourtLinker</strong> ! Nous avons bien reçu votre candidature pour rejoindre notre réseau de coachs partenaires.
                            </p>
                            <p style="margin:0 0 24px 0;font-size:15px;line-height:1.7;color:#d4d4d8;">
                                Notre équipe va examiner votre profil et reviendra vers vous <strong style="color:#EDAB18;">très rapidement</strong> par e-mail pour valider ensemble les modalités de votre inscription.
                            </p>

                            <!-- Recap box -->
                            <table width="100%" border="0" cellspacing="0" cellpadding="0" style="margin:0 0 30px 0;background-color:#191919;border-left:3px solid #EDAB18;border-radius:0 10px 10px 0;">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <p style="margin:0 0 10px 0;font-size:12px;font-weight:700;color:#EDAB18;text-transform:uppercase;letter-spacing:0.1em;">Votre candidature :</p>
                                        <p style="margin:0;font-size:14px;line-height:1.7;color:#d4d4d8;">
                                            <strong style="color:#fff;">Nom :</strong> ' . $prenom . ' ' . $nom . '<br>
                                            <strong style="color:#fff;">Ville :</strong> ' . $ville . '<br>
                                            <strong style="color:#fff;">Email :</strong> ' . $email . '
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 30px 0;font-size:15px;line-height:1.7;color:#d4d4d8;">
                                À très bientôt sur les courts,<br>
                                <strong style="color:#ffffff;">L\'équipe CourtLinker 🎾</strong>
                            </p>

                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="center">
                                        <a href="https://courtlinker.com" style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg, #F5C842, #EDAB18);color:#000000;text-decoration:none;border-radius:10px;font-weight:800;font-size:15px;box-shadow:0 4px 20px rgba(237,171,24,0.35);">Visiter notre site</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding:20px 40px;border-top:1px solid #262626;background-color:#0b0b0c;font-size:12px;color:#71717a;">
                            Vous recevez cet e-mail suite à votre candidature sur CourtLinker.<br>
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
    // Fonction helper pour configurer PHPMailer
    function createMailer() {
        $mail = new PHPMailer(true);
        $mail->CharSet   = 'UTF-8';
        $mail->isSMTP();
        $mail->Host      = SMTP_HOST;
        $mail->SMTPAuth  = true;
        $mail->Username  = SMTP_USER;
        $mail->Password  = SMTP_PASS;
        $mail->SMTPSecure = (SMTP_SECURE === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port      = SMTP_PORT;
        return $mail;
    }

    // 1. Mail ADMIN
    $mailAdmin = createMailer();
    $mailAdmin->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mailAdmin->addAddress(MAIL_TO);
    $mailAdmin->addReplyTo($email, $prenom . ' ' . $nom);
    $mailAdmin->isHTML(true);
    $mailAdmin->Subject = '=?UTF-8?B?' . base64_encode('Nouvelle candidature coach - CourtLinker') . '?=';
    $mailAdmin->Body    = $admin_body;
    $mailAdmin->AltBody = "Nouvelle candidature coach - CourtLinker\n\nNom: $prenom $nom\nEmail: $email\nVille: $ville";
    $mailAdmin->send();

    // 2. Mail CANDIDAT
    $mailCand = createMailer();
    $mailCand->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mailCand->addAddress($email, $prenom . ' ' . $nom);
    $mailCand->isHTML(true);
    $mailCand->Subject = '=?UTF-8?B?' . base64_encode('Candidature reçue - CourtLinker') . '?=';
    $mailCand->Body    = $candidate_body;
    $mailCand->AltBody = "Bonjour $prenom,\n\nNous avons bien reçu votre candidature pour rejoindre CourtLinker en tant que coach à $ville. Notre équipe reviendra vers vous très prochainement.\n\nL'équipe CourtLinker";
    $mailCand->send();

    echo json_encode(['status' => 'success', 'message' => 'Merci, votre candidature a bien été envoyée. Vous allez recevoir un e-mail de confirmation.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Une erreur est survenue lors de l\'envoi. Veuillez réessayer.',
        'debug'   => isset($mailAdmin) ? $mailAdmin->ErrorInfo : $e->getMessage()
    ]);
}

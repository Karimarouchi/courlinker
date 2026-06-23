<?php
/**
 * Backend d'envoi SMTP pour le formulaire de candidature coach.
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
$nom = isset($_POST['nom']) ? htmlspecialchars(trim($_POST['nom'])) : '';
$telephone = isset($_POST['telephone']) ? htmlspecialchars(trim($_POST['telephone'])) : '';
$email = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL) : '';
$ville = isset($_POST['ville']) ? htmlspecialchars(trim($_POST['ville'])) : '';
$zone_intervention = isset($_POST['zone_intervention']) ? htmlspecialchars(trim($_POST['zone_intervention'])) : '';
$sport_enseigne = isset($_POST['sport_enseigne']) ? htmlspecialchars(trim($_POST['sport_enseigne'])) : '';
$annees_experience = isset($_POST['annees_experience']) ? (int)$_POST['annees_experience'] : '';
$tarif_horaire = isset($_POST['tarif_horaire']) ? htmlspecialchars(trim($_POST['tarif_horaire'])) : '';
$disponibilites = isset($_POST['disponibilites']) ? htmlspecialchars(trim($_POST['disponibilites'])) : '';
$bio = isset($_POST['bio']) ? htmlspecialchars(trim($_POST['bio'])) : '';

// Optionnels
$diplomes = isset($_POST['diplomes_certifications']) ? htmlspecialchars(trim($_POST['diplomes_certifications'])) : '';
$instagram = isset($_POST['instagram']) ? filter_var(trim($_POST['instagram']), FILTER_SANITIZE_URL) : '';
$portfolio = isset($_POST['portfolio']) ? filter_var(trim($_POST['portfolio']), FILTER_SANITIZE_URL) : '';

// Gestion des niveaux des joueurs (checkboxes)
$niveaux = [];
if (isset($_POST['niveau_joueurs'])) {
    if (is_array($_POST['niveau_joueurs'])) {
        foreach ($_POST['niveau_joueurs'] as $n) {
            $niveaux[] = htmlspecialchars($n);
        }
    } else {
        $niveaux[] = htmlspecialchars($_POST['niveau_joueurs']);
    }
}
$niveau_joueurs_str = !empty($niveaux) ? implode(', ', $niveaux) : '';

// Validation des champs obligatoires
if (
    empty($prenom) || empty($nom) || empty($telephone) || empty($email) || 
    empty($ville) || empty($zone_intervention) || empty($sport_enseigne) || 
    empty($niveau_joueurs_str) || $annees_experience === '' || 
    empty($disponibilites) || empty($tarif_horaire) || empty($bio)
) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Veuillez remplir tous les champs obligatoires.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Adresse e-mail invalide.']);
    exit;
}

// Fonction de construction des lignes optionnelles pour le tableau admin
function buildOptionalRow($label, $value) {
    if (empty($value)) return '';
    return '<tr style="border-bottom:1px solid #262626;">
        <td style="font-size:13px;font-weight:bold;color:#EDAB18;text-transform:uppercase;letter-spacing:0.05em;padding-left:15px;">' . $label . '</td>
        <td style="font-size:15px;color:#ffffff;padding-right:15px;">' . $value . '</td>
    </tr>';
}

// Préparer les variables pour l'insertion HTML
$diplomes_row = buildOptionalRow('Diplômes / Certifications', nl2br($diplomes));
$instagram_row = buildOptionalRow('Instagram', !empty($instagram) ? '<a href="' . $instagram . '" style="color:#EDAB18;text-decoration:none;" target="_blank">' . $instagram . '</a>' : '');
$portfolio_row = buildOptionalRow('Portfolio', !empty($portfolio) ? '<a href="' . $portfolio . '" style="color:#EDAB18;text-decoration:none;" target="_blank">' . $portfolio . '</a>' : '');

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
                    <!-- Content -->
                    <tr>
                        <td style="padding:40px;">
                            <p style="margin:0 0 25px 0;font-size:16px;line-height:1.6;color:#ffffff;">
                                Un nouveau coach vient de postuler pour rejoindre la communauté CourtLinker. Voici les détails de sa candidature :
                            </p>
                            
                            <!-- Table of details -->
                            <table width="100%" border="0" cellspacing="0" cellpadding="12" style="border-collapse:collapse;margin-bottom:30px;background-color:#191919;border-radius:12px;overflow:hidden;">
                                <tr style="border-bottom:1px solid #262626;">
                                    <td width="40%" style="font-size:13px;font-weight:bold;color:#EDAB18;text-transform:uppercase;letter-spacing:0.05em;padding-left:15px;">Prénom &amp; Nom</td>
                                    <td width="60%" style="font-size:15px;color:#ffffff;font-weight:600;padding-right:15px;">' . $prenom . ' ' . $nom . '</td>
                                </tr>
                                <tr style="border-bottom:1px solid #262626;">
                                    <td style="font-size:13px;font-weight:bold;color:#EDAB18;text-transform:uppercase;letter-spacing:0.05em;padding-left:15px;">Téléphone</td>
                                    <td style="font-size:15px;color:#ffffff;padding-right:15px;"><a href="tel:' . $telephone . '" style="color:#ffffff;text-decoration:none;">' . $telephone . '</a></td>
                                </tr>
                                <tr style="border-bottom:1px solid #262626;">
                                    <td style="font-size:13px;font-weight:bold;color:#EDAB18;text-transform:uppercase;letter-spacing:0.05em;padding-left:15px;">Email</td>
                                    <td style="font-size:15px;color:#ffffff;padding-right:15px;"><a href="mailto:' . $email . '" style="color:#EDAB18;text-decoration:none;">' . $email . '</a></td>
                                </tr>
                                <tr style="border-bottom:1px solid #262626;">
                                    <td style="font-size:13px;font-weight:bold;color:#EDAB18;text-transform:uppercase;letter-spacing:0.05em;padding-left:15px;">Ville</td>
                                    <td style="font-size:15px;color:#ffffff;padding-right:15px;">' . $ville . '</td>
                                </tr>
                                <tr style="border-bottom:1px solid #262626;">
                                    <td style="font-size:13px;font-weight:bold;color:#EDAB18;text-transform:uppercase;letter-spacing:0.05em;padding-left:15px;">Zone d\'intervention</td>
                                    <td style="font-size:15px;color:#ffffff;padding-right:15px;">' . $zone_intervention . '</td>
                                </tr>
                                <tr style="border-bottom:1px solid #262626;">
                                    <td style="font-size:13px;font-weight:bold;color:#EDAB18;text-transform:uppercase;letter-spacing:0.05em;padding-left:15px;">Sport enseigné</td>
                                    <td style="font-size:15px;color:#ffffff;font-weight:bold;padding-right:15px;">' . $sport_enseigne . '</td>
                                </tr>
                                <tr style="border-bottom:1px solid #262626;">
                                    <td style="font-size:13px;font-weight:bold;color:#EDAB18;text-transform:uppercase;letter-spacing:0.05em;padding-left:15px;">Niveau des joueurs</td>
                                    <td style="font-size:15px;color:#ffffff;padding-right:15px;">' . $niveau_joueurs_str . '</td>
                                </tr>
                                <tr style="border-bottom:1px solid #262626;">
                                    <td style="font-size:13px;font-weight:bold;color:#EDAB18;text-transform:uppercase;letter-spacing:0.05em;padding-left:15px;">Années d\'expérience</td>
                                    <td style="font-size:15px;color:#ffffff;padding-right:15px;">' . $annees_experience . ' ans</td>
                                </tr>
                                <tr style="border-bottom:1px solid #262626;">
                                    <td style="font-size:13px;font-weight:bold;color:#EDAB18;text-transform:uppercase;letter-spacing:0.05em;padding-left:15px;">Tarif horaire</td>
                                    <td style="font-size:15px;color:#ffffff;padding-right:15px;font-weight:bold;">' . $tarif_horaire . '</td>
                                </tr>
                                <tr style="border-bottom:1px solid #262626;">
                                    <td style="font-size:13px;font-weight:bold;color:#EDAB18;text-transform:uppercase;letter-spacing:0.05em;padding-left:15px;">Disponibilités</td>
                                    <td style="font-size:15px;color:#ffffff;padding-right:15px;">' . $disponibilites . '</td>
                                </tr>
                                ' . $diplomes_row . '
                                ' . $instagram_row . '
                                ' . $portfolio_row . '
                            </table>

                            <h3 style="margin:0 0 10px 0;font-size:15px;color:#EDAB18;text-transform:uppercase;letter-spacing:0.05em;">Bio du coach</h3>
                            <div style="background-color:#191919;border-left:3px solid #EDAB18;padding:15px;border-radius:0 8px 8px 0;font-size:14px;line-height:1.6;color:#d4d4d8;margin-bottom:30px;white-space:pre-wrap;">' . $bio . '</div>

                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="center">
                                        <a href="mailto:' . $email . '" style="display:inline-block;padding:14px 30px;background:linear-gradient(135deg, #F5C842, #EDAB18);color:#000000;text-decoration:none;border-radius:10px;font-weight:bold;font-size:15px;box-shadow:0 4px 15px rgba(237,171,24,0.3);">Répondre par e-mail</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding:25px 40px;border-top:1px solid #262626;background-color:#0b0b0c;font-size:12px;color:#71717a;">
                            Ce message a été envoyé automatiquement depuis le formulaire d'inscription coach de CourtLinker.
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
                            <h2 style="margin:0 0 15px 0;font-size:20px;color:#ffffff;font-family:\'Epilogue\', Arial, sans-serif;">Bonjour ' . $prenom . ',</h2>
                            <p style="margin:0 0 20px 0;font-size:15px;line-height:1.65;color:#d4d4d8;">
                                Merci pour l\'intérêt que vous portez à <strong>CourtLinker</strong> ! Nous avons bien reçu votre candidature pour rejoindre notre réseau de coachs partenaires de Tennis &amp; Padel.
                            </p>
                            <p style="margin:0 0 20px 0;font-size:15px;line-height:1.65;color:#d4d4d8;">
                                Notre équipe examine actuellement vos informations et votre profil. Nous reviendrons vers vous très rapidement par téléphone ou par e-mail afin de valider ensemble les modalités de votre inscription et votre zone de cours.
                            </p>
                            
                            <table width="100%" border="0" cellspacing="0" cellpadding="0" style="margin:30px 0;background-color:#191919;border-left:3px solid #EDAB18;border-radius:0 8px 8px 0;padding:20px;">
                                <tr>
                                    <td>
                                        <h3 style="margin:0 0 8px 0;font-size:14px;color:#EDAB18;text-transform:uppercase;letter-spacing:0.05em;">Récapitulatif de votre profil :</h3>
                                        <ul style="margin:0;padding-left:20px;font-size:14px;line-height:1.6;color:#d4d4d8;">
                                            <li><strong>Sport :</strong> ' . $sport_enseigne . '</li>
                                            <li><strong>Ville principale :</strong> ' . $ville . '</li>
                                            <li><strong>Tarif horaire indiqué :</strong> ' . $tarif_horaire . '</li>
                                        </ul>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 30px 0;font-size:15px;line-height:1.65;color:#d4d4d8;">
                                À très bientôt sur les courts,<br>
                                <strong>L\'équipe CourtLinker</strong>
                            </p>

                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="center">
                                        <a href="https://courtlinker.com" style="display:inline-block;padding:14px 30px;background:linear-gradient(135deg, #F5C842, #EDAB18);color:#000000;text-decoration:none;border-radius:10px;font-weight:bold;font-size:15px;box-shadow:0 4px 15px rgba(237,171,24,0.3);">Visiter notre site</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding:25px 40px;border-top:1px solid #262626;background-color:#0b0b0c;font-size:12px;color:#71717a;">
                            Vous recevez cet e-mail suite à votre demande d\'inscription sur CourtLinker.<br>
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
    $mailAdmin->addReplyTo($email, $prenom . ' ' . $nom); // Permet de répondre directement au coach

    // Contenu
    $mailAdmin->isHTML(true);
    $mailAdmin->Subject = 'Nouvelle candidature coach - CourtLinker';
    $mailAdmin->Body    = $admin_body;
    $mailAdmin->AltBody = "Nouvelle candidature coach - CourtLinker\n\nNom: $prenom $nom\nEmail: $email\nTéléphone: $telephone\nSport: $sport_enseigne\nVille: $ville\nTarif: $tarif_horaire\nBio: $bio";

    $mailAdmin->send();

    // 2. Envoyer le mail de confirmation de réception au CANDIDAT (Coach)
    $mailCandidate = new PHPMailer(true);
    $mailCandidate->CharSet = 'UTF-8';
    
    // Config SMTP
    $mailCandidate->isSMTP();
    $mailCandidate->Host       = SMTP_HOST;
    $mailCandidate->SMTPAuth   = true;
    $mailCandidate->Username   = SMTP_USER;
    $mailCandidate->Password   = SMTP_PASS;
    
    if (SMTP_SECURE === 'ssl') {
        $mailCandidate->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else {
        $mailCandidate->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }
    $mailCandidate->Port = SMTP_PORT;

    // Destinataires
    $mailCandidate->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mailCandidate->addAddress($email, $prenom . ' ' . $nom);

    // Contenu
    $mailCandidate->isHTML(true);
    $mailCandidate->Subject = 'Candidature reçue - CourtLinker';
    $mailCandidate->Body    = $candidate_body;
    $mailCandidate->AltBody = "Bonjour $prenom,\n\nMerci pour votre intérêt ! Nous avons bien reçu votre candidature pour rejoindre CourtLinker en tant que coach. Notre équipe va l'examiner et reviendra vers vous très prochainement.\n\nL'équipe CourtLinker";

    $mailCandidate->send();

    // Réponse JSON de succès
    echo json_encode(['status' => 'success', 'message' => 'Merci, votre candidature a bien été envoyée.']);

} catch (Exception $e) {
    // Si l'envoi échoue (ex: SMTP indisponible ou mauvais mot de passe)
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Une erreur est survenue lors de l\'envoi de votre candidature par e-mail.',
        'debug' => $mailAdmin->ErrorInfo // Utile pour déboguer au début sur Hostinger
    ]);
}

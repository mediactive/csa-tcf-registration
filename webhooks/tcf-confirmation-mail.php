<?php
/**
 * TCF Confirmation Email Sender - Cron Script
 * Envoie les courriels de confirmation pour les inscriptions TCF payées
 * 
 * CRON: every 5 min - /usr/bin/php /home/leadercs/public_html/webhooks/tcf-confirmation-mail.php
 */

// DEBUG - Afficher les erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "DEBUG 1: Script démarré\n";

// Configuration
define('ADMIN_EMAIL', 'samuelgagnon@leadercsa.com');
define('FROM_EMAIL', 'noreply@leadercsa.com');
define('FROM_NAME', 'Leader CSA - Tests de français');

echo "DEBUG 2: Constantes définies\n";

// Database connection
$configPath = __DIR__ . '/../config3.php';
echo "DEBUG 3: Chemin config = " . $configPath . "\n";
if (!file_exists($configPath)) {
    die("ERREUR: Fichier config3.php introuvable à: " . $configPath);
}
require_once($configPath);

echo "DEBUG 4: Config chargé\n";

// Vérifier $bdd
if (!isset($bdd)) {
    die("ERREUR: Variable \$bdd non définie après require config3.php");
}

echo "DEBUG 5: \$bdd disponible\n";

// PHPMailer
$phpmailerPath = __DIR__ . '/../PHPMailer-6.10.0/src/PHPMailer.php';
echo "DEBUG 6: Chemin PHPMailer = " . $phpmailerPath . "\n";
if (!file_exists($phpmailerPath)) {
    die("ERREUR: PHPMailer introuvable à: " . $phpmailerPath);
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once(__DIR__ . '/../PHPMailer-6.10.0/src/Exception.php');
require_once(__DIR__ . '/../PHPMailer-6.10.0/src/PHPMailer.php');

echo "DEBUG 7: PHPMailer chargé\n";

/**
 * Generate HTML email content for a registration
 */
function generateEmailHTML($registration, $examName, $selectedTests)
{
    $registrationNumber = 'TCF-' . str_pad($registration['id'], 6, '0', STR_PAD_LEFT);

    $html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation d\'inscription - TCF</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
            color: #1D2020;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #FFFFFF;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #1D2020 0%, #2D3131 100%);
            color: #FFFFFF;
            padding: 40px 30px;
            text-align: center;
        }
        .success-icon {
            width: 60px;
            height: 60px;
            background: #28a745;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        .success-icon span {
            color: #FFFFFF;
            font-size: 30px;
        }
        .header h1 {
            font-size: 24px;
            margin: 0 0 10px 0;
        }
        .header p {
            font-size: 16px;
            opacity: 0.9;
            margin: 0;
        }
        .body {
            padding: 30px;
        }
        .registration-number {
            background: #F7F7F7;
            border-left: 4px solid #D00023;
            padding: 15px 20px;
            margin-bottom: 25px;
        }
        .registration-number label {
            font-size: 12px;
            color: #808889;
            text-transform: uppercase;
            display: block;
            margin-bottom: 5px;
        }
        .registration-number span {
            font-size: 20px;
            font-weight: bold;
            color: #D00023;
        }
        .section {
            margin-bottom: 25px;
        }
        .section h2 {
            font-size: 16px;
            color: #1D2020;
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #D00023;
        }
        .info-row {
            display: flex;
            margin-bottom: 10px;
        }
        .info-label {
            font-size: 12px;
            color: #808889;
            text-transform: uppercase;
            width: 150px;
            flex-shrink: 0;
        }
        .info-value {
            font-size: 14px;
            color: #1D2020;
        }
        .exam-badge {
            display: inline-block;
            background: linear-gradient(135deg, #D00023 0%, #A5001C 100%);
            color: #FFFFFF;
            padding: 6px 16px;
            border-radius: 15px;
            font-size: 14px;
        }
        .test-badge {
            display: inline-block;
            background: #F0F0F0;
            color: #1D2020;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 13px;
            margin: 2px;
        }
        .total-box {
            background: linear-gradient(135deg, #1D2020 0%, #2D3131 100%);
            color: #FFFFFF;
            padding: 20px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 25px;
        }
        .total-box label {
            font-size: 16px;
        }
        .total-box span {
            font-size: 24px;
            font-weight: bold;
        }
        .next-steps {
            background: #FFF8E6;
            border: 1px solid #FFD700;
            border-radius: 8px;
            padding: 20px;
            margin-top: 25px;
        }
        .next-steps h3 {
            margin: 0 0 15px 0;
            font-size: 16px;
        }
        .next-steps ul {
            margin: 0;
            padding-left: 20px;
        }
        .next-steps li {
            margin-bottom: 8px;
        }
        .transaction-info {
            background: #E8F5E9;
            border: 1px solid #4CAF50;
            border-radius: 8px;
            padding: 15px 20px;
            margin-top: 25px;
        }
        .transaction-info label {
            font-size: 12px;
            color: #2E7D32;
            text-transform: uppercase;
            display: block;
            margin-bottom: 5px;
        }
        .transaction-info span {
            font-size: 14px;
            color: #1B5E20;
            font-family: monospace;
        }
        .footer {
            background: #F7F7F7;
            padding: 20px 30px;
            text-align: center;
            font-size: 12px;
            color: #808889;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="success-icon">
                <span>✓</span>
            </div>
            <h1>Inscription confirmée !</h1>
            <p>Votre inscription au ' . htmlspecialchars($examName) . ' a été enregistrée avec succès.</p>
        </div>
        
        <div class="body">
            <div class="registration-number">
                <label>Numéro d\'inscription</label>
                <span>' . $registrationNumber . '</span>
            </div>
            
            <div class="section">
                <h2>Informations personnelles</h2>
                <div class="info-row">
                    <div class="info-label">Nom complet</div>
                    <div class="info-value">' . htmlspecialchars($registration['firstname'] . ' ' . $registration['name']) . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Date de naissance</div>
                    <div class="info-value">' . date('d/m/Y', strtotime($registration['birthday'])) . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Pays de naissance</div>
                    <div class="info-value">' . htmlspecialchars($registration['countryOfBirthName']) . '</div>
                </div>';

    if (!empty($registration['cityOfBirthName'])) {
        $html .= '
                <div class="info-row">
                    <div class="info-label">Commune de naissance</div>
                    <div class="info-value">' . htmlspecialchars($registration['cityOfBirthName']) . '</div>
                </div>';
    }

    $html .= '
                <div class="info-row">
                    <div class="info-label">Nationalité</div>
                    <div class="info-value">' . htmlspecialchars($registration['nationalityName']) . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Pièce d\'identité</div>
                    <div class="info-value">' . htmlspecialchars($registration['identityDocumentNumber']) . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Langue usuelle</div>
                    <div class="info-value">' . htmlspecialchars($registration['languageName']) . '</div>
                </div>
            </div>
            
            <div class="section">
                <h2>Coordonnées</h2>
                <div class="info-row">
                    <div class="info-label">Adresse</div>
                    <div class="info-value">' . htmlspecialchars($registration['address']) . '<br>' .
        htmlspecialchars($registration['postalCode'] . ' ' . $registration['city']) . '<br>' .
        htmlspecialchars($registration['countryName']) . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Téléphone</div>
                    <div class="info-value">' . htmlspecialchars(($registration['phoneCountryCode'] ?? '') . ' ' . $registration['phone']) . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Courriel</div>
                    <div class="info-value">' . htmlspecialchars($registration['email']) . '</div>
                </div>
            </div>
            
            <div class="section">
                <h2>Détails de l\'examen</h2>
                <div class="info-row">
                    <div class="info-label">Examen</div>
                    <div class="info-value"><span class="exam-badge">' . htmlspecialchars($examName) . '</span></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Session</div>
                    <div class="info-value">' . date('d/m/Y', strtotime($registration['sessionDate'])) . '<br>' .
        htmlspecialchars($registration['examCenter'] . ', ' . $registration['examCity']) . '</div>
                </div>';

    if (!empty($selectedTests)) {
        $html .= '
                <div class="info-row">
                    <div class="info-label">Épreuves</div>
                    <div class="info-value">';
        foreach ($selectedTests as $test) {
            $html .= '<span class="test-badge">' . htmlspecialchars($test) . '</span> ';
        }
        $html .= '</div>
                </div>';
    }

    $html .= '
                <div class="info-row">
                    <div class="info-label">Objectif</div>
                    <div class="info-value">' . htmlspecialchars($registration['reasonName']) . '</div>
                </div>
            </div>';

    if ($registration['specialNeeds'] == 1) {
        $html .= '
            <div class="section">
                <h2>Aménagements particuliers</h2>
                <div class="info-row">
                    <div class="info-label">Demande</div>
                    <div class="info-value">Oui</div>
                </div>';
        if (!empty($registration['specialNeedsDetails'])) {
            $html .= '
                <div class="info-row">
                    <div class="info-label">Précisions</div>
                    <div class="info-value">' . nl2br(htmlspecialchars($registration['specialNeedsDetails'])) . '</div>
                </div>';
        }
        $html .= '
            </div>';
    }

    $html .= '
            <div class="total-box">
                <label>Montant payé</label>
                <span>' . number_format($registration['total_amount'], 2, ',', ' ') . ' $</span>
            </div>';

    if (!empty($registration['transaction_id'])) {
        $html .= '
            <div class="transaction-info">
                <label>Numéro de transaction Square</label>
                <span>' . htmlspecialchars($registration['transaction_id']) . '</span>
            </div>';
    }

    $html .= '
            <div class="next-steps">
                <h3>📋 Prochaines étapes</h3>
                <ul>
                    <li>Vous recevrez votre convocation officielle par courriel environ 2 semaines avant l\'examen</li>
                    <li>Le jour de l\'examen, présentez-vous avec une pièce d\'identité valide</li>';

    if ($registration['specialNeeds'] == 1) {
        $html .= '
                    <li>Nous vous contacterons concernant vos besoins d\'aménagements particuliers</li>';
    }

    $html .= '
                </ul>
            </div>
        </div>
        
        <div class="footer">
            <p>Leader CSA - Centre de Services et d\'Accueil<br>
            Tests de connaissance du français (TCF)<br>
            <a href="https://www.leadercsa.com">www.leadercsa.com</a></p>
        </div>
    </div>
</body>
</html>';

    return $html;
}

/**
 * Send confirmation email
 */
function sendConfirmationEmail($registration, $examName, $selectedTests, $toEmail, $isAdmin = false)
{
    $mail = new PHPMailer(true);

    try {
        // Use PHP mail() function
        $mail->isMail();

        // Sender
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addReplyTo(ADMIN_EMAIL, FROM_NAME);

        // Recipient
        $mail->addAddress($toEmail);

        // Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';

        $registrationNumber = 'TCF-' . str_pad($registration['id'], 6, '0', STR_PAD_LEFT);

        if ($isAdmin) {
            $mail->Subject = '[NOUVELLE INSCRIPTION] ' . $registrationNumber . ' - ' . $registration['firstname'] . ' ' . $registration['name'];
        } else {
            $mail->Subject = 'Confirmation d\'inscription TCF - ' . $registrationNumber;
        }

        $mail->Body = generateEmailHTML($registration, $examName, $selectedTests);

        // Plain text version
        $mail->AltBody = "Confirmation d'inscription TCF\n\n" .
            "Numéro d'inscription: " . $registrationNumber . "\n" .
            "Nom: " . $registration['firstname'] . ' ' . $registration['name'] . "\n" .
            "Examen: " . $examName . "\n" .
            "Session: " . date('d/m/Y', strtotime($registration['sessionDate'])) . "\n" .
            "Montant payé: " . number_format($registration['total_amount'], 2, ',', ' ') . " $\n";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("TCF Email Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Main execution
echo "TCF Confirmation Email Cron - " . date('Y-m-d H:i:s') . "\n";

// Fetch pending registrations (payment confirmed but email not sent)
$stmt = $bdd->prepare("
    SELECT r.*, 
           c1.name as countryOfBirthName,
           c2.name as countryName,
           n.name as nationalityName,
           l.name as languageName,
           s.date as sessionDate,
           s.exam_center as examCenter,
           s.city as examCity,
           reason.name as reasonName,
           fm.name as cityOfBirthName
    FROM tcf_registrations r
    LEFT JOIN countries c1 ON r.countryOfBirth = c1.id
    LEFT JOIN countries c2 ON r.country = c2.id
    LEFT JOIN nationalities n ON r.nationality = n.id
    LEFT JOIN tcf_languages l ON r.language = l.id
    LEFT JOIN tcf_exam_sessions s ON r.disiredSession = s.id
    LEFT JOIN tcf_reasons_for_registration reason ON r.reasonsForRegistration = reason.id
    LEFT JOIN french_municipalities fm ON r.cityOfBirth = fm.id
    WHERE r.payment_confirmed = 1 
    AND r.confirmation_email_sent = 0
    ORDER BY r.id ASC
");
$stmt->execute();
$registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($registrations) . " pending registration(s)\n";

foreach ($registrations as $registration) {
    $registrationNumber = 'TCF-' . str_pad($registration['id'], 6, '0', STR_PAD_LEFT);
    echo "Processing: " . $registrationNumber . " (" . $registration['email'] . ")\n";

    // Get exam name
    $examName = $registration['exam'] == 1 ? 'TCF Canada' : 'TCF Québec';

    // Get selected tests for TCF Quebec
    $selectedTests = [];
    if ($registration['exam'] == 2) {
        if ($registration['testCE'])
            $selectedTests[] = 'Compréhension écrite';
        if ($registration['testCO'])
            $selectedTests[] = 'Compréhension orale';
        if ($registration['testEE'])
            $selectedTests[] = 'Expression écrite';
        if ($registration['testEO'])
            $selectedTests[] = 'Expression orale';
    }

    $success = true;

    // Send to registrant
    if (!sendConfirmationEmail($registration, $examName, $selectedTests, $registration['email'], false)) {
        echo "  ERROR: Failed to send email to registrant\n";
        $success = false;
    } else {
        echo "  OK: Email sent to registrant\n";
    }

    // Send to admin
    if (!sendConfirmationEmail($registration, $examName, $selectedTests, ADMIN_EMAIL, true)) {
        echo "  ERROR: Failed to send email to admin\n";
        $success = false;
    } else {
        echo "  OK: Email sent to admin\n";
    }

    // Mark as sent if at least one email succeeded
    if ($success) {
        $updateStmt = $bdd->prepare("UPDATE tcf_registrations SET confirmation_email_sent = 1, confirmation_email_sent_at = NOW() WHERE id = :id");
        $updateStmt->execute([':id' => $registration['id']]);
        echo "  DONE: Marked as sent\n";
    }
}

echo "Completed at " . date('Y-m-d H:i:s') . "\n";

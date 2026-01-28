<?php
/**
 * TCF Registration Confirmation Page
 * Displayed after successful payment
 */

// Database connection
try {
    $bdd = new PDO(
        'mysql:dbname=leadercsa_tcf;'
        . 'unix_socket=/opt/homebrew/var/mysql/mysql.sock;'
        . 'charset=utf8mb4',
        'root',
        'root'
    );
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Erreur de connexion : ' . $e->getMessage());
}

// Get registration ID from URL
$registrationId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch registration data
$registration = null;
if ($registrationId > 0) {
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
        WHERE r.id = :id
    ");
    $stmt->execute([':id' => $registrationId]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);
}

// If no registration found, show error
if (!$registration) {
    $error = true;
} else {
    // Mark payment as confirmed if not already done
    if (!$registration['payment_confirmed']) {
        $updateStmt = $bdd->prepare("UPDATE tcf_registrations SET payment_confirmed = 1, payment_confirmed_at = NOW() WHERE id = :id");
        $updateStmt->execute([':id' => $registrationId]);
        $registration['payment_confirmed'] = 1;
    }
}

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
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation d'inscription - TCF</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Figtree:ital,wght@0,300..900;1,300..900&family=Lustria&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .confirmation-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .confirmation-card {
            background: #FFFFFF;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .confirmation-header {
            background: linear-gradient(135deg, #1D2020 0%, #2D3131 100%);
            color: #FFFFFF;
            padding: 48px 40px;
            text-align: center;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: #28a745;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            animation: scaleIn 0.5s ease-out;
        }

        .success-icon svg {
            width: 40px;
            height: 40px;
            fill: #FFFFFF;
        }

        @keyframes scaleIn {
            0% {
                transform: scale(0);
                opacity: 0;
            }

            50% {
                transform: scale(1.2);
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .confirmation-header h1 {
            font-family: "Lustria", serif;
            font-size: 32px;
            font-weight: 400;
            margin: 0 0 12px 0;
        }

        .confirmation-header p {
            font-size: 18px;
            opacity: 0.9;
            margin: 0;
        }

        .confirmation-body {
            padding: 40px;
        }

        .registration-number {
            background: #F7F7F7;
            border-left: 4px solid #D00023;
            padding: 20px 24px;
            margin-bottom: 32px;
            border-radius: 0 8px 8px 0;
        }

        .registration-number label {
            font-size: 14px;
            color: #808889;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 4px;
        }

        .registration-number span {
            font-size: 24px;
            font-weight: 600;
            color: #D00023;
        }

        .info-section {
            margin-bottom: 32px;
        }

        .info-section h2 {
            font-family: "Lustria", serif;
            font-size: 20px;
            color: #1D2020;
            margin: 0 0 16px 0;
            padding-bottom: 12px;
            border-bottom: 2px solid #D00023;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        @media (max-width: 600px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }

        .info-item {
            padding: 12px 0;
        }

        .info-item label {
            font-size: 13px;
            color: #808889;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: block;
            margin-bottom: 4px;
        }

        .info-item span {
            font-size: 16px;
            color: #1D2020;
        }

        .exam-badge {
            display: inline-block;
            background: linear-gradient(135deg, #D00023 0%, #A5001C 100%);
            color: #FFFFFF !important;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 16px;
        }

        .selected-tests {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }

        .test-badge {
            background: #F0F0F0;
            color: #1D2020;
            padding: 6px 14px;
            border-radius: 16px;
            font-size: 14px;
        }

        .total-amount {
            background: linear-gradient(135deg, #1D2020 0%, #2D3131 100%);
            color: #FFFFFF;
            padding: 24px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 32px;
        }

        .total-amount label {
            font-size: 18px;
        }

        .total-amount span {
            font-size: 28px;
            font-weight: 600;
            color: #FFFFFF;
        }

        .next-steps {
            background: #FFF8E6;
            border: 1px solid #FFD700;
            border-radius: 12px;
            padding: 24px;
            margin-top: 32px;
        }

        .next-steps h3 {
            color: #1D2020;
            margin: 0 0 16px 0;
            font-size: 18px;
        }

        .next-steps ul {
            margin: 0;
            padding-left: 20px;
            color: #1D2020;
        }

        .next-steps li {
            margin-bottom: 8px;
            line-height: 1.5;
        }

        .print-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #D00023;
            color: #FFFFFF;
            border: none;
            padding: 14px 28px;
            font-size: 16px;
            font-weight: 500;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 24px;
        }

        .print-btn:hover {
            background: #A5001C;
            transform: translateY(-2px);
        }

        .error-container {
            text-align: center;
            padding: 60px 40px;
        }

        .error-icon {
            width: 80px;
            height: 80px;
            background: #DC3545;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }

        .error-icon span {
            font-size: 40px;
            color: #FFFFFF;
        }

        @media print {
            .print-btn {
                display: none;
            }

            .confirmation-card {
                box-shadow: none;
            }
        }
    </style>
</head>

<body>
    <div class="confirmation-container">
        <?php if (isset($error)): ?>
            <div class="confirmation-card">
                <div class="error-container">
                    <div class="error-icon">
                        <span>✕</span>
                    </div>
                    <h1>Inscription non trouvée</h1>
                    <p>Nous n'avons pas pu trouver cette inscription. Veuillez vérifier le lien ou contacter
                        l'administration.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="confirmation-card">
                <div class="confirmation-header">
                    <div class="success-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" />
                        </svg>
                    </div>
                    <h1>Inscription confirmée !</h1>
                    <p>Votre inscription au
                        <?= htmlspecialchars($examName) ?> a été enregistrée avec succès.
                    </p>
                </div>

                <div class="confirmation-body">
                    <div class="registration-number">
                        <label>Numéro d'inscription</label>
                        <span>TCF-
                            <?= str_pad($registration['id'], 6, '0', STR_PAD_LEFT) ?>
                        </span>
                    </div>

                    <div class="info-section">
                        <h2>Informations personnelles</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Nom complet</label>
                                <span>
                                    <?= htmlspecialchars($registration['firstname'] . ' ' . $registration['name']) ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <label>Date de naissance</label>
                                <span>
                                    <?= date('d/m/Y', strtotime($registration['birthday'])) ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <label>Pays de naissance</label>
                                <span>
                                    <?= htmlspecialchars($registration['countryOfBirthName']) ?>
                                </span>
                            </div>
                            <?php if (!empty($registration['cityOfBirthName'])): ?>
                                <div class="info-item">
                                    <label>Commune de naissance</label>
                                    <span>
                                        <?= htmlspecialchars($registration['cityOfBirthName']) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <div class="info-item">
                                <label>Nationalité</label>
                                <span>
                                    <?= htmlspecialchars($registration['nationalityName']) ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <label>Numéro de la pièce d'identité</label>
                                <span>
                                    <?= htmlspecialchars($registration['identityDocumentNumber']) ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <label>Langue usuelle</label>
                                <span>
                                    <?= htmlspecialchars($registration['languageName']) ?>
                                </span>
                            </div>
                            <?php if (!empty($registration['oldCandidateCode'])): ?>
                                <div class="info-item">
                                    <label>Ancien code candidat</label>
                                    <span>
                                        <?= htmlspecialchars($registration['oldCandidateCode']) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="info-section">
                        <h2>Coordonnées</h2>
                        <div class="info-grid">
                            <div class="info-item" style="grid-column: 1 / -1;">
                                <label>Adresse</label>
                                <span>
                                    <?= htmlspecialchars($registration['address']) ?><br>
                                    <?= htmlspecialchars($registration['postalCode']) ?>
                                    <?= htmlspecialchars($registration['city']) ?><br>
                                    <?= htmlspecialchars($registration['countryName']) ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <label>Téléphone</label>
                                <span>
                                    <?= htmlspecialchars(($registration['phoneCountryCode'] ?? '') . ' ' . $registration['phone']) ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <label>Courriel</label>
                                <span>
                                    <?= htmlspecialchars($registration['email']) ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="info-section">
                        <h2>Détails de l'examen</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Examen</label>
                                <span class="exam-badge">
                                    <?= htmlspecialchars($examName) ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <label>Session</label>
                                <span>
                                    <?= date('d/m/Y', strtotime($registration['sessionDate'])) ?><br>
                                    <?= htmlspecialchars($registration['examCenter']) ?>,
                                    <?= htmlspecialchars($registration['examCity']) ?>
                                </span>
                            </div>
                            <?php if (!empty($selectedTests)): ?>
                                <div class="info-item" style="grid-column: 1 / -1;">
                                    <label>Épreuves sélectionnées</label>
                                    <div class="selected-tests">
                                        <?php foreach ($selectedTests as $test): ?>
                                            <span class="test-badge">
                                                <?= htmlspecialchars($test) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="info-item">
                                <label>Objectif</label>
                                <span>
                                    <?= htmlspecialchars($registration['reasonName']) ?>
                                </span>
                            </div>
                        </div>

                    </div>

                    <div class="info-section">
                        <h2>Aménagements particuliers</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Avez-vous besoin d'aménagements particuliers ?</label>
                                <span>
                                    <?= $registration['specialNeeds'] == 1 ? 'Oui' : 'Non' ?>
                                </span>
                            </div>
                            <?php if ($registration['specialNeeds'] == 1 && !empty($registration['specialNeedsDetails'])): ?>
                                <div class="info-item" style="grid-column: 1 / -1;">
                                    <label>Précisions sur les aménagements</label>
                                    <span>
                                        <?= nl2br(htmlspecialchars($registration['specialNeedsDetails'])) ?>
                                    </span>
                                </div>

                            <?php endif; ?>
                        </div>
                    </div>

                    <div cla ss="info-section">
                        <h2>Utilisation des données</h2>
                        <div class="info-grid">
                            <div class="info-item" style="grid-column: 1 / -1;">
                                <span style="color: #28a745;">✓ J'accepte que mes données soient utilisées à des fins de
                                    formation.</span>
                            </div>
                            </di v>
                        </div>

                        <div class="total-amount">
                            <label>Montant payé</label>
                            <span>
                                <?= number_format($registration['total_amount'], 2, ',', ' ') ?> $
                            </span>
                        </div>

                        <div class="next-steps">
                            <h3>📋 Prochaines étapes</h3>
                            <ul>
                                <li>Un courriel de confirmation vous a été envoyé à <strong>
                                        <?= htmlspecialchars($registration['email']) ?>
                                    </strong></li>
                                <li>Vous recevrez votre convocation officielle par courriel environ 2 semaines avant
                                    l'examen
                                </li>
                                <li>Le jour de l'examen, présentez-vous avec une pièce d'identité valide</li>
                                <?php if ($registration['specialNeeds'] == 1): ?>
                                    <li>Nous vous contacterons concernant vos besoins d'aménagements particuliers</li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <button class="print-btn" onclick="window.print()">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z" />
                            </svg>
                            Imprimer cette confirmation
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
</body>

</html>
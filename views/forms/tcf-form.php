<?php
/**
 * TCF Registration Form
 * Inscription en ligne au TCF (Test de connaissance du français)
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

// Square API Configuration
define('SQUARE_ACCESS_TOKEN', 'EAAAl_7258zoE2kV5G7HBGZQPZ2IUFZzuNwCZIDa3mea7Gfzp9du5nWZQzA4cYRN');
define('SQUARE_LOCATION_ID', 'LP5Z3DN7TJGGQ');
define('SQUARE_API_URL', 'https://connect.squareup.com/v2/online-checkout/payment-links');

// Prices configuration
$prices = [
    'tcf_canada' => 420,
    'tcf_quebec' => [
        'comprehension_ecrite' => 100,
        'comprehension_orale' => 110,
        'expression_ecrite' => 100,
        'expression_orale' => 110
    ]
];

// Fetch data from database tables
$countries = $bdd->query("SELECT id, name FROM countries ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$nationalities = $bdd->query("SELECT id, name FROM nationalities ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$languages = $bdd->query("SELECT id, name FROM tcf_languages ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$sessions = $bdd->query("SELECT id, date, exam_center, city FROM tcf_exam_sessions ORDER BY date")->fetchAll(PDO::FETCH_ASSOC);
$reasons = $bdd->query("SELECT id, name FROM tcf_reasons_for_registration ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
// French municipalities are loaded via AJAX search

// Form submission handling
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $requiredFields = [
        'name',
        'firstname',
        'gender',
        'birthday',
        'countryOfBirth',
        'nationality',
        'identityDocumentNumber',
        'language',
        'address',
        'city',
        'postalCode',
        'country',
        'phone',
        'email',
        'exam',
        'reasonsForRegistration',
        'disiredSession',
        'specialNeeds',
        'dataUsageAgreement'
    ];

    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = "Le champ $field est requis.";
        }
    }

    // Validate TCF Quebec tests selection
    if ($_POST['exam'] == '2' && empty($_POST['tcfQuebecSelectedTests'])) {
        $errors[] = "Veuillez sélectionner au moins une épreuve pour le TCF Québec.";
    }

    // Validate cityOfBirth is required when countryOfBirth is France (id=73)
    if ($_POST['countryOfBirth'] == '73' && empty($_POST['cityOfBirth'])) {
        $errors[] = "La commune de naissance est obligatoire pour les personnes nées en France.";
    }

    // Calculate total amount
    $totalAmount = 0;
    if ($_POST['exam'] == '1') {
        $totalAmount = $prices['tcf_canada'];
    } elseif ($_POST['exam'] == '2') {
        $selectedTests = $_POST['tcfQuebecSelectedTests'] ?? [];
        $testPrices = [
            '1' => $prices['tcf_quebec']['comprehension_ecrite'],
            '2' => $prices['tcf_quebec']['comprehension_orale'],
            '3' => $prices['tcf_quebec']['expression_ecrite'],
            '4' => $prices['tcf_quebec']['expression_orale']
        ];
        foreach ($selectedTests as $test) {
            $totalAmount += $testPrices[$test] ?? 0;
        }
    }

    // Convert selected tests to bitmask for storage
    $tcfQuebecBitmask = 0;
    if (!empty($_POST['tcfQuebecSelectedTests'])) {
        foreach ($_POST['tcfQuebecSelectedTests'] as $test) {
            $tcfQuebecBitmask |= (1 << (intval($test) - 1));
        }
    }

    if (empty($errors)) {
        try {
            // Insert into database
            $stmt = $bdd->prepare("
                INSERT INTO tcf_registrations (
                    name, firstname, gender, birthday, countryOfBirth, cityOfBirth,
                    nationality, identityDocumentNumber, language, oldCandidateCode,
                    address, city, postalCode, country, phone, email,
                    exam, tcfQuebecSelectedTests, reasonsForRegistration, disiredSession,
                    specialNeeds, specialNeedsDetails, dataUsageAgreement, total_amount
                ) VALUES (
                    :name, :firstname, :gender, :birthday, :countryOfBirth, :cityOfBirth,
                    :nationality, :identityDocumentNumber, :language, :oldCandidateCode,
                    :address, :city, :postalCode, :country, :phone, :email,
                    :exam, :tcfQuebecSelectedTests, :reasonsForRegistration, :disiredSession,
                    :specialNeeds, :specialNeedsDetails, :dataUsageAgreement, :total_amount
                )
            ");

            $stmt->execute([
                ':name' => $_POST['name'],
                ':firstname' => $_POST['firstname'],
                ':gender' => $_POST['gender'],
                ':birthday' => $_POST['birthday'],
                ':countryOfBirth' => $_POST['countryOfBirth'],
                ':cityOfBirth' => !empty($_POST['cityOfBirth']) ? $_POST['cityOfBirth'] : 0,
                ':nationality' => $_POST['nationality'],
                ':identityDocumentNumber' => $_POST['identityDocumentNumber'],
                ':language' => $_POST['language'],
                ':oldCandidateCode' => $_POST['oldCandidateCode'] ?? '',
                ':address' => $_POST['address'],
                ':city' => $_POST['city'],
                ':postalCode' => $_POST['postalCode'],
                ':country' => $_POST['country'],
                ':phone' => $_POST['phone'],
                ':email' => $_POST['email'],
                ':exam' => $_POST['exam'],
                ':tcfQuebecSelectedTests' => $tcfQuebecBitmask,
                ':reasonsForRegistration' => $_POST['reasonsForRegistration'],
                ':disiredSession' => $_POST['disiredSession'],
                ':specialNeeds' => $_POST['specialNeeds'],
                ':specialNeedsDetails' => $_POST['specialNeedsDetails'] ?? '',
                ':dataUsageAgreement' => 1,
                ':total_amount' => $totalAmount
            ]);

            $registrationId = $bdd->lastInsertId();

            // Create Square payment link
            $examName = $_POST['exam'] == '1' ? 'TCF Canada' : 'TCF Québec';
            $paymentData = [
                'idempotency_key' => uniqid('tcf_', true),
                'quick_pay' => [
                    'name' => "Inscription TCF - $examName",
                    'price_money' => [
                        'amount' => $totalAmount * 100, // Square uses cents
                        'currency' => 'CAD'
                    ],
                    'location_id' => SQUARE_LOCATION_ID
                ],
                'checkout_options' => [
                    'redirect_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/confirmation.php?id=' . $registrationId
                ]
            ];

            $ch = curl_init(SQUARE_API_URL);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . SQUARE_ACCESS_TOKEN,
                    'Square-Version: 2024-01-18'
                ],
                CURLOPT_POSTFIELDS => json_encode($paymentData)
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 || $httpCode === 201) {
                $result = json_decode($response, true);
                $paymentUrl = $result['payment_link']['url'] ?? null;

                if ($paymentUrl) {
                    header('Location: ' . $paymentUrl);
                    exit;
                }
            }

            // If Square fails, show error but keep registration
            $errors[] = "Erreur lors de la création du lien de paiement. Veuillez contacter l'administration.";

        } catch (PDOException $e) {
            $errors[] = "Erreur lors de l'enregistrement : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription TCF - Test de connaissance du français</title>
    <link rel="stylesheet" href="../../css/style.css">
    <style>
        .tcf-form-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }

        fieldset {
            border: none;
            padding: 0;
            margin: 0 0 40px 0;
        }

        fieldset legend {
            font-family: "Lustria", sans-serif;
            font-size: 24px;
            font-weight: 400;
            color: #1D2020;
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 2px solid #D00023;
            width: 100%;
            display: block;
        }

        .field-note {
            font-size: 14px;
            color: #808889;
            margin-top: 8px;
            font-style: italic;
        }

        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }

        .checkbox-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #D00023;
        }

        .checkbox-item label {
            margin: 0;
            cursor: pointer;
            font-weight: 400;
        }

        .hidden {
            display: none !important;
        }

        .exam-fees {
            background: linear-gradient(135deg, #1D2020 0%, #2D3131 100%);
            color: #FFFFFF;
            padding: 32px;
            border-radius: 12px;
            margin-bottom: 32px;
        }

        .exam-fees legend {
            color: #FFFFFF;
            border-bottom-color: #FFFFFF;
        }

        .fee-breakdown {
            margin-bottom: 16px;
        }

        .fee-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .fee-total {
            display: flex;
            justify-content: space-between;
            padding: 16px 0 0 0;
            font-size: 24px;
            font-weight: 600;
        }

        .fee-total .amount {
            color: #D00023;
            background: #FFFFFF;
            padding: 4px 16px;
            border-radius: 4px;
        }

        .error-messages {
            background: #FFE6E6;
            border: 1px solid #D00023;
            color: #D00023;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
        }

        .error-messages ul {
            margin: 0;
            padding-left: 20px;
        }

        .submit-btn {
            background: #D00023;
            color: #FFFFFF;
            border: none;
            padding: 16px 40px;
            font-size: 18px;
            font-weight: 500;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.3s;
        }

        .submit-btn:hover {
            background: #A5001C;
            transform: translateY(-2px);
        }

        .conditional-field {
            transition: all 0.3s ease;
        }

        /* Autocomplete styles */
        .autocomplete-wrap {
            position: relative;
        }

        .autocomplete-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #FFFFFF;
            border: 1px solid #808889;
            border-top: none;
            max-height: 250px;
            overflow-y: auto;
            z-index: 100;
            display: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .autocomplete-results.active {
            display: block;
        }

        .autocomplete-item {
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid #EEE;
            font-size: 16px;
        }

        .autocomplete-item:hover,
        .autocomplete-item.selected {
            background: #F7F7F7;
            color: #D00023;
        }

        .autocomplete-item:last-child {
            border-bottom: none;
        }

        .autocomplete-loading {
            padding: 12px 15px;
            color: #808889;
            font-style: italic;
        }

        .autocomplete-no-results {
            padding: 12px 15px;
            color: #808889;
        }
    </style>
</head>

<body>
    <div class="tcf-form-container">
        <h1>Inscription au TCF</h1>
        <p>Test de connaissance du français - France Éducation international</p>

        <?php if (!empty($errors)): ?>
            <div class="error-messages">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li>
                            <?= htmlspecialchars($error) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="" method="POST" id="tcf-registration-form">

            <!-- Fieldset 1: Informations personnelles -->
            <fieldset>
                <legend>Informations personnelles</legend>

                <div class="row-form">
                    <div class="col-form">
                        <label for="name">Nom de famille <span class="required">*</span></label>
                        <input type="text" name="name" id="name" placeholder="Nom de famille" required
                            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    </div>
                    <div class="col-form">
                        <label for="firstname">Prénom <span class="required">*</span></label>
                        <input type="text" name="firstname" id="firstname" placeholder="Prénom" required
                            value="<?= htmlspecialchars($_POST['firstname'] ?? '') ?>">
                    </div>
                </div>

                <div class="row-form">
                    <div class="col-form">
                        <label for="gender">Genre <span class="required">*</span></label>
                        <div class="select-wrap">
                            <select name="gender" id="gender" required>
                                <option value="">Sélectionnez...</option>
                                <option value="1" <?= ($_POST['gender'] ?? '') == '1' ? 'selected' : '' ?>>Homme</option>
                                <option value="2" <?= ($_POST['gender'] ?? '') == '2' ? 'selected' : '' ?>>Femme</option>
                                <option value="3" <?= ($_POST['gender'] ?? '') == '3' ? 'selected' : '' ?>>Non-binaire
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="col-form">
                        <label for="birthday">Date de naissance <span class="required">*</span></label>
                        <div class="input-wrap">
                            <input type="date" name="birthday" id="birthday" required
                                value="<?= htmlspecialchars($_POST['birthday'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="row-form">
                    <div class="col-form">
                        <label for="countryOfBirth">Pays de naissance <span class="required">*</span></label>
                        <div class="select-wrap">
                            <select name="countryOfBirth" id="countryOfBirth" required>
                                <option value="">Sélectionnez...</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?= $country['id'] ?>" <?= ($_POST['countryOfBirth'] ?? '') == $country['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($country['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-form conditional-field" id="cityOfBirth-container">
                        <label for="cityOfBirthSearch">Commune de naissance <span class="required">*</span></label>
                        <div class="autocomplete-wrap">
                            <input type="text" id="cityOfBirthSearch" placeholder="Tapez le nom de la commune..."
                                autocomplete="off">
                            <input type="hidden" name="cityOfBirth" id="cityOfBirth"
                                value="<?= htmlspecialchars($_POST['cityOfBirth'] ?? '') ?>">
                            <div class="autocomplete-results" id="cityOfBirth-results"></div>
                        </div>
                        <p class="field-note">Commencez à taper le nom de la commune pour rechercher.</p>
                    </div>
                </div>

                <div class="row-form">
                    <div class="col-form">
                        <label for="nationality">Nationalité <span class="required">*</span></label>
                        <div class="select-wrap">
                            <select name="nationality" id="nationality" required>
                                <option value="">Sélectionnez...</option>
                                <?php foreach ($nationalities as $nationality): ?>
                                    <option value="<?= $nationality['id'] ?>" <?= ($_POST['nationality'] ?? '') == $nationality['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($nationality['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-form">
                        <label for="identityDocumentNumber">Numéro de la pièce d'identité <span
                                class="required">*</span></label>
                        <input type="text" name="identityDocumentNumber" id="identityDocumentNumber"
                            placeholder="Numéro de la pièce d'identité" required
                            value="<?= htmlspecialchars($_POST['identityDocumentNumber'] ?? '') ?>">
                    </div>
                </div>

                <div class="row-form">
                    <div class="col-form">
                        <label for="language">Langue usuelle <span class="required">*</span></label>
                        <div class="select-wrap">
                            <select name="language" id="language" required>
                                <option value="">Sélectionnez...</option>
                                <?php foreach ($languages as $language): ?>
                                    <option value="<?= $language['id'] ?>" <?= ($_POST['language'] ?? '') == $language['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($language['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-form">
                        <label for="oldCandidateCode">Ancien code candidat</label>
                        <input type="text" name="oldCandidateCode" id="oldCandidateCode"
                            placeholder="Ancien code candidat"
                            value="<?= htmlspecialchars($_POST['oldCandidateCode'] ?? '') ?>">
                        <p class="field-note">Si vous avez déjà passé le TCF et vous souhaitez vous inscrire uniquement
                            à l'épreuve d'expression orale et/ou d'expression écrite, saisissez ici la fin de votre code
                            candidat complet.</p>
                    </div>
                </div>
            </fieldset>

            <!-- Fieldset 2: Coordonnées -->
            <fieldset>
                <legend>Coordonnées</legend>

                <div class="row-form">
                    <div class="col-form">
                        <label for="address">Adresse complète <span class="required">*</span></label>
                        <input type="text" name="address" id="address" placeholder="Adresse complète" required
                            value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                    </div>
                </div>

                <div class="row-form">
                    <div class="col-form">
                        <label for="city">Ville <span class="required">*</span></label>
                        <input type="text" name="city" id="city" placeholder="Ville" required
                            value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                    </div>
                    <div class="col-form">
                        <label for="postalCode">Code postal <span class="required">*</span></label>
                        <input type="text" name="postalCode" id="postalCode" placeholder="Code postal" required
                            value="<?= htmlspecialchars($_POST['postalCode'] ?? '') ?>">
                    </div>
                </div>

                <div class="row-form">
                    <div class="col-form">
                        <label for="country">Pays <span class="required">*</span></label>
                        <div class="select-wrap">
                            <select name="country" id="country" required>
                                <option value="">Sélectionnez...</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?= $country['id'] ?>" <?= ($_POST['country'] ?? '') == $country['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($country['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row-form">
                    <div class="col-form">
                        <label for="phone">Téléphone <span class="required">*</span></label>
                        <input type="tel" name="phone" id="phone" placeholder="Téléphone" required
                            value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                    <div class="col-form">
                        <label for="email">Courriel <span class="required">*</span></label>
                        <input type="email" name="email" id="email" placeholder="Courriel" required
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>
            </fieldset>

            <!-- Fieldset 3: Choix de l'examen -->
            <fieldset>
                <legend>Choix de l'examen</legend>

                <div class="row-form">
                    <div class="col-form">
                        <label for="exam">Examen <span class="required">*</span></label>
                        <div class="select-wrap">
                            <select name="exam" id="exam" required>
                                <option value="">Sélectionnez...</option>
                                <option value="1" <?= ($_POST['exam'] ?? '') == '1' ? 'selected' : '' ?>>TCF Canada (420
                                    $)</option>
                                <option value="2" <?= ($_POST['exam'] ?? '') == '2' ? 'selected' : '' ?>>TCF Québec (100 à
                                    420 $)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row-form conditional-field hidden" id="tcfQuebecTests-container">
                    <div class="col-form">
                        <label>Sélectionner les épreuves d'examen selon les exigences du MIFI <span
                                class="required">*</span></label>
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" name="tcfQuebecSelectedTests[]" id="test-ce" value="1"
                                    <?= in_array('1', $_POST['tcfQuebecSelectedTests'] ?? []) ? 'checked' : '' ?>>
                                <label for="test-ce">Compréhension écrite (100 $)</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" name="tcfQuebecSelectedTests[]" id="test-co" value="2"
                                    <?= in_array('2', $_POST['tcfQuebecSelectedTests'] ?? []) ? 'checked' : '' ?>>
                                <label for="test-co">Compréhension orale (110 $)</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" name="tcfQuebecSelectedTests[]" id="test-ee" value="3"
                                    <?= in_array('3', $_POST['tcfQuebecSelectedTests'] ?? []) ? 'checked' : '' ?>>
                                <label for="test-ee">Expression écrite (100 $)</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" name="tcfQuebecSelectedTests[]" id="test-eo" value="4"
                                    <?= in_array('4', $_POST['tcfQuebecSelectedTests'] ?? []) ? 'checked' : '' ?>>
                                <label for="test-eo">Expression orale (110 $)</label>
                            </div>
                        </div>
                        <p class="field-note">Le candidat est responsable de choisir les épreuves exigées par l'autorité
                            concernée.</p>
                    </div>
                </div>

                <div class="row-form">
                    <div class="col-form">
                        <label for="reasonsForRegistration">Objectif de l'examen <span class="required">*</span></label>
                        <div class="select-wrap">
                            <select name="reasonsForRegistration" id="reasonsForRegistration" required>
                                <option value="">Sélectionnez...</option>
                                <?php foreach ($reasons as $reason): ?>
                                    <option value="<?= $reason['id'] ?>" <?= ($_POST['reasonsForRegistration'] ?? '') == $reason['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($reason['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row-form">
                    <div class="col-form">
                        <label for="disiredSession">Choisissez une session <span class="required">*</span></label>
                        <div class="select-wrap">
                            <select name="disiredSession" id="disiredSession" required>
                                <option value="">Sélectionnez...</option>
                                <?php foreach ($sessions as $session): ?>
                                    <option value="<?= $session['id'] ?>" <?= ($_POST['disiredSession'] ?? '') == $session['id'] ? 'selected' : '' ?>>
                                        <?= date('d/m/Y', strtotime($session['date'])) ?> -
                                        <?= htmlspecialchars($session['exam_center']) ?>,
                                        <?= htmlspecialchars($session['city']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <p class="field-note">La date finale sera confirmée par le centre d'examen selon les
                            disponibilités.</p>
                    </div>
                </div>

                <div class="row-form">
                    <div class="col-form">
                        <label for="specialNeeds">Avez-vous besoin d'aménagements particuliers ? <span
                                class="required">*</span></label>
                        <div class="select-wrap">
                            <select name="specialNeeds" id="specialNeeds" required>
                                <option value="">Sélectionnez...</option>
                                <option value="1" <?= ($_POST['specialNeeds'] ?? '') == '1' ? 'selected' : '' ?>>Oui
                                </option>
                                <option value="0" <?= ($_POST['specialNeeds'] ?? '') == '0' ? 'selected' : '' ?>>Non
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row-form conditional-field hidden" id="specialNeedsDetails-container">
                    <div class="col-form">
                        <label for="specialNeedsDetails">Veuillez préciser les aménagements particuliers dont vous avez
                            besoin <span class="required">*</span></label>
                        <textarea name="specialNeedsDetails" id="specialNeedsDetails" cols="40" rows="5"
                            placeholder="Décrivez vos besoins..."><?= htmlspecialchars($_POST['specialNeedsDetails'] ?? '') ?></textarea>
                        <p class="field-note">Les documents justificatifs vous seront demandés ultérieurement.</p>
                    </div>
                </div>
            </fieldset>

            <!-- Fieldset 4: Utilisation des données -->
            <fieldset>
                <legend>Utilisation des données</legend>

                <div class="row-form">
                    <div class="col-form">
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" name="dataUsageAgreement" id="dataUsageAgreement" value="1"
                                    required <?= !empty($_POST['dataUsageAgreement']) ? 'checked' : '' ?>>
                                <label for="dataUsageAgreement">J'accepte que mes données soient utilisées à des fins de
                                    formation. <span class="required">*</span></label>
                            </div>
                        </div>
                    </div>
                </div>
            </fieldset>

            <!-- Fieldset 5: Frais d'examen -->
            <fieldset class="exam-fees">
                <legend>Frais d'examen</legend>

                <div class="fee-breakdown" id="fee-breakdown">
                    <p id="no-selection-message">Veuillez sélectionner un examen pour voir le détail des frais.</p>
                </div>

                <div class="fee-total" id="fee-total" style="display: none;">
                    <span>Total à payer :</span>
                    <span class="amount" id="total-amount">0 $</span>
                </div>
            </fieldset>

            <div class="action-container">
                <button type="submit" class="submit-btn">Confirmer et payer</button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const examSelect = document.getElementById('exam');
            const tcfQuebecTestsContainer = document.getElementById('tcfQuebecTests-container');
            const specialNeedsSelect = document.getElementById('specialNeeds');
            const specialNeedsDetailsContainer = document.getElementById('specialNeedsDetails-container');
            const countryOfBirthSelect = document.getElementById('countryOfBirth');
            const cityOfBirthContainer = document.getElementById('cityOfBirth-container');
            const feeBreakdown = document.getElementById('fee-breakdown');
            const feeTotal = document.getElementById('fee-total');
            const totalAmount = document.getElementById('total-amount');
            const testCheckboxes = document.querySelectorAll('input[name="tcfQuebecSelectedTests[]"]');

            // France ID in the database (id=73 for FRANCE based on the SQL)
            const FRANCE_ID = '73';

            // Price configuration
            const prices = {
                tcfCanada: 420,
                tcfQuebec: {
                    1: { name: 'Compréhension écrite', price: 100 },
                    2: { name: 'Compréhension orale', price: 110 },
                    3: { name: 'Expression écrite', price: 100 },
                    4: { name: 'Expression orale', price: 110 }
                }
            };

            // Toggle TCF Quebec tests visibility
            function updateExamDisplay() {
                if (examSelect.value === '2') {
                    tcfQuebecTestsContainer.classList.remove('hidden');
                } else {
                    tcfQuebecTestsContainer.classList.add('hidden');
                }
                updateFeeBreakdown();
            }

            // Toggle special needs details visibility
            function updateSpecialNeedsDisplay() {
                if (specialNeedsSelect.value === '1') {
                    specialNeedsDetailsContainer.classList.remove('hidden');
                } else {
                    specialNeedsDetailsContainer.classList.add('hidden');
                }
            }

            // Toggle city of birth visibility (only for France)
            function updateCityOfBirthDisplay() {
                const cityOfBirthInput = document.getElementById('cityOfBirth');
                const cityOfBirthSearch = document.getElementById('cityOfBirthSearch');
                
                if (countryOfBirthSelect.value === FRANCE_ID) {
                    cityOfBirthContainer.classList.remove('hidden');
                } else {
                    cityOfBirthContainer.classList.add('hidden');
                    // Clear the selection when France is not selected
                    cityOfBirthInput.value = '';
                    cityOfBirthSearch.value = '';
                }
            }

            // Update fee breakdown
            function updateFeeBreakdown() {
                let html = '';
                let total = 0;

                if (examSelect.value === '1') {
                    // TCF Canada
                    html = '<div class="fee-item"><span>TCF Canada</span><span>420 $</span></div>';
                    total = prices.tcfCanada;
                    feeTotal.style.display = 'flex';
                } else if (examSelect.value === '2') {
                    // TCF Quebec
                    let hasSelection = false;
                    testCheckboxes.forEach(function (checkbox) {
                        if (checkbox.checked) {
                            hasSelection = true;
                            const testId = checkbox.value;
                            const testInfo = prices.tcfQuebec[testId];
                            html += `<div class="fee-item"><span>${testInfo.name}</span><span>${testInfo.price} $</span></div>`;
                            total += testInfo.price;
                        }
                    });

                    if (!hasSelection) {
                        html = '<p>Veuillez sélectionner au moins une épreuve.</p>';
                        feeTotal.style.display = 'none';
                    } else {
                        feeTotal.style.display = 'flex';
                    }
                } else {
                    html = '<p id="no-selection-message">Veuillez sélectionner un examen pour voir le détail des frais.</p>';
                    feeTotal.style.display = 'none';
                }

                feeBreakdown.innerHTML = html;
                totalAmount.textContent = total + ' $';
            }
            
            // Autocomplete for city of birth
            const cityOfBirthSearch = document.getElementById('cityOfBirthSearch');
            const cityOfBirthInput = document.getElementById('cityOfBirth');
            const cityOfBirthResults = document.getElementById('cityOfBirth-results');
            let searchTimeout = null;
            let currentFocus = -1;
            
            cityOfBirthSearch.addEventListener('input', function() {
                const query = this.value.trim();
                
                // Clear previous timeout
                if (searchTimeout) {
                    clearTimeout(searchTimeout);
                }
                
                // Clear the hidden input when typing
                cityOfBirthInput.value = '';
                
                if (query.length < 2) {
                    cityOfBirthResults.classList.remove('active');
                    cityOfBirthResults.innerHTML = '';
                    return;
                }
                
                // Show loading
                cityOfBirthResults.classList.add('active');
                cityOfBirthResults.innerHTML = '<div class="autocomplete-loading">Recherche...</div>';
                
                // Debounce search
                searchTimeout = setTimeout(function() {
                    fetch('search-municipalities.php?q=' + encodeURIComponent(query))
                        .then(response => response.json())
                        .then(data => {
                            if (data.length === 0) {
                                cityOfBirthResults.innerHTML = '<div class="autocomplete-no-results">Aucune commune trouvée</div>';
                                return;
                            }
                            
                            let html = '';
                            data.forEach(function(item, index) {
                                html += `<div class="autocomplete-item" data-id="${item.id}" data-name="${item.name}">${item.name}</div>`;
                            });
                            cityOfBirthResults.innerHTML = html;
                            currentFocus = -1;
                            
                            // Add click handlers
                            cityOfBirthResults.querySelectorAll('.autocomplete-item').forEach(function(el) {
                                el.addEventListener('click', function() {
                                    cityOfBirthSearch.value = this.dataset.name;
                                    cityOfBirthInput.value = this.dataset.id;
                                    cityOfBirthResults.classList.remove('active');
                                });
                            });
                        })
                        .catch(error => {
                            cityOfBirthResults.innerHTML = '<div class="autocomplete-no-results">Erreur de recherche</div>';
                        });
                }, 300);
            });
            
            // Keyboard navigation
            cityOfBirthSearch.addEventListener('keydown', function(e) {
                const items = cityOfBirthResults.querySelectorAll('.autocomplete-item');
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    currentFocus++;
                    if (currentFocus >= items.length) currentFocus = 0;
                    updateActiveItem(items);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    currentFocus--;
                    if (currentFocus < 0) currentFocus = items.length - 1;
                    updateActiveItem(items);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (currentFocus > -1 && items[currentFocus]) {
                        items[currentFocus].click();
                    }
                } else if (e.key === 'Escape') {
                    cityOfBirthResults.classList.remove('active');
                }
            });
            
            function updateActiveItem(items) {
                items.forEach((item, index) => {
                    item.classList.toggle('selected', index === currentFocus);
                });
            }
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!cityOfBirthSearch.contains(e.target) && !cityOfBirthResults.contains(e.target)) {
                    cityOfBirthResults.classList.remove('active');
                }
            });
            
            // Form validation
            document.getElementById('tcf-registration-form').addEventListener('submit', function(e) {
                if (countryOfBirthSelect.value === FRANCE_ID && !cityOfBirthInput.value) {
                    e.preventDefault();
                    alert('Veuillez sélectionner une commune de naissance.');
                    cityOfBirthSearch.focus();
                }
            });

            // Event listeners
            examSelect.addEventListener('change', updateExamDisplay);
            specialNeedsSelect.addEventListener('change', updateSpecialNeedsDisplay);
            countryOfBirthSelect.addEventListener('change', updateCityOfBirthDisplay);

            testCheckboxes.forEach(function (checkbox) {
                checkbox.addEventListener('change', updateFeeBreakdown);
            });

            // Initialize on page load
            updateExamDisplay();
            updateSpecialNeedsDisplay();
            updateCityOfBirthDisplay();
        });
    </script>
</body>

</html>
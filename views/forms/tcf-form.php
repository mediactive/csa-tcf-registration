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

// Prices configuration (TEMPORARY: divided by 100 for testing)
$prices = [
    'tcf_canada' => 4.20,
    'tcf_quebec' => [
        'comprehension_ecrite' => 1.00,
        'comprehension_orale' => 1.10,
        'expression_ecrite' => 1.00,
        'expression_orale' => 1.10
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
    // Validate required fields with their labels
    $requiredFields = [
        'name' => 'Nom de famille',
        'firstname' => 'Prénom',
        'gender' => 'Genre',
        'birthday' => 'Date de naissance',
        'countryOfBirth' => 'Pays de naissance',
        'nationality' => 'Nationalité',
        'identityDocumentNumber' => 'Numéro de la pièce d\'identité',
        'language' => 'Langue usuelle',
        'address' => 'Adresse complète',
        'city' => 'Ville',
        'postalCode' => 'Code postal',
        'country' => 'Pays',
        'phone' => 'Téléphone',
        'email' => 'Courriel',
        'exam' => 'Examen',
        'reasonsForRegistration' => 'Objectif de l\'examen',
        'disiredSession' => 'Choisissez une session',
        'specialNeeds' => 'Avez-vous besoin d\'aménagements particuliers ?',
        'dataUsageAgreement' => 'J\'accepte que mes données soient utilisées à des fins de formation'
    ];

    foreach ($requiredFields as $field => $label) {
        if (!isset($_POST[$field]) || $_POST[$field] === '') {
            $errors[] = "Le champ \"$label\" est requis.";
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

    // Extract individual test selections
    $selectedTests = $_POST['tcfQuebecSelectedTests'] ?? [];
    $testCE = in_array('1', $selectedTests) ? 1 : 0;
    $testCO = in_array('2', $selectedTests) ? 1 : 0;
    $testEE = in_array('3', $selectedTests) ? 1 : 0;
    $testEO = in_array('4', $selectedTests) ? 1 : 0;

    if (empty($errors)) {
        try {
            // Insert into database
            $stmt = $bdd->prepare("
                INSERT INTO tcf_registrations (
                    name, firstname, gender, birthday, countryOfBirth, cityOfBirth,
                    nationality, identityDocumentNumber, language, oldCandidateCode,
                    address, city, postalCode, country, phoneCountryCode, phone, email,
                    exam, testCE, testCO, testEE, testEO, reasonsForRegistration, disiredSession,
                    specialNeeds, specialNeedsDetails, dataUsageAgreement, total_amount
                ) VALUES (
                    :name, :firstname, :gender, :birthday, :countryOfBirth, :cityOfBirth,
                    :nationality, :identityDocumentNumber, :language, :oldCandidateCode,
                    :address, :city, :postalCode, :country, :phoneCountryCode, :phone, :email,
                    :exam, :testCE, :testCO, :testEE, :testEO, :reasonsForRegistration, :disiredSession,
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
                ':phoneCountryCode' => $_POST['phoneCountryCode'] ?? '+1',
                ':phone' => $_POST['phone'],
                ':email' => $_POST['email'],
                ':exam' => $_POST['exam'],
                ':testCE' => $testCE,
                ':testCO' => $testCO,
                ':testEE' => $testEE,
                ':testEO' => $testEO,
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
            $amountInCents = (int) round($totalAmount * 100); // Square uses cents, must be integer

            $paymentData = [
                'idempotency_key' => uniqid('tcf_', true),
                'quick_pay' => [
                    'name' => "Inscription TCF - $examName",
                    'price_money' => [
                        'amount' => $amountInCents,
                        'currency' => 'CAD'
                    ],
                    'location_id' => SQUARE_LOCATION_ID
                ],
                'checkout_options' => [
                    'redirect_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/views/tcf-confirmation.php?id=' . $registrationId
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
            $curlError = curl_error($ch);
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

            // If Square fails, show detailed error for debugging
            $squareResponse = json_decode($response, true);
            $errorDetail = $squareResponse['errors'][0]['detail'] ?? ($curlError ?: 'Unknown error');
            $errorCode = $squareResponse['errors'][0]['code'] ?? 'HTTP ' . $httpCode;
            $errors[] = "Erreur Square ($errorCode): $errorDetail";

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
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Figtree:ital,wght@0,300..900;1,300..900&family=Lustria&display=swap"
        rel="stylesheet">
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
            padding-top: 48px;
            border-radius: 12px;
            margin-bottom: 32px;
            position: relative;
        }

        .exam-fees legend {
            color: #FFFFFF;
            border-bottom-color: #FFFFFF;
            position: absolute;
            top: 24px;
            left: 32px;
            right: 32px;
            width: calc(100% - 64px);
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

        /* Payment Instructions Section */
        .payment-instructions {
            background: #FAFAFA;
            border: 1px solid #E0E0E0;
            border-radius: 12px;
            padding: 32px;
            margin-bottom: 32px;
        }

        .payment-instructions legend {
            background: #1D2020;
            color: #FFFFFF;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 18px;
        }

        .instruction-item {
            margin-bottom: 24px;
            padding-bottom: 24px;
            border-bottom: 1px solid #E0E0E0;
        }

        .instruction-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .instruction-item h3 {
            font-family: "Lustria", serif;
            font-size: 16px;
            color: #1D2020;
            margin: 0 0 12px 0;
        }

        .instruction-item p {
            font-size: 15px;
            color: #4A4A4A;
            line-height: 1.6;
            margin: 0 0 12px 0;
        }

        .instruction-item p:last-child {
            margin-bottom: 0;
        }

        .instruction-item ul {
            margin: 12px 0 0 0;
            padding-left: 20px;
            color: #4A4A4A;
        }

        .instruction-item li {
            margin-bottom: 8px;
            line-height: 1.5;
        }

        .instruction-item .note {
            font-style: italic;
            color: #808889;
            font-size: 14px;
        }

        .instruction-item.warning {
            background: #FFF8E6;
            border: 1px solid #FFD700;
            border-radius: 8px;
            padding: 20px;
            margin-top: 24px;
        }

        .instruction-item.warning h3 {
            color: #B8860B;
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

        /* Custom field error styling */
        .field-error-message {
            color: #D00023;
            font-size: 13px;
            margin-top: 6px;
            display: none;
        }

        .field-error-message.show {
            display: block;
        }

        input.field-invalid,
        select.field-invalid,
        textarea.field-invalid {
            border-color: #D00023 !important;
            box-shadow: 0 0 0 2px rgba(208, 0, 35, 0.15) !important;
        }

        .autocomplete-wrap input.field-invalid {
            border-color: #D00023 !important;
            box-shadow: 0 0 0 2px rgba(208, 0, 35, 0.15) !important;
        }

        /* Phone Input with Country Code Selector */
        .phone-input-container {
            display: flex;
            border-bottom: 1px solid #808889;
            border-radius: 4px;
            background: #FFFFFF;
            position: relative;
        }

        .phone-input-container:focus-within,
        .phone-input-container:hover {
            border-bottom-color: #D00023;
            /* box-shadow: 0 0 0 2px rgba(208, 0, 35, 0.1); */
        }

        .country-code-selector {
            position: static;
            flex-shrink: 0;
        }

        .country-code-trigger {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 12px 10px;
            background: #F7F7F7;
            border: none;
            border-right: 1px solid #808889;
            cursor: pointer;
            font-size: 14px;
            height: 100%;
            transition: background 0.2s;
        }

        .country-code-trigger:hover {
            background: #EFEFEF;
        }

        .country-code-trigger .flag {
            font-size: 20px;
            line-height: 1;
        }

        .country-code-trigger .dial-code {
            color: #1D2020;
            font-weight: 500;
        }

        .country-code-trigger .dropdown-arrow {
            font-size: 8px;
            color: #808889;
            margin-left: 2px;
        }

        .country-code-dropdown {
            position: absolute;
            top: calc(100% + 2px);
            left: 0;
            width: 300px;
            max-height: 350px;
            background: #FFFFFF;
            border: 1px solid #808889;
            border-radius: 4px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            z-index: 9999;
            display: none;
            overflow: hidden;
        }

        .country-code-dropdown.active {
            display: block;
        }

        .country-search-wrap {
            padding: 8px;
            border-bottom: 1px solid #EEE;
        }

        .country-search {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #DDD;
            border-radius: 4px;
            font-size: 14px;
            outline: none;
        }

        .country-search:focus {
            border-color: #D00023;
        }

        .country-list {
            max-height: 240px;
            overflow-y: auto;
        }

        .country-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            cursor: pointer;
            transition: background 0.15s;
        }

        .country-option:hover,
        .country-option.selected {
            background: #F7F7F7;
        }

        .country-option .flag {
            font-size: 20px;
            line-height: 1;
        }

        .country-option .country-name {
            flex: 1;
            font-size: 14px;
            color: #1D2020;
        }

        .country-option .country-dial-code {
            font-size: 14px;
            color: #808889;
        }

        .phone-number-input {
            flex: 1;
            border: none !important;
            padding: 12px 15px;
            font-size: 16px;
            outline: none;
            min-width: 0;
        }

        .phone-number-input:focus {
            outline: none;
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
                        <input type="date" name="birthday" id="birthday" required
                            value="<?= htmlspecialchars($_POST['birthday'] ?? '') ?>">
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
                        <span class="field-error-message" id="cityOfBirth-error">Veuillez sélectionner une commune de
                            naissance.</span>
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
                        <div class="phone-input-container">
                            <div class="country-code-selector" id="countryCodeSelector">
                                <button type="button" class="country-code-trigger" id="countryCodeTrigger">
                                    <span class="flag" id="selectedFlag">🇨🇦</span>
                                    <span class="dial-code" id="selectedDialCode">+1</span>
                                    <span class="dropdown-arrow">▼</span>
                                </button>
                                <div class="country-code-dropdown" id="countryCodeDropdown">
                                    <div class="country-search-wrap">
                                        <input type="text" class="country-search" id="countrySearch"
                                            placeholder="Search..." autocomplete="off">
                                    </div>
                                    <div class="country-list" id="countryList"></div>
                                </div>
                            </div>
                            <input type="hidden" name="phoneCountryCode" id="phoneCountryCode"
                                value="<?= htmlspecialchars($_POST['phoneCountryCode'] ?? '+1') ?>">
                            <input type="tel" name="phone" id="phone" class="phone-number-input"
                                placeholder="(514) 555-1234" required
                                value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        </div>
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
                                <option value="2" <?= ($_POST['specialNeeds'] ?? '') == '2' ? 'selected' : '' ?>>Non
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

            <!-- Instructions de paiement et d'inscription -->
            <fieldset class="payment-instructions">
                <legend>Instructions de paiement et d'inscription</legend>

                <div class="instruction-item">
                    <h3>1. Paiement requis pour confirmer votre place</h3>
                    <p>Veuillez effectuer le paiement pour l'examen que vous avez choisi.<br>
                        Votre place sera confirmée uniquement après traitement du paiement.</p>
                </div>

                <div class="instruction-item">
                    <h3>2. Courriel de confirmation avant l'examen</h3>
                    <p>Une fois votre paiement reçu, vous recevrez un courriel officiel de confirmation.<br>
                        Ce courriel contiendra toutes les informations importantes concernant votre test, y compris
                        l'horaire, les instructions et les détails pour le jour de l'examen.</p>
                </div>

                <div class="instruction-item">
                    <h3>3. Politique d'annulation</h3>
                    <p>Une fois le paiement effectué, les règles suivantes s'appliquent :</p>
                    <ul>
                        <li><strong>Jusqu'à 7 jours avant l'examen :</strong> des frais d'annulation de 40 CAD par test
                            seront retenus du remboursement.</li>
                        <li><strong>Moins de 7 jours avant l'examen :</strong> aucun remboursement possible, quelle
                            qu'en soit la raison.</li>
                    </ul>
                </div>

                <div class="instruction-item">
                    <h3>4. Documents requis le jour de l'examen</h3>
                    <p>Un passeport valide ou une autre pièce d'identité officielle avec photo est requis.</p>
                    <p class="note">Ces règles s'appliquent à toutes les sessions TCF administrées par notre centre à La
                        Pocatière.</p>
                </div>

                <div class="instruction-item warning">
                    <h3>5. Règle de réinscription – Délai minimal obligatoire de 20 jours</h3>
                    <p>Conformément aux règles officielles du TCF, si vous avez déjà passé une épreuve du TCF Canada, du
                        TCF Québec ou de toute autre version du TCF, vous devez respecter un <strong>délai minimal de 20
                            jours</strong> (ce délai de 20 jours est calculé en jours calendaires et inclut les
                        week-ends ainsi que les jours fériés) avant de vous réinscrire à la même épreuve, et ce, quel
                        que soit le centre d'examen.</p>
                    <ul>
                        <li>En cas de tentative de réinscription avant l'expiration de ce délai, l'inscription sera
                            automatiquement annulée par l'organisme responsable.</li>
                        <li>Cette règle est obligatoire et s'applique à l'ensemble des candidats, dans tous les centres
                            d'examen.</li>
                    </ul>
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

            // Price configuration (TEMPORARY: divided by 100 for testing)
            const prices = {
                tcfCanada: 4.20,
                tcfQuebec: {
                    1: { name: 'Compréhension écrite', price: 1.00 },
                    2: { name: 'Compréhension orale', price: 1.10 },
                    3: { name: 'Expression écrite', price: 1.00 },
                    4: { name: 'Expression orale', price: 1.10 }
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
                const specialNeedsDetailsTextarea = document.getElementById('specialNeedsDetails');
                if (specialNeedsSelect.value === '1') {
                    specialNeedsDetailsContainer.classList.remove('hidden');
                    specialNeedsDetailsTextarea.setAttribute('required', 'required');
                } else {
                    specialNeedsDetailsContainer.classList.add('hidden');
                    specialNeedsDetailsTextarea.removeAttribute('required');
                    specialNeedsDetailsTextarea.value = '';
                }
            }

            // Toggle city of birth visibility (only for France)
            function updateCityOfBirthDisplay() {
                const cityOfBirthInput = document.getElementById('cityOfBirth');
                const cityOfBirthSearch = document.getElementById('cityOfBirthSearch');

                if (countryOfBirthSelect.value === FRANCE_ID) {
                    cityOfBirthContainer.classList.remove('hidden');
                    cityOfBirthInput.setAttribute('required', 'required');
                } else {
                    cityOfBirthContainer.classList.add('hidden');
                    cityOfBirthInput.removeAttribute('required');
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

            cityOfBirthSearch.addEventListener('input', function () {
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
                searchTimeout = setTimeout(function () {
                    fetch('search-municipalities.php?q=' + encodeURIComponent(query))
                        .then(response => response.json())
                        .then(data => {
                            if (data.length === 0) {
                                cityOfBirthResults.innerHTML = '<div class="autocomplete-no-results">Aucune commune trouvée</div>';
                                return;
                            }

                            let html = '';
                            data.forEach(function (item, index) {
                                html += `<div class="autocomplete-item" data-id="${item.id}" data-name="${item.name}">${item.name}</div>`;
                            });
                            cityOfBirthResults.innerHTML = html;
                            currentFocus = -1;

                            // Add click handlers
                            cityOfBirthResults.querySelectorAll('.autocomplete-item').forEach(function (el) {
                                el.addEventListener('click', function () {
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
            cityOfBirthSearch.addEventListener('keydown', function (e) {
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
            document.addEventListener('click', function (e) {
                if (!cityOfBirthSearch.contains(e.target) && !cityOfBirthResults.contains(e.target)) {
                    cityOfBirthResults.classList.remove('active');
                }
            });

            // Form validation
            const cityOfBirthError = document.getElementById('cityOfBirth-error');

            function showCityOfBirthError() {
                cityOfBirthSearch.classList.add('field-invalid');
                cityOfBirthError.classList.add('show');
            }

            function hideCityOfBirthError() {
                cityOfBirthSearch.classList.remove('field-invalid');
                cityOfBirthError.classList.remove('show');
            }

            function validateCityOfBirth() {
                if (countryOfBirthSelect.value === FRANCE_ID && !cityOfBirthInput.value) {
                    showCityOfBirthError();
                    return false;
                } else {
                    hideCityOfBirthError();
                    return true;
                }
            }

            document.getElementById('tcf-registration-form').addEventListener('submit', function (e) {
                // Validate cityOfBirth when France is selected
                if (!validateCityOfBirth()) {
                    e.preventDefault();
                    cityOfBirthSearch.focus();
                    cityOfBirthSearch.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return false;
                }
            });

            // Validate when user leaves the search field
            cityOfBirthSearch.addEventListener('blur', function () {
                // Small delay to allow click on autocomplete item
                setTimeout(function () {
                    validateCityOfBirth();
                }, 200);
            });

            // Clear error when user starts typing in cityOfBirth
            cityOfBirthSearch.addEventListener('input', function () {
                hideCityOfBirthError();
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

            // ===== Country Code Selector for Phone =====
            const countryCodes = [
                { code: 'CA', name: 'Canada', dialCode: '+1', flag: '🇨🇦' },
                { code: 'FR', name: 'France', dialCode: '+33', flag: '🇫🇷' },
                { code: 'US', name: 'États-Unis', dialCode: '+1', flag: '🇺🇸' },
                { code: 'BE', name: 'Belgique', dialCode: '+32', flag: '🇧🇪' },
                { code: 'CH', name: 'Suisse', dialCode: '+41', flag: '🇨🇭' },
                { code: 'MA', name: 'Maroc', dialCode: '+212', flag: '🇲🇦' },
                { code: 'DZ', name: 'Algérie', dialCode: '+213', flag: '🇩🇿' },
                { code: 'TN', name: 'Tunisie', dialCode: '+216', flag: '🇹🇳' },
                { code: 'SN', name: 'Sénégal', dialCode: '+221', flag: '🇸🇳' },
                { code: 'CI', name: 'Côte d\'Ivoire', dialCode: '+225', flag: '🇨🇮' },
                { code: 'CM', name: 'Cameroun', dialCode: '+237', flag: '🇨🇲' },
                { code: 'CD', name: 'RD Congo', dialCode: '+243', flag: '🇨🇩' },
                { code: 'MG', name: 'Madagascar', dialCode: '+261', flag: '🇲🇬' },
                { code: 'HT', name: 'Haïti', dialCode: '+509', flag: '🇭🇹' },
                { code: 'LB', name: 'Liban', dialCode: '+961', flag: '🇱🇧' },
                { code: 'MX', name: 'Mexique', dialCode: '+52', flag: '🇲🇽' },
                { code: 'BR', name: 'Brésil', dialCode: '+55', flag: '🇧🇷' },
                { code: 'GB', name: 'Royaume-Uni', dialCode: '+44', flag: '🇬🇧' },
                { code: 'DE', name: 'Allemagne', dialCode: '+49', flag: '🇩🇪' },
                { code: 'IT', name: 'Italie', dialCode: '+39', flag: '🇮🇹' },
                { code: 'ES', name: 'Espagne', dialCode: '+34', flag: '🇪🇸' },
                { code: 'PT', name: 'Portugal', dialCode: '+351', flag: '🇵🇹' },
                { code: 'NL', name: 'Pays-Bas', dialCode: '+31', flag: '🇳🇱' },
                { code: 'RU', name: 'Russie', dialCode: '+7', flag: '🇷🇺' },
                { code: 'CN', name: 'Chine', dialCode: '+86', flag: '🇨🇳' },
                { code: 'JP', name: 'Japon', dialCode: '+81', flag: '🇯🇵' },
                { code: 'KR', name: 'Corée du Sud', dialCode: '+82', flag: '🇰🇷' },
                { code: 'IN', name: 'Inde', dialCode: '+91', flag: '🇮🇳' },
                { code: 'AU', name: 'Australie', dialCode: '+61', flag: '🇦🇺' },
                { code: 'VN', name: 'Vietnam', dialCode: '+84', flag: '🇻🇳' },
                { code: 'PH', name: 'Philippines', dialCode: '+63', flag: '🇵🇭' },
                { code: 'EG', name: 'Égypte', dialCode: '+20', flag: '🇪🇬' },
                { code: 'NG', name: 'Nigeria', dialCode: '+234', flag: '🇳🇬' },
                { code: 'ZA', name: 'Afrique du Sud', dialCode: '+27', flag: '🇿🇦' },
                { code: 'CO', name: 'Colombie', dialCode: '+57', flag: '🇨🇴' },
                { code: 'AR', name: 'Argentine', dialCode: '+54', flag: '🇦🇷' },
                { code: 'CL', name: 'Chili', dialCode: '+56', flag: '🇨🇱' },
                { code: 'PE', name: 'Pérou', dialCode: '+51', flag: '🇵🇪' },
                { code: 'VE', name: 'Venezuela', dialCode: '+58', flag: '🇻🇪' },
                { code: 'RO', name: 'Roumanie', dialCode: '+40', flag: '🇷🇴' },
                { code: 'PL', name: 'Pologne', dialCode: '+48', flag: '🇵🇱' },
                { code: 'TR', name: 'Turquie', dialCode: '+90', flag: '🇹🇷' },
                { code: 'SA', name: 'Arabie Saoudite', dialCode: '+966', flag: '🇸🇦' },
                { code: 'AE', name: 'Émirats arabes unis', dialCode: '+971', flag: '🇦🇪' },
                { code: 'IL', name: 'Israël', dialCode: '+972', flag: '🇮🇱' },
                { code: 'GR', name: 'Grèce', dialCode: '+30', flag: '🇬🇷' },
                { code: 'CZ', name: 'Tchéquie', dialCode: '+420', flag: '🇨🇿' },
                { code: 'SE', name: 'Suède', dialCode: '+46', flag: '🇸🇪' },
                { code: 'NO', name: 'Norvège', dialCode: '+47', flag: '🇳🇴' },
                { code: 'DK', name: 'Danemark', dialCode: '+45', flag: '🇩🇰' },
                { code: 'FI', name: 'Finlande', dialCode: '+358', flag: '🇫🇮' },
                { code: 'IE', name: 'Irlande', dialCode: '+353', flag: '🇮🇪' },
                { code: 'AT', name: 'Autriche', dialCode: '+43', flag: '🇦🇹' },
                { code: 'HU', name: 'Hongrie', dialCode: '+36', flag: '🇭🇺' },
                { code: 'UA', name: 'Ukraine', dialCode: '+380', flag: '🇺🇦' },
                { code: 'ML', name: 'Mali', dialCode: '+223', flag: '🇲🇱' },
                { code: 'BF', name: 'Burkina Faso', dialCode: '+226', flag: '🇧🇫' },
                { code: 'NE', name: 'Niger', dialCode: '+227', flag: '🇳🇪' },
                { code: 'TD', name: 'Tchad', dialCode: '+235', flag: '🇹🇩' },
                { code: 'GA', name: 'Gabon', dialCode: '+241', flag: '🇬🇦' },
                { code: 'CG', name: 'Congo', dialCode: '+242', flag: '🇨🇬' },
                { code: 'BJ', name: 'Bénin', dialCode: '+229', flag: '🇧🇯' },
                { code: 'TG', name: 'Togo', dialCode: '+228', flag: '🇹🇬' },
                { code: 'GN', name: 'Guinée', dialCode: '+224', flag: '🇬🇳' },
                { code: 'MU', name: 'Maurice', dialCode: '+230', flag: '🇲🇺' },
                { code: 'RE', name: 'La Réunion', dialCode: '+262', flag: '🇷🇪' },
                { code: 'GP', name: 'Guadeloupe', dialCode: '+590', flag: '🇬🇵' },
                { code: 'MQ', name: 'Martinique', dialCode: '+596', flag: '🇲🇶' },
                { code: 'GF', name: 'Guyane française', dialCode: '+594', flag: '🇬🇫' },
                { code: 'NC', name: 'Nouvelle-Calédonie', dialCode: '+687', flag: '🇳🇨' },
                { code: 'PF', name: 'Polynésie française', dialCode: '+689', flag: '🇵🇫' }
            ].sort((a, b) => a.name.localeCompare(b.name, 'fr'));

            const countryCodeTrigger = document.getElementById('countryCodeTrigger');
            const countryCodeDropdown = document.getElementById('countryCodeDropdown');
            const countrySearch = document.getElementById('countrySearch');
            const countryList = document.getElementById('countryList');
            const selectedFlag = document.getElementById('selectedFlag');
            const selectedDialCode = document.getElementById('selectedDialCode');
            const phoneCountryCodeInput = document.getElementById('phoneCountryCode');

            function renderCountryList(filter = '') {
                const filtered = countryCodes.filter(c =>
                    c.name.toLowerCase().includes(filter.toLowerCase()) ||
                    c.dialCode.includes(filter)
                );

                countryList.innerHTML = filtered.map(c => `
                    <div class="country-option" data-code="${c.code}" data-dial="${c.dialCode}" data-flag="${c.flag}">
                        <span class="flag">${c.flag}</span>
                        <span class="country-name">${c.name}</span>
                        <span class="country-dial-code">${c.dialCode}</span>
                    </div>
                `).join('');
            }

            function selectCountry(code, dialCode, flag) {
                selectedFlag.textContent = flag;
                selectedDialCode.textContent = dialCode;
                phoneCountryCodeInput.value = dialCode;
                countryCodeDropdown.classList.remove('active');
                countrySearch.value = '';
                renderCountryList();
            }

            // Initialize list
            renderCountryList();

            // Set initial value from POST if different
            const savedDialCode = phoneCountryCodeInput.value;
            if (savedDialCode) {
                const savedCountry = countryCodes.find(c => c.dialCode === savedDialCode);
                if (savedCountry) {
                    selectedFlag.textContent = savedCountry.flag;
                    selectedDialCode.textContent = savedCountry.dialCode;
                }
            }

            // Toggle dropdown
            countryCodeTrigger.addEventListener('click', function (e) {
                e.preventDefault();
                countryCodeDropdown.classList.toggle('active');
                if (countryCodeDropdown.classList.contains('active')) {
                    countrySearch.focus();
                }
            });

            // Search
            countrySearch.addEventListener('input', function () {
                renderCountryList(this.value);
            });

            // Select country
            countryList.addEventListener('click', function (e) {
                const option = e.target.closest('.country-option');
                if (option) {
                    selectCountry(
                        option.dataset.code,
                        option.dataset.dial,
                        option.dataset.flag
                    );
                }
            });

            // Close on outside click
            document.addEventListener('click', function (e) {
                if (!document.getElementById('countryCodeSelector').contains(e.target)) {
                    countryCodeDropdown.classList.remove('active');
                }
            });

            // Keyboard navigation for country dropdown
            countrySearch.addEventListener('keydown', function (e) {
                const options = countryList.querySelectorAll('.country-option');
                if (e.key === 'ArrowDown' && options.length > 0) {
                    e.preventDefault();
                    options[0].focus();
                } else if (e.key === 'Escape') {
                    countryCodeDropdown.classList.remove('active');
                }
            });
        });
    </script>
</body>

</html>
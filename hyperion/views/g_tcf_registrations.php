<?php
/**
 * TCF Registrations Management Interface
 * For integration into Hyperion CMS
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

// Get current action
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Handle form submission for edit
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit' && $id > 0) {
    try {
        $stmt = $bdd->prepare("
            UPDATE tcf_registrations SET
                name = :name,
                firstname = :firstname,
                gender = :gender,
                birthday = :birthday,
                countryOfBirth = :countryOfBirth,
                cityOfBirth = :cityOfBirth,
                nationality = :nationality,
                identityDocumentNumber = :identityDocumentNumber,
                language = :language,
                oldCandidateCode = :oldCandidateCode,
                address = :address,
                city = :city,
                postalCode = :postalCode,
                country = :country,
                phoneCountryCode = :phoneCountryCode,
                phone = :phone,
                email = :email,
                exam = :exam,
                testCE = :testCE,
                testCO = :testCO,
                testEE = :testEE,
                testEO = :testEO,
                reasonsForRegistration = :reasonsForRegistration,
                disiredSession = :disiredSession,
                specialNeeds = :specialNeeds,
                specialNeedsDetails = :specialNeedsDetails,
                payment_confirmed = :payment_confirmed
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':name' => $_POST['name'],
            ':firstname' => $_POST['firstname'],
            ':gender' => $_POST['gender'],
            ':birthday' => $_POST['birthday'],
            ':countryOfBirth' => $_POST['countryOfBirth'],
            ':cityOfBirth' => $_POST['cityOfBirth'] ?? null,
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
            ':testCE' => isset($_POST['testCE']) ? 1 : 0,
            ':testCO' => isset($_POST['testCO']) ? 1 : 0,
            ':testEE' => isset($_POST['testEE']) ? 1 : 0,
            ':testEO' => isset($_POST['testEO']) ? 1 : 0,
            ':reasonsForRegistration' => $_POST['reasonsForRegistration'],
            ':disiredSession' => $_POST['disiredSession'],
            ':specialNeeds' => $_POST['specialNeeds'],
            ':specialNeedsDetails' => $_POST['specialNeedsDetails'] ?? '',
            ':payment_confirmed' => isset($_POST['payment_confirmed']) ? 1 : 0,
            ':id' => $id
        ]);
        
        $successMessage = 'Les modifications ont été enregistrées avec succès.';
    } catch (PDOException $e) {
        $errorMessage = 'Erreur lors de la mise à jour : ' . $e->getMessage();
    }
}

// Fetch exam sessions for filter
$sessions = $bdd->query("SELECT id, date, exam_center, city FROM tcf_exam_sessions ORDER BY date DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch reference data for dropdowns
$countries = $bdd->query("SELECT id, name FROM countries ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$nationalities = $bdd->query("SELECT id, name FROM nationalities ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$languages = $bdd->query("SELECT id, name FROM tcf_languages ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$reasons = $bdd->query("SELECT id, name FROM tcf_reasons_for_registration ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des inscriptions TCF</title>
    <!-- Google Fonts - Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome 5 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f5f5;
            color: #333;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 20px;
        }
        
        h1 {
            color: #32c5d2;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 24px;
        }
        
        h2 {
            color: #32c5d2;
            font-size: 20px;
            font-weight: 500;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #32c5d2;
        }
        
        /* Filter Section */
        .filter-section {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .filter-section label {
            font-weight: 500;
            margin-right: 12px;
        }
        
        .filter-section select {
            padding: 10px 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            min-width: 300px;
        }
        
        /* Table Styles */
        .table-container {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #32c5d2;
            color: #fff;
        }
        
        th {
            padding: 14px 12px;
            text-align: left;
            font-weight: 500;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        tbody tr:last-child td {
            border-bottom: none;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-canada {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-quebec {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        /* Form Styles */
        .form-container {
            background: #fff;
            padding: 32px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .form-section {
            margin-bottom: 32px;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 6px;
            color: #555;
        }
        
        .bord_form {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .bord_form:focus {
            outline: none;
            border-color: #32c5d2;
        }
        
        .bord_form.name-field {
            height: 50px;
            background-color: #337ab710;
            font-weight: 500;
            font-size: 20px !important;
            line-height: 26px;
        }
        
        textarea.bord_form {
            min-height: 100px;
            resize: vertical;
        }
        
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 8px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #32c5d2;
        }
        
        /* Button Styles */
        .button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .button-primary {
            background: #32c5d2;
            color: #fff;
        }
        
        .button-primary:hover {
            background: #2aa8b3;
        }
        
        .button-secondary {
            background: #6c757d;
            color: #fff;
        }
        
        .button-secondary:hover {
            background: #5a6268;
        }
        
        .button-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        .button-icon {
            padding: 8px;
            min-width: 36px;
            justify-content: center;
        }
        
        /* Messages */
        .alert {
            padding: 14px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Back Link */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #32c5d2;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        /* Action buttons */
        .actions {
            display: flex;
            gap: 8px;
        }
        
        /* Stats bar */
        .stats-bar {
            display: flex;
            gap: 24px;
            margin-bottom: 16px;
            color: #666;
            font-size: 13px;
        }
        
        .stats-bar strong {
            color: #32c5d2;
        }
        
        /* Payment status */
        .payment-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .payment-paid {
            color: #28a745;
        }
        
        .payment-pending {
            color: #ffc107;
        }
        
        /* Autocomplete Styles */
        .autocomplete-container {
            position: relative;
        }
        
        .autocomplete-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 4px 4px;
            max-height: 250px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .autocomplete-results.show {
            display: block;
        }
        
        .autocomplete-item {
            padding: 10px 14px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        
        .autocomplete-item:last-child {
            border-bottom: none;
        }
        
        .autocomplete-item:hover,
        .autocomplete-item.active {
            background: #f0f9fa;
        }
        
        .autocomplete-item .municipality-name {
            font-weight: 500;
            color: #333;
        }
        
        .autocomplete-item .teo-code {
            color: #32c5d2;
            font-size: 13px;
        }
        
        .autocomplete-no-results {
            padding: 10px 14px;
            color: #999;
            font-style: italic;
        }
        
        .clear-field {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 16px;
            padding: 4px;
        }
        
        .clear-field:hover {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($action === 'list'): ?>
            <!-- LIST VIEW -->
            <h1><i class="fas fa-clipboard-list"></i> Gestion des inscriptions TCF</h1>
            
            <div class="filter-section">
                <label for="sessionFilter"><i class="fas fa-filter"></i> Filtrer par session d'examen :</label>
                <select id="sessionFilter" onchange="filterBySession(this.value)">
                    <option value="">Toutes les sessions</option>
                    <?php foreach ($sessions as $session): ?>
                        <option value="<?= $session['id'] ?>" <?= ($_GET['session'] ?? '') == $session['id'] ? 'selected' : '' ?>>
                            <?= date('d/m/Y', strtotime($session['date'])) ?> - <?= htmlspecialchars($session['exam_center']) ?>, <?= htmlspecialchars($session['city']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php
            // Build query with optional session filter
            $sessionFilter = isset($_GET['session']) && $_GET['session'] !== '' ? intval($_GET['session']) : null;
            
            $query = "
                SELECT r.id, r.name, r.firstname, r.phone, r.phoneCountryCode, r.email, r.exam,
                       n.name as nationalityName,
                       c.name as countryName,
                       reason.name as reasonName,
                       r.payment_confirmed
                FROM tcf_registrations r
                LEFT JOIN nationalities n ON r.nationality = n.id
                LEFT JOIN countries c ON r.country = c.id
                LEFT JOIN tcf_reasons_for_registration reason ON r.reasonsForRegistration = reason.id
            ";
            
            if ($sessionFilter) {
                $query .= " WHERE r.disiredSession = :session";
            }
            
            $query .= " ORDER BY r.name ASC, r.firstname ASC";
            
            $stmt = $bdd->prepare($query);
            if ($sessionFilter) {
                $stmt->execute([':session' => $sessionFilter]);
            } else {
                $stmt->execute();
            }
            $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <div class="stats-bar">
                <span><strong><?= count($registrations) ?></strong> inscription(s) trouvée(s)</span>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom, Prénom</th>
                            <th>Nationalité</th>
                            <th>Téléphone</th>
                            <th>Courriel</th>
                            <th>Pays de résidence</th>
                            <th>Examen</th>
                            <th>Objectif</th>
                            <th>Paiement</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($registrations)): ?>
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 40px; color: #999;">
                                    <i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 12px; display: block;"></i>
                                    Aucune inscription trouvée
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($registrations as $reg): ?>
                                <tr>
                                    <td><?= $reg['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($reg['name']) ?></strong>, <?= htmlspecialchars($reg['firstname']) ?></td>
                                    <td><?= htmlspecialchars($reg['nationalityName']) ?></td>
                                    <td><?= htmlspecialchars(($reg['phoneCountryCode'] ?? '') . ' ' . $reg['phone']) ?></td>
                                    <td><a href="mailto:<?= htmlspecialchars($reg['email']) ?>"><?= htmlspecialchars($reg['email']) ?></a></td>
                                    <td><?= htmlspecialchars($reg['countryName']) ?></td>
                                    <td>
                                        <span class="badge <?= $reg['exam'] == 1 ? 'badge-canada' : 'badge-quebec' ?>">
                                            <?= $reg['exam'] == 1 ? 'TCF Canada' : 'TCF Québec' ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($reg['reasonName']) ?></td>
                                    <td>
                                        <span class="payment-status <?= $reg['payment_confirmed'] ? 'payment-paid' : 'payment-pending' ?>">
                                            <i class="fas <?= $reg['payment_confirmed'] ? 'fa-check-circle' : 'fa-clock' ?>"></i>
                                            <?= $reg['payment_confirmed'] ? 'Confirmé' : 'En attente' ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <a href="?action=edit&id=<?= $reg['id'] ?>" class="button button-primary button-sm" title="Modifier">
                                            <i class="fas fa-edit"></i> Modifier
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
        <?php elseif ($action === 'edit' && $id > 0): ?>
            <!-- EDIT VIEW -->
            <?php
            // Fetch registration data
            $stmt = $bdd->prepare("SELECT * FROM tcf_registrations WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $reg = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Fetch current municipality name and teo_code if exists
            $currentMunicipality = null;
            if (!empty($reg['cityOfBirth'])) {
                $stmtMunicipality = $bdd->prepare("SELECT id, name, teo_code FROM french_municipalities WHERE id = :id");
                $stmtMunicipality->execute([':id' => $reg['cityOfBirth']]);
                $currentMunicipality = $stmtMunicipality->fetch(PDO::FETCH_ASSOC);
            }
            
            if (!$reg) {
                echo '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Inscription non trouvée.</div>';
                echo '<a href="?" class="back-link"><i class="fas fa-arrow-left"></i> Retour à la liste</a>';
            } else {
            ?>
            
            <a href="?" class="back-link"><i class="fas fa-arrow-left"></i> Retour à la liste</a>
            
            <h1><i class="fas fa-user-edit"></i> Modifier l'inscription #<?= $reg['id'] ?> - <?= date('d/m/Y', strtotime($reg['created_at'])) ?></h1>
            
            <?php if ($successMessage): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?></div>
            <?php endif; ?>
            
            <?php if ($errorMessage): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errorMessage) ?></div>
            <?php endif; ?>
            
            <form method="POST" class="form-container">
                <!-- Identité -->
                <div class="form-section">
                    <h2><i class="fas fa-user"></i> Identité</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Nom *</label>
                            <input type="text" name="name" id="name" class="bord_form name-field" required
                                value="<?= htmlspecialchars($reg['name']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="firstname">Prénom *</label>
                            <input type="text" name="firstname" id="firstname" class="bord_form name-field" required
                                value="<?= htmlspecialchars($reg['firstname']) ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="gender">Genre *</label>
                            <select name="gender" id="gender" class="bord_form" required>
                                <option value="1" <?= $reg['gender'] == 1 ? 'selected' : '' ?>>Homme</option>
                                <option value="2" <?= $reg['gender'] == 2 ? 'selected' : '' ?>>Femme</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="birthday">Date de naissance *</label>
                            <input type="date" name="birthday" id="birthday" class="bord_form" required
                                value="<?= htmlspecialchars($reg['birthday']) ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="countryOfBirth">Pays de naissance *</label>
                            <select name="countryOfBirth" id="countryOfBirth" class="bord_form" required>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?= $country['id'] ?>" <?= $reg['countryOfBirth'] == $country['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($country['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="cityOfBirthSearch">Commune de naissance</label>
                            <div class="autocomplete-container">
                                <input type="text" id="cityOfBirthSearch" class="bord_form" 
                                    placeholder="Rechercher une commune..."
                                    value="<?= $currentMunicipality ? htmlspecialchars($currentMunicipality['name'] . ' (' . $currentMunicipality['teo_code'] . ')') : '' ?>"
                                    autocomplete="off">
                                <input type="hidden" name="cityOfBirth" id="cityOfBirth" 
                                    value="<?= htmlspecialchars($reg['cityOfBirth'] ?? '') ?>">
                                <?php if ($currentMunicipality): ?>
                                <button type="button" class="clear-field" id="clearCityOfBirth" title="Effacer">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php endif; ?>
                                <div class="autocomplete-results" id="cityOfBirthResults"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nationality">Nationalité *</label>
                            <select name="nationality" id="nationality" class="bord_form" required>
                                <?php foreach ($nationalities as $nat): ?>
                                    <option value="<?= $nat['id'] ?>" <?= $reg['nationality'] == $nat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($nat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="identityDocumentNumber">Numéro de pièce d'identité *</label>
                            <input type="text" name="identityDocumentNumber" id="identityDocumentNumber" class="bord_form" required
                                value="<?= htmlspecialchars($reg['identityDocumentNumber']) ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="language">Langue usuelle *</label>
                            <select name="language" id="language" class="bord_form" required>
                                <?php foreach ($languages as $lang): ?>
                                    <option value="<?= $lang['id'] ?>" <?= $reg['language'] == $lang['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($lang['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="oldCandidateCode">Ancien code candidat</label>
                            <input type="text" name="oldCandidateCode" id="oldCandidateCode" class="bord_form"
                                value="<?= htmlspecialchars($reg['oldCandidateCode'] ?? '') ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Coordonnées -->
                <div class="form-section">
                    <h2><i class="fas fa-map-marker-alt"></i> Coordonnées</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="address">Adresse *</label>
                            <input type="text" name="address" id="address" class="bord_form" required
                                value="<?= htmlspecialchars($reg['address']) ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">Ville *</label>
                            <input type="text" name="city" id="city" class="bord_form" required
                                value="<?= htmlspecialchars($reg['city']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="postalCode">Code postal *</label>
                            <input type="text" name="postalCode" id="postalCode" class="bord_form" required
                                value="<?= htmlspecialchars($reg['postalCode']) ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="country">Pays de résidence *</label>
                            <select name="country" id="country" class="bord_form" required>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?= $country['id'] ?>" <?= $reg['country'] == $country['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($country['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phoneCountryCode">Indicatif téléphonique</label>
                            <input type="text" name="phoneCountryCode" id="phoneCountryCode" class="bord_form"
                                value="<?= htmlspecialchars($reg['phoneCountryCode'] ?? '+1') ?>">
                        </div>
                        <div class="form-group">
                            <label for="phone">Numéro de téléphone *</label>
                            <input type="tel" name="phone" id="phone" class="bord_form" required
                                value="<?= htmlspecialchars($reg['phone']) ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Adresse courriel *</label>
                            <input type="email" name="email" id="email" class="bord_form" required
                                value="<?= htmlspecialchars($reg['email']) ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Examen -->
                <div class="form-section">
                    <h2><i class="fas fa-graduation-cap"></i> Examen</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="exam">Type d'examen *</label>
                            <select name="exam" id="exam" class="bord_form" required>
                                <option value="1" <?= $reg['exam'] == 1 ? 'selected' : '' ?>>TCF Canada</option>
                                <option value="2" <?= $reg['exam'] == 2 ? 'selected' : '' ?>>TCF Québec</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="disiredSession">Session d'examen *</label>
                            <select name="disiredSession" id="disiredSession" class="bord_form" required>
                                <?php foreach ($sessions as $session): ?>
                                    <option value="<?= $session['id'] ?>" <?= $reg['disiredSession'] == $session['id'] ? 'selected' : '' ?>>
                                        <?= date('d/m/Y', strtotime($session['date'])) ?> - <?= htmlspecialchars($session['exam_center']) ?>, <?= htmlspecialchars($session['city']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Épreuves TCF Québec</label>
                            <div class="checkbox-group">
                                <label class="checkbox-item">
                                    <input type="checkbox" name="testCE" <?= $reg['testCE'] ? 'checked' : '' ?>>
                                    Compréhension écrite
                                </label>
                                <label class="checkbox-item">
                                    <input type="checkbox" name="testCO" <?= $reg['testCO'] ? 'checked' : '' ?>>
                                    Compréhension orale
                                </label>
                                <label class="checkbox-item">
                                    <input type="checkbox" name="testEE" <?= $reg['testEE'] ? 'checked' : '' ?>>
                                    Expression écrite
                                </label>
                                <label class="checkbox-item">
                                    <input type="checkbox" name="testEO" <?= $reg['testEO'] ? 'checked' : '' ?>>
                                    Expression orale
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="reasonsForRegistration">Objectif *</label>
                            <select name="reasonsForRegistration" id="reasonsForRegistration" class="bord_form" required>
                                <?php foreach ($reasons as $reason): ?>
                                    <option value="<?= $reason['id'] ?>" <?= $reg['reasonsForRegistration'] == $reason['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($reason['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Aménagements -->
                <div class="form-section">
                    <h2><i class="fas fa-universal-access"></i> Aménagements particuliers</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="specialNeeds">Besoin d'aménagements ?</label>
                            <select name="specialNeeds" id="specialNeeds" class="bord_form">
                                <option value="2" <?= $reg['specialNeeds'] == 2 ? 'selected' : '' ?>>Non</option>
                                <option value="1" <?= $reg['specialNeeds'] == 1 ? 'selected' : '' ?>>Oui</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="specialNeedsDetails">Détails des aménagements</label>
                            <textarea name="specialNeedsDetails" id="specialNeedsDetails" class="bord_form"><?= htmlspecialchars($reg['specialNeedsDetails'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Paiement -->
                <div class="form-section">
                    <h2><i class="fas fa-credit-card"></i> Paiement</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="checkbox-item">
                                <input type="checkbox" name="payment_confirmed" <?= $reg['payment_confirmed'] ? 'checked' : '' ?>>
                                <strong>Paiement confirmé</strong>
                            </label>
                        </div>
                        <div class="form-group">
                            <label>Montant total</label>
                            <input type="text" class="bord_form" value="<?= number_format($reg['total_amount'], 2, ',', ' ') ?> $" readonly style="background: #f5f5f5;">
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                    <a href="?" class="button button-secondary"><i class="fas fa-times"></i> Annuler</a>
                    <button type="submit" class="button button-primary"><i class="fas fa-save"></i> Enregistrer les modifications</button>
                </div>
            </form>
            
            <?php } ?>
        <?php endif; ?>
    </div>
    
    <script>
        function filterBySession(sessionId) {
            if (sessionId) {
                window.location.href = '?session=' + sessionId;
            } else {
                window.location.href = '?';
            }
        }
        
        // Autocomplete for cityOfBirth
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('cityOfBirthSearch');
            const hiddenInput = document.getElementById('cityOfBirth');
            const resultsContainer = document.getElementById('cityOfBirthResults');
            const clearButton = document.getElementById('clearCityOfBirth');
            
            if (!searchInput) return;
            
            let debounceTimer;
            let selectedIndex = -1;
            
            searchInput.addEventListener('input', function() {
                const query = this.value.trim();
                
                // Clear hidden input when typing
                hiddenInput.value = '';
                
                // Hide clear button when typing
                if (clearButton) clearButton.style.display = 'none';
                
                clearTimeout(debounceTimer);
                
                if (query.length < 2) {
                    resultsContainer.classList.remove('show');
                    return;
                }
                
                debounceTimer = setTimeout(() => {
                    fetch('../../views/forms/search-municipalities.php?q=' + encodeURIComponent(query))
                        .then(response => response.json())
                        .then(data => {
                            resultsContainer.innerHTML = '';
                            selectedIndex = -1;
                            
                            if (data.length === 0) {
                                resultsContainer.innerHTML = '<div class="autocomplete-no-results">Aucune commune trouvée</div>';
                            } else {
                                data.forEach((item, index) => {
                                    const div = document.createElement('div');
                                    div.className = 'autocomplete-item';
                                    div.dataset.id = item.id;
                                    div.dataset.name = item.name;
                                    div.dataset.teoCode = item.teo_code || '';
                                    div.dataset.index = index;
                                    div.innerHTML = `
                                        <span class="municipality-name">${item.name}</span>
                                        <span class="teo-code">(${item.teo_code || 'N/A'})</span>
                                    `;
                                    div.addEventListener('click', function() {
                                        selectMunicipality(this.dataset.id, this.dataset.name, this.dataset.teoCode);
                                    });
                                    resultsContainer.appendChild(div);
                                });
                            }
                            
                            resultsContainer.classList.add('show');
                        })
                        .catch(error => {
                            console.error('Error fetching municipalities:', error);
                        });
                }, 300);
            });
            
            function selectMunicipality(id, name, teoCode) {
                hiddenInput.value = id;
                searchInput.value = name + (teoCode ? ' (' + teoCode + ')' : '');
                resultsContainer.classList.remove('show');
                
                // Show/create clear button
                if (!clearButton) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'clear-field';
                    btn.id = 'clearCityOfBirth';
                    btn.title = 'Effacer';
                    btn.innerHTML = '<i class="fas fa-times"></i>';
                    btn.addEventListener('click', clearSelection);
                    searchInput.parentElement.appendChild(btn);
                } else {
                    clearButton.style.display = 'block';
                }
            }
            
            function clearSelection() {
                hiddenInput.value = '';
                searchInput.value = '';
                if (clearButton) clearButton.style.display = 'none';
                searchInput.focus();
            }
            
            if (clearButton) {
                clearButton.addEventListener('click', clearSelection);
            }
            
            // Keyboard navigation
            searchInput.addEventListener('keydown', function(e) {
                const items = resultsContainer.querySelectorAll('.autocomplete-item');
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
                    updateSelection(items);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    selectedIndex = Math.max(selectedIndex - 1, 0);
                    updateSelection(items);
                } else if (e.key === 'Enter' && selectedIndex >= 0) {
                    e.preventDefault();
                    const selected = items[selectedIndex];
                    if (selected) {
                        selectMunicipality(selected.dataset.id, selected.dataset.name, selected.dataset.teoCode);
                    }
                } else if (e.key === 'Escape') {
                    resultsContainer.classList.remove('show');
                }
            });
            
            function updateSelection(items) {
                items.forEach((item, index) => {
                    item.classList.toggle('active', index === selectedIndex);
                });
                if (items[selectedIndex]) {
                    items[selectedIndex].scrollIntoView({ block: 'nearest' });
                }
            }
            
            // Close results when clicking outside
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
                    resultsContainer.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>

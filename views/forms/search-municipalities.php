<?php
/**
 * AJAX endpoint for searching French municipalities
 */

header('Content-Type: application/json; charset=utf-8');

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
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get search query
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

// Search municipalities by name
$stmt = $bdd->prepare("
    SELECT id, name, teo_code 
    FROM french_municipalities 
    WHERE name LIKE :query 
    ORDER BY name 
    LIMIT 20
");

$stmt->execute([':query' => $query . '%']);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($results);


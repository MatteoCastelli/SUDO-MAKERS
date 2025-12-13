<?php
header('Content-Type: application/json');

// Ottieni il termine di ricerca
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$csvPath = __DIR__ . '/../assets/data/comuni.csv';

if (!file_exists($csvPath)) {
    echo json_encode([]);
    exit;
}

$handle = fopen($csvPath, 'r');
if (!$handle) {
    echo json_encode([]);
    exit;
}

// Salta la riga di intestazione
fgetcsv($handle);

$queryLower = strtolower($query);
$risultati = [];

while (($row = fgetcsv($handle)) !== false) {
    if (count($row) >= 2) {
        $denominazione = trim($row[0]);
        
        // Cerca i comuni che iniziano con la query
        if (stripos($denominazione, $query) === 0) {
            $risultati[] = [
                'nome' => $denominazione,
                'codice' => trim($row[1])
            ];
        }
    }
    
    // Limita a 10 risultati per prestazioni
    if (count($risultati) >= 10) {
        break;
    }
}

fclose($handle);

echo json_encode($risultati);

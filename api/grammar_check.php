<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] != 'student' && $_SESSION['user_type'] != 'lecturer')) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$text = $_POST['text'] ?? '';
if (strlen($text) < 3) {
    echo json_encode(['matches' => []]);
    exit;
}

// Use LanguageTool API (free, no key required)
$url = 'https://api.languagetool.org/v2/check';
$data = [
    'text' => $text,
    'language' => 'en-US',
    'enabledOnly' => 'false'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200 && $response) {
    echo $response;
} else {
    // Fallback: return empty matches (no suggestions) but do not break
    echo json_encode(['matches' => [], 'error' => 'Service temporarily unavailable']);
}
?>
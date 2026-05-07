<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$ca = isset($_GET['ca']) ? intval($_GET['ca']) : 0;
if (!$ca) {
    echo json_encode(['erro' => 'CA inválido']);
    exit;
}

$url = "https://consultaca.com/" . $ca;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
    'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
    'Accept-Encoding: gzip, deflate, br',
    'Connection: keep-alive',
    'Upgrade-Insecure-Requests: 1',
    'Referer: https://consultaca.com/',
]);
curl_setopt($ch, CURLOPT_ENCODING, ''); // aceita gzip automaticamente

$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode(['erro' => 'HTTP ' . $httpCode]);
    exit;
}

// Tenta extrair a validade
preg_match('/(\d{2}\/\d{2}\/\d{4})/', $html, $matches);
$validade = $matches[1] ?? null;

echo json_encode([
    'ca'       => $ca,
    'validade' => $validade,
    'status'   => $validade ? 'ok' : 'não encontrado'
]);
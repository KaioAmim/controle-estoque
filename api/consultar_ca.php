<?php
/**
 * API de Consulta de CA (Certificado de Aprovação)
 * Realiza web scraping no site consultaca.com para obter a validade e descrição do EPI.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Obtém o número do CA via GET
$ca = isset($_GET['ca']) ? preg_replace('/\D/', '', $_GET['ca']) : '';

if (empty($ca)) {
    echo json_encode([
        'sucesso' => false,
        'erro'    => 'Número do CA não informado.'
    ]);
    exit;
}

// URL do site ConsultaCA
$url = "https://consultaca.com/{$ca}";

// Configuração do cURL para busca do CA
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER     => [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept-Language: pt-BR,pt;q=0.9',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    ],
]);

$html      = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode([
        'sucesso' => false,
        'erro'    => 'Falha ao conectar ao consultaca.com.'
    ]);
    exit;
}

if ($httpCode === 404) {
    echo json_encode([
        'sucesso' => false,
        'erro'    => "CA {$ca} não encontrado."
    ]);
    exit;
}

if ($httpCode !== 200 || empty($html)) {
    echo json_encode([
        'sucesso' => false,
        'erro'    => "Erro ao buscar o CA (HTTP {$httpCode})."
    ]);
    exit;
}

// Parsing do HTML
$dom = new DOMDocument();
@$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
$xpath = new DOMXPath($dom);

$validade = null;
$descricao = null;

// 1. Extrair Validade
// O site usa <span class="validade_ca"> ou similar
$nodesValidade = $xpath->query("//span[contains(@class, 'validade_ca')]");
foreach ($nodesValidade as $node) {
    $texto = trim($node->textContent);
    if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $texto, $m)) {
        $validade = "{$m[3]}-{$m[2]}-{$m[1]}"; // ISO format
        break;
    }
}

// 2. Extrair Descrição Completa
// O site usa um <h3>Descrição Completa</h3> seguido de um <p>
$nodesDesc = $xpath->query("//h3[contains(text(), 'Descrição Completa')]/following-sibling::p[1]");
if ($nodesDesc->length > 0) {
    $descricao = trim($nodesDesc->item(0)->textContent);
}

// Fallback para validade se não encontrou pelo span
if (!$validade) {
    $textoLimpo = strip_tags($html);
    if (preg_match('/valid\w*[^\d]{0,50}(\d{2})\/(\d{2})\/(\d{4})/i', $textoLimpo, $m)) {
        $validade = "{$m[3]}-{$m[2]}-{$m[1]}";
    }
}

echo json_encode([
    'sucesso'   => true,
    'ca'        => $ca,
    'validade'  => $validade,
    'descricao' => $descricao
]);

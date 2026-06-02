<?php
// ═══════════════════════════════════════════════════════════════
//  forgot_password.php — Envio via API HTTP do Brevo
//  ⚠ Configure as variáveis no arquivo .env na raiz do projeto
// ═══════════════════════════════════════════════════════════════

// Carrega variáveis do .env
$envFile = __DIR__ . '/../.env';
if (is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
        $_ENV[trim($key)] = trim($value);
    }
}

$BREVO_API_KEY = getenv('BREVO_API_KEY');
$BREVO_FROM    = getenv('BREVO_FROM');
$BREVO_NAME    = 'UniStock — Controle de Estoque';
$TOKEN_TTL     = 30; // minutos

// BASE_URL
if (getenv('BASE_URL')) {
    $BASE_URL = rtrim(getenv('BASE_URL'), '/');
} else {
    $scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host      = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $scriptDir = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
    $BASE_URL  = $scheme . '://' . $host . $scriptDir;
}

// ═══════════════════════════════════════════════════════════════

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$d       = json_decode(file_get_contents('php://input'), true);
$usuario = trim($d['usuario'] ?? '');
$email   = trim($d['email']   ?? '');

if (!$usuario || !$email) {
    echo json_encode(['success' => false, 'erro' => 'Preencha o usuário e o e-mail.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'erro' => 'E-mail inválido.']);
    exit;
}

// Busca usuário
$stmt = $conn->prepare('SELECT id, nome FROM usuarios WHERE usuario = ? AND email = ?');
$stmt->bind_param('ss', $usuario, $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    sleep(1);
    echo json_encode(['success' => true]);
    exit;
}

// Cria tabela de tokens se não existir
$conn->query("
    CREATE TABLE IF NOT EXISTS reset_tokens (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        token      VARCHAR(64) NOT NULL UNIQUE,
        expira_em  DATETIME NOT NULL,
        usado      TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    )
");

// Apaga tokens antigos do usuário
$stmt = $conn->prepare('DELETE FROM reset_tokens WHERE usuario_id = ?');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$stmt->close();

// Gera token seguro
$token  = bin2hex(random_bytes(32));
$expira = date('Y-m-d H:i:s', strtotime("+{$TOKEN_TTL} minutes"));

$stmt = $conn->prepare('INSERT INTO reset_tokens (usuario_id, token, expira_em) VALUES (?, ?, ?)');
$stmt->bind_param('iss', $user['id'], $token, $expira);
$stmt->execute();
$stmt->close();

$link = $BASE_URL . '/reset_senha.html?token=' . urlencode($token);

// Envia e-mail via API HTTP do Brevo
$erro = '';
$ok   = _brevoSend([
    'api_key'   => $BREVO_API_KEY,
    'from'      => $BREVO_FROM,
    'from_name' => $BREVO_NAME,
    'to'        => $email,
    'to_name'   => $user['nome'],
    'subject'   => 'Redefinição de senha - UniStock',
    'html'      => _emailHtml($user['nome'], $link, $TOKEN_TTL),
    'text'      => "Olá, {$user['nome']}!\n\nAcesse o link para redefinir sua senha (válido por {$TOKEN_TTL} minutos):\n{$link}\n\nSe não foi você, ignore este e-mail.",
], $erro);

if (!$ok) {
    error_log("[UniStock][forgot_password] Erro Brevo API: $erro");
    echo json_encode(['success' => false, 'erro' => 'Não foi possível enviar o e-mail. Erro: ' . $erro]);
    exit;
}

echo json_encode(['success' => true]);

// ── Envio via API HTTP do Brevo ──────────────────────────────────────────────
function _brevoSend(array $c, string &$err = ''): bool {
    $payload = json_encode([
        'sender'     => ['name' => $c['from_name'], 'email' => $c['from']],
        'to'         => [['email' => $c['to'], 'name' => $c['to_name']]],
        'subject'    => $c['subject'],
        'htmlContent'=> $c['html'],
        'textContent'=> $c['text'],
    ]);

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'api-key: ' . $c['api_key'],
            'content-type: application/json',
        ],
        CURLOPT_TIMEOUT        => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) { $err = "cURL: $curlErr"; return false; }

    $body = json_decode($response, true);
    if ($httpCode >= 200 && $httpCode < 300) return true;

    $err = "HTTP $httpCode — " . ($body['message'] ?? $response);
    return false;
}

// ── Template HTML do e-mail ──────────────────────────────────────────────────
function _emailHtml(string $nome, string $link, int $ttl): string { return <<<HTML
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 16px;background:#f0f2f5;">
<tr><td align="center">
<table width="540" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:10px;border:1px solid #dde1e8;max-width:540px;">
  <tr><td style="background:#1a1a2e;padding:28px 36px;border-radius:10px 10px 0 0;">
    <p style="margin:0;color:#fff;font-size:20px;font-weight:bold;">&#128230; UniStock</p>
    <p style="margin:3px 0 0;color:#8888aa;font-size:12px;">Controle de Estoque</p>
  </td></tr>
  <tr><td style="padding:36px;">
    <p style="margin:0 0 10px;font-size:20px;font-weight:bold;color:#1a1a2e;">Redefinição de senha</p>
    <p style="margin:0 0 18px;font-size:14px;color:#444;line-height:1.6;">
      Olá, <strong>{$nome}</strong>! Recebemos uma solicitação para redefinir a senha da sua conta.
    </p>
    <table cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 20px;background:#fff8e1;border-left:4px solid #f59e0b;border-radius:4px;">
      <tr><td style="padding:12px 16px;font-size:13px;color:#7c5c00;">
        &#9200; Este link expira em <strong>{$ttl} minutos</strong>.
      </td></tr>
    </table>
    <table cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
      <tr><td style="background:#1a1a2e;border-radius:8px;">
        <a href="{$link}" style="display:inline-block;padding:14px 32px;color:#fff;font-size:15px;font-weight:bold;text-decoration:none;">
          Redefinir minha senha
        </a>
      </td></tr>
    </table>
    <p style="margin:0 0 4px;font-size:12px;color:#999;">Se o botão não funcionar, copie o link:</p>
    <p style="margin:0;font-size:11px;word-break:break-all;"><a href="{$link}" style="color:#2563eb;">{$link}</a></p>
  </td></tr>
  <tr><td style="height:1px;background:#eee;"></td></tr>
  <tr><td style="padding:18px 36px;background:#f9fafb;border-radius:0 0 10px 10px;">
    <p style="margin:0;font-size:12px;color:#aaa;line-height:1.6;">
      Se você não solicitou isso, ignore este e-mail. Sua senha atual continuará a mesma.
    </p>
  </td></tr>
</table>
</td></tr></table>
</body></html>
HTML; }
?>

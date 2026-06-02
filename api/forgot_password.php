<?php
// ═══════════════════════════════════════════════════════════════
//  forgot_password.php — AUTOSSUFICIENTE (sem dependências)
//
//  ⚠ Configure as variáveis no arquivo .env na raiz do projeto
// ═══════════════════════════════════════════════════════════════

// Carrega variáveis do .env
$envFile = __DIR__ . '/../.env';

if (is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);

        putenv(trim($key) . '=' . trim($value));
        $_ENV[trim($key)] = trim($value);
    }
}

$SMTP_USER = getenv('SMTP_USER') ?: '';
$SMTP_PASS = getenv('SMTP_PASS') ?: '';

// ── BASE_URL: detecta automaticamente se não estiver no .env ──
if (getenv('BASE_URL')) {
    $BASE_URL = rtrim(getenv('BASE_URL'), '/');
} else {
    // Detecta protocolo (http ou https)
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    // Detecta host (funciona em qualquer dispositivo na rede)
    $host   = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    // Detecta o caminho base (pasta do projeto)
    $scriptDir = dirname(dirname($_SERVER['SCRIPT_NAME'])); // sobe de /api/ para /
    $scriptDir = rtrim($scriptDir, '/');
    $BASE_URL  = $scheme . '://' . $host . $scriptDir;
}

// ── Não precisa alterar abaixo ──────────────────────────────────
$SMTP_HOST = 'smtp.gmail.com';
$SMTP_PORT = 587;
$SMTP_FROM = $SMTP_USER;
$SMTP_NAME = 'UniStock — Controle de Estoque';
$TOKEN_TTL = 30; // minutos
// ═══════════════════════════════════════════════════════════════

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db.php';

error_log("METHOD = " . $_SERVER['REQUEST_METHOD']);

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

// Envia e-mail via SMTP puro
$erroSmtp = '';
$ok = _smtpSend([
    'host'      => $SMTP_HOST,
    'port'      => $SMTP_PORT,
    'user'      => $SMTP_USER,
    'pass'      => $SMTP_PASS,
    'from'      => $SMTP_FROM,
    'from_name' => $SMTP_NAME,
    'to'        => $email,
    'to_name'   => $user['nome'],
    'subject'   => 'Redefinicao de senha - UniStock',
    'body'      => _emailHtml($user['nome'], $link, $TOKEN_TTL),
    'altbody'   => "Ola, {$user['nome']}!\n\nAcesse o link para redefinir sua senha (valido por {$TOKEN_TTL} minutos):\n{$link}\n\nSe nao foi voce, ignore este e-mail.",
], $erroSmtp);

if (!$ok) {
    error_log("[UniStock][forgot_password] Erro SMTP: $erroSmtp");
    echo json_encode([
        'success' => false,
        'erro'    => 'Nao foi possivel enviar o e-mail. Verifique as configuracoes SMTP no arquivo .env. Erro: ' . $erroSmtp
    ]);
    exit;
}

echo json_encode(['success' => true]);

// ── Cliente SMTP puro (sem bibliotecas externas) ─────────────────────────────
function _smtpSend(array $c, string &$err = ''): bool {
    $ssl = ((int)$c['port'] === 465);
    $ctx = stream_context_create(['ssl' => [
        'verify_peer'       => false,
        'verify_peer_name'  => false,
        'allow_self_signed' => true,
    ]]);
    $remote = ($ssl ? 'ssl://' : '') . $c['host'] . ':' . $c['port'];
    $sock   = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$sock) { $err = "Conexao recusada ({$c['host']}:{$c['port']}): $errstr"; return false; }
    stream_set_timeout($sock, 15);

    $rd = function() use ($sock): string {
        $b = '';
        while ($l = fgets($sock, 512)) { $b .= $l; if (isset($l[3]) && $l[3] === ' ') break; }
        return $b;
    };
    $wr  = fn($s) => fwrite($sock, $s . "\r\n");
    $exp = function(string $code) use ($rd, &$err): bool {
        $r = $rd();
        if (strpos($r, $code) !== 0) { $err = "SMTP: esperava $code, recebeu: $r"; return false; }
        return true;
    };

    $rd(); // banner
    $wr("EHLO " . (gethostname() ?: 'localhost'));
    $ehlo = $rd();
    if (strpos($ehlo, '250') !== 0) { $wr("HELO localhost"); if (!$exp('250')) { fclose($sock); return false; } }

    if (!$ssl) { // STARTTLS
        $wr("STARTTLS");
        if (!$exp('220')) { fclose($sock); return false; }
        if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            $err = "Falha ao ativar TLS."; fclose($sock); return false;
        }
        $wr("EHLO " . (gethostname() ?: 'localhost'));
        $rd();
    }

    $wr("AUTH LOGIN");
    if (!$exp('334')) { fclose($sock); return false; }
    $wr(base64_encode($c['user']));
    if (!$exp('334')) { fclose($sock); return false; }
    $wr(base64_encode($c['pass']));
    if (!$exp('235')) { $err = "Autenticacao falhou. Verifique SMTP_USER e SMTP_PASS no arquivo .env."; fclose($sock); return false; }

    $wr("MAIL FROM:<{$c['from']}>");
    if (!$exp('250')) { fclose($sock); return false; }
    $wr("RCPT TO:<{$c['to']}>");
    if (!$exp('250')) { fclose($sock); return false; }
    $wr("DATA");
    if (!$exp('354')) { fclose($sock); return false; }

    $bd  = 'unistock_' . md5(uniqid('', true));
    $fn  = $c['from_name'] ? '=?UTF-8?B?' . base64_encode($c['from_name']) . '?= <' . $c['from'] . '>' : $c['from'];
    $tn  = $c['to_name']   ? '=?UTF-8?B?' . base64_encode($c['to_name'])   . '?= <' . $c['to']   . '>' : $c['to'];
    $sb  = '=?UTF-8?B?' . base64_encode($c['subject']) . '?=';

    $msg  = "Date: " . date('r') . "\r\n";
    $msg .= "From: $fn\r\nTo: $tn\r\nSubject: $sb\r\n";
    $msg .= "MIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$bd\"\r\n\r\n";
    $msg .= "--$bd\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $msg .= chunk_split(base64_encode($c['altbody'])) . "\r\n";
    $msg .= "--$bd\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $msg .= chunk_split(base64_encode($c['body'])) . "\r\n";
    $msg .= "--$bd--\r\n.";
    fwrite($sock, $msg . "\r\n");
    if (!$exp('250')) { fclose($sock); return false; }

    $wr("QUIT");
    fclose($sock);
    return true;
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
    <p style="margin:0 0 10px;font-size:20px;font-weight:bold;color:#1a1a2e;">Redefinicao de senha</p>
    <p style="margin:0 0 18px;font-size:14px;color:#444;line-height:1.6;">
      Ola, <strong>{$nome}</strong>! Recebemos uma solicitacao para redefinir a senha da sua conta.
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
    <p style="margin:0 0 4px;font-size:12px;color:#999;">Se o botao nao funcionar, copie o link:</p>
    <p style="margin:0;font-size:11px;word-break:break-all;"><a href="{$link}" style="color:#2563eb;">{$link}</a></p>
  </td></tr>
  <tr><td style="height:1px;background:#eee;"></td></tr>
  <tr><td style="padding:18px 36px;background:#f9fafb;border-radius:0 0 10px 10px;">
    <p style="margin:0;font-size:12px;color:#aaa;line-height:1.6;">
      Se voce nao solicitou isso, ignore este e-mail. Sua senha atual continuara a mesma.
    </p>
  </td></tr>
</table>
</td></tr></table>
</body></html>
HTML; }
?>

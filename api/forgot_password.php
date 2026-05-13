<?php
/**
 * forgot_password.php
 * POST  { "usuario": "...", "email": "..." }
 *   → valida usuário+email, gera token, envia e-mail com link de reset.
 * 
 * Requer PHPMailer. Instale via Composer:
 *   composer require phpmailer/phpmailer
 * 
 * Configurações de SMTP estão na seção "CONFIGURAÇÕES" abaixo.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db.php';

// ── CONFIGURAÇÕES ─────────────────────────────────────────────────────────────
// Troque pelos dados reais do seu servidor SMTP / conta de e-mail.
define('SMTP_HOST',   'smtp.gmail.com');    // ex: smtp.gmail.com | smtp.office365.com
define('SMTP_PORT',   587);                 // 587 = TLS | 465 = SSL
define('SMTP_USER',   'seuemail@gmail.com');
define('SMTP_PASS',   'sua_senha_de_app');  // Senha de app (Gmail) ou senha SMTP
define('SMTP_FROM',   'seuemail@gmail.com');
define('SMTP_NAME',   'UniStock — Controle de Estoque');

// URL base do sistema — usada para montar o link de redefinição
define('BASE_URL',    'http://localhost/controle-estoque');

// Validade do token em minutos
define('TOKEN_TTL',   30);
// ─────────────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$d       = json_decode(file_get_contents('php://input'), true);
$usuario = trim($d['usuario'] ?? '');
$email   = trim($d['email']   ?? '');

// Validação básica
if (!$usuario || !$email) {
    echo json_encode(['success' => false, 'erro' => 'Preencha o usuário e o e-mail.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'erro' => 'E-mail inválido.']);
    exit;
}

// Busca o usuário no banco (usuario + email devem bater)
$stmt = $conn->prepare('SELECT id, nome FROM usuarios WHERE usuario = ? AND email = ?');
$stmt->bind_param('ss', $usuario, $email);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();

// Resposta genérica — não revela se usuário existe ou não
if (!$user) {
    // Aguarda 1 segundo para dificultar enumeração por timing
    sleep(1);
    echo json_encode([
        'success' => true,
        'msg'     => 'Se os dados estiverem corretos, você receberá um e-mail em breve.'
    ]);
    exit;
}

// Garante que a tabela de tokens existe
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

// Invalida tokens anteriores do mesmo usuário
$stmt = $conn->prepare('DELETE FROM reset_tokens WHERE usuario_id = ?');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$stmt->close();

// Gera token seguro (64 chars hex)
$token    = bin2hex(random_bytes(32));
$expira   = date('Y-m-d H:i:s', strtotime('+' . TOKEN_TTL . ' minutes'));

$stmt = $conn->prepare('INSERT INTO reset_tokens (usuario_id, token, expira_em) VALUES (?, ?, ?)');
$stmt->bind_param('iss', $user['id'], $token, $expira);
$stmt->execute();
$stmt->close();

// ── Envio de e-mail via PHPMailer ─────────────────────────────────────────────
$resetLink = BASE_URL . '/reset_senha.html?token=' . urlencode($token);

try {
    // Tenta carregar PHPMailer via Composer (autoload)
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        throw new Exception('PHPMailer não instalado. Execute: composer require phpmailer/phpmailer');
    }
    require_once $autoload;

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host        = SMTP_HOST;
    $mail->SMTPAuth    = true;
    $mail->Username    = SMTP_USER;
    $mail->Password    = SMTP_PASS;
    $mail->SMTPSecure  = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port        = SMTP_PORT;
    $mail->CharSet     = 'UTF-8';

    $mail->setFrom(SMTP_FROM, SMTP_NAME);
    $mail->addAddress($email, $user['nome']);
    $mail->isHTML(true);
    $mail->Subject = 'Redefinição de senha — UniStock';
    $mail->Body    = emailTemplate($user['nome'], $resetLink, TOKEN_TTL);
    $mail->AltBody = "Olá {$user['nome']},\n\nClique no link abaixo para redefinir sua senha (válido por " . TOKEN_TTL . " minutos):\n\n$resetLink\n\nSe não foi você, ignore este e-mail.";

    $mail->send();

    echo json_encode([
        'success' => true,
        'msg'     => 'Se os dados estiverem corretos, você receberá um e-mail em breve.'
    ]);
} catch (Exception $e) {
    // Em produção, logue o erro sem expô-lo ao usuário
    error_log('Erro ao enviar e-mail de reset: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'erro'    => 'Erro ao enviar e-mail. Contate o administrador.'
    ]);
}

// ── Template HTML do e-mail ───────────────────────────────────────────────────
function emailTemplate(string $nome, string $link, int $ttl): string {
    return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:32px 0;">
    <tr><td align="center">
      <table width="520" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e0e0e0;">
        
        <!-- Cabeçalho -->
        <tr>
          <td style="background:#1a1a2e;padding:24px 32px;">
            <p style="margin:0;color:#ffffff;font-size:20px;font-weight:bold;">📦 UniStock</p>
            <p style="margin:4px 0 0;color:#a0a0c0;font-size:13px;">Controle de Estoque</p>
          </td>
        </tr>

        <!-- Corpo -->
        <tr>
          <td style="padding:32px;">
            <p style="margin:0 0 16px;font-size:16px;color:#1a1a2e;">Olá, <strong>{$nome}</strong>!</p>
            <p style="margin:0 0 16px;font-size:14px;color:#444;line-height:1.6;">
              Recebemos uma solicitação para redefinir a senha da sua conta no
              <strong>UniStock</strong>. Clique no botão abaixo para criar uma nova senha.
            </p>
            <p style="margin:0 0 8px;font-size:13px;color:#888;">
              ⏱ Este link expira em <strong>{$ttl} minutos</strong>.
            </p>

            <!-- Botão -->
            <table cellpadding="0" cellspacing="0" style="margin:24px 0;">
              <tr>
                <td style="background:#1a1a2e;border-radius:6px;">
                  <a href="{$link}" style="display:inline-block;padding:12px 28px;color:#ffffff;font-size:15px;font-weight:bold;text-decoration:none;">
                    Redefinir minha senha
                  </a>
                </td>
              </tr>
            </table>

            <!-- Link alternativo -->
            <p style="margin:0 0 8px;font-size:12px;color:#888;">
              Se o botão não funcionar, copie e cole o link abaixo no navegador:
            </p>
            <p style="margin:0;font-size:11px;color:#1a73e8;word-break:break-all;">
              <a href="{$link}" style="color:#1a73e8;">{$link}</a>
            </p>
          </td>
        </tr>

        <!-- Rodapé -->
        <tr>
          <td style="background:#f9f9f9;padding:16px 32px;border-top:1px solid #e0e0e0;">
            <p style="margin:0;font-size:12px;color:#999;line-height:1.5;">
              Se você não solicitou a redefinição de senha, ignore este e-mail.
              Sua senha atual permanece a mesma.<br>
              Por segurança, nunca compartilhe este link.
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}
?>

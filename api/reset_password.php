<?php
/**
 * reset_password.php
 * 
 * GET  ?token=xxx          → valida o token (usado na página reset_senha.html)
 *   Retorna: { valid: bool, erro?: string }
 * 
 * POST { "token": "...", "senha": "..." }
 *   → valida token e atualiza a senha do usuário
 *   Retorna: { success: bool, erro?: string }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db.php';

// ── GET: verifica se token é válido ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = trim($_GET['token'] ?? '');
    if (!$token) {
        echo json_encode(['valid' => false, 'erro' => 'Token não informado.']);
        exit;
    }

    $stmt = $conn->prepare('
        SELECT rt.id, rt.expira_em, rt.usado, u.nome
        FROM reset_tokens rt
        JOIN usuarios u ON u.id = rt.usuario_id
        WHERE rt.token = ?
        LIMIT 1
    ');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['valid' => false, 'erro' => 'Link inválido ou já utilizado.']);
        exit;
    }
    if ($row['usado']) {
        echo json_encode(['valid' => false, 'erro' => 'Este link já foi utilizado.']);
        exit;
    }
    if (strtotime($row['expira_em']) < time()) {
        echo json_encode(['valid' => false, 'erro' => 'Link expirado. Solicite um novo.']);
        exit;
    }

    // Retorna quantos minutos restam
    $restam = (int)ceil((strtotime($row['expira_em']) - time()) / 60);
    echo json_encode(['valid' => true, 'nome' => $row['nome'], 'minutos_restantes' => $restam]);
    exit;
}

// ── POST: troca a senha ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d     = json_decode(file_get_contents('php://input'), true);
    $token = trim($d['token'] ?? '');
    $senha = $d['senha'] ?? '';

    if (!$token || !$senha) {
        echo json_encode(['success' => false, 'erro' => 'Token e senha são obrigatórios.']);
        exit;
    }
    if (strlen($senha) < 6) {
        echo json_encode(['success' => false, 'erro' => 'A senha deve ter pelo menos 6 caracteres.']);
        exit;
    }

    // Busca token válido e não usado
    $stmt = $conn->prepare('
        SELECT rt.id, rt.usuario_id, rt.expira_em, rt.usado
        FROM reset_tokens rt
        WHERE rt.token = ?
        LIMIT 1
    ');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'erro' => 'Link inválido.']);
        exit;
    }
    if ($row['usado']) {
        echo json_encode(['success' => false, 'erro' => 'Este link já foi utilizado.']);
        exit;
    }
    if (strtotime($row['expira_em']) < time()) {
        echo json_encode(['success' => false, 'erro' => 'Link expirado. Solicite um novo.']);
        exit;
    }

    // Atualiza a senha do usuário
    // NOTA: O sistema atual armazena senhas em texto puro. Se quiser usar hash:
    // $senha = password_hash($senha, PASSWORD_BCRYPT);
    $stmt = $conn->prepare('UPDATE usuarios SET senha = ? WHERE id = ?');
    $stmt->bind_param('si', $senha, $row['usuario_id']);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        echo json_encode(['success' => false, 'erro' => 'Erro ao atualizar a senha.']);
        exit;
    }

    // Marca token como usado (invalida)
    $stmt = $conn->prepare('UPDATE reset_tokens SET usado = 1 WHERE id = ?');
    $stmt->bind_param('i', $row['id']);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['erro' => 'Método não permitido.']);
?>

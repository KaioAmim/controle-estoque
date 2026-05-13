<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
include 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: retorna usuários no formato {u, p, n} esperado pelo JS ──
if ($method === 'GET') {
  $res = $conn->query('SELECT id, usuario, senha, nome, email FROM usuarios ORDER BY id');
  $data = [];
  while ($r = $res->fetch_assoc()) {
    $data[] = [
      'id' => (int)$r['id'],
      'u'  => $r['usuario'],
      'p'  => $r['senha'],
      'n'  => $r['nome'],
      'e'  => $r['email'] ?? '',
    ];
  }
  echo json_encode($data);
  exit;
}

// ── POST: criar ou atualizar usuário ─────────────────────────────
if ($method === 'POST') {
  $d = json_decode(file_get_contents('php://input'), true);
  if (!$d) { echo json_encode(['success' => false, 'erro' => 'JSON inválido']); exit; }

  $u     = trim($d['u'] ?? '');
  $p     = $d['p'] ?? '';
  $n     = trim($d['n'] ?? '');
  $e     = trim($d['e'] ?? '') ?: null;
  $id    = isset($d['id']) && is_numeric($d['id']) ? (int)$d['id'] : null;

  if (!$u || !$p || !$n) {
    echo json_encode(['success' => false, 'erro' => 'Usuário, senha e nome são obrigatórios']);
    exit;
  }

  if ($id) {
    // UPDATE — só atualiza senha se foi informada
    $stmt = $conn->prepare('UPDATE usuarios SET senha=?, nome=?, email=? WHERE id=?');
    $stmt->bind_param('sssi', $p, $n, $e, $id);
  } else {
    // INSERT
    $stmt = $conn->prepare('INSERT INTO usuarios(usuario, senha, nome, email) VALUES(?,?,?,?)');
    $stmt->bind_param('ssss', $u, $p, $n, $e);
  }

  $ok = $stmt->execute();
  if (!$ok) {
    echo json_encode(['success' => false, 'erro' => $stmt->error]);
  } else {
    $newId = $id ?? $conn->insert_id;
    echo json_encode(['success' => true, 'id' => $newId]);
  }
  $stmt->close();
  exit;
}

// ── DELETE: remover usuário ───────────────────────────────────────
if ($method === 'DELETE') {
  $d  = json_decode(file_get_contents('php://input'), true);
  $id = isset($d['id']) ? (int)$d['id'] : 0;
  if (!$id) { echo json_encode(['success' => false, 'erro' => 'ID inválido']); exit; }
  $stmt = $conn->prepare('DELETE FROM usuarios WHERE id=? AND usuario != "admin"');
  $stmt->bind_param('i', $id);
  $ok = $stmt->execute();
  echo json_encode(['success' => $ok]);
  $stmt->close();
  exit;
}
?>

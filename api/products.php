<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
include 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: lista todos os produtos ─────────────────────────────
if ($method === 'GET') {
  $res = $conn->query('SELECT * FROM produtos ORDER BY nome');
  $data = [];
  while ($r = $res->fetch_assoc()) {
    // Normaliza para o formato esperado pelo JS
    $data[] = [
      'id'   => (int)$r['id'],
      'nome' => $r['nome'],
      'sku'  => $r['sku']         ?? '',
      'cat'  => $r['categoria']   ?? '',
      'ca'   => $r['numero_ca']   ?? '',
      'val'  => $r['validade_ca'] ?? null,
      'qty'  => (int)$r['quantidade'],
      'min'  => (int)$r['estoque_minimo'],
      'desc' => $r['descricao_ca'] ?? '',
    ];
  }
  echo json_encode($data);
  exit;
}

// ── POST: criar ou atualizar produto ─────────────────────────
if ($method === 'POST') {
  $d = json_decode(file_get_contents('php://input'), true);
  if (!$d) { echo json_encode(['success' => false, 'erro' => 'JSON inválido']); exit; }

  $nome = trim($d['nome'] ?? '');
  $sku  = trim($d['sku']  ?? '');
  $cat  = trim($d['cat']  ?? '');
  $ca   = trim($d['ca']   ?? '') ?: null;
  $val  = !empty($d['val']) ? $d['val'] : null;
  $qty  = isset($d['qty']) ? (int)$d['qty'] : 0;
  $min  = isset($d['min']) ? (int)$d['min'] : 5;
  $desc = trim($d['desc'] ?? '');
  $id   = isset($d['id']) && is_numeric($d['id']) ? (int)$d['id'] : null;

  if (!$nome) { echo json_encode(['success' => false, 'erro' => 'Nome obrigatório']); exit; }

  if ($id) {
    // UPDATE
    $stmt = $conn->prepare(
      'UPDATE produtos SET nome=?, sku=?, categoria=?, numero_ca=?, validade_ca=?, quantidade=?, estoque_minimo=?, descricao_ca=? WHERE id=?'
    );
    $stmt->bind_param('sssssiisi', $nome, $sku, $cat, $ca, $val, $qty, $min, $desc, $id);
    $ok = $stmt->execute();
    $newId = $id;
  } else {
    // INSERT — id é AUTO_INCREMENT
    $stmt = $conn->prepare(
      'INSERT INTO produtos(nome,sku,categoria,numero_ca,validade_ca,quantidade,estoque_minimo,descricao_ca) VALUES(?,?,?,?,?,?,?,?)'
    );
    $stmt->bind_param('sssssiis', $nome, $sku, $cat, $ca, $val, $qty, $min, $desc);
    $ok = $stmt->execute();
    $newId = $conn->insert_id;
  }

  if (!$ok) {
    echo json_encode(['success' => false, 'erro' => $stmt->error]);
  } else {
    echo json_encode(['success' => true, 'id' => $newId, 'action' => $id ? 'updated' : 'inserted']);
  }
  $stmt->close();
  exit;
}

// ── DELETE: remover produto ───────────────────────────────────
if ($method === 'DELETE') {
  $d  = json_decode(file_get_contents('php://input'), true);
  $id = isset($d['id']) ? (int)$d['id'] : 0;
  if (!$id) { echo json_encode(['success' => false, 'erro' => 'ID inválido']); exit; }
  $stmt = $conn->prepare('DELETE FROM produtos WHERE id=?');
  $stmt->bind_param('i', $id);
  $ok = $stmt->execute();
  echo json_encode(['success' => $ok]);
  $stmt->close();
  exit;
}
?>

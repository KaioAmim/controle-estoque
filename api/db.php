<?php
$host = 'localhost';
$db   = 'unistock';
$user = 'root';
$pass = '';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  http_response_code(500);
  die(json_encode(['erro' => 'Falha na conexão com o banco de dados']));
}
$conn->set_charset('utf8mb4');
?>

<?php
require __DIR__ . '/db.php';
require __DIR__ . '/util.php';
$input = json_decode(file_get_contents('php://input'), true);
$email = isset($input['email']) ? trim($input['email']) : '';
$senha = isset($input['senha']) ? (string)$input['senha'] : '';
if ($email === '' || $senha === '') json_out(['erro' => 'dados_invalidos'], 400);
$stmt = $mysqli->prepare('SELECT id, senha_hash FROM `usuarios` WHERE `email`=? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->bind_result($uid, $hash);
if (!$stmt->fetch()) json_out(['erro' => 'credenciais_invalidas'], 401);
$stmt->close();
$h = is_string($hash) ? $hash : (is_resource($hash) ? stream_get_contents($hash) : '');
if (!is_string($h) || strlen($h) < 20) json_out(['erro' => 'hash_invalido'], 500);
$ok = false;
if (password_verify($senha, $h)) $ok = true;
if (!$ok) json_out(['erro' => 'credenciais_invalidas'], 401);
$token = rand_token();
$expira = (new DateTime('+30 days'))->format('Y-m-d H:i:s');
$ins = $mysqli->prepare('INSERT INTO `sessoes` (`usuario_id`,`token`,`expira_em`) VALUES (?,?,?)');
$ins->bind_param('iss', $uid, $token, $expira);
if (!$ins->execute()) {
    error_log("Erro login insert sessao: " . $ins->error);
    json_out(['erro' => 'erro_interno', 'msg' => 'Falha ao criar sessão'], 500);
}
$ins->close();
json_out(['token' => $token, 'usuario' => ['nome' => $nome ?? 'Usuário', 'email' => $email]]);

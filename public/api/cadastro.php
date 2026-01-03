<?php
// Garante que erros fatais retornem JSON
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_CORE_ERROR || $error['type'] === E_COMPILE_ERROR)) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['erro' => 'erro_fatal_servidor', 'msg' => $error['message']]);
    }
});

require __DIR__ . '/db.php';
require __DIR__ . '/util.php';

// Log da requisição para debug
error_log("Cadastro iniciado: " . file_get_contents('php://input'));

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    json_out(['erro' => 'dados_invalidos'], 400);
}
$nome = isset($input['nome_completo']) ? trim($input['nome_completo']) : '';
$cpf = isset($input['cpf']) ? preg_replace('/\D/', '', $input['cpf']) : '';
$telefone = isset($input['telefone']) ? trim($input['telefone']) : '';
$email = isset($input['email']) ? trim($input['email']) : '';
$login = isset($input['login']) ? trim($input['login']) : '';
$senha = isset($input['senha']) ? (string)$input['senha'] : '';
$aceitar = !empty($input['aceitar_termos']);
if ($nome === '' || $email === '' || $login === '' || $senha === '' || !$aceitar) json_out(['erro' => 'dados_invalidos'], 400);
$e = $mysqli->prepare('SELECT id FROM `usuarios` WHERE `email`=? LIMIT 1');
$e->bind_param('s', $email);
$e->execute();
$e->store_result();
if ($e->num_rows) json_out(['erro' => 'email_ja_cadastrado'], 409);
$e->close();
$l = $mysqli->prepare('SELECT id FROM `usuarios` WHERE `login`=? LIMIT 1');
$l->bind_param('s', $login);
$l->execute();
$l->store_result();
if ($l->num_rows) json_out(['erro' => 'login_ja_cadastrado'], 409);
$l->close();
if ($cpf !== '') {
  $c = $mysqli->prepare('SELECT id FROM `usuarios` WHERE `cpf`=? LIMIT 1');
  $c->bind_param('s', $cpf);
  $c->execute();
  $c->store_result();
  if ($c->num_rows) json_out(['erro' => 'cpf_ja_cadastrado'], 409);
  $c->close();
} else {
  $cpf = null;
}
$hash = password_hash($senha, PASSWORD_BCRYPT);
$stmt = $mysqli->prepare('INSERT INTO `usuarios` (`nome`,`cpf`,`telefone`,`email`,`login`,`senha_hash`,`termos_aceitos`,`termos_aceitos_em`,`ativo`) VALUES (?,?,?,?,?,?,?,?,1)');
$ta = $aceitar ? 1 : 0;
$agora = (new DateTime())->format('Y-m-d H:i:s');
$stmt->bind_param('ssssssis', $nome, $cpf, $telefone, $email, $login, $hash, $ta, $agora);
if (!$stmt->execute()) {
  error_log('Erro cadastro: ' . $stmt->error);
  json_out(['erro' => 'falha_banco'], 500);
}
$id = $stmt->insert_id;

// Envia email de boas-vindas (simples)
$assunto = "Bem-vindo ao Flowhedge";
$mensagem = "Olá $nome,\n\nSeu cadastro foi realizado com sucesso!\nLogin: $login\n\nAcesse: https://flowhedge.com.br/login.html\n\nAtenciosamente,\nEquipe Flowhedge";
$headers = "From: nao-responda@flowhedge.com.br" . "\r\n" .
           "Reply-To: suporte@flowhedge.com.br" . "\r\n" .
           "X-Mailer: PHP/" . phpversion();

mail($email, $assunto, $mensagem, $headers);

json_out(['usuario_id' => $id], 201);

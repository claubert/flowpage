<?php
// public/api/recuperar_senha.php
require __DIR__ . '/db.php';
require __DIR__ . '/util.php';

header('Content-Type: application/json');

// Função auxiliar para gerar token
if (!function_exists('rand_token')) {
    function rand_token($len = 64) { return bin2hex(random_bytes($len/2)); }
}

// Receber dados
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'msg' => 'E-mail inválido.']);
    exit;
}

// Verificar se usuário existe
$stmt = $mysqli->prepare("SELECT id, nome FROM usuarios WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

// Se não existir, retornamos sucesso falso (ou verdadeiro para evitar enumeração, dependendo da política)
if (!$user) {
    // Simulando delay
    sleep(1);
    echo json_encode(['sucesso' => true, 'msg' => 'Se o e-mail existir, as instruções foram enviadas.']);
    exit;
}

// Gerar Token e Data de Expiração (1 hora)
$token = rand_token(64);
$expira = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

// Cria tabela se não existir (garantia)
$mysqli->query("CREATE TABLE IF NOT EXISTS `recuperacoes_senha` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expira_em` datetime NOT NULL,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Salvar no banco
$mysqli->begin_transaction();
try {
    // Remove solicitações antigas
    $del = $mysqli->prepare("DELETE FROM recuperacoes_senha WHERE usuario_id = ?");
    $del->bind_param('i', $user['id']);
    $del->execute();
    $del->close();

    // Insere nova
    $ins = $mysqli->prepare("INSERT INTO recuperacoes_senha (usuario_id, token, expira_em) VALUES (?, ?, ?)");
    $ins->bind_param('iss', $user['id'], $token, $expira);
    $ins->execute();
    $ins->close();

    $mysqli->commit();

    // SIMULAÇÃO DE ENVIO DE E-MAIL
    // Em produção, configure PHPMailer/SMTP aqui.
    // Como estamos em ambiente de desenvolvimento, salvamos o link em um log.
    
    $link = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]/api/reset_password.php?token=$token";
    $log = date('Y-m-d H:i:s') . " | Recuperar para: $email | Link: $link" . PHP_EOL;
    file_put_contents(__DIR__ . '/email_log.txt', $log, FILE_APPEND);

    echo json_encode([
        'sucesso' => true, 
        'msg' => 'Instruções enviadas para o e-mail.',
        'debug' => 'Verifique api/email_log.txt para o link de teste.'
    ]);

} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'msg' => 'Erro interno ao processar solicitação.']);
}
?>
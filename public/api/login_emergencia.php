<?php
// api/login_emergencia.php
require __DIR__ . '/db.php';
require __DIR__ . '/util.php';

// Configuração de segurança mínima: só roda se tiver um parâmetro secreto na URL
// Exemplo de uso: https://.../api/login_emergencia.php?key=desbloqueio_imediato_123
if (!isset($_GET['key']) || $_GET['key'] !== 'desbloqueio_imediato_123') {
    die("Acesso negado.");
}

$email_alvo = 'ccaldaslopes@gmail.com';

// 1. Buscar ID do usuário
$stmt = $mysqli->prepare("SELECT id, nome, email FROM usuarios WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email_alvo);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user) {
    die("Usuário $email_alvo não encontrado no banco.");
}

// 2. Gerar Token Válido (igual ao login.php)
$token = rand_token();
$expira = (new DateTime('+2 hours'))->format('Y-m-d H:i:s'); // Token de 2 horas

$ins = $mysqli->prepare('INSERT INTO `sessoes` (`usuario_id`,`token`,`expira_em`) VALUES (?,?,?)');
$ins->bind_param('iss', $user['id'], $token, $expira);

if ($ins->execute()) {
    // 3. Auto-Login (Salva no LocalStorage e Redireciona)
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <title>Login de Emergência</title>
        <script>
            // Salva o token como se o login tivesse ocorrido
            localStorage.setItem('token', '<?php echo $token; ?>');
            localStorage.setItem('usuario', JSON.stringify({
                nome: '<?php echo $user['nome']; ?>',
                email: '<?php echo $user['email']; ?>'
            }));
            
            // Redireciona para o painel
            alert('Acesso de emergência liberado! Redirecionando para o painel...');
            window.location.href = '/painel.html';
        </script>
    </head>
    <body>
        <h1>Autenticando...</h1>
        <p>Aguarde o redirecionamento.</p>
    </body>
    </html>
    <?php
} else {
    die("Erro ao criar sessão: " . $mysqli->error);
}
$ins->close();
?>
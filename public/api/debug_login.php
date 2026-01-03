<?php
// api/debug_login.php

// 1. Configurações de exibição de erro para debug
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

echo "<h1>Diagnóstico de Login</h1>";
echo "<hr>";

// 2. Inclui conexão com banco (mesma lógica do sistema)
if (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
} else {
    die("<h3 style='color:red'>Erro Crítico: arquivo db.php não encontrado!</h3>");
}

// Verifica conexão
if (!isset($mysqli) || $mysqli->connect_errno) {
    die("<h3 style='color:red'>Falha na conexão com BD: " . ($mysqli->connect_error ?? 'Desconhecido') . "</h3>");
}
echo "<p style='color:green'><strong>✓ Conexão com banco de dados estabelecida.</strong></p>";

// 3. Definição dos alvos
$email_alvo = 'ccaldaslopes@gmail.com';
$senha_teste = 'Controle@22';

echo "<h2>1. Buscando Usuário</h2>";
echo "Procurando por: <strong>$email_alvo</strong><br>";

$sql = "SELECT id, nome, email, senha_hash, ativo, login FROM usuarios WHERE email = ?";
$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    die("<p style='color:red'>Erro na preparação da query: " . $mysqli->error . "</p>");
}

$stmt->bind_param('s', $email_alvo);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if (!$user) {
    echo "<h3 style='color:red'>❌ Usuário NÃO encontrado.</h3>";
    
    // Ajuda: listar parecidos ou últimos cadastrados
    echo "<p>Listando últimos 5 usuários cadastrados para conferência:</p>";
    $lista = $mysqli->query("SELECT id, email, login FROM usuarios ORDER BY id DESC LIMIT 5");
    echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>Email</th><th>Login</th></tr>";
    while($row = $lista->fetch_assoc()) {
        echo "<tr><td>{$row['id']}</td><td>{$row['email']}</td><td>{$row['login']}</td></tr>";
    }
    echo "</table>";
    exit;
}

echo "<h3 style='color:green'>✓ Usuário encontrado!</h3>";
echo "<ul>";
echo "<li><strong>ID:</strong> {$user['id']}</li>";
echo "<li><strong>Nome:</strong> {$user['nome']}</li>";
echo "<li><strong>Login:</strong> {$user['login']}</li>";
echo "<li><strong>Ativo:</strong> " . ($user['ativo'] ? 'Sim' : 'Não') . "</li>";
echo "</ul>";

// 4. Verificação de Senha
echo "<h2>2. Verificando Senha</h2>";
echo "Senha testada: <code>$senha_teste</code><br>";
$hash_banco = $user['senha_hash'];

// Exibe hash
$hash_display = substr($hash_banco, 0, 10) . '...' . substr($hash_banco, -10);
echo "Hash no banco: <code>$hash_display</code> (Tamanho: " . strlen($hash_banco) . ")<br><br>";

// Teste Real
$check = password_verify($senha_teste, $hash_banco);

if ($check) {
    echo "<div style='background-color: #dff0d8; padding: 15px; border: 1px solid #d6e9c6; color: #3c763d;'>";
    echo "<h3>✅ SUCESSO!</h3>";
    echo "A senha <strong>$senha_teste</strong> confere com o hash armazenado.";
    echo "</div>";
} else {
    echo "<div style='background-color: #f2dede; padding: 15px; border: 1px solid #ebccd1; color: #a94442;'>";
    echo "<h3>❌ FALHA!</h3>";
    echo "A senha <strong>$senha_teste</strong> NÃO confere com o hash armazenado.";
    echo "</div>";

    echo "<h4>Diagnóstico Avançado:</h4>";
    
    // 1. Verifica se está usando algoritmo antigo (MD5)
    if (md5($senha_teste) === $hash_banco) {
        echo "<p>⚠️ <strong>Alerta:</strong> A senha no banco é um MD5 simples. O sistema espera BCRYPT. É necessário resetar a senha.</p>";
    } 
    // 2. Verifica espaços em branco
    elseif (trim($hash_banco) !== $hash_banco) {
        echo "<p>⚠️ <strong>Alerta:</strong> O hash no banco contém espaços em branco extras.</p>";
    }
    else {
        echo "<p>O hash é válido, mas a senha é diferente.</p>";
    }

    echo "<hr>";
    echo "<strong>Novo Hash Gerado (para referência):</strong><br>";
    echo "<code>" . password_hash($senha_teste, PASSWORD_BCRYPT) . "</code>";
}
?>
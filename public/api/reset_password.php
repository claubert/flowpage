<?php
// api/reset_password.php
require __DIR__ . '/db.php';
require __DIR__ . '/util.php';

// Configuração
$email_alvo = 'ccaldaslopes@gmail.com';
$nova_senha = 'Controle@22'; // Mantendo a senha que o usuário deseja

// Cabeçalho para visualização no navegador
header('Content-Type: text/html; charset=utf-8');
echo "<h2>Redefinição de Senha Manual</h2>";

// 1. Verificar conexão
if (!isset($mysqli)) {
    die("<p style='color:red'>Erro: Conexão com banco de dados não estabelecida (verifique db.php).</p>");
}

// 2. Verificar se usuário existe
$stmt = $mysqli->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
if (!$stmt) {
    die("<p style='color:red'>Erro SQL: " . $mysqli->error . "</p>");
}

$stmt->bind_param('s', $email_alvo);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    die("<p style='color:red'>Erro: Usuário <strong>$email_alvo</strong> não encontrado no banco de dados.</p>");
}
$stmt->close();

// 3. Gerar novo hash (BCRYPT forte)
$hash = password_hash($nova_senha, PASSWORD_BCRYPT);

// 4. Atualizar no banco
$upd = $mysqli->prepare("UPDATE usuarios SET senha_hash = ?, ativo = 1 WHERE email = ?");
$upd->bind_param('ss', $hash, $email_alvo);

if ($upd->execute()) {
    echo "<div style='background:#e8f5e9; padding:15px; border-radius:5px; border:1px solid #c8e6c9; color:#2e7d32'>";
    echo "<h3>✅ Senha Alterada com Sucesso!</h3>";
    echo "<ul>";
    echo "<li><strong>Usuário:</strong> $email_alvo</li>";
    echo "<li><strong>Nova Senha:</strong> $nova_senha</li>";
    echo "<li><strong>Hash Gerado:</strong> " . substr($hash, 0, 20) . "...</li>";
    echo "</ul>";
    echo "<p>Tente fazer login novamente agora.</p>";
    echo "</div>";
} else {
    echo "<p style='color:red'>Erro ao atualizar registro: " . $mysqli->error . "</p>";
}

$upd->close();
$mysqli->close();
?>
<?php
require __DIR__ . '/db.php';
require __DIR__ . '/util.php';

echo "<h1>Reparação Automática do Banco de Dados</h1>";

// 1. Garantir Schema da Tabela Sessoes
echo "<h2>1. Verificando Tabela 'sessoes'</h2>";
$mysqli->query("ALTER TABLE `sessoes` MODIFY `token` VARCHAR(255) NOT NULL");
$mysqli->query("ALTER TABLE `sessoes` MODIFY `usuario_id` INT(11) NOT NULL");
echo "Coluna 'token' expandida para VARCHAR(255).<br>";

// 2. Garantir Schema da Tabela Assinaturas
echo "<h2>2. Verificando Tabela 'assinaturas'</h2>";
$cols = $mysqli->query("SHOW COLUMNS FROM assinaturas");
$found = [];
while($c = $cols->fetch_assoc()) $found[] = $c['Field'];

if (!in_array('plano_id', $found)) {
    $mysqli->query("ALTER TABLE `assinaturas` ADD COLUMN `plano_id` INT(11) NOT NULL AFTER `usuario_id`");
    echo "Coluna 'plano_id' adicionada.<br>";
}
if (!in_array('status', $found)) {
    $mysqli->query("ALTER TABLE `assinaturas` ADD COLUMN `status` VARCHAR(50) DEFAULT 'pendente'");
    echo "Coluna 'status' adicionada.<br>";
}

// 3. Garantir Schema da Tabela Pagamentos
echo "<h2>3. Verificando Tabela 'pagamentos'</h2>";
$cols = $mysqli->query("SHOW COLUMNS FROM pagamentos");
$found = [];
while($c = $cols->fetch_assoc()) $found[] = $c['Field'];

if (!in_array('valor_centavos', $found)) {
    $mysqli->query("ALTER TABLE `pagamentos` ADD COLUMN `valor_centavos` INT(11) NOT NULL");
    echo "Coluna 'valor_centavos' adicionada.<br>";
}
if (!in_array('metodo', $found)) {
    $mysqli->query("ALTER TABLE `pagamentos` ADD COLUMN `metodo` VARCHAR(50) DEFAULT NULL");
    echo "Coluna 'metodo' adicionada.<br>";
}

// 4. Teste de Inserção e Leitura de Sessão
echo "<h2>4. Teste de Sessão</h2>";
$uid = 1; // Supõe admin/primeiro user
$token = bin2hex(random_bytes(32));
$expira = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

$stmt = $mysqli->prepare("INSERT INTO sessoes (usuario_id, token, expira_em) VALUES (?, ?, ?)");
if ($stmt) {
    $stmt->bind_param('iss', $uid, $token, $expira);
    if ($stmt->execute()) {
        echo "Inserção de sessão de teste: <span style='color:green'>SUCESSO</span><br>";
        
        // Tenta ler de volta
        $check = $mysqli->prepare("SELECT * FROM sessoes WHERE token = ?");
        $check->bind_param('s', $token);
        $check->execute();
        $res = $check->get_result();
        if ($row = $res->fetch_assoc()) {
            echo "Leitura de sessão de teste: <span style='color:green'>SUCESSO</span> (Token: " . substr($token, 0, 10) . "...)<br>";
            
            // Verifica Horário
            $dbTime = $mysqli->query("SELECT NOW()")->fetch_row()[0];
            echo "Horário DB: $dbTime | Expira em: $expira<br>";
            if ($row['expira_em'] > $dbTime) {
                echo "Validação de tempo: <span style='color:green'>OK</span><br>";
            } else {
                echo "Validação de tempo: <span style='color:red'>ERRO (DB Time > Expira)</span><br>";
            }
        } else {
            echo "Leitura de sessão de teste: <span style='color:red'>FALHA (Não encontrado)</span><br>";
        }
        
        // Limpa teste
        $mysqli->query("DELETE FROM sessoes WHERE token = '$token'");
    } else {
        echo "Inserção de sessão de teste: <span style='color:red'>FALHA (" . $stmt->error . ")</span><br>";
    }
} else {
    echo "Erro prepare: " . $mysqli->error;
}

echo "<h3>Diagnóstico Concluído. Tente fazer login novamente.</h3>";

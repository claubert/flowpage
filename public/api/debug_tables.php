<?php
require __DIR__ . '/db.php';
require __DIR__ . '/util.php';

echo "<h1>Diagnóstico de Banco de Dados</h1>";

// 1. Verificar Tabelas Existentes
echo "<h2>Tabelas</h2>";
$res = $mysqli->query("SHOW TABLES");
if ($res) {
    while ($row = $res->fetch_array()) {
        echo "Tabela encontrada: " . $row[0] . "<br>";
        
        // Mostrar colunas
        $cols = $mysqli->query("SHOW COLUMNS FROM " . $row[0]);
        echo "<ul>";
        while ($col = $cols->fetch_assoc()) {
            echo "<li>" . $col['Field'] . " (" . $col['Type'] . ")</li>";
        }
        echo "</ul>";
    }
} else {
    echo "Erro ao listar tabelas: " . $mysqli->error;
}

// 2. Verificar Sessão (se token for passado na URL)
if (isset($_GET['token'])) {
    echo "<h2>Verificação de Token</h2>";
    $token = $_GET['token'];
    echo "Token recebido: " . htmlspecialchars($token) . "<br>";
    
    $stmt = $mysqli->prepare("SELECT * FROM sessoes WHERE token = ?");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        echo "Sessão encontrada!<br>";
        echo "Usuario ID: " . $row['usuario_id'] . "<br>";
        echo "Expira em: " . $row['expira_em'] . "<br>";
        
        // Verificar NOW() do banco
        $time = $mysqli->query("SELECT NOW() as agora")->fetch_assoc()['agora'];
        echo "Horário do Banco (NOW): " . $time . "<br>";
        
        if ($row['expira_em'] > $time) {
            echo "<span style='color:green'>Sessão VÁLIDA</span>";
        } else {
            echo "<span style='color:red'>Sessão EXPIRADA</span>";
        }
    } else {
        echo "<span style='color:red'>Token não encontrado no banco.</span>";
    }
}

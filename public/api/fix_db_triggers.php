<?php
// public/api/fix_db_triggers.php
require __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO E CORREÇÃO DE TRIGGERS ===\n\n";

if ($mysqli->connect_errno) {
    die("ERRO: Falha na conexão com banco de dados: " . $mysqli->connect_error);
}

// 1. Identificar triggers na tabela 'sessoes'
$tabela = 'sessoes';
echo "Verificando triggers na tabela '$tabela'...\n";

$sql = "SELECT TRIGGER_NAME FROM INFORMATION_SCHEMA.TRIGGERS 
        WHERE EVENT_OBJECT_TABLE = ? AND TRIGGER_SCHEMA = DATABASE()";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $tabela);
$stmt->execute();
$res = $stmt->get_result();

$triggers = [];
while ($row = $res->fetch_assoc()) {
    $triggers[] = $row['TRIGGER_NAME'];
}
$stmt->close();

if (empty($triggers)) {
    echo "Nenhuma trigger encontrada em '$tabela'. O banco parece limpo.\n";
} else {
    echo "Encontradas: " . implode(", ", $triggers) . "\n";
    echo "Removendo triggers para corrigir o erro de recursão...\n";

    foreach ($triggers as $nome) {
        $drop = "DROP TRIGGER IF EXISTS `$nome`";
        if ($mysqli->query($drop)) {
            echo " [OK] Trigger '$nome' removida.\n";
        } else {
            echo " [ERRO] Falha ao remover '$nome': " . $mysqli->error . "\n";
        }
    }
    echo "\nCorreção concluída. Tente fazer login novamente.\n";
}
?>
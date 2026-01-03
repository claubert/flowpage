<?php
// public/api/update_prices.php
require __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');
echo "=== ATUALIZAÇÃO DE PREÇOS NO BANCO DE DADOS ===\n\n";

if ($mysqli->connect_errno) {
    die("ERRO: Falha na conexão com banco de dados: " . $mysqli->connect_error);
}

// Novos Preços (em centavos)
// R$ 297,00 -> 29700
// R$ 1.677,00 -> 167700
// R$ 2.964,00 -> 296400

$updates = [
    ['mensal', 29700],
    ['semestral', 167700],
    ['anual', 296400]
];

foreach ($updates as $plano) {
    $codigo = $plano[0];
    $preco = $plano[1];

    $stmt = $mysqli->prepare("UPDATE planos SET preco_centavos = ? WHERE codigo = ?");
    $stmt->bind_param('is', $preco, $codigo);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo " [OK] Plano '$codigo' atualizado para R$ " . number_format($preco/100, 2, ',', '.') . "\n";
        } else {
            echo " [INFO] Plano '$codigo' já estava com o preço correto ou não encontrado.\n";
        }
    } else {
        echo " [ERRO] Falha ao atualizar '$codigo': " . $stmt->error . "\n";
    }
    $stmt->close();
}

echo "\nProcesso concluído.";
?>
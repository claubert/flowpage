<?php
ob_start();
require __DIR__ . '/db.php';
require __DIR__ . '/util.php';

$token = get_bearer_token();
if (!$token) json_out(['erro' => 'nao_autorizado'], 401);

// Valida sessão e pega usuário
$stmt = $mysqli->prepare('SELECT usuario_id FROM sessoes WHERE token = ? AND expira_em > NOW() LIMIT 1');
$stmt->bind_param('s', $token);
$stmt->execute();
$sessao = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sessao) json_out(['erro' => 'sessao_invalida'], 401);
$usuario_id = $sessao['usuario_id'];

// Busca assinatura ativa ou última
$stmt = $mysqli->prepare("
    SELECT a.*, p.nome as plano_nome, p.preco_centavos 
    FROM assinaturas a
    JOIN planos p ON a.plano_id = p.id
    WHERE a.usuario_id = ? 
    ORDER BY a.criado_em DESC LIMIT 1
");
$stmt->bind_param('i', $usuario_id);
$stmt->execute();
$assinatura = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Busca histórico de pagamentos
$pagamentos = [];
try {
    $stmt = $mysqli->prepare("
        SELECT p.*, a.plano_id 
        FROM pagamentos p
        JOIN assinaturas a ON p.assinatura_id = a.id
        WHERE a.usuario_id = ?
        ORDER BY p.criado_em DESC LIMIT 50
    ");
    if ($stmt) {
        $stmt->bind_param('i', $usuario_id);
        $stmt->execute();
        $pagamentos_res = $stmt->get_result();
        while ($row = $pagamentos_res->fetch_assoc()) {
            $pagamentos[] = $row;
        }
        $stmt->close();
    }
} catch (Exception $e) {
    // Ignora erro se tabela não existir e retorna array vazio
    error_log('Erro ao buscar pagamentos: ' . $e->getMessage());
}

json_out([
    'assinatura' => $assinatura,
    'pagamentos' => $pagamentos
]);
<?php
ob_start(); // Inicia buffer de saída para capturar erros HTML indesejados

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_CORE_ERROR)) {
        if (ob_get_length()) ob_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['erro' => 'erro_fatal_servidor', 'msg' => $error['message']]);
    } else {
        ob_end_flush();
    }
});

require __DIR__ . '/db.php';
require __DIR__ . '/util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['erro' => 'metodo_nao_permitido', 'msg' => 'Apenas POST é suportado'], 405);
}

$token = get_bearer_token();
if (!$token) {
    json_out(['erro' => 'token_nao_fornecido', 'msg' => 'Token de autenticação é obrigatório'], 401);
}

$stmt = $mysqli->prepare('SELECT usuario_id FROM sessoes WHERE token = ? AND expira_em > NOW() LIMIT 1');
if (!$stmt) json_out(['erro' => 'erro_interno'], 500);
$stmt->bind_param('s', $token);
$stmt->execute();
$sessao = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sessao) {
    json_out(['erro' => 'sessao_invalida', 'msg' => 'Sessão inválida. Faça login novamente.'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    json_out(['erro' => 'json_invalido'], 400);
}

$codigo_plano = $input['codigo_plano'] ?? '';
if (empty($codigo_plano)) {
    json_out(['erro' => 'dados_incompletos', 'msg' => 'Código do plano obrigatório'], 400);
}

// Busca plano
$stmt = $mysqli->prepare('SELECT id FROM planos WHERE codigo = ? AND ativo = 1 LIMIT 1');
$stmt->bind_param('s', $codigo_plano);
$stmt->execute();
$plano = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$plano) {
    json_out(['erro' => 'plano_invalido'], 400);
}

// Cria assinatura pendente
$stmt = $mysqli->prepare("INSERT INTO assinaturas (usuario_id, plano_id, status, inicio_em, criado_em) VALUES (?, ?, 'pendente', NOW(), NOW())");
$stmt->bind_param('ii', $sessao['usuario_id'], $plano['id']);

if ($stmt->execute()) {
    json_out(['assinatura_id' => $stmt->insert_id], 201);
} else {
    json_out(['erro' => 'erro_banco', 'msg' => 'Erro ao criar assinatura'], 500);
}

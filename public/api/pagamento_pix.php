<?php
ob_start(); // Inicia buffer para capturar qualquer output indesejado

// Função de shutdown para capturar erros fatais (ex: sintaxe, falta de arquivo)
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_CORE_ERROR)) {
        // Se houve output anterior (HTML de erro), limpa
        if (ob_get_length()) ob_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['erro' => 'erro_fatal_servidor', 'msg' => $error['message'], 'arquivo' => basename($error['file']), 'linha' => $error['line']]);
        exit;
    }
});

require __DIR__ . '/db.php';
require __DIR__ . '/util.php';

// Limpa buffer anterior se houver (ex: warnings do include)
if (ob_get_length()) ob_clean();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['erro' => 'metodo_nao_permitido'], 405);
}

$token = get_bearer_token();
if (!$token) json_out(['erro' => 'nao_autorizado'], 401);

// Valida sessão com verificação de erro no prepare
$stmt = $mysqli->prepare('SELECT usuario_id FROM sessoes WHERE token = ? AND expira_em > NOW() LIMIT 1');

if (!$stmt) {
    error_log("Erro prepare sessoes: " . $mysqli->error);
    json_out(['erro' => 'erro_interno', 'msg' => 'Erro interno ao validar sessão.'], 500);
}

$stmt->bind_param('s', $token);
$stmt->execute();
$sessao = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sessao) json_out(['erro' => 'sessao_invalida'], 401);

// Parse seguro do JSON de entrada
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    json_out(['erro' => 'json_invalido'], 400);
}

$assinatura_id = $input['assinatura_id'] ?? 0;

if (!$assinatura_id) json_out(['erro' => 'id_invalido'], 400);

// Valida assinatura
$stmt = $mysqli->prepare('SELECT a.id, p.preco_centavos FROM assinaturas a JOIN planos p ON a.plano_id = p.id WHERE a.id = ? AND a.usuario_id = ?');

if (!$stmt) {
    error_log("Erro prepare assinaturas: " . $mysqli->error);
    json_out(['erro' => 'erro_interno', 'msg' => 'Erro ao buscar assinatura.'], 500);
}

$stmt->bind_param('ii', $assinatura_id, $sessao['usuario_id']);
$stmt->execute();
$assinatura = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$assinatura) {
    json_out(['erro' => 'assinatura_nao_encontrada'], 404);
}

// Configurações do PIX (Edite aqui)
require_once __DIR__ . '/PixPayload.php';
$chave_pix = 'ccaldaslopes@gmail.com'; // Sua Chave PIX
$beneficiario = 'CLAUBERT LOPES'; // Nome do Beneficiário
$cidade = 'BRASILIA'; // Cidade do Beneficiário

$valor = $assinatura['preco_centavos'] / 100;
$txid = 'FLOW' . $assinatura_id; // Identificador único (até 25 chars)

// Gera Payload
$obPayload = (new PixPayload())
    ->setPixKey($chave_pix)
    ->setDescription('Assinatura Flowhedge #' . $assinatura_id)
    ->setMerchantName($beneficiario)
    ->setMerchantCity($cidade)
    ->setAmount($valor)
    ->setTxid($txid);

$qr_code_payload = $obPayload->getPayload();
$id_externo = $txid;

// Retorna dados
json_out([
    'id_externo' => $id_externo,
    'qr_code' => $qr_code_payload,
    'copia_cola' => $qr_code_payload
]);
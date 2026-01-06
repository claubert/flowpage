<?php
require __DIR__ . '/db.php';
require __DIR__ . '/config.php';

// Log para debug
function log_mp($msg) {
    file_put_contents('mp_webhook.log', date('Y-m-d H:i:s') . " - $msg\n", FILE_APPEND);
}

// 1. Receber Notificação
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    log_mp("Recebido input vazio ou inválido.");
    http_response_code(400);
    exit;
}

// log_mp("Recebido: " . print_r($data, true));

// O Mercado Pago envia o ID do pagamento no campo 'data.id' quando type é 'payment'
// Ou apenas 'id' dependendo da versão da API
$payment_id = $data['data']['id'] ?? $data['id'] ?? null;
$type = $data['type'] ?? $data['topic'] ?? null;

if ($type !== 'payment' || !$payment_id) {
    log_mp("Ignorado: Tipo $type ou ID ausente.");
    http_response_code(200); // Retorna 200 para o MP não ficar reenviando coisas inúteis
    exit;
}

// 2. Consultar Status no Mercado Pago
$url = "https://api.mercadopago.com/v1/payments/$payment_id";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . MP_ACCESS_TOKEN
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    log_mp("Erro ao consultar MP: $http_code");
    http_response_code(500);
    exit;
}

$payment_data = json_decode($response, true);
$status = $payment_data['status']; // approved, pending, rejected
$external_ref = $payment_data['external_reference']; // ID da assinatura ou user_id

log_mp("Pagamento $payment_id está $status. Ref: $external_ref");

// 3. Atualizar Banco de Dados
if ($status === 'approved') {
    // Buscar pagamento pelo ID externo ou criar novo
    // Assumindo que external_reference é o ID da assinatura
    $assinatura_id = intval($external_ref);

    if ($assinatura_id > 0) {
        // Atualiza pagamento
        $stmt = $mysqli->prepare("
            INSERT INTO pagamentos (assinatura_id, valor_centavos, metodo, status, id_externo, pago_em)
            VALUES (?, ?, 'pix', 'pago', ?, NOW())
            ON DUPLICATE KEY UPDATE status = 'pago', pago_em = NOW()
        ");
        $valor = intval($payment_data['transaction_amount'] * 100);
        $stmt->bind_param("iis", $assinatura_id, $valor, $payment_id);
        $stmt->execute();

        // Ativa assinatura
        $stmt = $mysqli->prepare("UPDATE assinaturas SET status = 'ativa', atualizado_em = NOW() WHERE id = ?");
        $stmt->bind_param("i", $assinatura_id);
        $stmt->execute();

        log_mp("Assinatura $assinatura_id ATIVADA com sucesso!");

        // 4. Notificar N8N
        if (defined('N8N_WEBHOOK_URL') && N8N_WEBHOOK_URL) {
            $n8n_data = [
                'event' => 'payment_approved',
                'assinatura_id' => $assinatura_id,
                'payment_id' => $payment_id,
                'valor' => $valor,
                'status' => 'approved',
                'timestamp' => date('Y-m-d H:i:s')
            ];

            $ch_n8n = curl_init(N8N_WEBHOOK_URL);
            curl_setopt($ch_n8n, CURLOPT_POST, 1);
            curl_setopt($ch_n8n, CURLOPT_POSTFIELDS, json_encode($n8n_data));
            curl_setopt($ch_n8n, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch_n8n, CURLOPT_RETURNTRANSFER, true);
            $resp_n8n = curl_exec($ch_n8n);
            curl_close($ch_n8n);
            
            log_mp("N8N notificado. Resposta: " . substr($resp_n8n, 0, 100));
        }

    } else {
        log_mp("ERRO: External Reference inválido ($external_ref)");
    }
}

http_response_code(200);
echo json_encode(['status' => 'ok']);

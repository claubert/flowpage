<?php
ob_start();
// Headers anti-cache cruciais para polling
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require __DIR__ . '/db.php';
require __DIR__ . '/util.php';

$token = get_bearer_token();
// Opcional: validar token se quiser proteger o status

$assinatura_id = $_GET['assinatura_id'] ?? 0;

if ($assinatura_id) {
    $stmt = $mysqli->prepare('SELECT status FROM assinaturas WHERE id = ?');
    $stmt->bind_param('i', $assinatura_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    
    $status = 'pendente';
    if ($res) {
        // Mapeia status do banco para status do frontend
        $status = ($res['status'] === 'ativo') ? 'pago' : $res['status'];
    }
    
    json_out(['pagamento' => ['status' => $status]]);
}

json_out(['pagamento' => null]);
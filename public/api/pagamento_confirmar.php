<?php
ob_start();
require __DIR__ . '/db.php';
require __DIR__ . '/util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['erro' => 'metodo_nao_permitido'], 405);

$token = get_bearer_token();
if (!$token) json_out(['erro' => 'nao_autorizado'], 401);

// Busca usuário
$stmt = $mysqli->prepare('SELECT usuario_id FROM sessoes WHERE token = ? AND expira_em > NOW() LIMIT 1');
$stmt->bind_param('s', $token);
$stmt->execute();
$sessao = $stmt->get_result()->fetch_assoc();
if (!$sessao) json_out(['erro' => 'sessao_invalida'], 401);

// Lê o JSON recebido
$input = json_decode(file_get_contents('php://input'), true);
$assinatura_id = $input['assinatura_id'] ?? null;

if ($assinatura_id) {
    // Ativa assinatura específica
    $stmt = $mysqli->prepare("UPDATE assinaturas SET status = 'ativo', atualizado_em = NOW() WHERE id = ? AND usuario_id = ? AND status = 'pendente'");
    $stmt->bind_param('ii', $assinatura_id, $sessao['usuario_id']);
} else {
    // Fallback
    $stmt = $mysqli->prepare("UPDATE assinaturas SET status = 'ativo', atualizado_em = NOW() WHERE usuario_id = ? AND status = 'pendente' ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('i', $sessao['usuario_id']);
}

$stmt->execute();

if ($stmt->affected_rows > 0) {
    
    // --- INÍCIO ENVIO DE E-MAIL ---
    try {
        // Busca dados para envio
        $stmt_user = $mysqli->prepare("SELECT nome, email FROM usuarios WHERE id = ?");
        $stmt_user->bind_param('i', $sessao['usuario_id']);
        $stmt_user->execute();
        $user_data = $stmt_user->get_result()->fetch_assoc();
        
        if ($user_data && !empty($user_data['email'])) {
            $destinatario = $user_data['email'];
            $nome = $user_data['nome'] ?: 'Cliente';
            $assunto = "Pagamento Confirmado - Flowhedge";
            
            // Tenta localizar o template (ajuste o caminho se necessário)
            $template_path = __DIR__ . '/../../flowhedge/src/templates/email_base.html';
            
            $conteudo_email = "<h2>Pagamento Confirmado!</h2>
            <p>Olá <strong>{{nome}}</strong>,</p>
            <p>Seu pagamento foi recebido com sucesso e sua assinatura já está ativa.</p>
            <p>Aproveite todos os recursos do Flowhedge.</p>
            <br>
            <a href='https://flowhedge.com/painel.html' class='btn'>Acessar Painel</a>";

            $html_final = "";

            if (file_exists($template_path)) {
                $base_html = file_get_contents($template_path);
                $html_final = str_replace('{{conteudo}}', $conteudo_email, $base_html);
                $html_final = str_replace('{{ano}}', date('Y'), $html_final);
            } else {
                // Fallback template
                $html_final = "<html><body style='font-family:sans-serif;padding:20px;'>$conteudo_email</body></html>";
            }
            
            $html_final = str_replace('{{nome}}', $nome, $html_final);
            
            // Headers
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= 'From: no-reply@flowhedge.com' . "\r\n";
            
            // Tenta enviar
            $enviado = @mail($destinatario, $assunto, $html_final, $headers);
            
            // Log de auditoria (funciona mesmo sem SMTP configurado)
            $log_line = date('Y-m-d H:i:s') . " | CONFIRMAÇÃO PAGAMENTO | Para: $destinatario | Enviado: " . ($enviado ? 'SIM' : 'NAO (simulado)') . PHP_EOL;
            file_put_contents(__DIR__ . '/email_log.txt', $log_line, FILE_APPEND);
        }
    } catch (Exception $e) {
        // Log de erro silencioso para não quebrar a resposta JSON
        file_put_contents(__DIR__ . '/email_error_log.txt', date('Y-m-d H:i:s') . ' ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    }
    // --- FIM ENVIO DE E-MAIL ---

    json_out(['status' => 'sucesso', 'msg' => 'Pagamento confirmado com sucesso']);
} else {
    json_out(['status' => 'erro', 'msg' => 'Assinatura não encontrada ou já ativada'], 404);
}
?>
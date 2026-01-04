<?php
require __DIR__ . '/db.php';
require __DIR__ . '/util.php';

// Configuração de segurança
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// 1. Autenticação Básica
// $user_id = auth_check(); // Removendo auth_check global que não lê Bearer

$token = get_bearer_token();
if (!$token) json_out(['erro' => 'token_ausente'], 401);

$stmt = $mysqli->prepare('SELECT u.id, u.email FROM sessoes s JOIN usuarios u ON u.id = s.usuario_id WHERE s.token = ? AND s.expira_em > NOW() LIMIT 1');
$stmt->bind_param('s', $token);
$stmt->execute();
$res = $stmt->get_result();
$user_data = $res->fetch_assoc();
$user_id = $user_data['id'] ?? 0;

// 2. Verificação de Super Admin (Hardcoded + Banco de Dados)
// $stmt = $mysqli->prepare("SELECT email, nome FROM usuarios WHERE id = ? LIMIT 1");
// $stmt->bind_param("i", $user_id);
// $stmt->execute();
// $res = $stmt->get_result();
// $user_data = $res->fetch_assoc();

// Lista de e-mails permitidos (Whitelist)
$admin_whitelist = ['claubert.lopez@gmail.com'];

if (!$user_data || !in_array($user_data['email'], $admin_whitelist)) {
    // Log de tentativa não autorizada
    $ip = $_SERVER['REMOTE_ADDR'];
    $log = $mysqli->prepare("INSERT INTO logs_acesso (usuario_id, acao, sucesso, ip) VALUES (?, 'admin_access_denied', 0, ?)");
    $log->bind_param("is", $user_id, $ip);
    $log->execute();
    
    json_out(['erro' => 'acesso_negado', 'msg' => 'Este usuário não possui privilégios administrativos.'], 403);
}

// 3. Log de Acesso Autorizado
$ip = $_SERVER['REMOTE_ADDR'];
$log = $mysqli->prepare("INSERT INTO logs_acesso (usuario_id, acao, sucesso, ip) VALUES (?, 'admin_dashboard_view', 1, ?)");
$log->bind_param("is", $user_id, $ip);
$log->execute();

// 4. Coleta de Métricas

// A. Contagem de Usuários
$stats = [
    'usuarios_total' => 0,
    'usuarios_ativos' => 0,
    'assinaturas_ativas' => 0,
    'assinaturas_atrasadas' => 0,
    'receita_total' => 0,
    'planos_distribuicao' => [],
    'ultimos_usuarios' => [],
    'crescimento_mensal' => []
];

// Usuários Totais e Ativos
$q = $mysqli->query("SELECT COUNT(*) as total, SUM(CASE WHEN ativo=1 THEN 1 ELSE 0 END) as ativos FROM usuarios");
$r = $q->fetch_assoc();
$stats['usuarios_total'] = $r['total'];
$stats['usuarios_ativos'] = $r['ativos'];

// Assinaturas
$q = $mysqli->query("SELECT 
    SUM(CASE WHEN status='ativa' THEN 1 ELSE 0 END) as ativas,
    SUM(CASE WHEN status='em_atraso' THEN 1 ELSE 0 END) as atrasadas
    FROM assinaturas");
$r = $q->fetch_assoc();
$stats['assinaturas_ativas'] = $r['ativas'];
$stats['assinaturas_atrasadas'] = $r['atrasadas'];

// Receita Total (Estimada)
$q = $mysqli->query("SELECT SUM(valor_centavos) as total FROM pagamentos WHERE status IN ('pago', 'paid')");
$r = $q->fetch_assoc();
$stats['receita_total'] = $r['total'] ?? 0;

// Distribuição por Plano
$q = $mysqli->query("
    SELECT p.nome, COUNT(a.id) as qtd 
    FROM assinaturas a 
    JOIN planos p ON a.plano_id = p.id 
    WHERE a.status = 'ativa' 
    GROUP BY p.nome
");
while ($row = $q->fetch_assoc()) {
    $stats['planos_distribuicao'][] = $row;
}

// Últimos Usuários Cadastrados
$q = $mysqli->query("SELECT id, nome, email, criado_em FROM usuarios ORDER BY criado_em DESC LIMIT 5");
while ($row = $q->fetch_assoc()) {
    $stats['ultimos_usuarios'][] = $row;
}

// Crescimento Mensal (Últimos 6 meses)
$q = $mysqli->query("
    SELECT DATE_FORMAT(criado_em, '%Y-%m') as mes, COUNT(*) as qtd
    FROM usuarios
    WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY mes
    ORDER BY mes ASC
");
while ($row = $q->fetch_assoc()) {
    $stats['crescimento_mensal'][] = $row;
}

json_out($stats);

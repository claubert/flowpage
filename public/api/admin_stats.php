<?php
require __DIR__ . '/db.php';
require __DIR__ . '/util.php';

// Configuração de segurança
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// 1. Autenticação Básica
$token = get_bearer_token();
if (!$token) json_out(['erro' => 'token_ausente'], 401);

$stmt = $mysqli->prepare('SELECT u.id, u.email FROM sessoes s JOIN usuarios u ON u.id = s.usuario_id WHERE s.token = ? AND s.expira_em > NOW() LIMIT 1');
$stmt->bind_param('s', $token);
$stmt->execute();
$res = $stmt->get_result();
$user_data = $res->fetch_assoc();
$user_id = $user_data['id'] ?? 0;

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

// --- Filtros de Data ---
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Validação básica de datas
if (!strtotime($start_date) || !strtotime($end_date)) {
    $start_date = date('Y-m-d', strtotime('-30 days'));
    $end_date = date('Y-m-d');
}

// Adiciona hora para cobrir o dia inteiro
$start_ts = $start_date . ' 00:00:00';
$end_ts = $end_date . ' 23:59:59';

// 4. Coleta de Métricas
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

// A. Contagem de Usuários (Total vs Filtrado)
// Total Geral (independente do filtro)
$q = $mysqli->query("SELECT COUNT(*) as total FROM usuarios");
$stats['usuarios_total'] = $q->fetch_assoc()['total'];

// Ativos (no momento)
$q = $mysqli->query("SELECT COUNT(*) as ativos FROM usuarios WHERE ativo=1");
$stats['usuarios_ativos'] = $q->fetch_assoc()['ativos'];

// B. Assinaturas (Status Atual)
$q = $mysqli->query("SELECT 
    SUM(CASE WHEN status='ativa' THEN 1 ELSE 0 END) as ativas,
    SUM(CASE WHEN status='em_atraso' THEN 1 ELSE 0 END) as atrasadas
    FROM assinaturas");
$r = $q->fetch_assoc();
$stats['assinaturas_ativas'] = $r['ativas'] ?? 0;
$stats['assinaturas_atrasadas'] = $r['atrasadas'] ?? 0;

// C. Receita Total (Filtrada por Data)
// Soma pagamentos com status 'pago' dentro do período
$stmt = $mysqli->prepare("
    SELECT SUM(valor_centavos) as total 
    FROM pagamentos 
    WHERE status IN ('pago', 'paid') 
    AND (pago_em BETWEEN ? AND ? OR criado_em BETWEEN ? AND ?)
");
// Verificamos tanto 'pago_em' quanto 'criado_em' para garantir
$stmt->bind_param("ssss", $start_ts, $end_ts, $start_ts, $end_ts);
$stmt->execute();
$stats['receita_total'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// D. Distribuição por Plano (Ativas)
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

// E. Últimos Usuários (Apenas visualização, sem filtro de data)
$q = $mysqli->query("SELECT id, nome, email, criado_em FROM usuarios ORDER BY criado_em DESC LIMIT 5");
while ($row = $q->fetch_assoc()) {
    $stats['ultimos_usuarios'][] = $row;
}

// F. Crescimento/Gráfico (Filtrado por Data)
// Agrupa por dia ou mês dependendo do range
$diff = strtotime($end_date) - strtotime($start_date);
$days = round($diff / (60 * 60 * 24));

if ($days <= 60) {
    // Agrupamento Diário
    $stmt = $mysqli->prepare("
        SELECT DATE_FORMAT(criado_em, '%d/%m') as label, COUNT(*) as qtd
        FROM usuarios
        WHERE criado_em BETWEEN ? AND ?
        GROUP BY label
        ORDER BY criado_em ASC
    ");
} else {
    // Agrupamento Mensal
    $stmt = $mysqli->prepare("
        SELECT DATE_FORMAT(criado_em, '%m/%Y') as label, COUNT(*) as qtd
        FROM usuarios
        WHERE criado_em BETWEEN ? AND ?
        GROUP BY label
        ORDER BY criado_em ASC
    ");
}
$stmt->bind_param("ss", $start_ts, $end_ts);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $stats['crescimento_mensal'][] = ['mes' => $row['label'], 'qtd' => $row['qtd']];
}

json_out($stats);

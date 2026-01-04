<?php
require __DIR__ . '/db.php';
require __DIR__ . '/util.php';

header('Content-Type: application/json');

// --- Middleware de Segurança Admin ---
$token = get_bearer_token();
if (!$token) json_out(['erro' => 'token_ausente'], 401);

$stmt = $mysqli->prepare('SELECT u.id, u.email FROM sessoes s JOIN usuarios u ON u.id = s.usuario_id WHERE s.token = ? AND s.expira_em > NOW() LIMIT 1');
$stmt->bind_param('s', $token);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if (!$user || !in_array($user['email'], ['claubert.lopez@gmail.com'])) {
    json_out(['erro' => 'acesso_negado'], 403);
}
// -------------------------------------

$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    $sql = "
        SELECT a.id, u.nome as usuario, p.nome as plano, a.status, a.inicio_em, a.fim_em, a.valor_mensal
        FROM assinaturas a
        JOIN usuarios u ON a.usuario_id = u.id
        JOIN planos p ON a.plano_id = p.id
        ORDER BY a.id DESC LIMIT 50
    ";
    $res = $mysqli->query($sql);
    $subs = [];
    while ($r = $res->fetch_assoc()) $subs[] = $r;
    json_out(['data' => $subs]);
}

if ($action === 'report') {
    // Exemplo de relatório simples CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="relatorio_assinaturas.csv"');
    
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Usuario', 'Plano', 'Status', 'Inicio', 'Fim', 'Valor']);
    
    $sql = "
        SELECT a.id, u.nome, p.nome as plano, a.status, a.inicio_em, a.fim_em, a.valor_mensal
        FROM assinaturas a
        JOIN usuarios u ON a.usuario_id = u.id
        JOIN planos p ON a.plano_id = p.id
    ";
    $res = $mysqli->query($sql);
    while ($row = $res->fetch_assoc()) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

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

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

if ($method === 'GET' && $action === 'list') {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $search = isset($_GET['search']) ? "%" . $_GET['search'] . "%" : null;

    $where = "WHERE 1=1";
    $types = "";
    $params = [];

    if ($search) {
        $where .= " AND (nome LIKE ? OR email LIKE ? OR cpf LIKE ?)";
        $types .= "sss";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }

    // Count
    $countSql = "SELECT COUNT(*) as total FROM usuarios $where";
    $stmt = $mysqli->prepare($countSql);
    if ($search) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];

    // Data
    $sql = "SELECT id, nome, email, cpf, ativo, criado_em, ultimo_login_em FROM usuarios $where ORDER BY id DESC LIMIT ? OFFSET ?";
    $stmt = $mysqli->prepare($sql);
    $types .= "ii";
    $params[] = $limit;
    $params[] = $offset;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $users = [];
    while ($r = $res->fetch_assoc()) $users[] = $r;

    json_out([
        'data' => $users,
        'meta' => [
            'total' => $total,
            'page' => $page,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

if ($method === 'POST' && $action === 'toggle_status') {
    $input = json_decode(file_get_contents('php://input'), true);
    $uid = $input['id'] ?? 0;
    $status = $input['ativo'] ? 1 : 0;
    
    $stmt = $mysqli->prepare("UPDATE usuarios SET ativo = ? WHERE id = ?");
    $stmt->bind_param("ii", $status, $uid);
    $stmt->execute();
    
    // Log Audit
    $log = $mysqli->prepare("INSERT INTO logs_acesso (usuario_id, acao, sucesso, ip, agente_usuario) VALUES (?, ?, 1, ?, ?)");
    $act = "admin_toggle_user_" . $uid . "_to_" . $status;
    $ip = $_SERVER['REMOTE_ADDR'];
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $log->bind_param("isss", $user['id'], $act, $ip, $ua);
    $log->execute();

    json_out(['status' => 'success']);
}

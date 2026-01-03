<?php
// Tenta carregar config local se existir
if (file_exists(__DIR__ . '/config.php')) {
    include_once __DIR__ . '/config.php';
}

// Carregar .env manualmente se necessário e arquivo existir
if (!getenv('BD_HOST') && file_exists(__DIR__ . '/../../.env')) {
    $lines = @file(__DIR__ . '/../../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines) {
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $n = trim($parts[0]); 
                $v = trim($parts[1]);
                putenv("$n=$v"); 
                $_ENV[$n] = $v;
            }
        }
    }
}

// Fallback para constantes se definidas em config.php
$host = getenv('BD_HOST') ?: (defined('DB_HOST') ? DB_HOST : 'localhost');
$user = getenv('BD_USUARIO') ?: (defined('DB_USER') ? DB_USER : '');
$pass = getenv('BD_SENHA') ?: (defined('DB_PASS') ? DB_PASS : '');
$name = getenv('BD_NOME') ?: (defined('DB_NAME') ? DB_NAME : 'flowhedge');
$port = getenv('BD_PORT') ? intval(getenv('BD_PORT')) : 3306;

// Garante que o driver não lance exceções não tratadas antes do nosso controle
mysqli_report(MYSQLI_REPORT_OFF);

try {
    $mysqli = @new mysqli($host, $user, $pass, $name, $port);

    if ($mysqli->connect_errno) {
        throw new Exception($mysqli->connect_error);
    }
    
    $mysqli->set_charset('utf8mb4');
    
} catch (Throwable $e) {
    error_log("DB Connect Error: " . $e->getMessage());
    // Retorna JSON amigável se não for CLI
    if (php_sapi_name() !== 'cli' && !headers_sent()) {
        http_response_code(503);
        header('Content-Type: application/json');
        echo json_encode(['erro' => 'db_indisponivel', 'msg' => 'Serviço temporariamente indisponível.']);
        exit;
    }
}
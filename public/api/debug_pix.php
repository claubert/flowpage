<?php
// debug_pix.php
// Diagnóstico de Conexão e Tabelas para Pagamento PIX
// Salve em: public/api/debug_pix.php

// 1. Forçar exibição de todos os erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO FLOWHEDGE (PIX) ===\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n\n";

// 2. Verificar Extensão MySQLi
echo "[1] Verificando Extensões PHP:\n";
if (extension_loaded('mysqli')) {
    echo "OK: Extensão 'mysqli' carregada.\n";
} else {
    die("FALHA CRÍTICA: Extensão 'mysqli' NÃO está carregada no PHP. Verifique php.ini.\n");
}

// 3. Carregar Variáveis de Ambiente (.env)
echo "\n[2] Carregando Configuração:\n";
// Procura .env em níveis acima
$envPaths = [
    __DIR__ . '/../../.env', // Se estiver em public/api/
    __DIR__ . '/../.env',    // Se estiver em api/
    __DIR__ . '/.env'        // Se estiver na raiz
];

$envFound = false;
foreach ($envPaths as $path) {
    if (file_exists($path)) {
        echo "OK: Arquivo .env encontrado em: $path\n";
        $envFound = true;
        // Parser manual simples para .env
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $val = trim($parts[1]);
                putenv("$key=$val");
                if (!isset($_ENV[$key])) $_ENV[$key] = $val;
            }
        }
        break; 
    }
}

if (!$envFound) {
    echo "AVISO: Nenhum arquivo .env encontrado nos caminhos padrão.\n";
}

// Fallback para constantes se definidas em config.php
if (file_exists(__DIR__ . '/config.php')) {
    include_once __DIR__ . '/config.php';
    echo "OK: config.php encontrado e carregado.\n";
}

// Definição das variáveis de conexão
$host = getenv('BD_HOST') ?: (defined('DB_HOST') ? DB_HOST : 'localhost');
$user = getenv('BD_USUARIO') ?: (defined('DB_USER') ? DB_USER : '');
$pass = getenv('BD_SENHA') ?: (defined('DB_PASS') ? DB_PASS : '');
$name = getenv('BD_NOME') ?: (defined('DB_NAME') ? DB_NAME : 'flowhedge');
$port = getenv('BD_PORT') ? intval(getenv('BD_PORT')) : 3306;

// Mascara a senha para exibição
$passDisplay = $pass ? str_repeat('*', 5) : '(vazia)';
echo "Configuração Identificada:\n";
echo "  Host: $host\n  User: $user\n  Pass: $passDisplay\n  Base: $name\n  Port: $port\n";

// 4. Testar Conexão com o Banco
echo "\n[3] Testando Conexão MySQL:\n";
try {
    $mysqli = new mysqli($host, $user, $pass, $name, $port);
    
    if ($mysqli->connect_errno) {
        throw new Exception("Erro " . $mysqli->connect_errno . ": " . $mysqli->connect_error);
    }
    
    echo "OK: Conexão estabelecida com sucesso!\n";
    echo "Info do Servidor: " . $mysqli->server_info . "\n";
    $mysqli->set_charset('utf8mb4');
    
} catch (Exception $e) {
    echo "FALHA DE CONEXÃO:\n";
    echo $e->getMessage() . "\n";
    echo "\nDICA: Verifique se o MySQL está rodando e se as credenciais no .env estão corretas.\n";
    exit; // Para aqui se não conectar
}

// 5. Verificar Tabelas Necessárias
echo "\n[4] Verificando Tabelas do Sistema:\n";
$tabelasCriticas = ['sessoes', 'assinaturas', 'planos', 'usuarios'];
$todasOk = true;

foreach ($tabelasCriticas as $tab) {
    $res = $mysqli->query("SHOW TABLES LIKE '$tab'");
    if ($res && $res->num_rows > 0) {
        echo "OK: Tabela '$tab' existe.\n";
        
        // Se for planos, listar conteúdo para ver se está vazio
        if ($tab == 'planos') {
             $p = $mysqli->query("SELECT id, nome, preco_centavos FROM planos");
             if ($p->num_rows > 0) {
                 echo "    -> Conteúdo planos: OK ($p->num_rows registros)\n";
                 while($row = $p->fetch_assoc()) {
                     echo "       [{$row['id']}] {$row['nome']} - R$ " . ($row['preco_centavos']/100) . "\n";
                 }
             } else {
                 echo "    -> ALERTA: Tabela 'planos' vazia!\n";
             }
        }

    } else {
        echo "ERRO: Tabela '$tab' NÃO EXISTE.\n";
        $todasOk = false;
    }
}

if (!$todasOk) {
    echo "\nALERTA: Faltam tabelas. Execute o setup.php.\n";
}

// 6. Teste Funcional (Simula leitura de sessão)
echo "\n[5] Teste Funcional (Leitura):\n";
$res = $mysqli->query("SELECT count(*) as total FROM sessoes");
if ($res) {
    $row = $res->fetch_assoc();
    echo "OK: Leitura da tabela 'sessoes' funcionou. Total de registros: " . $row['total'] . "\n";
} else {
    echo "ERRO: Falha ao ler tabela 'sessoes': " . $mysqli->error . "\n";
}

echo "\n=== FIM DO DIAGNÓSTICO ===\n";
?>
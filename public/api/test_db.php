<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Diagnóstico de Ambiente e Banco de Dados</h1>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";

echo "<h2>Extensões Necessárias</h2>";
echo "MySQLi: " . (extension_loaded('mysqli') ? '<span style="color:green">OK</span>' : '<span style="color:red">Faltando</span>') . "<br>";
echo "OpenSSL: " . (extension_loaded('openssl') ? '<span style="color:green">OK</span>' : '<span style="color:red">Faltando</span>') . "<br>";

echo "<h2>Variáveis de Ambiente (DB)</h2>";
$vars = ['BD_HOST', 'BD_USUARIO', 'BD_SENHA', 'BD_NOME', 'BD_PORT'];
foreach ($vars as $v) {
    $val = getenv($v);
    echo "$v: " . ($val ? "Definido (" . strlen($val) . " chars)" : '<span style="color:red">Não definido</span>') . "<br>";
}

echo "<h2>Teste de Conexão</h2>";
require_once 'db.php';

if (isset($mysqli) && !$mysqli->connect_errno) {
    echo "<h3 style='color:green'>Conexão bem sucedida!</h3>";
    echo "Host info: " . $mysqli->host_info . "<br>";
} else {
    echo "<h3 style='color:red'>Falha na conexão</h3>";
    if (isset($mysqli)) {
        echo "Erro: " . $mysqli->connect_error . "<br>";
        echo "Errno: " . $mysqli->connect_errno . "<br>";
    } else {
        echo "Objeto mysqli não criado.<br>";
    }
}

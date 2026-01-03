<?php
require __DIR__ . '/db.php';
require __DIR__ . '/util.php';

echo "<h1>Configuração do Banco de Dados</h1>";

$queries = [
    "CREATE TABLE IF NOT EXISTS `usuarios` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `nome` varchar(255) NOT NULL,
        `email` varchar(255) NOT NULL,
        `cpf` varchar(14) DEFAULT NULL,
        `telefone` varchar(20) DEFAULT NULL,
        `login` varchar(50) NOT NULL,
        `senha_hash` varchar(255) NOT NULL,
        `termos_aceitos` tinyint(1) DEFAULT 0,
        `termos_aceitos_em` datetime DEFAULT NULL,
        `ativo` tinyint(1) DEFAULT 1,
        `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `email` (`email`),
        UNIQUE KEY `login` (`login`),
        UNIQUE KEY `cpf` (`cpf`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `sessoes` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `usuario_id` int(11) NOT NULL,
        `token` varchar(64) NOT NULL,
        `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
        `expira_em` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `token` (`token`),
        KEY `usuario_id` (`usuario_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `planos` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `nome` varchar(100) NOT NULL,
        `codigo` varchar(50) NOT NULL,
        `descricao` text,
        `preco_centavos` int(11) NOT NULL,
        `intervalo_dias` int(11) NOT NULL,
        `ativo` tinyint(1) DEFAULT 1,
        PRIMARY KEY (`id`),
        UNIQUE KEY `codigo` (`codigo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `assinaturas` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `usuario_id` int(11) NOT NULL,
        `plano_id` int(11) NOT NULL,
        `status` enum('pendente','ativo','cancelado','expirado') DEFAULT 'pendente',
        `inicio_em` datetime DEFAULT NULL,
        `fim_em` datetime DEFAULT NULL,
        `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
        `atualizado_em` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `usuario_id` (`usuario_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `pagamentos` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `assinatura_id` int(11) NOT NULL,
        `transacao_id` varchar(100) DEFAULT NULL,
        `valor_centavos` int(11) NOT NULL,
        `metodo` varchar(50) DEFAULT NULL,
        `status` enum('pendente','pago','falhou','reembolsado') DEFAULT 'pendente',
        `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
        `pago_em` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `assinatura_id` (`assinatura_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

// Inserir planos padrão se não existirem
$planos = [
    "INSERT IGNORE INTO `planos` (`nome`, `codigo`, `preco_centavos`, `intervalo_dias`) VALUES ('Plano Mensal', 'mensal', 29700, 30);",
    "INSERT IGNORE INTO `planos` (`nome`, `codigo`, `preco_centavos`, `intervalo_dias`) VALUES ('Plano Semestral', 'semestral', 167700, 180);",
    "INSERT IGNORE INTO `planos` (`nome`, `codigo`, `preco_centavos`, `intervalo_dias`) VALUES ('Plano Anual', 'anual', 296400, 365);"
];

foreach ($queries as $sql) {
    if ($mysqli->query($sql)) {
        echo "Tabela verificada/criada com sucesso.<br>";
    } else {
        echo "Erro ao criar tabela: " . $mysqli->error . "<br>";
    }
}

foreach ($planos as $sql) {
    if ($mysqli->query($sql)) {
        echo "Plano inserido/verificado.<br>";
    } else {
        echo "Erro ao inserir plano: " . $mysqli->error . "<br>";
    }
}

echo "Configuração concluída.";

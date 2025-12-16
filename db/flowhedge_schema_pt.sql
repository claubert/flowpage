CREATE DATABASE IF NOT EXISTS `flowhedge` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE `flowhedge`;

CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(120) NOT NULL,
  `cpf` CHAR(11) NULL,
  `telefone` VARCHAR(20) NULL,
  `email` VARCHAR(255) NOT NULL,
  `login` VARCHAR(50) NULL,
  `senha_hash` VARBINARY(255) NOT NULL,
  `termos_aceitos` TINYINT(1) NOT NULL DEFAULT 0,
  `termos_aceitos_em` DATETIME NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ativo` TINYINT(1) NOT NULL DEFAULT 0,
  `tentativas_falhas` INT NOT NULL DEFAULT 0,
  `bloqueado_ate` DATETIME NULL,
  `ultimo_login_em` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_usuarios_email` (`email`),
  UNIQUE KEY `uniq_usuarios_login` (`login`),
  UNIQUE KEY `uniq_usuarios_cpf` (`cpf`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `planos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(100) NOT NULL,
  `codigo` VARCHAR(50) NOT NULL,
  `periodo_cobranca` ENUM('mensal','semestral','anual') NOT NULL,
  `preco_centavos` INT UNSIGNED NOT NULL,
  `moeda` CHAR(3) NOT NULL DEFAULT 'BRL',
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_planos_codigo` (`codigo`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `assinaturas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` BIGINT UNSIGNED NULL,
  `plano_id` BIGINT UNSIGNED NOT NULL,
  `status` ENUM('ativa','em_atraso','cancelada','expirada','pendente') NOT NULL,
  `inicio_em` DATETIME NOT NULL,
  `fim_em` DATETIME NULL,
  `proxima_cobranca_em` DATETIME NULL,
  `id_cliente_gateway` VARCHAR(100) NULL,
  `contratante_email` VARCHAR(255) NULL,
  `contratante_nome` VARCHAR(120) NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assinaturas_usuario_id` (`usuario_id`),
  KEY `idx_assinaturas_plano_id` (`plano_id`),
  KEY `idx_assinaturas_contratante_email` (`contratante_email`),
  CONSTRAINT `fk_assinaturas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_assinaturas_plano` FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `pagamentos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assinatura_id` BIGINT UNSIGNED NOT NULL,
  `valor_centavos` INT UNSIGNED NOT NULL,
  `moeda` CHAR(3) NOT NULL DEFAULT 'BRL',
  `metodo` ENUM('pix','cartao_credito','cartao_debito') NOT NULL,
  `status` ENUM('pendente','pago','falhou','estornado') NOT NULL,
  `id_externo` VARCHAR(100) NULL,
  `pago_em` DATETIME NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pagamentos_assinatura_id` (`assinatura_id`),
  CONSTRAINT `fk_pagamentos_assinatura` FOREIGN KEY (`assinatura_id`) REFERENCES `assinaturas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `sessoes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` BIGINT UNSIGNED NOT NULL,
  `token` CHAR(64) NOT NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expira_em` DATETIME NOT NULL,
  `revogada_em` DATETIME NULL,
  `ip` VARCHAR(45) NULL,
  `agente_usuario` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sessoes_token` (`token`),
  KEY `idx_sessoes_usuario_id` (`usuario_id`),
  CONSTRAINT `fk_sessoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `recuperacoes_senha` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` BIGINT UNSIGNED NOT NULL,
  `token` CHAR(64) NOT NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expira_em` DATETIME NOT NULL,
  `usado_em` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_recuperacoes_token` (`token`),
  KEY `idx_recuperacoes_usuario_id` (`usuario_id`),
  CONSTRAINT `fk_recuperacoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `notificacoes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` BIGINT UNSIGNED NOT NULL,
  `tipo` ENUM('confirmacao_pagamento','lembrete_renovacao','acesso_bloqueado') NOT NULL,
  `status` ENUM('em_fila','enviado','falhou') NOT NULL,
  `enviado_em` DATETIME NULL,
  `metadados` JSON NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notificacoes_usuario_id` (`usuario_id`),
  CONSTRAINT `fk_notificacoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `logs_acesso` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` BIGINT UNSIGNED NULL,
  `acao` VARCHAR(100) NOT NULL,
  `sucesso` TINYINT(1) NOT NULL,
  `ip` VARCHAR(45) NULL,
  `agente_usuario` VARCHAR(255) NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logs_usuario_id` (`usuario_id`),
  CONSTRAINT `fk_logs_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `estrategias_ls` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(120) NOT NULL,
  `descricao` TEXT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `sinais_ls` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `estrategia_id` BIGINT UNSIGNED NOT NULL,
  `codigo_par` VARCHAR(50) NOT NULL,
  `simbolo_long` VARCHAR(20) NOT NULL,
  `simbolo_short` VARCHAR(20) NOT NULL,
  `correlacao` DECIMAL(5,4) NULL,
  `zscore` DECIMAL(6,3) NULL,
  `estado` ENUM('aberto','fechado','pendente') NOT NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fechado_em` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sinais_estrategia_id` (`estrategia_id`),
  CONSTRAINT `fk_sinais_estrategia` FOREIGN KEY (`estrategia_id`) REFERENCES `estrategias_ls` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `posicoes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` BIGINT UNSIGNED NOT NULL,
  `sinal_id` BIGINT UNSIGNED NULL,
  `simbolo_long` VARCHAR(20) NOT NULL,
  `simbolo_short` VARCHAR(20) NOT NULL,
  `aberta_em` DATETIME NOT NULL,
  `fechada_em` DATETIME NULL,
  `pnl_centavos` INT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_posicoes_usuario_id` (`usuario_id`),
  KEY `idx_posicoes_sinal_id` (`sinal_id`),
  CONSTRAINT `fk_posicoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_posicoes_sinal` FOREIGN KEY (`sinal_id`) REFERENCES `sinais_ls` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `acessos_recurso` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` BIGINT UNSIGNED NOT NULL,
  `codigo_recurso` VARCHAR(50) NOT NULL,
  `habilitado` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_acesso_usuario_codigo` (`usuario_id`,`codigo_recurso`),
  KEY `idx_acessos_usuario_id` (`usuario_id`),
  CONSTRAINT `fk_acessos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

INSERT INTO `planos` (`nome`,`codigo`,`periodo_cobranca`,`preco_centavos`,`moeda`,`ativo`) VALUES
('Plano Mensal','mensal','mensal',1,'BRL',1),
('Plano Semestral','semestral','semestral',0,'BRL',1),
('Plano Anual','anual','anual',0,'BRL',1)
ON DUPLICATE KEY UPDATE `nome`=VALUES(`nome`),`periodo_cobranca`=VALUES(`periodo_cobranca`),`preco_centavos`=VALUES(`preco_centavos`),`moeda`=VALUES(`moeda`),`ativo`=VALUES(`ativo`);
USE `flowhedge`;

ALTER TABLE `usuarios`
  ADD COLUMN IF NOT EXISTS `cpf` CHAR(11) NULL AFTER `nome`,
  ADD COLUMN IF NOT EXISTS `telefone` VARCHAR(20) NULL AFTER `cpf`,
  ADD COLUMN IF NOT EXISTS `login` VARCHAR(50) NULL AFTER `email`,
  ADD COLUMN IF NOT EXISTS `termos_aceitos` TINYINT(1) NOT NULL DEFAULT 0 AFTER `senha_hash`,
  ADD COLUMN IF NOT EXISTS `termos_aceitos_em` DATETIME NULL AFTER `termos_aceitos`,
  ADD COLUMN IF NOT EXISTS `tentativas_falhas` INT NOT NULL DEFAULT 0 AFTER `ativo`,
  ADD COLUMN IF NOT EXISTS `bloqueado_ate` DATETIME NULL AFTER `tentativas_falhas`,
  ADD COLUMN IF NOT EXISTS `ultimo_login_em` DATETIME NULL AFTER `bloqueado_ate`;

SET @has_idx_login := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios' AND INDEX_NAME='uniq_usuarios_login');
SET @sql := IF(@has_idx_login=0,'ALTER TABLE `usuarios` ADD UNIQUE KEY `uniq_usuarios_login` (`login`);','SELECT "skip"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_idx_cpf := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios' AND INDEX_NAME='uniq_usuarios_cpf');
SET @sql := IF(@has_idx_cpf=0,'ALTER TABLE `usuarios` ADD UNIQUE KEY `uniq_usuarios_cpf` (`cpf`);','SELECT "skip"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
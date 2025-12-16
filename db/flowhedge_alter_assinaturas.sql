USE `flowhedge`;

SET @col_nullable := (SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='assinaturas' AND COLUMN_NAME='usuario_id');
SET @sql := IF(@col_nullable='NO','ALTER TABLE `assinaturas` MODIFY `usuario_id` BIGINT UNSIGNED NULL;','SELECT "skip"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_email := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='assinaturas' AND COLUMN_NAME='contratante_email');
SET @sql := IF(@has_email=0,'ALTER TABLE `assinaturas` ADD COLUMN `contratante_email` VARCHAR(255) NULL AFTER `id_cliente_gateway`;','SELECT "skip"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_nome := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='assinaturas' AND COLUMN_NAME='contratante_nome');
SET @sql := IF(@has_nome=0,'ALTER TABLE `assinaturas` ADD COLUMN `contratante_nome` VARCHAR(120) NULL AFTER `contratante_email`;','SELECT "skip"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_idx := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='assinaturas' AND INDEX_NAME='idx_assinaturas_contratante_email');
SET @sql := IF(@has_idx=0,'ALTER TABLE `assinaturas` ADD INDEX `idx_assinaturas_contratante_email` (`contratante_email`);','SELECT "skip"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
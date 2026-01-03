-- Índices idempotentes para colunas de FK e alto uso
SET @skip := 'SELECT "skip"';

SET @i1 := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagamentos' AND INDEX_NAME='idx_pagamentos_assinatura_id');
SET @sql := IF(@i1=0,'ALTER TABLE `pagamentos` ADD INDEX `idx_pagamentos_assinatura_id` (`assinatura_id`);',@skip);
PREPARE s1 FROM @sql; EXECUTE s1; DEALLOCATE PREPARE s1;

SET @i2 := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='assinaturas' AND INDEX_NAME='idx_assinaturas_usuario_id');
SET @sql := IF(@i2=0,'ALTER TABLE `assinaturas` ADD INDEX `idx_assinaturas_usuario_id` (`usuario_id`);',@skip);
PREPARE s2 FROM @sql; EXECUTE s2; DEALLOCATE PREPARE s2;

SET @i3 := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='assinaturas' AND INDEX_NAME='idx_assinaturas_plano_id');
SET @sql := IF(@i3=0,'ALTER TABLE `assinaturas` ADD INDEX `idx_assinaturas_plano_id` (`plano_id`);',@skip);
PREPARE s3 FROM @sql; EXECUTE s3; DEALLOCATE PREPARE s3;

SET @i4 := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sessoes' AND INDEX_NAME='idx_sessoes_usuario_id');
SET @sql := IF(@i4=0,'ALTER TABLE `sessoes` ADD INDEX `idx_sessoes_usuario_id` (`usuario_id`);',@skip);
PREPARE s4 FROM @sql; EXECUTE s4; DEALLOCATE PREPARE s4;

SET @i5 := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='logs_acesso' AND INDEX_NAME='idx_logs_acesso_usuario_id');
SET @sql := IF(@i5=0,'ALTER TABLE `logs_acesso` ADD INDEX `idx_logs_acesso_usuario_id` (`usuario_id`);',@skip);
PREPARE s5 FROM @sql; EXECUTE s5; DEALLOCATE PREPARE s5;

SET @i6 := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='recuperacoes_senha' AND INDEX_NAME='idx_recuperacoes_senha_usuario_id');
SET @sql := IF(@i6=0,'ALTER TABLE `recuperacoes_senha` ADD INDEX `idx_recuperacoes_senha_usuario_id` (`usuario_id`);',@skip);
PREPARE s6 FROM @sql; EXECUTE s6; DEALLOCATE PREPARE s6;

SET @i7 := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notificacoes' AND INDEX_NAME='idx_notificacoes_usuario_id');
SET @sql := IF(@i7=0,'ALTER TABLE `notificacoes` ADD INDEX `idx_notificacoes_usuario_id` (`usuario_id`);',@skip);
PREPARE s7 FROM @sql; EXECUTE s7; DEALLOCATE PREPARE s7;
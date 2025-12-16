CREATE DATABASE IF NOT EXISTS `flowhedge`;
USE `flowhedge`;

SET @has_users := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users');
SET @has_usuarios := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios');
SET @sql := IF(@has_users=1 AND @has_usuarios=0,'RENAME TABLE `users` TO `usuarios`;','SELECT "skip"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_plans := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plans');
SET @has_planos := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='planos');
SET @sql := IF(@has_plans=1 AND @has_planos=0,'RENAME TABLE `plans` TO `planos`;','SELECT "skip"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_subscriptions := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='subscriptions');
SET @has_assinaturas := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='assinaturas');
SET @sql := IF(@has_subscriptions=1 AND @has_assinaturas=0,'RENAME TABLE `subscriptions` TO `assinaturas`;','SELECT "skip"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_payments := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payments');
SET @has_pagamentos := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagamentos');
SET @sql := IF(@has_payments=1 AND @has_pagamentos=0,'RENAME TABLE `payments` TO `pagamentos`;','SELECT "skip"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_sessions := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sessions');
SET @has_sessoes := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sessoes');
SET @sql := IF(@has_sessions=1 AND @has_sessoes=0,'RENAME TABLE `sessions` TO `sessoes`;','SELECT "skip"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_password_resets := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_resets');
SET @has_recuperacoes := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='recuperacoes_senha');
SET @sql := IF(@has_password_resets=1 AND @has_recuperacoes=0,'RENAME TABLE `password_resets` TO `recuperacoes_senha`;','SELECT "skip"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_notifications := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications');
SET @has_notificacoes := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notificacoes');
SET @sql := IF(@has_notifications=1 AND @has_notificacoes=0,'RENAME TABLE `notifications` TO `notificacoes`;','SELECT "skip"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_access_logs := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='access_logs');
SET @has_logs_acesso := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='logs_acesso');
SET @sql := IF(@has_access_logs=1 AND @has_logs_acesso=0,'RENAME TABLE `access_logs` TO `logs_acesso`;','SELECT "skip"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_ls_strategies := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ls_strategies');
SET @has_estrategias_ls := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='estrategias_ls');
SET @sql := IF(@has_ls_strategies=1 AND @has_estrategias_ls=0,'RENAME TABLE `ls_strategies` TO `estrategias_ls`;','SELECT "skip"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_ls_signals := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ls_signals');
SET @has_sinais_ls := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sinais_ls');
SET @sql := IF(@has_ls_signals=1 AND @has_sinais_ls=0,'RENAME TABLE `ls_signals` TO `sinais_ls`;','SELECT "skip"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_positions := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='positions');
SET @has_posicoes := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='posicoes');
SET @sql := IF(@has_positions=1 AND @has_posicoes=0,'RENAME TABLE `positions` TO `posicoes`;','SELECT "skip"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_feature_access := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='feature_access');
SET @has_acessos_recurso := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='acessos_recurso');
SET @sql := IF(@has_feature_access=1 AND @has_acessos_recurso=0,'RENAME TABLE `feature_access` TO `acessos_recurso`;','SELECT "skip"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
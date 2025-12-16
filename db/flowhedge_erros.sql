USE `flowhedge`;

CREATE TABLE IF NOT EXISTS `erros_sistema` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rota` VARCHAR(120) NOT NULL,
  `mensagem` TEXT NULL,
  `stack` TEXT NULL,
  `status_code` INT NULL,
  `ip` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
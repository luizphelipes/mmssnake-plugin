-- SQL para adicionar coluna service_id_pedido na tabela de pedidos processados
-- Execute este SQL no seu banco de dados WordPress

ALTER TABLE `wp_pedidos_processados` 
ADD COLUMN `service_id_pedido` VARCHAR(50) NULL 
AFTER `instagram_username`;

-- Comentário da coluna
COMMENT ON COLUMN `wp_pedidos_processados`.`service_id_pedido` IS 'Service ID SMM configurado no produto';

-- Índice para melhor performance
CREATE INDEX `idx_service_id_pedido` ON `wp_pedidos_processados` (`service_id_pedido`);

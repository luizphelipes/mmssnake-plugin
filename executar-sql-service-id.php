<?php
/**
 * Executar SQL para adicionar coluna service_id_pedido
 */

// Carregar WordPress
require_once('../../../wp-load.php');

echo "<h2>🔧 Executando SQL para adicionar coluna service_id_pedido</h2>";

// Verificar se o WooCommerce está ativo
if (!class_exists('WooCommerce')) {
    die('❌ WooCommerce não está ativo');
}
echo "<p>✅ WooCommerce ativo</p>";

// Verificar se o plugin está ativo
if (!class_exists('PedidosProcessandoPlugin')) {
    die('❌ Plugin Pedidos em Processamento não está ativo');
}
echo "<p>✅ Plugin ativo</p>";

echo "<hr><h3>🔧 Verificando Estrutura Atual da Tabela</h3>";

global $wpdb;
$table_name = $wpdb->prefix . 'pedidos_processados';

if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
    echo "<p>✅ Tabela do plugin existe</p>";
    
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
    $has_service_id_column = false;
    
    echo "<p>📋 Colunas atuais da tabela:</p><ul>";
    foreach ($columns as $column) {
        echo "<li><strong>{$column->Field}</strong> - {$column->Type}";
        if ($column->Field === 'service_id_pedido') {
            $has_service_id_column = true;
            echo " ✅";
        }
        echo "</li>";
    }
    echo "</ul>";
    
    if ($has_service_id_column) {
        echo "<p>✅ Coluna 'service_id_pedido' já existe!</p>";
        echo "<p>💡 Não é necessário executar o SQL.</p>";
    } else {
        echo "<p>❌ Coluna 'service_id_pedido' NÃO existe</p>";
        echo "<p>🔧 Executando SQL para adicionar a coluna...</p>";
        
        // SQL para adicionar a coluna
        $sql = "ALTER TABLE `{$table_name}` 
                ADD COLUMN `service_id_pedido` VARCHAR(50) NULL 
                AFTER `instagram_username`";
        
        echo "<p>📝 SQL a ser executado:</p>";
        echo "<code>{$sql}</code>";
        
        // Executar o SQL
        $resultado = $wpdb->query($sql);
        
        if ($resultado !== false) {
            echo "<p>✅ Coluna 'service_id_pedido' adicionada com sucesso!</p>";
            
            // Verificar se a coluna foi criada
            $columns_apos = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
            $has_service_id_column_apos = false;
            
            foreach ($columns_apos as $column) {
                if ($column->Field === 'service_id_pedido') {
                    $has_service_id_column_apos = true;
                    break;
                }
            }
            
            if ($has_service_id_column_apos) {
                echo "<p>✅ Verificação: Coluna criada com sucesso!</p>";
                
                // Criar índice para melhor performance
                $index_sql = "CREATE INDEX `idx_service_id_pedido` ON `{$table_name}` (`service_id_pedido`)";
                $index_resultado = $wpdb->query($index_sql);
                
                if ($index_resultado !== false) {
                    echo "<p>✅ Índice criado com sucesso!</p>";
                } else {
                    echo "<p>⚠️ Índice não foi criado (pode já existir)</p>";
                }
                
            } else {
                echo "<p>❌ Erro: Coluna não foi criada</p>";
            }
            
        } else {
            echo "<p>❌ Erro ao executar SQL: {$wpdb->last_error}</p>";
        }
    }
    
} else {
    echo "<p>❌ Tabela do plugin não existe</p>";
}

echo "<hr><h3>🔧 Verificando Produtos com Service ID</h3>";

// Verificar produtos que têm Service ID configurado
$produtos_com_service_id = $wpdb->get_results(
    "SELECT p.ID, p.post_title, pm.meta_value as service_id 
     FROM {$wpdb->posts} p 
     INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
     WHERE p.post_type = 'product' 
     AND p.post_status = 'publish' 
     AND pm.meta_key = '_smm_service_id' 
     AND pm.meta_value != ''"
);

if (!empty($produtos_com_service_id)) {
    echo "<p>✅ Produtos com Service ID configurado:</p>";
    echo "<ul>";
    foreach ($produtos_com_service_id as $produto) {
        echo "<li><strong>{$produto->post_title}</strong> (ID: {$produto->ID}) - Service ID: {$produto->service_id}</li>";
    }
    echo "</ul>";
} else {
    echo "<p>⚠️ Nenhum produto tem Service ID configurado</p>";
    echo "<p>💡 Configure o Service ID nos produtos para que os pedidos sejam processados</p>";
}

echo "<hr><h3>🔧 Atualizando Pedidos Existentes</h3>";

if ($has_service_id_column || $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
    // Verificar se há pedidos sem Service ID
    $pedidos_sem_service_id = $wpdb->get_results(
        "SELECT * FROM {$table_name} 
         WHERE (service_id_pedido IS NULL OR service_id_pedido = '') 
         AND status_api = 'pending'"
    );
    
    if (!empty($pedidos_sem_service_id)) {
        echo "<p>📋 Pedidos sem Service ID encontrados: " . count($pedidos_sem_service_id) . "</p>";
        
        $atualizados = 0;
        foreach ($pedidos_sem_service_id as $pedido) {
            // Buscar Service ID do produto
            $service_id_produto = get_post_meta($pedido->produto_id, '_smm_service_id', true);
            
            if (!empty($service_id_produto)) {
                // Atualizar pedido com Service ID
                $update_result = $wpdb->update(
                    $table_name,
                    ['service_id_pedido' => $service_id_produto],
                    ['id' => $pedido->id],
                    ['%s'],
                    ['%d']
                );
                
                if ($update_result !== false) {
                    $atualizados++;
                    echo "<p>✅ Pedido #{$pedido->order_id} atualizado com Service ID: {$service_id_produto}</p>";
                } else {
                    echo "<p>❌ Erro ao atualizar pedido #{$pedido->order_id}</p>";
                }
            } else {
                echo "<p>⚠️ Produto #{$pedido->produto_id} não tem Service ID configurado</p>";
            }
        }
        
        if ($atualizados > 0) {
            echo "<p>🎉 <strong>{$atualizados} pedidos foram atualizados com Service ID!</strong></p>";
            echo "<p>💡 Agora você pode usar o botão 'Processar Pedidos Pendentes' para enviá-los para a API SMM.</p>";
        }
        
    } else {
        echo "<p>✅ Todos os pedidos já têm Service ID configurado</p>";
    }
}

echo "<hr>";
echo "<p><a href='../pedidos-processando.php'>🔙 Voltar ao Plugin</a></p>";
echo "<p><a href='teste-botao-debug.php'>🔄 Verificar Status</a></p>";
echo "<p><strong>💡 PRÓXIMOS PASSOS:</strong></p>";
echo "<ol>";
echo "<li>✅ SQL executado (coluna service_id_pedido criada)</li>";
echo "<li>✅ Pedidos existentes atualizados com Service ID</li>";
echo "<li>🔧 Configure Service ID nos produtos que não têm</li>";
echo "<li>🚀 Use o botão 'Processar Pedidos Pendentes' na página admin</li>";
echo "</ol>";
?>

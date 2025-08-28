<?php
/**
 * Verificar Pedido Mais Recente
 */

// Carregar WordPress
require_once('../../../wp-load.php');

echo "<h2>🔍 Verificando Pedido Mais Recente</h2>";

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

echo "<hr><h3>📋 Verificando Banco de Dados</h3>";

global $wpdb;
$table_name = $wpdb->prefix . 'pedidos_processados';

if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
    echo "<p>✅ Tabela do plugin existe</p>";
    
    // Verificar estrutura da tabela
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
    echo "<p>📋 Estrutura da tabela:</p><ul>";
    foreach ($columns as $column) {
        echo "<li><strong>{$column->Field}</strong> - {$column->Type}";
        if ($column->Field === 'service_id_pedido') {
            echo " ✅";
        }
        echo "</li>";
    }
    echo "</ul>";
    
    // Buscar pedido mais recente
    $pedido_recente = $wpdb->get_row("SELECT * FROM $table_name ORDER BY id DESC LIMIT 1");
    
    if ($pedido_recente) {
        echo "<p>🎯 <strong>Pedido mais recente encontrado:</strong></p>";
        echo "<div style='background: #f5f5f5; padding: 15px; border: 1px solid #ddd; margin: 10px 0;'>";
        echo "<h4>📋 Detalhes do Pedido #{$pedido_recente->order_id}</h4>";
        echo "<table style='width: 100%; border-collapse: collapse;'>";
        echo "<tr><td style='padding: 5px; font-weight: bold;'>ID Plugin:</td><td>{$pedido_recente->id}</td></tr>";
        echo "<tr><td style='padding: 5px; font-weight: bold;'>Order ID:</td><td>{$pedido_recente->order_id}</td></tr>";
        echo "<tr><td style='padding: 5px; font-weight: bold;'>Produto ID:</td><td>{$pedido_recente->produto_id}</td></tr>";
        echo "<tr><td style='padding: 5px; font-weight: bold;'>Produto Nome:</td><td>{$pedido_recente->produto_nome}</td></tr>";
        echo "<tr><td style='padding: 5px; font-weight: bold;'>Quantidade Variação:</td><td>{$pedido_recente->quantidade_variacao}</td></tr>";
        echo "<tr><td style='padding: 5px; font-weight: bold;'>Instagram Username:</td><td>{$pedido_recente->instagram_username}</td></tr>";
        echo "<tr><td style='padding: 5px; font-weight: bold;'>Service ID Pedido:</td><td style='background: " . (empty($pedido_recente->service_id_pedido) ? '#ffebee' : '#e8f5e8') . ";'>" . ($pedido_recente->service_id_pedido ?: 'N/A') . "</td></tr>";
        echo "<tr><td style='padding: 5px; font-weight: bold;'>Status API:</td><td>{$pedido_recente->status_api}</td></tr>";
        echo "<tr><td style='padding: 5px; font-weight: bold;'>Tentativas:</td><td>{$pedido_recente->tentativas}</td></tr>";
        echo "<tr><td style='padding: 5px; font-weight: bold;'>Data Processamento:</td><td>{$pedido_recente->data_processamento}</td></tr>";
        if (!empty($pedido_recente->mensagem_api)) {
            echo "<tr><td style='padding: 5px; font-weight: bold;'>Mensagem API:</td><td>{$pedido_recente->mensagem_api}</td></tr>";
        }
        echo "</table>";
        echo "</div>";
        
        // Verificar se o produto tem Service ID configurado
        echo "<hr><h3>🔧 Verificando Produto</h3>";
        
        $produto_id = $pedido_recente->produto_id;
        $produto = get_post($produto_id);
        
        if ($produto) {
            echo "<p>✅ Produto encontrado: <strong>{$produto->post_title}</strong> (ID: {$produto_id})</p>";
            
            // Verificar meta do produto
            $service_id_produto = get_post_meta($produto_id, '_smm_service_id', true);
            echo "<p>🔧 Service ID configurado no produto: <strong>" . ($service_id_produto ?: 'NÃO CONFIGURADO') . "</strong></p>";
            
            if (!empty($service_id_produto)) {
                echo "<p>✅ Produto tem Service ID configurado</p>";
                
                // Verificar se o pedido tem o Service ID correto
                if (empty($pedido_recente->service_id_pedido)) {
                    echo "<p>⚠️ <strong>PROBLEMA IDENTIFICADO:</strong> Pedido não tem Service ID, mas o produto tem!</p>";
                    echo "<p>💡 Vou atualizar o pedido com o Service ID correto...</p>";
                    
                    // Atualizar pedido com Service ID
                    $update_result = $wpdb->update(
                        $table_name,
                        ['service_id_pedido' => $service_id_produto],
                        ['id' => $pedido_recente->id],
                        ['%s'],
                        ['%d']
                    );
                    
                    if ($update_result !== false) {
                        echo "<p>✅ Pedido atualizado com Service ID: {$service_id_produto}</p>";
                        
                        // Verificar novamente
                        $pedido_atualizado = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $pedido_recente->id));
                        echo "<p>🔄 Verificação após atualização:</p>";
                        echo "<p>Service ID no pedido: <strong>" . ($pedido_atualizado->service_id_pedido ?: 'N/A') . "</strong></p>";
                        
                    } else {
                        echo "<p>❌ Erro ao atualizar pedido: {$wpdb->last_error}</p>";
                    }
                    
                } else {
                    echo "<p>✅ Pedido já tem Service ID configurado</p>";
                }
                
            } else {
                echo "<p>❌ <strong>PROBLEMA CRÍTICO:</strong> Produto não tem Service ID configurado!</p>";
                echo "<p>💡 Configure o Service ID no produto para que os pedidos sejam processados.</p>";
            }
            
        } else {
            echo "<p>❌ Produto não encontrado (ID: {$produto_id})</p>";
        }
        
    } else {
        echo "<p>❌ Nenhum pedido encontrado na tabela</p>";
    }
    
} else {
    echo "<p>❌ Tabela do plugin não existe</p>";
}

echo "<hr>";
echo "<p><a href='../pedidos-processando.php'>🔙 Voltar ao Plugin</a></p>";
echo "<p><a href='teste-botao-debug.php'>🔄 Verificar Status</a></p>";
?>

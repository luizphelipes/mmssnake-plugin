<?php
/**
 * Teste de Pedido Completo com Instagram
 */

// Carregar WordPress
require_once('../../../wp-load.php');

echo "<h2>🛒 Teste de Pedido Completo com Instagram</h2>";

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

echo "<hr><h3>🔧 Verificando Produto de Teste</h3>";

// Verificar se o produto de teste existe e tem Service ID configurado
$produto_teste = get_page_by_title('Produto Teste SMM', OBJECT, 'product');
if ($produto_teste) {
    echo "<p>✅ Produto de teste encontrado (ID: {$produto_teste->ID})</p>";
    
    // Verificar Service ID
    $service_id = get_post_meta($produto_teste->ID, '_smm_service_id', true);
    if (!empty($service_id)) {
        echo "<p>✅ Service ID configurado: {$service_id}</p>";
    } else {
        echo "<p>❌ Service ID NÃO configurado</p>";
        echo "<p>💡 Configure o Service ID no produto</p>";
    }
} else {
    echo "<p>❌ Produto de teste não encontrado</p>";
    echo "<p>💡 Crie um produto chamado 'Produto Teste SMM'</p>";
}

echo "<hr><h3>🛒 Criando Pedido com Instagram</h3>";

try {
    if ($produto_teste) {
        // Criar um pedido de teste
        $order = wc_create_order();
        
        // Adicionar produto ao pedido
        $order->add_product(wc_get_product($produto_teste->ID), 1);
        echo "<p>✅ Produto adicionado ao pedido</p>";
        
        // Definir endereço básico
        $order->set_address([
            'first_name' => 'Teste',
            'last_name' => 'Instagram',
            'email' => 'teste@instagram.com',
            'phone' => '11999999999'
        ], 'billing');
        
        // Salvar o pedido
        $order->save();
        
        $order_id = $order->get_id();
        echo "<p>✅ Pedido criado com ID: {$order_id}</p>";
        echo "<p>📊 Status inicial: {$order->get_status()}</p>";
        
        // Adicionar meta do Instagram ANTES de salvar
        foreach ($order->get_items() as $item) {
            // Adicionar username do Instagram
            wc_add_order_item_meta($item->get_id(), 'Instagram', 'phelipesf', true);
            echo "<p>✅ Username Instagram 'phelipesf' adicionado ao item</p>";
        }
        
        // Salvar novamente para garantir que o meta foi salvo
        $order->save();
        
        // Mudar status para processing para triggerar os hooks
        $order->set_status('processing');
        $order->save();
        echo "<p>✅ Status alterado para: {$order->get_status()}</p>";
        
        // Aguardar um pouco para o processamento
        echo "<p>⏳ Aguardando processamento...</p>";
        sleep(3);
        
        // Verificar se foi inserido na tabela
        global $wpdb;
        $table_name = $wpdb->prefix . 'pedidos_processados';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            echo "<p>✅ Tabela do plugin existe</p>";
            
            $pedido_plugin = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE order_id = %d",
                $order_id
            ));
            
            if ($pedido_plugin) {
                echo "<p>🎉 Pedido encontrado na tabela do plugin!</p>";
                echo "<p>📋 Detalhes do pedido:</p>";
                echo "<ul>";
                echo "<li><strong>ID Plugin:</strong> {$pedido_plugin->id}</li>";
                echo "<li><strong>Status API:</strong> {$pedido_plugin->status_api}</li>";
                echo "<li><strong>Service ID:</strong> " . ($pedido_plugin->service_id_pedido ?: 'N/A') . "</li>";
                echo "<li><strong>Username:</strong> {$pedido_plugin->instagram_username}</li>";
                echo "<li><strong>Quantidade:</strong> {$pedido_plugin->quantidade_variacao}</li>";
                echo "<li><strong>Produto:</strong> {$pedido_plugin->produto_nome}</li>";
                echo "<li><strong>Cliente:</strong> {$pedido_plugin->cliente_nome}</li>";
                echo "</ul>";
                
                // Verificar se o Service ID foi salvo corretamente
                if (!empty($pedido_plugin->service_id_pedido)) {
                    echo "<p>✅ Service ID salvo corretamente: {$pedido_plugin->service_id_pedido}</p>";
                } else {
                    echo "<p>⚠️ Service ID não foi salvo</p>";
                }
                
                // Verificar se o username foi salvo corretamente
                if (!empty($pedido_plugin->instagram_username)) {
                    echo "<p>✅ Username Instagram salvo corretamente: {$pedido_plugin->instagram_username}</p>";
                } else {
                    echo "<p>⚠️ Username Instagram não foi salvo</p>";
                }
                
            } else {
                echo "<p>❌ Pedido NÃO encontrado na tabela do plugin</p>";
                
                // Verificar se há outros pedidos na tabela
                $total_pedidos = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                echo "<p>📊 Total de pedidos na tabela: {$total_pedidos}</p>";
                
                if ($total_pedidos > 0) {
                    $ultimo_pedido = $wpdb->get_row("SELECT * FROM $table_name ORDER BY id DESC LIMIT 1");
                    echo "<p>📋 Último pedido na tabela:</p>";
                    echo "<ul>";
                    echo "<li><strong>ID Plugin:</strong> {$ultimo_pedido->id}</li>";
                    echo "<li><strong>Order ID:</strong> {$ultimo_pedido->order_id}</li>";
                    echo "<li><strong>Status API:</strong> {$ultimo_pedido->status_api}</li>";
                    echo "<li><strong>Service ID:</strong> " . ($ultimo_pedido->service_id_pedido ?: 'N/A') . "</li>";
                    echo "<li><strong>Username:</strong> {$ultimo_pedido->instagram_username}</li>";
                    echo "</ul>";
                }
            }
        } else {
            echo "<p>❌ Tabela do plugin não existe</p>";
        }
        
        // Verificar logs do plugin
        echo "<hr><h3>📝 Verificando Logs</h3>";
        
        $log_file = plugin_dir_path(__FILE__) . 'debug-pedidos-plugin.log';
        if (file_exists($log_file)) {
            echo "<p>✅ Arquivo de log encontrado</p>";
            
            // Buscar logs relacionados ao pedido
            $log_content = file_get_contents($log_file);
            $log_lines = explode("\n", $log_content);
            
            // Filtrar linhas relacionadas ao pedido
            $pedido_logs = [];
            foreach ($log_lines as $line) {
                if (strpos($line, $order_id) !== false || 
                    strpos($line, 'PROCESSAMENTO_AUTOMATICO') !== false ||
                    strpos($line, 'ENVIAR_PEDIDO_API') !== false) {
                    $pedido_logs[] = $line;
                }
            }
            
            if (!empty($pedido_logs)) {
                echo "<p>📋 Logs relacionados ao pedido {$order_id}:</p>";
                echo "<div style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd; max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 12px;'>";
                foreach ($pedido_logs as $line) {
                    if (!empty(trim($line))) {
                        echo htmlspecialchars($line) . "<br>";
                    }
                }
                echo "</div>";
            } else {
                echo "<p>⚠️ Nenhum log encontrado para o pedido {$order_id}</p>";
                
                // Mostrar últimas linhas do log
                $recent_logs = array_slice($log_lines, -10);
                echo "<p>📋 Últimas 10 linhas do log:</p>";
                echo "<div style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd; max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 12px;'>";
                foreach ($recent_logs as $line) {
                    if (!empty(trim($line))) {
                        echo htmlspecialchars($line) . "<br>";
                    }
                }
                echo "</div>";
            }
        } else {
            echo "<p>⚠️ Arquivo de log não encontrado</p>";
        }
        
    } else {
        echo "<p>❌ Não foi possível criar o pedido - produto não encontrado</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erro ao criar pedido: " . $e->getMessage() . "</p>";
    echo "<p>📋 Stack trace:</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p><a href='../pedidos-processando.php'>🔙 Voltar ao Plugin</a></p>";
echo "<p><a href='teste-hooks-simples.php'>🔄 Executar Teste de Hooks</a></p>";
?>

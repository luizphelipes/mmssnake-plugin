<?php
/**
 * Teste Completo do Plugin
 */

// Carregar WordPress
require_once('../../../wp-load.php');

echo "<h2>🧪 Teste Completo do Plugin</h2>";

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

// Verificar se o módulo SMM está ativo
if (!class_exists('SMMModule')) {
    die('❌ Módulo SMM não está ativo');
}
echo "<p>✅ Módulo SMM ativo</p>";

// Verificar se o gerenciador de provedores está ativo
if (!class_exists('SMMProvidersManager')) {
    die('❌ Gerenciador de Provedores SMM não está ativo');
}
echo "<p>✅ Gerenciador de Provedores ativo</p>";

echo "<hr><h3>📊 Testando Funções SMM</h3>";

// Testar função que estava causando erro
try {
    $stats = SMMProvidersManager::get_providers_stats();
    echo "<p>✅ Função get_providers_stats() funcionou!</p>";
    echo "<p>📈 Estatísticas: Total: {$stats['total']}, Ativos: {$stats['active']}, Inativos: {$stats['inactive']}</p>";
} catch (Exception $e) {
    echo "<p>❌ Erro na função get_providers_stats(): " . $e->getMessage() . "</p>";
}

// Testar outras funções
try {
    $providers = SMMProvidersManager::get_all_providers();
    echo "<p>✅ Função get_all_providers() funcionou!</p>";
    echo "<p>🔧 Provedores encontrados: " . count($providers) . "</p>";
    
    if (!empty($providers)) {
        echo "<p>📋 Lista de provedores:</p><ul>";
        foreach ($providers as $id => $provider) {
            echo "<li><strong>{$provider['name']}</strong> (ID: {$id})</li>";
        }
        echo "</ul>";
    }
} catch (Exception $e) {
    echo "<p>❌ Erro na função get_all_providers(): " . $e->getMessage() . "</p>";
}

echo "<hr><h3>🛒 Testando Criação de Pedido</h3>";

try {
    // Criar um produto de teste se não existir
    $produto_teste = get_page_by_title('Produto Teste SMM', OBJECT, 'product');
    if (!$produto_teste) {
        echo "<p>🔨 Criando produto de teste...</p>";
        
        $produto_id = wp_insert_post([
            'post_title' => 'Produto Teste SMM',
            'post_type' => 'product',
            'post_status' => 'publish',
            'meta_input' => [
                '_smm_service_id' => '4420', // Service ID de teste
                '_regular_price' => '10.00',
                '_price' => '10.00'
            ]
        ]);
        
        if ($produto_id) {
            echo "<p>✅ Produto criado com ID: {$produto_id}</p>";
            $produto_teste = get_post($produto_id);
        } else {
            echo "<p>❌ Erro ao criar produto</p>";
        }
    } else {
        echo "<p>✅ Produto de teste já existe (ID: {$produto_teste->ID})</p>";
    }
    
    if ($produto_teste) {
        // Criar um pedido de teste
        echo "<p>🛒 Criando pedido de teste...</p>";
        $order = wc_create_order();
        
        // Adicionar produto ao pedido
        $order->add_product(wc_get_product($produto_teste->ID), 1);
        echo "<p>✅ Produto adicionado ao pedido</p>";
        
        // Definir endereço básico
        $order->set_address([
            'first_name' => 'Teste',
            'last_name' => 'Plugin',
            'email' => 'teste@plugin.com',
            'phone' => '11999999999'
        ], 'billing');
        
        // Salvar o pedido como pending primeiro
        $order->save();
        $order_id = $order->get_id();
        echo "<p>✅ Pedido criado com ID: {$order_id}</p>";
        echo "<p>📊 Status inicial: {$order->get_status()}</p>";
        
        // Mudar status para processing para triggerar os hooks
        $order->set_status('processing');
        $order->save();
        echo "<p>✅ Status alterado para: {$order->get_status()}</p>";
        
        // Aguardar um pouco para o processamento
        sleep(2);
        
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
                echo "<li><strong>Data Criação:</strong> {$pedido_plugin->data_criacao}</li>";
                echo "</ul>";
                
                // Verificar se o Service ID foi salvo corretamente
                if (!empty($pedido_plugin->service_id_pedido)) {
                    echo "<p>✅ Service ID salvo corretamente: {$pedido_plugin->service_id_pedido}</p>";
                } else {
                    echo "<p>⚠️ Service ID não foi salvo</p>";
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
            $log_content = file_get_contents($log_file);
            $log_lines = explode("\n", $log_content);
            $recent_logs = array_slice($log_lines, -10); // Últimas 10 linhas
            
            echo "<p>📋 Últimas 10 linhas do log:</p>";
            echo "<div style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd; max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 12px;'>";
            foreach ($recent_logs as $line) {
                if (!empty(trim($line))) {
                    echo htmlspecialchars($line) . "<br>";
                }
            }
            echo "</div>";
        } else {
            echo "<p>⚠️ Arquivo de log não encontrado</p>";
        }
        
    } else {
        echo "<p>❌ Não foi possível criar ou obter o produto de teste</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erro ao criar pedido: " . $e->getMessage() . "</p>";
    echo "<p>📋 Stack trace:</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr><h3>🔧 Verificando Configurações</h3>";

// Verificar se a coluna service_id_pedido existe
global $wpdb;
$table_name = $wpdb->prefix . 'pedidos_processados';

if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
    $has_service_id_column = false;
    
    foreach ($columns as $column) {
        if ($column->Field === 'service_id_pedido') {
            $has_service_id_column = true;
            break;
        }
    }
    
    if ($has_service_id_column) {
        echo "<p>✅ Coluna 'service_id_pedido' existe na tabela</p>";
    } else {
        echo "<p>❌ Coluna 'service_id_pedido' NÃO existe na tabela</p>";
        echo "<p>💡 Execute o SQL: <code>ALTER TABLE `{$table_name}` ADD COLUMN `service_id_pedido` VARCHAR(50) NULL AFTER `instagram_username`;</code></p>";
    }
}

echo "<hr>";
echo "<p><a href='../pedidos-processando.php'>🔙 Voltar ao Plugin</a></p>";
echo "<p><a href='teste-erro-corrigido.php'>🔄 Executar Teste Simples</a></p>";
?>

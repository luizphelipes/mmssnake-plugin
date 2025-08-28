<?php
/**
 * Teste de Processamento Direto - Chamar Função do Plugin
 */

// Carregar WordPress
require_once('../../../wp-load.php');

echo "<h2>🧪 Teste de Processamento Direto</h2>";

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

echo "<hr><h3>🔍 Verificando Produto #62</h3>";

$produto_id = 62;
$produto = wc_get_product($produto_id);

if ($produto) {
    echo "<p>✅ Produto encontrado: <strong>{$produto->get_name()}</strong> (ID: {$produto_id})</p>";
    
    // Verificar Service ID do produto
    $service_id_produto = $produto->get_meta('_smm_service_id');
    echo "<p>🔧 Service ID configurado no produto: <strong>" . ($service_id_produto ?: 'NÃO CONFIGURADO') . "</strong></p>";
    
    if (empty($service_id_produto)) {
        echo "<p>❌ <strong>PROBLEMA IDENTIFICADO:</strong> Produto não tem Service ID!</p>";
        die();
    }
    
} else {
    echo "<p>❌ Produto #62 não encontrado</p>";
    die();
}

echo "<hr><h3>🧪 Testando Processamento Direto</h3>";

// Criar um pedido de teste
echo "<p>📝 Criando pedido de teste...</p>";

try {
    // Criar pedido
    $order = wc_create_order();
    
    // Adicionar produto
    $order->add_product($produto, 1);
    
    // Adicionar meta do Instagram
    foreach ($order->get_items() as $item) {
        wc_add_order_item_meta($item->get_id(), 'Instagram', 'teste_direto', true);
        echo "<p>✅ Meta Instagram adicionada ao item</p>";
        break;
    }
    
    // Definir endereço
    $order->set_address([
        'first_name' => 'Teste',
        'last_name' => 'Direto',
        'email' => 'teste@example.com'
    ], 'billing');
    
    // Definir status
    $order->set_status('pending');
    
    // Salvar pedido
    $order->save();
    
    echo "<p>✅ Pedido criado com ID: <strong>{$order->get_id()}</strong></p>";
    
    // AGORA VOU CHAMAR DIRETAMENTE A FUNÇÃO DO PLUGIN
    echo "<hr><h3>🔧 Processamento Direto do Plugin</h3>";
    
    // Buscar instância do plugin
    global $wp_filter;
    $plugin_instancia = null;
    
    foreach ($wp_filter as $hook_name => $hook_obj) {
        if (isset($hook_obj->callbacks)) {
            foreach ($hook_obj->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    if (is_array($callback['function']) && is_object($callback['function'][0])) {
                        $class_name = get_class($callback['function'][0]);
                        if ($class_name === 'PedidosProcessandoPlugin') {
                            $plugin_instancia = $callback['function'][0];
                            break 3;
                        }
                    }
                }
            }
        }
    }
    
    if ($plugin_instancia) {
        echo "<p>✅ Instância do plugin encontrada</p>";
        
        // Chamar diretamente a função de processamento
        echo "<p>📝 Chamando <code>processar_pedido_automaticamente</code> diretamente...</p>";
        
        $plugin_instancia->processar_pedido_automaticamente($order->get_id());
        
        echo "<p>✅ Função chamada com sucesso</p>";
        
        // Aguardar um pouco
        echo "<p>⏳ Aguardando processamento...</p>";
        sleep(2);
        
        // Verificar se o pedido foi inserido na tabela
        echo "<hr><h3>🔍 Verificando Inserção na Tabela</h3>";
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'pedidos_processados';
        
        $pedido_inserido = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE order_id = %d",
            $order->get_id()
        ));
        
        if ($pedido_inserido) {
            echo "<p>🎉 <strong>SUCESSO!</strong> Pedido encontrado na tabela do plugin!</p>";
            echo "<div style='background: #f5f5f5; padding: 15px; border: 1px solid #ddd; margin: 10px 0;'>";
            echo "<h4>📋 Detalhes do Pedido Inserido</h4>";
            echo "<table style='width: 100%; border-collapse: collapse;'>";
            echo "<tr><td style='padding: 5px; font-weight: bold;'>Order ID:</td><td>{$pedido_inserido->order_id}</td></tr>";
            echo "<tr><td style='padding: 5px; font-weight: bold;'>Produto ID:</td><td>{$pedido_inserido->produto_id}</td></tr>";
            echo "<tr><td style='padding: 5px; font-weight: bold;'>Instagram Username:</td><td>{$pedido_inserido->instagram_username}</td></tr>";
            echo "<tr><td style='padding: 5px; font-weight: bold;'>Service ID Pedido:</td><td style='background: " . (empty($pedido_inserido->service_id_pedido) ? '#ffebee' : '#e8f5e8') . ";'>" . ($pedido_inserido->service_id_pedido ?: 'N/A') . "</td></tr>";
            echo "<tr><td style='padding: 5px; font-weight: bold;'>Status API:</td><td>{$pedido_inserido->status_api}</td></tr>";
            echo "</table>";
            echo "</div>";
            
            if (empty($pedido_inserido->service_id_pedido)) {
                echo "<p>❌ <strong>PROBLEMA CONFIRMADO:</strong> Service ID não foi salvo no pedido!</p>";
                echo "<p>💡 Agora vou verificar os logs para entender o que aconteceu na função...</p>";
                
                // Verificar logs
                $log_file = plugin_dir_path(__FILE__) . 'debug-pedidos-plugin.log';
                if (file_exists($log_file)) {
                    echo "<hr><h3>📝 Últimas Linhas do Log</h3>";
                    $log_content = file_get_contents($log_file);
                    $log_lines = explode("\n", $log_content);
                    $recent_logs = array_slice($log_lines, -40); // Últimas 40 linhas
                    
                    echo "<div style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd; max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 11px;'>";
                    foreach ($recent_logs as $line) {
                        if (!empty(trim($line))) {
                            echo htmlspecialchars($line) . "<br>";
                        }
                    }
                    echo "</div>";
                }
                
            } else {
                echo "<p>🎉 <strong>SUCESSO TOTAL!</strong> Service ID foi salvo corretamente!</p>";
                echo "<p>🚀 O plugin está funcionando perfeitamente!</p>";
            }
            
        } else {
            echo "<p>❌ Pedido NÃO foi inserido na tabela do plugin!</p>";
            echo "<p>💡 Isso indica um problema na função <code>enviar_pedido_para_api</code></p>";
        }
        
    } else {
        echo "<p>❌ Instância do plugin não encontrada</p>";
    }
    
    // Limpar pedido de teste
    $order->delete(true);
    echo "<p>🧹 Pedido de teste removido</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Erro ao criar pedido de teste: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='../pedidos-processando.php'>🔙 Voltar ao Plugin</a></p>";
echo "<p><strong>💡 DIAGNÓSTICO:</strong></p>";
echo "<ol>";
echo "<li>✅ Produto verificado e configurado</li>";
echo "<li>🧪 Pedido de teste criado</li>";
echo "<li>🔧 Processamento direto do plugin</li>";
echo "<li>🔍 Verificação da inserção na tabela</li>";
echo "<li>📝 Análise dos logs para identificar o problema</li>";
echo "</ol>";
?>

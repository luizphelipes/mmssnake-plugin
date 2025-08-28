<?php
/**
 * Teste de Novo Pedido - Verificar Service ID
 */

// Carregar WordPress
require_once('../../../wp-load.php');

echo "<h2>🧪 Teste de Novo Pedido - Service ID</h2>";

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
        echo "<p>💡 Vou configurar o Service ID agora...</p>";
        
        $resultado = update_post_meta($produto_id, '_smm_service_id', '4420');
        if ($resultado !== false) {
            echo "<p>✅ Service ID configurado: 4420</p>";
            
            // Verificar novamente
            $service_id_verificado = $produto->get_meta('_smm_service_id');
            echo "<p>🔄 Verificação: Service ID agora é <strong>{$service_id_verificado}</strong></p>";
            
            if ($service_id_verificado == '4420') {
                echo "<p>🎉 <strong>SUCESSO!</strong> Produto configurado!</p>";
            } else {
                echo "<p>❌ Erro: Service ID não foi salvo corretamente</p>";
            }
        } else {
            echo "<p>❌ Erro ao configurar Service ID</p>";
        }
    } else {
        echo "<p>✅ Produto já tem Service ID configurado</p>";
    }
    
} else {
    echo "<p>❌ Produto #62 não encontrado</p>";
    die();
}

echo "<hr><h3>🧪 Testando Criação de Pedido</h3>";

// Criar um pedido de teste
echo "<p>📝 Criando pedido de teste...</p>";

try {
    // Criar pedido
    $order = wc_create_order();
    
    // Adicionar produto
    $order->add_product($produto, 1);
    
    // Adicionar meta do Instagram
    foreach ($order->get_items() as $item) {
        wc_add_order_item_meta($item->get_id(), 'Instagram', 'teste_service_id', true);
        echo "<p>✅ Meta Instagram adicionada ao item</p>";
        break;
    }
    
    // Definir endereço
    $order->set_address([
        'first_name' => 'Teste',
        'last_name' => 'Service ID',
        'email' => 'teste@example.com'
    ], 'billing');
    
    // Salvar pedido
    $order->save();
    
    echo "<p>✅ Pedido criado com ID: <strong>{$order->get_id()}</strong></p>";
    
    // Verificar se o pedido foi inserido na tabela do plugin
    echo "<hr><h3>🔍 Verificando Inserção na Tabela</h3>";
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'pedidos_processados';
    
    $pedido_inserido = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE order_id = %d",
        $order->get_id()
    ));
    
    if ($pedido_inserido) {
        echo "<p>✅ Pedido encontrado na tabela do plugin!</p>";
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
            echo "<p>💡 Vou verificar os logs para entender o que aconteceu...</p>";
            
            // Verificar logs
            $log_file = plugin_dir_path(__FILE__) . 'debug-pedidos-plugin.log';
            if (file_exists($log_file)) {
                echo "<hr><h3>📝 Últimas Linhas do Log</h3>";
                $log_content = file_get_contents($log_file);
                $log_lines = explode("\n", $log_content);
                $recent_logs = array_slice($log_lines, -20); // Últimas 20 linhas
                
                echo "<div style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px;'>";
                foreach ($recent_logs as $line) {
                    if (!empty(trim($line))) {
                        echo htmlspecialchars($line) . "<br>";
                    }
                }
                echo "</div>";
            }
            
        } else {
            echo "<p>🎉 <strong>SUCESSO!</strong> Service ID foi salvo corretamente!</p>";
        }
        
    } else {
        echo "<p>❌ Pedido NÃO foi inserido na tabela do plugin!</p>";
        echo "<p>💡 Isso indica que a função <code>enviar_pedido_para_api</code> não foi executada.</p>";
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
echo "<li>🔍 Verificação da inserção na tabela</li>";
echo "<li>📝 Análise dos logs para identificar o problema</li>";
echo "</ol>";
?>

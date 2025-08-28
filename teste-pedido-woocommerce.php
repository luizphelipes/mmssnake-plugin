<?php
/**
 * Teste de Pedido WooCommerce
 * 
 * Este arquivo simula a criação de um pedido do WooCommerce
 * para testar se os hooks do plugin estão funcionando
 */

// Carregar WordPress
require_once('../../../wp-load.php');

// Verificar se o WooCommerce está ativo
if (!class_exists('WooCommerce')) {
    die('WooCommerce não está ativo');
}

// Verificar se o plugin está ativo
if (!class_exists('PedidosProcessandoPlugin')) {
    die('Plugin Pedidos em Processamento não está ativo');
}

echo "<h2>Teste de Pedido WooCommerce</h2>";

try {
    // Criar um produto de teste se não existir
    $produto_teste = get_page_by_title('Produto Teste SMM', OBJECT, 'product');
    
    if (!$produto_teste) {
        echo "<p>Criando produto de teste...</p>";
        
        $produto_id = wp_insert_post([
            'post_title' => 'Produto Teste SMM',
            'post_type' => 'product',
            'post_status' => 'publish',
            'meta_input' => [
                '_smm_service_id' => '4420',
                '_price' => '29.90',
                '_regular_price' => '29.90'
            ]
        ]);
        
        if (is_wp_error($produto_id)) {
            die('Erro ao criar produto: ' . $produto_id->get_error_message());
        }
        
        echo "<p>Produto criado com ID: {$produto_id}</p>";
    } else {
        $produto_id = $produto_teste->ID;
        echo "<p>Produto existente com ID: {$produto_id}</p>";
    }
    
    // Criar um pedido de teste
    echo "<p>Criando pedido de teste...</p>";
    
    $order = wc_create_order();
    
    // Adicionar produto ao pedido
    $order->add_product(wc_get_product($produto_id), 1);
    
    // Definir endereço de cobrança
    $order->set_address([
        'first_name' => 'Cliente',
        'last_name' => 'Teste',
        'email' => 'teste@exemplo.com',
        'phone' => '11999999999'
    ], 'billing');
    
    // Adicionar meta do Instagram
    $order->add_meta_data('Instagram', 'phelipesf');
    
    // Salvar o pedido primeiro com status pending
    $order->save();
    
    echo "<p>Pedido salvo com status: {$order->get_status()}</p>";
    
    // Agora mudar para processing para disparar o hook
    $order->set_status('processing');
    $order->save();
    
    $order_id = $order->get_id();
    
    echo "<p>Pedido criado com ID: {$order_id}</p>";
    echo "<p>Status: {$order->get_status()}</p>";
    
    // Verificar se foi inserido na tabela do plugin
    global $wpdb;
    $table_name = $wpdb->prefix . 'pedidos_processados';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
        $pedido_plugin = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE order_id = %d",
            $order_id
        ));
        
        if ($pedido_plugin) {
            echo "<p>✅ Pedido encontrado na tabela do plugin!</p>";
            echo "<p>Status API: {$pedido_plugin->status_api}</p>";
            echo "<p>Data: {$pedido_plugin->data_processamento}</p>";
        } else {
            echo "<p>❌ Pedido NÃO encontrado na tabela do plugin</p>";
        }
    } else {
        echo "<p>❌ Tabela do plugin não existe</p>";
    }
    
    // Verificar logs
    echo "<h3>Logs do Plugin:</h3>";
    $log_file = WP_CONTENT_DIR . '/debug-pedidos-plugin.log';
    if (file_exists($log_file)) {
        $logs = file_get_contents($log_file);
        $linhas = explode("\n", $logs);
        $ultimas_linhas = array_slice($linhas, -20);
        
        echo "<pre>";
        foreach ($ultimas_linhas as $linha) {
            if (strpos($linha, 'PROCESSAMENTO_AUTOMATICO') !== false || 
                strpos($linha, 'ENVIAR_PEDIDO_API') !== false ||
                strpos($linha, 'MUDANCA_STATUS') !== false) {
                echo htmlspecialchars($linha) . "\n";
            }
        }
        echo "</pre>";
    } else {
        echo "<p>Arquivo de log não encontrado</p>";
    }
    
} catch (Exception $e) {
    echo "<p>Erro: " . $e->getMessage() . "</p>";
}

echo "<p><a href='../pedidos-processando.php'>Voltar ao Plugin</a></p>";
?>

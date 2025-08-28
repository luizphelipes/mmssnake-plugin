<?php
/**
 * Teste Simples dos Hooks
 */

// Carregar WordPress
require_once('../../../wp-load.php');

echo "<h2>🔧 Teste Simples dos Hooks</h2>";

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

// Verificar hooks registrados
echo "<hr><h3>📋 Hooks Registrados</h3>";

global $wp_filter;

$hooks_to_check = [
    'woocommerce_checkout_order_processed',
    'woocommerce_order_status_changed',
    'woocommerce_new_order',
    'woocommerce_payment_complete'
];

foreach ($hooks_to_check as $hook) {
    if (isset($wp_filter[$hook])) {
        $callbacks = $wp_filter[$hook]->callbacks;
        echo "<p>✅ Hook <strong>{$hook}</strong> registrado com " . count($callbacks) . " callback(s)</p>";
        
        foreach ($callbacks as $priority => $priority_callbacks) {
            foreach ($priority_callbacks as $callback) {
                if (is_array($callback['function']) && is_object($callback['function'][0])) {
                    $class_name = get_class($callback['function'][0]);
                    $method_name = $callback['function'][1];
                    echo "<p>&nbsp;&nbsp;📌 Prioridade {$priority}: {$class_name}::{$method_name}</p>";
                } else {
                    echo "<p>&nbsp;&nbsp;📌 Prioridade {$priority}: " . gettype($callback['function']) . "</p>";
                }
            }
        }
    } else {
        echo "<p>❌ Hook <strong>{$hook}</strong> NÃO registrado</p>";
    }
}

// Verificar se a coluna service_id_pedido existe
echo "<hr><h3>🔧 Verificando Estrutura da Tabela</h3>";

global $wpdb;
$table_name = $wpdb->prefix . 'pedidos_processados';

if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
    echo "<p>✅ Tabela do plugin existe</p>";
    
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
    $has_service_id_column = false;
    
    echo "<p>📋 Colunas da tabela:</p><ul>";
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
        echo "<p>✅ Coluna 'service_id_pedido' existe</p>";
    } else {
        echo "<p>❌ Coluna 'service_id_pedido' NÃO existe</p>";
        echo "<p>💡 Execute o SQL: <code>ALTER TABLE `{$table_name}` ADD COLUMN `service_id_pedido` VARCHAR(50) NULL AFTER `instagram_username`;</code></p>";
    }
    
    // Verificar dados existentes
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
        echo "<li><strong>Data Criação:</strong> {$ultimo_pedido->data_criacao}</li>";
        echo "</ul>";
    }
    
} else {
    echo "<p>❌ Tabela do plugin não existe</p>";
}

// Testar criação de pedido simples
echo "<hr><h3>🛒 Testando Criação de Pedido</h3>";

try {
    // Criar um pedido de teste
    $order = wc_create_order();
    
    // Adicionar um produto simples
    $produto_teste = get_page_by_title('Produto Teste SMM', OBJECT, 'product');
    if ($produto_teste) {
        $order->add_product(wc_get_product($produto_teste->ID), 1);
        echo "<p>✅ Produto adicionado ao pedido</p>";
    } else {
        echo "<p>⚠️ Produto de teste não encontrado</p>";
    }
    
    // Definir endereço básico
    $order->set_address([
        'first_name' => 'Teste',
        'last_name' => 'Hooks',
        'email' => 'teste@hooks.com'
    ], 'billing');
    
    // Salvar o pedido
    $order->save();
    
    $order_id = $order->get_id();
    echo "<p>✅ Pedido criado com ID: {$order_id}</p>";
    echo "<p>📊 Status inicial: {$order->get_status()}</p>";
    
    // Verificar se foi inserido na tabela
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
        $pedido_plugin = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE order_id = %d",
            $order_id
        ));
        
        if ($pedido_plugin) {
            echo "<p>🎉 Pedido encontrado na tabela do plugin!</p>";
            echo "<p>📋 Service ID: " . ($pedido_plugin->service_id_pedido ?: 'N/A') . "</p>";
        } else {
            echo "<p>❌ Pedido NÃO encontrado na tabela do plugin</p>";
            echo "<p>💡 Isso indica que o hook não está funcionando</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erro ao criar pedido: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='../pedidos-processando.php'>🔙 Voltar ao Plugin</a></p>";
echo "<p><a href='teste-completo-plugin.php'>🔄 Executar Teste Completo</a></p>";
?>

<?php
/**
 * Teste Simples do Plugin
 */

// Carregar WordPress
require_once('../../../wp-load.php');

echo "<h2>Teste Simples do Plugin</h2>";

// Verificar se o WooCommerce está ativo
if (class_exists('WooCommerce')) {
    echo "<p>✅ WooCommerce está ativo</p>";
} else {
    echo "<p>❌ WooCommerce não está ativo</p>";
}

// Verificar se o plugin está ativo
if (class_exists('PedidosProcessandoPlugin')) {
    echo "<p>✅ Plugin Pedidos em Processamento está ativo</p>";
} else {
    echo "<p>❌ Plugin Pedidos em Processamento não está ativo</p>";
}

// Verificar se a tabela existe
global $wpdb;
$table_name = $wpdb->prefix . 'pedidos_processados';

if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
    echo "<p>✅ Tabela de pedidos existe</p>";
    
    // Contar pedidos
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    echo "<p>Total de pedidos na tabela: {$total}</p>";
    
    // Mostrar últimos pedidos
    $pedidos = $wpdb->get_results("SELECT * FROM $table_name ORDER BY data_processamento DESC LIMIT 5");
    if ($pedidos) {
        echo "<h3>Últimos 5 pedidos:</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Order ID</th><th>Status</th><th>Data</th></tr>";
        foreach ($pedidos as $pedido) {
            echo "<tr>";
            echo "<td>{$pedido->id}</td>";
            echo "<td>{$pedido->order_id}</td>";
            echo "<td>{$pedido->status_api}</td>";
            echo "<td>{$pedido->data_processamento}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Nenhum pedido na tabela</p>";
    }
} else {
    echo "<p>❌ Tabela de pedidos não existe</p>";
}

// Verificar hooks registrados
echo "<h3>Hooks WooCommerce registrados:</h3>";
$hooks = [
    'woocommerce_checkout_order_processed',
    'woocommerce_new_order',
    'woocommerce_payment_complete',
    'woocommerce_order_status_changed'
];

foreach ($hooks as $hook) {
    if (has_action($hook)) {
        echo "<p>✅ Hook '{$hook}' está registrado</p>";
    } else {
        echo "<p>❌ Hook '{$hook}' NÃO está registrado</p>";
    }
}

echo "<p><a href='../pedidos-processando.php'>Voltar ao Plugin</a></p>";
?>

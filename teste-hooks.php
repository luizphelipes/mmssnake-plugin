<?php
/**
 * Teste de Hooks do WooCommerce
 */

// Carregar WordPress
require_once('../../../wp-load.php');

echo "<h2>🔧 Teste de Hooks do WooCommerce</h2>";

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

echo "<hr><h3>🔍 Verificando Hooks Registrados</h3>";

// Verificar hooks registrados
global $wp_filter;

$hooks_importantes = [
    'woocommerce_checkout_order_processed',
    'woocommerce_new_order',
    'woocommerce_order_created',
    'woocommerce_order_status_changed',
    'woocommerce_payment_complete'
];

echo "<p>📋 Hooks importantes para o plugin:</p>";
foreach ($hooks_importantes as $hook) {
    if (isset($wp_filter[$hook])) {
        echo "<p>✅ <strong>{$hook}</strong> - Registrado com " . count($wp_filter[$hook]->callbacks) . " callbacks</p>";
        
        // Mostrar detalhes dos callbacks
        foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (is_array($callback['function']) && is_object($callback['function'][0])) {
                    $class_name = get_class($callback['function'][0]);
                    $method_name = $callback['function'][1];
                    echo "<p style='margin-left: 20px; color: #666;'>• Prioridade {$priority}: {$class_name}->{$method_name}</p>";
                }
            }
        }
    } else {
        echo "<p>❌ <strong>{$hook}</strong> - NÃO registrado</p>";
    }
}

echo "<hr><h3>🧪 Testando Hook woocommerce_order_created</h3>";

// Verificar se o hook woocommerce_order_created existe
if (isset($wp_filter['woocommerce_order_created'])) {
    echo "<p>✅ Hook <code>woocommerce_order_created</code> está registrado</p>";
    
    // Testar disparando o hook manualmente
    echo "<p>📝 Testando disparo manual do hook...</p>";
    
    // Simular um pedido
    $order_data = [
        'status' => 'pending',
        'customer_id' => 1
    ];
    
    // Disparar o hook manualmente
    do_action('woocommerce_order_created', 999999); // ID fictício
    
    echo "<p>✅ Hook disparado manualmente</p>";
    
} else {
    echo "<p>❌ Hook <code>woocommerce_order_created</code> NÃO está registrado</p>";
    echo "<p>💡 Isso explica por que os pedidos não estão sendo processados automaticamente!</p>";
}

echo "<hr><h3>🔍 Verificando Instância do Plugin</h3>";

// Verificar se a instância do plugin está ativa
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
    echo "<p>🔧 Classe: " . get_class($plugin_instancia) . "</p>";
    
    // Verificar se os métodos existem
    $metodos = ['processar_pedido_automaticamente', 'enviar_pedido_para_api'];
    foreach ($metodos as $metodo) {
        if (method_exists($plugin_instancia, $metodo)) {
            echo "<p>✅ Método <code>{$metodo}</code> existe</p>";
        } else {
            echo "<p>❌ Método <code>{$metodo}</code> NÃO existe</p>";
        }
    }
    
} else {
    echo "<p>❌ Instância do plugin NÃO encontrada</p>";
    echo "<p>💡 O plugin pode não ter sido inicializado corretamente</p>";
}

echo "<hr>";
echo "<p><a href='../pedidos-processando.php'>🔙 Voltar ao Plugin</a></p>";
echo "<p><strong>💡 DIAGNÓSTICO:</strong></p>";
echo "<ol>";
echo "<li>🔍 Verificação dos hooks registrados</li>";
echo "<li>🧪 Teste do hook woocommerce_order_created</li>";
echo "<li>🔧 Verificação da instância do plugin</li>";
echo "<li>📝 Identificação do problema</li>";
echo "</ol>";
?>

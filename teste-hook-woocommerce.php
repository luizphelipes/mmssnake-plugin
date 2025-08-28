<?php
/**
 * Teste do Hook WooCommerce
 */

// Carregar WordPress
require_once('../../../wp-load.php');

echo "<h2>Teste do Hook WooCommerce</h2>";

// Verificar se o WooCommerce está ativo
if (!class_exists('WooCommerce')) {
    die('WooCommerce não está ativo');
}

// Verificar se o plugin está ativo
if (!class_exists('PedidosProcessandoPlugin')) {
    die('Plugin Pedidos em Processamento não está ativo');
}

// Verificar se a tabela existe
global $wpdb;
$table_name = $wpdb->prefix . 'pedidos_processados';

if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
    die('Tabela de pedidos não existe');
}

echo "<p>✅ Tabela de pedidos existe</p>";

// Contar pedidos existentes
$total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
echo "<p>Total de pedidos na tabela: {$total}</p>";

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
        
        // Mostrar callbacks registrados
        $callbacks = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}options WHERE option_name = 'active_plugins'");
        if ($callbacks) {
            $active_plugins = maybe_unserialize($callbacks[0]->option_value);
            foreach ($active_plugins as $plugin) {
                if (strpos($plugin, 'pedidos-processando') !== false) {
                    echo "<p>  - Plugin ativo: {$plugin}</p>";
                }
            }
        }
    } else {
        echo "<p>❌ Hook '{$hook}' NÃO está registrado</p>";
    }
}

// Verificar se o plugin está sendo carregado
echo "<h3>Verificando carregamento do plugin:</h3>";
$plugin_file = 'pedidos-processando-plugin/pedidos-processando.php';
$active_plugins = get_option('active_plugins');
$is_active = in_array($plugin_file, $active_plugins);

if ($is_active) {
    echo "<p>✅ Plugin está ativo no WordPress</p>";
} else {
    echo "<p>❌ Plugin NÃO está ativo no WordPress</p>";
}

// Verificar se a classe foi instanciada
global $wp_filter;
if (isset($wp_filter['woocommerce_new_order'])) {
    echo "<p>✅ Hook woocommerce_new_order tem callbacks registrados</p>";
    foreach ($wp_filter['woocommerce_new_order']->callbacks as $priority => $callbacks) {
        foreach ($callbacks as $callback) {
            if (is_array($callback['function']) && is_object($callback['function'][0])) {
                $class_name = get_class($callback['function'][0]);
                echo "<p>  - Prioridade {$priority}: {$class_name}</p>";
            }
        }
    }
} else {
    echo "<p>❌ Hook woocommerce_new_order não tem callbacks</p>";
}

// Testar função do plugin diretamente
echo "<h3>Testando função do plugin diretamente...</h3>";

try {
    // Buscar instância do plugin
    global $wp_filter;
    $plugin_instance = null;
    
    if (isset($wp_filter['woocommerce_new_order'])) {
        foreach ($wp_filter['woocommerce_new_order']->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (is_array($callback['function']) && is_object($callback['function'][0])) {
                    $class_name = get_class($callback['function'][0]);
                    if ($class_name === 'PedidosProcessandoPlugin') {
                        $plugin_instance = $callback['function'][0];
                        break 2;
                    }
                }
            }
        }
    }
    
    if ($plugin_instance) {
        echo "<p>✅ Instância do plugin encontrada</p>";
        
        // Testar função processar_pedido_automaticamente
        if (method_exists($plugin_instance, 'processar_pedido_automaticamente')) {
            echo "<p>✅ Método processar_pedido_automaticamente existe</p>";
        } else {
            echo "<p>❌ Método processar_pedido_automaticamente NÃO existe</p>";
        }
        
        // Testar função enviar_pedido_para_api
        if (method_exists($plugin_instance, 'enviar_pedido_para_api')) {
            echo "<p>✅ Método enviar_pedido_para_api existe</p>";
        } else {
            echo "<p>❌ Método enviar_pedido_para_api NÃO existe</p>";
        }
        
    } else {
        echo "<p>❌ Instância do plugin NÃO encontrada</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erro ao testar plugin: " . $e->getMessage() . "</p>";
}

// Testar criação de pedido simples
echo "<h3>Testando criação de pedido...</h3>";

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
        'last_name' => 'Hook',
        'email' => 'teste@hook.com'
    ], 'billing');
    
    // Salvar o pedido
    $order->save();
    
    $order_id = $order->get_id();
    echo "<p>✅ Pedido criado com ID: {$order_id}</p>";
    echo "<p>Status inicial: {$order->get_status()}</p>";
    
    // Verificar se foi inserido na tabela
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
        echo "<p>Isso indica que o hook não está funcionando</p>";
    }
    
    // Verificar logs recentes
    echo "<h3>Logs recentes do plugin:</h3>";
    $log_file = WP_CONTENT_DIR . '/debug-pedidos-plugin.log';
    if (file_exists($log_file)) {
        $logs = file_get_contents($log_file);
        $linhas = explode("\n", $logs);
        $ultimas_linhas = array_slice($linhas, -30);
        
        echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 300px; overflow-y: auto;'>";
        foreach ($ultimas_linhas as $linha) {
            if (strpos($linha, 'PROCESSAMENTO_AUTOMATICO') !== false || 
                strpos($linha, 'ENVIAR_PEDIDO_API') !== false ||
                strpos($linha, 'MUDANCA_STATUS') !== false ||
                strpos($linha, 'HOOKS_REGISTRATION') !== false) {
                echo htmlspecialchars($linha) . "\n";
            }
        }
        echo "</pre>";
    } else {
        echo "<p>Arquivo de log não encontrado</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erro ao criar pedido: " . $e->getMessage() . "</p>";
}

echo "<p><a href='../pedidos-processando.php'>Voltar ao Plugin</a></p>";
?>

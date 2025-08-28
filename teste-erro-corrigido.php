
<?php
/**
 * Teste para verificar se o erro foi corrigido
 */

// Carregar WordPress
require_once('../../../wp-load.php');

echo "<h2>Teste de Correção do Erro</h2>";

// Verificar se o WooCommerce está ativo
if (!class_exists('WooCommerce')) {
    die('WooCommerce não está ativo');
}

// Verificar se o plugin está ativo
if (!class_exists('PedidosProcessandoPlugin')) {
    die('Plugin Pedidos em Processamento não está ativo');
}

// Verificar se o módulo SMM está ativo
if (!class_exists('SMMModule')) {
    die('Módulo SMM não está ativo');
}

// Verificar se o gerenciador de provedores está ativo
if (!class_exists('SMMProvidersManager')) {
    die('Gerenciador de Provedores SMM não está ativo');
}

echo "<p>✅ Todas as classes estão ativas</p>";

// Testar função que estava causando erro
try {
    $stats = SMMProvidersManager::get_providers_stats();
    echo "<p>✅ Função get_providers_stats() funcionou!</p>";
    echo "<p>Estatísticas: Total: {$stats['total']}, Ativos: {$stats['active']}, Inativos: {$stats['inactive']}</p>";
} catch (Exception $e) {
    echo "<p>❌ Erro na função get_providers_stats(): " . $e->getMessage() . "</p>";
}

// Testar outras funções
try {
    $providers = SMMProvidersManager::get_all_providers();
    echo "<p>✅ Função get_all_providers() funcionou!</p>";
    echo "<p>Provedores encontrados: " . count($providers) . "</p>";
} catch (Exception $e) {
    echo "<p>❌ Erro na função get_all_providers(): " . $e->getMessage() . "</p>";
}

// Testar criação de pedido
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
        'last_name' => 'Erro',
        'email' => 'teste@erro.com'
    ], 'billing');
    
    // Salvar o pedido
    $order->save();
    
    $order_id = $order->get_id();
    echo "<p>✅ Pedido criado com ID: {$order_id}</p>";
    echo "<p>Status: {$order->get_status()}</p>";
    
    // Verificar se foi inserido na tabela
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
            echo "<p>Service ID: " . ($pedido_plugin->service_id_pedido ?: 'N/A') . "</p>";
        } else {
            echo "<p>❌ Pedido NÃO encontrado na tabela do plugin</p>";
        }
    } else {
        echo "<p>❌ Tabela do plugin não existe</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erro ao criar pedido: " . $e->getMessage() . "</p>";
}

echo "<p><a href='../pedidos-processando.php'>Voltar ao Plugin</a></p>";
?>

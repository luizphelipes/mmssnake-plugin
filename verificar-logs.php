<?php
/**
 * Verificar Logs - Diagnóstico do Service ID
 */

// Carregar WordPress
require_once('../../../wp-load.php');

echo "<h2>📝 Verificação de Logs - Diagnóstico</h2>";

echo "<hr><h3>📋 Log do Plugin Principal</h3>";

$log_file = plugin_dir_path(__FILE__) . 'debug-pedidos-plugin.log';
if (file_exists($log_file)) {
    echo "<p>✅ Arquivo de log encontrado</p>";
    $log_size = filesize($log_file);
    echo "<p>📏 Tamanho: " . number_format($log_size / 1024, 2) . " KB</p>";
    
    // Mostrar últimas linhas do log
    $log_content = file_get_contents($log_file);
    $log_lines = explode("\n", $log_content);
    $recent_logs = array_slice($log_lines, -50); // Últimas 50 linhas
    
    echo "<p>📋 Últimas 50 linhas do log:</p>";
    echo "<div style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd; max-height: 500px; overflow-y: auto; font-family: monospace; font-size: 11px;'>";
    foreach ($recent_logs as $line) {
        if (!empty(trim($line))) {
            echo htmlspecialchars($line) . "<br>";
        }
    }
    echo "</div>";
} else {
    echo "<p>❌ Arquivo de log não encontrado</p>";
}

echo "<hr><h3>🔍 Verificar Pedidos na Tabela</h3>";

global $wpdb;
$table_name = $wpdb->prefix . 'pedidos_processados';

if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
    echo "<p>✅ Tabela do plugin existe</p>";
    
    // Contar pedidos
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    echo "<p>📊 Total de pedidos na tabela: <strong>{$total}</strong></p>";
    
    if ($total > 0) {
        // Mostrar pedidos mais recentes
        $pedidos_recentes = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY id DESC LIMIT 5"
        );
        
        echo "<p>📋 Pedidos mais recentes:</p>";
        echo "<div style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd; max-height: 400px; overflow-y: auto;'>";
        foreach ($pedidos_recentes as $pedido) {
            echo "<div style='border-bottom: 1px solid #ddd; padding: 10px; margin-bottom: 10px;'>";
            echo "<strong>Pedido #{$pedido->order_id}</strong> (ID Plugin: {$pedido->id})<br>";
            echo "Produto: {$pedido->produto_nome} (ID: {$pedido->produto_id})<br>";
            echo "Instagram: {$pedido->instagram_username}<br>";
            echo "Service ID: <span style='background: " . (empty($pedido->service_id_pedido) ? '#ffebee' : '#e8f5e8') . "; padding: 2px 6px; border-radius: 3px;'>" . ($pedido->service_id_pedido ?: 'N/A') . "</span><br>";
            echo "Status: {$pedido->status_api}<br>";
            echo "Data: {$pedido->data_processamento}<br>";
            echo "</div>";
        }
        echo "</div>";
    } else {
        echo "<p>⚠️ Nenhum pedido na tabela</p>";
    }
    
} else {
    echo "<p>❌ Tabela do plugin não existe</p>";
}

echo "<hr><h3>🔧 Verificar Produto #62</h3>";

$produto_id = 62;
$produto = wc_get_product($produto_id);

if ($produto) {
    echo "<p>✅ Produto encontrado: <strong>{$produto->get_name()}</strong></p>";
    
    $service_id_produto = $produto->get_meta('_smm_service_id');
    echo "<p>🔧 Service ID no produto: <strong>" . ($service_id_produto ?: 'NÃO CONFIGURADO') . "</strong></p>";
    
    if (empty($service_id_produto)) {
        echo "<p>❌ <strong>PROBLEMA:</strong> Produto não tem Service ID!</p>";
    } else {
        echo "<p>✅ Produto tem Service ID configurado</p>";
    }
    
} else {
    echo "<p>❌ Produto #62 não encontrado</p>";
}

echo "<hr>";
echo "<p><a href='../pedidos-processando.php'>🔙 Voltar ao Plugin</a></p>";
echo "<p><strong>💡 ANÁLISE:</strong></p>";
echo "<ol>";
echo "<li>📝 Verificação dos logs para identificar erros</li>";
echo "<li>🔍 Verificação dos pedidos na tabela</li>";
echo "<li>🔧 Verificação do produto e Service ID</li>";
echo "<li>📋 Diagnóstico completo do problema</li>";
echo "</ol>";
?>

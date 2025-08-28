<?php
/**
 * Teste do Botão de Debug
 */

// Carregar WordPress
require_once('../../../wp-load.php');

echo "<h2>🔧 Teste do Botão de Debug</h2>";

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

echo "<hr><h3>📋 Verificando Pedidos na Tabela</h3>";

global $wpdb;
$table_name = $wpdb->prefix . 'pedidos_processados';

if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
    echo "<p>✅ Tabela do plugin existe</p>";
    
    // Contar pedidos por status
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $pending = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status_api = 'pending'");
    $success = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status_api = 'success'");
    $error = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status_api = 'error'");
    $processing = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status_api = 'processing'");
    
    echo "<p>📊 Estatísticas dos pedidos:</p>";
    echo "<ul>";
    echo "<li><strong>Total:</strong> {$total}</li>";
    echo "<li><strong>Pending:</strong> {$pending}</li>";
    echo "<li><strong>Processing:</strong> {$processing}</li>";
    echo "<li><strong>Success:</strong> {$success}</li>";
    echo "<li><strong>Error:</strong> {$error}</li>";
    echo "</ul>";
    
    if ($pending > 0) {
        echo "<p>🎯 <strong>Há {$pending} pedidos pendentes para processar!</strong></p>";
        echo "<p>💡 Use o botão 'Processar Pedidos Pendentes' na página admin para processá-los.</p>";
        
        // Mostrar detalhes dos pedidos pendentes
        $pedidos_pendentes = $wpdb->get_results(
            "SELECT * FROM $table_name 
             WHERE status_api = 'pending' 
             ORDER BY data_processamento ASC 
             LIMIT 5"
        );
        
        echo "<p>📋 Detalhes dos primeiros 5 pedidos pendentes:</p>";
        echo "<div style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd; max-height: 300px; overflow-y: auto;'>";
        foreach ($pedidos_pendentes as $pedido) {
            echo "<div style='border-bottom: 1px solid #ddd; padding: 10px; margin-bottom: 10px;'>";
            echo "<strong>Pedido #{$pedido->order_id}</strong><br>";
            echo "Produto: {$pedido->produto_nome}<br>";
            echo "Username: {$pedido->instagram_username}<br>";
            echo "Service ID: " . ($pedido->service_id_pedido ?: 'N/A') . "<br>";
            echo "Quantidade: {$pedido->quantidade_variacao}<br>";
            echo "Tentativas: {$pedido->tentativas}<br>";
            echo "Data: {$pedido->data_processamento}<br>";
            if (!empty($pedido->mensagem_api)) {
                echo "Mensagem: {$pedido->mensagem_api}<br>";
            }
            echo "</div>";
        }
        echo "</div>";
        
    } else {
        echo "<p>✅ Não há pedidos pendentes para processar.</p>";
    }
    
} else {
    echo "<p>❌ Tabela do plugin não existe</p>";
}

echo "<hr><h3>🔧 Verificando Configurações</h3>";

// Verificar se a coluna service_id_pedido existe
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

// Verificar logs
echo "<hr><h3>📝 Verificando Logs</h3>";

$log_file = plugin_dir_path(__FILE__) . 'debug-pedidos-plugin.log';
if (file_exists($log_file)) {
    echo "<p>✅ Arquivo de log encontrado</p>";
    $log_size = filesize($log_file);
    echo "<p>📏 Tamanho do log: " . number_format($log_size / 1024, 2) . " KB</p>";
    
    // Mostrar últimas linhas do log
    $log_content = file_get_contents($log_file);
    $log_lines = explode("\n", $log_content);
    $recent_logs = array_slice($log_lines, -15); // Últimas 15 linhas
    
    echo "<p>📋 Últimas 15 linhas do log:</p>";
    echo "<div style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px;'>";
    foreach ($recent_logs as $line) {
        if (!empty(trim($line))) {
            echo htmlspecialchars($line) . "<br>";
        }
    }
    echo "</div>";
} else {
    echo "<p>⚠️ Arquivo de log não encontrado</p>";
}

// Verificar log da API SMM
$log_api_file = plugin_dir_path(__FILE__) . 'debug-api-smm.log';
if (file_exists($log_api_file)) {
    echo "<p>✅ Arquivo de log da API SMM encontrado</p>";
    $log_api_size = filesize($log_api_file);
    echo "<p>📏 Tamanho do log da API: " . number_format($log_api_size / 1024, 2) . " KB</p>";
    
    // Mostrar últimas linhas do log da API
    $log_api_content = file_get_contents($log_api_file);
    $log_api_lines = explode("\n", $log_api_content);
    $recent_api_logs = array_slice($log_api_lines, -10); // Últimas 10 linhas
    
    echo "<p>📋 Últimas 10 linhas do log da API SMM:</p>";
    echo "<div style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd; max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 12px;'>";
    foreach ($recent_api_logs as $line) {
        if (!empty(trim($line))) {
            echo htmlspecialchars($line) . "<br>";
        }
    }
    echo "</div>";
} else {
    echo "<p>⚠️ Arquivo de log da API SMM não encontrado</p>";
}

echo "<hr>";
echo "<p><a href='../pedidos-processando.php'>🔙 Voltar ao Plugin</a></p>";
echo "<p><strong>💡 INSTRUÇÕES:</strong></p>";
echo "<ol>";
echo "<li>Acesse a página admin do plugin</li>";
echo "<li>Clique no botão <strong>'Processar Pedidos Pendentes'</strong> (botão vermelho)</li>";
echo "<li>Confirme a ação</li>";
echo "<li>Aguarde o processamento</li>";
echo "<li>Verifique os logs em <code>debug-api-smm.log</code></li>";
echo "</ol>";
?>

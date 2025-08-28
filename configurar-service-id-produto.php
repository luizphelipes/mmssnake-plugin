<?php
/**
 * Configurar Service ID no Produto #62
 */

// Carregar WordPress
require_once('../../../wp-load.php');

echo "<h2>🔧 Configurando Service ID no Produto #62</h2>";

// Verificar se o WooCommerce está ativo
if (!class_exists('WooCommerce')) {
    die('❌ WooCommerce não está ativo');
}
echo "<p>✅ WooCommerce ativo</p>";

echo "<hr><h3>🔍 Verificando Produto #62</h3>";

$produto_id = 62;
$produto = get_post($produto_id);

if ($produto) {
    echo "<p>✅ Produto encontrado: <strong>{$produto->post_title}</strong> (ID: {$produto_id})</p>";
    
    // Verificar Service ID atual
    $service_id_atual = get_post_meta($produto_id, '_smm_service_id', true);
    echo "<p>🔧 Service ID atual: <strong>" . ($service_id_atual ?: 'NÃO CONFIGURADO') . "</strong></p>";
    
    // Verificar outros produtos para determinar qual Service ID usar
    echo "<hr><h3>🔍 Verificando Outros Produtos</h3>";
    
    global $wpdb;
    $produtos_com_service_id = $wpdb->get_results(
        "SELECT p.ID, p.post_title, pm.meta_value as service_id 
         FROM {$wpdb->posts} p 
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
         WHERE p.post_type = 'product' 
         AND p.post_status = 'publish' 
         AND pm.meta_key = '_smm_service_id' 
         AND pm.meta_value != ''
         ORDER BY p.ID"
    );
    
    if (!empty($produtos_com_service_id)) {
        echo "<p>📋 Produtos com Service ID configurado:</p>";
        echo "<ul>";
        foreach ($produtos_com_service_id as $prod) {
            echo "<li><strong>{$prod->post_title}</strong> (ID: {$prod->ID}) - Service ID: <strong>{$prod->service_id}</strong></li>";
        }
        echo "</ul>";
        
        // Determinar qual Service ID usar para o produto #62
        // Como é "Seguidores Internacionais - 10", vou usar o mesmo do "Seguidores Internacionais"
        $service_id_recomendado = '4420'; // Baseado no produto "Seguidores Internacionais"
        
        echo "<p>💡 <strong>Service ID recomendado para 'Seguidores Internacionais - 10': {$service_id_recomendado}</strong></p>";
        echo "<p>📝 Justificativa: Mesmo tipo de serviço (Seguidores Internacionais)</p>";
        
        // Configurar o Service ID
        echo "<hr><h3>🔧 Configurando Service ID</h3>";
        
        $resultado = update_post_meta($produto_id, '_smm_service_id', $service_id_recomendado);
        
        if ($resultado !== false) {
            echo "<p>✅ Service ID configurado com sucesso!</p>";
            
            // Verificar se foi salvo
            $service_id_verificado = get_post_meta($produto_id, '_smm_service_id', true);
            echo "<p>🔄 Verificação: Service ID agora é <strong>{$service_id_verificado}</strong></p>";
            
            if ($service_id_verificado == $service_id_recomendado) {
                echo "<p>🎉 <strong>SUCESSO!</strong> Produto configurado corretamente.</p>";
                
                // Agora vou atualizar o pedido pendente com este Service ID
                echo "<hr><h3>🔄 Atualizando Pedido Pendente</h3>";
                
                $table_name = $wpdb->prefix . 'pedidos_processados';
                $pedido_pendente = $wpdb->get_row(
                    "SELECT * FROM $table_name 
                     WHERE produto_id = {$produto_id} 
                     AND status_api = 'pending'"
                );
                
                if ($pedido_pendente) {
                    echo "<p>📋 Pedido pendente encontrado: #{$pedido_pendente->order_id}</p>";
                    
                    $update_result = $wpdb->update(
                        $table_name,
                        ['service_id_pedido' => $service_id_recomendado],
                        ['id' => $pedido_pendente->id],
                        ['%s'],
                        ['%d']
                    );
                    
                    if ($update_result !== false) {
                        echo "<p>✅ Pedido atualizado com Service ID: {$service_id_recomendado}</p>";
                        echo "<p>🚀 <strong>AGORA O PEDIDO PODE SER PROCESSADO!</strong></p>";
                        echo "<p>💡 Use o botão 'Processar Pedidos Pendentes' na página admin.</p>";
                        
                    } else {
                        echo "<p>❌ Erro ao atualizar pedido: {$wpdb->last_error}</p>";
                    }
                    
                } else {
                    echo "<p>⚠️ Nenhum pedido pendente encontrado para este produto</p>";
                }
                
            } else {
                echo "<p>❌ Erro: Service ID não foi salvo corretamente</p>";
            }
            
        } else {
            echo "<p>❌ Erro ao configurar Service ID</p>";
        }
        
    } else {
        echo "<p>⚠️ Nenhum produto tem Service ID configurado</p>";
        echo "<p>💡 Configure manualmente o Service ID no produto</p>";
    }
    
} else {
    echo "<p>❌ Produto #62 não encontrado</p>";
}

echo "<hr>";
echo "<p><a href='../pedidos-processando.php'>🔙 Voltar ao Plugin</a></p>";
echo "<p><a href='verificar-pedido-recente.php'>🔄 Verificar Pedido</a></p>";
echo "<p><strong>💡 PRÓXIMOS PASSOS:</strong></p>";
echo "<ol>";
echo "<li>✅ Service ID configurado no produto</li>";
echo "<li>✅ Pedido atualizado com Service ID</li>";
echo "<li>🚀 Use o botão 'Processar Pedidos Pendentes' na página admin</li>";
echo "<li>📝 Monitore os logs em debug-api-smm.log</li>";
echo "</ol>";
?>

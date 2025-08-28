<?php
/**
 * Carregador do Módulo SMM
 * Este arquivo carrega todos os componentes do módulo SMM
 */

if (!defined('ABSPATH')) {
    exit;
}

// Carregar dependências do módulo SMM
$smm_files = [
    'smm-api.php',
    'providers-manager.php',
    'smm-module.php'
];

foreach ($smm_files as $file) {
    $file_path = plugin_dir_path(__FILE__) . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        add_action('admin_notices', function() use ($file) {
            echo '<div class="notice notice-error"><p>Erro: Arquivo SMM não encontrado: ' . esc_html($file) . '</p></div>';
        });
        return;
    }
}

// Verificar se o módulo principal foi carregado
if (!class_exists('SMMModule')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>Erro ao carregar o módulo SMM. Classe SMMModule não encontrada.</p></div>';
    });
    return;
}

// Verificar se outras classes necessárias foram carregadas
if (!class_exists('SMMProvidersManager')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>Erro ao carregar o módulo SMM. Classe SMMProvidersManager não encontrada.</p></div>';
    });
    return;
}

if (!class_exists('SMMApi')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>Erro ao carregar o módulo SMM. Classe SMMApi não encontrada.</p></div>';
    });
    return;
}

// Adicionar informações do módulo
add_action('admin_notices', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'smm-settings') {
        try {
            $stats = SMMProvidersManager::get_providers_stats();
            echo '<div class="notice notice-info">';
            echo '<p><strong>Módulo SMM:</strong> ' . $stats['total'] . ' provedor(es) configurado(s), ' . $stats['active'] . ' ativo(s).</p>';
            echo '</div>';
        } catch (Exception $e) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>Módulo SMM:</strong> Carregado com sucesso, mas ainda não configurado.</p>';
            echo '</div>';
        }
    }
});

// Adicionar link para configurações SMM no menu principal
add_filter('plugin_action_links_' . plugin_basename(dirname(dirname(__FILE__)) . '/pedidos-processando.php'), function($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=smm-settings') . '">Configurações SMM</a>';
    array_unshift($links, $settings_link);
    return $links;
});

// Adicionar informações SMM na página de produtos
add_action('woocommerce_product_options_general_product_data', function() {
    global $post;
    
    if (!$post) return;
    
    $smm_enabled = SMMModule::is_product_smm_enabled($post->ID);
    $smm_provider = SMMModule::get_product_provider($post->ID);
    $smm_service_id = SMMModule::get_product_service_id($post->ID);
    
    if ($smm_enabled) {
        $provider = SMMProvidersManager::get_provider($smm_provider);
        $provider_name = $provider ? $provider['name'] : 'Provedor não encontrado';
        
        echo '<div class="options_group">';
        echo '<h4 style="margin: 0 0 10px 0; color: #0073aa;">📡 Configurações SMM</h4>';
        echo '<p style="margin: 0; padding: 10px; background: #f9f9f9; border-left: 3px solid #0073aa;">';
        echo '<strong>Provedor:</strong> ' . esc_html($provider_name) . '<br>';
        echo '<strong>Service ID:</strong> ' . esc_html($smm_service_id) . '<br>';
        echo '<a href="' . admin_url('post.php?post=' . $post->ID . '&action=edit') . '#product_smm_settings" class="button button-small">Editar Configurações</a>';
        echo '</p>';
        echo '</div>';
    }
});

// Adicionar informações SMM no resumo do pedido
add_action('woocommerce_order_item_meta_end', function($item_id, $item, $order, $plain_text) {
    $smm_order_id = wc_get_order_item_meta($item_id, '_smm_order_id', true);
    $smm_status = wc_get_order_item_meta($item_id, '_smm_status', true);
    
    if ($smm_order_id) {
        echo '<br><small style="color: #0073aa;">';
        echo '📡 Pedido SMM: #' . esc_html($smm_order_id);
        if ($smm_status) {
            echo ' | Status: ' . esc_html($smm_status);
        }
        echo '</small>';
    }
}, 10, 4);

// Adicionar coluna SMM na lista de pedidos
add_filter('manage_edit-shop_order_columns', function($columns) {
    $columns['smm_status'] = 'Status SMM';
    return $columns;
});

add_action('manage_shop_order_posts_custom_column', function($column, $post_id) {
    if ($column === 'smm_status') {
        $smm_order_id = get_post_meta($post_id, '_smm_order_id', true);
        $smm_status = get_post_meta($post_id, '_smm_status', true);
        
        if ($smm_order_id) {
            echo '<span style="color: #0073aa; font-weight: bold;">📡 #' . esc_html($smm_order_id) . '</span><br>';
            echo '<small>Status: ' . esc_html($smm_status ?: 'N/A') . '</small>';
        } else {
            echo '<span style="color: #999;">—</span>';
        }
    }
}, 10, 2);

// Adicionar filtro por status SMM
add_action('restrict_manage_posts', function() {
    global $typenow;
    
    if ($typenow === 'shop_order') {
        $smm_statuses = [
            '' => 'Todos os Status SMM',
            'pending' => 'Pendente',
            'processing' => 'Processando',
            'completed' => 'Concluído',
            'cancelled' => 'Cancelado',
            'error' => 'Erro'
        ];
        
        $current_status = $_GET['smm_status'] ?? '';
        
        echo '<select name="smm_status">';
        foreach ($smm_statuses as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($current_status, $value, false) . '>';
            echo esc_html($label);
            echo '</option>';
        }
        echo '</select>';
    }
});

// Filtrar pedidos por status SMM
add_filter('parse_query', function($query) {
    global $pagenow, $typenow;
    
    if ($pagenow === 'edit.php' && $typenow === 'shop_order' && isset($_GET['smm_status']) && $_GET['smm_status'] !== '') {
        $smm_status = sanitize_text_field($_GET['smm_status']);
        
        $meta_query = [
            [
                'key' => '_smm_status',
                'value' => $smm_status,
                'compare' => '='
            ]
        ];
        
        $query->set('meta_query', $meta_query);
    }
});

// Adicionar informações SMM no dashboard
add_action('wp_dashboard_setup', function() {
    wp_add_dashboard_widget(
        'smm_dashboard_widget',
        '📡 Status SMM',
        function() {
            $stats = SMMProvidersManager::get_providers_stats();
            $providers = SMMProvidersManager::get_all_providers();
            
            echo '<div style="padding: 10px;">';
            echo '<h3>Provedores SMM</h3>';
            echo '<p><strong>Total:</strong> ' . $stats['total'] . ' | <strong>Ativos:</strong> ' . $stats['active'] . '</p>';
            
            if (!empty($providers)) {
                echo '<h4>Provedores Configurados:</h4>';
                echo '<ul>';
                foreach ($providers as $id => $provider) {
                    $status_color = ($provider['status'] === 'active') ? '#28a745' : '#dc3545';
                    echo '<li style="color: ' . $status_color . ';">';
                    echo '• ' . esc_html($provider['name']) . ' (' . esc_html($provider['status']) . ')';
                    echo '</li>';
                }
                echo '</ul>';
            } else {
                echo '<p style="color: #dc3545;">Nenhum provedor SMM configurado.</p>';
                echo '<p><a href="' . admin_url('admin.php?page=smm-settings') . '" class="button button-primary">Configurar Provedores</a></p>';
            }
            
            echo '</div>';
        }
    );
});

// Adicionar estilos CSS para o módulo SMM
add_action('admin_head', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'smm-settings') {
        ?>
        <style>
        .smm-provider-item {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .smm-provider-item h4 {
            margin: 0 0 15px 0;
            color: #0073aa;
        }
        
        .smm-provider-item p {
            margin: 5px 0;
        }
        
        .smm-provider-item .button {
            margin-right: 10px;
        }
        
        .smm-add-provider {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .smm-add-provider h4 {
            margin: 0 0 15px 0;
            color: #333;
        }
        
        .smm-test-section,
        .smm-balance-section {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .smm-test-section h2,
        .smm-balance-section h2 {
            margin: 0 0 15px 0;
            color: #333;
        }
        </style>
        <?php
    }
});

// Log de carregamento do módulo
add_action('admin_notices', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'smm-settings' && !isset($_GET['settings-updated'])) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>Módulo SMM carregado com sucesso!</strong> Configure seus provedores SMM abaixo.</p>';
        echo '</div>';
    }
});

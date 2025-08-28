<?php
/**
 * Módulo SMM - Sistema de Provedores SMM
 * Integração com provedores de serviços de mídia social
 */

if (!defined('ABSPATH')) {
    exit;
}

class SMMModule {
    
    private $providers = [];
    private $api_class = null;
    
    public function __construct() {
        // Verificar se estamos no admin
        if (!is_admin()) {
            return;
        }
        
        // Verificar se o WooCommerce está ativo (usar a mesma função do plugin principal)
        if (!function_exists('is_woocommerce_active')) {
            function is_woocommerce_active() {
                // Verificar se o WooCommerce está ativo como plugin
                if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
                    return true;
                }
                
                // Verificar se o WooCommerce está ativo como plugin de rede (multisite)
                if (is_multisite() && in_array('woocommerce/woocommerce.php', get_site_option('active_sitewide_plugins'))) {
                    return true;
                }
                
                // Verificar se a classe WooCommerce existe
                if (class_exists('WooCommerce')) {
                    return true;
                }
                
                return false;
            }
        }
        
        if (!is_woocommerce_active()) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>O módulo SMM requer o WooCommerce ativo.</p></div>';
            });
            return;
        }
        
        add_action('admin_menu', [$this, 'add_smm_menu']);
        add_action('admin_init', [$this, 'register_smm_settings']);
        add_action('add_meta_boxes', [$this, 'add_product_smm_meta_box']);
        add_action('save_post', [$this, 'save_product_smm_meta_box']);
        add_action('wp_ajax_test_smm_provider', [$this, 'ajax_test_smm_provider']);
        add_action('wp_ajax_get_smm_services', [$this, 'ajax_get_smm_services']);
        add_action('wp_ajax_send_smm_order', [$this, 'ajax_send_smm_order']);
        
        // Carregar provedores configurados
        $this->load_providers();
    }
    
    /**
     * Adicionar menu SMM no admin
     */
    public function add_smm_menu() {
        // Verificar se o menu principal existe
        global $submenu;
        
        if (!isset($submenu['pedidos-processando'])) {
            // Se o menu principal não existir, criar como menu independente
            add_menu_page(
                'Configurações SMM',
                'Configurações SMM',
                'manage_woocommerce',
                'smm-settings',
                [$this, 'render_smm_settings_page'],
                'dashicons-share',
                57
            );
        } else {
            // Adicionar como submenu do plugin principal
            add_submenu_page(
                'pedidos-processando',
                'Configurações SMM',
                'Configurações SMM',
                'manage_woocommerce',
                'smm-settings',
                [$this, 'render_smm_settings_page']
            );
        }
    }
    
    /**
     * Registrar configurações SMM
     */
    public function register_smm_settings() {
        register_setting('smm_options', 'smm_providers');
        register_setting('smm_options', 'smm_default_provider');
        
        add_settings_section('smm_providers_section', 'Provedores SMM', [$this, 'render_providers_section'], 'smm-settings');
        add_settings_field('smm_providers_field', 'Provedores', [$this, 'render_providers_field'], 'smm-settings', 'smm_providers_section');
        add_settings_field('smm_default_provider_field', 'Provedor Padrão', [$this, 'render_default_provider_field'], 'smm-settings', 'smm_providers_section');
    }
    
    /**
     * Renderizar página de configurações SMM
     */
    public function render_smm_settings_page() {
        ?>
        <div class="wrap">
            <h1>Configurações SMM</h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('smm_options');
                do_settings_sections('smm-settings');
                submit_button('Salvar Configurações');
                ?>
            </form>
            
            <div class="smm-test-section">
                <h2>Testar Provedores</h2>
                <div id="smm-test-results"></div>
            </div>
            
            <div class="smm-balance-section">
                <h2>Saldo dos Provedores</h2>
                <div id="smm-balance-results"></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Testar provedor
            $('.test-provider').on('click', function() {
                const providerId = $(this).data('provider-id');
                testProvider(providerId);
            });
            
            // Verificar saldo
            $('.check-balance').on('click', function() {
                const providerId = $(this).data('provider-id');
                checkBalance(providerId);
            });
            
            function testProvider(providerId) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'test_smm_provider',
                        provider_id: providerId,
                        nonce: '<?php echo wp_create_nonce('smm_test_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#smm-test-results').html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                        } else {
                            $('#smm-test-results').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                        }
                    }
                });
            }
            
            function checkBalance(providerId) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'check_smm_balance',
                        provider_id: providerId,
                        nonce: '<?php echo wp_create_nonce('smm_balance_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#smm-balance-results').html('<div class="notice notice-success"><p>Saldo: ' + response.data + '</p></div>');
                        } else {
                            $('#smm-balance-results').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                        }
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * Renderizar seção de provedores
     */
    public function render_providers_section() {
        echo '<p>Configure os provedores SMM que serão utilizados para envio de pedidos.</p>';
    }
    
    /**
     * Renderizar campo de provedores
     */
    public function render_providers_field() {
        $providers = get_option('smm_providers', []);
        
        // Garantir que $providers seja um array
        if (!is_array($providers)) {
            $providers = [];
        }
        ?>
        <div id="smm-providers-container">
            <?php if (!empty($providers)): ?>
                <?php foreach ($providers as $id => $provider): ?>
                    <div class="smm-provider-item" data-provider-id="<?php echo esc_attr($id); ?>">
                        <h4>Provedor: <?php echo esc_html($provider['name']); ?></h4>
                        <p><strong>URL:</strong> <?php echo esc_html($provider['api_url']); ?></p>
                        <p><strong>API Key:</strong> <?php echo esc_html(substr($provider['api_key'], 0, 10) . '...'); ?></p>
                        <button type="button" class="button button-secondary test-provider" data-provider-id="<?php echo esc_attr($id); ?>">Testar</button>
                        <button type="button" class="button button-secondary check-balance" data-provider-id="<?php echo esc_attr($id); ?>">Ver Saldo</button>
                        <button type="button" class="button button-link-delete remove-provider" data-provider-id="<?php echo esc_attr($id); ?>">Remover</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="smm-add-provider">
            <h4>Adicionar Novo Provedor</h4>
            <table class="form-table">
                <tr>
                    <th><label for="new_provider_name">Nome do Provedor</label></th>
                    <td><input type="text" id="new_provider_name" name="new_provider_name" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="new_provider_url">URL da API</label></th>
                    <td><input type="url" id="new_provider_url" name="new_provider_url" class="regular-text" placeholder="https://exemplo.com/api/v2" /></td>
                </tr>
                <tr>
                    <th><label for="new_provider_key">API Key</label></th>
                    <td><input type="password" id="new_provider_key" name="new_provider_key" class="regular-text" /></td>
                </tr>
            </table>
            <button type="button" class="button button-primary" id="add-provider">Adicionar Provedor</button>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#add-provider').on('click', function() {
                const name = $('#new_provider_name').val();
                const url = $('#new_provider_url').val();
                const key = $('#new_provider_key').val();
                
                if (!name || !url || !key) {
                    alert('Preencha todos os campos');
                    return;
                }
                
                // Adicionar provedor via AJAX
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'add_smm_provider',
                        name: name,
                        url: url,
                        key: key,
                        nonce: '<?php echo wp_create_nonce('smm_add_provider_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Erro ao adicionar provedor: ' + response.data);
                        }
                    }
                });
            });
            
            $('.remove-provider').on('click', function() {
                if (confirm('Tem certeza que deseja remover este provedor?')) {
                    const providerId = $(this).data('provider-id');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'remove_smm_provider',
                            provider_id: providerId,
                            nonce: '<?php echo wp_create_nonce('smm_remove_provider_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('Erro ao remover provedor: ' + response.data);
                            }
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Renderizar campo de provedor padrão
     */
    public function render_default_provider_field() {
        $default_provider = get_option('smm_default_provider', '');
        $providers = get_option('smm_providers', []);
        
        // Garantir que $providers seja um array
        if (!is_array($providers)) {
            $providers = [];
        }
        ?>
        <select name="smm_default_provider">
            <option value="">Selecione um provedor padrão</option>
            <?php foreach ($providers as $id => $provider): ?>
                <option value="<?php echo esc_attr($id); ?>" <?php selected($default_provider, $id); ?>>
                    <?php echo esc_html($provider['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Este provedor será usado como padrão para novos produtos.</p>
        <?php
    }
    
    /**
     * Adicionar meta box SMM no produto
     */
    public function add_product_smm_meta_box() {
        add_meta_box(
            'product_smm_settings',
            'Configurações SMM',
            [$this, 'render_product_smm_meta_box'],
            'product',
            'side',
            'default'
        );
    }
    
    /**
     * Renderizar meta box SMM do produto
     */
    public function render_product_smm_meta_box($post) {
        wp_nonce_field('product_smm_meta_box', 'product_smm_meta_box_nonce');
        
        $smm_enabled = get_post_meta($post->ID, '_smm_enabled', true);
        $smm_provider = get_post_meta($post->ID, '_smm_provider', true);
        $smm_service_id = get_post_meta($post->ID, '_smm_service_id', true);
        
        $providers = get_option('smm_providers', []);
        
        // Garantir que $providers seja um array
        if (!is_array($providers)) {
            $providers = [];
        }
        ?>
        <div class="smm-product-settings">
            <p>
                <label>
                    <input type="checkbox" name="smm_enabled" value="1" <?php checked($smm_enabled, '1'); ?> />
                    <strong>Ativar envio automático SMM</strong>
                </label>
            </p>
            
            <?php if (!empty($providers)): ?>
                <p>
                    <label for="smm_provider"><strong>Provedor SMM:</strong></label><br>
                    <select name="smm_provider" id="smm_provider" style="width: 100%;">
                        <option value="">Selecione um provedor</option>
                        <?php foreach ($providers as $id => $provider): ?>
                            <option value="<?php echo esc_attr($id); ?>" <?php selected($smm_provider, $id); ?>>
                                <?php echo esc_html($provider['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>
                
                <p>
                    <label for="smm_service_id"><strong>Service ID:</strong></label><br>
                    <input type="text" name="smm_service_id" id="smm_service_id" value="<?php echo esc_attr($smm_service_id); ?>" style="width: 100%;" placeholder="ID do serviço no provedor" />
                    <button type="button" class="button button-secondary" id="get-services" style="margin-top: 5px; width: 100%;">Buscar Serviços</button>
                </p>
                

                
                <div id="services-list" style="margin-top: 10px; display: none;">
                    <h4>Serviços Disponíveis:</h4>
                    <div id="services-content"></div>
                </div>
            <?php else: ?>
                <p style="color: #d63638;">
                    <strong>⚠️ Nenhum provedor SMM configurado.</strong><br>
                    Configure os provedores em <a href="<?php echo admin_url('admin.php?page=smm-settings'); ?>">Configurações SMM</a>.
                </p>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#get-services').on('click', function() {
                const providerId = $('#smm_provider').val();
                if (!providerId) {
                    alert('Selecione um provedor primeiro');
                    return;
                }
                
                $(this).text('Buscando...').prop('disabled', true);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_smm_services',
                        provider_id: providerId,
                        nonce: '<?php echo wp_create_nonce('smm_services_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            displayServices(response.data);
                        } else {
                            alert('Erro ao buscar serviços: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('Erro na conexão');
                    },
                    complete: function() {
                        $('#get-services').text('Buscar Serviços').prop('disabled', false);
                    }
                });
            });
            
            function displayServices(services) {
                let html = '<div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">';
                
                services.forEach(function(service) {
                    html += `
                        <div style="border-bottom: 1px solid #eee; padding: 5px 0;">
                            <strong>ID: ${service.service}</strong><br>
                            <span style="color: #666;">${service.name}</span><br>
                            <small style="color: #999;">Preço: ${service.rate} | Mín: ${service.min} | Máx: ${service.max}</small>
                        </div>
                    `;
                });
                
                html += '</div>';
                $('#services-content').html(html);
                $('#services-list').show();
            }
        });
        </script>
        <?php
    }
    
    /**
     * Salvar meta box SMM do produto
     */
    public function save_product_smm_meta_box($post_id) {
        if (!isset($_POST['product_smm_meta_box_nonce']) || 
            !wp_verify_nonce($_POST['product_smm_meta_box_nonce'], 'product_smm_meta_box')) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (get_post_type($post_id) !== 'product') {
            return;
        }
        
        $smm_enabled = isset($_POST['smm_enabled']) ? '1' : '0';
        $smm_provider = sanitize_text_field($_POST['smm_provider'] ?? '');
        $smm_service_id = sanitize_text_field($_POST['smm_service_id'] ?? '');
        
        update_post_meta($post_id, '_smm_enabled', $smm_enabled);
        update_post_meta($post_id, '_smm_provider', $smm_provider);
        update_post_meta($post_id, '_smm_service_id', $smm_service_id);
    }
    
    /**
     * AJAX: Testar provedor SMM
     */
    public function ajax_test_smm_provider() {
        if (!wp_verify_nonce($_POST['nonce'], 'smm_test_nonce')) {
            wp_send_json_error('Erro de segurança');
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permissão negada');
        }
        
        $provider_id = sanitize_text_field($_POST['provider_id']);
        $providers = get_option('smm_providers', []);
        
        // Garantir que $providers seja um array
        if (!is_array($providers)) {
            $providers = [];
        }
        
        if (!isset($providers[$provider_id])) {
            wp_send_json_error('Provedor não encontrado');
        }
        
        $provider = $providers[$provider_id];
        $api = $this->get_api_instance($provider);
        
        try {
            $services = $api->services();
            if (isset($services->error)) {
                wp_send_json_error('Erro na API: ' . $services->error);
            }
            
            $services_count = is_array($services) ? count($services) : (is_object($services) ? count((array)$services) : 0);
            wp_send_json_success('Provedor testado com sucesso! ' . $services_count . ' serviços encontrados.');
        } catch (Exception $e) {
            wp_send_json_error('Erro na conexão: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Buscar serviços do provedor
     */
    public function ajax_get_smm_services() {
        if (!wp_verify_nonce($_POST['nonce'], 'smm_services_nonce')) {
            wp_send_json_error('Erro de segurança');
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permissão negada');
        }
        
        $provider_id = sanitize_text_field($_POST['provider_id']);
        $providers = get_option('smm_providers', []);
        
        // Garantir que $providers seja um array
        if (!is_array($providers)) {
            $providers = [];
        }
        
        if (!isset($providers[$provider_id])) {
            wp_send_json_error('Provedor não encontrado');
        }
        
        $provider = $providers[$provider_id];
        $api = $this->get_api_instance($provider);
        
        try {
            $services = $api->services();
            if (isset($services->error)) {
                wp_send_json_error('Erro na API: ' . $services->error);
            }
            
            wp_send_json_success($services);
        } catch (Exception $e) {
            wp_send_json_error('Erro na conexão: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Enviar pedido SMM
     */
    public function ajax_send_smm_order() {
        if (!wp_verify_nonce($_POST['nonce'], 'smm_order_nonce')) {
            wp_send_json_error('Erro de segurança');
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permissão negada');
        }
        
        $order_id = intval($_POST['order_id']);
        $provider_id = sanitize_text_field($_POST['provider_id']);
        $service_id = intval($_POST['service_id']);
        $quantity = intval($_POST['quantity']);
        $link = sanitize_url($_POST['link']);
        
        $providers = get_option('smm_providers', []);
        
        // Garantir que $providers seja um array
        if (!is_array($providers)) {
            $providers = [];
        }
        
        if (!isset($providers[$provider_id])) {
            wp_send_json_error('Provedor não encontrado');
        }
        
        $provider = $providers[$provider_id];
        $api = $this->get_api_instance($provider);
        
        try {
            $order_data = [
                'service' => $service_id,
                'link' => $link,
                'quantity' => $quantity
            ];
            
            $result = $api->order($order_data);
            
            if (isset($result->error)) {
                wp_send_json_error('Erro na API: ' . $result->error);
            }
            
            // Salvar informações do pedido SMM
            update_post_meta($order_id, '_smm_order_id', $result->order);
            update_post_meta($order_id, '_smm_provider_id', $provider_id);
            update_post_meta($order_id, '_smm_service_id', $service_id);
            update_post_meta($order_id, '_smm_quantity', $quantity);
            update_post_meta($order_id, '_smm_link', $link);
            update_post_meta($order_id, '_smm_status', 'pending');
            update_post_meta($order_id, '_smm_created_at', current_time('mysql'));
            
            wp_send_json_success([
                'message' => 'Pedido SMM enviado com sucesso!',
                'order_id' => $result->order,
                'charge' => $result->charge ?? 0
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error('Erro ao enviar pedido: ' . $e->getMessage());
        }
    }
    
    /**
     * Obter instância da API SMM
     */
    private function get_api_instance($provider) {
        if (!$this->api_class) {
            require_once plugin_dir_path(__FILE__) . 'smm-api.php';
            $this->api_class = new SMMApi();
        }
        
        $this->api_class->api_url = $provider['api_url'];
        $this->api_class->api_key = $provider['api_key'];
        
        return $this->api_class;
    }
    
    /**
     * Carregar provedores configurados
     */
    private function load_providers() {
        $this->providers = get_option('smm_providers', []);
        
        // Garantir que $this->providers seja um array
        if (!is_array($this->providers)) {
            $this->providers = [];
        }
    }
    
    /**
     * Verificar se produto tem SMM ativado
     */
    public static function is_product_smm_enabled($product_id) {
        return get_post_meta($product_id, '_smm_enabled', true) === '1';
    }
    
    /**
     * Obter provedor do produto
     */
    public static function get_product_provider($product_id) {
        return get_post_meta($product_id, '_smm_provider', true);
    }
    
    /**
     * Obter service ID do produto
     */
    public static function get_product_service_id($product_id) {
        return get_post_meta($product_id, '_smm_service_id', true);
    }
    

}

// Inicializar o módulo SMM
new SMMModule();

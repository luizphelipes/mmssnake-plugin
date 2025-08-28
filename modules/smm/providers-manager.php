<?php
/**
 * Gerenciador de Provedores SMM
 * Funcionalidades AJAX para adicionar, remover e gerenciar provedores
 */

if (!defined('ABSPATH')) {
    exit;
}

class SMMProvidersManager {
    
    public function __construct() {
        add_action('wp_ajax_add_smm_provider', [$this, 'ajax_add_provider']);
        add_action('wp_ajax_remove_smm_provider', [$this, 'ajax_remove_provider']);
        add_action('wp_ajax_check_smm_balance', [$this, 'ajax_check_balance']);
    }
    
    /**
     * AJAX: Adicionar provedor SMM
     */
    public function ajax_add_provider() {
        if (!wp_verify_nonce($_POST['nonce'], 'smm_add_provider_nonce')) {
            wp_send_json_error('Erro de segurança');
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permissão negada');
        }
        
        $name = sanitize_text_field($_POST['name']);
        $url = sanitize_url($_POST['url']);
        $key = sanitize_text_field($_POST['key']);
        
        if (empty($name) || empty($url) || empty($key)) {
            wp_send_json_error('Todos os campos são obrigatórios');
        }
        
        // Validar URL da API
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error('URL da API inválida');
        }
        
        // Testar conexão com o provedor (opcional)
        try {
            $test_result = $this->test_provider_connection($url, $key);
            if (!$test_result['success']) {
                // Apenas mostrar aviso, não bloquear o salvamento
                error_log('SMM Provider Connection Test Failed: ' . $test_result['message']);
            }
        } catch (Exception $e) {
            // Log do erro, mas continuar com o salvamento
            error_log('SMM Provider Connection Test Exception: ' . $e->getMessage());
        }
        
        // Adicionar provedor
        $providers = get_option('smm_providers', []);
        
        // Garantir que $providers seja um array
        if (!is_array($providers)) {
            $providers = [];
        }
        
        $provider_id = 'provider_' . time();
        
        $providers[$provider_id] = [
            'name' => $name,
            'api_url' => $url,
            'api_key' => $key,
            'created_at' => current_time('mysql'),
            'status' => 'active'
        ];
        
        update_option('smm_providers', $providers);
        
        wp_send_json_success('Provedor adicionado com sucesso!');
    }
    
    /**
     * AJAX: Remover provedor SMM
     */
    public function ajax_remove_provider() {
        if (!wp_verify_nonce($_POST['nonce'], 'smm_remove_provider_nonce')) {
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
        
        // Verificar se há produtos usando este provedor
        $products_using_provider = $this->get_products_using_provider($provider_id);
        if (!empty($products_using_provider)) {
            wp_send_json_error('Não é possível remover o provedor. Existem produtos configurados para usá-lo.');
        }
        
        // Remover provedor
        unset($providers[$provider_id]);
        update_option('smm_providers', $providers);
        
        // Se era o provedor padrão, limpar a configuração
        $default_provider = get_option('smm_default_provider', '');
        if ($default_provider === $provider_id) {
            delete_option('smm_default_provider');
        }
        
        wp_send_json_success('Provedor removido com sucesso!');
    }
    
    /**
     * AJAX: Verificar saldo do provedor
     */
    public function ajax_check_balance() {
        if (!wp_verify_nonce($_POST['nonce'], 'smm_balance_nonce')) {
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
        
        try {
            require_once plugin_dir_path(__FILE__) . 'smm-api.php';
            $api = new SMMApi();
            $api->api_url = $provider['api_url'];
            $api->api_key = $provider['api_key'];
            
            $balance = $api->balance();
            
            if (isset($balance->error)) {
                wp_send_json_error('Erro na API: ' . $balance->error);
            }
            
            $balance_text = '';
            if (isset($balance->balance)) {
                $balance_text = number_format($balance->balance, 2);
                if (isset($balance->currency)) {
                    $balance_text .= ' ' . $balance->currency;
                }
            } else {
                $balance_text = 'Informação não disponível';
            }
            
            wp_send_json_success($balance_text);
            
        } catch (Exception $e) {
            wp_send_json_error('Erro na conexão: ' . $e->getMessage());
        }
    }
    
    /**
     * Testar conexão com provedor
     */
    private function test_provider_connection($url, $key) {
        try {
            // Verificar se a classe SMMApi existe
            if (!class_exists('SMMApi')) {
                require_once plugin_dir_path(__FILE__) . 'smm-api.php';
            }
            
            if (!class_exists('SMMApi')) {
                return [
                    'success' => false,
                    'message' => 'Classe SMMApi não encontrada'
                ];
            }
            
            $api = new SMMApi();
            $api->api_url = $url;
            $api->api_key = $key;
            
            // Definir timeout para evitar travamentos
            $api->timeout = 10;
            
            $services = $api->services();
            
            if (isset($services->error)) {
                return [
                    'success' => false,
                    'message' => $services->error
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Conexão bem-sucedida'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro na conexão: ' . $e->getMessage()
            ];
        } catch (Error $e) {
            return [
                'success' => false,
                'message' => 'Erro fatal: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obter produtos que usam um provedor específico
     */
    private function get_products_using_provider($provider_id) {
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_smm_provider',
                    'value' => $provider_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1,
            'fields' => 'ids'
        ];
        
        $query = new WP_Query($args);
        return $query->posts;
    }
    
    /**
     * Obter provedor por ID
     */
    public static function get_provider($provider_id) {
        $providers = get_option('smm_providers', []);
        
        // Garantir que $providers seja um array
        if (!is_array($providers)) {
            return null;
        }
        
        return isset($providers[$provider_id]) ? $providers[$provider_id] : null;
    }
    
    /**
     * Obter todos os provedores
     */
    public static function get_all_providers() {
        $providers = get_option('smm_providers', []);
        
        // Garantir que sempre retorne um array
        if (!is_array($providers)) {
            $providers = [];
        }
        
        return $providers;
    }
    
    /**
     * Obter provedor padrão
     */
    public static function get_default_provider() {
        $default_id = get_option('smm_default_provider', '');
        if (empty($default_id)) {
            return null;
        }
        
        return self::get_provider($default_id);
    }
    
    /**
     * Verificar se provedor existe
     */
    public static function provider_exists($provider_id) {
        $providers = get_option('smm_providers', []);
        
        // Garantir que $providers seja um array
        if (!is_array($providers)) {
            return false;
        }
        
        return isset($providers[$provider_id]);
    }
    
    /**
     * Obter estatísticas dos provedores
     */
    public static function get_providers_stats() {
        $providers = self::get_all_providers();
        
        // Verificar se $providers é um array válido
        if (!is_array($providers)) {
            $providers = [];
        }
        
        $stats = [
            'total' => count($providers),
            'active' => 0,
            'inactive' => 0
        ];
        
        foreach ($providers as $provider) {
            if (isset($provider['status']) && $provider['status'] === 'active') {
                $stats['active']++;
            } else {
                $stats['inactive']++;
            }
        }
        
        return $stats;
    }
}

// Inicializar o gerenciador de provedores
new SMMProvidersManager();

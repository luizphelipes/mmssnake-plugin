<?php
/**
 * Sistema de Mapeamento Automático SMM
 * Funciona APENAS com Service ID individual dos produtos
 * Elimina completamente a dependência do Service ID Global
 */

if (!defined('ABSPATH')) {
    exit;
}

class SMMAutoMapper {
    
    /**
     * Obter mapa de produtos com configuração SMM
     * Retorna um array com todos os produtos que têm SMM configurado
     */
    public static function get_smm_products_map() {
        global $wpdb;
        
        $products_map = [];
        
        // Buscar todos os produtos que têm SMM ativado
        $products = $wpdb->get_results("
            SELECT p.ID, p.post_title, pm1.meta_value as smm_enabled, 
                   pm2.meta_value as smm_provider, pm3.meta_value as smm_service_id
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_smm_enabled'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_smm_provider'
            LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_smm_service_id'
            WHERE p.post_type = 'product' 
            AND p.post_status = 'publish'
            AND pm1.meta_value = '1'
            AND pm2.meta_value IS NOT NULL 
            AND pm2.meta_value != ''
            AND pm3.meta_value IS NOT NULL 
            AND pm3.meta_value != ''
        ");
        
        foreach ($products as $product) {
            $products_map[$product->ID] = [
                'name' => $product->post_title,
                'provider_id' => $product->smm_provider,
                'service_id' => $product->smm_service_id,
                'enabled' => true
            ];
        }
        
        return $products_map;
    }
    
    /**
     * Obter configuração SMM de um produto específico
     * Implementa herança de configurações para variações
     */
    public static function get_product_smm_config($product_id) {
        // Obter ID do produto pai se for variação
        $actual_product_id = self::get_parent_product_id($product_id);
        
        $enabled = get_post_meta($actual_product_id, '_smm_enabled', true);
        $provider_id = get_post_meta($actual_product_id, '_smm_provider', true);
        $service_id = get_post_meta($actual_product_id, '_smm_service_id', true);
        
        if ($enabled !== '1' || empty($provider_id) || empty($service_id)) {
            return false;
        }
        
        return [
            'enabled' => true,
            'provider_id' => $provider_id,
            'service_id' => $service_id,
            'product_name' => get_the_title($actual_product_id),
            'is_variation' => ($actual_product_id !== $product_id),
            'variation_id' => ($actual_product_id !== $product_id) ? $product_id : null,
            'parent_id' => $actual_product_id
        ];
    }
    
    /**
     * Obter ID do produto pai se for uma variação
     * Centraliza a lógica de herança de configurações
     */
    private static function get_parent_product_id($product_id) {
        if (!is_numeric($product_id) || $product_id <= 0) {
            return $product_id;
        }
        
        $produto = wc_get_product($product_id);
        if ($produto && $produto->is_type('variation')) {
            $produto_pai = wc_get_product($produto->get_parent_id());
            if ($produto_pai) {
                if (function_exists('pedidos_debug_log')) {
                    pedidos_debug_log("Variação #{$product_id} detectada, usando configurações do produto pai #{$produto_pai->get_id()}", 'SMM_AUTO_MAPPER');
                }
                return $produto_pai->get_id();
            }
        }
        
        if (function_exists('pedidos_debug_log')) {
            pedidos_debug_log("Produto #{$product_id} não é variação, usando ID original", 'SMM_AUTO_MAPPER');
        }
        
        return $product_id;
    }
    
    /**
     * Verificar se um produto tem configuração SMM válida
     * Implementa herança de configurações para variações
     */
    public static function is_product_smm_valid($product_id) {
        $config = self::get_product_smm_config($product_id);
        if (!$config) {
            return false;
        }
        
        // Verificar se o provedor ainda existe
        $providers = get_option('smm_providers', []);
        return isset($providers[$config['provider_id']]);
    }
    
    /**
     * Obter pedidos pendentes que podem ser processados automaticamente
     */
    public static function get_pending_smm_orders() {
        global $wpdb;
        
        $pending_orders = [];
        
        // Buscar pedidos com status 'processing' que contêm produtos SMM
        $orders = $wpdb->get_results("
            SELECT DISTINCT o.ID as order_id, o.post_status, o.post_date
            FROM {$wpdb->posts} o
            INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON o.ID = oi.order_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            WHERE o.post_type = 'shop_order'
            AND o.post_status = 'processing'
            AND oi.order_item_type = 'line_item'
            AND oim.meta_key = '_product_id'
            AND oim.meta_value IN (
                SELECT p.ID 
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'product' 
                AND p.post_status = 'publish'
                AND pm.meta_key = '_smm_enabled'
                AND pm.meta_value = '1'
            )
            ORDER BY o.post_date ASC
        ");
        
        foreach ($orders as $order) {
            $order_obj = wc_get_order($order->order_id);
            if (!$order_obj) continue;
            
            $order_items = $order_obj->get_items();
            $smm_items = [];
            
            foreach ($order_items as $item) {
                $product_id = $item->get_product_id();
                $smm_config = self::get_product_smm_config($product_id);
                
                if ($smm_config) {
                    $smm_items[] = [
                        'product_id' => $product_id,
                        'product_name' => $item->get_name(),
                        'quantity' => $item->get_quantity(),
                        'smm_config' => $smm_config
                    ];
                }
            }
            
            if (!empty($smm_items)) {
                $pending_orders[] = [
                    'order_id' => $order->order_id,
                    'order_date' => $order->post_date,
                    'smm_items' => $smm_items,
                    'customer' => [
                        'name' => $order_obj->get_billing_first_name() . ' ' . $order_obj->get_billing_last_name(),
                        'email' => $order_obj->get_billing_email()
                    ]
                ];
            }
        }
        
        return $pending_orders;
    }
    
    /**
     * Processar automaticamente um pedido pendente
     */
    public static function process_pending_order($order_id) {
        $order_obj = wc_get_order($order_id);
        if (!$order_obj) {
            return ['success' => false, 'message' => 'Pedido não encontrado'];
        }
        
        $order_items = $order_obj->get_items();
        $processed_items = [];
        $errors = [];
        
        foreach ($order_items as $item) {
            $product_id = $item->get_product_id();
            $smm_config = self::get_product_smm_config($product_id);
            
            if (!$smm_config) {
                continue; // Produto sem SMM configurado
            }
            
            // Verificar se já foi processado
            $already_processed = get_post_meta($order_id, '_smm_processed_' . $product_id, true);
            if ($already_processed) {
                $processed_items[] = [
                    'product_id' => $product_id,
                    'status' => 'already_processed',
                    'message' => 'Item já processado anteriormente'
                ];
                continue;
            }
            
            try {
                // Obter provedor
                $providers = get_option('smm_providers', []);
                if (!isset($providers[$smm_config['provider_id']])) {
                    throw new Exception('Provedor não encontrado');
                }
                
                $provider = $providers[$smm_config['provider_id']];
                
                // Obter link do pedido (campo personalizado ou observações)
                $link = self::extract_link_from_order($order_obj, $item);
                if (empty($link)) {
                    throw new Exception('Link não encontrado no pedido');
                }
                
                // Enviar pedido SMM
                require_once plugin_dir_path(__FILE__) . 'smm-api.php';
                $api = new SMMApi();
                $api->api_url = $provider['api_url'];
                $api->api_key = $provider['api_key'];
                
                $order_data = [
                    'service' => $smm_config['service_id'],
                    'link' => $link,
                    'quantity' => $item->get_quantity()
                ];
                
                $result = $api->order($order_data);
                
                if (isset($result->error)) {
                    throw new Exception('Erro na API: ' . $result->error);
                }
                
                // Marcar como processado
                update_post_meta($order_id, '_smm_processed_' . $product_id, '1');
                update_post_meta($order_id, '_smm_order_id_' . $product_id, $result->order);
                update_post_meta($order_id, '_smm_provider_id_' . $product_id, $smm_config['provider_id']);
                update_post_meta($order_id, '_smm_service_id_' . $product_id, $smm_config['service_id']);
                update_post_meta($order_id, '_smm_quantity_' . $product_id, $item->get_quantity());
                update_post_meta($order_id, '_smm_link_' . $product_id, $link);
                update_post_meta($order_id, '_smm_status_' . $product_id, 'pending');
                update_post_meta($order_id, '_smm_created_at_' . $product_id, current_time('mysql'));
                
                $processed_items[] = [
                    'product_id' => $product_id,
                    'status' => 'success',
                    'message' => 'Pedido SMM enviado com sucesso',
                    'smm_order_id' => $result->order,
                    'charge' => $result->charge ?? 0
                ];
                
            } catch (Exception $e) {
                $errors[] = [
                    'product_id' => $product_id,
                    'error' => $e->getMessage()
                ];
                
                $processed_items[] = [
                    'product_id' => $product_id,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }
        
        return [
            'success' => empty($errors),
            'processed_items' => $processed_items,
            'errors' => $errors
        ];
    }
    
    /**
     * Extrair link do pedido (campo personalizado ou observações)
     */
    private static function extract_link_from_order($order, $item) {
        // Tentar campo personalizado 'link' ou 'url'
        $link = $order->get_meta('_link') ?: $order->get_meta('_url') ?: $order->get_meta('link') ?: $order->get_meta('url');
        
        if (!empty($link)) {
            return $link;
        }
        
        // Tentar nas observações do item
        $item_meta = $item->get_meta_data();
        foreach ($item_meta as $meta) {
            if (in_array(strtolower($meta->key), ['link', 'url', 'instagram', 'perfil'])) {
                $value = $meta->value;
                if (filter_var($value, FILTER_VALIDATE_URL)) {
                    return $value;
                }
            }
        }
        
        // Tentar nas observações do pedido
        $order_notes = $order->get_customer_note();
        if (!empty($order_notes)) {
            // Buscar URLs nas observações
            preg_match_all('/https?:\/\/[^\s]+/', $order_notes, $matches);
            if (!empty($matches[0])) {
                return $matches[0][0];
            }
        }
        
        return '';
    }
    
    /**
     * Processar todos os pedidos pendentes automaticamente
     */
    public static function process_all_pending_orders() {
        $pending_orders = self::get_pending_smm_orders();
        $results = [];
        
        foreach ($pending_orders as $pending_order) {
            $result = self::process_pending_order($pending_order['order_id']);
            $results[] = [
                'order_id' => $pending_order['order_id'],
                'result' => $result
            ];
        }
        
        return $results;
    }
    
    /**
     * Obter estatísticas dos produtos SMM
     */
    public static function get_smm_products_stats() {
        $products_map = self::get_smm_products_map();
        $total_products = count($products_map);
        
        $providers_count = [];
        foreach ($products_map as $product) {
            $provider_id = $product['provider_id'];
            $providers_count[$provider_id] = ($providers_count[$provider_id] ?? 0) + 1;
        }
        
        return [
            'total_products' => $total_products,
            'providers_distribution' => $providers_count,
            'products_map' => $products_map
        ];
    }
    
    /**
     * Verificar se o sistema está funcionando corretamente
     */
    public static function health_check() {
        $results = [];
        
        // Verificar se há produtos configurados
        $products_map = self::get_smm_products_map();
        $results['products_configured'] = count($products_map);
        
        // Verificar se há provedores configurados
        $providers = get_option('smm_providers', []);
        $results['providers_configured'] = count($providers);
        
        // Verificar se há pedidos pendentes
        $pending_orders = self::get_pending_smm_orders();
        $results['pending_orders'] = count($pending_orders);
        
        // Verificar se o WooCommerce está ativo
        $results['woocommerce_active'] = class_exists('WooCommerce');
        
        return $results;
    }
}


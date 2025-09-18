<?php
/**
 * SMM Variation Mapper - Mapeamento automático de Service IDs por variação
 * Detecta automaticamente se uma variação é BR ou Internacional
 */

if (!defined('ABSPATH')) {
    exit;
}

class SMMVariationMapper {
    
    /**
     * Detectar tipo de variação (BR ou Internacional)
     */
    public function detect_variation_type($variation) {
        if (!$variation || !is_object($variation)) {
            return 'internacional'; // Default
        }
        
        // Log para debug
        if (function_exists('pedidos_debug_log')) {
            pedidos_debug_log("Detectando tipo da variação ID: {$variation->get_id()}", 'VARIATION_MAPPER');
        }
        
        // 1. Verificar atributos da variação
        $attributes = $variation->get_attributes();
        
        if (function_exists('pedidos_debug_log')) {
            pedidos_debug_log("Atributos da variação: " . json_encode($attributes), 'VARIATION_MAPPER');
        }
        
        foreach ($attributes as $key => $value) {
            $value_lower = strtolower(trim($value));
            
            // Padrões para Brasil
            $br_patterns = ['br', 'brasil', 'brasileiro', 'brasileira', 'nacional', 'brazil'];
            if (in_array($value_lower, $br_patterns)) {
                if (function_exists('pedidos_debug_log')) {
                    pedidos_debug_log("Variação detectada como BR pelo atributo: {$key} = {$value}", 'VARIATION_MAPPER');
                }
                return 'br';
            }
            
            // Padrões para Internacional
            $int_patterns = ['int', 'internacional', 'global', 'worldwide', 'international', 'extern', 'externo'];
            if (in_array($value_lower, $int_patterns)) {
                if (function_exists('pedidos_debug_log')) {
                    pedidos_debug_log("Variação detectada como Internacional pelo atributo: {$key} = {$value}", 'VARIATION_MAPPER');
                }
                return 'internacional';
            }
        }
        
        // 2. Verificar nome da variação
        $variation_name = strtolower($variation->get_name());
        
        if (function_exists('pedidos_debug_log')) {
            pedidos_debug_log("Nome da variação: {$variation_name}", 'VARIATION_MAPPER');
        }
        
        // Padrões no nome para Brasil
        $br_name_patterns = ['br', 'brasil', 'brasileiro', 'nacional'];
        foreach ($br_name_patterns as $pattern) {
            if (strpos($variation_name, $pattern) !== false) {
                if (function_exists('pedidos_debug_log')) {
                    pedidos_debug_log("Variação detectada como BR pelo nome (contém: {$pattern})", 'VARIATION_MAPPER');
                }
                return 'br';
            }
        }
        
        // Padrões no nome para Internacional
        $int_name_patterns = ['int', 'internacional', 'global', 'international'];
        foreach ($int_name_patterns as $pattern) {
            if (strpos($variation_name, $pattern) !== false) {
                if (function_exists('pedidos_debug_log')) {
                    pedidos_debug_log("Variação detectada como Internacional pelo nome (contém: {$pattern})", 'VARIATION_MAPPER');
                }
                return 'internacional';
            }
        }
        
        // 3. Verificar SKU da variação
        $sku = strtolower($variation->get_sku());
        if (!empty($sku)) {
            if (function_exists('pedidos_debug_log')) {
                pedidos_debug_log("SKU da variação: {$sku}", 'VARIATION_MAPPER');
            }
            
            if (strpos($sku, 'br') !== false || strpos($sku, 'brasil') !== false) {
                if (function_exists('pedidos_debug_log')) {
                    pedidos_debug_log("Variação detectada como BR pelo SKU", 'VARIATION_MAPPER');
                }
                return 'br';
            }
            
            if (strpos($sku, 'int') !== false || strpos($sku, 'global') !== false) {
                if (function_exists('pedidos_debug_log')) {
                    pedidos_debug_log("Variação detectada como Internacional pelo SKU", 'VARIATION_MAPPER');
                }
                return 'internacional';
            }
        }
        
        // Default: Internacional
        if (function_exists('pedidos_debug_log')) {
            pedidos_debug_log("Variação não detectada especificamente, usando default: Internacional", 'VARIATION_MAPPER');
        }
        
        return 'internacional';
    }
    
    /**
     * Obter Service ID correto para uma variação
     */
    public function get_service_id_for_variation($variation_id) {
        if (function_exists('pedidos_debug_log')) {
            pedidos_debug_log("Obtendo Service ID para variação: {$variation_id}", 'VARIATION_MAPPER');
        }
        
        $variation = wc_get_product($variation_id);
        
        if (!$variation || !$variation->is_type('variation')) {
            if (function_exists('pedidos_debug_log')) {
                pedidos_debug_log("ERRO: Produto não é uma variação válida", 'VARIATION_MAPPER');
            }
            return '';
        }
        
        $parent_id = $variation->get_parent_id();
        
        // Verificar se a variação já tem Service ID específico configurado
        $variation_service_id = get_post_meta($variation_id, '_smm_service_id', true);
        if (!empty($variation_service_id)) {
            if (function_exists('pedidos_debug_log')) {
                pedidos_debug_log("Variação já tem Service ID específico: {$variation_service_id}", 'VARIATION_MAPPER');
            }
            return $variation_service_id;
        }
        
        // Detectar tipo da variação
        $type = $this->detect_variation_type($variation);
        
        // Obter Service ID do produto pai baseado no tipo
        if ($type === 'br') {
            $service_id = get_post_meta($parent_id, '_smm_service_id_br', true);
            
            // Fallback para Service ID padrão se BR não estiver configurado
            if (empty($service_id)) {
                $service_id = get_post_meta($parent_id, '_smm_service_id', true);
                if (function_exists('pedidos_debug_log')) {
                    pedidos_debug_log("Service ID BR não configurado, usando padrão: {$service_id}", 'VARIATION_MAPPER');
                }
            } else {
                if (function_exists('pedidos_debug_log')) {
                    pedidos_debug_log("Usando Service ID BR: {$service_id}", 'VARIATION_MAPPER');
                }
            }
        } else {
            $service_id = get_post_meta($parent_id, '_smm_service_id_internacional', true);
            
            // Fallback para Service ID padrão se Internacional não estiver configurado
            if (empty($service_id)) {
                $service_id = get_post_meta($parent_id, '_smm_service_id', true);
                if (function_exists('pedidos_debug_log')) {
                    pedidos_debug_log("Service ID Internacional não configurado, usando padrão: {$service_id}", 'VARIATION_MAPPER');
                }
            } else {
                if (function_exists('pedidos_debug_log')) {
                    pedidos_debug_log("Usando Service ID Internacional: {$service_id}", 'VARIATION_MAPPER');
                }
            }
        }
        
        return $service_id;
    }
    
    /**
     * Aplicar configurações SMM do produto pai para todas as variações
     */
    public function apply_to_all_variations($product_id) {
        if (function_exists('pedidos_debug_log')) {
            pedidos_debug_log("=== APLICANDO CONFIGURAÇÕES PARA TODAS AS VARIAÇÕES ===", 'VARIATION_MAPPER');
            pedidos_debug_log("Produto ID: {$product_id}", 'VARIATION_MAPPER');
        }
        
        $product = wc_get_product($product_id);
        
        if (!$product || !$product->is_type('variable')) {
            if (function_exists('pedidos_debug_log')) {
                pedidos_debug_log("ERRO: Produto não é variável ou não existe", 'VARIATION_MAPPER');
            }
            return false;
        }
        
        $variations = $product->get_children();
        
        if (empty($variations)) {
            if (function_exists('pedidos_debug_log')) {
                pedidos_debug_log("AVISO: Produto não tem variações", 'VARIATION_MAPPER');
            }
            return false;
        }
        
        if (function_exists('pedidos_debug_log')) {
            pedidos_debug_log("Encontradas " . count($variations) . " variações para processar", 'VARIATION_MAPPER');
        }
        
        // Obter configurações do produto pai
        $parent_config = [
            'enabled' => get_post_meta($product_id, '_smm_enabled', true),
            'provider' => get_post_meta($product_id, '_smm_provider', true),
            'logic_type' => get_post_meta($product_id, '_smm_logic_type', true),
            'service_id_br' => get_post_meta($product_id, '_smm_service_id_br', true),
            'service_id_internacional' => get_post_meta($product_id, '_smm_service_id_internacional', true),
            'service_id_default' => get_post_meta($product_id, '_smm_service_id', true)
        ];
        
        if (function_exists('pedidos_debug_log')) {
            pedidos_debug_log("Configuração do produto pai: " . json_encode($parent_config), 'VARIATION_MAPPER');
        }
        
        $applied_count = 0;
        $br_count = 0;
        $int_count = 0;
        
        foreach ($variations as $variation_id) {
            $variation = wc_get_product($variation_id);
            
            if (!$variation) {
                continue;
            }
            
            // Detectar tipo da variação
            $type = $this->detect_variation_type($variation);
            
            // Determinar Service ID baseado no tipo
            if ($type === 'br') {
                $service_id = !empty($parent_config['service_id_br']) ? 
                    $parent_config['service_id_br'] : 
                    $parent_config['service_id_default'];
                $br_count++;
            } else {
                $service_id = !empty($parent_config['service_id_internacional']) ? 
                    $parent_config['service_id_internacional'] : 
                    $parent_config['service_id_default'];
                $int_count++;
            }
            
            // Aplicar configuração na variação
            if (!empty($service_id)) {
                update_post_meta($variation_id, '_smm_service_id', $service_id);
                update_post_meta($variation_id, '_smm_enabled', $parent_config['enabled']);
                update_post_meta($variation_id, '_smm_provider', $parent_config['provider']);
                update_post_meta($variation_id, '_smm_logic_type', $parent_config['logic_type']);
                
                $applied_count++;
                
                if (function_exists('pedidos_debug_log')) {
                    pedidos_debug_log("Variação {$variation_id} ({$type}): Service ID {$service_id} aplicado", 'VARIATION_MAPPER');
                }
            } else {
                if (function_exists('pedidos_debug_log')) {
                    pedidos_debug_log("AVISO: Service ID vazio para variação {$variation_id} ({$type})", 'VARIATION_MAPPER');
                }
            }
        }
        
        if (function_exists('pedidos_debug_log')) {
            pedidos_debug_log("=== APLICAÇÃO CONCLUÍDA ===", 'VARIATION_MAPPER');
            pedidos_debug_log("Total aplicado: {$applied_count}", 'VARIATION_MAPPER');
            pedidos_debug_log("Variações BR: {$br_count}", 'VARIATION_MAPPER');
            pedidos_debug_log("Variações Internacionais: {$int_count}", 'VARIATION_MAPPER');
        }
        
        return [
            'success' => true,
            'applied_count' => $applied_count,
            'br_count' => $br_count,
            'int_count' => $int_count,
            'total_variations' => count($variations)
        ];
    }
    
    /**
     * Obter estatísticas de variações de um produto
     */
    public function get_variation_stats($product_id) {
        $product = wc_get_product($product_id);
        
        if (!$product || !$product->is_type('variable')) {
            return null;
        }
        
        $variations = $product->get_children();
        $stats = [
            'total' => count($variations),
            'br' => 0,
            'internacional' => 0,
            'configured' => 0
        ];
        
        foreach ($variations as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation) continue;
            
            $type = $this->detect_variation_type($variation);
            $stats[$type]++;
            
            $service_id = get_post_meta($variation_id, '_smm_service_id', true);
            if (!empty($service_id)) {
                $stats['configured']++;
            }
        }
        
        return $stats;
    }
}

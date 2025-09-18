<?php
/**
 * Sistema de Regras por Categoria
 * Gerencia regras específicas para diferentes tipos de produtos SMM
 */

if (!defined('ABSPATH')) {
    exit;
}

class SMMCategoryRules {
    
    /**
     * Regras por categoria
     */
    private static $rules = [
        'curtidas' => [
            'keywords' => ['curtidas', 'likes', 'like'],
            'requires_link_reconstruction' => true,
            'supports_distributed_orders' => true,
            'meta_fields' => [
                'instagram_reels' => 'Instagram Reels',
                'instagram_posts' => 'Instagram Posts'
            ]
        ],
        'visualizacoes' => [
            'keywords' => ['visualizações', 'visualizacoes', 'views', 'view', 'visualizações stories', 'visualizações reels'],
            'requires_link_reconstruction' => true,
            'supports_distributed_orders' => true,
            'meta_fields' => [
                'instagram_reels' => 'Instagram Reels',
                'instagram_posts' => 'Instagram Posts'
            ]
        ],
        'seguidores' => [
            'keywords' => ['seguidores', 'followers', 'follower'],
            'requires_link_reconstruction' => false,
            'supports_distributed_orders' => false,
            'meta_fields' => [
                'instagram_username' => 'Instagram'
            ]
        ],
        'comentarios_ia' => [
            'keywords' => ['comentarios', 'comments', 'comentário', 'comentários', 'comentarios ia', 'comentários ia', 'comentarios + ia', 'comentários + ia'],
            'requires_link_reconstruction' => true,
            'supports_distributed_orders' => true,
            'meta_fields' => [
                'instagram_reels' => 'Instagram Reels',
                'instagram_posts' => 'Instagram Posts'
            ]
        ]
    ];
    
    /**
     * Determinar categoria do produto baseado no nome
     */
    public static function get_product_category($product_name) {
        $product_name_lower = strtolower($product_name);
        
        foreach (self::$rules as $category => $rule) {
            foreach ($rule['keywords'] as $keyword) {
                if (strpos($product_name_lower, $keyword) !== false) {
                    return $category;
                }
            }
        }
        
        return 'default';
    }
    
    /**
     * Verificar se produto requer reconstrução de links
     * Agora usa o campo manual do produto em vez do mapeamento automático
     * Suporta herança de configurações para variações
     */
    public static function requires_link_reconstruction($product_id) {
        // Verificar se é um ID de produto
        if (is_numeric($product_id)) {
            // Obter ID do produto pai se for variação
            $product_id = self::get_parent_product_id($product_id);
            
            $logic_type = get_post_meta($product_id, '_smm_logic_type', true);
            return in_array($logic_type, ['posts_reels', 'comentarios_ia']);
        }
        
        // Fallback para compatibilidade (usar nome do produto)
        $category = self::get_product_category($product_id);
        return isset(self::$rules[$category]) ? self::$rules[$category]['requires_link_reconstruction'] : false;
    }
    
    /**
     * Verificar se produto suporta pedidos distribuídos
     * Agora usa o campo manual do produto em vez do mapeamento automático
     * Suporta herança de configurações para variações
     */
    public static function supports_distributed_orders($product_id) {
        // Verificar se é um ID de produto
        if (is_numeric($product_id)) {
            // Obter ID do produto pai se for variação
            $product_id = self::get_parent_product_id($product_id);
            
            $logic_type = get_post_meta($product_id, '_smm_logic_type', true);
            return in_array($logic_type, ['posts_reels', 'comentarios_ia']);
        }
        
        // Fallback para compatibilidade (usar nome do produto)
        $category = self::get_product_category($product_id);
        return isset(self::$rules[$category]) ? self::$rules[$category]['supports_distributed_orders'] : false;
    }
    
    /**
     * Obter campos de meta para uma categoria
     */
    public static function get_meta_fields($product_name) {
        $category = self::get_product_category($product_name);
        return isset(self::$rules[$category]) ? self::$rules[$category]['meta_fields'] : [];
    }
    
    /**
     * Obter ID do produto pai se for uma variação
     * Centraliza a lógica de herança de configurações
     */
    public static function get_parent_product_id($product_id) {
        if (!is_numeric($product_id) || $product_id <= 0) {
            return $product_id;
        }
        
        $produto = wc_get_product($product_id);
        if ($produto && $produto->is_type('variation')) {
            $produto_pai = wc_get_product($produto->get_parent_id());
            if ($produto_pai) {
                if (function_exists('pedidos_debug_log')) {
                    pedidos_debug_log("Variação #{$product_id} detectada, usando configurações do produto pai #{$produto_pai->get_id()}", 'CATEGORY_RULES');
                }
                return $produto_pai->get_id();
            }
        }
        
        if (function_exists('pedidos_debug_log')) {
            pedidos_debug_log("Produto #{$product_id} não é variação, usando ID original", 'CATEGORY_RULES');
        }
        
        return $product_id;
    }
    
    /**
     * Reconstruir links de publicações/reels
     * Agora suporta tanto links completos quanto IDs
     */
    public static function reconstruct_links($item_meta) {
        $links = [];
        
        if (function_exists('pedidos_debug_log')) {
            pedidos_debug_log("Iniciando reconstrução de links para item_id: {$item_meta['item_id']}", 'CATEGORY_RULES');
        }
        
        // Buscar TODOS os campos de publicações (posts e reels) - múltiplas tentativas
        $all_publications = [];
        
        // Lista de campos possíveis para buscar
        $publication_fields = [
            'Instagram Reels',
            'instagram_reels', 
            'Instagram Posts',
            'instagram_posts',
            'Publicação 1 (REEL)',
            'Publicação 1 (POST)',
            'Publicação 2 (REEL)',
            'Publicação 2 (POST)',
            'Publicação 3 (REEL)',
            'Publicação 3 (POST)',
            'Publicação 4 (REEL)',
            'Publicação 4 (POST)',
            'Publicação 5 (REEL)',
            'Publicação 5 (POST)'
        ];
        
        if (function_exists('pedidos_debug_log')) {
            pedidos_debug_log("Buscando em " . count($publication_fields) . " campos de publicações", 'CATEGORY_RULES');
        }
        
        // Buscar em todos os campos possíveis
        foreach ($publication_fields as $field) {
            $field_value = wc_get_order_item_meta($item_meta['item_id'], $field, true);
            if (!empty($field_value)) {
                if (function_exists('pedidos_debug_log')) {
                    pedidos_debug_log("Campo '{$field}' encontrado: '{$field_value}'", 'CATEGORY_RULES');
                }
                
                // Dividir por vírgula e processar cada entrada
                $entries = array_filter(array_map('trim', explode(',', $field_value)));
                foreach ($entries as $entry) {
                    if (!empty($entry)) {
                        $all_publications[] = $entry;
                        if (function_exists('pedidos_debug_log')) {
                            pedidos_debug_log("Publicação adicionada: '{$entry}'", 'CATEGORY_RULES');
                        }
                    }
                }
            }
        }
        
        if (function_exists('pedidos_debug_log')) {
            pedidos_debug_log("Total de publicações encontradas: " . count($all_publications), 'CATEGORY_RULES');
        }
        
        // Processar todas as publicações encontradas
        foreach ($all_publications as $publication) {
            if (!empty($publication)) {
                if (function_exists('pedidos_debug_log')) {
                    pedidos_debug_log("Processando publicação: '{$publication}'", 'CATEGORY_RULES');
                }
                $link_data = self::process_instagram_entry($publication, 'auto'); // 'auto' para detectar automaticamente
                if ($link_data) {
                    $links[] = $link_data;
                    if (function_exists('pedidos_debug_log')) {
                        pedidos_debug_log("Link processado: " . print_r($link_data, true), 'CATEGORY_RULES');
                    }
                }
            }
        }
        
        if (function_exists('pedidos_debug_log')) {
            pedidos_debug_log("Total de links reconstruídos: " . count($links), 'CATEGORY_RULES');
        }
        
        return $links;
    }
    
    /**
     * Processar entrada do Instagram (link completo ou ID)
     * Detecta automaticamente se é um link completo ou apenas um ID
     */
    private static function process_instagram_entry($entry, $type) {
        // Log de debug
        if (function_exists('pedidos_debug_log')) {
            pedidos_debug_log("Processando entrada {$type}: {$entry}", 'CATEGORY_RULES');
        }
        
        // Verificar se é um link completo
        if (filter_var($entry, FILTER_VALIDATE_URL)) {
            // É um link completo, detectar automaticamente o tipo e extrair ID
            $detected_type = self::detect_instagram_type($entry);
            if ($detected_type) {
                $id = self::extract_id_from_url($entry, $detected_type);
                if ($id) {
                    if (function_exists('pedidos_debug_log')) {
                        pedidos_debug_log("Link completo detectado - Tipo: {$detected_type}, ID extraído: {$id}", 'CATEGORY_RULES');
                    }
                    return [
                        'type' => $detected_type,
                        'id' => $id,
                        'url' => $entry // Usar o link original
                    ];
                } else {
                    if (function_exists('pedidos_error_log')) {
                        pedidos_error_log("Falha ao extrair ID do link: {$entry}", 'CATEGORY_RULES');
                    }
                }
            } else {
                if (function_exists('pedidos_error_log')) {
                    pedidos_error_log("Tipo de link Instagram não reconhecido: {$entry}", 'CATEGORY_RULES');
                }
            }
        } else {
            // É apenas um ID, detectar tipo automaticamente se for 'auto'
            if ($type === 'auto') {
                // Tentar detectar se é um ID de post ou reel baseado no padrão
                if (preg_match('/^[A-Za-z0-9_-]+$/', $entry)) {
                    // É um ID válido, tentar reconstruir como post primeiro
                    $url = self::reconstruct_instagram_url($entry, 'post');
                    if ($url) {
                        if (function_exists('pedidos_debug_log')) {
                            pedidos_debug_log("ID detectado como post - URL reconstruída: {$url}", 'CATEGORY_RULES');
                        }
                        return [
                            'type' => 'post',
                            'id' => $entry,
                            'url' => $url
                        ];
                    }
                }
            } else {
                // Usar tipo específico fornecido
                $url = self::reconstruct_instagram_url($entry, $type);
                if ($url) {
                    if (function_exists('pedidos_debug_log')) {
                        pedidos_debug_log("ID detectado - URL reconstruída: {$url}", 'CATEGORY_RULES');
                    }
                    return [
                        'type' => $type,
                        'id' => $entry,
                        'url' => $url
                    ];
                } else {
                    if (function_exists('pedidos_error_log')) {
                        pedidos_error_log("Falha ao reconstruir URL do ID: {$entry}", 'CATEGORY_RULES');
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * Detectar automaticamente o tipo de link do Instagram
     */
    private static function detect_instagram_type($url) {
        if (preg_match('/instagram\.com\/reel\/([^\/\?]+)/', $url)) {
            return 'reel';
        } elseif (preg_match('/instagram\.com\/p\/([^\/\?]+)/', $url)) {
            return 'post';
        }
        
        return null;
    }
    
    /**
     * Extrair ID de uma URL do Instagram
     */
    private static function extract_id_from_url($url, $type) {
        if ($type === 'reel') {
            // Padrão: https://www.instagram.com/reel/REEL_ID/
            if (preg_match('/instagram\.com\/reel\/([^\/\?]+)/', $url, $matches)) {
                return $matches[1];
            }
        } elseif ($type === 'post') {
            // Padrão: https://www.instagram.com/p/POST_ID/
            if (preg_match('/instagram\.com\/p\/([^\/\?]+)/', $url, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
    
    /**
     * Reconstruir URL do Instagram baseado no tipo
     */
    private static function reconstruct_instagram_url($id, $type) {
        if ($type === 'reel') {
            return self::reconstruct_instagram_reel_url($id);
        } elseif ($type === 'post') {
            return self::reconstruct_instagram_post_url($id);
        }
        
        return null;
    }
    
    /**
     * Reconstruir URL de Instagram Reel
     */
    private static function reconstruct_instagram_reel_url($reel_id) {
        // Formato: https://www.instagram.com/reel/REEL_ID/
        return "https://www.instagram.com/reel/{$reel_id}/";
    }
    
    /**
     * Reconstruir URL de Instagram Post
     */
    private static function reconstruct_instagram_post_url($post_id) {
        // Formato: https://www.instagram.com/p/POST_ID/
        return "https://www.instagram.com/p/{$post_id}/";
    }
    
    /**
     * Processar pedido distribuído
     */
    public static function process_distributed_order($order_data, $links, $quantity, $multiplier = 1) {
        $distributed_orders = [];
        
        if (empty($links)) {
            // Se não há links, retornar array vazio (não processar)
            return [];
        }
        
        // Calcular quantidade por link
        $quantity_per_link = intval($quantity / count($links));
        $remaining_quantity = $quantity % count($links);
        
        foreach ($links as $index => $link) {
            $link_quantity = $quantity_per_link;
            
            // Distribuir quantidade restante nos primeiros links
            if ($index < $remaining_quantity) {
                $link_quantity++;
            }
            
            // Aplicar multiplicador se configurado
            $link_quantity *= $multiplier;
            
            if ($link_quantity > 0) {
                $distributed_order = $order_data;
                $distributed_order['link'] = $link['url'];
                $distributed_order['quantity'] = $link_quantity;
                $distributed_order['link_type'] = $link['type'];
                $distributed_order['link_id'] = $link['id'];
                
                $distributed_orders[] = $distributed_order;
            }
        }
        
        return $distributed_orders;
    }
    
    /**
     * Obter multiplicador do pedido
     */
    public static function get_order_multiplier($item_meta) {
        // Buscar campo de multiplicador
        $multiplier_meta = wc_get_order_item_meta($item_meta['item_id'], 'Multiplicador', true);
        if (!empty($multiplier_meta) && is_numeric($multiplier_meta)) {
            return intval($multiplier_meta);
        }
        
        // Buscar campo de quantidade multiplicada
        $multiplied_meta = wc_get_order_item_meta($item_meta['item_id'], 'Quantidade Multiplicada', true);
        if (!empty($multiplied_meta) && strtolower($multiplied_meta) === 'sim') {
            return 3; // Valor padrão se não especificado
        }
        
        return 1;
    }
    
    /**
     * Obter informações de distribuição do pedido
     * Agora usa o campo manual do produto em vez do mapeamento automático
     */
    public static function get_distribution_info($item_meta) {
        $info = [
            'is_distributed' => false,
            'multiplier' => 1,
            'links' => [],
            'total_quantity' => 0
        ];
        
        // Log de debug
        if (function_exists('pedidos_debug_log')) {
            pedidos_debug_log("Verificando distribuição para item_id: {$item_meta['item_id']}", 'CATEGORY_RULES');
        }
        
        // Verificar se o produto está configurado para usar posts/reels ou comentários + IA
        $product_id = $item_meta['product_id'] ?? 0;
        if (function_exists('pedidos_debug_log')) {
            pedidos_debug_log("Verificando produto #{$product_id} para posts/reels ou comentários + IA", 'CATEGORY_RULES');
        }
        
        if ($product_id > 0) {
            // Obter ID do produto pai se for variação
            $product_id = self::get_parent_product_id($product_id);
            
            $logic_type = get_post_meta($product_id, '_smm_logic_type', true);
            if (function_exists('pedidos_debug_log')) {
                pedidos_debug_log("Tipo de lógica do produto #{$product_id}: '{$logic_type}'", 'CATEGORY_RULES');
            }
            
            if (!in_array($logic_type, ['posts_reels', 'comentarios_ia'])) {
                if (function_exists('pedidos_debug_log')) {
                    pedidos_debug_log("Produto #{$product_id} não configurado para posts/reels ou comentários + IA (tipo: {$logic_type})", 'CATEGORY_RULES');
                }
                return $info;
            }
        }
        
        // Buscar TODOS os campos de publicações possíveis
        $publication_fields = [
            'Instagram Reels',
            'instagram_reels', 
            'Instagram Posts',
            'instagram_posts',
            'Publicação 1 (REEL)',
            'Publicação 1 (POST)',
            'Publicação 2 (REEL)',
            'Publicação 2 (POST)',
            'Publicação 3 (REEL)',
            'Publicação 3 (POST)',
            'Publicação 4 (REEL)',
            'Publicação 4 (POST)',
            'Publicação 5 (REEL)',
            'Publicação 5 (POST)'
        ];
        
        $found_publications = false;
        
        if (function_exists('pedidos_debug_log')) {
            pedidos_debug_log("Item ID: {$item_meta['item_id']}", 'CATEGORY_RULES');
        }
        
        // Verificar se tem pelo menos um campo com publicações
        foreach ($publication_fields as $field) {
            $field_value = wc_get_order_item_meta($item_meta['item_id'], $field, true);
            if (!empty($field_value)) {
                $found_publications = true;
                if (function_exists('pedidos_debug_log')) {
                    pedidos_debug_log("Campo '{$field}' encontrado: '{$field_value}'", 'CATEGORY_RULES');
                }
                break; // Se encontrou pelo menos um, já é suficiente
            }
        }
        
        if (!$found_publications) {
            if (function_exists('pedidos_debug_log')) {
                pedidos_debug_log("Nenhum campo de publicação encontrado", 'CATEGORY_RULES');
            }
        } else {
            // Se encontrou publicações, processar como pedido distribuído
            $info['is_distributed'] = true;
            $info['links'] = self::reconstruct_links($item_meta);
            $info['multiplier'] = self::get_order_multiplier($item_meta);
            $info['total_quantity'] = count($info['links']) * $info['multiplier'];
            
            if (function_exists('pedidos_debug_log')) {
                pedidos_debug_log("Pedido distribuído detectado: " . count($info['links']) . " links, multiplicador: {$info['multiplier']}", 'CATEGORY_RULES');
                foreach ($info['links'] as $index => $link) {
                    pedidos_debug_log("Link " . ($index + 1) . ": {$link['type']} - ID: {$link['id']} - URL: {$link['url']}", 'CATEGORY_RULES');
                }
            }
        }
        
        return $info;
    }
}

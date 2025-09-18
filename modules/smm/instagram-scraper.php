<?php
/**
 * Instagram Scraper - Obtém informações de posts do Instagram
 * Usa RapidAPI Instagram Social API para extrair dados
 */

if (!defined('ABSPATH')) {
    exit;
}

class InstagramScraper {
    
    private $rapidapi_key;
    private $rapidapi_host;
    private $log_file;
    
    public function __construct() {
        $this->rapidapi_key = get_option('instagram_scraper_api_key', 'bb099aa633mshc32e5a3e833a238p1ba333jsn4e4ed3a7d3ce');
        $this->rapidapi_host = get_option('instagram_scraper_api_host', 'instagram-social-api.p.rapidapi.com');
        $this->log_file = plugin_dir_path(__FILE__) . '../debug-instagram-scraper.log';
    }
    
    /**
     * Log para arquivo de debug
     */
    private function log_to_file($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] {$message}" . PHP_EOL;
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Extrair código/ID do post a partir da URL
     */
    public function extract_post_code($url) {
        $this->log_to_file("Extraindo código da URL: {$url}");
        
        // Padrões suportados:
        // https://www.instagram.com/p/ABC123/
        // https://www.instagram.com/reel/ABC123/
        // https://instagram.com/p/ABC123/
        // ABC123 (código direto)
        
        if (preg_match('/instagram\.com\/(?:p|reel)\/([A-Za-z0-9_-]+)/', $url, $matches)) {
            $code = $matches[1];
            $this->log_to_file("Código extraído via regex: {$code}");
            return $code;
        }
        
        // Se não tem instagram.com, assumir que é código direto
        if (preg_match('/^[A-Za-z0-9_-]+$/', $url)) {
            $this->log_to_file("Código direto identificado: {$url}");
            return $url;
        }
        
        $this->log_to_file("ERRO: Não foi possível extrair código da URL");
        return false;
    }
    
    /**
     * Obter informações do post via API
     */
    public function get_post_info($url) {
        $this->log_to_file("=== INICIANDO INSTAGRAM SCRAPER ===");
        $this->log_to_file("URL recebida: {$url}");
        
        try {
            // Extrair código do post
            $post_code = $this->extract_post_code($url);
            if (!$post_code) {
                throw new Exception('URL do Instagram inválida ou código não encontrado');
            }
            
            $this->log_to_file("Código extraído: {$post_code}");
            
            // Fazer requisição para a API
            $response = $this->make_api_request($post_code);
            
            if (!$response) {
                throw new Exception('Falha na requisição à API do Instagram');
            }
            
            // Processar resposta
            $post_data = $this->process_api_response($response);
            
            if (!$post_data) {
                throw new Exception('Falha ao processar resposta da API');
            }
            
            $this->log_to_file("=== SCRAPING CONCLUÍDO COM SUCESSO ===");
            return $post_data;
            
        } catch (Exception $e) {
            $this->log_to_file("ERRO no scraping: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Fazer requisição para a API RapidAPI
     */
    private function make_api_request($post_code) {
        $this->log_to_file("Fazendo requisição para API...");
        
        $url = 'https://instagram-social-api.p.rapidapi.com/v1/post_info';
        
        $args = [
            'headers' => [
                'x-rapidapi-key' => $this->rapidapi_key,
                'x-rapidapi-host' => $this->rapidapi_host,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ];
        
        // Adicionar parâmetro de query
        $url .= '?' . http_build_query(['code_or_id_or_url' => $post_code]);
        
        $this->log_to_file("URL da requisição: {$url}");
        $this->log_to_file("Headers: " . json_encode($args['headers']));
        
        // Fazer requisição
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            $this->log_to_file("ERRO na requisição: " . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        $this->log_to_file("Código de resposta: {$response_code}");
        $this->log_to_file("Resposta (primeiros 500 chars): " . substr($response_body, 0, 500));
        
        if ($response_code !== 200) {
            $this->log_to_file("ERRO: Código de resposta inválido");
            return false;
        }
        
        return $response_body;
    }
    
    /**
     * Processar resposta da API e extrair dados relevantes
     */
    private function process_api_response($response_body) {
        $this->log_to_file("Processando resposta da API...");
        
        $data = json_decode($response_body, true);
        
        if (!$data || !isset($data['data'])) {
            $this->log_to_file("ERRO: Resposta JSON inválida ou sem campo 'data'");
            return false;
        }
        
        $post = $data['data'];
        
        // Extrair informações principais
        $post_info = [
            'id' => $post['id'] ?? '',
            'code' => $post['code'] ?? '',
            'is_video' => $post['is_video'] ?? false,
            'media_type' => $post['media_type'] ?? 1, // 1=photo, 2=video
            'caption' => '',
            'hashtags' => [],
            'mentions' => [],
            'owner_username' => '',
            'owner_full_name' => '',
            'owner_is_verified' => false,
            'owner_is_private' => false,
            'like_count' => 0,
            'comment_count' => 0,
            'view_count' => 0,
            'play_count' => 0,
            'thumbnail_url' => '',
            'display_url' => '',
            'video_url' => '',
            'taken_at' => 0
        ];
        
        // Caption e texto
        if (isset($post['caption']['text'])) {
            $post_info['caption'] = $post['caption']['text'];
        }
        
        // Hashtags
        if (isset($post['caption']['hashtags']) && is_array($post['caption']['hashtags'])) {
            $post_info['hashtags'] = $post['caption']['hashtags'];
        }
        
        // Mentions
        if (isset($post['caption']['mentions']) && is_array($post['caption']['mentions'])) {
            $post_info['mentions'] = $post['caption']['mentions'];
        }
        
        // Dados do proprietário
        if (isset($post['owner'])) {
            $post_info['owner_username'] = $post['owner']['username'] ?? '';
            $post_info['owner_full_name'] = $post['owner']['full_name'] ?? '';
            $post_info['owner_is_verified'] = $post['owner']['is_verified'] ?? false;
            $post_info['owner_is_private'] = $post['owner']['is_private'] ?? false;
        }
        
        // Métricas
        if (isset($post['metrics'])) {
            $post_info['like_count'] = $post['metrics']['like_count'] ?? 0;
            $post_info['comment_count'] = $post['metrics']['comment_count'] ?? 0;
            $post_info['view_count'] = $post['metrics']['view_count'] ?? 0;
            $post_info['play_count'] = $post['metrics']['play_count'] ?? 0;
        }
        
        // URLs de imagem
        $post_info['display_url'] = $post['display_url'] ?? '';
        $post_info['thumbnail_url'] = $post['thumbnail_url'] ?? '';
        
        // URL de vídeo
        if ($post_info['is_video'] && isset($post['video_url'])) {
            $post_info['video_url'] = $post['video_url'];
        }
        
        // Timestamp
        $post_info['taken_at'] = $post['taken_at'] ?? 0;
        
        $this->log_to_file("Dados processados com sucesso");
        $this->log_to_file("Tipo de mídia: " . ($post_info['is_video'] ? 'vídeo' : 'foto'));
        $this->log_to_file("Caption (primeiros 100 chars): " . substr($post_info['caption'], 0, 100));
        $this->log_to_file("Curtidas: {$post_info['like_count']}, Comentários: {$post_info['comment_count']}");
        
        return $post_info;
    }
    
    /**
     * Baixar imagem/thumbnail para análise
     */
    public function download_image($image_url, $temp_filename = null) {
        if (!$image_url) {
            return false;
        }
        
        $this->log_to_file("Baixando imagem: {$image_url}");
        
        if (!$temp_filename) {
            $temp_filename = 'instagram_post_' . time() . '.jpg';
        }
        
        $upload_dir = wp_upload_dir();
        $temp_path = $upload_dir['basedir'] . '/temp/' . $temp_filename;
        
        // Criar diretório temp se não existir
        $temp_dir = dirname($temp_path);
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        // Baixar imagem
        $image_data = wp_remote_get($image_url, ['timeout' => 30]);
        
        if (is_wp_error($image_data)) {
            $this->log_to_file("ERRO ao baixar imagem: " . $image_data->get_error_message());
            return false;
        }
        
        $image_body = wp_remote_retrieve_body($image_data);
        
        if (file_put_contents($temp_path, $image_body)) {
            $this->log_to_file("Imagem salva em: {$temp_path}");
            return $temp_path;
        }
        
        $this->log_to_file("ERRO ao salvar imagem");
        return false;
    }
    
    /**
     * Limpar arquivos temporários
     */
    public function cleanup_temp_files($file_path) {
        if (file_exists($file_path)) {
            unlink($file_path);
            $this->log_to_file("Arquivo temporário removido: {$file_path}");
        }
    }
    
    /**
     * Testar conectividade com a API
     */
    public function test_connection() {
        $this->log_to_file("=== TESTE DE CONECTIVIDADE ===");
        
        // Usar um post público conhecido do Instagram oficial para teste
        $test_code = 'C0UWodpJogI'; // Post de exemplo
        
        $response = $this->make_api_request($test_code);
        
        if ($response) {
            $data = json_decode($response, true);
            if ($data && isset($data['data'])) {
                $this->log_to_file("TESTE: Conectividade OK");
                return true;
            }
        }
        
        $this->log_to_file("TESTE: Falha na conectividade");
        return false;
    }
}

<?php
/**
 * Gemini Comments Generator - Gera comentários usando Gemini 2.5 Pro
 * Analisa imagens e textos para criar comentários personalizados
 */

if (!defined('ABSPATH')) {
    exit;
}

class GeminiCommentsGenerator {
    
    private $api_key;
    private $api_url;
    private $log_file;
    
    public function __construct() {
        $this->api_key = get_option('gemini_api_key', '');
        $this->api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent';
        $this->log_file = plugin_dir_path(__FILE__) . '../debug-gemini-comments.log';
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
     * Gerar comentários baseados nas informações do post
     */
    public function generate_comments($post_info, $quantity, $image_path = null) {
        $this->log_to_file("=== INICIANDO GERAÇÃO DE COMENTÁRIOS ===");
        $this->log_to_file("Quantidade solicitada: {$quantity}");
        $this->log_to_file("Post ID: " . ($post_info['id'] ?? 'N/A'));
        $this->log_to_file("Imagem fornecida: " . ($image_path ? 'SIM' : 'NÃO'));
        
        try {
            // Verificar se a API key está configurada
            if (empty($this->api_key)) {
                throw new Exception('API Key do Gemini não configurada');
            }
            
            // Preparar prompt baseado nas informações
            $prompt = $this->build_prompt($post_info, $quantity);
            
            // Preparar dados da requisição
            $request_data = $this->build_request_data($prompt, $image_path);
            
            // Fazer requisição para Gemini
            $response = $this->make_gemini_request($request_data);
            
            if (!$response) {
                throw new Exception('Falha na requisição ao Gemini');
            }
            
            // Processar resposta
            $comments = $this->process_gemini_response($response, $quantity);
            
            if (empty($comments)) {
                throw new Exception('Nenhum comentário foi gerado');
            }
            
            $this->log_to_file("Comentários gerados com sucesso: " . count($comments));
            $this->log_to_file("=== GERAÇÃO CONCLUÍDA ===");
            
            return $comments;
            
        } catch (Exception $e) {
            $this->log_to_file("ERRO na geração: " . $e->getMessage());
            
            // Fallback para comentários padrão
            return $this->get_fallback_comments($quantity);
        }
    }
    
    /**
     * Construir prompt para o Gemini
     */
    private function build_prompt($post_info, $quantity) {
        $this->log_to_file("Construindo prompt...");
        
        $caption = $post_info['caption'] ?? '';
        $username = $post_info['owner_username'] ?? '';
        $is_video = $post_info['is_video'] ?? false;
        $hashtags = $post_info['hashtags'] ?? [];
        $mentions = $post_info['mentions'] ?? [];
        
        $media_type = $is_video ? 'vídeo' : 'foto';
        $hashtags_text = !empty($hashtags) ? implode(', ', $hashtags) : 'nenhuma';
        $mentions_text = !empty($mentions) ? implode(', ', $mentions) : 'nenhuma';
        
        $prompt = "Você é um especialista em marketing digital e engajamento no Instagram.\n\n";
        $prompt .= "Gere {$quantity} comentários autênticos e envolventes para uma {$media_type} do Instagram.\n\n";
        $prompt .= "INFORMAÇÕES DA PUBLICAÇÃO:\n";
        $prompt .= "- Usuário: @{$username}\n";
        $prompt .= "- Tipo: {$media_type}\n";
        
        if (!empty($caption)) {
            $prompt .= "- Legenda: \"" . substr($caption, 0, 200) . (strlen($caption) > 200 ? '...' : '') . "\"\n";
        }
        
        if (!empty($hashtags)) {
            $prompt .= "- Hashtags: {$hashtags_text}\n";
        }
        
        if (!empty($mentions)) {
            $prompt .= "- Menções: {$mentions_text}\n";
        }
        
        $prompt .= "\nREQUISITOS:\n";
        $prompt .= "1. Comentários devem ser em português brasileiro\n";
        $prompt .= "2. Seja autêntico e natural, como um usuário real\n";
        $prompt .= "3. Use emojis apropriados (máximo 2 por comentário)\n";
        $prompt .= "4. Varie o tom: elogios, perguntas, reações, etc.\n";
        $prompt .= "5. Seja criativo e específico ao conteúdo\n";
        $prompt .= "6. Evite comentários genéricos como 'legal' ou 'muito bom'\n";
        $prompt .= "7. Use gírias brasileiras quando apropriado\n";
        $prompt .= "8. Comentários devem ter entre 5-20 palavras\n";
        $prompt .= "9. Se houver imagem anexada, analise-a e comente sobre ela\n";
        $prompt .= "10. Retorne apenas os comentários, um por linha, sem numeração\n\n";
        
        $prompt .= "FORMATO DE RESPOSTA:\n";
        $prompt .= "Retorne exatamente {$quantity} comentários, um por linha, sem números ou marcadores.\n";
        $prompt .= "Exemplo:\n";
        $prompt .= "Que foto incrível! 😍\n";
        $prompt .= "Adorei esse estilo\n";
        $prompt .= "Perfeita como sempre ✨\n";
        
        $this->log_to_file("Prompt construído com " . strlen($prompt) . " caracteres");
        return $prompt;
    }
    
    /**
     * Construir dados da requisição para o Gemini
     */
    private function build_request_data($prompt, $image_path = null) {
        $this->log_to_file("Construindo dados da requisição...");
        
        $parts = [
            ['text' => $prompt]
        ];
        
        // Adicionar imagem se fornecida
        if ($image_path && file_exists($image_path)) {
            $this->log_to_file("Adicionando imagem à requisição: {$image_path}");
            
            $image_data = file_get_contents($image_path);
            $image_base64 = base64_encode($image_data);
            
            $parts[] = [
                'inline_data' => [
                    'mime_type' => 'image/jpeg',
                    'data' => $image_base64
                ]
            ];
            
            $this->log_to_file("Imagem codificada em base64 (" . strlen($image_base64) . " chars)");
        }
        
        $request_data = [
            'contents' => [
                [
                    'parts' => $parts
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.8,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 1024
            ]
        ];
        
        return $request_data;
    }
    
    /**
     * Fazer requisição para o Gemini
     */
    private function make_gemini_request($request_data) {
        $this->log_to_file("Fazendo requisição para Gemini...");
        
        $url = $this->api_url . '?key=' . $this->api_key;
        
        $args = [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($request_data),
            'timeout' => 60
        ];
        
        $this->log_to_file("URL: {$url}");
        $this->log_to_file("Body size: " . strlen($args['body']) . " bytes");
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            $this->log_to_file("ERRO na requisição: " . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        $this->log_to_file("Código de resposta: {$response_code}");
        $this->log_to_file("Resposta (primeiros 300 chars): " . substr($response_body, 0, 300));
        
        if ($response_code !== 200) {
            $this->log_to_file("ERRO: Código de resposta inválido");
            $this->log_to_file("Resposta completa: " . $response_body);
            return false;
        }
        
        return $response_body;
    }
    
    /**
     * Processar resposta do Gemini
     */
    private function process_gemini_response($response_body, $expected_quantity) {
        $this->log_to_file("Processando resposta do Gemini...");
        
        $data = json_decode($response_body, true);
        
        if (!$data || !isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $this->log_to_file("ERRO: Resposta JSON inválida");
            return [];
        }
        
        $generated_text = $data['candidates'][0]['content']['parts'][0]['text'];
        $this->log_to_file("Texto gerado: " . substr($generated_text, 0, 200) . "...");
        
        // Dividir por linhas e limpar
        $lines = explode("\n", $generated_text);
        $comments = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Remover numeração e marcadores
            $line = preg_replace('/^\d+[\.\)]\s*/', '', $line);
            $line = preg_replace('/^[-\*]\s*/', '', $line);
            
            // Filtrar linhas válidas
            if (!empty($line) && strlen($line) > 3 && strlen($line) <= 100) {
                $comments[] = $line;
            }
        }
        
        // Limitar à quantidade solicitada
        $comments = array_slice($comments, 0, $expected_quantity);
        
        $this->log_to_file("Comentários processados: " . count($comments));
        foreach ($comments as $i => $comment) {
            $this->log_to_file("Comentário " . ($i + 1) . ": {$comment}");
        }
        
        return $comments;
    }
    
    /**
     * Comentários de fallback em caso de erro
     */
    private function get_fallback_comments($quantity) {
        $this->log_to_file("Usando comentários de fallback...");
        
        $fallback_comments = [
            'Que foto incrível! 😍',
            'Adorei esse post ✨',
            'Perfeita como sempre!',
            'Que estilo! 🔥',
            'Foto maravilhosa',
            'Muito top! 👏',
            'Que lindo!',
            'Amei demais! 💕',
            'Foto perfeita 📸',
            'Que charme! ✨',
            'Lindíssima!',
            'Foto incrível! 🤩',
            'Que elegância!',
            'Maravilhosa! 💖',
            'Foto top! 👌'
        ];
        
        // Embaralhar e pegar a quantidade solicitada
        shuffle($fallback_comments);
        $selected = array_slice($fallback_comments, 0, $quantity);
        
        $this->log_to_file("Comentários de fallback selecionados: " . count($selected));
        return $selected;
    }
    
    /**
     * Testar conectividade com o Gemini
     */
    public function test_connection() {
        $this->log_to_file("=== TESTE DE CONECTIVIDADE GEMINI ===");
        
        if (empty($this->api_key)) {
            $this->log_to_file("ERRO: API Key não configurada");
            return false;
        }
        
        $test_prompt = "Diga apenas 'Conexão OK' em português";
        
        $request_data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $test_prompt]
                    ]
                ]
            ]
        ];
        
        $response = $this->make_gemini_request($request_data);
        
        if ($response) {
            $data = json_decode($response, true);
            if ($data && isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $this->log_to_file("TESTE: Conectividade OK");
                return true;
            }
        }
        
        $this->log_to_file("TESTE: Falha na conectividade");
        return false;
    }
}

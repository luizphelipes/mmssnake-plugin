<?php
/**
 * Classe API SMM - Baseada no padrão de provedores SMM
 * Compatível com a maioria dos provedores SMM do mercado
 */

if (!defined('ABSPATH')) {
    exit;
}

class SMMApi {
    
    /** API URL */
    public $api_url = '';
    
    /** Your API key */
    public $api_key = '';
    
    /** Timeout para requisições cURL */
    public $timeout = 30;
    
    /** Add order */
    public function order($data) {
        $post = array_merge(['key' => $this->api_key, 'action' => 'add'], $data);
        return json_decode((string)$this->connect($post));
    }
    
    /** Add order with custom comments */
    public function orderWithComments($data) {
        // Preparar comentários para envio
        $comments = isset($data['comments']) ? $data['comments'] : '';
        if (is_array($comments)) {
            $comments = implode("\n", $comments);
        }
        
        // Remover quantity dos dados e adicionar comments
        $order_data = $data;
        unset($order_data['quantity']); // Não enviar quantity para comentários
        $order_data['comments'] = $comments;
        
        $post = array_merge(['key' => $this->api_key, 'action' => 'add'], $order_data);
        return json_decode((string)$this->connect($post));
    }
    
    /** Get order status  */
    public function status($order_id) {
        return json_decode(
            $this->connect([
                'key' => $this->api_key,
                'action' => 'status',
                'order' => $order_id
            ])
        );
    }
    
    /** Get orders status */
    public function multiStatus($order_ids) {
        return json_decode(
            $this->connect([
                'key' => $this->api_key,
                'action' => 'status',
                'orders' => implode(",", (array)$order_ids)
            ])
        );
    }
    
    /** Get services */
    public function services() {
        return json_decode(
            $this->connect([
                'key' => $this->api_key,
                'action' => 'services',
            ])
        );
    }
    
    /** Refill order */
    public function refill(int $orderId) {
        return json_decode(
            $this->connect([
                'key' => $this->api_key,
                'action' => 'refill',
                'order' => $orderId,
            ])
        );
    }
    
    /** Refill orders */
    public function multiRefill(array $orderIds) {
        return json_decode(
            $this->connect([
                'key' => $this->api_key,
                'action' => 'refill',
                'orders' => implode(',', $orderIds),
            ]),
            true,
        );
    }
    
    /** Get refill status */
    public function refillStatus(int $refillId) {
         return json_decode(
            $this->connect([
                'key' => $this->api_key,
                'action' => 'refill_status',
                'refill' => $refillId,
            ])
        );
    }
    
    /** Get refill statuses */
    public function multiRefillStatus(array $refillIds) {
         return json_decode(
            $this->connect([
                'key' => $this->api_key,
                'action' => 'refill_status',
                'refills' => implode(',', $refillIds),
            ]),
            true,
        );
    }
    
    /** Cancel orders */
    public function cancel(array $orderIds) {
        return json_decode(
            $this->connect([
                'key' => $this->api_key,
                'action' => 'cancel',
                'orders' => implode(',', $orderIds),
            ]),
            true,
        );
    }
    
    /** Get balance */
    public function balance() {
        return json_decode(
            $this->connect([
                'key' => $this->api_key,
                'action' => 'balance',
            ])
        );
    }
    
    private function connect($post) {
        try {
            // Verificar se a URL da API está definida
            if (empty($this->api_url)) {
                throw new Exception('URL da API não definida');
            }
            
            // Verificar se a API key está definida
            if (empty($this->api_key)) {
                throw new Exception('API key não definida');
            }
            
            $_post = [];
            if (is_array($post)) {
                foreach ($post as $name => $value) {
                    $_post[] = $name . '=' . urlencode($value);
                }
            }
            
            $post_data = join('&', $_post);
            
            // Tentar usar cURL primeiro (mais robusto)
            if (function_exists('curl_init')) {
                return $this->connect_with_curl($post_data);
            }
            
            // Fallback para file_get_contents (mais comum em servidores compartilhados)
            if (ini_get('allow_url_fopen')) {
                return $this->connect_with_file_get_contents($post_data);
            }
            
            // Último recurso: usar wp_remote_post (WordPress built-in)
            return $this->connect_with_wp_remote($post_data);
            
        } catch (Exception $e) {
            return json_encode(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Conectar usando cURL
     */
    private function connect_with_curl($post_data) {
        $ch = curl_init($this->api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_USERAGENT, 'WordPress SMM Plugin/1.0');
        
        $result = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($result === false) {
            throw new Exception('Erro cURL: ' . $curl_error);
        }
        
        if ($http_code >= 400) {
            throw new Exception('Erro HTTP: ' . $http_code);
        }
        
        return $result;
    }
    
    /**
     * Conectar usando file_get_contents
     */
    private function connect_with_file_get_contents($post_data) {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'User-Agent: WordPress SMM Plugin/1.0'
                ],
                'content' => $post_data,
                'timeout' => $this->timeout
            ]
        ]);
        
        $result = file_get_contents($this->api_url, false, $context);
        
        if ($result === false) {
            throw new Exception('Erro ao conectar com file_get_contents');
        }
        
        return $result;
    }
    
    /**
     * Conectar usando wp_remote_post (WordPress built-in)
     */
    private function connect_with_wp_remote($post_data) {
        $response = wp_remote_post($this->api_url, [
            'body' => $post_data,
            'timeout' => $this->timeout,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => 'WordPress SMM Plugin/1.0'
            ]
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('Erro wp_remote_post: ' . $response->get_error_message());
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code >= 400) {
            throw new Exception('Erro HTTP: ' . $http_code);
        }
        
        return wp_remote_retrieve_body($response);
    }
}

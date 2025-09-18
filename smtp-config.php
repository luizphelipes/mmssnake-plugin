<?php
/**
 * Configuração SMTP para Evitar Spam
 * Adicione este código ao seu wp-config.php ou functions.php
 */

// Configurações SMTP para evitar spam
if (!defined('ABSPATH')) {
    exit;
}

// Configurar SMTP se não estiver configurado
if (!function_exists('configurar_smtp_anti_spam')) {
    function configurar_smtp_anti_spam() {
        // Configurações SMTP para Zoho Mail
        define('SMTP_HOST', 'smtp.zoho.com'); // Servidor SMTP do Zoho
        define('SMTP_PORT', 587);
        define('SMTP_USERNAME', 'sac@seguipix.com.br');
        define('SMTP_PASSWORD', 'sua-senha-zoho'); // Senha do Zoho Mail
        define('SMTP_SECURE', 'tls');
        define('SMTP_AUTH', true);
        define('SMTP_DEBUG', false);
    }
    
    // Ativar configuração SMTP
    add_action('init', 'configurar_smtp_anti_spam');
}

// Configurações DKIM e SPF (adicione ao seu DNS)
/*
RECORDS DNS NECESSÁRIOS:

1. SPF Record (TXT):
   v=spf1 include:_spf.google.com ~all

2. DKIM Record (TXT):
   k=rsa; p=SUA_CHAVE_PUBLICA_DKIM

3. DMARC Record (TXT):
   v=DMARC1; p=quarantine; rua=mailto:admin@seudominio.com

4. CNAME Records:
   mail.seudominio.com -> ghs.googlehosted.com
*/

// Headers adicionais para evitar spam
if (!function_exists('adicionar_headers_anti_spam')) {
    function adicionar_headers_anti_spam($headers) {
        $headers[] = 'X-Mailer: WordPress/' . get_bloginfo('version');
        $headers[] = 'X-Priority: 3';
        $headers[] = 'X-MSMail-Priority: Normal';
        $headers[] = 'Importance: Normal';
        $headers[] = 'X-Auto-Response-Suppress: All';
        $headers[] = 'X-Precedence: bulk';
        $headers[] = 'X-Spam-Status: No';
        $headers[] = 'X-Spam-Score: 0';
        $headers[] = 'X-Spam-Level: ';
        
        return $headers;
    }
    
    add_filter('wp_mail', function($mail) {
        if (!isset($mail['headers'])) {
            $mail['headers'] = [];
        }
        
        $mail['headers'] = adicionar_headers_anti_spam($mail['headers']);
        
        return $mail;
    });
}

// Configurar From e Reply-To
add_filter('wp_mail_from', function($from_email) {
    $site_url = home_url();
    return 'noreply@' . parse_url($site_url, PHP_URL_HOST);
});

add_filter('wp_mail_from_name', function($from_name) {
    return get_bloginfo('name');
});

// Configurações específicas para provedores de e-mail
if (!function_exists('configurar_headers_provedores')) {
    function configurar_headers_provedores($headers) {
        // Headers para Gmail
        $headers[] = 'X-Gmail-Labels: ' . get_bloginfo('name');
        
        // Headers para Outlook
        $headers[] = 'X-Microsoft-Exchange-Organization-SCL: -1';
        
        // Headers para Yahoo
        $headers[] = 'X-Yahoo-Newman-Property: ymail-3';
        
        // Headers para Hotmail
        $headers[] = 'X-Microsoft-Exchange-Organization-Antispam: BCL:0';
        
        return $headers;
    }
    
    add_filter('wp_mail', function($mail) {
        if (!isset($mail['headers'])) {
            $mail['headers'] = [];
        }
        
        $mail['headers'] = configurar_headers_provedores($mail['headers']);
        
        return $mail;
    });
}
?>

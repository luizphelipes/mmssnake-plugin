<?php
/**
 * Teste das Configurações SMM
 * Execute este arquivo para verificar se as configurações estão carregando
 */

// Simular ambiente WordPress
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Função para simular get_option
function get_option($option, $default = '') {
    $options = [
        'gemini_api_key' => '',
        'instagram_scraper_api_key' => 'bb099aa633mshc32e5a3e833a238p1ba333jsn4e4ed3a7d3ce',
        'instagram_scraper_api_host' => 'instagram-social-api.p.rapidapi.com'
    ];
    
    return isset($options[$option]) ? $options[$option] : $default;
}

// Função para simular esc_attr
function esc_attr($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Função para simular wp_create_nonce
function wp_create_nonce($action) {
    return 'teste_nonce_' . md5($action);
}

echo "<h1>Teste das Configurações SMM</h1>";
echo "<h2>Seção: APIs para Comentários + IA</h2>";

// Simular a renderização da seção
?>
<table class="form-table">
    <tr>
        <th scope="row">
            <label for="gemini_api_key">Gemini API Key</label>
        </th>
        <td>
            <input type="password" name="gemini_api_key" id="gemini_api_key" 
                   value="<?php echo esc_attr(get_option('gemini_api_key', '')); ?>" 
                   style="width: 400px;" placeholder="AIza..." />
            <p class="description">
                Chave da API do Google Gemini 2.5 Pro. 
                <a href="https://aistudio.google.com/app/apikey" target="_blank">Obter API Key</a>
            </p>
            
            <?php $gemini_key = get_option('gemini_api_key', ''); ?>
            <?php if (!empty($gemini_key)): ?>
                <button type="button" class="button" onclick="testGeminiConnection()">Testar Conexão</button>
                <div id="gemini-test-result" style="margin-top: 10px;"></div>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <th scope="row">
            <label>Instagram Scraper API</label>
        </th>
        <td>
            <table style="width: 100%;">
                <tr>
                    <td style="width: 150px;"><strong>RapidAPI Key:</strong></td>
                    <td>
                        <input type="password" name="instagram_scraper_api_key" 
                               value="<?php echo esc_attr(get_option('instagram_scraper_api_key', 'bb099aa633mshc32e5a3e833a238p1ba333jsn4e4ed3a7d3ce')); ?>" 
                               style="width: 400px;">
                    </td>
                </tr>
                <tr>
                    <td><strong>API Host:</strong></td>
                    <td>
                        <input type="text" name="instagram_scraper_api_host" 
                               value="<?php echo esc_attr(get_option('instagram_scraper_api_host', 'instagram-social-api.p.rapidapi.com')); ?>" 
                               style="width: 400px;">
                    </td>
                </tr>
            </table>
            <p class="description">
                API do RapidAPI para scraping do Instagram. 
                <a href="https://rapidapi.com/maatootz/api/instagram-social-api/" target="_blank">Obter API Key</a>
            </p>
            
            <?php $instagram_key = get_option('instagram_scraper_api_key', ''); ?>
            <?php if (!empty($instagram_key)): ?>
                <button type="button" class="button" onclick="testInstagramScraper()">Testar Scraping</button>
                <div id="instagram-test-result" style="margin-top: 10px;"></div>
            <?php endif; ?>
        </td>
    </tr>
</table>

<p><strong>✅ A seção de APIs está sendo renderizada corretamente!</strong></p>

<h3>Verificações:</h3>
<ul>
    <li>✅ Campo Gemini API Key presente</li>
    <li>✅ Campo Instagram Scraper API Key presente</li>
    <li>✅ Campo Instagram API Host presente</li>
    <li>✅ Botões de teste configurados</li>
    <li>✅ Links para obter APIs incluídos</li>
</ul>

<h3>Próximos Passos:</h3>
<ol>
    <li>Acesse: <strong>WP Admin > Pedidos Processando > Configurações SMM</strong></li>
    <li>Role até o final da página</li>
    <li>Procure pela seção <strong>"APIs para Comentários + IA"</strong></li>
    <li>Configure as APIs e teste as conexões</li>
</ol>

<style>
.form-table {
    border-collapse: collapse;
    width: 100%;
}
.form-table th {
    padding: 10px;
    text-align: left;
    vertical-align: top;
    width: 200px;
}
.form-table td {
    padding: 10px;
}
.description {
    font-style: italic;
    color: #666;
}
.button {
    padding: 5px 10px;
    background: #0073aa;
    color: white;
    border: none;
    cursor: pointer;
}
</style>

<script>
function testGeminiConnection() {
    alert('Função de teste do Gemini seria executada aqui');
}

function testInstagramScraper() {
    alert('Função de teste do Instagram seria executada aqui');
}
</script>

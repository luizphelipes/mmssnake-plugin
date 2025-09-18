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
        add_action('wp_ajax_test_gemini_connection', [$this, 'ajax_test_gemini_connection']);
        add_action('wp_ajax_test_instagram_scraper', [$this, 'ajax_test_instagram_scraper']);
        add_action('wp_ajax_apply_smm_to_variations', [$this, 'ajax_apply_smm_to_variations']);
        
        // Hook para preservar provedores durante salvamento
        add_action('update_option_smm_default_provider', [$this, 'preserve_providers_on_update'], 10, 2);
        add_action('update_option_smm_global_service_id', [$this, 'preserve_providers_on_update'], 10, 2);
        
        // Hook para preservar provedores antes do salvamento
        add_action('admin_init', [$this, 'preserve_providers_before_save']);
        
        // Hook para processar formulário personalizado
        add_action('admin_init', [$this, 'process_smm_settings_form']);
        
        // Hooks AJAX para gerenciar provedores
        // add_smm_provider e remove_smm_provider são gerenciados pelo providers-manager.php
        add_action('wp_ajax_get_smm_nonce', [$this, 'ajax_get_nonce']);
        
        // Hook para debug de metadados
        add_action('wp_ajax_debug_order_metadata', [$this, 'ajax_debug_order_metadata']);
        
        
        // Hooks removidos - sistema de mapeamento automático desabilitado
        
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
        register_setting('smm_options', 'smm_global_service_id');
        register_setting('smm_options', 'gemini_api_key');
        register_setting('smm_options', 'instagram_scraper_api_key');
        register_setting('smm_options', 'instagram_scraper_api_host');
        
        add_settings_section('smm_providers_section', 'Provedores SMM', [$this, 'render_providers_section'], 'smm-settings');
        add_settings_field('smm_providers_field', 'Provedores', [$this, 'render_providers_field'], 'smm-settings', 'smm_providers_section');
        add_settings_field('smm_default_provider_field', 'Provedor Padrão', [$this, 'render_default_provider_field'], 'smm-settings', 'smm_providers_section');
        add_settings_field('smm_global_service_id_field', 'Service ID Global', [$this, 'render_global_service_id_field'], 'smm-settings', 'smm_providers_section');
        
        add_settings_section('smm_ai_section', 'APIs para Comentários + IA', [$this, 'render_ai_section'], 'smm-settings');
        add_settings_field('gemini_api_key_field', 'Gemini API Key', [$this, 'render_gemini_api_key_field'], 'smm-settings', 'smm_ai_section');
        add_settings_field('instagram_scraper_api_field', 'Instagram Scraper API', [$this, 'render_instagram_scraper_api_field'], 'smm-settings', 'smm_ai_section');
    }
    
    /**
     * Renderizar página de configurações SMM
     */
    public function render_smm_settings_page() {
        ?>
        <div class="wrap">
            <h1>Configurações SMM</h1>
            
            <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true'): ?>
                <div class="notice notice-success is-dismissible">
                    <p>✅ <strong>Configurações salvas com sucesso!</strong> Os provedores foram preservados.</p>
                </div>
            <?php endif; ?>
            
            <div class="smm-test-section">
                <h2>Testar Provedores</h2>
                <div id="smm-test-results"></div>
            </div>
            
            <div class="smm-balance-section">
                <h2>Saldo dos Provedores</h2>
                <div id="smm-balance-results"></div>
            </div>
            
            <style>
            .smm-providers-list {
                margin: 15px 0;
            }
            .smm-provider-item {
                background: #f9f9f9;
                border: 1px solid #ddd;
                padding: 15px;
                margin: 10px 0;
                border-radius: 5px;
            }
            .smm-provider-actions {
                margin-top: 10px;
            }
            .smm-provider-actions .button {
                margin-right: 5px;
            }
            .smm-add-provider {
                background: #fff;
                border: 1px solid #ddd;
                padding: 20px;
                margin: 20px 0;
                border-radius: 5px;
            }
            .smm-add-provider h4 {
                margin-top: 0;
                color: #23282d;
            }
            </style>
            
            <script>
            // Definir ajaxurl para AJAX
            var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
            </script>
            
            <form method="post" action="">
                <?php
                wp_nonce_field('smm_settings_save', 'smm_settings_nonce');
                ?>
                <input type="hidden" name="action" value="save_smm_settings" />
                <input type="hidden" id="smm_provider_nonce" value="<?php echo wp_create_nonce('smm_provider_nonce'); ?>" />
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="smm_providers">Provedores SMM</label>
                        </th>
                        <td>
                            <div id="smm-providers-container">
                                <?php 
                                $providers = get_option('smm_providers', []);
                                if (!empty($providers)): 
                                ?>
                                    <div class="smm-providers-list">
                                        <?php foreach ($providers as $id => $provider): ?>
                                            <div class="smm-provider-item" data-provider-id="<?php echo esc_attr($id); ?>">
                                                <strong><?php echo esc_html($provider['name']); ?></strong>
                                                <br>
                                                <small>API Key: <?php echo esc_html(substr($provider['api_key'], 0, 10)) . '...'; ?></small>
                                                <br>
                                                <small>URL: <?php echo esc_html($provider['api_url']); ?></small>
                                                <div class="smm-provider-actions">
                                                    <button type="button" class="button button-small test-provider" data-provider-id="<?php echo esc_attr($id); ?>">Testar</button>
                                                    <button type="button" class="button button-small check-balance" data-provider-id="<?php echo esc_attr($id); ?>">Saldo</button>
                                                    <button type="button" class="button button-small remove-provider" data-provider-id="<?php echo esc_attr($id); ?>">Remover</button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p><em>Nenhum provedor configurado.</em></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="smm-add-provider">
                                <h4>Adicionar Novo Provedor</h4>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">Nome do Provedor</th>
                                        <td>
                                            <input type="text" id="new-provider-name" placeholder="Ex: SMM Panel" class="regular-text" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">URL da API</th>
                                        <td>
                                            <input type="url" id="new-provider-url" placeholder="https://exemplo.com/api/v2" class="regular-text" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">API Key</th>
                                        <td>
                                            <input type="text" id="new-provider-key" placeholder="Sua chave API aqui" class="regular-text" />
                                        </td>
                                    </tr>
                                </table>
                                <button type="button" id="add-provider-btn" class="button button-primary">✅ Adicionar Provedor</button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="smm_default_provider">Provedor Padrão</label>
                        </th>
                        <td>
                            <select name="smm_default_provider" id="smm_default_provider">
                                <option value="">Selecione um provedor padrão</option>
                                <?php 
                                $default_provider = get_option('smm_default_provider', '');
                                foreach ($providers as $id => $provider): 
                                ?>
                                    <option value="<?php echo esc_attr($id); ?>" <?php selected($default_provider, $id); ?>>
                                        <?php echo esc_html($provider['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Este provedor será usado para todos os pedidos SMM.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="smm_global_service_id">Service ID Global</label>
                        </th>
                        <td>
                            <input type="text" name="smm_global_service_id" id="smm_global_service_id" 
                                   value="<?php echo esc_attr(get_option('smm_global_service_id', '')); ?>" 
                                   class="regular-text" placeholder="Ex: 4420" />
                            <p class="description">Service ID único que será usado para todos os pedidos SMM.</p>
                        </td>
                    </tr>
                </table>
                
                <h2>APIs para Comentários + IA</h2>
                <p>Configure as APIs necessárias para a funcionalidade de Comentários + IA.</p>
                
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
                
                <p class="submit">
                    <button type="submit" class="button button-primary">Salvar Configurações</button>
                </p>
            </form>
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
            
            // Remover provedor
            $('.remove-provider').on('click', function() {
                const providerId = $(this).data('provider-id');
                if (confirm('Tem certeza que deseja remover este provedor?')) {
                    removeProvider(providerId);
                }
            });
            
            // Adicionar provedor
            $('#add-provider-btn').on('click', function() {
                addProvider();
            });
            
            
            function addProvider() {
                const name = $('#new-provider-name').val().trim();
                const url = $('#new-provider-url').val().trim();
                const key = $('#new-provider-key').val().trim();
                
                if (!name || !url || !key) {
                    alert('Por favor, preencha todos os campos.');
                    return;
                }
                
                // Usar nonce direto para adicionar provedor
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
                    },
                    error: function() {
                        alert('Erro na requisição AJAX.');
                    }
                });
            }
            
            
            
            function removeProvider(providerId) {
                // Usar nonce direto para remover provedor
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
                    },
                    error: function() {
                        alert('Erro na requisição AJAX.');
                    }
                });
            }
            
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
            
            // Função para testar conexão Gemini
            window.testGeminiConnection = function() {
                document.getElementById('gemini-test-result').innerHTML = 'Testando...';
                
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=test_gemini_connection&nonce=' + '<?php echo wp_create_nonce('test_gemini'); ?>'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('gemini-test-result').innerHTML = 
                            '<span style="color: green;">✅ Conexão OK</span>';
                    } else {
                        document.getElementById('gemini-test-result').innerHTML = 
                            '<span style="color: red;">❌ Erro: ' + data.data + '</span>';
                    }
                })
                .catch(error => {
                    document.getElementById('gemini-test-result').innerHTML = 
                        '<span style="color: red;">❌ Erro de conexão</span>';
                });
            };
            
            // Função para testar scraping Instagram
            window.testInstagramScraper = function() {
                document.getElementById('instagram-test-result').innerHTML = 'Testando...';
                
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=test_instagram_scraper&nonce=' + '<?php echo wp_create_nonce('test_instagram'); ?>'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('instagram-test-result').innerHTML = 
                            '<span style="color: green;">✅ Scraping OK</span>';
                    } else {
                        document.getElementById('instagram-test-result').innerHTML = 
                            '<span style="color: red;">❌ Erro: ' + data.data + '</span>';
                    }
                })
                .catch(error => {
                    document.getElementById('instagram-test-result').innerHTML = 
                        '<span style="color: red;">❌ Erro de conexão</span>';
                });
            };
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
        
        // Campo hidden para preservar os provedores existentes
        // Usar um nome único para evitar conflitos
        echo '<input type="hidden" name="smm_providers_preserve" value="' . esc_attr(json_encode($providers)) . '" />';
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
                
                // Usar função centralizada
                addProvider();
            });
            
            $('.remove-provider').on('click', function() {
                if (confirm('Tem certeza que deseja remover este provedor?')) {
                    const providerId = $(this).data('provider-id');
                    removeProvider(providerId);
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
        <p class="description">Este provedor será usado para todos os pedidos SMM.</p>
        <?php
    }
    
    /**
     * Renderizar campo de Service ID global
     */
    public function render_global_service_id_field() {
        $global_service_id = get_option('smm_global_service_id', '');
        $default_provider = get_option('smm_default_provider', '');
        
        ?>
        <input type="text" name="smm_global_service_id" value="<?php echo esc_attr($global_service_id); ?>" class="regular-text" placeholder="Ex: 4420" />
        <p class="description">
            <strong>Service ID único que será usado para todos os pedidos SMM.</strong><br>
            <?php if (!empty($default_provider)): ?>
                <button type="button" class="button button-secondary" id="get-global-services" style="margin-top: 5px;">
                    <span class="dashicons dashicons-search"></span> Buscar Serviços do Provedor Padrão
                </button>
            <?php else: ?>
                <em>Configure um provedor padrão para buscar serviços disponíveis.</em>
            <?php endif; ?>
        </p>
        
        <div id="global-services-list" style="margin-top: 10px; display: none;">
            <h4>Serviços Disponíveis:</h4>
            <div id="global-services-content" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#get-global-services').on('click', function() {
                const providerId = '<?php echo esc_js($default_provider); ?>';
                if (!providerId) {
                    alert('Configure um provedor padrão primeiro');
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
                            displayGlobalServices(response.data);
                        } else {
                            alert('Erro ao buscar serviços: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('Erro na conexão');
                    },
                    complete: function() {
                        $('#get-global-services').html('<span class="dashicons dashicons-search"></span> Buscar Serviços do Provedor Padrão').prop('disabled', false);
                    }
                });
            });
            
            function displayGlobalServices(services) {
                let html = '';
                
                services.forEach(function(service) {
                    html += `
                        <div style="border-bottom: 1px solid #eee; padding: 8px 0; cursor: pointer;" 
                             onclick="selectGlobalService('${service.service}')">
                            <strong>ID: ${service.service}</strong><br>
                            <span style="color: #666;">${service.name}</span><br>
                            <small style="color: #999;">Preço: ${service.rate} | Mín: ${service.min} | Máx: ${service.max}</small>
                        </div>
                    `;
                });
                
                $('#global-services-content').html(html);
                $('#global-services-list').show();
            }
            
            window.selectGlobalService = function(serviceId) {
                $('input[name="smm_global_service_id"]').val(serviceId);
                $('#global-services-list').hide();
                alert('Service ID ' + serviceId + ' selecionado!');
            };
        });
        </script>
        <?php
    }
    
    /**
     * Adicionar meta box SMM no produto
     */
    public function add_product_smm_meta_box() {
        // Meta box para produtos principais
        add_meta_box(
            'product_smm_settings',
            'Configurações SMM',
            [$this, 'render_product_smm_meta_box'],
            'product',
            'side',
            'default'
        );
        
        // Meta box para variações (herda configurações do pai)
        add_meta_box(
            'product_smm_settings',
            'Configurações SMM',
            [$this, 'render_product_smm_meta_box'],
            'product_variation',
            'side',
            'default'
        );
    }
    
    /**
     * Renderizar meta box SMM do produto
     */
    public function render_product_smm_meta_box($post) {
        wp_nonce_field('product_smm_meta_box', 'product_smm_meta_box_nonce');
        
        // Verificar se é uma variação
        $is_variation = (get_post_type($post->ID) === 'product_variation');
        $parent_id = null;
        
        if ($is_variation) {
            $product = wc_get_product($post->ID);
            if ($product && $product->is_type('variation')) {
                $parent_id = $product->get_parent_id();
            }
        }
        
        // Obter configurações (do produto atual ou do pai se for variação)
        $actual_product_id = $is_variation && $parent_id ? $parent_id : $post->ID;
        
        // Verificar se é produto variável (para mostrar botão de aplicar)
        $is_variable_product = false;
        if (!$is_variation) {
            $product = wc_get_product($post->ID);
            $is_variable_product = $product && $product->is_type('variable');
        }
        
        $smm_enabled = get_post_meta($actual_product_id, '_smm_enabled', true);
        $smm_provider = get_post_meta($actual_product_id, '_smm_provider', true);
        $smm_service_id = get_post_meta($actual_product_id, '_smm_service_id', true);
        $smm_service_id_br = get_post_meta($actual_product_id, '_smm_service_id_br', true);
        $smm_service_id_internacional = get_post_meta($actual_product_id, '_smm_service_id_internacional', true);
        $smm_logic_type = get_post_meta($actual_product_id, '_smm_logic_type', true);
        
        $providers = get_option('smm_providers', []);
        
        // Garantir que $providers seja um array
        if (!is_array($providers)) {
            $providers = [];
        }
        ?>
        <div class="smm-product-settings">
            <?php if ($is_variation && $parent_id): ?>
                <div style="background: #f0f8ff; border: 1px solid #0073aa; padding: 10px; margin-bottom: 15px; border-radius: 4px;">
                    <h4 style="margin: 0 0 5px 0; color: #0073aa;">🔄 Variação - Herança de Configurações</h4>
                    <p style="margin: 0; font-size: 12px; color: #666;">
                        Esta variação herda as configurações SMM do produto pai: 
                        <strong><?php echo esc_html(get_the_title($parent_id)); ?></strong>
                    </p>
                    <p style="margin: 5px 0 0 0; font-size: 11px; color: #999;">
                        Para alterar as configurações, edite o produto pai.
                    </p>
                </div>
            <?php endif; ?>
            
            <p>
                <label>
                    <input type="checkbox" name="smm_enabled" value="1" <?php checked($smm_enabled, '1'); ?> 
                           <?php echo $is_variation ? 'disabled' : ''; ?> />
                    <strong>Ativar envio automático SMM</strong>
                    <?php if ($is_variation): ?>
                        <small style="color: #666; display: block; margin-top: 5px;">
                            (Configurado no produto pai)
                        </small>
                    <?php endif; ?>
                </label>
            </p>
            
            <p>
                <label for="smm_logic_type"><strong>Tipo de Lógica:</strong></label><br>
                <select name="smm_logic_type" id="smm_logic_type" style="width: 100%;" 
                        <?php echo $is_variation ? 'disabled' : ''; ?>>
                    <option value="">Selecione o tipo</option>
                    <option value="followers" <?php selected($smm_logic_type, 'followers'); ?>>👥 Seguidores (Username)</option>
                    <option value="posts_reels" <?php selected($smm_logic_type, 'posts_reels'); ?>>📱 Posts/Reels (Links)</option>
                    <option value="comentarios_ia" <?php selected($smm_logic_type, 'comentarios_ia'); ?>>💬 Comentários + IA (Links)</option>
                </select>
                <small style="color: #666; display: block; margin-top: 5px;">
                    <strong>Seguidores:</strong> Usa username do Instagram<br>
                    <strong>Posts/Reels:</strong> Usa links de posts e reels<br>
                    <strong>Comentários + IA:</strong> Usa links com comentários inteligentes
                    <?php if ($is_variation): ?>
                        <br><em style="color: #999;">(Herdado do produto pai)</em>
                    <?php endif; ?>
                </small>
            </p>
            
            <?php if (!empty($providers)): ?>
                <p>
                    <label for="smm_provider"><strong>Provedor SMM:</strong></label><br>
                    <select name="smm_provider" id="smm_provider" style="width: 100%;" 
                            <?php echo $is_variation ? 'disabled' : ''; ?>>
                        <option value="">Selecione um provedor</option>
                        <?php foreach ($providers as $id => $provider): ?>
                            <option value="<?php echo esc_attr($id); ?>" <?php selected($smm_provider, $id); ?>>
                                <?php echo esc_html($provider['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($is_variation): ?>
                        <small style="color: #666; display: block; margin-top: 5px;">
                            (Herdado do produto pai)
                        </small>
                    <?php endif; ?>
                </p>
                
                <p>
                    <label for="smm_service_id"><strong>Service ID (Padrão):</strong></label><br>
                    <input type="text" name="smm_service_id" id="smm_service_id" 
                           value="<?php echo esc_attr($smm_service_id); ?>" 
                           style="width: 100%;" 
                           placeholder="ID do serviço padrão (fallback)"
                           <?php echo $is_variation ? 'disabled' : ''; ?> />
                    <small style="color: #666; display: block; margin-top: 2px;">
                        Service ID usado como fallback quando BR/Internacional não estão configurados
                    </small>
                </p>
                
                <div style="border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; background: #f9f9f9;">
                    <h4 style="margin: 0 0 10px 0; color: #0073aa;">🌍 Service IDs por Região</h4>
                    
                    <p>
                        <label for="smm_service_id_br"><strong>🇧🇷 Service ID Brasil:</strong></label><br>
                        <input type="text" name="smm_service_id_br" id="smm_service_id_br" 
                               value="<?php echo esc_attr($smm_service_id_br); ?>" 
                               style="width: 100%;" 
                               placeholder="ID do serviço para variações BR"
                               <?php echo $is_variation ? 'disabled' : ''; ?> />
                    </p>
                    
                    <p>
                        <label for="smm_service_id_internacional"><strong>🌎 Service ID Internacional:</strong></label><br>
                        <input type="text" name="smm_service_id_internacional" id="smm_service_id_internacional" 
                               value="<?php echo esc_attr($smm_service_id_internacional); ?>" 
                               style="width: 100%;" 
                               placeholder="ID do serviço para variações Internacionais"
                               <?php echo $is_variation ? 'disabled' : ''; ?> />
                    </p>
                    
                    <?php if (!$is_variation && $is_variable_product): ?>
                        <div style="margin-top: 15px; padding: 10px; background: #fff; border-radius: 3px;">
                            <button type="button" class="button button-primary" id="apply-to-variations" 
                                    style="width: 100%;">
                                🔄 Aplicar às Variações
                            </button>
                            <div id="apply-status" style="margin-top: 10px; text-align: center;"></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($is_variation): ?>
                        <small style="color: #666; display: block; margin-top: 10px;">
                            ℹ️ <strong>Variações herdam as configurações do produto pai.</strong><br>
                            O sistema detecta automaticamente se é BR ou Internacional.
                        </small>
                    <?php endif; ?>
                </div>
                
                <p>
                    <button type="button" class="button button-secondary" id="get-services" 
                            style="width: 100%;" 
                            <?php echo $is_variation ? 'disabled' : ''; ?>>
                        🔍 Buscar Serviços do Provedor
                    </button>
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
        
        // Aplicar configurações às variações
        $('#apply-to-variations').on('click', function() {
            const button = $(this);
            const statusDiv = $('#apply-status');
            
            if (!confirm('Deseja aplicar as configurações SMM do produto pai para todas as variações?\n\nIsso irá detectar automaticamente quais são BR ou Internacionais e aplicar os Service IDs correspondentes.')) {
                return;
            }
            
            button.prop('disabled', true).text('🔄 Aplicando...');
            statusDiv.html('<span style="color: #0073aa;">⏳ Processando variações...</span>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'apply_smm_to_variations',
                    product_id: <?php echo $post->ID; ?>,
                    nonce: '<?php echo wp_create_nonce('smm_apply_variations'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        statusDiv.html(`
                            <div style="color: #00a32a; background: #f0f8f0; padding: 8px; border-radius: 3px; border-left: 3px solid #00a32a;">
                                ✅ <strong>Aplicado com sucesso!</strong><br>
                                📊 Total: ${data.total_variations} variações<br>
                                🇧🇷 Brasil: ${data.br_count} variações<br>
                                🌎 Internacional: ${data.int_count} variações<br>
                                ⚙️ Configuradas: ${data.applied_count} variações
                            </div>
                        `);
                    } else {
                        statusDiv.html(`
                            <div style="color: #d63638; background: #f8f0f0; padding: 8px; border-radius: 3px; border-left: 3px solid #d63638;">
                                ❌ <strong>Erro:</strong> ${response.data || 'Erro desconhecido'}
                            </div>
                        `);
                    }
                },
                error: function() {
                    statusDiv.html(`
                        <div style="color: #d63638; background: #f8f0f0; padding: 8px; border-radius: 3px; border-left: 3px solid #d63638;">
                            ❌ <strong>Erro de conexão.</strong> Tente novamente.
                        </div>
                    `);
                },
                complete: function() {
                    button.prop('disabled', false).text('🔄 Aplicar às Variações');
                }
            });
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
        
        // Suportar tanto produtos quanto variações
        if (get_post_type($post_id) !== 'product' && get_post_type($post_id) !== 'product_variation') {
            return;
        }
        
        // Se for variação, não salvar (herda do pai)
        if (get_post_type($post_id) === 'product_variation') {
            return;
        }
        
        $smm_enabled = isset($_POST['smm_enabled']) ? '1' : '0';
        $smm_provider = sanitize_text_field($_POST['smm_provider'] ?? '');
        $smm_service_id = sanitize_text_field($_POST['smm_service_id'] ?? '');
        $smm_service_id_br = sanitize_text_field($_POST['smm_service_id_br'] ?? '');
        $smm_service_id_internacional = sanitize_text_field($_POST['smm_service_id_internacional'] ?? '');
        $smm_logic_type = sanitize_text_field($_POST['smm_logic_type'] ?? '');
        
        update_post_meta($post_id, '_smm_enabled', $smm_enabled);
        update_post_meta($post_id, '_smm_provider', $smm_provider);
        update_post_meta($post_id, '_smm_service_id', $smm_service_id);
        update_post_meta($post_id, '_smm_service_id_br', $smm_service_id_br);
        update_post_meta($post_id, '_smm_service_id_internacional', $smm_service_id_internacional);
        update_post_meta($post_id, '_smm_logic_type', $smm_logic_type);
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
     * Preservar provedores durante atualização de outras opções
     */
    public function preserve_providers_on_update($old_value, $new_value) {
        // Verificar se os provedores existem
        $current_providers = get_option('smm_providers', []);
        
        // Se não há provedores, não há nada para preservar
        if (empty($current_providers)) {
            return;
        }
        
        // Verificar se há provedores no campo hidden do formulário
        if (isset($_POST['smm_providers_preserve'])) {
            $preserved_providers = json_decode(stripslashes($_POST['smm_providers_preserve']), true);
            
            // Se os provedores preservados são válidos, restaurar
            if (is_array($preserved_providers) && !empty($preserved_providers)) {
                update_option('smm_providers', $preserved_providers);
                return;
            }
        }
        
        // Se não há campo hidden, verificar se os provedores foram perdidos
        $updated_providers = get_option('smm_providers', []);
        
        // Se os provedores foram perdidos, restaurar
        if (empty($updated_providers) && !empty($current_providers)) {
            update_option('smm_providers', $current_providers);
        }
    }
    
    /**
     * Hook para preservar provedores antes do salvamento
     */
    public function preserve_providers_before_save() {
        // Verificar se estamos na página de configurações SMM
        if (!isset($_POST['option_page']) || $_POST['option_page'] !== 'smm_options') {
            return;
        }
        
        // Verificar se há provedores para preservar
        $current_providers = get_option('smm_providers', []);
        if (empty($current_providers)) {
            return;
        }
        
        // Salvar provedores temporariamente em uma opção temporária
        update_option('smm_providers_temp', $current_providers);
        
        // Adicionar hook para restaurar após o salvamento
        add_action('update_option_smm_default_provider', [$this, 'restore_providers_after_save'], 999);
        add_action('update_option_smm_global_service_id', [$this, 'restore_providers_after_save'], 999);
    }
    
    /**
     * Restaurar provedores após salvamento
     */
    public function restore_providers_after_save() {
        // Restaurar provedores da opção temporária
        $temp_providers = get_option('smm_providers_temp', []);
        if (!empty($temp_providers)) {
            update_option('smm_providers', $temp_providers);
            delete_option('smm_providers_temp');
        }
    }
    
    /**
     * Processar formulário de configurações SMM
     */
    public function process_smm_settings_form() {
        // Verificar se é o formulário SMM
        if (!isset($_POST['action']) || $_POST['action'] !== 'save_smm_settings') {
            return;
        }
        
        // Verificar nonce
        if (!wp_verify_nonce($_POST['smm_settings_nonce'], 'smm_settings_save')) {
            wp_die('Erro de segurança');
        }
        
        // Verificar permissões
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Permissão negada');
        }
        
        // Preservar provedores existentes
        $current_providers = get_option('smm_providers', []);
        
        // Processar provedor padrão
        if (isset($_POST['smm_default_provider'])) {
            $default_provider = sanitize_text_field($_POST['smm_default_provider']);
            update_option('smm_default_provider', $default_provider);
        }
        
        // Processar Service ID Global
        if (isset($_POST['smm_global_service_id'])) {
            $global_service_id = sanitize_text_field($_POST['smm_global_service_id']);
            update_option('smm_global_service_id', $global_service_id);
        }
        
        // Processar configurações das APIs de IA
        if (isset($_POST['gemini_api_key'])) {
            $gemini_api_key = sanitize_text_field($_POST['gemini_api_key']);
            update_option('gemini_api_key', $gemini_api_key);
        }
        
        if (isset($_POST['instagram_scraper_api_key'])) {
            $instagram_api_key = sanitize_text_field($_POST['instagram_scraper_api_key']);
            update_option('instagram_scraper_api_key', $instagram_api_key);
        }
        
        if (isset($_POST['instagram_scraper_api_host'])) {
            $instagram_api_host = sanitize_text_field($_POST['instagram_scraper_api_host']);
            update_option('instagram_scraper_api_host', $instagram_api_host);
        }
        
        // Garantir que os provedores não foram perdidos
        if (!empty($current_providers)) {
            update_option('smm_providers', $current_providers);
        }
        
        // Redirecionar com mensagem de sucesso
        wp_redirect(add_query_arg('settings-updated', 'true', admin_url('admin.php?page=smm-settings')));
        exit;
    }
    
    // Função removida - usando a do providers-manager.php para evitar duplicação
    
    // Função removida - usando a do providers-manager.php para evitar duplicação
    
    
    
    /**
     * AJAX: Obter nonce atualizado
     */
    public function ajax_get_nonce() {
        // Verificar permissões
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permissão negada');
        }
        
        // Gerar novo nonce
        $nonce = wp_create_nonce('smm_provider_nonce');
        wp_send_json_success($nonce);
    }
    
    /**
     * AJAX: Debug de metadados do pedido
     */
    public function ajax_debug_order_metadata() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permissão negada');
        }
        
        $order_id = intval($_POST['order_id'] ?? 0);
        if (!$order_id) {
            wp_send_json_error('ID do pedido não fornecido');
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error('Pedido não encontrado');
        }
        
        $debug_info = [
            'order_id' => $order_id,
            'items' => []
        ];
        
        foreach ($order->get_items() as $item_id => $item) {
            $item_debug = [
                'item_id' => $item_id,
                'product_id' => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'name' => $item->get_name(),
                'metadata' => []
            ];
            
            // Buscar todos os metadados do item
            $all_meta = wc_get_order_item_meta($item_id, '', false);
            foreach ($all_meta as $meta) {
                if (is_object($meta) && isset($meta->key, $meta->value)) {
                    $item_debug['metadata'][$meta->key] = $meta->value;
                }
            }
            
            // Buscar metadados específicos do Instagram
            $instagram_fields = [
                'Instagram Reels',
                'Instagram Posts', 
                'instagram_reels',
                'instagram_posts',
                'Instagram',
                'instagram'
            ];
            
            foreach ($instagram_fields as $field) {
                $value = wc_get_order_item_meta($item_id, $field, true);
                if (!empty($value)) {
                    $item_debug['instagram_fields'][$field] = $value;
                }
            }
            
            $debug_info['items'][] = $item_debug;
        }
        
        wp_send_json_success($debug_info);
    }
    
    /**
     * Verificar se produto tem SMM ativado
     * Implementa herança de configurações para variações
     */
    public static function is_product_smm_enabled($product_id) {
        $actual_product_id = self::get_parent_product_id($product_id);
        return get_post_meta($actual_product_id, '_smm_enabled', true) === '1';
    }
    
    /**
     * Obter provedor do produto
     * Implementa herança de configurações para variações
     */
    public static function get_product_provider($product_id) {
        $actual_product_id = self::get_parent_product_id($product_id);
        return get_post_meta($actual_product_id, '_smm_provider', true);
    }
    
    /**
     * Obter service ID do produto
     * Implementa herança de configurações para variações e mapeamento BR/Internacional
     */
    public static function get_product_service_id($product_id) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return get_post_meta($product_id, '_smm_service_id', true);
        }
        
        // Se for uma variação, usar o mapeamento automático
        if ($product->is_type('variation')) {
            // Verificar se a variação já tem Service ID específico
            $variation_service_id = get_post_meta($product_id, '_smm_service_id', true);
            if (!empty($variation_service_id)) {
                return $variation_service_id;
            }
            
            // Usar mapeamento automático
            if (class_exists('SMMVariationMapper')) {
                $mapper = new SMMVariationMapper();
                $mapped_service_id = $mapper->get_service_id_for_variation($product_id);
                
                if (!empty($mapped_service_id)) {
                    return $mapped_service_id;
                }
            }
        }
        
        // Para produtos simples ou fallback, usar o ID do produto pai
        $actual_product_id = self::get_parent_product_id($product_id);
        return get_post_meta($actual_product_id, '_smm_service_id', true);
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
                    pedidos_debug_log("Variação #{$product_id} detectada, usando configurações do produto pai #{$produto_pai->get_id()}", 'SMM_MODULE');
                }
                return $produto_pai->get_id();
            }
        }
        
        if (function_exists('pedidos_debug_log')) {
            pedidos_debug_log("Produto #{$product_id} não é variação, usando ID original", 'SMM_MODULE');
        }
        
        return $product_id;
    }
    
    /**
     * Obter provedor padrão global
     */
    public static function get_global_provider() {
        return get_option('smm_default_provider', '');
    }
    
    /**
     * Obter service ID global
     */
    public static function get_global_service_id() {
        return get_option('smm_global_service_id', '');
    }
    
    /**
     * Verificar se configuração global está completa
     */
    public static function is_global_config_complete() {
        $provider = self::get_global_provider();
        $service_id = self::get_global_service_id();
        
        return !empty($provider) && !empty($service_id);
    }
    
    /**
     * Renderizar seção de APIs para IA
     */
    public function render_ai_section() {
        echo '<p>Configure as APIs necessárias para a funcionalidade de Comentários + IA.</p>';
    }
    
    /**
     * Renderizar campo Gemini API Key
     */
    public function render_gemini_api_key_field() {
        $api_key = get_option('gemini_api_key', '');
        ?>
        <input type="password" name="gemini_api_key" value="<?php echo esc_attr($api_key); ?>" 
               style="width: 400px;" placeholder="AIza...">
        <p class="description">
            Chave da API do Google Gemini 2.5 Pro. 
            <a href="https://aistudio.google.com/app/apikey" target="_blank">Obter API Key</a>
        </p>
        
        <?php if (!empty($api_key)): ?>
            <button type="button" class="button" onclick="testGeminiConnection()">Testar Conexão</button>
            <div id="gemini-test-result" style="margin-top: 10px;"></div>
        <?php endif; ?>
        
        <script>
        function testGeminiConnection() {
            document.getElementById('gemini-test-result').innerHTML = 'Testando...';
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=test_gemini_connection&nonce=' + '<?php echo wp_create_nonce('test_gemini'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('gemini-test-result').innerHTML = 
                        '<span style="color: green;">✅ Conexão OK</span>';
                } else {
                    document.getElementById('gemini-test-result').innerHTML = 
                        '<span style="color: red;">❌ Erro: ' + data.data + '</span>';
                }
            })
            .catch(error => {
                document.getElementById('gemini-test-result').innerHTML = 
                    '<span style="color: red;">❌ Erro de conexão</span>';
            });
        }
        </script>
        <?php
    }
    
    /**
     * Renderizar campos Instagram Scraper API
     */
    public function render_instagram_scraper_api_field() {
        $api_key = get_option('instagram_scraper_api_key', 'bb099aa633mshc32e5a3e833a238p1ba333jsn4e4ed3a7d3ce');
        $api_host = get_option('instagram_scraper_api_host', 'instagram-social-api.p.rapidapi.com');
        ?>
        <table style="width: 100%;">
            <tr>
                <td style="width: 150px;"><strong>RapidAPI Key:</strong></td>
                <td>
                    <input type="password" name="instagram_scraper_api_key" 
                           value="<?php echo esc_attr($api_key); ?>" style="width: 400px;">
                </td>
            </tr>
            <tr>
                <td><strong>API Host:</strong></td>
                <td>
                    <input type="text" name="instagram_scraper_api_host" 
                           value="<?php echo esc_attr($api_host); ?>" style="width: 400px;">
                </td>
            </tr>
        </table>
        <p class="description">
            API do RapidAPI para scraping do Instagram. 
            <a href="https://rapidapi.com/maatootz/api/instagram-social-api/" target="_blank">Obter API Key</a>
        </p>
        
        <?php if (!empty($api_key)): ?>
            <button type="button" class="button" onclick="testInstagramScraper()">Testar Scraping</button>
            <div id="instagram-test-result" style="margin-top: 10px;"></div>
        <?php endif; ?>
        
        <script>
        function testInstagramScraper() {
            document.getElementById('instagram-test-result').innerHTML = 'Testando...';
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=test_instagram_scraper&nonce=' + '<?php echo wp_create_nonce('test_instagram'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('instagram-test-result').innerHTML = 
                        '<span style="color: green;">✅ Scraping OK</span>';
                } else {
                    document.getElementById('instagram-test-result').innerHTML = 
                        '<span style="color: red;">❌ Erro: ' + data.data + '</span>';
                }
            })
            .catch(error => {
                document.getElementById('instagram-test-result').innerHTML = 
                    '<span style="color: red;">❌ Erro de conexão</span>';
            });
        }
        </script>
        <?php
    }
    
    /**
     * AJAX: Testar conexão com Gemini
     */
    public function ajax_test_gemini_connection() {
        if (!wp_verify_nonce($_POST['nonce'], 'test_gemini')) {
            wp_send_json_error('Erro de segurança');
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permissão negada');
        }
        
        try {
            $generator = new GeminiCommentsGenerator();
            $result = $generator->test_connection();
            
            if ($result) {
                wp_send_json_success('Conexão com Gemini estabelecida com sucesso');
            } else {
                wp_send_json_error('Falha na conexão com Gemini');
            }
        } catch (Exception $e) {
            wp_send_json_error('Erro: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Testar scraping do Instagram
     */
    public function ajax_test_instagram_scraper() {
        if (!wp_verify_nonce($_POST['nonce'], 'test_instagram')) {
            wp_send_json_error('Erro de segurança');
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permissão negada');
        }
        
        try {
            $scraper = new InstagramScraper();
            $result = $scraper->test_connection();
            
            if ($result) {
                wp_send_json_success('Scraping do Instagram funcionando corretamente');
            } else {
                wp_send_json_error('Falha no scraping do Instagram');
            }
        } catch (Exception $e) {
            wp_send_json_error('Erro: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Aplicar configurações SMM às variações
     */
    public function ajax_apply_smm_to_variations() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'smm_apply_variations')) {
            wp_send_json_error('Erro de segurança');
        }
        
        // Verificar permissões
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permissão negada');
        }
        
        // Obter ID do produto
        $product_id = intval($_POST['product_id'] ?? 0);
        if (!$product_id) {
            wp_send_json_error('ID do produto não fornecido');
        }
        
        // Verificar se o produto existe e é variável
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('variable')) {
            wp_send_json_error('Produto não é variável ou não existe');
        }
        
        try {
            // Aplicar mapeamento usando a classe SMMVariationMapper
            if (!class_exists('SMMVariationMapper')) {
                wp_send_json_error('Classe SMMVariationMapper não encontrada');
            }
            
            $mapper = new SMMVariationMapper();
            $result = $mapper->apply_to_all_variations($product_id);
            
            if ($result === false) {
                wp_send_json_error('Falha ao aplicar configurações às variações');
            }
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            wp_send_json_error('Erro: ' . $e->getMessage());
        }
    }

}

// Inicializar o módulo SMM
new SMMModule();

<?php
/**
 * Plugin Name: Pedidos em Processamento - WooCommerce
 * Description: Plugin para listar e gerenciar pedidos do WooCommerce com status "processando"
 * Version: 1.0.0
 * Author: Desenvolvedor
 * Text Domain: pedidos-processando
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// ============================================================================
// SISTEMA DE LOG DE DEBUG
// ============================================================================

// Ativar/desativar debug (mude para true para ativar)
define('PEDIDOS_DEBUG', true);

// Função para log de debug
function pedidos_debug_log($message, $type = 'INFO', $context = '') {
    if (!PEDIDOS_DEBUG) {
        return;
    }
    
    $timestamp = current_time('Y-m-d H:i:s');
    $context_str = $context ? " [{$context}]" : '';
    $log_message = "[{$timestamp}] [{$type}]{$context_str}: {$message}";
    
    // Log para arquivo
    $log_file = WP_CONTENT_DIR . '/debug-pedidos-plugin.log';
    error_log($log_message . PHP_EOL, 3, $log_file);
    
    // Log para error_log do WordPress
    error_log("PEDIDOS_DEBUG: {$log_message}");
    
    // Log para console do navegador (se estiver no admin)
    if (is_admin() && !wp_doing_ajax() && !defined('WP_PLUGIN_DIR')) {
        $safe_message = esc_js($message);
        echo "<script>if(typeof console !== 'undefined') console.log('PEDIDOS_DEBUG: {$safe_message}');</script>";
    }
}

// Função para log de erro
function pedidos_error_log($message, $context = '') {
    pedidos_debug_log($message, 'ERROR', $context);
}

// Função para log de sucesso
function pedidos_success_log($message, $context = '') {
    pedidos_debug_log($message, 'SUCCESS', $context);
}

// Função para log de warning
function pedidos_warning_log($message, $context = '') {
    pedidos_debug_log($message, 'WARNING', $context);
}

// Função para log de passo
function pedidos_step_log($step, $context = '') {
    pedidos_debug_log("PASSO: {$step}", 'STEP', $context);
}

// Função para log de dados
function pedidos_data_log($data, $context = '') {
    if (is_array($data) || is_object($data)) {
        $data_str = print_r($data, true);
    } else {
        $data_str = $data;
    }
    pedidos_debug_log("DADOS: {$data_str}", 'DATA', $context);
}

// Função para log específico da API SMM (arquivo separado)
function pedidos_api_smm_log($message, $context = '') {
    if (!defined('PEDIDOS_DEBUG') || !PEDIDOS_DEBUG) {
        return;
    }
    
    $timestamp = current_time('Y-m-d H:i:s');
    $context_str = $context ? " [{$context}]" : '';
    $log_message = "[{$timestamp}] PEDIDOS_API_SMM{$context_str}: {$message}";
    
    // Salvar em arquivo separado
    $log_file = WP_CONTENT_DIR . '/debug-api-smm.log';
    error_log($log_message . PHP_EOL, 3, $log_file);
    
    // Também logar no WordPress se estiver ativo
    if (function_exists('error_log')) {
        error_log("PEDIDOS_API_SMM{$context_str}: {$message}");
    }
}

// ============================================================================
// VERIFICAÇÃO DO WOOCOMMERCE
// ============================================================================

// Verificar se o WooCommerce está ativo
function mmssnake_is_woocommerce_active() {
    pedidos_step_log('Verificando se o WooCommerce está ativo', 'WOOCOMMERCE_CHECK');
    
    // Verificar se o WooCommerce está ativo como plugin
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        pedidos_success_log('WooCommerce ativo como plugin', 'WOOCOMMERCE_CHECK');
        return true;
    }
    
    // Verificar se o WooCommerce está ativo como plugin de rede (multisite)
    if (is_multisite() && in_array('woocommerce/woocommerce.php', get_site_option('active_sitewide_plugins'))) {
        pedidos_success_log('WooCommerce ativo como plugin de rede', 'WOOCOMMERCE_CHECK');
        return true;
    }
    
    // Verificar se a classe WooCommerce existe
    if (class_exists('WooCommerce')) {
        pedidos_success_log('Classe WooCommerce encontrada', 'WOOCOMMERCE_CHECK');
        return true;
    }
    
    pedidos_error_log('WooCommerce não está ativo', 'WOOCOMMERCE_CHECK');
    return false;
}

if (!mmssnake_is_woocommerce_active()) {
    pedidos_error_log('WooCommerce não ativo - exibindo aviso e parando execução', 'PLUGIN_INIT');
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>O plugin <strong>Pedidos em Processamento</strong> requer o WooCommerce ativo.</p></div>';
    });
    return;
}

// ============================================================================
// CLASSE PRINCIPAL DO PLUGIN
// ============================================================================

class PedidosProcessandoPlugin {
    
    public function __construct() {
        pedidos_step_log('Iniciando construtor da classe PedidosProcessandoPlugin', 'PLUGIN_CONSTRUCTOR');
        
        try {
            // Registrar hooks
            pedidos_step_log('Registrando hooks do plugin', 'HOOKS_REGISTRATION');
            
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
            add_action('wp_ajax_atualizar_status_pedido', [$this, 'ajax_atualizar_status_pedido']);
            add_action('wp_ajax_buscar_pedidos_processados', [$this, 'ajax_buscar_pedidos_processados']);
            add_action('wp_ajax_limpar_pedidos', [$this, 'ajax_limpar_pedidos']);
            add_action('wp_ajax_testar_processamento', [$this, 'ajax_testar_processamento']);
            add_action('wp_ajax_testar_api_smm', [$this, 'ajax_testar_api_smm']);
            add_action('wp_ajax_verificar_status', [$this, 'ajax_verificar_status']);
            add_action('wp_ajax_verificar_pedidos_tabela', [$this, 'ajax_verificar_pedidos_tabela']);
        add_action('wp_ajax_processar_pedidos_pendentes_manual', [$this, 'ajax_processar_pedidos_pendentes_manual']);
            
            // Processamento automático de pedidos - capturar quando o pedido é criado
            add_action('woocommerce_checkout_order_processed', [$this, 'processar_pedido_automaticamente']);
            add_action('woocommerce_new_order', [$this, 'processar_pedido_automaticamente']);
            add_action('woocommerce_payment_complete', [$this, 'processar_pedido_automaticamente']);
            
                    // Hook para mudança de status (com prioridade baixa para evitar conflitos)
        add_action('woocommerce_order_status_changed', [$this, 'verificar_mudanca_status_pedido'], 20, 3);
        
        // Hook para pedidos criados (funciona para pedidos programáticos e checkout)
        add_action('woocommerce_order_created', [$this, 'processar_pedido_automaticamente']);
        
        add_action('init', [$this, 'agendar_processamento_pedidos']);
        add_action('processar_pedidos_pendentes', [$this, 'processar_pedidos_pendentes']);
        
        // Registrar cron personalizado de 2 minutos
        add_filter('cron_schedules', [$this, 'adicionar_cron_2_minutos']);
            
            // Adicionar campo Service ID no admin do produto
            add_action('woocommerce_product_options_general_product_data', [$this, 'adicionar_campo_service_id']);
            add_action('woocommerce_process_product_meta', [$this, 'salvar_campo_service_id']);
            
            // Teste do cron e solução alternativa
            add_action('admin_init', [$this, 'processar_pedidos_pendentes_ajax']);
            add_action('init', [$this, 'testar_cron_wordpress']);
            
            // Hook para página de confirmação de perfil público
            add_action('init', [$this, 'init_confirmacao_perfil']);
            
            pedidos_success_log('Todos os hooks registrados com sucesso', 'HOOKS_REGISTRATION');
            
        } catch (Exception $e) {
            pedidos_error_log('Erro no construtor: ' . $e->getMessage(), 'PLUGIN_CONSTRUCTOR');
        }
    }
    
    /**
     * Adicionar menu no admin
     */
    public function add_admin_menu() {
        pedidos_step_log('Tentando adicionar menu no admin', 'ADMIN_MENU');
        
        try {
            // Verificar se o usuário tem permissão
            if (!current_user_can('manage_woocommerce')) {
                pedidos_warning_log('Usuário não tem permissão manage_woocommerce', 'ADMIN_MENU');
                return;
            }
            
            add_menu_page(
                'Pedidos Processados',
                'Pedidos Processados',
                'manage_woocommerce',
                'pedidos-processando',
                [$this, 'render_admin_page'],
                'dashicons-yes-alt',
                56
            );
            
            pedidos_success_log('Menu admin adicionado com sucesso', 'ADMIN_MENU');
            
        } catch (Exception $e) {
            pedidos_error_log('Erro ao adicionar menu admin: ' . $e->getMessage(), 'ADMIN_MENU');
        }
    }
    
    /**
     * Carregar scripts e estilos do admin
     */
    public function enqueue_admin_scripts($hook) {
        pedidos_step_log("Carregando scripts para hook: {$hook}", 'ADMIN_SCRIPTS');
        
        if ($hook !== 'toplevel_page_pedidos-processando') {
            pedidos_step_log('Hook não corresponde à página do plugin, pulando carregamento', 'ADMIN_SCRIPTS');
            return;
        }
        
        try {
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_style('pedidos-processando-admin', plugin_dir_url(__FILE__) . 'assets/admin-style.css', [], '1.0.0');
            
            // Adicionar CSS inline para o status
            wp_add_inline_style('pedidos-processando-admin', '
                .pedidos-status {
                    background: #f9f9f9;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    padding: 15px;
                    margin-bottom: 20px;
                    display: flex;
                    justify-content: space-between;
                    flex-wrap: wrap;
                }
                
                .status-item {
                    flex: 1;
                    min-width: 200px;
                    margin: 5px;
                }
                
                .status-label {
                    font-weight: bold;
                    color: #555;
                    display: block;
                    margin-bottom: 5px;
                }
                
                .status-value {
                    color: #0073aa;
                    font-size: 14px;
                }
                
                #testar-processamento {
                    margin-left: 10px;
                }
            ');
            wp_enqueue_script('pedidos-processando-admin', plugin_dir_url(__FILE__) . 'assets/admin-script.js', ['jquery'], '1.0.0', true);
            
            wp_localize_script('pedidos-processando-admin', 'pedidos_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('pedidos_processando_nonce'),
                'strings' => [
                    'confirmar_atualizacao' => 'Tem certeza que deseja atualizar o status deste pedido?',
                    'erro_atualizacao' => 'Erro ao atualizar o status do pedido.',
                    'sucesso_atualizacao' => 'Status do pedido atualizado com sucesso!',
                    'carregando' => 'Carregando...',
                    'nenhum_pedido' => 'Nenhum pedido processado encontrado.',
                    'confirmar_limpeza' => 'Tem certeza que deseja limpar todos os pedidos e recarregar do WooCommerce? Esta ação irá:\n\n• Apagar TODOS os pedidos da tabela atual\n• Ler pedidos atuais do WooCommerce com status "processing"\n• Recriar a lista completa do zero\n\n⚠️ ATENÇÃO: Esta ação é irreversível e apagará todos os dados!',
                    'limpeza_sucesso' => 'Pedidos limpos e recarregados com sucesso!',
                    'limpeza_erro' => 'Erro ao limpar e recarregar pedidos.',
                    'limpeza_em_andamento' => 'Limpando e recarregando pedidos...',
                    'teste_processamento' => 'Testando processamento...',
                    'teste_sucesso' => 'Processamento testado com sucesso!',
                    'teste_erro' => 'Erro ao testar processamento.',
                    'teste_api_smm' => 'Testando API SMM...',
                    'teste_api_sucesso' => 'API SMM testada com sucesso!',
                    'teste_api_erro' => 'Erro ao testar API SMM.'
                ]
            ]);
            
            // Adicionar JavaScript inline para funcionalidades básicas
            wp_add_inline_script('pedidos-processando-admin', '
                jQuery(document).ready(function($) {
                    // Botão de teste de processamento
                    $("#testar-processamento").on("click", function() {
                        var $btn = $(this);
                        var originalText = $btn.text();
                        
                        $btn.prop("disabled", true).text(pedidos_ajax.strings.teste_processamento);
                        
                        $.ajax({
                            url: pedidos_ajax.ajax_url,
                            type: "POST",
                            data: {
                                action: "testar_processamento",
                                nonce: pedidos_ajax.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert(pedidos_ajax.strings.teste_sucesso + "\\n\\n" + response.data.message);
                                    // Recarregar a página para atualizar dados
                                    location.reload();
                                } else {
                                    alert("Erro: " + (response.data || "Erro desconhecido"));
                                }
                            },
                            error: function(xhr, status, error) {
                                alert("Erro na requisição: " + error);
                            },
                            complete: function() {
                                $btn.prop("disabled", false).text(originalText);
                            }
                        });
                    });
                    
                    // Botão de teste da API SMM
                    $("#testar-api-smm").on("click", function() {
                        var $btn = $(this);
                        var originalText = $btn.text();
                        
                        $btn.prop("disabled", true).text(pedidos_ajax.strings.teste_api_smm);
                        
                        $.ajax({
                            url: pedidos_ajax.ajax_url,
                            type: "POST",
                            data: {
                                action: "testar_api_smm",
                                nonce: pedidos_ajax.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert(pedidos_ajax.strings.teste_api_sucesso + "\\n\\n" + response.data.message);
                                } else {
                                    alert("Erro: " + (response.data || "Erro desconhecido"));
                                }
                            },
                            error: function(xhr, status, error) {
                                alert("Erro na requisição: " + error);
                            },
                            complete: function() {
                                $btn.prop("disabled", false).text(originalText);
                            }
                        });
                    });
                    
                    // Botão de teste de geração de comentários
                    $("#diagnosticar-conectividade").on("click", function() {
                        var $btn = $(this);
                        var originalText = $btn.text();
                        
                        $btn.prop("disabled", true).text("Diagnosticando...");
                        
                        $.ajax({
                            url: pedidos_ajax.ajax_url,
                            type: "POST",
                            data: {
                                action: "diagnosticar_conectividade",
                                nonce: pedidos_ajax.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert("Diagnóstico concluído! Verifique o log de debug para detalhes.");
                                } else {
                                    alert("Erro no diagnóstico: " + response.data);
                                }
                            },
                            error: function() {
                                alert("Erro na requisição de diagnóstico");
                            },
                            complete: function() {
                                $btn.prop("disabled", false).text(originalText);
                            }
                        });
                    });
                    
                    $("#testar-geracao-comentarios").on("click", function() {
                        var $btn = $(this);
                        var originalText = $btn.text();
                        
                        // Solicitar URL do Instagram
                        var instagram_url = prompt("Digite a URL do Instagram para testar a geração de comentários:\n\nExemplo: https://instagram.com/p/ABC123/");
                        
                        if (!instagram_url) {
                            return;
                        }
                        
                        // Validar URL do Instagram
                        if (!instagram_url.match(/instagram\.com\/(p|reel|tv)\/[a-zA-Z0-9_-]+/)) {
                            alert("URL inválida! Use uma URL do Instagram válida.\n\nExemplo: https://instagram.com/p/ABC123/");
                            return;
                        }
                        
                        // Solicitar quantidade de comentários
                        var quantidade = prompt("Quantos comentários deseja gerar? (1-20)", "10");
                        
                        if (!quantidade || isNaN(quantidade) || quantidade < 1 || quantidade > 20) {
                            alert("Quantidade inválida! Use um número entre 1 e 20.");
                            return;
                        }
                        
                        $btn.prop("disabled", true).text("Gerando comentários...");
                        
                        $.ajax({
                            url: pedidos_ajax.ajax_url,
                            type: "POST",
                            data: {
                                action: "testar_geracao_comentarios",
                                nonce: pedidos_ajax.nonce,
                                instagram_url: instagram_url,
                                quantidade: parseInt(quantidade)
                            },
                            success: function(response) {
                                if (response.success) {
                                    var dados = response.data;
                                    var mensagem = "✅ Comentários gerados com sucesso!\n\n";
                                    mensagem += "📱 URL: " + dados.instagram_url + "\n";
                                    mensagem += "🔢 Quantidade: " + dados.quantidade + "\n";
                                    mensagem += "🤖 Fonte: " + dados.fonte + "\n\n";
                                    mensagem += "💬 Comentários:\n";
                                    mensagem += dados.comentarios.join("\n");
                                    
                                    alert(mensagem);
                                } else {
                                    alert("❌ Erro: " + (response.data || "Erro desconhecido"));
                                }
                            },
                            error: function(xhr, status, error) {
                                alert("❌ Erro na requisição: " + error);
                            },
                            complete: function() {
                                $btn.prop("disabled", false).text(originalText);
                            }
                        });
                    });
                    
                    // Botão de verificar pedidos na tabela
                    $("#verificar-pedidos").on("click", function() {
                        var $btn = $(this);
                        var originalText = $btn.text();
                        
                        $btn.prop("disabled", true).text("Verificando...");
                        
                        $.ajax({
                            url: pedidos_ajax.ajax_url,
                            type: "POST",
                            data: {
                                action: "verificar_pedidos_tabela",
                                nonce: pedidos_ajax.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    var dados = response.data;
                                    var mensagem = "Pedidos na tabela:\\n\\n";
                                    mensagem += "Total de pedidos: " + dados.total + "\\n";
                                    mensagem += "Status pending: " + dados.pending + "\\n";
                                    mensagem += "Status success: " + dados.success + "\\n";
                                    mensagem += "Status error: " + dados.error + "\\n";
                                    mensagem += "Último pedido: " + (dados.ultimo_pedido || "Nenhum");
                                    alert(mensagem);
                                } else {
                                    alert("Erro ao verificar pedidos: " + (response.data || "Erro desconhecido"));
                                }
                            },
                            error: function(xhr, status, error) {
                                alert("Erro na requisição: " + error);
                            },
                            complete: function() {
                                $btn.prop("disabled", false).text(originalText);
                            }
                        });
                    });
                    
                    // Botão de processar pedidos pendentes
                    $("#processar-pedidos-pendentes").on("click", function() {
                        var $btn = $(this);
                        var originalText = $btn.text();
                        
                        if (!confirm("Tem certeza que deseja processar TODOS os pedidos pendentes?\\n\\nIsso pode demorar alguns minutos.")) {
                            return;
                        }
                        
                        $btn.prop("disabled", true).text("Processando...");
                        
                        $.ajax({
                            url: pedidos_ajax.ajax_url,
                            type: "POST",
                            data: {
                                action: "processar_pedidos_pendentes_manual",
                                nonce: pedidos_ajax.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    var dados = response.data;
                                    var mensagem = "Processamento concluído!\\n\\n";
                                    mensagem += "Pedidos processados: " + dados.processados + "\\n";
                                    mensagem += "Pedidos com sucesso: " + dados.sucesso + "\\n";
                                    mensagem += "Pedidos com erro: " + dados.erro + "\\n";
                                    mensagem += "Logs salvos em: debug-api-smm.log";
                                    alert(mensagem);
                                    
                                    // Atualizar a lista de pedidos
                                    carregarPedidos();
                                } else {
                                    alert("Erro ao processar pedidos: " + (response.data || "Erro desconhecido"));
                                }
                            },
                            error: function(xhr, status, error) {
                                alert("Erro na requisição: " + error);
                            },
                            complete: function() {
                                $btn.prop("disabled", false).text(originalText);
                            }
                        });
                    });
                    
                    // Verificar status do sistema
                    function verificarStatus() {
                        $.ajax({
                            url: pedidos_ajax.ajax_url,
                            type: "POST",
                            data: {
                                action: "verificar_status",
                                nonce: pedidos_ajax.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    $("#status-cron").text(response.data.cron_status);
                                    $("#status-proximo").text(response.data.proximo_processamento);
                                    $("#status-modo").text(response.data.modo_processamento);
                                }
                            }
                        });
                    }
                    
                    // Verificar status inicial e a cada 30 segundos
                    verificarStatus();
                    setInterval(verificarStatus, 30000);
                });
            ');
            
            pedidos_success_log('Scripts e estilos carregados com sucesso', 'ADMIN_SCRIPTS');
            
        } catch (Exception $e) {
            pedidos_error_log('Erro ao carregar scripts: ' . $e->getMessage(), 'ADMIN_SCRIPTS');
        }
    }
    
    /**
     * Renderizar página principal do admin
     */
    public function render_admin_page() {
        pedidos_step_log('Renderizando página principal do admin', 'ADMIN_PAGE_RENDER');
        
        try {
            // Verificar permissões novamente
            if (!current_user_can('manage_woocommerce')) {
                pedidos_warning_log('Usuário não tem permissão para acessar a página', 'ADMIN_PAGE_RENDER');
                wp_die(__('Você não tem permissão para acessar esta página.'));
            }
            
            // Verificar se o WooCommerce está ativo
            if (!class_exists('WooCommerce')) {
                pedidos_error_log('WooCommerce não está ativo durante renderização', 'ADMIN_PAGE_RENDER');
                echo '<div class="notice notice-error"><p>O WooCommerce não está ativo.</p></div>';
                return;
            }
            
            pedidos_step_log('Iniciando renderização da interface HTML', 'ADMIN_PAGE_RENDER');
            
            ?>
            <div class="wrap">
                             <h1 class="wp-heading-inline">
                     <span class="dashicons dashicons-yes-alt" style="margin-right: 10px;"></span>
                     Pedidos Processados
                 </h1>
                
                <div class="pedidos-header">
                                     <div class="pedidos-stats">
                         <div class="stat-card">
                             <span class="stat-number" id="total-pedidos">-</span>
                             <span class="stat-label">Total de Pedidos</span>
                         </div>
                         <div class="stat-card">
                             <span class="stat-number" id="pedidos-sucesso">-</span>
                             <span class="stat-label">Pedidos com Sucesso</span>
                         </div>
                         <div class="stat-card">
                             <span class="stat-number" id="pedidos-pendentes">-</span>
                             <span class="stat-label">Pedidos Pendentes</span>
                         </div>
                         <div class="stat-card">
                             <span class="stat-number" id="total-valor">-</span>
                             <span class="stat-label">Valor Total</span>
                         </div>
                     </div>
                    
                    <div class="pedidos-actions">
                        <button type="button" class="button button-primary" id="atualizar-lista">
                            <span class="dashicons dashicons-update"></span>
                            Atualizar Lista
                        </button>
                        <button type="button" class="button button-secondary" id="exportar-csv">
                            <span class="dashicons dashicons-download"></span>
                            Exportar CSV
                        </button>
                        <button type="button" class="button button-warning" id="limpar-pedidos">
                            <span class="dashicons dashicons-trash"></span>
                            Limpeza de Pedidos
                        </button>
                        <button type="button" class="button button-secondary" id="testar-processamento">
                            <span class="dashicons dashicons-controls-play"></span>
                            Testar Processamento
                        </button>
                        
                        <button type="button" class="button button-secondary" id="testar-api-smm" style="margin-left: 10px;">
                            <span class="dashicons dashicons-share"></span>
                            Testar API SMM
                        </button>
                        
                        <button type="button" class="button button-secondary" id="testar-geracao-comentarios" style="margin-left: 10px;">
                            <span class="dashicons dashicons-format-chat"></span>
                            Testar Geração de Comentários
                        </button>
                        
                        <button type="button" class="button button-secondary" id="diagnosticar-conectividade" style="margin-left: 10px;">
                            <span class="dashicons dashicons-admin-tools"></span>
                            Diagnosticar Conectividade
                        </button>
                        
                        <button type="button" class="button button-secondary" id="verificar-pedidos" style="margin-left: 10px;">
                            <span class="dashicons dashicons-database"></span>
                            Verificar Pedidos na Tabela
                        </button>
                        
                        <button type="button" class="button button-primary" id="processar-pedidos-pendentes" style="margin-left: 10px; background: #d63638; border-color: #d63638;">
                            <span class="dashicons dashicons-controls-forward"></span>
                            Processar Pedidos Pendentes
                        </button>
                    </div>
                </div>
                
                <div class="pedidos-filters">
                    <div class="filter-group">
                        <label for="filter-produto">Filtrar por Produto:</label>
                        <select id="filter-produto">
                            <option value="">Todos os Produtos</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="filter-data">Filtrar por Data:</label>
                        <select id="filter-data">
                            <option value="">Todas as Datas</option>
                            <option value="hoje">Hoje</option>
                            <option value="ontem">Ontem</option>
                            <option value="semana">Última Semana</option>
                            <option value="mes">Último Mês</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="search-pedido">Buscar:</label>
                        <input type="text" id="search-pedido" placeholder="ID do pedido, username...">
                    </div>
                </div>
                
                <div class="pedidos-container">
                    <!-- Status do Sistema -->
                    <div class="pedidos-status" id="pedidos-status">
                        <div class="status-item">
                            <span class="status-label">Cron WordPress:</span>
                            <span class="status-value" id="status-cron">Verificando...</span>
                        </div>
                        <div class="status-item">
                            <span class="status-label">Próximo Processamento:</span>
                            <span class="status-value" id="status-proximo">Verificando...</span>
                        </div>
                        <div class="status-item">
                            <span class="status-label">Modo de Processamento:</span>
                            <span class="status-value" id="status-modo">Verificando...</span>
                        </div>
                    </div>
                    
                    <div class="pedidos-loading" id="pedidos-loading">
                        <div class="loading-spinner"></div>
                        <p>Carregando pedidos...</p>
                    </div>
                    
                    <div class="pedidos-list" id="pedidos-list" style="display: none;">
                        <!-- Lista de pedidos será carregada aqui via AJAX -->
                    </div>
                    
                                     <div class="pedidos-empty" id="pedidos-empty" style="display: none;">
                         <div class="empty-state">
                             <span class="dashicons dashicons-yes-alt"></span>
                             <h3>Nenhum Pedido Processado</h3>
                             <p>Não há pedidos processados no momento.</p>
                         </div>
                     </div>
                </div>
            </div>
            
            <!-- Modal para detalhes do pedido -->
            <div id="pedido-modal" class="pedido-modal" style="display: none;">
                <div class="modal-content">
                                     <div class="modal-header">
                         <h2>Detalhes do Pedido Processado</h2>
                         <button type="button" class="modal-close">&times;</button>
                     </div>
                    <div class="modal-body">
                        <!-- Conteúdo do modal será carregado aqui -->
                    </div>
                </div>
            </div>
            <?php
            
            pedidos_success_log('Página admin renderizada com sucesso', 'ADMIN_PAGE_RENDER');
            
        } catch (Exception $e) {
            pedidos_error_log('Erro ao renderizar página admin: ' . $e->getMessage(), 'ADMIN_PAGE_RENDER');
        }
    }
    
    /**
     * AJAX: Buscar pedidos processados
     */
    public function ajax_buscar_pedidos_processados() {
        pedidos_step_log('Iniciando AJAX: buscar pedidos processados', 'AJAX_BUSCAR_PEDIDOS');
        pedidos_data_log($_POST, 'AJAX_BUSCAR_PEDIDOS - Dados recebidos');
        
        try {
            // Verificar nonce
            if (!wp_verify_nonce($_POST['nonce'], 'pedidos_processando_nonce')) {
                pedidos_error_log('Erro de segurança - nonce inválido', 'AJAX_BUSCAR_PEDIDOS');
                pedidos_data_log($_POST['nonce'], 'AJAX_BUSCAR_PEDIDOS - Nonce recebido');
                pedidos_data_log(wp_create_nonce('pedidos_processando_nonce'), 'AJAX_BUSCAR_PEDIDOS - Nonce esperado');
                wp_send_json_error('Erro de segurança');
            }
            
            // Verificar permissões
            if (!current_user_can('manage_woocommerce')) {
                pedidos_error_log('Permissão negada para usuário', 'AJAX_BUSCAR_PEDIDOS');
                wp_send_json_error('Permissão negada');
            }
            
            $filtros = [
                'produto' => sanitize_text_field($_POST['produto'] ?? ''),
                'data' => sanitize_text_field($_POST['data'] ?? ''),
                'busca' => sanitize_text_field($_POST['busca'] ?? '')
            ];
            
            pedidos_data_log($filtros, 'AJAX_BUSCAR_PEDIDOS - Filtros recebidos');
            
            $pedidos = $this->buscar_pedidos_processados($filtros);
            
            if (is_wp_error($pedidos)) {
                pedidos_error_log('Erro ao buscar pedidos: ' . $pedidos->get_error_message(), 'AJAX_BUSCAR_PEDIDOS');
                wp_send_json_error($pedidos->get_error_message());
            }
            
            pedidos_success_log('Pedidos encontrados: ' . count($pedidos), 'AJAX_BUSCAR_PEDIDOS');
            
            $estatisticas = $this->calcular_estatisticas($pedidos);
            pedidos_data_log($estatisticas, 'AJAX_BUSCAR_PEDIDOS - Estatísticas calculadas');
            
            wp_send_json_success([
                'pedidos' => $pedidos,
                'estatisticas' => $estatisticas
            ]);
            
        } catch (Exception $e) {
            pedidos_error_log('Exceção em AJAX buscar pedidos: ' . $e->getMessage(), 'AJAX_BUSCAR_PEDIDOS');
            wp_send_json_error('Erro interno: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Atualizar status do pedido
     */
    public function ajax_atualizar_status_pedido() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'pedidos_processando_nonce')) {
            wp_send_json_error('Erro de segurança');
        }
        
        // Verificar permissões
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permissão negada');
        }
        
        $pedido_id = intval($_POST['pedido_id']);
        $novo_status = sanitize_text_field($_POST['novo_status']);
        
        if (!$pedido_id || !$novo_status) {
            wp_send_json_error('Dados inválidos');
        }
        
        $pedido = wc_get_order($pedido_id);
        if (!$pedido) {
            wp_send_json_error('Pedido não encontrado');
        }
        
        $pedido->update_status($novo_status, 'Status alterado via plugin Pedidos em Processamento');
        
        wp_send_json_success([
            'message' => 'Status do pedido atualizado com sucesso',
            'novo_status' => $novo_status
        ]);
    }
    
    /**
     * AJAX: Testar processamento manual
     */
    public function ajax_testar_processamento() {
        pedidos_step_log('Iniciando AJAX: testar processamento manual', 'AJAX_TESTAR_PROCESSAMENTO');
        
        try {
            // Verificar nonce
            if (!wp_verify_nonce($_POST['nonce'], 'pedidos_processando_nonce')) {
                pedidos_error_log('Erro de segurança - nonce inválido', 'AJAX_TESTAR_PROCESSAMENTO');
                wp_send_json_error('Erro de segurança');
            }
            
            // Verificar permissões
            if (!current_user_can('manage_woocommerce')) {
                pedidos_error_log('Permissão negada para usuário', 'AJAX_TESTAR_PROCESSAMENTO');
                wp_send_json_error('Permissão negada');
            }
            
            pedidos_step_log('Iniciando processamento manual de pedidos pendentes', 'AJAX_TESTAR_PROCESSAMENTO');
            
            // Processar pedidos pendentes
            $this->processar_pedidos_pendentes();
            
            // Buscar estatísticas atualizadas
            $pedidos = $this->buscar_pedidos_processados([]);
            $estatisticas = $this->calcular_estatisticas($pedidos);
            
            pedidos_success_log('Processamento manual concluído com sucesso', 'AJAX_TESTAR_PROCESSAMENTO');
            
            wp_send_json_success([
                'message' => 'Processamento manual executado com sucesso!',
                'estatisticas' => $estatisticas,
                'pedidos_processados' => count($pedidos)
            ]);
            
        } catch (Exception $e) {
            pedidos_error_log('Exceção em AJAX testar processamento: ' . $e->getMessage(), 'AJAX_TESTAR_PROCESSAMENTO');
            wp_send_json_error('Erro interno: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Testar API SMM com pedido real
     */
    public function ajax_testar_api_smm() {
        pedidos_api_smm_log('Iniciando teste da API SMM', 'AJAX_TESTE_API');
        
        try {
            // Verificar nonce
            if (!wp_verify_nonce($_POST['nonce'], 'pedidos_processando_nonce')) {
                pedidos_api_smm_log('Erro de segurança - nonce inválido', 'AJAX_TESTE_API');
                wp_send_json_error('Erro de segurança');
            }
            
            // Verificar permissões
            if (!current_user_can('manage_woocommerce')) {
                pedidos_api_smm_log('Permissão negada para usuário', 'AJAX_TESTE_API');
                wp_send_json_error('Permissão negada');
            }
            
            pedidos_api_smm_log('Permissões verificadas, iniciando teste', 'AJAX_TESTE_API');
            
            // Verificar se o módulo SMM está disponível
            if (!class_exists('SMMApi') || !class_exists('SMMProvidersManager')) {
                pedidos_api_smm_log('Módulo SMM não disponível', 'AJAX_TESTE_API');
                wp_send_json_error('Módulo SMM não disponível');
            }
            
            // Obter provedores configurados
            $providers = get_option('smm_providers', []);
            if (empty($providers)) {
                pedidos_api_smm_log('Nenhum provedor SMM configurado', 'AJAX_TESTE_API');
                wp_send_json_error('Nenhum provedor SMM configurado');
            }
            
            pedidos_api_smm_log('Provedores encontrados: ' . count($providers), 'AJAX_TESTE_API');
            
            // Usar o provedor padrão configurado
            $default_provider_id = get_option('smm_default_provider', '');
            if (empty($default_provider_id) || !isset($providers[$default_provider_id])) {
                pedidos_api_smm_log('Provedor padrão não configurado ou inválido', 'AJAX_TESTE_API');
                wp_send_json_error('Provedor padrão não configurado ou inválido');
            }
            
            $active_provider = $providers[$default_provider_id];
            
            pedidos_api_smm_log("Provedor ativo: {$active_provider['name']}", 'AJAX_TESTE_API');
            pedidos_api_smm_log("URL da API: {$active_provider['api_url']}", 'AJAX_TESTE_API');
            
            // Criar instância da API SMM
            $smm_api = new SMMApi();
            $smm_api->api_url = $active_provider['api_url'];
            $smm_api->api_key = $active_provider['api_key'];
            $smm_api->timeout = 30;
            
            pedidos_api_smm_log('Instância da API SMM criada', 'AJAX_TESTE_API');
            
            // Dados de teste fixos
            $test_data = [
                'service' => 4420,           // Código do serviço
                'link' => 'phelipesf',      // Username do Instagram
                'quantity' => 10,            // 10 seguidores
                'runs' => 1,                 // Uma execução
                'interval' => 0,             // Sem intervalo
                'comments' => 'TESTE PLUGIN - 10 seguidores para phelipesf'
            ];
            
            pedidos_api_smm_log('Dados de teste preparados', 'AJAX_TESTE_API');
            pedidos_data_log($test_data, 'AJAX_TESTE_API - Dados de teste');
            
            // Enviar pedido de teste para a API
            pedidos_api_smm_log('Enviando pedido de teste para API SMM', 'AJAX_TESTE_API');
            $response = $smm_api->order($test_data);
            
            if (!$response) {
                pedidos_api_smm_log('Resposta vazia da API SMM', 'AJAX_TESTE_API');
                wp_send_json_error('Resposta vazia da API SMM');
            }
            
            pedidos_api_smm_log('Resposta recebida da API SMM', 'AJAX_TESTE_API');
            pedidos_data_log($response, 'AJAX_TESTE_API - Resposta da API');
            
            // Verificar resposta
            if (is_object($response)) {
                if (isset($response->order) && !empty($response->order)) {
                    pedidos_api_smm_log("Pedido SMM criado com sucesso! ID: {$response->order}", 'AJAX_TESTE_API');
                    
                    $message = "✅ **Teste da API SMM realizado com sucesso!**\n\n";
                    $message .= "**Provedor:** {$active_provider['name']}\n";
                    $message .= "**Serviço:** 4420 (10 seguidores)\n";
                    $message .= "**Perfil:** phelipesf\n";
                    $message .= "**ID do Pedido SMM:** {$response->order}\n";
                    $message .= "**Status:** Enviado com sucesso\n\n";
                    $message .= "📋 **Log completo salvo em:** `debug-api-smm.log`";
                    
                    wp_send_json_success([
                        'message' => $message,
                        'order_id' => $response->order,
                        'provider' => $active_provider['name']
                    ]);
                    
                } elseif (isset($response->error) && !empty($response->error)) {
                    pedidos_api_smm_log("Erro da API SMM: {$response->error}", 'AJAX_TESTE_API');
                    wp_send_json_error("Erro da API SMM: {$response->error}");
                }
            }
            
            // Resposta inesperada
            pedidos_api_smm_log('Resposta inesperada da API SMM', 'AJAX_TESTE_API');
            pedidos_data_log($response, 'AJAX_TESTE_API - Resposta inesperada');
            wp_send_json_error('Resposta inesperada da API SMM');
            
        } catch (Exception $e) {
            pedidos_api_smm_log('Exceção durante teste da API SMM: ' . $e->getMessage(), 'AJAX_TESTE_API');
            wp_send_json_error('Erro interno: ' . $e->getMessage());
        }
    }
    
    
    /**
     * AJAX: Verificar pedidos na tabela
     */
    public function ajax_verificar_pedidos_tabela() {
        try {
            // Verificar nonce
            if (!wp_verify_nonce($_POST['nonce'], 'pedidos_processando_nonce')) {
                wp_send_json_error('Nonce inválido');
                return;
            }
            
            // Verificar permissões
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error('Permissão negada');
                return;
            }
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'pedidos_processados';
            
            // Verificar se a tabela existe
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                wp_send_json_error('Tabela de pedidos não existe');
                return;
            }
            
            // Contar pedidos por status
            $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $pending = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status_api = 'pending'");
            $success = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status_api = 'success'");
            $error = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status_api = 'error'");
            
            // Buscar último pedido
            $ultimo_pedido = $wpdb->get_row("SELECT * FROM $table_name ORDER BY data_processamento DESC LIMIT 1");
            
            $dados = [
                'total' => intval($total),
                'pending' => intval($pending),
                'success' => intval($success),
                'error' => intval($error),
                'ultimo_pedido' => $ultimo_pedido ? "ID: {$ultimo_pedido->order_id}, Status: {$ultimo_pedido->status_api}, Data: {$ultimo_pedido->data_processamento}" : null
            ];
            
            wp_send_json_success($dados);
            
        } catch (Exception $e) {
            wp_send_json_error('Erro: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Processar pedidos pendentes manualmente
     */
    public function ajax_processar_pedidos_pendentes_manual() {
        try {
            // Verificar nonce
            if (!wp_verify_nonce($_POST['nonce'], 'pedidos_processando_nonce')) {
                wp_send_json_error('Nonce inválido');
                return;
            }
            
            // Verificar permissões
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error('Permissão negada');
                return;
            }
            
            pedidos_step_log('Iniciando processamento manual de pedidos pendentes', 'AJAX_PROCESSAR_PEDIDOS_MANUAL');
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'pedidos_processados';
            
            // Verificar se a tabela existe
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                wp_send_json_error('Tabela de pedidos não existe');
                return;
            }
            
            // Buscar pedidos pendentes
            $pedidos_pendentes = $wpdb->get_results(
                "SELECT * FROM $table_name 
                 WHERE status_api = 'pending' 
                 AND tentativas < 5
                 ORDER BY data_processamento ASC"
            );
            
            if (empty($pedidos_pendentes)) {
                wp_send_json_success([
                    'processados' => 0,
                    'sucesso' => 0,
                    'erro' => 0,
                    'mensagem' => 'Nenhum pedido pendente para processar'
                ]);
                return;
            }
            
            pedidos_step_log('Encontrados ' . count($pedidos_pendentes) . ' pedidos pendentes para processar', 'AJAX_PROCESSAR_PEDIDOS_MANUAL');
            
            $processados = 0;
            $sucesso = 0;
            $erro = 0;
            
            foreach ($pedidos_pendentes as $pedido) {
                $processados++;
                pedidos_step_log("Processando pedido #{$pedido->order_id} ({$processados}/" . count($pedidos_pendentes) . ")", 'AJAX_PROCESSAR_PEDIDOS_MANUAL');
                
                try {
                    // Tentar processar o pedido
                    $resultado = $this->tentar_processar_pedido($pedido);
                    
                    if ($resultado) {
                        $sucesso++;
                        pedidos_success_log("Pedido #{$pedido->order_id} processado com sucesso", 'AJAX_PROCESSAR_PEDIDOS_MANUAL');
                    } else {
                        $erro++;
                        pedidos_warning_log("Pedido #{$pedido->order_id} falhou no processamento", 'AJAX_PROCESSAR_PEDIDOS_MANUAL');
                    }
                    
                } catch (Exception $e) {
                    $erro++;
                    pedidos_error_log("Erro ao processar pedido #{$pedido->order_id}: " . $e->getMessage(), 'AJAX_PROCESSAR_PEDIDOS_MANUAL');
                }
                
                // Pequena pausa entre pedidos para não sobrecarregar a API
                usleep(500000); // 0.5 segundos
            }
            
            pedidos_success_log("Processamento manual concluído: {$processados} processados, {$sucesso} sucesso, {$erro} erro", 'AJAX_PROCESSAR_PEDIDOS_MANUAL');
            
            wp_send_json_success([
                'processados' => $processados,
                'sucesso' => $sucesso,
                'erro' => $erro,
                'mensagem' => "Processamento concluído: {$processados} pedidos processados"
            ]);
            
        } catch (Exception $e) {
            pedidos_error_log('Erro no AJAX de processamento manual: ' . $e->getMessage(), 'AJAX_PROCESSAR_PEDIDOS_MANUAL');
            wp_send_json_error('Erro: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Verificar status do sistema
     */
    public function ajax_verificar_status() {
        pedidos_step_log('Iniciando AJAX: verificar status do sistema', 'AJAX_VERIFICAR_STATUS');
        
        try {
            // Verificar nonce
            if (!wp_verify_nonce($_POST['nonce'], 'pedidos_processando_nonce')) {
                pedidos_error_log('Erro de segurança - nonce inválido', 'AJAX_VERIFICAR_STATUS');
                wp_send_json_error('Erro de segurança');
            }
            
            // Verificar permissões
            if (!current_user_can('manage_woocommerce')) {
                pedidos_error_log('Permissão negada para usuário', 'AJAX_VERIFICAR_STATUS');
                wp_send_json_error('Permissão negada');
            }
            
            // Verificar status do cron
            $cron_status = 'Desativado';
            $proximo_processamento = 'N/A';
            $modo_processamento = 'AJAX (Fallback)';
            
            if (defined('DOING_CRON') && DOING_CRON) {
                $cron_status = 'Ativo (DOING_CRON)';
                $modo_processamento = 'Cron WordPress';
            } else {
                $next_scheduled = wp_next_scheduled('processar_pedidos_pendentes');
                if ($next_scheduled) {
                    $cron_status = 'Agendado';
                    $proximo_processamento = date('d/m/Y H:i:s', $next_scheduled);
                    $modo_processamento = 'Cron WordPress + AJAX';
                } else {
                    $cron_status = 'Não agendado';
                    $modo_processamento = 'AJAX (Fallback)';
                }
            }
            
            // Verificar última execução
            $ultimo_processamento = get_transient('ultimo_processamento_pedidos');
            if ($ultimo_processamento) {
                $tempo_desde_ultimo = time() - $ultimo_processamento;
                $proximo_processamento = "Em " . (120 - $tempo_desde_ultimo) . " segundos";
            }
            
            pedidos_success_log('Status do sistema verificado com sucesso', 'AJAX_VERIFICAR_STATUS');
            
            wp_send_json_success([
                'cron_status' => $cron_status,
                'proximo_processamento' => $proximo_processamento,
                'modo_processamento' => $modo_processamento,
                'ultimo_processamento' => $ultimo_processamento ? date('d/m/Y H:i:s', $ultimo_processamento) : 'Nunca'
            ]);
            
        } catch (Exception $e) {
            pedidos_error_log('Exceção em AJAX verificar status: ' . $e->getMessage(), 'AJAX_VERIFICAR_STATUS');
            wp_send_json_error('Erro interno: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Limpar todos os pedidos e recarregar do WooCommerce
     */
    public function ajax_limpar_pedidos() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'pedidos_processando_nonce')) {
            wp_send_json_error('Erro de segurança');
        }
        
        // Verificar permissões
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permissão negada');
        }
        
        try {
            $resultado = $this->limpar_e_recarregar_pedidos();
            
            if (is_wp_error($resultado)) {
                wp_send_json_error($resultado->get_error_message());
            }
            
            wp_send_json_success([
                'message' => 'Pedidos limpos e recarregados com sucesso!',
                'pedidos_removidos' => $resultado['pedidos_removidos'],
                'pedidos_recarregados' => $resultado['pedidos_recarregados']
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error('Erro ao limpar e recarregar pedidos: ' . $e->getMessage());
        }
    }
    
    /**
     * Limpar todos os pedidos e recarregar do WooCommerce
     */
    private function limpar_e_recarregar_pedidos() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pedidos_processados';
        
        // Verificar se a tabela existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return new WP_Error('tabela_inexistente', 'Tabela de pedidos processados não existe');
        }
        
        // Contar pedidos antes da limpeza
        $pedidos_antes = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // Limpar TODOS os pedidos da tabela
        $resultado_limpeza = $wpdb->query("TRUNCATE TABLE $table_name");
        
        if ($resultado_limpeza === false) {
            return new WP_Error('erro_limpeza', 'Erro ao limpar tabela: ' . $wpdb->last_error);
        }
        
        $pedidos_removidos = $pedidos_antes;
        error_log("Tabela limpa: $pedidos_removidos pedidos removidos");
        
        // Recarregar pedidos atuais do WooCommerce com status "processing"
        $pedidos_recarregados = $this->recarregar_pedidos_woocommerce();
        
        if (is_wp_error($pedidos_recarregados)) {
            return $pedidos_recarregados;
        }
        
        // Limpar cache do WordPress
        wp_cache_flush();
        
        return [
            'pedidos_removidos' => $pedidos_removidos,
            'pedidos_recarregados' => $pedidos_recarregados,
            'total_processados' => $pedidos_antes
        ];
    }
    
    /**
     * Recarregar pedidos do WooCommerce com status "processing"
     */
    private function recarregar_pedidos_woocommerce() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pedidos_processados';
        
        // Buscar todos os pedidos com status "processing" no WooCommerce
        $args = [
            'status' => 'processing',
            'limit' => -1, // Sem limite
            'return' => 'ids'
        ];
        
        $order_ids = wc_get_orders($args);
        
        if (empty($order_ids)) {
            error_log("Nenhum pedido com status 'processing' encontrado no WooCommerce");
            return 0;
        }
        
        $pedidos_processados = 0;
        
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                continue;
            }
            
            // Processar cada item do pedido
            foreach ($order->get_items() as $item) {
                $produto = $item->get_product();
                $variation_id = $item->get_variation_id();
                
                // Determinar quantidade baseada na variação
                $quantidade_variacao = $this->obter_quantidade_da_variacao($variation_id, $item);
                
                // Buscar username do Instagram ou processar links distribuídos
                $instagram_username = '';
                $instagram_meta = wc_get_order_item_meta($item->get_id(), 'Instagram', true);
                if (!empty($instagram_meta)) {
                    $instagram_username = $instagram_meta;
                }
                
                // Verificar se produto requer processamento de links distribuídos
                $produto_id = $produto ? $produto->get_id() : 0;
                $requires_link_reconstruction = class_exists('SMMCategoryRules') && SMMCategoryRules::requires_link_reconstruction($produto_id);
                
                if ($requires_link_reconstruction) {
                    pedidos_step_log("Produto '{$produto_nome}' requer processamento de links distribuídos", 'PROCESSAMENTO_PEDIDO');
                    
                    // Obter informações de distribuição
                    $distribution_info = SMMCategoryRules::get_distribution_info([
                        'item_id' => $item->get_id(),
                        'product_id' => $produto_id
                    ]);
                    
                    if ($distribution_info['is_distributed']) {
                        pedidos_success_log("Pedido distribuído detectado: {$distribution_info['total_quantity']} links, multiplicador: {$distribution_info['multiplier']}", 'PROCESSAMENTO_PEDIDO');
                        // Para pedidos distribuídos, não precisamos de username
                        $instagram_username = ''; // Limpar username para pedidos distribuídos
                    }
                }
                
                // Obter Service ID individual do produto
                $service_id_produto = '';
                if (class_exists('SMMModule') && $produto) {
                    $produto_original_id = $produto->get_id();
                    $produto_id = $produto_original_id;
                    
                    // Se for uma variação, buscar o produto pai para verificar SMM
                    if ($produto->is_type('variation')) {
                        $produto_pai = wc_get_product($produto->get_parent_id());
                        if ($produto_pai) {
                            $produto_id = $produto_pai->get_id();
                            pedidos_step_log("Produto é variação #{$produto_original_id}, verificando SMM no produto pai #{$produto_id}", 'PROCESSAMENTO_PEDIDO');
                        }
                    }
                    
                    // Verificar se o produto (ou produto pai) tem SMM ativado
                    if (SMMModule::is_product_smm_enabled($produto_id)) {
                        // IMPORTANTE: Usar o ID original da variação para obter o Service ID correto
                        $service_id_produto = SMMModule::get_product_service_id($produto_original_id);
                        pedidos_success_log("Service ID individual obtido para produto #{$produto_original_id} (pai: #{$produto_id}): {$service_id_produto}", 'PROCESSAMENTO_PEDIDO');
                    } else {
                        // Fallback para Service ID global se produto não tem SMM individual
                        $service_id_produto = SMMModule::get_global_service_id();
                        pedidos_warning_log("Produto #{$produto_id} não tem SMM individual, usando Service ID global: {$service_id_produto}", 'PROCESSAMENTO_PEDIDO');
                    }
                }
                
                // Inserir na tabela de pedidos processados
                $resultado = $wpdb->insert(
                    $table_name,
                    [
                        'order_id' => $order->get_id(),
                        'produto_id' => $produto ? $produto->get_id() : 0,
                        'produto_nome' => $produto ? $produto->get_name() : $item->get_name(),
                        'quantidade_variacao' => $quantidade_variacao > 0 ? $quantidade_variacao : $item->get_quantity(),
                        'instagram_username' => $instagram_username, // Pode estar vazio para pedidos distribuídos
                        'service_id_pedido' => $service_id_produto,
                        'cliente_nome' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                        'cliente_email' => $order->get_billing_email(),
                        'valor_total' => $item->get_total(),
                        'status_api' => 'pending',
                        'tentativas' => 0,
                        'proxima_tentativa' => date('Y-m-d H:i:s', strtotime('+2 minutes'))
                    ],
                    [
                        '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%d', '%s'
                    ]
                );
                
                if ($resultado !== false) {
                    $pedidos_processados++;
                    error_log("Pedido #$order_id recarregado na tabela");
                } else {
                    error_log("Erro ao recarregar pedido #$order_id: " . $wpdb->last_error);
                }
            }
        }
        
        error_log("Recarregamento concluído: $pedidos_processados pedidos processados");
        return $pedidos_processados;
    }
    
    /**
     * Buscar pedidos processados
     */
    private function buscar_pedidos_processados($filtros = []) {
        pedidos_step_log('Iniciando busca de pedidos processados', 'BUSCAR_PEDIDOS');
        pedidos_data_log($filtros, 'BUSCAR_PEDIDOS - Filtros aplicados');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'pedidos_processados';
        
        pedidos_step_log("Verificando existência da tabela: {$table_name}", 'BUSCAR_PEDIDOS');
        
        // Verificar se a tabela existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            pedidos_warning_log("Tabela {$table_name} não existe", 'BUSCAR_PEDIDOS');
            return [];
        }
        
        pedidos_success_log("Tabela {$table_name} encontrada", 'BUSCAR_PEDIDOS');
        
        $where_conditions = [];
        $where_values = [];
        
        // Aplicar filtros
        if (!empty($filtros['data'])) {
            pedidos_step_log("Aplicando filtro de data: {$filtros['data']}", 'BUSCAR_PEDIDOS');
            $data_filter = $this->aplicar_filtro_data_sql($filtros['data']);
            if ($data_filter) {
                $where_conditions[] = $data_filter;
                pedidos_step_log("Filtro de data aplicado: {$data_filter}", 'BUSCAR_PEDIDOS');
            }
        }
        
        if (!empty($filtros['produto'])) {
            pedidos_step_log("Aplicando filtro de produto: {$filtros['produto']}", 'BUSCAR_PEDIDOS');
            $where_conditions[] = 'produto_id = %d';
            $where_values[] = intval($filtros['produto']);
        }
        
        if (!empty($filtros['busca'])) {
            pedidos_step_log("Aplicando filtro de busca: {$filtros['busca']}", 'BUSCAR_PEDIDOS');
            $where_conditions[] = '(order_id LIKE %s OR instagram_username LIKE %s OR cliente_nome LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($filtros['busca']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
            pedidos_step_log("Cláusula WHERE construída: {$where_clause}", 'BUSCAR_PEDIDOS');
        }
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY data_processamento DESC",
            $where_values
        );
        
        pedidos_step_log("Executando query: {$query}", 'BUSCAR_PEDIDOS');
        
        $pedidos_db = $wpdb->get_results($query);
        
        if ($wpdb->last_error) {
            pedidos_error_log("Erro na query: {$wpdb->last_error}", 'BUSCAR_PEDIDOS');
        }
        
        pedidos_success_log("Pedidos encontrados na DB: " . count($pedidos_db), 'BUSCAR_PEDIDOS');
        
        $pedidos_filtrados = [];
        
        foreach ($pedidos_db as $pedido_db) {
            $pedidos_filtrados[] = $this->preparar_dados_pedido_processado($pedido_db);
        }
        
        pedidos_success_log("Total de pedidos processados: " . count($pedidos_filtrados), 'BUSCAR_PEDIDOS');
        
        return $pedidos_filtrados;
    }
    
    /**
     * Preparar dados do pedido para exibição
     */
    private function preparar_dados_pedido($pedido) {
        $itens = [];
        $total_produtos = 0;
        
        foreach ($pedido->get_items() as $item) {
            $produto = $item->get_product();
            $variation_id = $item->get_variation_id();
            
            // Determinar quantidade baseada na variação
            $quantidade_variacao = $this->obter_quantidade_da_variacao($variation_id, $item);
            
            $itens[] = [
                'id' => $produto ? $produto->get_id() : 0,
                'variation_id' => $variation_id,
                'nome' => $produto ? $produto->get_name() : $item->get_name(),
                'quantidade_original' => $item->get_quantity(),
                'quantidade_variacao' => $quantidade_variacao,
                'quantidade_final' => $quantidade_variacao > 0 ? $quantidade_variacao : $item->get_quantity(),
                'preco_unitario' => $item->get_total() / $item->get_quantity(),
                'preco_total' => $item->get_total(),
                'atributos_variacao' => $this->obter_atributos_variacao($item)
            ];
            
            // Usar quantidade da variação se disponível, senão usar quantidade original
            $quantidade_para_total = $quantidade_variacao > 0 ? $quantidade_variacao : $item->get_quantity();
            $total_produtos += $quantidade_para_total;
        }
        
        // Buscar username do Instagram se existir
        $instagram_username = '';
        foreach ($pedido->get_items() as $item) {
            $instagram_meta = wc_get_order_item_meta($item->get_id(), 'Instagram', true);
            if (!empty($instagram_meta)) {
                $instagram_username = $instagram_meta;
                break;
            }
        }
        
        return [
            'id' => $pedido->get_id(),
            'numero' => $pedido->get_order_number(),
            'data' => $pedido->get_date_created()->format('d/m/Y H:i'),
            'cliente' => [
                'nome' => $pedido->get_billing_first_name() . ' ' . $pedido->get_billing_last_name(),
                'email' => $pedido->get_billing_email(),
                'telefone' => $pedido->get_billing_phone()
            ],
            'instagram_username' => $instagram_username,
            'itens' => $itens,
            'total_produtos' => $total_produtos,
            'subtotal' => $pedido->get_subtotal(),
            'total' => $pedido->get_total(),
            'status' => $pedido->get_status(),
            'metodo_pagamento' => $pedido->get_payment_method_title(),
            'notas' => $pedido->get_customer_note()
        ];
    }
    
    /**
     * Aplicar filtro de data
     */
    private function aplicar_filtro_data($args, $filtro_data) {
        $hoje = current_time('Y-m-d');
        
        switch ($filtro_data) {
            case 'hoje':
                $args['date_created'] = '>=' . $hoje;
                break;
            case 'ontem':
                $ontem = date('Y-m-d', strtotime('-1 day'));
                $args['date_created'] = $ontem . '...' . $hoje;
                break;
            case 'semana':
                $semana_atras = date('Y-m-d', strtotime('-7 days'));
                $args['date_created'] = '>=' . $semana_atras;
                break;
            case 'mes':
                $mes_atras = date('Y-m-d', strtotime('-30 days'));
                $args['date_created'] = '>=' . $mes_atras;
                break;
        }
        
        return $args;
    }
    
    /**
     * Verificar se pedido contém produto específico
     */
    private function pedido_contem_produto($pedido, $produto_id) {
        foreach ($pedido->get_items() as $item) {
            if ($item->get_product_id() == $produto_id) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Verificar se pedido atende critério de busca
     */
    private function pedido_atende_busca($pedido_data, $busca) {
        $busca = strtolower($busca);
        
        // Buscar por ID do pedido
        if (strpos($pedido_data['id'], $busca) !== false) {
            return true;
        }
        
        // Buscar por username do Instagram
        if (strpos(strtolower($pedido_data['instagram_username']), $busca) !== false) {
            return true;
        }
        
        // Buscar por nome do cliente
        if (strpos(strtolower($pedido_data['cliente']['nome']), $busca) !== false) {
            return true;
        }
        
        // Buscar por email
        if (strpos(strtolower($pedido_data['cliente']['email']), $busca) !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Obter quantidade da variação do produto
     */
    private function obter_quantidade_da_variacao($variation_id, $item) {
        if (!$variation_id) {
            return 0; // Não é uma variação
        }
        
        // Tentar obter a quantidade do nome da variação (ex: "250", "500", "1000")
        $variation_name = $item->get_name();
        
        // Buscar por números no nome da variação
        if (preg_match('/(\d+)/', $variation_name, $matches)) {
            $quantidade = intval($matches[1]);
            if ($quantidade > 0) {
                return $quantidade;
            }
        }
        
        // Tentar obter dos atributos da variação
        $variation_attributes = $item->get_meta_data();
        foreach ($variation_attributes as $meta) {
            if (is_object($meta) && method_exists($meta, 'get_data')) {
                $meta_data = $meta->get_data();
                if (isset($meta_data['key']) && isset($meta_data['value'])) {
                    $key = $meta_data['key'];
                    $value = $meta_data['value'];
                    
                    // Buscar por atributos que possam conter quantidade
                    if (in_array(strtolower($key), ['quantidade', 'quantity', 'qty', 'amount', 'value']) || 
                        preg_match('/(\d+)/', $value)) {
                        $qty = intval($value);
                        if ($qty > 0) {
                            return $qty;
                        }
                    }
                }
            }
        }
        
        // Tentar obter do produto da variação
        $variation_product = wc_get_product($variation_id);
        if ($variation_product && method_exists($variation_product, 'get_attribute')) {
            // Buscar em atributos comuns
            $attributes_to_check = ['quantidade', 'quantity', 'qty', 'amount', 'value'];
            foreach ($attributes_to_check as $attr) {
                $attr_value = $variation_product->get_attribute($attr);
                if (!empty($attr_value)) {
                    $qty = intval($attr_value);
                    if ($qty > 0) {
                        return $qty;
                    }
                }
            }
            
            // Buscar no SKU da variação (às vezes contém a quantidade)
            $sku = $variation_product->get_sku();
            if (!empty($sku) && preg_match('/(\d+)/', $sku, $matches)) {
                $qty = intval($matches[1]);
                if ($qty > 0) {
                    return $qty;
                }
            }
        }
        
        return 0; // Não foi possível determinar a quantidade
    }
    
    /**
     * Obter atributos da variação para exibição
     */
    private function obter_atributos_variacao($item) {
        $atributos = [];
        $variation_id = $item->get_variation_id();
        
        if ($variation_id) {
            $variation_product = wc_get_product($variation_id);
            if ($variation_product) {
                $variation_attributes = $variation_product->get_variation_attributes();
                foreach ($variation_attributes as $attribute_name => $attribute_value) {
                    if (!empty($attribute_value)) {
                        $atributos[] = [
                            'nome' => wc_attribute_label(str_replace('attribute_', '', $attribute_name)),
                            'valor' => $attribute_value
                        ];
                    }
                }
            }
        }
        
        return $atributos;
    }
    

    
    /**
     * Verificar mudança de status do pedido
     */
    public function verificar_mudanca_status_pedido($order_id, $old_status = '', $new_status = '') {
        // Log dos parâmetros recebidos para debug
        pedidos_step_log("verificar_mudanca_status_pedido chamado com: order_id={$order_id}, old_status='{$old_status}', new_status='{$new_status}'", 'MUDANCA_STATUS');
        
        // Se não temos os parâmetros completos, buscar do pedido
        if (empty($new_status)) {
            $order = wc_get_order($order_id);
            if ($order) {
                $new_status = $order->get_status();
                pedidos_step_log("Status do pedido #{$order_id} determinado: {$new_status}", 'MUDANCA_STATUS');
            } else {
                pedidos_step_log("Pedido #{$order_id} não encontrado para verificação de status", 'MUDANCA_STATUS');
                return;
            }
        } else {
            pedidos_step_log("Status do pedido #{$order_id} mudou de '{$old_status}' para '{$new_status}'", 'MUDANCA_STATUS');
        }
        
        // Processar APENAS quando o pedido for mudado para "processing"
        if ($new_status === 'processing') {
            pedidos_step_log("Pedido #{$order_id} mudou para status 'processing' - iniciando processamento SMM", 'MUDANCA_STATUS');
            $this->processar_pedido_automaticamente($order_id);
        } else {
            pedidos_step_log("Pedido #{$order_id} com status '{$new_status}' - não processável (apenas 'processing')", 'MUDANCA_STATUS');
        }
    }
    
    /**
     * Processar pedido automaticamente quando é criado ou confirmado
     */
    public function processar_pedido_automaticamente($order_id) {
        pedidos_step_log("Processando pedido automaticamente: #{$order_id}", 'PROCESSAMENTO_AUTOMATICO');
        
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                pedidos_error_log("Pedido #{$order_id} não encontrado", 'PROCESSAMENTO_AUTOMATICO');
                return;
            }
            
            pedidos_success_log("Pedido #{$order_id} encontrado", 'PROCESSAMENTO_AUTOMATICO');
            
            // Verificar se o pedido já foi processado
            if ($this->pedido_ja_processado($order_id)) {
                pedidos_warning_log("Pedido #{$order_id} já foi processado anteriormente", 'PROCESSAMENTO_AUTOMATICO');
                return;
            }
            
            pedidos_step_log("Pedido #{$order_id} não processado, enviando para API", 'PROCESSAMENTO_AUTOMATICO');
            
            // Processar o pedido
            $this->enviar_pedido_para_api($order);
            
        } catch (Exception $e) {
            pedidos_error_log("Erro ao processar pedido #{$order_id}: " . $e->getMessage(), 'PROCESSAMENTO_AUTOMATICO');
        }
    }
    
    /**
     * Verificar se pedido já foi processado
     */
    private function pedido_ja_processado($order_id) {
        pedidos_step_log("Verificando se pedido #{$order_id} já foi processado", 'VERIFICAR_PEDIDO_PROCESSADO');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'pedidos_processados';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            pedidos_warning_log("Tabela {$table_name} não existe para verificação", 'VERIFICAR_PEDIDO_PROCESSADO');
            return false;
        }
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE order_id = %d",
            $order_id
        ));
        
        if ($wpdb->last_error) {
            pedidos_error_log("Erro ao verificar pedido #{$order_id}: {$wpdb->last_error}", 'VERIFICAR_PEDIDO_PROCESSADO');
        }
        
        $ja_processado = !empty($result);
        pedidos_step_log("Pedido #{$order_id} já processado: " . ($ja_processado ? 'Sim' : 'Não'), 'VERIFICAR_PEDIDO_PROCESSADO');
        
        return $ja_processado;
    }
    
    /**
     * Enviar pedido para API SMM
     */
    private function enviar_pedido_para_api($order) {
        pedidos_step_log("Enviando pedido #{$order->get_id()} para API", 'ENVIAR_PEDIDO_API');
        
        // Verificar se o pedido está com status "processing"
        $status = $order->get_status();
        if ($status !== 'processing') {
            pedidos_warning_log("Pedido #{$order->get_id()} tem status '{$status}' - ignorando (apenas 'processing' é processado)", 'ENVIAR_PEDIDO_API');
            return;
        }
        
        pedidos_step_log("Pedido #{$order->get_id()} com status 'processing' confirmado - prosseguindo", 'ENVIAR_PEDIDO_API');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'pedidos_processados';
        
        $itens_processados = 0;
        $erros = 0;
        
        foreach ($order->get_items() as $item) {
            pedidos_step_log("Processando item do pedido #{$order->get_id()}", 'ENVIAR_PEDIDO_API');
            
            try {
                $produto = $item->get_product();
                $variation_id = $item->get_variation_id();
                
                pedidos_data_log([
                    'produto_id' => $produto ? $produto->get_id() : 0,
                    'variation_id' => $variation_id,
                    'nome_produto' => $produto ? $produto->get_name() : $item->get_name(),
                    'quantidade_original' => $item->get_quantity()
                ], 'ENVIAR_PEDIDO_API - Dados do item');
                
                // Determinar quantidade baseada na variação
                $quantidade_variacao = $this->obter_quantidade_da_variacao($variation_id, $item);
                pedidos_step_log("Quantidade da variação determinada: {$quantidade_variacao}", 'ENVIAR_PEDIDO_API');
                
                // Buscar username do Instagram ou processar links distribuídos
                $instagram_username = '';
                $instagram_meta = wc_get_order_item_meta($item->get_id(), 'Instagram', true);
                if (!empty($instagram_meta)) {
                    $instagram_username = $instagram_meta;
                }
                
                // Verificar se produto requer processamento de links distribuídos
                $produto_id = $produto ? $produto->get_id() : 0;
                $requires_link_reconstruction = class_exists('SMMCategoryRules') && SMMCategoryRules::requires_link_reconstruction($produto_id);
                
                if ($requires_link_reconstruction) {
                    pedidos_step_log("Produto '{$produto_nome}' requer processamento de links distribuídos", 'ENVIAR_PEDIDO_API');
                    
                    // Obter informações de distribuição
                    $distribution_info = SMMCategoryRules::get_distribution_info([
                        'item_id' => $item->get_id(),
                        'product_id' => $produto_id
                    ]);
                    
                    if ($distribution_info['is_distributed']) {
                        pedidos_success_log("Pedido distribuído detectado: {$distribution_info['total_quantity']} links, multiplicador: {$distribution_info['multiplier']}", 'ENVIAR_PEDIDO_API');
                        // Para pedidos distribuídos, não precisamos de username
                        $instagram_username = ''; // Limpar username para pedidos distribuídos
                    }
                }
                
                if (!empty($instagram_username)) {
                    pedidos_success_log("Username Instagram encontrado: {$instagram_username}", 'ENVIAR_PEDIDO_API');
                } else {
                    pedidos_error_log("Username Instagram não encontrado para item", 'ENVIAR_PEDIDO_API');
                }
                
                // Obter Service ID individual do produto
                $service_id_produto = '';
                if (class_exists('SMMModule') && $produto) {
                    $produto_original_id = $produto->get_id();
                    $produto_id = $produto_original_id;
                    
                    // Se for uma variação, buscar o produto pai para verificar SMM
                    if ($produto->is_type('variation')) {
                        $produto_pai = wc_get_product($produto->get_parent_id());
                        if ($produto_pai) {
                            $produto_id = $produto_pai->get_id();
                            pedidos_step_log("Produto é variação #{$produto_original_id}, verificando SMM no produto pai #{$produto_id}", 'ENVIAR_PEDIDO_API');
                        }
                    }
                    
                    // Verificar se o produto (ou produto pai) tem SMM ativado
                    if (SMMModule::is_product_smm_enabled($produto_id)) {
                        // IMPORTANTE: Usar o ID original da variação para obter o Service ID correto
                        $service_id_produto = SMMModule::get_product_service_id($produto_original_id);
                        pedidos_success_log("Service ID individual obtido para produto #{$produto_original_id} (pai: #{$produto_id}): {$service_id_produto}", 'ENVIAR_PEDIDO_API');
                    } else {
                        // Fallback para Service ID global se produto não tem SMM individual
                        $service_id_produto = SMMModule::get_global_service_id();
                        pedidos_warning_log("Produto #{$produto_id} não tem SMM individual, usando Service ID global: {$service_id_produto}", 'ENVIAR_PEDIDO_API');
                    }
                    
                    pedidos_data_log([
                        'service_id_individual' => $service_id_produto,
                        'service_id_vazio' => empty($service_id_produto),
                        'produto_id' => $produto_id,
                        'produto_original_id' => $produto->get_id(),
                        'smm_habilitado' => SMMModule::is_product_smm_enabled($produto_id)
                    ], 'ENVIAR_PEDIDO_API - Service ID Individual');
                    
                    if (empty($service_id_produto)) {
                        pedidos_warning_log("SERVICE ID NÃO CONFIGURADO para produto #{$produto_id}!", 'ENVIAR_PEDIDO_API');
                    }
                } else {
                    pedidos_error_log("Módulo SMM não disponível ou produto não encontrado", 'ENVIAR_PEDIDO_API');
                }
                
                // Preparar dados para inserção
                $dados_insercao = [
                    'order_id' => $order->get_id(),
                    'produto_id' => $produto ? $produto->get_id() : 0,
                    'produto_nome' => $produto ? $produto->get_name() : $item->get_name(),
                    'quantidade_variacao' => $quantidade_variacao > 0 ? $quantidade_variacao : $item->get_quantity(),
                    'instagram_username' => $instagram_username,
                    'service_id_pedido' => $service_id_produto,  // Service ID salvo no pedido
                    'cliente_nome' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'cliente_email' => $order->get_billing_email(),
                    'valor_total' => $item->get_total(),
                    'status_api' => 'pending',
                    'tentativas' => 0,
                    'proxima_tentativa' => date('Y-m-d H:i:s', strtotime('+2 minutes'))
                ];
                
                pedidos_data_log($dados_insercao, 'ENVIAR_PEDIDO_API - Dados para inserção');
                
                // Inserir na tabela de pedidos processados
                $resultado_insercao = $wpdb->insert(
                    $table_name,
                    $dados_insercao,
                    [
                        '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%d', '%s', '%s'
                    ]
                );
                
                if ($resultado_insercao === false) {
                    pedidos_error_log("Erro ao inserir item na tabela: {$wpdb->last_error}", 'ENVIAR_PEDIDO_API');
                    $erros++;
                } else {
                    pedidos_success_log("Item inserido com sucesso na tabela", 'ENVIAR_PEDIDO_API');
                    $itens_processados++;
                }
                
            } catch (Exception $e) {
                pedidos_error_log("Erro ao processar item: " . $e->getMessage(), 'ENVIAR_PEDIDO_API');
                $erros++;
            }
        }
        
        pedidos_success_log("Pedido #{$order->get_id()} processado: {$itens_processados} itens inseridos, {$erros} erros", 'ENVIAR_PEDIDO_API');
        
        // Agendar primeira tentativa
        $agendado = wp_schedule_single_event(time() + 120, 'processar_pedidos_pendentes');
        if ($agendado) {
            pedidos_success_log("Processamento agendado para 2 minutos", 'ENVIAR_PEDIDO_API');
        } else {
            pedidos_warning_log("Falha ao agendar processamento", 'ENVIAR_PEDIDO_API');
        }
    }
    
    /**
     * Adicionar campo Service ID no admin do produto
     */
    public function adicionar_campo_service_id() {
        // Campo removido - agora usa Service ID global nas configurações SMM
        // Só exibir se não estiver em ativação
        if (!defined('WP_PLUGIN_DIR') && is_admin()) {
            echo '<p style="color: #666; font-style: italic;">'
                . '<strong>Nota:</strong> O Service ID agora é configurado globalmente nas '
                . '<a href="' . admin_url('admin.php?page=smm-settings') . '" target="_blank">Configurações SMM</a>.'
                . '</p>';
        }
    }
    
    /**
     * Salvar campo Service ID do produto
     */
    public function salvar_campo_service_id($post_id) {
        // Remover campos antigos se existirem (limpeza)
        delete_post_meta($post_id, '_smm_service_id');
    }
    
    /**
     * Adicionar cron personalizado de 2 minutos
     */
    public function adicionar_cron_2_minutos($schedules) {
        // Evitar múltiplas execuções durante ativação
        static $configurado = false;
        if ($configurado) {
            return $schedules;
        }
        
        pedidos_step_log('Adicionando cron personalizado de 2 minutos', 'CRON_CONFIG');
        
        $schedules['every_2_minutes'] = [
            'interval' => 120, // 2 minutos em segundos
            'display' => 'A cada 2 minutos'
        ];
        
        pedidos_success_log('Cron de 2 minutos configurado', 'CRON_CONFIG');
        $configurado = true;
        return $schedules;
    }
    
    /**
     * Agendar processamento de pedidos pendentes
     */
    public function agendar_processamento_pedidos() {
        // Evitar múltiplas execuções durante ativação
        static $agendado = false;
        if ($agendado) {
            return;
        }
        
        pedidos_step_log('Verificando agendamento de processamento de pedidos', 'AGENDAR_PROCESSAMENTO');
        
        // Limpar cron existente para evitar duplicatas
        wp_clear_scheduled_hook('processar_pedidos_pendentes');
        
        // Agendar novo cron
        $resultado = wp_schedule_event(time(), 'every_2_minutes', 'processar_pedidos_pendentes');
        
        if ($resultado) {
            pedidos_success_log('Processamento de pedidos agendado com sucesso', 'AGENDAR_PROCESSAMENTO');
            $agendado = true;
            
            // Verificar próximo agendamento
            $next_scheduled = wp_next_scheduled('processar_pedidos_pendentes');
            if ($next_scheduled) {
                pedidos_step_log("Próximo processamento agendado para: " . date('Y-m-d H:i:s', $next_scheduled), 'AGENDAR_PROCESSAMENTO');
            }
        } else {
            pedidos_error_log('Falha ao agendar processamento de pedidos', 'AGENDAR_PROCESSAMENTO');
            
            // Tentar agendar com intervalo padrão
            $agendado_padrao = wp_schedule_event(time() + 120, 'hourly', 'processar_pedidos_pendentes');
            if ($agendado_padrao) {
                pedidos_warning_log('Processamento agendado com intervalo padrão (1 hora) como fallback', 'AGENDAR_PROCESSAMENTO');
                $agendado = true;
            } else {
                pedidos_error_log('Falha total ao agendar processamento - usando apenas solução AJAX', 'AGENDAR_PROCESSAMENTO');
            }
        }
    }
    
    /**
     * Testar se o cron do WordPress está funcionando
     */
    public function testar_cron_wordpress() {
        pedidos_step_log('Testando funcionamento do cron do WordPress', 'CRON_TEST');
        
        if (defined('DOING_CRON') && DOING_CRON) {
            pedidos_success_log('CRON do WordPress está funcionando (DOING_CRON = true)', 'CRON_TEST');
        } else {
            pedidos_warning_log('CRON do WordPress não está ativo (DOING_CRON = false)', 'CRON_TEST');
            
            // Verificar se há cron agendado
            $next_scheduled = wp_next_scheduled('processar_pedidos_pendentes');
            if ($next_scheduled) {
                pedidos_step_log("Próximo cron agendado para: " . date('Y-m-d H:i:s', $next_scheduled), 'CRON_TEST');
            } else {
                pedidos_warning_log('Nenhum cron agendado para processar_pedidos_pendentes', 'CRON_TEST');
            }
        }
    }
    
    /**
     * Processar pedidos pendentes via AJAX (solução alternativa)
     */
    public function processar_pedidos_pendentes_ajax() {
        // Só processar se estiver na página do plugin e se passou tempo suficiente
        if (isset($_GET['page']) && $_GET['page'] === 'pedidos-processando') {
            $ultimo_processamento = get_transient('ultimo_processamento_pedidos');
            $agora = time();
            
            // Processar a cada 2 minutos (120 segundos)
            if (!$ultimo_processamento || ($agora - $ultimo_processamento) >= 120) {
                pedidos_step_log('Processando pedidos pendentes via AJAX (solução alternativa)', 'PROCESSAR_PEDIDOS_AJAX');
                
                $this->processar_pedidos_pendentes();
                
                // Marcar último processamento
                set_transient('ultimo_processamento_pedidos', $agora, 300); // 5 minutos
                
                pedidos_success_log('Processamento via AJAX concluído', 'PROCESSAR_PEDIDOS_AJAX');
            } else {
                $tempo_restante = 120 - ($agora - $ultimo_processamento);
                pedidos_step_log("Aguardando {$tempo_restante} segundos para próximo processamento via AJAX", 'PROCESSAR_PEDIDOS_AJAX');
            }
        }
    }
    
    /**
     * Processar pedidos pendentes
     */
    public function processar_pedidos_pendentes() {
        pedidos_step_log('Iniciando processamento de pedidos pendentes', 'PROCESSAR_PEDIDOS_PENDENTES');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'pedidos_processados';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            pedidos_warning_log("Tabela {$table_name} não existe para processamento", 'PROCESSAR_PEDIDOS_PENDENTES');
            return;
        }
        
        pedidos_success_log("Tabela {$table_name} encontrada para processamento", 'PROCESSAR_PEDIDOS_PENDENTES');
        
        // Buscar pedidos pendentes que devem ser processados
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE status_api = 'pending' 
             AND tentativas < 5
             ORDER BY data_processamento ASC
             LIMIT 10"
        );
        
        pedidos_step_log("Executando query para buscar pedidos pendentes: {$query}", 'PROCESSAR_PEDIDOS_PENDENTES');
        
        $pedidos_pendentes = $wpdb->get_results($query);
        
        if ($wpdb->last_error) {
            pedidos_error_log("Erro na query de pedidos pendentes: {$wpdb->last_error}", 'PROCESSAR_PEDIDOS_PENDENTES');
        }
        
        pedidos_success_log("Pedidos pendentes encontrados: " . count($pedidos_pendentes), 'PROCESSAR_PEDIDOS_PENDENTES');
        
        foreach ($pedidos_pendentes as $pedido) {
            pedidos_step_log("Processando pedido pendente #{$pedido->order_id}", 'PROCESSAR_PEDIDOS_PENDENTES');
            $this->tentar_processar_pedido($pedido);
        }
        
        pedidos_success_log("Processamento de pedidos pendentes concluído", 'PROCESSAR_PEDIDOS_PENDENTES');
    }
    
    /**
     * Tentar processar pedido individual
     */
    private function tentar_processar_pedido($pedido) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pedidos_processados';
        
        pedidos_step_log("Tentando processar pedido #{$pedido->order_id} (Tentativa " . ($pedido->tentativas + 1) . ")", 'TENTAR_PROCESSAR_PEDIDO');
        pedidos_debug_log("Dados do pedido: ID={$pedido->id}, Order ID={$pedido->order_id}, Produto ID={$pedido->produto_id}, Username={$pedido->instagram_username}, Quantidade={$pedido->quantidade_variacao}", 'TENTAR_PROCESSAR_PEDIDO');
        
        try {
            // Verificar se é um pedido distribuído (verificar se o produto requer processamento distribuído)
            $produto = wc_get_product($pedido->produto_id);
            pedidos_debug_log("Produto carregado: " . ($produto ? "SIM - {$produto->get_name()}" : "NÃO"), 'TENTAR_PROCESSAR_PEDIDO');
            
            if ($produto && class_exists('SMMCategoryRules')) {
                // Obter o item_id real do pedido WooCommerce
                $order = wc_get_order($pedido->order_id);
                pedidos_debug_log("Order WooCommerce carregado: " . ($order ? "SIM" : "NÃO"), 'TENTAR_PROCESSAR_PEDIDO');
                
                $item_id = 0;
                if ($order) {
                    pedidos_debug_log("Buscando item do pedido para produto ID {$pedido->produto_id}", 'TENTAR_PROCESSAR_PEDIDO');
                    foreach ($order->get_items() as $item) {
                        pedidos_debug_log("Item encontrado - Product ID: {$item->get_product_id()}, Variation ID: {$item->get_variation_id()}, Item ID: {$item->get_id()}", 'TENTAR_PROCESSAR_PEDIDO');
                        // Verificar tanto o product_id quanto o variation_id
                        if ($item->get_product_id() == $pedido->produto_id || $item->get_variation_id() == $pedido->produto_id) {
                            $item_id = $item->get_id();
                            pedidos_debug_log("Item ID encontrado: {$item_id} (match com product_id: {$item->get_product_id()}, variation_id: {$item->get_variation_id()})", 'TENTAR_PROCESSAR_PEDIDO');
                            break;
                        }
                    }
                }
                
                pedidos_debug_log("Chamando get_distribution_info com item_id={$item_id}, product_id={$pedido->produto_id}", 'TENTAR_PROCESSAR_PEDIDO');
                $distribution_info = SMMCategoryRules::get_distribution_info([
                    'item_id' => $item_id,
                    'product_id' => $pedido->produto_id
                ]);
                
                pedidos_debug_log("Resultado da verificação de distribuição: " . ($distribution_info['is_distributed'] ? 'SIM' : 'NÃO'), 'TENTAR_PROCESSAR_PEDIDO');
                pedidos_debug_log("Detalhes da distribuição: " . print_r($distribution_info, true), 'TENTAR_PROCESSAR_PEDIDO');
                
                if ($distribution_info['is_distributed']) {
                    pedidos_success_log("Pedido distribuído detectado para produto '{$produto->get_name()}'", 'TENTAR_PROCESSAR_PEDIDO');
                    return $this->processar_pedido_distribuido($pedido);
                } else {
                    // Se o produto está configurado para posts/reels mas não tem links, falhar
                    $produto_id = $pedido->produto_id;
                    $produto_obj = wc_get_product($produto_id);
                    if ($produto_obj && $produto_obj->is_type('variation')) {
                        $produto_id = $produto_obj->get_parent_id();
                    }
                    
                    $logic_type = get_post_meta($produto_id, '_smm_logic_type', true);
                    
                    if (in_array($logic_type, ['posts_reels', 'comentarios_ia'])) {
                        pedidos_error_log("Produto configurado para {$logic_type} mas sem links de publicações", 'TENTAR_PROCESSAR_PEDIDO');
                        $this->marcar_pedido_erro($pedido, "Produto configurado para {$logic_type} mas sem links de publicações");
                        return false;
                    } else {
                        pedidos_debug_log("Pedido NÃO é distribuído, continuando com processamento normal", 'TENTAR_PROCESSAR_PEDIDO');
                    }
                }
            }
            
            // Verificar se o pedido tem username do Instagram (apenas para pedidos não distribuídos)
            if (empty($pedido->instagram_username)) {
                pedidos_error_log("Pedido #{$pedido->order_id} não tem username do Instagram configurado", 'TENTAR_PROCESSAR_PEDIDO');
                $this->marcar_pedido_erro($pedido, 'Username do Instagram não configurado');
                return false;
            }
            
            // Verificar se o perfil é público (apenas para pedidos não distribuídos)
            $perfil_publico = $this->verificar_perfil_publico($pedido);
            if ($perfil_publico === false) {
                pedidos_warning_log("Pedido #{$pedido->order_id} - Perfil não é público", 'TENTAR_PROCESSAR_PEDIDO');
                $this->marcar_pedido_erro($pedido, 'Perfil do Instagram não é público');
                return false;
            } elseif ($perfil_publico === null) {
                pedidos_warning_log("Pedido #{$pedido->order_id} - Status do perfil desconhecido", 'TENTAR_PROCESSAR_PEDIDO');
                $this->marcar_pedido_erro($pedido, 'Status do perfil Instagram desconhecido');
                return false;
            }
            
            pedidos_success_log("Perfil público confirmado para pedido #{$pedido->order_id}", 'TENTAR_PROCESSAR_PEDIDO');
            
            pedidos_success_log("Username Instagram encontrado: {$pedido->instagram_username}", 'TENTAR_PROCESSAR_PEDIDO');
            
            // Verificar se a quantidade é válida
            if ($pedido->quantidade_variacao <= 0) {
                pedidos_warning_log("Pedido #{$pedido->order_id} tem quantidade inválida: {$pedido->quantidade_variacao}", 'TENTAR_PROCESSAR_PEDIDO');
                $this->marcar_pedido_erro($pedido, 'Quantidade inválida: ' . $pedido->quantidade_variacao);
                return false;
            }
            
            pedidos_success_log("Quantidade válida: {$pedido->quantidade_variacao}", 'TENTAR_PROCESSAR_PEDIDO');
            
            // Enviar para API SMM
            pedidos_step_log("Enviando pedido #{$pedido->order_id} para API SMM", 'TENTAR_PROCESSAR_PEDIDO');
            $sucesso = $this->enviar_para_api_smm($pedido);
            
            if ($sucesso) {
                pedidos_api_smm_log("Pedido #{$pedido->order_id} processado com sucesso na API SMM", 'TENTAR_PROCESSAR_PEDIDO');
                
                // O status já foi atualizado pela função enviar_para_api_smm
                // Apenas confirmar o sucesso
                pedidos_api_smm_log("Status do pedido #{$pedido->order_id} confirmado como 'processing'", 'TENTAR_PROCESSAR_PEDIDO');
                return true;
            } else {
                // Marcar para nova tentativa em 2 minutos
                $tentativas = $pedido->tentativas + 1;
                $proxima_tentativa = date('Y-m-d H:i:s', strtotime('+2 minutes'));
                
                pedidos_warning_log("Pedido #{$pedido->order_id} falhou na tentativa {$tentativas}. Nova tentativa em 2 minutos", 'TENTAR_PROCESSAR_PEDIDO');
                
                $update_result = $wpdb->update(
                    $table_name,
                    [
                        'status_api' => 'pending',
                        'tentativas' => $tentativas,
                        'proxima_tentativa' => $proxima_tentativa,
                        'mensagem_api' => 'Tentativa ' . $tentativas . ' falhou. Nova tentativa em 2 minutos',
                        'data_atualizacao' => current_time('mysql')
                    ],
                    ['id' => $pedido->id],
                    ['%s', '%d', '%s', '%s', '%s'],
                    ['%d']
                );
                
                if ($update_result === false) {
                    pedidos_error_log("Erro ao atualizar status do pedido #{$pedido->order_id}: {$wpdb->last_error}", 'TENTAR_PROCESSAR_PEDIDO');
                } else {
                    pedidos_success_log("Pedido #{$pedido->order_id} marcado para nova tentativa", 'TENTAR_PROCESSAR_PEDIDO');
                }
                
                return false;
            }
            
        } catch (Exception $e) {
            pedidos_error_log("Exceção ao processar pedido {$pedido->order_id}: " . $e->getMessage(), 'TENTAR_PROCESSAR_PEDIDO');
            $this->marcar_pedido_erro($pedido, 'Exceção: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Marcar pedido com sucesso
     */
    private function marcar_pedido_sucesso($pedido, $mensagem) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pedidos_processados';
        
        pedidos_step_log("Marcando pedido #{$pedido->order_id} com sucesso: {$mensagem}", 'MARCAR_PEDIDO_SUCESSO');
        
        // Verificar se já existe order_id_api para não sobrescrever
        $existing_order = $wpdb->get_row($wpdb->prepare("SELECT order_id_api FROM {$table_name} WHERE id = %d", $pedido->id));
        $preserve_order_id = !empty($existing_order->order_id_api);
        
        $update_data = [
            'status_api' => 'completed',
            'mensagem_api' => $mensagem,
            'data_atualizacao' => current_time('mysql')
        ];
        
        // Se não há order_id_api, tentar obter do pedido atual
        if (!$preserve_order_id && !empty($pedido->order_id_api)) {
            $update_data['order_id_api'] = $pedido->order_id_api;
        }
        
        $update_result = $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $pedido->id],
            array_fill(0, count($update_data), '%s'),
            ['%d']
        );
        
        if ($update_result === false) {
            pedidos_error_log("Erro ao marcar pedido #{$pedido->order_id} com sucesso: {$wpdb->last_error}", 'MARCAR_PEDIDO_SUCESSO');
        } else {
            pedidos_success_log("Pedido #{$pedido->order_id} marcado como concluído com sucesso", 'MARCAR_PEDIDO_SUCESSO');
        }
    }
    
    /**
     * Verificar se o perfil do Instagram é público
     */
    private function verificar_perfil_publico($pedido) {
        // Obter o pedido WooCommerce
        $order = wc_get_order($pedido->order_id);
        if (!$order) {
            pedidos_error_log("Pedido WooCommerce #{$pedido->order_id} não encontrado para verificação de perfil", 'VERIFICAR_PERFIL_PUBLICO');
            return null;
        }
        
        // Buscar o item do pedido correspondente
        $item_id = null;
        foreach ($order->get_items() as $item) {
            if ($item->get_product_id() == $pedido->produto_id || $item->get_variation_id() == $pedido->produto_id) {
                $item_id = $item->get_id();
                break;
            }
        }
        
        if (!$item_id) {
            pedidos_error_log("Item do pedido não encontrado para produto ID {$pedido->produto_id}", 'VERIFICAR_PERFIL_PUBLICO');
            return null;
        }
        
        // Obter o status do perfil
        $status_perfil = wc_get_order_item_meta($item_id, 'Status do Perfil', true);
        pedidos_debug_log("Status do perfil encontrado: '{$status_perfil}' para item ID {$item_id}", 'VERIFICAR_PERFIL_PUBLICO');
        
        // Verificar se o campo existe
        if (empty($status_perfil)) {
            pedidos_warning_log("Campo 'Status do Perfil' não encontrado ou vazio para item ID {$item_id}", 'VERIFICAR_PERFIL_PUBLICO');
            return null;
        }
        
        // Verificar se é um valor válido
        $valores_validos = ['Público', 'Privado', 'Desconhecido'];
        if (!in_array($status_perfil, $valores_validos)) {
            pedidos_warning_log("Valor inválido para 'Status do Perfil': '{$status_perfil}'", 'VERIFICAR_PERFIL_PUBLICO');
            return null;
        }
        
        // Retornar true se público, false se privado, null se desconhecido
        switch ($status_perfil) {
            case 'Público':
                pedidos_success_log("Perfil confirmado como PÚBLICO", 'VERIFICAR_PERFIL_PUBLICO');
                return true;
            case 'Privado':
                pedidos_warning_log("Perfil confirmado como PRIVADO", 'VERIFICAR_PERFIL_PUBLICO');
                // Enviar e-mail de notificação para o cliente
                $this->enviar_email_perfil_privado($pedido, $item_id);
                return false;
            case 'Desconhecido':
            default:
                pedidos_warning_log("Status do perfil DESCONHECIDO", 'VERIFICAR_PERFIL_PUBLICO');
                return null;
        }
    }
    
    /**
     * Enviar e-mail de notificação para perfil privado
     */
    private function enviar_email_perfil_privado($pedido, $item_id) {
        try {
            // Obter dados do pedido
            $order = wc_get_order($pedido->order_id);
            if (!$order) {
                pedidos_error_log("Pedido WooCommerce não encontrado para envio de e-mail", 'ENVIAR_EMAIL_PERFIL_PRIVADO');
                return false;
            }
            
            // Obter dados do cliente
            $cliente_email = $order->get_billing_email();
            $cliente_nome = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $instagram_username = $pedido->instagram_username;
            
            if (empty($cliente_email)) {
                pedidos_error_log("E-mail do cliente não encontrado para pedido #{$pedido->order_id}", 'ENVIAR_EMAIL_PERFIL_PRIVADO');
                return false;
            }
            
            // Gerar token de confirmação
            $token = $this->gerar_token_confirmacao($pedido->id, $item_id);
            if (!$token) {
                pedidos_error_log("Falha ao gerar token de confirmação", 'ENVIAR_EMAIL_PERFIL_PRIVADO');
                return false;
            }
            
            // Criar link de confirmação
            $link_confirmacao = home_url("/confirmar-perfil-publico/?token={$token}");
            
            // Criar link de tracking (opcional)
            $tracking_link = home_url("/track-email/?token={$token}&action=open");
            
            // Preparar dados do e-mail
            $assunto = "🔒 Seu perfil Instagram precisa ser público - Pedido #{$pedido->order_id}";
            
            $mensagem = $this->preparar_mensagem_email_perfil_privado([
                'cliente_nome' => $cliente_nome,
                'order_id' => $pedido->order_id,
                'instagram_username' => $instagram_username,
                'link_confirmacao' => $link_confirmacao,
                'produto_nome' => $pedido->produto_nome
            ]);
            
            // Configurar headers do e-mail profissionais
            $site_name = get_bloginfo('name');
            $admin_email = get_option('admin_email');
            $current_time = current_time('r');
            
            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $site_name . ' <noreply@seguipix.com.br>',
                'Reply-To: ' . $admin_email,
                'Return-Path: noreply@seguipix.com.br',
                'Sender: noreply@seguipix.com.br',
                'X-Mailer: ' . $site_name . ' Plugin v1.0',
                'X-Priority: 3',
                'X-MSMail-Priority: Normal',
                'Importance: Normal',
                'X-Report-Abuse: Please report abuse to ' . $admin_email,
                'List-Unsubscribe: <mailto:' . $admin_email . '?subject=Unsubscribe>',
                'Message-ID: <' . time() . '.' . uniqid() . '@seguipix.com.br>',
                'Date: ' . $current_time,
                'MIME-Version: 1.0'
            ];
            
            // Enviar e-mail com retry
            $enviado = $this->enviar_email_com_retry($cliente_email, $assunto, $mensagem, $headers);
            
            if ($enviado) {
                pedidos_success_log("E-mail de perfil privado enviado para {$cliente_email} - Pedido #{$pedido->order_id}", 'ENVIAR_EMAIL_PERFIL_PRIVADO');
                
                // Marcar que o e-mail foi enviado
                $this->marcar_email_enviado($pedido->id, $item_id);
                
                return true;
            } else {
                pedidos_error_log("Falha ao enviar e-mail de perfil privado para {$cliente_email} após 3 tentativas", 'ENVIAR_EMAIL_PERFIL_PRIVADO');
                return false;
            }
            
        } catch (Exception $e) {
            pedidos_error_log("Erro ao enviar e-mail de perfil privado: " . $e->getMessage(), 'ENVIAR_EMAIL_PERFIL_PRIVADO');
            return false;
        }
    }
    
    /**
     * Preparar mensagem do e-mail para perfil privado
     */
    private function preparar_mensagem_email_perfil_privado($dados) {
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        $current_time = current_time('d/m/Y H:i');
        $expiration_time = date('d/m/Y H:i', strtotime('+24 hours'));
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Perfil Instagram Privado - ' . esc_html($site_name) . '</title>
            <style>
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; 
                    line-height: 1.6; 
                    color: #333; 
                    max-width: 600px; 
                    margin: 0 auto; 
                    padding: 20px; 
                    background: #f8f9fa;
                }
                .email-container { 
                    background: white; 
                    border-radius: 12px; 
                    overflow: hidden; 
                    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                }
                .header { 
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                    color: white; 
                    padding: 40px 30px; 
                    text-align: center; 
                }
                .header h1 { 
                    margin: 0 0 10px 0; 
                    font-size: 28px; 
                    font-weight: 700;
                }
                .header p { 
                    margin: 0; 
                    font-size: 16px; 
                    opacity: 0.9;
                }
                .content { 
                    padding: 40px 30px; 
                }
                .greeting { 
                    font-size: 18px; 
                    margin-bottom: 25px; 
                    color: #2c3e50;
                }
                .alert { 
                    background: #fff3cd; 
                    border: 1px solid #ffeaa7; 
                    color: #856404; 
                    padding: 20px; 
                    border-radius: 8px; 
                    margin: 25px 0; 
                    border-left: 4px solid #ffc107;
                }
                .alert strong { 
                    color: #856404; 
                    font-weight: 600;
                }
                .order-info { 
                    background: #f8f9fa; 
                    border: 1px solid #e9ecef; 
                    border-radius: 8px; 
                    padding: 20px; 
                    margin: 25px 0;
                }
                .order-info h3 { 
                    margin: 0 0 15px 0; 
                    color: #495057; 
                    font-size: 16px;
                }
                .order-detail { 
                    display: flex; 
                    justify-content: space-between; 
                    margin: 8px 0; 
                    padding: 5px 0;
                }
                .order-detail strong { 
                    color: #495057;
                }
                .order-detail span { 
                    color: #6c757d;
                }
                .steps { 
                    background: #e7f3ff; 
                    border-left: 4px solid #007cba; 
                    padding: 25px; 
                    margin: 25px 0; 
                    border-radius: 0 8px 8px 0;
                }
                .steps h3 { 
                    margin: 0 0 20px 0; 
                    color: #007cba; 
                    font-size: 18px;
                }
                .steps ol { 
                    margin: 0; 
                    padding-left: 20px;
                }
                .steps li { 
                    margin: 12px 0; 
                    color: #495057;
                }
                .btn-container { 
                    text-align: center; 
                    margin: 35px 0;
                }
                .btn { 
                    display: inline-block; 
                    background: #28a745; 
                    color: white; 
                    padding: 18px 35px; 
                    text-decoration: none; 
                    border-radius: 8px; 
                    font-weight: 600; 
                    font-size: 16px; 
                    transition: background 0.3s ease;
                    box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
                }
                .btn:hover { 
                    background: #218838; 
                    text-decoration: none; 
                    color: white;
                }
                .info-box { 
                    background: #d1ecf1; 
                    border: 1px solid #bee5eb; 
                    color: #0c5460; 
                    padding: 20px; 
                    border-radius: 8px; 
                    margin: 25px 0;
                }
                .info-box strong { 
                    color: #0c5460;
                }
                .footer { 
                    background: #f8f9fa; 
                    padding: 30px; 
                    text-align: center; 
                    color: #6c757d; 
                    font-size: 14px; 
                    border-top: 1px solid #e9ecef;
                }
                .footer p { 
                    margin: 8px 0;
                }
                .logo { 
                    font-size: 20px; 
                    font-weight: 700; 
                    color: #667eea; 
                    margin-bottom: 10px;
                }
                .expiration { 
                    background: #f8d7da; 
                    border: 1px solid #f5c6cb; 
                    color: #721c24; 
                    padding: 15px; 
                    border-radius: 8px; 
                    margin: 20px 0; 
                    text-align: center;
                }
                .expiration strong { 
                    color: #721c24;
                }
                @media (max-width: 600px) {
                    body { padding: 10px; }
                    .content { padding: 30px 20px; }
                    .header { padding: 30px 20px; }
                    .header h1 { font-size: 24px; }
                    .btn { padding: 15px 25px; font-size: 14px; }
                }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="header">
                    <h1>🔒 Perfil Instagram Privado</h1>
                    <p>Seu pedido precisa de atenção</p>
                </div>
                
                <div class="content">
                    <div class="greeting">
                        Olá <strong>' . esc_html($dados['cliente_nome']) . '</strong>,
                    </div>
                    
                    <div class="alert">
                        <strong>⚠️ Atenção:</strong> Detectamos que seu perfil Instagram <strong>@' . esc_html($dados['instagram_username']) . '</strong> está configurado como <strong>PRIVADO</strong>.
                    </div>
                    
                    <div class="order-info">
                        <h3>📦 Informações do Pedido</h3>
                        <div class="order-detail">
                            <strong>Número do Pedido:</strong>
                            <span>#' . esc_html($dados['order_id']) . '</span>
                        </div>
                        <div class="order-detail">
                            <strong>Produto:</strong>
                            <span>' . esc_html($dados['produto_nome']) . '</span>
                        </div>
                        <div class="order-detail">
                            <strong>Data/Hora:</strong>
                            <span>' . $current_time . '</span>
                        </div>
                    </div>
                    
                    <div class="steps">
                        <h3>📋 O que você precisa fazer:</h3>
                        <ol>
                            <li>Acesse seu Instagram no celular ou computador</li>
                            <li>Vá em <strong>Configurações</strong> → <strong>Privacidade</strong></li>
                            <li>Desative a opção <strong>"Conta Privada"</strong></li>
                            <li>Clique no botão abaixo para confirmar</li>
                        </ol>
                    </div>
                    
                    <div class="btn-container">
                        <a href="' . esc_url($dados['link_confirmacao']) . '" class="btn">
                            ✅ Confirmar Perfil Público
                        </a>
                    </div>
                    
                    <div class="expiration">
                        <strong>⏰ Importante:</strong> Este link expira em <strong>' . $expiration_time . '</strong>
                    </div>
                    
                    <div class="info-box">
                        <strong>💡 Dica:</strong> Você pode tornar seu perfil privado novamente após o processamento, se desejar.
                    </div>
                    
                    <p style="text-align: center; margin: 30px 0; font-size: 16px; color: #495057;">
                        <strong>Após confirmar:</strong> Seu pedido será processado automaticamente em até 5 minutos.
                    </p>
                </div>
                
                <div class="footer">
                    <div class="logo">' . esc_html($site_name) . '</div>
                    <p>Este é um e-mail automático. Não responda a esta mensagem.</p>
                    <p>Se você não fez este pedido, ignore este e-mail.</p>
                    <p><a href="' . esc_url($site_url) . '" style="color: #667eea; text-decoration: none;">' . esc_url($site_url) . '</a></p>
                </div>
                
                <!-- Tracking pixel invisível -->
                <img src="' . esc_url($tracking_link) . '" width="1" height="1" style="display: none;" alt="" />
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Gerar token de confirmação
     */
    private function gerar_token_confirmacao($pedido_id, $item_id) {
        global $wpdb;
        
        // Gerar token único
        $token = wp_generate_password(32, false);
        
        // Salvar token na tabela
        $table_name = $wpdb->prefix . 'pedidos_confirmacao_perfil';
        
        // Criar tabela se não existir
        $this->criar_tabela_confirmacao_perfil();
        
        $resultado = $wpdb->insert(
            $table_name,
            [
                'pedido_id' => $pedido_id,
                'item_id' => $item_id,
                'token' => $token,
                'status' => 'pending',
                'data_criacao' => current_time('mysql'),
                'data_expiracao' => date('Y-m-d H:i:s', strtotime('+24 hours'))
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s']
        );
        
        if ($resultado !== false) {
            pedidos_success_log("Token de confirmação gerado: {$token} para pedido #{$pedido_id}", 'GERAR_TOKEN_CONFIRMACAO');
            return $token;
        } else {
            pedidos_error_log("Erro ao gerar token de confirmação: " . $wpdb->last_error, 'GERAR_TOKEN_CONFIRMACAO');
            return false;
        }
    }
    
    /**
     * Criar tabela de confirmação de perfil
     */
    private function criar_tabela_confirmacao_perfil() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'pedidos_confirmacao_perfil';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            pedido_id bigint(20) NOT NULL,
            item_id bigint(20) NOT NULL,
            token varchar(32) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            data_criacao datetime DEFAULT CURRENT_TIMESTAMP,
            data_expiracao datetime NOT NULL,
            data_confirmacao datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY pedido_id (pedido_id),
            KEY item_id (item_id),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Enviar e-mail com sistema de retry
     */
    private function enviar_email_com_retry($to, $subject, $message, $headers, $max_tentativas = 3) {
        $tentativas = 0;
        $enviado = false;
        
        while ($tentativas < $max_tentativas && !$enviado) {
            $tentativas++;
            
            pedidos_debug_log("Tentativa {$tentativas}/{$max_tentativas} de envio de e-mail para {$to}", 'ENVIAR_EMAIL_COM_RETRY');
            
            $enviado = wp_mail($to, $subject, $message, $headers);
            
            if (!$enviado) {
                pedidos_warning_log("Tentativa {$tentativas} falhou para {$to}", 'ENVIAR_EMAIL_COM_RETRY');
                
                // Aguardar antes da próxima tentativa (exponential backoff)
                if ($tentativas < $max_tentativas) {
                    $delay = pow(2, $tentativas - 1) * 5; // 5s, 10s, 20s
                    pedidos_debug_log("Aguardando {$delay} segundos antes da próxima tentativa", 'ENVIAR_EMAIL_COM_RETRY');
                    sleep($delay);
                }
            } else {
                pedidos_success_log("E-mail enviado com sucesso na tentativa {$tentativas}", 'ENVIAR_EMAIL_COM_RETRY');
            }
        }
        
        return $enviado;
    }
    
    /**
     * Marcar que o e-mail foi enviado
     */
    private function marcar_email_enviado($pedido_id, $item_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'pedidos_processados';
        
        $wpdb->update(
            $table_name,
            [
                'mensagem_api' => 'E-mail de perfil privado enviado - Aguardando confirmação',
                'data_atualizacao' => current_time('mysql')
            ],
            ['id' => $pedido_id],
            ['%s', '%s'],
            ['%d']
        );
    }
    
    /**
     * Inicializar sistema de confirmação de perfil
     */
    public function init_confirmacao_perfil() {
        // Verificar se é a página de confirmação
        if (isset($_GET['token']) && !empty($_GET['token'])) {
            $this->processar_confirmacao_perfil($_GET['token']);
        }
    }
    
    /**
     * Processar confirmação de perfil público
     */
    private function processar_confirmacao_perfil($token) {
        global $wpdb;
        
        try {
            // Buscar token na tabela
            $table_name = $wpdb->prefix . 'pedidos_confirmacao_perfil';
            $confirmacao = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE token = %s AND status = 'pending'",
                $token
            ));
            
            pedidos_debug_log("Token recebido: {$token}", 'PROCESSAR_CONFIRMACAO_PERFIL');
            pedidos_debug_log("Confirmação encontrada: " . ($confirmacao ? 'SIM' : 'NÃO'), 'PROCESSAR_CONFIRMACAO_PERFIL');
            
            if (!$confirmacao) {
                pedidos_error_log("Token inválido ou já utilizado: {$token}", 'PROCESSAR_CONFIRMACAO_PERFIL');
                $this->exibir_pagina_confirmacao('error', 'Token inválido ou já utilizado.');
                return;
            }
            
            pedidos_debug_log("Dados da confirmação: " . print_r($confirmacao, true), 'PROCESSAR_CONFIRMACAO_PERFIL');
            
            // Verificar se o token não expirou
            if (strtotime($confirmacao->data_expiracao) < time()) {
                $this->exibir_pagina_confirmacao('error', 'Token expirado. Solicite um novo link de confirmação.');
                return;
            }
            
            // Buscar o pedido na nossa tabela primeiro
            $table_pedidos = $wpdb->prefix . 'pedidos_processados';
            $pedido_interno = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_pedidos} WHERE id = %d",
                $confirmacao->pedido_id
            ));
            
            if (!$pedido_interno) {
                $this->exibir_pagina_confirmacao('error', 'Pedido interno não encontrado.');
                return;
            }
            
            // Agora buscar o pedido WooCommerce usando o order_id
            $order = wc_get_order($pedido_interno->order_id);
            if (!$order) {
                $this->exibir_pagina_confirmacao('error', 'Pedido WooCommerce não encontrado.');
                return;
            }
            
            // Atualizar meta do item
            wc_update_order_item_meta($confirmacao->item_id, 'Status do Perfil', 'Público');
            
            // Marcar confirmação como concluída
            $wpdb->update(
                $table_name,
                [
                    'status' => 'confirmed',
                    'data_confirmacao' => current_time('mysql')
                ],
                ['id' => $confirmacao->id],
                ['%s', '%s'],
                ['%d']
            );
            
            // Atualizar status do pedido para reprocessar
            $this->reprocessar_pedido_apos_confirmacao($pedido_interno->order_id);
            
            pedidos_success_log("Perfil confirmado como público para pedido #{$confirmacao->pedido_id}", 'PROCESSAR_CONFIRMACAO_PERFIL');
            
            $this->exibir_pagina_confirmacao('success', 'Perfil confirmado como público! Seu pedido será processado em breve.');
            
        } catch (Exception $e) {
            pedidos_error_log("Erro ao processar confirmação de perfil: " . $e->getMessage(), 'PROCESSAR_CONFIRMACAO_PERFIL');
            $this->exibir_pagina_confirmacao('error', 'Erro interno. Tente novamente mais tarde.');
        }
    }
    
    /**
     * Reprocessar pedido após confirmação
     */
    private function reprocessar_pedido_apos_confirmacao($order_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'pedidos_processados';
        
        // Buscar pedidos pendentes para este order_id
        $pedidos = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE order_id = %d AND status_api = 'pending'",
            $order_id
        ));
        
        foreach ($pedidos as $pedido) {
            // Atualizar para reprocessar
            $wpdb->update(
                $table_name,
                [
                    'status_api' => 'pending',
                    'tentativas' => 0,
                    'proxima_tentativa' => date('Y-m-d H:i:s', strtotime('+1 minute')),
                    'mensagem_api' => 'Aguardando reprocessamento após confirmação de perfil público',
                    'data_atualizacao' => current_time('mysql')
                ],
                ['id' => $pedido->id],
                ['%s', '%d', '%s', '%s', '%s'],
                ['%d']
            );
        }
        
        pedidos_success_log("Pedido #{$order_id} marcado para reprocessamento", 'REPROCESSAR_PEDIDO_APOS_CONFIRMACAO');
    }
    
    /**
     * Exibir página de confirmação
     */
    private function exibir_pagina_confirmacao($tipo, $mensagem) {
        // Definir headers para evitar cache
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $titulo = ($tipo === 'success') ? '✅ Confirmação Realizada!' : '❌ Erro na Confirmação';
        $cor = ($tipo === 'success') ? '#28a745' : '#dc3545';
        
        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . esc_html($titulo) . '</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    line-height: 1.6; 
                    color: #333; 
                    max-width: 600px; 
                    margin: 50px auto; 
                    padding: 20px; 
                    background: #f5f5f5;
                }
                .container { 
                    background: white; 
                    padding: 40px; 
                    border-radius: 10px; 
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    text-align: center;
                }
                .icon { 
                    font-size: 48px; 
                    margin-bottom: 20px; 
                }
                .title { 
                    color: ' . $cor . '; 
                    font-size: 24px; 
                    margin-bottom: 20px; 
                }
                .message { 
                    font-size: 16px; 
                    margin-bottom: 30px; 
                }
                .btn { 
                    display: inline-block; 
                    background: #007cba; 
                    color: white; 
                    padding: 12px 24px; 
                    text-decoration: none; 
                    border-radius: 5px; 
                    font-weight: bold; 
                }
                .btn:hover { 
                    background: #005a87; 
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="icon">' . ($tipo === 'success' ? '✅' : '❌') . '</div>
                <h1 class="title">' . esc_html($titulo) . '</h1>
                <p class="message">' . esc_html($mensagem) . '</p>
                <a href="' . home_url() . '" class="btn">Voltar ao Site</a>
            </div>
        </body>
        </html>';
        
        exit;
    }
    
    /**
     * Marcar pedido com erro
     */
    private function marcar_pedido_erro($pedido, $mensagem) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pedidos_processados';
        
        pedidos_step_log("Marcando pedido #{$pedido->order_id} com erro: {$mensagem}", 'MARCAR_PEDIDO_ERRO');
        
        $tentativas = $pedido->tentativas + 1;
        $proxima_tentativa = date('Y-m-d H:i:s', strtotime('+2 minutes'));
        
        $update_result = $wpdb->update(
            $table_name,
            [
                'status_api' => 'pending',
                'tentativas' => $tentativas,
                'proxima_tentativa' => $proxima_tentativa,
                'mensagem_api' => $mensagem,
                'data_atualizacao' => current_time('mysql')
            ],
            ['id' => $pedido->id],
            ['%s', '%d', '%s', '%s', '%s'],
            ['%d']
        );
        
        if ($update_result === false) {
            pedidos_error_log("Erro ao marcar pedido #{$pedido->order_id} com erro: {$wpdb->last_error}", 'MARCAR_PEDIDO_ERRO');
        } else {
            pedidos_success_log("Pedido #{$pedido->order_id} marcado com erro e agendado para nova tentativa", 'MARCAR_PEDIDO_ERRO');
        }
    }
    
    
    /**
     * Enviar para API SMM usando o módulo SMM
     */
    private function enviar_para_api_smm($pedido) {
        pedidos_api_smm_log("Iniciando envio para API SMM - Pedido #{$pedido->order_id}", 'ENVIAR_API_SMM');
        
        try {
            // Verificar se o módulo SMM está disponível
            if (!class_exists('SMMApi') || !class_exists('SMMProvidersManager')) {
                pedidos_api_smm_log('Módulo SMM não disponível para pedido ' . $pedido->order_id, 'ENVIAR_API_SMM');
                return false;
            }
            
            pedidos_api_smm_log('Classes SMM encontradas', 'ENVIAR_API_SMM');
            
            // Obter provedores configurados
            $providers = get_option('smm_providers', []);
            if (empty($providers)) {
                pedidos_api_smm_log('Nenhum provedor SMM configurado para pedido ' . $pedido->order_id, 'ENVIAR_API_SMM');
                return false;
            }
            
            pedidos_api_smm_log('Provedores encontrados: ' . count($providers), 'ENVIAR_API_SMM');
            
            // Obter provedor do produto ou usar o padrão
            $provider_id = '';
            if (class_exists('SMMModule')) {
                $provider_id = SMMModule::get_product_provider($pedido->produto_id);
                if (empty($provider_id)) {
                    $provider_id = get_option('smm_default_provider', '');
                    pedidos_warning_log("Provedor individual não encontrado para produto #{$pedido->produto_id}, usando provedor padrão: {$provider_id}", 'ENVIAR_API_SMM');
                } else {
                    pedidos_success_log("Provedor individual encontrado para produto #{$pedido->produto_id}: {$provider_id}", 'ENVIAR_API_SMM');
                }
            } else {
                $provider_id = get_option('smm_default_provider', '');
            }
            
            if (empty($provider_id) || !isset($providers[$provider_id])) {
                pedidos_api_smm_log('Provedor não configurado ou inválido para pedido ' . $pedido->order_id, 'ENVIAR_API_SMM');
                return false;
            }
            
            $active_provider = $providers[$provider_id];
            
            pedidos_api_smm_log("Provedor ativo encontrado: {$active_provider['name']}", 'ENVIAR_API_SMM');
            pedidos_api_smm_log("URL da API: {$active_provider['api_url']}", 'ENVIAR_API_SMM');
            
            // Criar instância da API SMM (mesma configuração do teste)
            $smm_api = new SMMApi();
            $smm_api->api_url = $active_provider['api_url'];
            $smm_api->api_key = $active_provider['api_key'];
            $smm_api->timeout = 30;
            
            pedidos_api_smm_log('Instância da API SMM criada', 'ENVIAR_API_SMM');
            
            // Obter Service ID diretamente do pedido
            $service_id = $this->obter_service_id_do_pedido($pedido);
            if ($service_id === false) {
                pedidos_api_smm_log('Service ID não encontrado no pedido #' . $pedido->order_id . '. Verifique se foi configurado no produto.', 'ENVIAR_API_SMM');
                return false;
            }
            
            pedidos_api_smm_log('Service ID determinado para pedido #' . $pedido->order_id . ': ' . $service_id, 'ENVIAR_API_SMM');
            
            // Preparar dados para a API SMM (conectando com dados reais)
            $order_data = [
                'service' => $service_id,                    // Service ID configurado no admin do produto
                'link' => $pedido->instagram_username,       // Username do Instagram do pedido
                'quantity' => $pedido->quantidade_variacao,  // Quantidade da variação do produto
                'runs' => 1,                                 // Uma execução
                'interval' => 0                              // Sem intervalo
                // Comentários serão adicionados pela lógica específica se necessário
            ];
            
            pedidos_api_smm_log('Dados preparados para envio', 'ENVIAR_API_SMM');
            pedidos_data_log($order_data, 'ENVIAR_API_SMM - Dados para envio');
            
            // Enviar pedido para a API (mesmo método do teste)
            pedidos_api_smm_log("Enviando pedido #{$pedido->order_id} para API SMM", 'ENVIAR_API_SMM');
            $response = $smm_api->order($order_data);
            
            if (!$response) {
                pedidos_api_smm_log('Resposta vazia da API SMM para pedido ' . $pedido->order_id, 'ENVIAR_API_SMM');
                return false;
            }
            
            pedidos_api_smm_log('Resposta recebida da API SMM', 'ENVIAR_API_SMM');
            pedidos_data_log($response, 'ENVIAR_API_SMM - Resposta da API');
            
            // Verificar resposta (mesma lógica do teste)
            if (is_object($response)) {
                if (isset($response->order) && !empty($response->order)) {
                    pedidos_api_smm_log("Pedido SMM criado com sucesso! ID: {$response->order} para pedido WooCommerce {$pedido->order_id}", 'ENVIAR_API_SMM');
                    
                    // Salvar ID do pedido SMM no banco
                    $this->salvar_id_pedido_smm($pedido->id, $response->order);
                    
                    return true;
                    
                } elseif (isset($response->error) && !empty($response->error)) {
                    pedidos_api_smm_log("Erro da API SMM para pedido {$pedido->order_id}: {$response->error}", 'ENVIAR_API_SMM');
                    return false;
                }
            }
            
            // Resposta inesperada
            pedidos_api_smm_log('Resposta inesperada da API SMM para pedido ' . $pedido->order_id, 'ENVIAR_API_SMM');
            pedidos_data_log($response, 'ENVIAR_API_SMM - Resposta inesperada');
            return false;
            
        } catch (Exception $e) {
            pedidos_api_smm_log('Exceção ao enviar pedido ' . $pedido->order_id . ' para API SMM: ' . $e->getMessage(), 'ENVIAR_API_SMM');
            return false;
        }
    }
    
    /**
     * Processar pedido distribuído (com links de publicações/reels)
     */
    private function processar_pedido_distribuido($pedido) {
        pedidos_step_log("Processando pedido distribuído #{$pedido->order_id}", 'PROCESSAR_PEDIDO_DISTRIBUIDO');
        pedidos_debug_log("Dados do pedido distribuído: ID={$pedido->id}, Order ID={$pedido->order_id}, Produto ID={$pedido->produto_id}", 'PROCESSAR_PEDIDO_DISTRIBUIDO');
        
        try {
            // Obter informações do pedido WooCommerce
            $order = wc_get_order($pedido->order_id);
            pedidos_debug_log("Order WooCommerce carregado: " . ($order ? "SIM" : "NÃO"), 'PROCESSAR_PEDIDO_DISTRIBUIDO');
            
            if (!$order) {
                pedidos_error_log("Pedido WooCommerce #{$pedido->order_id} não encontrado", 'PROCESSAR_PEDIDO_DISTRIBUIDO');
                return false;
            }
            
            // Buscar item do pedido
            $items = $order->get_items();
            pedidos_debug_log("Total de itens no pedido: " . count($items), 'PROCESSAR_PEDIDO_DISTRIBUIDO');
            
            $item = null;
            foreach ($items as $order_item) {
                pedidos_debug_log("Verificando item - Product ID: {$order_item->get_product_id()}, Variation ID: {$order_item->get_variation_id()}, Item ID: {$order_item->get_id()}", 'PROCESSAR_PEDIDO_DISTRIBUIDO');
                // Verificar se é o produto direto ou uma variação
                if ($order_item->get_product_id() == $pedido->produto_id || 
                    $order_item->get_variation_id() == $pedido->produto_id) {
                    $item = $order_item;
                    pedidos_debug_log("Item encontrado: {$order_item->get_id()}", 'PROCESSAR_PEDIDO_DISTRIBUIDO');
                    break;
                }
            }
            
            if (!$item) {
                pedidos_error_log("Item do pedido #{$pedido->order_id} não encontrado", 'PROCESSAR_PEDIDO_DISTRIBUIDO');
                return false;
            }
            
            // Obter informações de distribuição
            $produto = $item->get_product();
            $produto_nome = $produto ? $produto->get_name() : $item->get_name();
            pedidos_debug_log("Produto do item: {$produto_nome}", 'PROCESSAR_PEDIDO_DISTRIBUIDO');
            
            pedidos_debug_log("Chamando get_distribution_info com item_id={$item->get_id()}, product_id={$pedido->produto_id}", 'PROCESSAR_PEDIDO_DISTRIBUIDO');
            $distribution_info = SMMCategoryRules::get_distribution_info([
                'item_id' => $item->get_id(),
                'product_id' => $pedido->produto_id
            ]);
            
            pedidos_debug_log("Resultado get_distribution_info: " . print_r($distribution_info, true), 'PROCESSAR_PEDIDO_DISTRIBUIDO');
            
            if (!$distribution_info['is_distributed'] || empty($distribution_info['links'])) {
                pedidos_error_log("Pedido #{$pedido->order_id} não tem links distribuídos válidos", 'PROCESSAR_PEDIDO_DISTRIBUIDO');
                $this->marcar_pedido_erro($pedido, 'Links distribuídos não encontrados');
                return false;
            }
            
            pedidos_success_log("Processando {$distribution_info['total_quantity']} links distribuídos", 'PROCESSAR_PEDIDO_DISTRIBUIDO');
            
            // Obter Service ID
            $service_id = $this->obter_service_id_do_pedido($pedido);
            if ($service_id === false) {
                pedidos_error_log("Service ID não encontrado para pedido distribuído #{$pedido->order_id}", 'PROCESSAR_PEDIDO_DISTRIBUIDO');
                return false;
            }
            
            // Processar cada link distribuído
            $sucessos = 0;
            $erros = 0;
            
            foreach ($distribution_info['links'] as $index => $link) {
                pedidos_step_log("Processando link " . ($index + 1) . "/" . count($distribution_info['links']) . " - {$link['type']}: {$link['id']}", 'PROCESSAR_PEDIDO_DISTRIBUIDO');
                
                // Calcular quantidade para este link
                $quantidade_por_link = intval($pedido->quantidade_variacao / count($distribution_info['links']));
                $quantidade_restante = $pedido->quantidade_variacao % count($distribution_info['links']);
                
                if ($index < $quantidade_restante) {
                    $quantidade_por_link++;
                }
                
                // Aplicar multiplicador
                $quantidade_por_link *= $distribution_info['multiplier'];
                
                if ($quantidade_por_link <= 0) {
                    pedidos_warning_log("Quantidade inválida para link {$link['id']}: {$quantidade_por_link}", 'PROCESSAR_PEDIDO_DISTRIBUIDO');
                    continue;
                }
                
                // Enviar pedido individual para este link
                $resultado = $this->enviar_pedido_distribuido_individual($pedido, $link, $quantidade_por_link, $service_id);
                
                if ($resultado) {
                    $sucessos++;
                    pedidos_success_log("Link {$link['id']} processado com sucesso", 'PROCESSAR_PEDIDO_DISTRIBUIDO');
                } else {
                    $erros++;
                    pedidos_error_log("Erro ao processar link {$link['id']}", 'PROCESSAR_PEDIDO_DISTRIBUIDO');
                }
            }
            
            // Atualizar status do pedido
            if ($sucessos > 0) {
                $this->marcar_pedido_sucesso($pedido, "Processado: {$sucessos} links com sucesso, {$erros} erros");
                pedidos_success_log("Pedido distribuído #{$pedido->order_id} processado: {$sucessos} sucessos, {$erros} erros", 'PROCESSAR_PEDIDO_DISTRIBUIDO');
                return true;
            } else {
                $this->marcar_pedido_erro($pedido, "Nenhum link processado com sucesso");
                pedidos_error_log("Pedido distribuído #{$pedido->order_id} falhou: todos os links falharam", 'PROCESSAR_PEDIDO_DISTRIBUIDO');
                return false;
            }
            
        } catch (Exception $e) {
            pedidos_error_log("Erro ao processar pedido distribuído #{$pedido->order_id}: " . $e->getMessage(), 'PROCESSAR_PEDIDO_DISTRIBUIDO');
            $this->marcar_pedido_erro($pedido, 'Erro interno: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enviar pedido individual distribuído para API SMM
     */
    private function enviar_pedido_distribuido_individual($pedido, $link, $quantidade, $service_id) {
        pedidos_step_log("Enviando pedido individual para {$link['type']} {$link['id']} - Quantidade: {$quantidade}", 'ENVIAR_PEDIDO_DISTRIBUIDO_INDIVIDUAL');
        pedidos_debug_log("Dados do link: " . print_r($link, true), 'ENVIAR_PEDIDO_DISTRIBUIDO_INDIVIDUAL');
        pedidos_debug_log("Quantidade: {$quantidade}, Service ID: {$service_id}", 'ENVIAR_PEDIDO_DISTRIBUIDO_INDIVIDUAL');
        
        try {
            // Obter provedor ativo
            $providers = get_option('smm_providers', []);
            if (empty($providers)) {
                pedidos_error_log('Nenhum provedor SMM configurado', 'ENVIAR_PEDIDO_DISTRIBUIDO_INDIVIDUAL');
                return false;
            }
            
            // Usar provedor padrão ou do produto
            $provider_id = '';
            if (class_exists('SMMModule')) {
                $provider_id = SMMModule::get_product_provider($pedido->produto_id);
                if (empty($provider_id)) {
                    $provider_id = get_option('smm_default_provider', '');
                }
            }
            
            if (empty($provider_id) || !isset($providers[$provider_id])) {
                pedidos_error_log('Provedor SMM não encontrado', 'ENVIAR_PEDIDO_DISTRIBUIDO_INDIVIDUAL');
                return false;
            }
            
            $active_provider = $providers[$provider_id];
            
            // Criar instância da API SMM
            $smm_api = new SMMApi();
            $smm_api->api_url = $active_provider['api_url'];
            $smm_api->api_key = $active_provider['api_key'];
            $smm_api->timeout = 30;
            
            // Obter tipo de lógica do produto
            $product_id = $pedido->produto_id;
            if (class_exists('SMMCategoryRules')) {
                $product_id = SMMCategoryRules::get_parent_product_id($product_id);
            }
            $logic_type = get_post_meta($product_id, '_smm_logic_type', true);
            
            // Preparar dados do pedido
            $order_data = [
                'service' => $service_id,
                'link' => $link['url'],
                'quantity' => $quantidade,
                'runs' => 1
            ];
            
            // Verificar se é produto de comentários + IA
            if ($logic_type === 'comentarios_ia') {
                pedidos_debug_log("Produto de comentários + IA detectado - Iniciando geração inteligente", 'ENVIAR_PEDIDO_DISTRIBUIDO_INDIVIDUAL');
                
                try {
                    // Instanciar módulos de scraping e geração
                    $scraper = new InstagramScraper();
                    $generator = new GeminiCommentsGenerator();
                    
                    pedidos_debug_log("Fazendo scraping da publicação: " . $link['url'], 'ENVIAR_PEDIDO_DISTRIBUIDO_INDIVIDUAL');
                    
                    // Obter informações da publicação
                    $post_info = $scraper->get_post_info($link['url']);
                    
                    if ($post_info) {
                        pedidos_debug_log("Scraping realizado com sucesso. Caption: " . substr($post_info['caption'] ?? '', 0, 100), 'ENVIAR_PEDIDO_DISTRIBUIDO_INDIVIDUAL');
                        
                        // Baixar imagem para análise
                        $image_path = null;
                        if (!empty($post_info['display_url'])) {
                            $image_path = $scraper->download_image($post_info['display_url']);
                            pedidos_debug_log("Imagem baixada: " . ($image_path ? 'SIM' : 'NÃO'), 'ENVIAR_PEDIDO_DISTRIBUIDO_INDIVIDUAL');
                        }
                        
                        // Gerar comentários inteligentes
                        pedidos_debug_log("Gerando comentários com Gemini 2.5 Pro (quantidade: {$quantidade})", 'ENVIAR_PEDIDO_DISTRIBUIDO_INDIVIDUAL');
                        $generated_comments = $generator->generate_comments($post_info, $quantidade, $image_path);
                        
                        // Limpar arquivo temporário
                        if ($image_path) {
                            $scraper->cleanup_temp_files($image_path);
                        }
                        
                        if (!empty($generated_comments)) {
                            $order_data['comments'] = implode("\n", $generated_comments);
                            unset($order_data['quantity']); // Não enviar quantity para comentários
                            
                            pedidos_debug_log("Comentários gerados com sucesso (" . count($generated_comments) . " comentários)", 'ENVIAR_PEDIDO_DISTRIBUIDO_INDIVIDUAL');
                            pedidos_debug_log("Comentários: " . $order_data['comments'], 'ENVIAR_PEDIDO_DISTRIBUIDO_INDIVIDUAL');
                        } else {
                            throw new Exception('Nenhum comentário foi gerado');
                        }
                        
                    } else {
                        throw new Exception('Falha no scraping da publicação');
                    }
                    
                } catch (Exception $e) {
                    pedidos_error_log("ERRO na geração de comentários IA: " . $e->getMessage(), 'ENVIAR_PEDIDO_DISTRIBUIDO_INDIVIDUAL');
                    
                    // Fallback para comentários pré-definidos
                    pedidos_debug_log("Usando comentários de fallback", 'ENVIAR_PEDIDO_DISTRIBUIDO_INDIVIDUAL');
                    $fallback_comments = [
                        'Que foto incrível! 😍',
                        'Adorei esse post ✨',
                        'Perfeita como sempre!',
                        'Que estilo! 🔥'
                    ];
                    
                    $selected_comments = array_slice($fallback_comments, 0, min($quantidade, count($fallback_comments)));
                    $order_data['comments'] = implode("\n", $selected_comments);
                    unset($order_data['quantity']); // Não enviar quantity para comentários
                    
                    pedidos_debug_log("Comentários de fallback aplicados", 'ENVIAR_PEDIDO_DISTRIBUIDO_INDIVIDUAL');
                }
            }
            
            pedidos_data_log($order_data, 'ENVIAR_PEDIDO_DISTRIBUIDO_INDIVIDUAL - Dados do pedido');
            pedidos_debug_log("Enviando para API SMM - Link: {$link['url']} | Quantidade: {$quantidade} | Service: {$service_id}", 'ENVIAR_PEDIDO_DISTRIBUIDO_INDIVIDUAL');
            
            // Enviar para API (usar método apropriado baseado no tipo)
            if (isset($order_data['comments'])) {
                pedidos_debug_log("Enviando pedido com comentários usando orderWithComments", 'ENVIAR_PEDIDO_DISTRIBUIDO_INDIVIDUAL');
                $resultado = $smm_api->orderWithComments($order_data);
            } else {
                pedidos_debug_log("Enviando pedido padrão usando order", 'ENVIAR_PEDIDO_DISTRIBUIDO_INDIVIDUAL');
                $resultado = $smm_api->order($order_data);
            }
            
            if (isset($resultado->error)) {
                pedidos_error_log("Erro na API SMM para link {$link['id']}: " . $resultado->error, 'ENVIAR_PEDIDO_DISTRIBUIDO_INDIVIDUAL');
                return false;
            }
            
            // Salvar informações do pedido SMM
            $smm_order_id = $resultado->order ?? 'N/A';
            $charge = $resultado->charge ?? 0;
            
            pedidos_success_log("Pedido SMM criado para link {$link['id']}: #{$smm_order_id} (Custo: {$charge})", 'ENVIAR_PEDIDO_DISTRIBUIDO_INDIVIDUAL');
            
            // Salvar ID do pedido SMM no banco e atualizar status
            $this->salvar_id_pedido_smm($pedido->id, $smm_order_id);
            
            return true;
            
        } catch (Exception $e) {
            pedidos_error_log("Erro ao enviar pedido distribuído individual: " . $e->getMessage(), 'ENVIAR_PEDIDO_DISTRIBUIDO_INDIVIDUAL');
            return false;
        }
    }
    
    /**
     * Obter Service ID diretamente do produto via wp_postmeta
     */
    private function obter_service_id_do_pedido($pedido) {
        pedidos_step_log("Obtendo Service ID do produto #{$pedido->produto_id} para pedido #{$pedido->order_id}", 'OBTER_SERVICE_ID');
        
        $produto_id = $pedido->produto_id;
        $produto = wc_get_product($produto_id);
        
        if (!$produto) {
            pedidos_error_log("Produto #{$produto_id} não encontrado", 'OBTER_SERVICE_ID');
            return false;
        }
        
        // Usar o novo sistema de mapeamento se disponível
        if (class_exists('SMMModule')) {
            $service_id = SMMModule::get_product_service_id($produto_id);
            
            if (!empty($service_id) && is_numeric($service_id)) {
                pedidos_success_log("Service ID obtido via SMMModule para produto #{$produto_id}: {$service_id}", 'OBTER_SERVICE_ID');
                return intval($service_id);
            }
        }
        
        // Fallback: usar mapeamento manual se SMMModule não funcionar
        if ($produto->is_type('variation')) {
            pedidos_step_log("Produto é variação, aplicando mapeamento BR/Internacional", 'OBTER_SERVICE_ID');
            
            // Obter produto pai
            $parent_id = $produto->get_parent_id();
            $parent_product = wc_get_product($parent_id);
            
            if (!$parent_product) {
                pedidos_error_log("Produto pai #{$parent_id} não encontrado para variação #{$produto_id}", 'OBTER_SERVICE_ID');
                return false;
            }
            
            // Detectar tipo da variação (BR ou Internacional)
            $variation_type = $this->detect_variation_type($produto);
            pedidos_step_log("Tipo detectado para variação #{$produto_id}: {$variation_type}", 'OBTER_SERVICE_ID');
            
            // Obter Service ID baseado no tipo
            if ($variation_type === 'br') {
                $service_id = get_post_meta($parent_id, '_smm_service_id_br', true);
                if (empty($service_id)) {
                    $service_id = get_post_meta($parent_id, '_smm_service_id', true);
                    pedidos_warning_log("Service ID BR não configurado para produto #{$parent_id}, usando padrão: {$service_id}", 'OBTER_SERVICE_ID');
                } else {
                    pedidos_success_log("Service ID BR obtido para variação #{$produto_id}: {$service_id}", 'OBTER_SERVICE_ID');
                }
            } else {
                $service_id = get_post_meta($parent_id, '_smm_service_id_internacional', true);
                if (empty($service_id)) {
                    $service_id = get_post_meta($parent_id, '_smm_service_id', true);
                    pedidos_warning_log("Service ID Internacional não configurado para produto #{$parent_id}, usando padrão: {$service_id}", 'OBTER_SERVICE_ID');
                } else {
                    pedidos_success_log("Service ID Internacional obtido para variação #{$produto_id}: {$service_id}", 'OBTER_SERVICE_ID');
                }
            }
        } else {
            // Produto simples - usar Service ID padrão
            $service_id = get_post_meta($produto_id, '_smm_service_id', true);
            pedidos_step_log("Produto simples #{$produto_id}, usando Service ID padrão: {$service_id}", 'OBTER_SERVICE_ID');
        }
        
        // Fallback final: Service ID global
        if (empty($service_id) || !is_numeric($service_id)) {
            if (class_exists('SMMModule')) {
                $service_id = SMMModule::get_global_service_id();
                pedidos_warning_log("Service ID individual não encontrado para produto #{$produto_id}, usando Service ID global: {$service_id}", 'OBTER_SERVICE_ID');
            }
            
            if (empty($service_id) || !is_numeric($service_id)) {
                pedidos_error_log('Service ID não encontrado para produto #' . $produto_id . ' nem global. Verifique se foi configurado.', 'OBTER_SERVICE_ID');
                return false;
            }
        }
        
        return intval($service_id);
    }
    
    /**
     * Detectar tipo de variação (BR ou Internacional)
     * Baseado nos padrões: "Brasileiros" = BR, "Globais" = Internacional
     */
    private function detect_variation_type($variation) {
        if (!$variation || !is_object($variation)) {
            return 'internacional'; // Default
        }
        
        // 1. Verificar atributos da variação
        $attributes = $variation->get_attributes();
        
        foreach ($attributes as $key => $value) {
            $value_lower = strtolower(trim($value));
            
            // Padrões para Brasil (baseado na imagem: "Brasileiros")
            $br_patterns = ['br', 'brasil', 'brasileiro', 'brasileira', 'brasileiros', 'nacional', 'brazil'];
            if (in_array($value_lower, $br_patterns)) {
                return 'br';
            }
            
            // Padrões para Internacional (baseado na imagem: "Globais")
            $int_patterns = ['int', 'internacional', 'global', 'globais', 'worldwide', 'international', 'extern', 'externo'];
            if (in_array($value_lower, $int_patterns)) {
                return 'internacional';
            }
        }
        
        // 2. Verificar nome da variação
        $variation_name = strtolower($variation->get_name());
        
        // Padrões no nome para Brasil
        $br_name_patterns = ['br', 'brasil', 'brasileiro', 'brasileiros', 'nacional'];
        foreach ($br_name_patterns as $pattern) {
            if (strpos($variation_name, $pattern) !== false) {
                return 'br';
            }
        }
        
        // Padrões no nome para Internacional
        $int_name_patterns = ['int', 'internacional', 'global', 'globais', 'international'];
        foreach ($int_name_patterns as $pattern) {
            if (strpos($variation_name, $pattern) !== false) {
                return 'internacional';
            }
        }
        
        // 3. Verificar SKU da variação
        $sku = strtolower($variation->get_sku());
        if (!empty($sku)) {
            if (strpos($sku, 'br') !== false || strpos($sku, 'brasil') !== false) {
                return 'br';
            }
            
            if (strpos($sku, 'int') !== false || strpos($sku, 'global') !== false) {
                return 'internacional';
            }
        }
        
        // Default: Internacional
        return 'internacional';
    }
    
    /**
     * Salvar ID do pedido SMM no banco
     */
    private function salvar_id_pedido_smm($pedido_id, $smm_order_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pedidos_processados';
        
        $resultado = $wpdb->update(
            $table_name,
            [
                'order_id_api' => $smm_order_id,
                'status_api' => 'processing',
                'data_atualizacao' => current_time('mysql')
            ],
            ['id' => $pedido_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        if ($resultado !== false) {
            pedidos_api_smm_log("ID do pedido SMM salvo no banco: {$smm_order_id}", 'SALVAR_ID_SMM');
        } else {
            pedidos_api_smm_log("Erro ao salvar ID do pedido SMM no banco", 'SALVAR_ID_SMM');
        }
    }
    
    /**
     * Preparar dados do pedido processado para exibição
     */
    private function preparar_dados_pedido_processado($pedido_db) {
        return [
            'id' => $pedido_db->id,
            'order_id' => $pedido_db->order_id,
            'produto_id' => $pedido_db->produto_id,
            'produto_nome' => $pedido_db->produto_nome,
            'quantidade_variacao' => $pedido_db->quantidade_variacao,
            'instagram_username' => $pedido_db->instagram_username,
            'service_id_pedido' => $pedido_db->service_id_pedido,
            'cliente' => [
                'nome' => $pedido_db->cliente_nome,
                'email' => $pedido_db->cliente_email
            ],
            'valor_total' => $pedido_db->valor_total,
            'status_api' => $pedido_db->status_api,
            'order_id_api' => $pedido_db->order_id_api,
            'mensagem_api' => $pedido_db->mensagem_api,
            'tentativas' => $pedido_db->tentativas,
            'proxima_tentativa' => $pedido_db->proxima_tentativa,
            'data_processamento' => $pedido_db->data_processamento,
            'data_atualizacao' => $pedido_db->data_atualizacao
        ];
    }
    
    /**
     * Aplicar filtro de data para SQL
     */
    private function aplicar_filtro_data_sql($filtro_data) {
        $hoje = current_time('Y-m-d');
        
        switch ($filtro_data) {
            case 'hoje':
                return "DATE(data_processamento) = '$hoje'";
            case 'ontem':
                $ontem = date('Y-m-d', strtotime('-1 day'));
                return "DATE(data_processamento) = '$ontem'";
            case 'semana':
                $semana_atras = date('Y-m-d', strtotime('-7 days'));
                return "DATE(data_processamento) >= '$semana_atras'";
            case 'mes':
                $mes_atras = date('Y-m-d', strtotime('-30 days'));
                return "DATE(data_processamento) >= '$mes_atras'";
            default:
                return '';
        }
    }
    
    /**
     * Calcular estatísticas dos pedidos
     */
    private function calcular_estatisticas($pedidos) {
        $total_pedidos = count($pedidos);
        $pedidos_sucesso = 0;
        $pedidos_pendentes = 0;
        $total_valor = 0;
        
        foreach ($pedidos as $pedido) {
            if ($pedido['status_api'] === 'success') {
                $pedidos_sucesso++;
            } else {
                $pedidos_pendentes++;
            }
            $total_valor += $pedido['valor_total'];
        }
        
        return [
            'total_pedidos' => $total_pedidos,
            'pedidos_sucesso' => $pedidos_sucesso,
            'pedidos_pendentes' => $pedidos_pendentes,
            'total_valor' => $total_valor
        ];
    }
}

// Todos os arquivos de teste e debug foram removidos para limpeza do plugin

// Função para criar tabela na ativação
function criar_tabela_pedidos_processados_ativacao() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pedidos_processados';
    
    // Verificar se a tabela já existe
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
        // Tabela já existe, verificar se precisa de alterações
        $charset_collate = $wpdb->get_charset_collate();
        
        // Verificar se a chave UNIQUE já existe
        $result = $wpdb->get_results("SHOW INDEX FROM $table_name WHERE Key_name = 'order_id'");
        if (empty($result)) {
            // Adicionar chave UNIQUE se não existir
            $wpdb->query("ALTER TABLE $table_name ADD UNIQUE KEY `order_id` (`order_id`)");
        }
        return;
    }
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        order_id bigint(20) NOT NULL,
        produto_id bigint(20) NOT NULL,
        produto_nome varchar(255) NOT NULL,
        quantidade_variacao int(11) NOT NULL,
        instagram_username varchar(255) NOT NULL,
        cliente_nome varchar(255) NOT NULL,
        cliente_email varchar(255) NOT NULL,
        valor_total decimal(10,2) NOT NULL,
        status_api varchar(50) DEFAULT 'pending',
        order_id_api varchar(255) DEFAULT '',
        mensagem_api text,
        tentativas int(11) DEFAULT 0,
        proxima_tentativa datetime DEFAULT NULL,
        data_processamento datetime DEFAULT CURRENT_TIMESTAMP,
        data_atualizacao datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY order_id (order_id),
        KEY status_api (status_api),
        KEY data_processamento (data_processamento)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Função para limpar cron na desativação
function limpar_cron_pedidos_processados_desativacao() {
    wp_clear_scheduled_hook('processar_pedidos_pendentes');
}

// Carregar módulo SMM
if (file_exists(plugin_dir_path(__FILE__) . 'modules/smm/load-smm.php')) {
    require_once plugin_dir_path(__FILE__) . 'modules/smm/load-smm.php';
}


// Registrar hooks de ativação/desativação (deve vir depois da definição das funções)
register_activation_hook(__FILE__, 'criar_tabela_pedidos_processados_ativacao');
register_deactivation_hook(__FILE__, 'limpar_cron_pedidos_processados_desativacao');

// Inicializar o plugin
pedidos_step_log('Iniciando plugin Pedidos em Processamento', 'PLUGIN_INIT');

try {
    new PedidosProcessandoPlugin();
    pedidos_success_log('Plugin Pedidos em Processamento inicializado com sucesso', 'PLUGIN_INIT');
} catch (Exception $e) {
    pedidos_error_log('Erro ao inicializar plugin: ' . $e->getMessage(), 'PLUGIN_INIT');
}



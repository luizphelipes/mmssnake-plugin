/**
 * Pedidos em Processamento - Admin Script
 * Funcionalidades interativas para a interface administrativa
 */

jQuery(document).ready(function($) {
    
    // Variáveis globais
    let pedidosData = [];
    let filtrosAtivos = {
        produto: '',
        data: '',
        busca: ''
    };
    
    // Inicialização
    init();
    
    function init() {
        carregarPedidos();
        bindEvents();
        carregarFiltros();
    }
    
    /**
     * Vincular eventos
     */
    function bindEvents() {
        // Botão atualizar lista
        $('#atualizar-lista').on('click', function() {
            carregarPedidos();
        });
        
        // Botão exportar CSV
        $('#exportar-csv').on('click', function() {
            exportarCSV();
        });
        
        // Botão limpar pedidos
        $('#limpar-pedidos').on('click', function() {
            limparPedidos();
        });
        
        // Filtros
        $('#filter-produto').on('change', function() {
            filtrosAtivos.produto = $(this).val();
            aplicarFiltros();
        });
        
        $('#filter-data').on('change', function() {
            filtrosAtivos.data = $(this).val();
            aplicarFiltros();
        });
        
        // Busca com debounce
        let searchTimeout;
        $('#search-pedido').on('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                filtrosAtivos.busca = $('#search-pedido').val();
                aplicarFiltros();
            }, 500);
        });
        
        // Fechar modal
        $(document).on('click', '.modal-close, .pedido-modal', function(e) {
            if (e.target === this) {
                fecharModal();
            }
        });
        
        // Clique em pedido para abrir modal
        $(document).on('click', '.pedido-item', function() {
            const pedidoId = $(this).data('pedido-id');
            abrirModalPedido(pedidoId);
        });
        
        // Atualizar status do pedido
        $(document).on('click', '.atualizar-status', function(e) {
            e.stopPropagation();
            const pedidoId = $(this).data('pedido-id');
            const novoStatus = $(this).data('status');
            atualizarStatusPedido(pedidoId, novoStatus);
        });
        
        // Ver pedido no WooCommerce
        $(document).on('click', '.ver-pedido', function(e) {
            e.stopPropagation();
            const pedidoId = $(this).data('pedido-id');
            window.open(ajaxurl + '?action=woocommerce_mark_order_status&status=processing&order_id=' + pedidoId, '_blank');
        });
    }
    
    /**
     * Carregar pedidos via AJAX
     */
    function carregarPedidos() {
        mostrarLoading();
        
        $.ajax({
            url: pedidos_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'buscar_pedidos_processados',
                nonce: pedidos_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    pedidosData = response.data.pedidos;
                    atualizarEstatisticas(response.data.estatisticas);
                    renderizarPedidosProcessados(pedidosData);
                    mostrarLista();
                } else {
                    mostrarErro('Erro ao carregar pedidos: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                mostrarErro('Erro na conexão: ' + error);
            }
        });
    }
    
    /**
     * Carregar filtros disponíveis
     */
    function carregarFiltros() {
        // Carregar produtos para filtro
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'woocommerce_json_search_products',
                security: pedidos_ajax.nonce
            },
            success: function(response) {
                if (response) {
                    const $select = $('#filter-produto');
                    $select.find('option:not(:first)').remove();
                    
                    $.each(response, function(id, nome) {
                        $select.append(`<option value="${id}">${nome}</option>`);
                    });
                }
            }
        });
    }
    
    /**
     * Aplicar filtros ativos
     */
    function aplicarFiltros() {
        let pedidosFiltrados = pedidosData;
        
        // Filtro por produto
        if (filtrosAtivos.produto) {
            pedidosFiltrados = pedidosFiltrados.filter(pedido => {
                return pedido.itens.some(item => item.id == filtrosAtivos.produto);
            });
        }
        
        // Filtro por data
        if (filtrosAtivos.data) {
            pedidosFiltrados = pedidosFiltrados.filter(pedido => {
                const dataPedido = new Date(pedido.data.split(' ')[0].split('/').reverse().join('-'));
                const hoje = new Date();
                
                switch (filtrosAtivos.data) {
                    case 'hoje':
                        return dataPedido.toDateString() === hoje.toDateString();
                    case 'ontem':
                        const ontem = new Date(hoje);
                        ontem.setDate(hoje.getDate() - 1);
                        return dataPedido.toDateString() === ontem.toDateString();
                    case 'semana':
                        const semanaAtras = new Date(hoje);
                        semanaAtras.setDate(hoje.getDate() - 7);
                        return dataPedido >= semanaAtras;
                    case 'mes':
                        const mesAtras = new Date(hoje);
                        mesAtras.setMonth(hoje.getMonth() - 1);
                        return dataPedido >= mesAtras;
                }
                return true;
            });
        }
        
        // Filtro por busca
        if (filtrosAtivos.busca) {
            const busca = filtrosAtivos.busca.toLowerCase();
            pedidosFiltrados = pedidosFiltrados.filter(pedido => {
                return (
                    pedido.id.toString().includes(busca) ||
                    pedido.instagram_username.toLowerCase().includes(busca) ||
                    pedido.cliente.nome.toLowerCase().includes(busca) ||
                    pedido.cliente.email.toLowerCase().includes(busca)
                );
            });
        }
        
        renderizarPedidos(pedidosFiltrados);
        atualizarEstatisticas(calcularEstatisticas(pedidosFiltrados));
    }
    
    /**
     * Renderizar lista de pedidos
     */
    function renderizarPedidos(pedidos) {
        const $lista = $('#pedidos-list');
        
        if (pedidos.length === 0) {
            $lista.html(`
                <div class="pedidos-empty">
                    <div class="empty-state">
                        <span class="dashicons dashicons-cart"></span>
                        <h3>Nenhum Pedido Encontrado</h3>
                        <p>Não há pedidos que correspondam aos filtros aplicados.</p>
                    </div>
                </div>
            `);
            return;
        }
        
        let html = '';
        
        pedidos.forEach(function(pedido) {
            const classeEspecial = getClasseEspecial(pedido);
            const produtosHtml = renderizarProdutos(pedido.itens);
            
            html += `
                <div class="pedido-item ${classeEspecial}" data-pedido-id="${pedido.id}">
                    <div class="pedido-header">
                        <div class="pedido-id">#${pedido.numero}</div>
                        <div class="pedido-data">${pedido.data}</div>
                        <span class="pedido-status">${pedido.status}</span>
                    </div>
                    
                    <div class="pedido-cliente">
                        <div class="cliente-nome">${pedido.cliente.nome}</div>
                        <div class="cliente-info">📧 ${pedido.cliente.email}</div>
                        ${pedido.cliente.telefone ? `<div class="cliente-info">📞 ${pedido.cliente.telefone}</div>` : ''}
                        ${pedido.instagram_username ? `<div class="instagram-username">📸 @${pedido.instagram_username}</div>` : ''}
                    </div>
                    
                    <div class="pedido-produtos">
                        ${produtosHtml}
                    </div>
                    
                    <div class="pedido-footer">
                        <div class="pedido-total">
                            Total: R$ ${formatarMoeda(pedido.total)}
                        </div>
                        <div class="pedido-acoes">
                            <button type="button" class="button button-secondary ver-pedido" data-pedido-id="${pedido.id}">
                                <span class="dashicons dashicons-external"></span>
                                Ver Pedido
                            </button>
                            <button type="button" class="button button-primary atualizar-status" data-pedido-id="${pedido.id}" data-status="completed">
                                <span class="dashicons dashicons-yes"></span>
                                Marcar Concluído
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
        
        $lista.html(html);
    }
    
    /**
     * Renderizar produtos do pedido
     */
    function renderizarProdutos(itens) {
        let html = '';
        
        itens.forEach(function(item) {
            html += `
                <div class="produto-item">
                    <div class="produto-info">
                        <div class="produto-nome">${item.nome}</div>
                        <div class="produto-detalhes">
                            R$ ${formatarMoeda(item.preco_unitario)} cada
                        </div>
                    </div>
                    <span class="produto-quantidade">${item.quantidade}</span>
                </div>
            `;
        });
        
        return html;
    }
    
    /**
     * Renderizar pedidos processados do plugin
     */
    function renderizarPedidosProcessados(pedidos) {
        const $lista = $('#pedidos-list');
        
        if (pedidos.length === 0) {
            $lista.html(`
                <div class="pedidos-empty">
                    <div class="empty-state">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <h3>Nenhum Pedido Processado</h3>
                        <p>Não há pedidos processados no momento.</p>
                    </div>
                </div>
            `);
            return;
        }
        
        let html = '';
        
        pedidos.forEach(function(pedido) {
            const statusClass = getStatusClass(pedido.status_api);
            const statusText = getStatusText(pedido.status_api);
            
            html += `
                <div class="pedido-item ${statusClass}" data-pedido-id="${pedido.id}">
                    <div class="pedido-header">
                        <div class="pedido-id">#${pedido.order_id}</div>
                        <div class="pedido-data">${formatarData(pedido.data_processamento)}</div>
                        <span class="pedido-status ${statusClass}">${statusText}</span>
                    </div>
                    
                    <div class="pedido-info">
                        <div class="produto-info">
                            <strong>${pedido.produto_nome}</strong>
                            <div class="produto-detalhes">
                                Qtd: ${pedido.quantidade_variacao} | 
                                Service ID: <span class="service-id">${pedido.service_id_pedido || 'N/A'}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="pedido-cliente">
                        <div class="cliente-nome">${pedido.cliente.nome}</div>
                        <div class="cliente-info">📧 ${pedido.cliente.email}</div>
                        ${pedido.instagram_username ? `<div class="instagram-username">📸 @${pedido.instagram_username}</div>` : ''}
                    </div>
                    
                    <div class="pedido-footer">
                        <div class="pedido-total">
                            Total: R$ ${formatarMoeda(pedido.valor_total)}
                        </div>
                        <div class="pedido-acoes">
                            <button type="button" class="button button-secondary ver-pedido" data-pedido-id="${pedido.order_id}">
                                <span class="dashicons dashicons-external"></span>
                                Ver Pedido WC
                            </button>
                            ${pedido.order_id_api ? `
                                <button type="button" class="button button-info" disabled>
                                    <span class="dashicons dashicons-share"></span>
                                    API ID: ${pedido.order_id_api}
                                </button>
                            ` : ''}
                        </div>
                    </div>
                    
                    ${pedido.mensagem_api ? `
                        <div class="pedido-mensagem">
                            <strong>Mensagem API:</strong> ${pedido.mensagem_api}
                        </div>
                    ` : ''}
                </div>
            `;
        });
        
        $lista.html(html);
    }
    
    /**
     * Obter classe CSS para o status
     */
    function getStatusClass(status) {
        switch (status) {
            case 'success':
                return 'status-success';
            case 'processing':
                return 'status-processing';
            case 'error':
                return 'status-error';
            case 'pending':
            default:
                return 'status-pending';
        }
    }
    
    /**
     * Obter texto do status
     */
    function getStatusText(status) {
        switch (status) {
            case 'success':
                return '✅ Sucesso';
            case 'processing':
                return '🔄 Processando';
            case 'error':
                return '❌ Erro';
            case 'pending':
            default:
                return '⏳ Pendente';
        }
    }
    
    /**
     * Formatar data
     */
    function formatarData(dataString) {
        if (!dataString) return 'N/A';
        const data = new Date(dataString);
        return data.toLocaleDateString('pt-BR') + ' ' + data.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
    }
    
    /**
     * Determinar classe especial para o pedido
     */
    function getClasseEspecial(pedido) {
        const dataPedido = new Date(pedido.data.split(' ')[0].split('/').reverse().join('-'));
        const hoje = new Date();
        const diffTime = Math.abs(hoje - dataPedido);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays <= 1) {
            return 'novo';
        } else if (diffDays >= 3) {
            return 'urgente';
        }
        
        return '';
    }
    
    /**
     * Atualizar estatísticas
     */
    function atualizarEstatisticas(estatisticas) {
        $('#total-pedidos').text(estatisticas.total_pedidos);
        $('#total-produtos').text(estatisticas.total_produtos);
        $('#total-valor').text('R$ ' + formatarMoeda(estatisticas.total_valor));
    }
    
    /**
     * Calcular estatísticas dos pedidos filtrados
     */
    function calcularEstatisticas(pedidos) {
        const total_pedidos = pedidos.length;
        let total_produtos = 0;
        let total_valor = 0;
        
        pedidos.forEach(function(pedido) {
            total_produtos += pedido.total_produtos;
            total_valor += pedido.total;
        });
        
        return {
            total_pedidos: total_pedidos,
            total_produtos: total_produtos,
            total_valor: total_valor
        };
    }
    
    /**
     * Atualizar status do pedido
     */
    function atualizarStatusPedido(pedidoId, novoStatus) {
        if (!confirm(pedidos_ajax.strings.confirmar_atualizacao)) {
            return;
        }
        
        $.ajax({
            url: pedidos_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'atualizar_status_pedido',
                pedido_id: pedidoId,
                novo_status: novoStatus,
                nonce: pedidos_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Remover pedido da lista
                    $(`.pedido-item[data-pedido-id="${pedidoId}"]`).fadeOut(300, function() {
                        $(this).remove();
                        // Recarregar estatísticas
                        const pedidosRestantes = pedidosData.filter(p => p.id != pedidoId);
                        pedidosData = pedidosRestantes;
                        atualizarEstatisticas(calcularEstatisticas(pedidosRestantes));
                        
                        // Verificar se não há mais pedidos
                        if (pedidosRestantes.length === 0) {
                            mostrarVazio();
                        }
                    });
                    
                    // Mostrar notificação de sucesso
                    mostrarNotificacao(pedidos_ajax.strings.sucesso_atualizacao, 'success');
                } else {
                    mostrarNotificacao(pedidos_ajax.strings.erro_atualizacao + ': ' + response.data, 'error');
                }
            },
            error: function() {
                mostrarNotificacao(pedidos_ajax.strings.erro_atualizacao, 'error');
            }
        });
    }
    
    /**
     * Abrir modal com detalhes do pedido
     */
    function abrirModalPedido(pidoId) {
        const pedido = pedidosData.find(p => p.id == pidoId);
        if (!pedido) return;
        
        const modalHtml = `
            <div class="modal-detalhes-pedido">
                <div class="detalhes-header">
                    <h3>Pedido #${pedido.numero}</h3>
                    <p><strong>Data:</strong> ${pedido.data}</p>
                    <p><strong>Status:</strong> <span class="pedido-status">${pedido.status}</span></p>
                </div>
                
                <div class="detalhes-cliente">
                    <h4>Informações do Cliente</h4>
                    <p><strong>Nome:</strong> ${pedido.cliente.nome}</p>
                    <p><strong>Email:</strong> ${pedido.cliente.email}</p>
                    ${pedido.cliente.telefone ? `<p><strong>Telefone:</strong> ${pedido.cliente.telefone}</p>` : ''}
                    ${pedido.instagram_username ? `<p><strong>Instagram:</strong> @${pedido.instagram_username}</p>` : ''}
                </div>
                
                <div class="detalhes-produtos">
                    <h4>Produtos</h4>
                    ${pedido.itens.map(item => `
                        <div class="produto-detalhe">
                            <span class="produto-nome">${item.nome}</span>
                            <span class="produto-qtd">Qtd: ${item.quantidade}</span>
                            <span class="produto-preco">R$ ${formatarMoeda(item.preco_total)}</span>
                        </div>
                    `).join('')}
                </div>
                
                <div class="detalhes-total">
                    <h4>Resumo</h4>
                    <p><strong>Subtotal:</strong> R$ ${formatarMoeda(pedido.subtotal)}</p>
                    <p><strong>Total:</strong> R$ ${formatarMoeda(pedido.total)}</p>
                    <p><strong>Método de Pagamento:</strong> ${pedido.metodo_pagamento}</p>
                </div>
                
                ${pedido.notas ? `
                    <div class="detalhes-notas">
                        <h4>Observações</h4>
                        <p>${pedido.notas}</p>
                    </div>
                ` : ''}
            </div>
        `;
        
        $('#pedido-modal .modal-body').html(modalHtml);
        $('#pedido-modal').show();
    }
    
    /**
     * Fechar modal
     */
    function fecharModal() {
        $('#pedido-modal').hide();
    }
    
    /**
     * Limpar todos os pedidos e recarregar do WooCommerce
     */
    function limparPedidos() {
        // Confirmar ação
        if (!confirm(pedidos_ajax.strings.confirmar_limpeza)) {
            return;
        }
        
        // Desabilitar botão e mostrar loading
        const botao = $('#limpar-pedidos');
        const textoOriginal = botao.html();
        
        botao.prop('disabled', true);
        botao.html('<span class="dashicons dashicons-update-alt"></span> ' + pedidos_ajax.strings.limpeza_em_andamento);
        
        // Fazer requisição AJAX
        $.ajax({
            url: pedidos_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'limpar_pedidos',
                nonce: pedidos_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Mostrar mensagem de sucesso
                    mostrarNotificacao(response.data.message, 'success');
                    
                    // Mostrar detalhes da limpeza
                    const detalhes = `Pedidos removidos: ${response.data.pedidos_removidos}\nPedidos recarregados: ${response.data.pedidos_recarregados}`;
                    alert('Limpeza e recarregamento concluídos com sucesso!\n\n' + detalhes);
                    
                    // Recarregar lista de pedidos
                    carregarPedidos();
                } else {
                    mostrarNotificacao(response.data || pedidos_ajax.strings.limpeza_erro, 'error');
                }
            },
            error: function() {
                mostrarNotificacao(pedidos_ajax.strings.limpeza_erro, 'error');
            },
            complete: function() {
                // Restaurar botão
                botao.prop('disabled', false);
                botao.html(textoOriginal);
            }
        });
    }
    
    /**
     * Exportar dados para CSV
     */
    function exportarCSV() {
        if (pedidosData.length === 0) {
            mostrarNotificacao('Nenhum pedido para exportar', 'warning');
            return;
        }
        
        let csvContent = 'ID,Número,Data,Cliente,Email,Instagram,Produtos,Quantidade Total,Total\n';
        
        pedidosData.forEach(function(pedido) {
            const produtos = pedido.itens.map(item => item.nome).join('; ');
            const linha = [
                pedido.id,
                pedido.numero,
                pedido.data,
                `"${pedido.cliente.nome}"`,
                pedido.cliente.email,
                pedido.instagram_username || '',
                `"${produtos}"`,
                pedido.total_produtos,
                pedido.total
            ].join(',');
            
            csvContent += linha + '\n';
        });
        
        // Criar e baixar arquivo
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', `pedidos-processando-${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    
    /**
     * Mostrar estado de loading
     */
    function mostrarLoading() {
        $('#pedidos-loading').show();
        $('#pedidos-list').hide();
        $('#pedidos-empty').hide();
    }
    
    /**
     * Mostrar lista de pedidos
     */
    function mostrarLista() {
        $('#pedidos-loading').hide();
        $('#pedidos-list').show();
        $('#pedidos-empty').hide();
    }
    
    /**
     * Mostrar estado vazio
     */
    function mostrarVazio() {
        $('#pedidos-loading').hide();
        $('#pedidos-list').hide();
        $('#pedidos-empty').show();
    }
    
    /**
     * Mostrar erro
     */
    function mostrarErro(mensagem) {
        $('#pedidos-loading').hide();
        $('#pedidos-list').html(`
            <div class="pedidos-empty">
                <div class="empty-state">
                    <span class="dashicons dashicons-warning" style="color: #dc3545;"></span>
                    <h3>Erro ao Carregar</h3>
                    <p>${mensagem}</p>
                </div>
            </div>
        `);
        $('#pedidos-list').show();
        $('#pedidos-empty').hide();
    }
    
    /**
     * Mostrar notificação
     */
    function mostrarNotificacao(mensagem, tipo = 'info') {
        const notificacao = $(`
            <div class="notice notice-${tipo} is-dismissible" style="position: fixed; top: 20px; right: 20px; z-index: 100001; max-width: 400px;">
                <p>${mensagem}</p>
            </div>
        `);
        
        $('body').append(notificacao);
        
        // Auto-remover após 5 segundos
        setTimeout(function() {
            notificacao.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        // Botão fechar
        notificacao.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');
        notificacao.find('.notice-dismiss').on('click', function() {
            notificacao.fadeOut(300, function() {
                $(this).remove();
            });
        });
    }
    
    /**
     * Formatar moeda
     */
    function formatarMoeda(valor) {
        return parseFloat(valor).toFixed(2).replace('.', ',');
    }
    
    // Auto-atualização a cada 5 minutos
    setInterval(function() {
        if ($('#pedidos-list').is(':visible')) {
            carregarPedidos();
        }
    }, 300000); // 5 minutos
    
});


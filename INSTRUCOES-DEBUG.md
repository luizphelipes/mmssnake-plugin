# 🔍 Sistema de Debug - Plugin Pedidos em Processamento

Este arquivo explica como usar o sistema de debug implementado no plugin para identificar e resolver problemas.

## ✨ Funcionalidades do Sistema de Debug

### 📝 **Logs Detalhados**
- **Log de cada passo** do funcionamento do plugin
- **Log de dados** recebidos e processados
- **Log de erros** com contexto detalhado
- **Log de sucessos** para confirmar funcionamento
- **Log de warnings** para situações que merecem atenção

### 📍 **Contextos de Log**
- `PLUGIN_INIT` - Inicialização do plugin
- `WOOCOMMERCE_CHECK` - Verificação do WooCommerce
- `PLUGIN_CONSTRUCTOR` - Construtor da classe principal
- `HOOKS_REGISTRATION` - Registro de hooks
- `ADMIN_MENU` - Criação do menu administrativo
- `ADMIN_SCRIPTS` - Carregamento de scripts e estilos
- `ADMIN_PAGE_RENDER` - Renderização da página admin
- `AJAX_BUSCAR_PEDIDOS` - Busca de pedidos via AJAX
- `BUSCAR_PEDIDOS` - Busca de pedidos no banco
- `PROCESSAMENTO_AUTOMATICO` - Processamento automático
- `VERIFICAR_PEDIDO_PROCESSADO` - Verificação de pedidos
- `ENVIAR_PEDIDO_API` - Envio para API
- `TENTAR_PROCESSAR_PEDIDO` - Tentativa de processamento
- `MARCAR_PEDIDO_ERRO` - Marcação de erros
- `ENVIAR_API_SMM` - Envio para API SMM
- `DETERMINAR_SERVICE_ID` - Determinação do Service ID
- `CRON_CONFIG` - Configuração de cron
- `AGENDAR_PROCESSAMENTO` - Agendamento de processamento
- `PROCESSAR_PEDIDOS_PENDENTES` - Processamento de pendentes

## 🚀 Como Ativar o Debug

### **1. Configuração**
No arquivo `pedidos-processando.php`, linha 25:

```php
// Ativar/desativar debug (mude para true para ativar)
define('PEDIDOS_DEBUG', true);
```

### **2. Para Desativar**
```php
define('PEDIDOS_DEBUG', false);
```

## 📊 Onde Encontrar os Logs

### **1. Arquivo de Log**
- **Localização**: `/wp-content/debug-pedidos-plugin.log`
- **Formato**: Log estruturado com timestamp e contexto
- **Exemplo**:
```
[2024-01-15 10:30:15] [STEP] [PLUGIN_INIT]: PASSO: Iniciando plugin Pedidos em Processamento
[2024-01-15 10:30:15] [SUCCESS] [PLUGIN_INIT]: Plugin Pedidos em Processamento inicializado com sucesso
```

### **2. Log do WordPress**
- **Localização**: Log de erros do WordPress (geralmente em `/wp-content/debug.log`)
- **Formato**: Prefixado com `PEDIDOS_DEBUG:`
- **Exemplo**:
```
PEDIDOS_DEBUG: [2024-01-15 10:30:15] [STEP] [PLUGIN_INIT]: PASSO: Iniciando plugin Pedidos em Processamento
```

### **3. Console do Navegador**
- **Localização**: Ferramentas do desenvolvedor (F12) > Console
- **Formato**: Log JavaScript com prefixo `PEDIDOS_DEBUG:`
- **Exemplo**:
```
PEDIDOS_DEBUG: Iniciando plugin Pedidos em Processamento
```

## 🔧 Funções de Debug Disponíveis

### **Log de Passo**
```php
pedidos_step_log('Descrição do passo', 'CONTEXTO');
```

### **Log de Sucesso**
```php
pedidos_success_log('Operação realizada com sucesso', 'CONTEXTO');
```

### **Log de Erro**
```php
pedidos_error_log('Descrição do erro', 'CONTEXTO');
```

### **Log de Warning**
```php
pedidos_warning_log('Atenção necessária', 'CONTEXTO');
```

### **Log de Dados**
```php
pedidos_data_log($array_ou_objeto, 'CONTEXTO');
```

## 📋 Exemplo de Uso

### **1. Debug de Inicialização**
```php
pedidos_step_log('Iniciando construtor da classe', 'CONSTRUCTOR');
try {
    // código aqui
    pedidos_success_log('Construtor executado com sucesso', 'CONSTRUCTOR');
} catch (Exception $e) {
    pedidos_error_log('Erro no construtor: ' . $e->getMessage(), 'CONSTRUCTOR');
}
```

### **2. Debug de Dados**
```php
pedidos_data_log($filtros, 'FILTROS_RECEBIDOS');
pedidos_data_log($resultado_query, 'RESULTADO_QUERY');
```

### **3. Debug de Processamento**
```php
pedidos_step_log("Processando pedido #{$order_id}", 'PROCESSAMENTO');
pedidos_success_log("Pedido #{$order_id} processado", 'PROCESSAMENTO');
```

## 🎯 Como Usar para Resolver Problemas

### **1. Identificar o Problema**
- Ative o debug (`PEDIDOS_DEBUG = true`)
- Execute a funcionalidade que não está funcionando
- Verifique os logs para identificar onde para

### **2. Analisar os Logs**
- **Logs de STEP**: Mostram o fluxo de execução
- **Logs de SUCCESS**: Confirmam que algo funcionou
- **Logs de ERROR**: Mostram onde está o problema
- **Logs de DATA**: Mostram os dados sendo processados

### **3. Exemplo de Análise**
```
[10:30:15] [STEP] [AJAX_BUSCAR_PEDIDOS]: PASSO: Iniciando AJAX: buscar pedidos processados
[10:30:15] [DATA] [AJAX_BUSCAR_PEDIDOS - Filtros recebidos]: Array ( [produto] => [data] => [busca] => )
[10:30:15] [STEP] [BUSCAR_PEDIDOS]: PASSO: Iniciando busca de pedidos processados
[10:30:15] [STEP] [BUSCAR_PEDIDOS]: PASSO: Verificando existência da tabela: wp_pedidos_processados
[10:30:15] [WARNING] [BUSCAR_PEDIDOS]: Tabela wp_pedidos_processados não existe
```

**Problema identificado**: A tabela não existe!

## 🚨 Problemas Comuns e Como Identificar

### **1. Tabela Não Existe**
```
[WARNING] [BUSCAR_PEDIDOS]: Tabela wp_pedidos_processados não existe
```
**Solução**: Ativar o plugin para criar a tabela

### **2. WooCommerce Não Ativo**
```
[ERROR] [WOOCOMMERCE_CHECK]: WooCommerce não está ativo
```
**Solução**: Ativar o plugin WooCommerce

### **3. Módulo SMM Não Disponível**
```
[ERROR] [ENVIAR_API_SMM]: Módulo SMM não disponível para pedido 123
```
**Solução**: Verificar se o módulo SMM está carregado

### **4. Provedor SMM Não Configurado**
```
[ERROR] [ENVIAR_API_SMM]: Nenhum provedor SMM configurado para pedido 123
```
**Solução**: Configurar provedores SMM

### **5. Service ID Não Encontrado**
```
[WARNING] [DETERMINAR_SERVICE_ID]: Service ID não configurado para produto 456
```
**Solução**: Configurar Service ID nos produtos

## 📱 Interface de Debug

### **1. Logs em Tempo Real**
- Os logs aparecem no console do navegador em tempo real
- Útil para debug durante o desenvolvimento

### **2. Logs Persistentes**
- Arquivo de log mantém histórico completo
- Útil para análise posterior e troubleshooting

### **3. Contextos Organizados**
- Cada log tem contexto específico
- Facilita a busca e análise de problemas

## 🔒 Segurança

### **1. Em Produção**
- **SEMPRE** desative o debug: `define('PEDIDOS_DEBUG', false);`
- Os logs podem conter informações sensíveis
- Impacta a performance do plugin

### **2. Em Desenvolvimento**
- Ative o debug para identificar problemas
- Monitore os logs regularmente
- Limpe os logs antigos quando necessário

## 📞 Suporte

Se você encontrar problemas que não consegue resolver com o sistema de debug:

1. **Colete os logs** relevantes ao problema
2. **Identifique o contexto** onde o problema ocorre
3. **Descreva o comportamento esperado** vs. o que está acontecendo
4. **Inclua informações do ambiente** (WordPress, WooCommerce, PHP)

O sistema de debug fornece todas as informações necessárias para identificar e resolver problemas rapidamente!

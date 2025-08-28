# 📡 Módulo SMM - Sistema de Provedores SMM

Módulo para integração com provedores de serviços de mídia social (SMM) no plugin de Pedidos em Processamento.

## ✨ Funcionalidades

### 🔧 **Configuração de Provedores**
- **Adicionar Provedores**: Configure múltiplos provedores SMM
- **URL da API**: Endpoint personalizado para cada provedor
- **API Key**: Chave de autenticação segura
- **Teste de Conexão**: Verifique se o provedor está funcionando
- **Verificação de Saldo**: Monitore o saldo de cada provedor

### 📦 **Configuração de Produtos**
- **Ativação SMM**: Ative/desative o envio automático por produto
- **Seleção de Provedor**: Escolha qual provedor usar para cada produto
- **Service ID**: Configure o ID do serviço específico no provedor

- **Busca de Serviços**: Liste todos os serviços disponíveis no provedor

### 📊 **Integração com Pedidos**
- **Meta Box SMM**: Campo personalizado na página de produtos
- **Coluna SMM**: Status SMM na lista de pedidos do WooCommerce
- **Filtros SMM**: Filtre pedidos por status SMM
- **Dashboard Widget**: Visão geral dos provedores SMM

## 🚀 Instalação

### 1. **Estrutura de Arquivos**
```
modules/smm/
├── smm-api.php              # Classe da API SMM
├── providers-manager.php     # Gerenciador de provedores
├── smm-module.php           # Módulo principal SMM
├── load-smm.php             # Carregador do módulo
└── README-SMM.md            # Esta documentação
```

### 2. **Carregamento Automático**
O módulo é carregado automaticamente pelo plugin principal. Não é necessário fazer configurações adicionais.

## ⚙️ Configuração

### **1. Configurar Provedores SMM**

1. Acesse **Pedidos Processando > Configurações SMM**
2. Clique em **"Adicionar Novo Provedor"**
3. Preencha:
   - **Nome do Provedor**: Nome identificador (ex: "MachineSMM")
   - **URL da API**: Endpoint da API (ex: https://machinesmm.com/api/v2)
   - **API Key**: Chave de autenticação fornecida pelo provedor
4. Clique em **"Adicionar Provedor"**
5. Teste a conexão clicando em **"Testar"**

### **2. Configurar Produtos**

1. Edite um produto no WooCommerce
2. Role até a seção **"Configurações SMM"** (lado direito)
3. Marque **"Ativar envio automático SMM"**
4. Selecione o **Provedor SMM**
5. Digite o **Service ID** ou clique em **"Buscar Serviços"**

7. Salve o produto

## 🔌 Compatibilidade de Provedores

### **Provedores Testados**
- ✅ **MachineSMM** (machinesmm.com)
- ✅ **SMMPanel** (smmpanel.com)
- ✅ **CheapPanel** (cheappanel.com)
- ✅ **SMMKings** (smmkings.com)

### **Padrão da API**
O módulo é compatível com a maioria dos provedores SMM que seguem o padrão:
- Endpoint: `/api/v2`
- Método: POST
- Parâmetros: `key`, `action`, `service`, `link`, `quantity`
- Resposta: JSON

## 📋 Funcionalidades da API

### **Operações Suportadas**
- ✅ **Adicionar Pedido**: `add` - Criar novo pedido
- ✅ **Status do Pedido**: `status` - Verificar status
- ✅ **Status Múltiplo**: `status` - Status de vários pedidos
- ✅ **Listar Serviços**: `services` - Serviços disponíveis
- ✅ **Refill**: `refill` - Reabastecer pedido
- ✅ **Cancelar**: `cancel` - Cancelar pedidos
- ✅ **Ver Saldo**: `balance` - Saldo da conta

### **Parâmetros de Pedido**
```php
$order_data = [
    'service' => 1,           // ID do serviço
    'link' => 'http://...',   // Link do post/perfil
    'quantity' => 100,        // Quantidade
    'runs' => 2,              // Número de execuções
    'interval' => 5,          // Intervalo entre execuções
    'comments' => '...',      // Comentários personalizados
    'usernames' => '...',     // Usernames para menções
    'hashtags' => '...',      // Hashtags
    'username' => '...',      // Username específico
    'min' => 100,             // Mínimo para assinaturas
    'max' => 110,             // Máximo para assinaturas
    'posts' => 0,             // Posts para assinaturas
    'delay' => 30,            // Delay em minutos
    'expiry' => '11/11/2022'  // Data de expiração
];
```

## 🎯 Casos de Uso

### **Para Lojistas**
- **Automatização**: Envio automático de pedidos SMM
- **Múltiplos Provedores**: Distribua pedidos entre provedores
- **Controle de Qualidade**: Teste provedores antes de usar
- **Monitoramento**: Acompanhe saldo e status dos pedidos

### **Para Atendimento**
- **Rastreamento**: Status SMM nos pedidos do WooCommerce
- **Filtros**: Organize pedidos por status SMM
- **Histórico**: Acompanhe todos os pedidos SMM enviados

### **Para Gerentes**
- **Dashboard**: Visão geral dos provedores SMM
- **Relatórios**: Estatísticas de uso dos provedores
- **Configuração**: Gerencie todos os provedores centralmente

## 🔒 Segurança

### **Validações Implementadas**
- ✅ **Verificação de Nonce**: Todas as requisições AJAX
- ✅ **Verificação de Permissões**: Apenas usuários autorizados
- ✅ **Sanitização de Dados**: Todos os inputs são sanitizados
- ✅ **Validação de URL**: Verificação de URLs válidas
- ✅ **Teste de Conexão**: Validação antes de salvar provedor

### **Permissões Requeridas**
- **Visualizar**: `manage_woocommerce`
- **Configurar**: `manage_woocommerce`
- **Testar**: `manage_woocommerce`
- **Gerenciar**: `manage_woocommerce`

## 🐛 Solução de Problemas

### **Provedor não conecta**
1. Verifique se a URL da API está correta
2. Confirme se a API Key é válida
3. Teste a conexão clicando em "Testar"
4. Verifique se o provedor está online

### **Service ID não encontrado**
1. Clique em "Buscar Serviços" para listar todos
2. Verifique se o ID está correto
3. Confirme se o serviço está ativo no provedor

### **Erro de permissão**
1. Verifique se o usuário tem permissão `manage_woocommerce`
2. Confirme se o plugin WooCommerce está ativo
3. Verifique se não há conflitos com outros plugins

### **Pedido não é enviado**
1. Verifique se o produto tem SMM ativado
2. Confirme se o provedor está configurado
3. Verifique se o Service ID está correto
4. Monitore os logs de erro do WordPress

## 🔄 Atualizações

### **Auto-atualização**
- O módulo verifica automaticamente a conexão com provedores
- Status dos pedidos pode ser atualizado via cron
- Saldo dos provedores é verificado periodicamente

### **Cache**
- Lista de serviços é armazenada temporariamente
- Status dos pedidos é atualizado em tempo real
- Configurações são salvas no banco de dados do WordPress

## 📞 Suporte

### **Logs de Erro**
- Verifique o console do navegador para erros JavaScript
- Monitore os logs do WordPress para erros PHP
- Use as ferramentas de desenvolvedor para debug

### **Compatibilidade**
- Testado com WordPress 5.8+
- Compatível com WooCommerce 5.0+
- Funciona com temas padrão e personalizados
- Suporte a múltiplos provedores simultâneos

## 🚀 Próximas Versões

### **Funcionalidades Planejadas**
- [ ] **Webhook SMM**: Receber atualizações automáticas dos provedores
- [ ] **Relatórios Avançados**: Estatísticas detalhadas de uso
- [ ] **Integração WhatsApp**: Notificações via WhatsApp Business
- [ ] **API REST**: Endpoints para integrações externas
- [ ] **Backup Automático**: Backup das configurações SMM
- [ ] **Sincronização**: Sincronização automática de status

### **Melhorias Técnicas**
- [ ] **Cache Inteligente**: Cache otimizado para melhor performance
- [ ] **Fila de Pedidos**: Sistema de fila para grandes volumes
- [ ] **Retry Automático**: Tentativas automáticas em caso de falha
- [ ] **Monitoramento**: Sistema de monitoramento de saúde dos provedores
- [ ] **Logs Estruturados**: Sistema de logs mais robusto

## 📄 Licença

Este módulo é desenvolvido para uso pessoal e comercial. Sinta-se livre para modificar e distribuir conforme suas necessidades.

## 🤝 Contribuições

Sugestões e melhorias são sempre bem-vindas! Entre em contato para compartilhar suas ideias ou reportar bugs.

---

**Desenvolvido com ❤️ para a comunidade WordPress/WooCommerce/SMM**

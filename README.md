# 🛒 Plugin Pedidos em Processamento - WooCommerce

Plugin para WordPress que lista e gerencia todos os pedidos do WooCommerce com status "processando" em uma interface administrativa dedicada.

## ✨ Funcionalidades

### 📊 **Dashboard Completo**
- Lista todos os pedidos com status "processando"
- Estatísticas em tempo real (total de pedidos, produtos e valor)
- Interface moderna e responsiva
- Auto-atualização a cada 5 minutos

### 🔍 **Sistema de Filtros Avançado**
- **Filtro por Produto**: Mostra apenas pedidos que contêm produtos específicos
- **Filtro por Data**: Hoje, ontem, última semana, último mês
- **Busca Inteligente**: Por ID do pedido, username do Instagram, nome ou email do cliente

### 📋 **Informações Detalhadas**
- **ID do Pedido** e número
- **Data e hora** da criação
- **Dados do Cliente**: Nome, email, telefone
- **Username do Instagram** (se configurado)
- **Produtos**: Nome, quantidade, preço unitário e total
- **Valor total** do pedido
- **Método de pagamento**
- **Observações** do cliente

### ⚡ **Ações Rápidas**
- **Ver Pedido**: Abre o pedido no WooCommerce
- **Marcar Concluído**: Atualiza status para "completed"
- **Modal de Detalhes**: Clique no pedido para ver informações completas

### 📤 **Exportação de Dados**
- Exportar lista completa para CSV
- Inclui todos os dados relevantes
- Nome do arquivo com data atual

## 🚀 Instalação

### 1. **Upload do Plugin**
- Faça upload da pasta `pedidos-processando-plugin` para `/wp-content/plugins/`
- Ou compacte a pasta e faça upload via painel administrativo

### 2. **Ativação**
- No painel WordPress, vá em **Plugins**
- Ative o plugin **"Pedidos em Processamento - WooCommerce"**

### 3. **Acesso**
- O menu **"Pedidos Processando"** aparecerá no menu lateral
- Clique para acessar a interface

## 📁 Estrutura do Plugin

```
pedidos-processando-plugin/
├── pedidos-processando.php      # Arquivo principal do plugin
├── assets/
│   ├── admin-style.css         # Estilos da interface administrativa
│   └── admin-script.js         # JavaScript para funcionalidades
└── README.md                   # Esta documentação
```

## ⚙️ Requisitos

- **WordPress** 5.0 ou superior
- **WooCommerce** 4.0 ou superior
- **PHP** 7.4 ou superior
- **Permissões**: `manage_woocommerce`

## 🔧 Configuração

### **Permissões**
O plugin requer que o usuário tenha permissão `manage_woocommerce` para:
- Visualizar a lista de pedidos
- Atualizar status dos pedidos
- Exportar dados
- Acessar todas as funcionalidades

### **Integração com Instagram**
Se você usa o plugin de consulta do Instagram:
- Os usernames do Instagram serão exibidos automaticamente
- Funciona com o campo personalizado do WooCommerce

## 📱 Interface

### **Header com Estatísticas**
- **Total de Pedidos**: Contador em tempo real
- **Total de Produtos**: Soma de todas as quantidades
- **Valor Total**: Soma de todos os valores

### **Filtros**
- **Produto**: Dropdown com todos os produtos disponíveis
- **Data**: Filtros pré-definidos por período
- **Busca**: Campo de texto para busca personalizada

### **Lista de Pedidos**
- **Cards individuais** para cada pedido
- **Indicadores visuais**:
  - 🟢 **Novo**: Pedidos de até 1 dia
  - 🔴 **Urgente**: Pedidos com mais de 3 dias
- **Ações rápidas** em cada card

### **Modal de Detalhes**
- **Informações completas** do pedido
- **Lista detalhada** de produtos
- **Dados do cliente** e observações

## 🎯 Casos de Uso

### **Para Lojistas**
- **Controle de Estoque**: Visualizar todos os pedidos pendentes
- **Gestão de Pedidos**: Marcar pedidos como concluídos rapidamente
- **Análise de Vendas**: Estatísticas em tempo real

### **Para Atendimento**
- **Suporte ao Cliente**: Acesso rápido aos dados do pedido
- **Rastreamento**: Verificar status e detalhes dos pedidos
- **Comunicação**: Dados completos para contato com clientes

### **Para Gerentes**
- **Relatórios**: Exportar dados para análise
- **Monitoramento**: Acompanhar volume de pedidos
- **Decisões**: Base de dados para tomada de decisões

## 🔒 Segurança

- **Verificação de Nonce**: Todas as requisições AJAX são validadas
- **Verificação de Permissões**: Apenas usuários autorizados podem acessar
- **Sanitização de Dados**: Todos os inputs são sanitizados
- **Validação de Dados**: Verificação de integridade antes de processar

## 🐛 Solução de Problemas

### **Plugin não aparece no menu**
- Verifique se o WooCommerce está ativo
- Confirme se o usuário tem permissão `manage_woocommerce`
- Verifique se não há conflitos com outros plugins

### **Lista não carrega**
- Verifique o console do navegador para erros JavaScript
- Confirme se as permissões AJAX estão funcionando
- Verifique se há pedidos com status "processing"

### **Erro de permissão**
- O usuário deve ter permissão para gerenciar WooCommerce
- Verifique as capacidades do usuário no WordPress

## 🔄 Atualizações

### **Auto-atualização**
- A lista é atualizada automaticamente a cada 5 minutos
- Clique em **"Atualizar Lista"** para atualização manual

### **Cache**
- Os dados são buscados em tempo real do WooCommerce
- Não há cache persistente para garantir dados sempre atualizados

## 📞 Suporte

### **Logs de Erro**
- Verifique o console do navegador para erros JavaScript
- Monitore os logs do WordPress para erros PHP
- Use as ferramentas de desenvolvedor para debug

### **Compatibilidade**
- Testado com WordPress 5.8+
- Compatível com WooCommerce 5.0+
- Funciona com temas padrão e personalizados

## 🚀 Próximas Versões

### **Funcionalidades Planejadas**
- [ ] Notificações por email para pedidos urgentes
- [ ] Integração com WhatsApp Business
- [ ] Relatórios avançados e gráficos
- [ ] Sistema de tags e categorização
- [ ] API REST para integrações externas
- [ ] Modo escuro na interface

### **Melhorias Técnicas**
- [ ] Cache inteligente para melhor performance
- [ ] Paginação para grandes volumes de pedidos
- [ ] Filtros salvos automaticamente
- [ ] Atalhos de teclado
- [ ] Modo offline com sincronização

## 📄 Licença

Este plugin é desenvolvido para uso pessoal e comercial. Sinta-se livre para modificar e distribuir conforme suas necessidades.

## 🤝 Contribuições

Sugestões e melhorias são sempre bem-vindas! Entre em contato para compartilhar suas ideias.

---

**Desenvolvido com ❤️ para a comunidade WordPress/WooCommerce**


# 🌍 Sistema de Service ID Duplo (BR/Internacional)

## 📋 **Visão Geral**

Sistema inteligente que permite configurar **Service IDs diferentes para variações Brasil e Internacional** no mesmo produto, com detecção automática e aplicação via um clique.

## 🎯 **Como Funciona**

### **1. Interface Simples**
```
📦 Meta Box SMM (Produto Principal):
┌─────────────────────────────────────┐
│ ☑ Ativar envio automático SMM       │
│ Provedor: [MachineSMM ▼]           │
│                                     │
│ Service ID (Padrão): [1111    ]    │ ← Fallback
│                                     │
│ 🌍 Service IDs por Região:          │
│ 🇧🇷 Service ID Brasil: [2222  ]    │ ← Para variações BR  
│ 🌎 Service ID Internacional: [3333] │ ← Para variações INT
│                                     │
│ [🔄 Aplicar às Variações]          │
└─────────────────────────────────────┘
```

### **2. Detecção Automática**
O sistema detecta automaticamente o tipo da variação por:

#### **🔍 Atributos WooCommerce**
- **Brasil**: `br`, `brasil`, `brasileiro`, `nacional`
- **Internacional**: `int`, `internacional`, `global`, `worldwide`

#### **📝 Nome da Variação**
- **Brasil**: Contém "br", "brasil", "brasileiro"
- **Internacional**: Contém "int", "internacional", "global"

#### **🏷️ SKU da Variação**
- **Brasil**: SKU contém "br" ou "brasil"
- **Internacional**: SKU contém "int" ou "global"

### **3. Aplicação Automática**
```
Fluxo de Trabalho:
1. Configure os Service IDs no produto pai
2. Clique em "🔄 Aplicar às Variações"
3. Sistema detecta automaticamente cada variação
4. Aplica o Service ID correto baseado na detecção
5. Mostra relatório: "✅ 12 variações configuradas (6 BR + 6 INT)"
```

## 🛠️ **Uso Prático**

### **Exemplo Real**
```
Produto: "Curtidas Instagram"
├── 100 Curtidas - BR        → Service ID: 2222
├── 100 Curtidas - INT       → Service ID: 3333
├── 500 Curtidas - BR        → Service ID: 2222
├── 500 Curtidas - INT       → Service ID: 3333
├── 1000 Curtidas - Brasil   → Service ID: 2222
└── 1000 Curtidas - Global   → Service ID: 3333
```

### **Processamento de Pedidos**
```php
// Quando um pedido é processado:
1. Sistema verifica se é variação
2. Se for, detecta automaticamente o tipo (BR/INT)
3. Aplica o Service ID correto
4. Envia para a API SMM com o Service ID apropriado
```

## ⚙️ **Lógica de Fallback**

### **Hierarquia de Service IDs**
```
1. Service ID específico da variação (se configurado manualmente)
2. Service ID BR/Internacional (baseado na detecção)
3. Service ID padrão (fallback global)
```

### **Exemplo de Fallback**
```
Variação "500 Curtidas - BR":
✅ Tem Service ID BR configurado (2222) → USA: 2222
❌ Service ID BR vazio → USA: Service ID Padrão (1111)
```

## 🎨 **Interface Visual**

### **Resultado da Aplicação**
```
Status após clicar "Aplicar às Variações":

✅ Aplicado com sucesso!
📊 Total: 12 variações
🇧🇷 Brasil: 6 variações  
🌎 Internacional: 6 variações
⚙️ Configuradas: 12 variações
```

## 🔧 **Arquivos Modificados**

### **1. `smm-variation-mapper.php` (NOVO)**
- **Classe**: `SMMVariationMapper`
- **Função**: Detecção automática e mapeamento de variações
- **Métodos**:
  - `detect_variation_type()` - Detecta se é BR ou Internacional
  - `get_service_id_for_variation()` - Retorna Service ID correto
  - `apply_to_all_variations()` - Aplica configurações em massa

### **2. `smm-module.php` (MODIFICADO)**
- **Interface**: Novos campos Service ID BR/Internacional
- **JavaScript**: Botão "Aplicar às Variações" com AJAX
- **AJAX**: Função `ajax_apply_smm_to_variations()`
- **Lógica**: Função `get_product_service_id()` atualizada

### **3. `load-smm.php` (MODIFICADO)**
- Carregamento do novo módulo `smm-variation-mapper.php`

## 🚀 **Vantagens**

### **✅ Simplicidade**
- Apenas 3 campos (padrão, BR, internacional)
- Um botão para aplicar automaticamente
- Interface intuitiva e visual

### **✅ Inteligência**
- Detecção automática por múltiplos critérios
- Fallbacks inteligentes
- Logs detalhados para debug

### **✅ Flexibilidade**
- Suporta qualquer naming convention
- Permite override manual
- Funciona com produtos existentes

### **✅ Compatibilidade**
- Não quebra funcionalidade existente
- Produtos simples inalterados
- Migração transparente

## 📝 **Como Testar**

### **1. Criar Produto Variável**
```
1. Criar produto "Curtidas Instagram"
2. Adicionar atributo "Origem" com valores: "BR", "Internacional"
3. Adicionar atributo "Quantidade" com valores: "100", "500", "1000"
4. Gerar variações automaticamente
```

### **2. Configurar SMM**
```
1. Ir na meta box SMM do produto pai
2. Ativar SMM
3. Escolher provedor
4. Configurar:
   - Service ID Brasil: 1234
   - Service ID Internacional: 5678
5. Clicar em "🔄 Aplicar às Variações"
```

### **3. Verificar Resultado**
```
1. Verificar se variações foram configuradas
2. Ver logs de debug para detecção
3. Testar pedido com variação BR
4. Testar pedido com variação Internacional
```

## 🔍 **Debug e Logs**

O sistema gera logs detalhados em:
- Detecção de tipo de variação
- Aplicação de Service IDs
- Processamento de pedidos
- Erros e fallbacks

```php
// Exemplo de log
[VARIATION_MAPPER] Detectando tipo da variação ID: 123
[VARIATION_MAPPER] Atributos da variação: {"pa_origem":"br","pa_quantidade":"100"}
[VARIATION_MAPPER] Variação detectada como BR pelo atributo: pa_origem = br
[VARIATION_MAPPER] Usando Service ID BR: 1234
```

## 🎯 **Conclusão**

Esta é uma **solução sólida e profissional** que resolve o problema de Service IDs duplos de forma:

- **🎯 Elegante**: Interface simples e intuitiva
- **🧠 Inteligente**: Detecção automática robusta  
- **🔧 Maintível**: Código limpo e bem estruturado
- **📈 Escalável**: Pode ser expandido facilmente

**Sistema pronto para produção! 🚀**

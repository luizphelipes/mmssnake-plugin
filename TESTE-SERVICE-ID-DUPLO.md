# 🧪 Teste do Sistema de Service ID Duplo

## 📋 **Como Testar o Sistema**

### **1. Configuração Inicial**

#### **A. Configurar Produto Pai**
```
1. Ir ao produto variável no WordPress Admin
2. Meta Box SMM > Configurar:
   ✅ Ativar envio automático SMM
   📦 Provedor: MachineSMM
   🔢 Service ID (Padrão): 1111
   🇧🇷 Service ID Brasil: 2222
   🌎 Service ID Internacional: 3333
   📝 Tipo de Lógica: Posts/Reels
3. Clicar "🔄 Aplicar às Variações"
4. Ver resultado: "✅ 12 variações configuradas (6 BR + 6 INT)"
```

#### **B. Verificar Variações**
```
Variações esperadas:
├── 10 Curtidas - Brasileiros    → Service ID: 2222
├── 10 Curtidas - Globais        → Service ID: 3333
├── 250 Curtidas - Brasileiros   → Service ID: 2222
├── 250 Curtidas - Globais       → Service ID: 3333
├── 500 Curtidas - Brasileiros   → Service ID: 2222
├── 500 Curtidas - Globais       → Service ID: 3333
├── 1000 Curtidas - Brasileiros  → Service ID: 2222
├── 1000 Curtidas - Globais      → Service ID: 3333
├── 2500 Curtidas - Brasileiros  → Service ID: 2222
├── 2500 Curtidas - Globais      → Service ID: 3333
├── 5000 Curtidas - Brasileiros  → Service ID: 2222
├── 5000 Curtidas - Globais      → Service ID: 3333
├── 10000 Curtidas - Brasileiros → Service ID: 2222
└── 10000 Curtidas - Globais     → Service ID: 3333
```

### **2. Teste de Processamento**

#### **A. Criar Pedido de Teste**
```
1. Fazer pedido com variação "Brasileiros"
2. Verificar logs de debug
3. Confirmar que Service ID 2222 foi usado
```

#### **B. Verificar Logs**
```
Logs esperados:
[OBTER_SERVICE_ID] Obtendo Service ID do produto #123 para pedido #456
[OBTER_SERVICE_ID] Produto é variação, aplicando mapeamento BR/Internacional
[OBTER_SERVICE_ID] Tipo detectado para variação #123: br
[OBTER_SERVICE_ID] Service ID BR obtido para variação #123: 2222
```

### **3. Teste de Detecção**

#### **A. Padrões de Detecção**
```
✅ "Brasileiros" → BR (Service ID: 2222)
✅ "Globais" → Internacional (Service ID: 3333)
✅ "brasil" → BR
✅ "brasileiro" → BR
✅ "brasileira" → BR
✅ "nacional" → BR
✅ "global" → Internacional
✅ "internacional" → Internacional
✅ "int" → Internacional
```

#### **B. Fallbacks**
```
❌ Service ID BR vazio → USA Service ID Padrão (1111)
❌ Service ID INT vazio → USA Service ID Padrão (1111)
❌ Ambos vazios → USA Service ID Global
❌ Tudo vazio → ERRO (não processa)
```

## 🔍 **Debug e Verificação**

### **1. Verificar Meta Data**
```sql
-- Verificar configurações do produto pai
SELECT post_id, meta_key, meta_value 
FROM wp_postmeta 
WHERE post_id = [PRODUTO_PAI_ID] 
AND meta_key LIKE '_smm_%';

-- Verificar configurações das variações
SELECT post_id, meta_key, meta_value 
FROM wp_postmeta 
WHERE post_id IN (SELECT ID FROM wp_posts WHERE post_parent = [PRODUTO_PAI_ID])
AND meta_key = '_smm_service_id';
```

### **2. Logs de Debug**
```
Arquivo: wp-content/debug-pedidos-plugin.log

Procurar por:
- [OBTER_SERVICE_ID] - Logs de obtenção de Service ID
- [VARIATION_MAPPER] - Logs de detecção de variação
- [PROCESSAMENTO_PEDIDO] - Logs de processamento
```

### **3. Teste Manual**
```php
// Testar detecção manualmente
$variation = wc_get_product([VARIATION_ID]);
$detector = new PedidosProcessando();
$type = $detector->detect_variation_type($variation);
echo "Tipo detectado: " . $type;
```

## 🚨 **Problemas Comuns e Soluções**

### **1. Service ID Vazio**
```
Problema: Pedido processado sem Service ID
Solução: 
1. Verificar se produto pai tem Service IDs configurados
2. Verificar se botão "Aplicar às Variações" foi clicado
3. Verificar logs de debug
```

### **2. Detecção Incorreta**
```
Problema: Variação "Brasileiros" detectada como Internacional
Solução:
1. Verificar atributos da variação
2. Verificar nome da variação
3. Verificar SKU da variação
4. Ajustar padrões na função detect_variation_type()
```

### **3. Fallback Não Funciona**
```
Problema: Sistema não usa Service ID padrão como fallback
Solução:
1. Verificar se Service ID padrão está configurado
2. Verificar se Service ID global está configurado
3. Verificar logs de fallback
```

## ✅ **Checklist de Teste**

### **Configuração**
- [ ] Produto pai configurado com Service IDs BR e Internacional
- [ ] Botão "Aplicar às Variações" clicado
- [ ] Variações mostram Service IDs corretos
- [ ] Logs mostram aplicação bem-sucedida

### **Detecção**
- [ ] Variações "Brasileiros" detectadas como BR
- [ ] Variações "Globais" detectadas como Internacional
- [ ] Logs mostram detecção correta
- [ ] Fallbacks funcionam quando necessário

### **Processamento**
- [ ] Pedido com variação BR usa Service ID BR
- [ ] Pedido com variação INT usa Service ID Internacional
- [ ] Logs mostram Service ID correto
- [ ] API SMM recebe Service ID correto

### **Fallbacks**
- [ ] Service ID padrão usado quando BR/INT vazios
- [ ] Service ID global usado quando tudo vazio
- [ ] Erro mostrado quando nada configurado
- [ ] Logs mostram fallbacks corretos

## 🎯 **Resultado Esperado**

### **Sucesso Total**
```
✅ Todas as variações configuradas automaticamente
✅ Detecção BR/Internacional funcionando 100%
✅ Service IDs corretos aplicados nos pedidos
✅ Logs detalhados para debug
✅ Fallbacks funcionando perfeitamente
```

### **Sistema Funcionando**
```
🔄 Cliente compra "500 Curtidas - Brasileiros"
📊 Sistema detecta: BR
🎯 Aplica Service ID: 2222
📤 Envia para API SMM com Service ID correto
✅ Pedido processado com sucesso
```

**Sistema pronto para produção! 🚀**

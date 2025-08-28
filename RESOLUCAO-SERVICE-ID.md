# 🔧 Resolução do Problema do Service ID

## 🚨 Problema Identificado

Os campos `smm_service_id` não estão sendo salvos corretamente nos produtos, causando o uso incorreto do ID sequencial do produto como service ID.

## 🔍 Diagnóstico

### **Sintomas**
- Campos SMM não aparecem na edição do produto
- Valores não são salvos quando configurados
- Plugin usa ID do produto em vez do service ID configurado
- Logs mostram "Service ID não configurado"

### **Causas Possíveis**
1. **Módulo SMM não carregado corretamente**
2. **Hooks não registrados**
3. **Conflitos com outros plugins**
4. **Permissões de usuário insuficientes**
5. **Ordem de carregamento incorreta**

## 🧪 Testes para Diagnóstico

### **1. Teste Básico de Campos**
Execute `teste-campos-smm.php` para verificar:
- ✅ Campos SMM visíveis no produto
- ✅ Valores sendo salvos
- ✅ Meta fields no banco de dados

### **2. Teste Forçado do Módulo**
Execute `teste-forcar-smm.php` para verificar:
- ✅ Classes SMM carregadas
- ✅ Hooks registrados
- ✅ Meta boxes funcionando

### **3. Teste de Service ID**
Execute `teste-service-id-produto.php` para verificar:
- ✅ Service ID sendo determinado corretamente
- ✅ Campos sendo lidos
- ✅ Fallback funcionando

## 🔧 Soluções

### **Solução 1: Verificar Carregamento do Módulo**

1. **Verificar se o arquivo está sendo carregado:**
   ```php
   // No arquivo pedidos-processando.php, linha ~1270
   if (file_exists(plugin_dir_path(__FILE__) . 'modules/smm/load-smm.php')) {
       require_once plugin_dir_path(__FILE__) . 'modules/smm/load-smm.php';
   }
   ```

2. **Verificar se não há erros de sintaxe:**
   - Acesse `/wp-content/debug.log`
   - Procure por erros relacionados ao módulo SMM

### **Solução 2: Forçar Inicialização**

1. **Adicionar inicialização manual:**
   ```php
   // No final do arquivo pedidos-processando.php
   if (class_exists('SMMModule')) {
       new SMMModule();
   }
   ```

2. **Verificar ordem de carregamento:**
   - WooCommerce deve estar ativo
   - Plugin deve ser carregado após WooCommerce

### **Solução 3: Verificar Hooks**

1. **Verificar se os hooks estão registrados:**
   ```php
   // No módulo SMM
   add_action('add_meta_boxes', [$this, 'add_product_smm_meta_box']);
   add_action('save_post', [$this, 'save_product_smm_meta_box']);
   ```

2. **Verificar se não há conflitos:**
   - Desativar outros plugins temporariamente
   - Testar com tema padrão

### **Solução 4: Verificar Permissões**

1. **Usuário deve ter permissões:**
   - `edit_post` para produtos
   - `manage_woocommerce` para configurações

2. **Verificar no código:**
   ```php
   if (!current_user_can('edit_post', $post_id)) {
       return;
   }
   ```

## 📋 Passos para Resolver

### **Passo 1: Diagnóstico**
1. Execute `teste-forcar-smm.php`
2. Verifique se as classes estão sendo carregadas
3. Confirme se os hooks estão registrados

### **Passo 2: Verificação de Arquivos**
1. Confirme que todos os arquivos SMM existem
2. Verifique se não há erros de sintaxe
3. Confirme ordem de carregamento

### **Passo 3: Teste de Salvamento**
1. Use o formulário de teste para salvar campos
2. Verifique se os valores persistem
3. Confirme se o módulo está lendo os campos

### **Passo 4: Configuração Manual**
1. Configure um produto com service ID
2. Salve e verifique se foi salvo
3. Teste com pedido real

## 🔍 Verificações Específicas

### **Verificar Meta Fields no Banco**
```sql
SELECT post_id, meta_key, meta_value 
FROM wp_postmeta 
WHERE meta_key LIKE '%smm%' OR meta_key LIKE '%service%'
ORDER BY post_id, meta_key;
```

### **Verificar Hooks WordPress**
```php
// Adicionar temporariamente no arquivo de teste
add_action('admin_notices', function() {
    echo '<div class="notice notice-info">';
    echo '<p>Hooks add_meta_boxes: ' . (has_action('add_meta_boxes') ? '✅' : '❌') . '</p>';
    echo '<p>Hooks save_post: ' . (has_action('save_post') ? '✅' : '❌') . '</p>';
    echo '</div>';
});
```

### **Verificar Classes Carregadas**
```php
// Adicionar temporariamente no arquivo de teste
add_action('admin_notices', function() {
    echo '<div class="notice notice-info">';
    echo '<p>SMMModule: ' . (class_exists('SMMModule') ? '✅' : '❌') . '</p>';
    echo '<p>SMMApi: ' . (class_exists('SMMApi') ? '✅' : '❌') . '</p>';
    echo '</div>';
});
```

## 🚨 Casos Especiais

### **Produtos com Variações**
- Service ID deve ser configurado no produto pai
- Variações herdam o service ID do pai
- Verificar se o produto pai tem SMM habilitado

### **Múltiplos Provedores**
- Verificar se o provedor está selecionado
- Confirmar se o provedor está ativo
- Testar conexão com a API

### **Conflitos de Plugins**
- Desativar plugins temporariamente
- Verificar se o problema persiste
- Reativar plugins um por vez

## 📊 Logs de Debug

### **Ativar Debug WordPress**
```php
// No wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### **Logs Importantes**
```
[INFO] Módulo SMM carregado
[INFO] Meta box criado para produto
[INFO] Campo salvo: _smm_service_id = 4393
[INFO] Service ID determinado: 4393
```

## 🔄 Próximos Passos

1. **Execute os testes de diagnóstico**
2. **Identifique a causa raiz do problema**
3. **Aplique a solução específica**
4. **Teste com produto real**
5. **Verifique se o service ID está sendo usado corretamente**

## 📞 Suporte

### **Informações para Debug**
- Resultados dos testes executados
- Logs de erro do WordPress
- Versão do WooCommerce
- Lista de plugins ativos
- Tema em uso

### **Arquivos de Teste Disponíveis**
- `teste-campos-smm.php` - Teste básico de campos
- `teste-forcar-smm.php` - Teste forçado do módulo
- `teste-service-id-produto.php` - Teste de service ID

---

**🎯 Objetivo: Garantir que o campo `_smm_service_id` seja salvo corretamente e usado pelo plugin em vez do ID sequencial do produto.**

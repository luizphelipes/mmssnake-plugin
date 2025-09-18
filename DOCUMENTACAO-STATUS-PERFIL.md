# 📋 Documentação: Status do Perfil Instagram

## 🎯 **Resposta Direta**

### **Nome do Campo de Meta:**
```php
'Status do Perfil'
```

### **Como Acessar:**
```php
$status = wc_get_order_item_meta($item_id, 'Status do Perfil', true);
```

### **Valores Possíveis:**
- `'Público'` - Perfil público
- `'Privado'` - Perfil privado  
- `'Desconhecido'` - Status não determinado

## 🔍 **Detalhes Técnicos**

### **Localização:**
- **Tipo:** Meta do item do pedido (não do pedido)
- **Função:** `wc_get_order_item_meta()`
- **Chave:** `'Status do Perfil'`

### **Fluxo de Dados:**
1. **Frontend:** JavaScript captura `is_private` da API
2. **Processamento:** PHP converte para formato legível
3. **Salvamento:** Salvo como meta do item do pedido

### **Mapeamento de Valores:**
```php
// Valores brutos da API → Valores salvos
'private' → 'Privado'
'public'  → 'Público'
'unknown' → 'Desconhecido'
```

## 💻 **Exemplos de Uso**

### **1. Verificar Status de um Item:**
```php
$order = wc_get_order($order_id);
$items = $order->get_items();

foreach ($items as $item_id => $item) {
    $status = wc_get_order_item_meta($item_id, 'Status do Perfil', true);
    
    if ($status === 'Privado') {
        echo "⚠️ Perfil privado detectado!";
    } elseif ($status === 'Público') {
        echo "✅ Perfil público - OK para processar";
    } else {
        echo "❓ Status desconhecido";
    }
}
```

### **2. Filtrar Apenas Perfis Públicos:**
```php
function obter_apenas_perfis_publicos($order_id) {
    $order = wc_get_order($order_id);
    $perfis_publicos = [];
    
    foreach ($order->get_items() as $item_id => $item) {
        $status = wc_get_order_item_meta($item_id, 'Status do Perfil', true);
        
        if ($status === 'Público') {
            $perfis_publicos[] = $item_id;
        }
    }
    
    return $perfis_publicos;
}
```

### **3. Verificação Completa:**
```php
function verificar_status_perfil($order_id) {
    $order = wc_get_order($order_id);
    $resultado = [
        'publicos' => 0,
        'privados' => 0,
        'desconhecidos' => 0,
        'total' => 0
    ];
    
    foreach ($order->get_items() as $item_id => $item) {
        $status = wc_get_order_item_meta($item_id, 'Status do Perfil', true);
        
        switch ($status) {
            case 'Público':
                $resultado['publicos']++;
                break;
            case 'Privado':
                $resultado['privados']++;
                break;
            default:
                $resultado['desconhecidos']++;
                break;
        }
        
        $resultado['total']++;
    }
    
    return $resultado;
}

// Uso:
$status = verificar_status_perfil($order_id);
echo "Públicos: {$status['publicos']}, Privados: {$status['privados']}";
```

## ⚠️ **Importante**

### **Validação:**
```php
$status = wc_get_order_item_meta($item_id, 'Status do Perfil', true);

// Sempre validar se o campo existe
if (empty($status)) {
    // Campo não existe ou está vazio
    return;
}

// Verificar se é um valor válido
$valores_validos = ['Público', 'Privado', 'Desconhecido'];
if (!in_array($status, $valores_validos)) {
    // Valor inválido
    return;
}
```

### **Tratamento de Erros:**
```php
function obter_status_perfil_seguro($item_id) {
    $status = wc_get_order_item_meta($item_id, 'Status do Perfil', true);
    
    if (empty($status)) {
        return 'Desconhecido'; // Valor padrão
    }
    
    return $status;
}
```

## 🎯 **Resumo para Seu Plugin**

```php
// ✅ CORRETO - Como acessar o status
$status = wc_get_order_item_meta($item_id, 'Status do Perfil', true);

// ✅ Valores que você receberá:
// 'Público'   - Perfil público
// 'Privado'   - Perfil privado
// 'Desconhecido' - Status não determinado

// ✅ Exemplo de uso no seu plugin:
if ($status === 'Público') {
    // Processar curtidas normalmente
    processar_curtidas($url);
} elseif ($status === 'Privado') {
    // Pular ou avisar sobre perfil privado
    log_erro("Perfil privado - não é possível processar curtidas");
} else {
    // Status desconhecido - decidir o que fazer
    log_aviso("Status do perfil desconhecido");
}
```

## 📊 **Estrutura Completa dos Metadados**

| Campo | Tipo | Valores | Descrição |
|-------|------|---------|-----------|
| `Status do Perfil` | String | `'Público'`, `'Privado'`, `'Desconhecido'` | Status do perfil Instagram |
| `Instagram` | String | `'@username'` | Username do Instagram |
| `Instagram Posts` | String | URLs separadas por vírgula | Links das publicações |
| `Quantidade Multiplicada` | String | `'Sim'` | Se quantidade foi multiplicada |
| `Multiplicador` | String | Número | Número do multiplicador |

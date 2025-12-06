# 🗄️ Sistema de Banco de Dados - Aquabeat Relatórios

Sistema de banco de dados MySQL para armazenar vendas, promotores e configurações. Preparado para migração futura para plugin WordPress.

## 📋 Estrutura

### Tabelas Criadas

1. **aq_vendas** - Todos os dados das vendas (25 campos do CSV + campos calculados)
2. **aq_promotores** - Dados dos promotores/consultores
3. **aq_pins_consultores** - Sistema de PIN para acesso
4. **aq_usuarios** - Usuários administrativos
5. **aq_arquivos_csv** - Metadados dos arquivos CSV importados
6. **aq_configuracoes** - Configurações do sistema
7. **aq_ranges_pontuacao** - Ranges de pontuação por data
8. **aq_log_imports** - Log de importações

### Compatibilidade WordPress

A classe `AquabeatDatabase` foi criada com métodos compatíveis com `$wpdb` do WordPress:

- `get_results()` - Buscar múltiplas linhas
- `get_row()` - Buscar uma linha
- `get_var()` - Buscar um valor
- `query()` - Executar query
- `insert()` - Inserir registro
- `update()` - Atualizar registro
- `delete()` - Deletar registro
- `prepare()` - Preparar query

## 🚀 Configuração Inicial

### 1. Configurar Banco de Dados

Edite `database/config.php` se necessário:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'aquabeat_relatorios');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
```

### 2. Criar Banco de Dados

```sql
CREATE DATABASE aquabeat_relatorios CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Executar Migrations

Acesse via browser:
```
http://seusite.com/database/migrations.php
```

Ou via CLI:
```php
php database/migrations.php
```

Clique em **"Criar/Atualizar Tabelas"**

## 📥 Importando Dados

### Via Admin (em breve)

Uma interface será criada no Admin para importar CSVs diretamente para o banco.

### Via Código

```php
require_once 'database/import_csv.php';

// Importar vendas
$resultado = importarVendasParaBanco(
    'data/vendas/arquivo.csv',
    '2025-11',  // mês de referência
    'Vendas Novembro 2025'  // nome amigável
);

// Importar promotores
$resultado = importarPromotoresParaBanco(
    'data/promotores/arquivo.csv',
    'Promotores Novembro 2025'
);
```

## 📤 Exportar/Importar Dados

### Exportar

Acesse `migrations.php` e clique em **"Exportar Dados"**

Isso gera um arquivo JSON em `data/export_database_YYYY-MM-DD_HHmmss.json`

### Importar

Para importar em outro ambiente ou no plugin WordPress:

```php
require_once 'database/migrations.php';
importarDados('data/export_database_2025-12-06_123456.json');
```

## 🔄 Migração para WordPress

Quando for migrar para plugin WordPress:

1. **Renomear classe**: `AquabeatDatabase` → usar `$wpdb` global do WordPress
2. **Prefixo de tabelas**: Usar `$wpdb->prefix` em vez de `TABLE_PREFIX`
3. **Importar dados**: Usar o arquivo JSON exportado
4. **Adaptar queries**: Todas já são compatíveis!

### Exemplo de Migração

**Antes (sistema atual):**
```php
global $aqdb;
$vendas = $aqdb->get_results("SELECT * FROM " . TABLE_VENDAS);
```

**Depois (WordPress):**
```php
global $wpdb;
$vendas = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}vendas");
```

## 🔧 Uso no Código

### Buscar Vendas

```php
require_once 'database/config.php';
global $aqdb;

// Buscar vendas de um mês
$vendas = $aqdb->get_results("
    SELECT * FROM " . TABLE_VENDAS . "
    WHERE mes_referencia = '2025-11'
    AND status = 'Ativo'
    ORDER BY data_cadastro DESC
");

// Buscar vendas de um consultor
$consultor = 'Nome do Consultor';
$vendas = $aqdb->get_results($aqdb->prepare("
    SELECT * FROM " . TABLE_VENDAS . "
    WHERE consultor = %s
", $consultor));
```

### Inserir Registro

```php
$resultado = $aqdb->insert(TABLE_VENDAS, [
    'titulo_id' => 'SFA-12345',
    'consultor' => 'João Silva',
    'valor_total' => 1500.00,
    'status' => 'Ativo',
    'mes_referencia' => '2025-11'
]);

$id_inserido = $aqdb->insert_id;
```

### Atualizar Registro

```php
$aqdb->update(
    TABLE_VENDAS,
    ['status' => 'Cancelado'],  // dados
    ['titulo_id' => 'SFA-12345']  // where
);
```

## 📊 Queries Úteis

### Estatísticas de um Mês

```php
$stats = $aqdb->get_row("
    SELECT
        COUNT(*) as total_vendas,
        COUNT(DISTINCT consultor) as total_consultores,
        SUM(valor_total) as valor_total,
        SUM(valor_pago) as valor_pago
    FROM " . TABLE_VENDAS . "
    WHERE mes_referencia = '2025-11'
    AND status = 'Ativo'
");
```

### Ranking de Consultores

```php
$ranking = $aqdb->get_results("
    SELECT
        consultor,
        COUNT(*) as quantidade_vendas,
        SUM(num_vagas) as total_vagas,
        SUM(valor_total) as valor_total
    FROM " . TABLE_VENDAS . "
    WHERE mes_referencia = '2025-11'
    AND status = 'Ativo'
    GROUP BY consultor
    ORDER BY total_vagas DESC
");
```

## 🔐 Segurança

### Usuário Padrão

- **Username**: `admin`
- **Password**: `admin123`

⚠️ **IMPORTANTE**: Altere a senha após primeiro login!

### Alterar Senha Admin

```php
$nova_senha = password_hash('sua_nova_senha', PASSWORD_DEFAULT);
$aqdb->update(
    TABLE_USUARIOS,
    ['password' => $nova_senha],
    ['username' => 'admin']
);
```

## 🔄 Sincronização API (Futuro)

A estrutura já está preparada para sincronização via API:

- Tabela `aq_arquivos_csv` tem campo `status_import`
- Log de importações em `aq_log_imports`
- Campos `created_at` e `updated_at` em todas as tabelas

### Estrutura Futura

```php
// Função para sincronizar via API (a ser implementada)
function sincronizarVendasAPI($mes_referencia) {
    $api_url = 'https://api.aquabeat.com/vendas';
    $dados = chamarAPI($api_url, ['mes' => $mes_referencia]);

    // Limpa dados antigos
    limparVendasMes($mes_referencia);

    // Importa novos dados
    foreach ($dados as $venda) {
        $aqdb->insert(TABLE_VENDAS, $venda);
    }
}
```

## ⚙️ Manutenção

### Resetar Banco (CUIDADO!)

```
http://seusite.com/database/migrations.php?action=resetar
```

Isso **APAGA TODOS OS DADOS**!

### Verificar Integridade

```
http://seusite.com/database/migrations.php?action=verificar
```

### Backup

Sempre exporte os dados antes de resetar:

```
http://seusite.com/database/migrations.php?action=exportar
```

## 📝 Logs

Todos os imports são registrados em `aq_log_imports`:

```php
$logs = $aqdb->get_results("
    SELECT * FROM " . TABLE_LOG_IMPORTS . "
    ORDER BY created_at DESC
    LIMIT 10
");
```

## 🎯 Próximos Passos

- [ ] Interface admin para importar CSVs
- [ ] Atualizar functions/vendas.php para usar banco
- [ ] Atualizar functions/promotores.php para usar banco
- [ ] Sincronização automática via API
- [ ] Plugin WordPress completo

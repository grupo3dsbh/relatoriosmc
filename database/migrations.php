<?php
/**
 * Script de Migração do Banco de Dados
 * Cria e atualiza tabelas
 */

require_once __DIR__ . '/config.php';

global $aqdb;

/**
 * Executa migrations (criação de tabelas)
 */
function executarMigrations() {
    global $aqdb;

    echo "<h2>Executando Migrations...</h2>";

    // Lê o schema SQL
    $schema_sql = file_get_contents(__DIR__ . '/schema.sql');

    // Separa por statement (cada CREATE TABLE ou INSERT)
    $statements = array_filter(
        array_map('trim', explode(';', $schema_sql)),
        function($stmt) {
            return !empty($stmt) && substr(trim($stmt), 0, 2) !== '--';
        }
    );

    $sucesso = 0;
    $erros = 0;

    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;

        try {
            $aqdb->query($statement);
            $sucesso++;

            // Extrai nome da tabela se for CREATE TABLE
            if (preg_match('/CREATE TABLE.*?`([^`]+)`/i', $statement, $matches)) {
                echo "<p style='color: green;'>✓ Tabela criada: {$matches[1]}</p>";
            } elseif (preg_match('/INSERT INTO.*?`([^`]+)`/i', $statement, $matches)) {
                echo "<p style='color: blue;'>✓ Dados inseridos em: {$matches[1]}</p>";
            }
        } catch (Exception $e) {
            $erros++;
            echo "<p style='color: red;'>✗ Erro: " . $e->getMessage() . "</p>";
            echo "<pre style='font-size:10px;'>" . substr($statement, 0, 200) . "...</pre>";
        }
    }

    echo "<h3>Resultado: {$sucesso} sucesso, {$erros} erros</h3>";

    return $erros === 0;
}

/**
 * Verifica se as tabelas existem
 */
function verificarTabelas() {
    global $aqdb;

    $tabelas = [
        TABLE_VENDAS,
        TABLE_PROMOTORES,
        TABLE_PINS,
        TABLE_USUARIOS,
        TABLE_ARQUIVOS_CSV,
        TABLE_CONFIGURACOES,
        TABLE_RANGES,
        TABLE_LOG_IMPORTS
    ];

    echo "<h2>Verificando Tabelas...</h2>";
    $todas_existem = true;

    foreach ($tabelas as $tabela) {
        $existe = $aqdb->get_var("SHOW TABLES LIKE '$tabela'");
        if ($existe) {
            $count = $aqdb->get_var("SELECT COUNT(*) FROM `$tabela`");
            echo "<p style='color: green;'>✓ $tabela ({$count} registros)</p>";
        } else {
            echo "<p style='color: red;'>✗ $tabela (não existe)</p>";
            $todas_existem = false;
        }
    }

    return $todas_existem;
}

/**
 * Reseta o banco (CUIDADO!)
 */
function resetarBanco() {
    global $aqdb;

    $tabelas = [
        TABLE_LOG_IMPORTS,
        TABLE_RANGES,
        TABLE_CONFIGURACOES,
        TABLE_ARQUIVOS_CSV,
        TABLE_PINS,
        TABLE_VENDAS,
        TABLE_PROMOTORES,
        TABLE_USUARIOS
    ];

    echo "<h2>Resetando Banco...</h2>";

    foreach ($tabelas as $tabela) {
        try {
            $aqdb->query("DROP TABLE IF EXISTS `$tabela`");
            echo "<p style='color: orange;'>✓ Tabela removida: $tabela</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ Erro ao remover $tabela: " . $e->getMessage() . "</p>";
        }
    }

    echo "<p><strong>Banco resetado! Execute as migrations novamente.</strong></p>";
}

/**
 * Exporta dados para JSON (para migração futura)
 */
function exportarDados() {
    global $aqdb;

    $export = [
        'versao' => '1.0',
        'data_export' => date('Y-m-d H:i:s'),
        'tabelas' => []
    ];

    $tabelas = [
        'vendas' => TABLE_VENDAS,
        'promotores' => TABLE_PROMOTORES,
        'pins' => TABLE_PINS,
        'usuarios' => TABLE_USUARIOS,
        'arquivos_csv' => TABLE_ARQUIVOS_CSV,
        'configuracoes' => TABLE_CONFIGURACOES,
        'ranges' => TABLE_RANGES
    ];

    foreach ($tabelas as $nome => $tabela) {
        $dados = $aqdb->get_results("SELECT * FROM `$tabela`", ARRAY_A);
        $export['tabelas'][$nome] = $dados;
        echo "<p>✓ Exportando $nome: " . count($dados) . " registros</p>";
    }

    $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $arquivo = __DIR__ . '/../data/export_database_' . date('Y-m-d_His') . '.json';

    file_put_contents($arquivo, $json);

    echo "<p style='color: green;'><strong>✓ Dados exportados para: $arquivo</strong></p>";
    echo "<p>Tamanho: " . number_format(strlen($json) / 1024, 2) . " KB</p>";

    return $arquivo;
}

/**
 * Importa dados de JSON
 */
function importarDados($arquivo_json) {
    global $aqdb;

    if (!file_exists($arquivo_json)) {
        echo "<p style='color: red;'>✗ Arquivo não encontrado: $arquivo_json</p>";
        return false;
    }

    $json = file_get_contents($arquivo_json);
    $dados = json_decode($json, true);

    if (!$dados) {
        echo "<p style='color: red;'>✗ Erro ao decodificar JSON</p>";
        return false;
    }

    echo "<h2>Importando Dados...</h2>";
    echo "<p>Versão: {$dados['versao']}</p>";
    echo "<p>Data Export: {$dados['data_export']}</p>";

    $tabelas_map = [
        'vendas' => TABLE_VENDAS,
        'promotores' => TABLE_PROMOTORES,
        'pins' => TABLE_PINS,
        'usuarios' => TABLE_USUARIOS,
        'arquivos_csv' => TABLE_ARQUIVOS_CSV,
        'configuracoes' => TABLE_CONFIGURACOES,
        'ranges' => TABLE_RANGES
    ];

    $aqdb->begin_transaction();

    try {
        foreach ($dados['tabelas'] as $nome => $registros) {
            $tabela = $tabelas_map[$nome];

            foreach ($registros as $registro) {
                // Remove campo id para auto_increment
                unset($registro['id']);

                $aqdb->insert($tabela, $registro);
            }

            echo "<p style='color: green;'>✓ Importados " . count($registros) . " registros para $nome</p>";
        }

        $aqdb->commit();
        echo "<p style='color: green;'><strong>✓ Importação concluída com sucesso!</strong></p>";
        return true;

    } catch (Exception $e) {
        $aqdb->rollback();
        echo "<p style='color: red;'>✗ Erro na importação: " . $e->getMessage() . "</p>";
        return false;
    }
}

// Se executado diretamente via browser
if (php_sapi_name() !== 'cli' && basename($_SERVER['PHP_SELF']) === 'migrations.php') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Migrations - Aquabeat Relatórios</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
            h1 { color: #333; }
            .btn { display: inline-block; padding: 10px 20px; margin: 5px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
            .btn-danger { background: #dc3545; }
            .btn-success { background: #28a745; }
            .result { border: 1px solid #ddd; padding: 15px; margin: 20px 0; border-radius: 5px; background: #f9f9f9; }
        </style>
    </head>
    <body>
        <h1>🗄️ Gerenciador de Banco de Dados</h1>

        <div>
            <a href="?action=verificar" class="btn">Verificar Tabelas</a>
            <a href="?action=criar" class="btn btn-success">Criar/Atualizar Tabelas</a>
            <a href="?action=exportar" class="btn">Exportar Dados</a>
            <a href="?action=resetar" class="btn btn-danger" onclick="return confirm('ATENÇÃO! Isso vai APAGAR TODOS OS DADOS! Tem certeza?')">Resetar Banco</a>
        </div>

        <div class="result">
            <?php
            $action = $_GET['action'] ?? '';

            switch ($action) {
                case 'verificar':
                    verificarTabelas();
                    break;

                case 'criar':
                    executarMigrations();
                    break;

                case 'exportar':
                    exportarDados();
                    break;

                case 'resetar':
                    resetarBanco();
                    break;

                default:
                    echo "<p>Selecione uma ação acima.</p>";
                    echo "<h3>Configuração Atual:</h3>";
                    echo "<p><strong>Host:</strong> " . DB_HOST . "</p>";
                    echo "<p><strong>Database:</strong> " . DB_NAME . "</p>";
                    echo "<p><strong>Prefixo:</strong> " . TABLE_PREFIX . "</p>";
            }
            ?>
        </div>

        <hr>
        <p><a href="../?page=admin">← Voltar para Admin</a></p>
    </body>
    </html>
    <?php
}

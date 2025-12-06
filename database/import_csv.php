<?php
/**
 * Funções para importar CSV para o banco de dados
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../functions/vendas.php';
require_once __DIR__ . '/../functions/promotores.php';

/**
 * Importa arquivo CSV de vendas para o banco
 */
function importarVendasParaBanco($caminho_csv, $mes_referencia = null, $nome_amigavel = null) {
    global $aqdb;

    $inicio = microtime(true);

    echo "<h3>Importando Vendas do CSV...</h3>";
    echo "<p>Arquivo: " . basename($caminho_csv) . "</p>";

    // Registra o arquivo
    $slug = $nome_amigavel ? gerarSlugArquivo($nome_amigavel) : null;

    $arquivo_id = $aqdb->insert(TABLE_ARQUIVOS_CSV, [
        'nome_arquivo' => basename($caminho_csv),
        'nome_amigavel' => $nome_amigavel,
        'slug' => $slug,
        'tipo' => 'vendas',
        'mes_referencia' => $mes_referencia,
        'caminho' => $caminho_csv,
        'tamanho_bytes' => filesize($caminho_csv),
        'status_import' => 'processando',
        'data_upload' => date('Y-m-d H:i:s')
    ]);

    if (!$arquivo_id) {
        echo "<p style='color:red;'>Erro ao registrar arquivo!</p>";
        return false;
    }

    // Processa o CSV
    $resultado = processarVendasCSV($caminho_csv);

    if (!$resultado || empty($resultado['vendas'])) {
        echo "<p style='color:red;'>Erro ao processar CSV!</p>";
        return false;
    }

    $total_linhas = count($resultado['vendas']);
    $sucesso = 0;
    $erros = 0;

    echo "<p>Total de vendas no CSV: {$total_linhas}</p>";
    echo "<p>Inserindo no banco de dados...</p>";

    $aqdb->begin_transaction();

    try {
        foreach ($resultado['vendas'] as $venda) {
            $dados_insert = [
                'titulo_id' => $venda['id'],
                'produto_original' => $venda['produto_original'],
                'produto_atual' => $venda['produto_atual'],
                'alterou_vagas' => $venda['alterou_vagas'] ?? '',
                'categoria' => $venda['categoria'],
                'data_cadastro' => $venda['data_cadastro'],
                'data_venda' => $venda['data_venda'],
                'origem_venda' => $venda['origem_venda'] ?? '',
                'telefone' => $venda['telefone'],
                'status' => $venda['status'],
                'consultor' => $venda['consultor'],
                'gerente' => $venda['gerente'],
                'titular' => $venda['titular'],
                'cpf' => $venda['cpf'],
                'cpf_limpo' => $venda['cpf_limpo'],
                'quantidade_parcelas_venda' => $venda['quantidade_parcelas_venda'],
                'parcelas' => $venda['parcelas'],
                'valores_pagos' => $venda['valores_pagos'],
                'forma_pagamento' => $venda['forma_pagamento'] ?? '',
                'parcelas_pagas' => $venda['parcelas_pagas'] ?? 0,
                'tipo_pagamento' => $venda['tipo_pagamento'],
                'valor_parcela' => $venda['valor_parcela'],
                'valor_total' => $venda['valor_total'],
                'valor_pago' => $venda['valor_pago'],
                'valor_restante' => $venda['valor_restante'],
                'parcelas_restantes' => $venda['parcelas_restantes'] ?? 0,
                'num_vagas' => $venda['num_vagas'],
                'e_vista' => $venda['e_vista'] ? 1 : 0,
                'primeira_parcela_paga' => $venda['primeira_parcela_paga'] ? 1 : 0,
                'produto_alterado' => $venda['produto_alterado'] ? 1 : 0,
                'data_para_filtro' => $venda['data_para_filtro'] ?? $venda['data_venda'],
                'data_para_pontuacao' => $venda['data_para_pontuacao'] ?? $venda['data_venda'],
                'mes_referencia' => $mes_referencia,
                'arquivo_csv_id' => $arquivo_id
            ];

            if ($aqdb->insert(TABLE_VENDAS, $dados_insert)) {
                $sucesso++;
            } else {
                $erros++;
                echo "<p style='color:orange;'>Erro ao inserir venda: {$venda['id']}</p>";
            }
        }

        // Atualiza status do arquivo
        $aqdb->update(TABLE_ARQUIVOS_CSV, [
            'status_import' => 'completo',
            'total_linhas' => $total_linhas,
            'total_processadas' => $sucesso,
            'total_ignoradas' => $erros,
            'data_processamento' => date('Y-m-d H:i:s')
        ], ['id' => $arquivo_id]);

        // Log da importação
        $tempo = microtime(true) - $inicio;
        $aqdb->insert(TABLE_LOG_IMPORTS, [
            'arquivo_csv_id' => $arquivo_id,
            'tipo_operacao' => 'import',
            'total_processados' => $total_linhas,
            'total_sucesso' => $sucesso,
            'total_erros' => $erros,
            'tempo_execucao' => round($tempo, 2),
            'detalhes' => json_encode([
                'ignorados' => $resultado['log_ignorados']
            ])
        ]);

        $aqdb->commit();

        echo "<p style='color:green;'><strong>✓ Importação concluída!</strong></p>";
        echo "<p>Sucesso: {$sucesso} | Erros: {$erros}</p>";
        echo "<p>Tempo: " . round($tempo, 2) . " segundos</p>";

        return [
            'sucesso' => true,
            'arquivo_id' => $arquivo_id,
            'total' => $total_linhas,
            'sucesso_count' => $sucesso,
            'erros_count' => $erros
        ];

    } catch (Exception $e) {
        $aqdb->rollback();

        // Atualiza status do arquivo
        $aqdb->update(TABLE_ARQUIVOS_CSV, [
            'status_import' => 'erro'
        ], ['id' => $arquivo_id]);

        echo "<p style='color:red;'>✗ Erro na importação: " . $e->getMessage() . "</p>";

        return [
            'sucesso' => false,
            'erro' => $e->getMessage()
        ];
    }
}

/**
 * Importa arquivo CSV de promotores para o banco
 */
function importarPromotoresParaBanco($caminho_csv, $nome_amigavel = null) {
    global $aqdb;

    $inicio = microtime(true);

    echo "<h3>Importando Promotores do CSV...</h3>";
    echo "<p>Arquivo: " . basename($caminho_csv) . "</p>";

    // Registra o arquivo
    $slug = $nome_amigavel ? gerarSlugArquivo($nome_amigavel) : null;

    $arquivo_id = $aqdb->insert(TABLE_ARQUIVOS_CSV, [
        'nome_arquivo' => basename($caminho_csv),
        'nome_amigavel' => $nome_amigavel,
        'slug' => $slug,
        'tipo' => 'promotores',
        'caminho' => $caminho_csv,
        'tamanho_bytes' => filesize($caminho_csv),
        'status_import' => 'processando',
        'data_upload' => date('Y-m-d H:i:s')
    ]);

    // Processa o CSV
    $resultado = processarPromotoresCSV($caminho_csv);

    if (!$resultado || empty($resultado['promotores'])) {
        echo "<p style='color:red;'>Erro ao processar CSV de promotores!</p>";
        return false;
    }

    $total_linhas = count($resultado['promotores']);
    $sucesso = 0;
    $erros = 0;

    echo "<p>Total de promotores no CSV: {$total_linhas}</p>";

    $aqdb->begin_transaction();

    try {
        foreach ($resultado['promotores'] as $promotor) {
            $dados_insert = [
                'nome' => $promotor['nome'],
                'cpf' => $promotor['cpf'] ?? null,
                'telefone' => $promotor['telefone'] ?? null,
                'email' => $promotor['email'] ?? null,
                'gerente' => $promotor['gerente'] ?? null,
                'status' => $promotor['status'] ?? 'Ativo',
                'arquivo_csv_id' => $arquivo_id
            ];

            // Verifica se já existe pelo CPF
            if (!empty($promotor['cpf'])) {
                $existe = $aqdb->get_var("SELECT id FROM " . TABLE_PROMOTORES . " WHERE cpf = '{$promotor['cpf']}'");
                if ($existe) {
                    // Atualiza
                    unset($dados_insert['arquivo_csv_id']); // Mantém o ID original
                    $aqdb->update(TABLE_PROMOTORES, $dados_insert, ['id' => $existe]);
                    $sucesso++;
                    continue;
                }
            }

            if ($aqdb->insert(TABLE_PROMOTORES, $dados_insert)) {
                $sucesso++;
            } else {
                $erros++;
            }
        }

        // Atualiza status do arquivo
        $aqdb->update(TABLE_ARQUIVOS_CSV, [
            'status_import' => 'completo',
            'total_linhas' => $total_linhas,
            'total_processadas' => $sucesso,
            'total_ignoradas' => $erros,
            'data_processamento' => date('Y-m-d H:i:s')
        ], ['id' => $arquivo_id]);

        $tempo = microtime(true) - $inicio;
        $aqdb->commit();

        echo "<p style='color:green;'><strong>✓ Importação concluída!</strong></p>";
        echo "<p>Sucesso: {$sucesso} | Erros: {$erros}</p>";
        echo "<p>Tempo: " . round($tempo, 2) . " segundos</p>";

        return [
            'sucesso' => true,
            'arquivo_id' => $arquivo_id,
            'total' => $total_linhas,
            'sucesso_count' => $sucesso,
            'erros_count' => $erros
        ];

    } catch (Exception $e) {
        $aqdb->rollback();
        echo "<p style='color:red;'>✗ Erro: " . $e->getMessage() . "</p>";

        return [
            'sucesso' => false,
            'erro' => $e->getMessage()
        ];
    }
}

/**
 * Remove todas as vendas de um mês específico (para re-importação)
 */
function limparVendasMes($mes_referencia) {
    global $aqdb;

    $count = $aqdb->get_var("SELECT COUNT(*) FROM " . TABLE_VENDAS . " WHERE mes_referencia = '$mes_referencia'");

    if ($count > 0) {
        $aqdb->query("DELETE FROM " . TABLE_VENDAS . " WHERE mes_referencia = '$mes_referencia'");
        echo "<p style='color:orange;'>✓ Removidas {$count} vendas do mês {$mes_referencia}</p>";
        return $count;
    }

    return 0;
}

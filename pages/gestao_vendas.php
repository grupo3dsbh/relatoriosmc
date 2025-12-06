<?php
// pages/gestao_vendas.php - Gestão Administrativa de Vendas com Filtros Avançados

// Verifica autenticação admin
if (!verificarAdmin()):
    echo '<div class="alert alert-danger">Acesso negado! Apenas administradores podem acessar esta página.</div>';
    return;
endif;

// Tenta carregar banco de dados
$usar_banco = false;
if (file_exists(BASE_DIR . '/database/queries.php')) {
    require_once BASE_DIR . '/database/queries.php';
    $usar_banco = bancoDadosDisponivel();
}

// ===== PROCESSAMENTO DE EXPORTAÇÃO =====
if (isset($_GET['exportar'])) {
    $formato = $_GET['exportar']; // csv, json, excel

    // Reaplica filtros para exportar
    $filtros_export = [
        'cpf' => $_GET['filtro_cpf'] ?? '',
        'titular' => $_GET['filtro_titular'] ?? '',
        'titulo_id' => $_GET['filtro_titulo_id'] ?? '',
        'status' => $_GET['filtro_status'] ?? '',
        'data_inicio' => $_GET['data_inicio'] ?? '',
        'data_fim' => $_GET['data_fim'] ?? '',
        'primeira_parcela_paga' => isset($_GET['filtro_primeira_parcela']),
        'produto_alterado' => isset($_GET['filtro_produto_alterado']),
        'forma_pagamento' => $_GET['filtro_forma_pagamento'] ?? '',
        'valor_pago_min' => $_GET['valor_pago_min'] ?? '',
        'valor_pago_max' => $_GET['valor_pago_max'] ?? '',
        'apenas_duplicadas' => isset($_GET['apenas_duplicadas'])
    ];

    // Busca vendas com filtros
    if ($usar_banco) {
        $vendas_export = buscarVendasAvancado($filtros_export);
    } else {
        $arquivos = listarCSVs('vendas');
        $vendas_export = [];
        foreach ($arquivos as $arquivo) {
            $resultado = processarVendasCSV($arquivo['caminho']);
            $vendas_export = array_merge($vendas_export, $resultado['vendas']);
        }
        $vendas_export = aplicarFiltrosAvancados($vendas_export, $filtros_export);
    }

    exportarVendas($vendas_export, $formato);
    exit;
}

// ===== CARREGA VENDAS =====
$vendas_data = [];
$duplicidades = [];
$total_sem_filtro = 0;

// Aplica filtros
$filtros = [
    'cpf' => $_GET['filtro_cpf'] ?? '',
    'titular' => $_GET['filtro_titular'] ?? '',
    'titulo_id' => $_GET['filtro_titulo_id'] ?? '',
    'status' => $_GET['filtro_status'] ?? '',
    'data_inicio' => $_GET['data_inicio'] ?? '',
    'data_fim' => $_GET['data_fim'] ?? '',
    'primeira_parcela_paga' => isset($_GET['filtro_primeira_parcela']),
    'produto_alterado' => isset($_GET['filtro_produto_alterado']),
    'forma_pagamento' => $_GET['filtro_forma_pagamento'] ?? '',
    'valor_pago_min' => $_GET['valor_pago_min'] ?? '',
    'valor_pago_max' => $_GET['valor_pago_max'] ?? '',
    'apenas_duplicadas' => isset($_GET['apenas_duplicadas'])
];

if ($usar_banco) {
    // Busca do banco de dados (TODOS OS MESES)
    $vendas_data = buscarVendasAvancado($filtros);

    // Conta total sem filtro
    global $aqdb;
    $total_sem_filtro = (int) $aqdb->get_var("SELECT COUNT(*) FROM " . TABLE_VENDAS);

} else {
    // Busca de CSV (todos os arquivos disponíveis)
    $arquivos_vendas = listarCSVs('vendas');
    $todas_vendas = [];

    foreach ($arquivos_vendas as $arquivo) {
        $vendas_processadas = processarVendasCSV($arquivo['caminho']);
        $todas_vendas = array_merge($todas_vendas, $vendas_processadas['vendas']);
    }

    $total_sem_filtro = count($todas_vendas);
    $vendas_data = aplicarFiltrosAvancados($todas_vendas, $filtros);
}

// Detecta duplicidades
$duplicidades = detectarDuplicidadesPorCPF($vendas_data);

// Ordena por CPF e prioriza vendas principais
usort($vendas_data, function($a, $b) use ($duplicidades) {
    $cpf_a = preg_replace('/[^0-9]/', '', $a['cpf'] ?? '');
    $cpf_b = preg_replace('/[^0-9]/', '', $b['cpf'] ?? '');

    // Primeiro ordena por CPF
    $cmp_cpf = strcmp($cpf_a, $cpf_b);
    if ($cmp_cpf !== 0) {
        return $cmp_cpf;
    }

    // Mesmo CPF: verifica se alguma é principal
    $a_principal = false;
    $b_principal = false;

    if (isset($duplicidades[$cpf_a])) {
        foreach ($duplicidades[$cpf_a] as $v) {
            if ($v['id'] === $a['id'] && isset($v['e_principal'])) {
                $a_principal = true;
            }
            if ($v['id'] === $b['id'] && isset($v['e_principal'])) {
                $b_principal = true;
            }
        }
    }

    // Principal vem primeiro
    if ($a_principal && !$b_principal) return -1;
    if (!$a_principal && $b_principal) return 1;

    // Se ambas são principais ou nenhuma é, mantém ordem original (por data)
    return strtotime($b['data_venda'] ?? $b['data_cadastro']) - strtotime($a['data_venda'] ?? $a['data_cadastro']);
});

// ===== FUNÇÕES AUXILIARES =====

/**
 * Busca vendas do banco com filtros avançados
 */
function buscarVendasAvancado($filtros) {
    global $aqdb;

    $where = ["1=1"];
    $params = [];

    // Filtro CPF
    if (!empty($filtros['cpf'])) {
        $cpf_limpo = preg_replace('/[^0-9]/', '', $filtros['cpf']);
        $where[] = "cpf_limpo LIKE %s";
        $params[] = '%' . $cpf_limpo . '%';
    }

    // Filtro Titular
    if (!empty($filtros['titular'])) {
        $where[] = "titular LIKE %s";
        $params[] = '%' . $filtros['titular'] . '%';
    }

    // Filtro ID da Cota
    if (!empty($filtros['titulo_id'])) {
        $where[] = "titulo_id LIKE %s";
        $params[] = '%' . $filtros['titulo_id'] . '%';
    }

    // Filtro Status
    if (!empty($filtros['status'])) {
        $where[] = "status = %s";
        $params[] = $filtros['status'];
    }

    // Filtro Data Início
    if (!empty($filtros['data_inicio'])) {
        $where[] = "data_venda >= %s";
        $params[] = $filtros['data_inicio'] . ' 00:00:00';
    }

    // Filtro Data Fim
    if (!empty($filtros['data_fim'])) {
        $where[] = "data_venda <= %s";
        $params[] = $filtros['data_fim'] . ' 23:59:59';
    }

    // Filtro Primeira Parcela Paga
    if ($filtros['primeira_parcela_paga']) {
        $where[] = "primeira_parcela_paga = 1";
    }

    // Filtro Produto Alterado
    if ($filtros['produto_alterado']) {
        $where[] = "produto_alterado = 1";
    }

    // Filtro Forma Pagamento
    if (!empty($filtros['forma_pagamento'])) {
        $where[] = "forma_pagamento LIKE %s";
        $params[] = '%' . $filtros['forma_pagamento'] . '%';
    }

    // Filtro Valor Pago Mínimo
    if (!empty($filtros['valor_pago_min'])) {
        $where[] = "valor_pago >= %f";
        $params[] = (float) $filtros['valor_pago_min'];
    }

    // Filtro Valor Pago Máximo
    if (!empty($filtros['valor_pago_max'])) {
        $where[] = "valor_pago <= %f";
        $params[] = (float) $filtros['valor_pago_max'];
    }

    $where_sql = implode(' AND ', $where);

    // Monta query
    $query = "SELECT * FROM " . TABLE_VENDAS . " WHERE $where_sql ORDER BY cpf_limpo, data_venda DESC";

    // Prepara query se houver parâmetros
    if (!empty($params)) {
        $query = $aqdb->prepare($query, ...$params);
    }

    $vendas_db = $aqdb->get_results($query, ARRAY_A);

    // Converte para formato compatível
    $vendas = [];
    foreach ($vendas_db as $venda_db) {
        // Converte tipos booleanos
        $venda_db['e_vista'] = (bool) $venda_db['e_vista'];
        $venda_db['primeira_parcela_paga'] = (bool) $venda_db['primeira_parcela_paga'];
        $venda_db['produto_alterado'] = (bool) $venda_db['produto_alterado'];

        // Renomeia campo titulo_id para id (compatibilidade)
        $venda_db['id'] = $venda_db['titulo_id'];

        $vendas[] = $venda_db;
    }

    return $vendas;
}

/**
 * Aplica filtros avançados em vendas do CSV
 */
function aplicarFiltrosAvancados($vendas, $filtros) {
    $resultado = $vendas;

    // Filtro CPF
    if (!empty($filtros['cpf'])) {
        $cpf_busca = preg_replace('/[^0-9]/', '', $filtros['cpf']);
        $resultado = array_filter($resultado, function($v) use ($cpf_busca) {
            $cpf = preg_replace('/[^0-9]/', '', $v['cpf'] ?? '');
            return strpos($cpf, $cpf_busca) !== false;
        });
    }

    // Filtro Titular
    if (!empty($filtros['titular'])) {
        $resultado = array_filter($resultado, function($v) use ($filtros) {
            return stripos($v['titular'] ?? '', $filtros['titular']) !== false;
        });
    }

    // Filtro ID da Cota
    if (!empty($filtros['titulo_id'])) {
        $resultado = array_filter($resultado, function($v) use ($filtros) {
            return stripos($v['id'] ?? '', $filtros['titulo_id']) !== false;
        });
    }

    // Filtro Status
    if (!empty($filtros['status'])) {
        $resultado = array_filter($resultado, function($v) use ($filtros) {
            return strcasecmp($v['status'] ?? '', $filtros['status']) === 0;
        });
    }

    // Filtro Data Início
    if (!empty($filtros['data_inicio'])) {
        $data_inicio = strtotime($filtros['data_inicio']);
        $resultado = array_filter($resultado, function($v) use ($data_inicio) {
            $data_venda = strtotime($v['data_venda'] ?? $v['data_cadastro']);
            return $data_venda >= $data_inicio;
        });
    }

    // Filtro Data Fim
    if (!empty($filtros['data_fim'])) {
        $data_fim = strtotime($filtros['data_fim'] . ' 23:59:59');
        $resultado = array_filter($resultado, function($v) use ($data_fim) {
            $data_venda = strtotime($v['data_venda'] ?? $v['data_cadastro']);
            return $data_venda <= $data_fim;
        });
    }

    // Filtro Primeira Parcela Paga
    if ($filtros['primeira_parcela_paga']) {
        $resultado = array_filter($resultado, function($v) {
            return !empty($v['primeira_parcela_paga']) || (!empty($v['valor_pago']) && $v['valor_pago'] > 0);
        });
    }

    // Filtro Produto Alterado
    if ($filtros['produto_alterado']) {
        $resultado = array_filter($resultado, function($v) {
            return !empty($v['produto_alterado']);
        });
    }

    // Filtro Forma Pagamento
    if (!empty($filtros['forma_pagamento'])) {
        $resultado = array_filter($resultado, function($v) use ($filtros) {
            return stripos($v['forma_pagamento'] ?? '', $filtros['forma_pagamento']) !== false;
        });
    }

    // Filtro Valor Pago Mínimo
    if (!empty($filtros['valor_pago_min'])) {
        $resultado = array_filter($resultado, function($v) use ($filtros) {
            return ($v['valor_pago'] ?? 0) >= (float) $filtros['valor_pago_min'];
        });
    }

    // Filtro Valor Pago Máximo
    if (!empty($filtros['valor_pago_max'])) {
        $resultado = array_filter($resultado, function($v) use ($filtros) {
            return ($v['valor_pago'] ?? 0) <= (float) $filtros['valor_pago_max'];
        });
    }

    return array_values($resultado);
}

/**
 * Detecta vendas duplicadas pelo CPF do titular
 */
function detectarDuplicidadesPorCPF($vendas) {
    $por_cpf = [];

    foreach ($vendas as $venda) {
        $cpf = preg_replace('/[^0-9]/', '', $venda['cpf'] ?? '');
        if (empty($cpf)) continue;

        if (!isset($por_cpf[$cpf])) {
            $por_cpf[$cpf] = [];
        }
        $por_cpf[$cpf][] = $venda;
    }

    // Filtra apenas CPFs com múltiplas vendas
    $duplicados = array_filter($por_cpf, function($vendas) {
        return count($vendas) > 1;
    });

    // Para cada grupo de duplicados, marca qual tem prioridade (primeira parcela paga)
    foreach ($duplicados as $cpf => &$grupo) {
        usort($grupo, function($a, $b) {
            // Prioriza: primeira parcela paga > data mais recente
            $a_pago = !empty($a['valor_pago']) && $a['valor_pago'] > 0;
            $b_pago = !empty($b['valor_pago']) && $b['valor_pago'] > 0;

            if ($a_pago != $b_pago) {
                return $b_pago - $a_pago; // Pago vem primeiro
            }

            $data_a = strtotime($a['data_venda'] ?? $a['data_cadastro']);
            $data_b = strtotime($b['data_venda'] ?? $b['data_cadastro']);
            return $data_b - $data_a; // Mais recente primeiro
        });

        // Marca a primeira como principal
        $grupo[0]['e_principal'] = true;
    }

    return $duplicados;
}

/**
 * Exporta vendas em diferentes formatos
 */
function exportarVendas($vendas, $formato) {
    $nome_arquivo = 'vendas_' . date('Y-m-d_His');

    switch ($formato) {
        case 'csv':
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $nome_arquivo . '.csv"');

            $output = fopen('php://output', 'w');

            // BOM UTF-8 para Excel abrir corretamente
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            // Cabeçalho
            fputcsv($output, [
                'ID', 'Data Cadastro', 'Data Venda', 'Titular', 'CPF',
                'Produto Original', 'Produto Atual', 'Produto Alterado',
                'Consultor', 'Gerente', 'Status', 'Forma Pagamento',
                'Valor Total', 'Valor Pago', 'Valor Restante',
                'Parcelas', 'Parcelas Pagas', 'Primeira Parcela Paga',
                'Telefone', 'Origem Venda'
            ], ';');

            // Dados
            foreach ($vendas as $v) {
                fputcsv($output, [
                    $v['id'] ?? $v['titulo_id'],
                    date('d/m/Y H:i', strtotime($v['data_cadastro'])),
                    date('d/m/Y H:i', strtotime($v['data_venda'] ?? $v['data_cadastro'])),
                    $v['titular'],
                    $v['cpf'],
                    $v['produto_original'],
                    $v['produto_atual'],
                    ($v['produto_alterado'] ?? false) ? 'Sim' : 'Não',
                    $v['consultor'],
                    $v['gerente'] ?? '',
                    $v['status'],
                    $v['forma_pagamento'] ?? '',
                    number_format($v['valor_total'], 2, ',', '.'),
                    number_format($v['valor_pago'], 2, ',', '.'),
                    number_format($v['valor_restante'], 2, ',', '.'),
                    $v['quantidade_parcelas_venda'] ?? 0,
                    $v['parcelas_pagas'] ?? 0,
                    ($v['primeira_parcela_paga'] ?? false) ? 'Sim' : 'Não',
                    $v['telefone'] ?? '',
                    $v['origem_venda'] ?? ''
                ], ';');
            }

            fclose($output);
            break;

        case 'json':
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $nome_arquivo . '.json"');

            echo json_encode([
                'exportacao' => date('Y-m-d H:i:s'),
                'total' => count($vendas),
                'vendas' => $vendas
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;

        case 'excel':
            // Formato HTML que Excel consegue abrir
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $nome_arquivo . '.xls"');

            echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
            echo '<head><meta charset="UTF-8"></head>';
            echo '<body>';
            echo '<table border="1">';
            echo '<thead><tr>';
            echo '<th>ID</th><th>Data Cadastro</th><th>Data Venda</th><th>Titular</th><th>CPF</th>';
            echo '<th>Produto Original</th><th>Produto Atual</th><th>Produto Alterado</th>';
            echo '<th>Consultor</th><th>Gerente</th><th>Status</th><th>Forma Pagamento</th>';
            echo '<th>Valor Total</th><th>Valor Pago</th><th>Valor Restante</th>';
            echo '<th>Parcelas</th><th>Parcelas Pagas</th><th>Primeira Parcela Paga</th>';
            echo '<th>Telefone</th><th>Origem Venda</th>';
            echo '</tr></thead><tbody>';

            foreach ($vendas as $v) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($v['id'] ?? $v['titulo_id']) . '</td>';
                echo '<td>' . date('d/m/Y H:i', strtotime($v['data_cadastro'])) . '</td>';
                echo '<td>' . date('d/m/Y H:i', strtotime($v['data_venda'] ?? $v['data_cadastro'])) . '</td>';
                echo '<td>' . htmlspecialchars($v['titular']) . '</td>';
                echo '<td>' . htmlspecialchars($v['cpf']) . '</td>';
                echo '<td>' . htmlspecialchars($v['produto_original']) . '</td>';
                echo '<td>' . htmlspecialchars($v['produto_atual']) . '</td>';
                echo '<td>' . (($v['produto_alterado'] ?? false) ? 'Sim' : 'Não') . '</td>';
                echo '<td>' . htmlspecialchars($v['consultor']) . '</td>';
                echo '<td>' . htmlspecialchars($v['gerente'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($v['status']) . '</td>';
                echo '<td>' . htmlspecialchars($v['forma_pagamento'] ?? '') . '</td>';
                echo '<td>' . number_format($v['valor_total'], 2, ',', '.') . '</td>';
                echo '<td>' . number_format($v['valor_pago'], 2, ',', '.') . '</td>';
                echo '<td>' . number_format($v['valor_restante'], 2, ',', '.') . '</td>';
                echo '<td>' . ($v['quantidade_parcelas_venda'] ?? 0) . '</td>';
                echo '<td>' . ($v['parcelas_pagas'] ?? 0) . '</td>';
                echo '<td>' . (($v['primeira_parcela_paga'] ?? false) ? 'Sim' : 'Não') . '</td>';
                echo '<td>' . htmlspecialchars($v['telefone'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($v['origem_venda'] ?? '') . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '</body></html>';
            break;
    }
}

?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h4 class="mb-0">
                    <i class="fas fa-tasks"></i> Gestão Avançada de Vendas
                    <?php if ($usar_banco): ?>
                        <span class="badge badge-success">📊 Banco de Dados</span>
                    <?php else: ?>
                        <span class="badge badge-secondary">📄 CSV</span>
                    <?php endif; ?>
                </h4>
            </div>
            <div class="card-body">

                <!-- Estatísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h3><?= number_format($total_sem_filtro) ?></h3>
                                <p class="mb-0">Total de Vendas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h3><?= count($duplicidades) ?></h3>
                                <p class="mb-0">CPFs Duplicados</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <?php
                                $total_principais = 0;
                                foreach ($duplicidades as $grupo) {
                                    foreach ($grupo as $v) {
                                        if (isset($v['e_principal'])) $total_principais++;
                                    }
                                }
                                ?>
                                <h3><?= $total_principais ?></h3>
                                <p class="mb-0">Vendas Principais</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h3><?= count($vendas_data) ?></h3>
                                <p class="mb-0">Resultados Filtrados</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botões de Exportação -->
                <div class="mb-3 text-right">
                    <div class="btn-group" role="group">
                        <a href="?page=gestao_vendas&exportar=csv&<?= http_build_query(array_filter($filtros)) ?>"
                           class="btn btn-success" title="Exportar como CSV">
                            <i class="fas fa-file-csv"></i> Exportar CSV
                        </a>
                        <a href="?page=gestao_vendas&exportar=excel&<?= http_build_query(array_filter($filtros)) ?>"
                           class="btn btn-primary" title="Exportar como Excel">
                            <i class="fas fa-file-excel"></i> Exportar Excel
                        </a>
                        <a href="?page=gestao_vendas&exportar=json&<?= http_build_query(array_filter($filtros)) ?>"
                           class="btn btn-secondary" title="Exportar como JSON">
                            <i class="fas fa-file-code"></i> Exportar JSON
                        </a>
                    </div>
                </div>

                <!-- Filtros Avançados -->
                <div class="card mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-filter"></i> Filtros Avançados
                            <?php if ($usar_banco): ?>
                                <small class="float-right">Busca em todos os meses do banco de dados</small>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="get" id="formFiltros">
                            <input type="hidden" name="page" value="gestao_vendas">

                            <div class="row">
                                <!-- Linha 1 -->
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label><i class="fas fa-id-card"></i> CPF</label>
                                        <input type="text" class="form-control form-control-sm" name="filtro_cpf"
                                               placeholder="000.000.000-00"
                                               value="<?= htmlspecialchars($filtros['cpf']) ?>">
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label><i class="fas fa-user"></i> Nome Cliente</label>
                                        <input type="text" class="form-control form-control-sm" name="filtro_titular"
                                               placeholder="Nome do titular"
                                               value="<?= htmlspecialchars($filtros['titular']) ?>">
                                    </div>
                                </div>

                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label><i class="fas fa-ticket-alt"></i> ID da Cota</label>
                                        <input type="text" class="form-control form-control-sm" name="filtro_titulo_id"
                                               placeholder="SFA-12345"
                                               value="<?= htmlspecialchars($filtros['titulo_id']) ?>">
                                    </div>
                                </div>

                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label><i class="fas fa-flag"></i> Status</label>
                                        <select class="form-control form-control-sm" name="filtro_status">
                                            <option value="">Todos</option>
                                            <option value="Ativo" <?= $filtros['status'] === 'Ativo' ? 'selected' : '' ?>>Ativo</option>
                                            <option value="Desativado" <?= $filtros['status'] === 'Desativado' ? 'selected' : '' ?>>Desativado</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label><i class="fas fa-credit-card"></i> Forma Pagamento</label>
                                        <input type="text" class="form-control form-control-sm" name="filtro_forma_pagamento"
                                               placeholder="PIX, Boleto, etc"
                                               value="<?= htmlspecialchars($filtros['forma_pagamento']) ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <!-- Linha 2 -->
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label><i class="fas fa-calendar"></i> Data Início</label>
                                        <input type="date" class="form-control form-control-sm" name="data_inicio"
                                               value="<?= htmlspecialchars($filtros['data_inicio']) ?>">
                                    </div>
                                </div>

                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label><i class="fas fa-calendar"></i> Data Fim</label>
                                        <input type="date" class="form-control form-control-sm" name="data_fim"
                                               value="<?= htmlspecialchars($filtros['data_fim']) ?>">
                                    </div>
                                </div>

                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label><i class="fas fa-money-bill"></i> Valor Pago Mín.</label>
                                        <input type="number" class="form-control form-control-sm" name="valor_pago_min"
                                               step="0.01" placeholder="0.00"
                                               value="<?= htmlspecialchars($filtros['valor_pago_min']) ?>">
                                    </div>
                                </div>

                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label><i class="fas fa-money-bill"></i> Valor Pago Máx.</label>
                                        <input type="number" class="form-control form-control-sm" name="valor_pago_max"
                                               step="0.01" placeholder="0.00"
                                               value="<?= htmlspecialchars($filtros['valor_pago_max']) ?>">
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <div>
                                            <div class="custom-control custom-checkbox custom-control-inline">
                                                <input type="checkbox" class="custom-control-input"
                                                       id="filtro_primeira_parcela" name="filtro_primeira_parcela"
                                                       <?= $filtros['primeira_parcela_paga'] ? 'checked' : '' ?>>
                                                <label class="custom-control-label" for="filtro_primeira_parcela">
                                                    <small>1ª Parcela Paga</small>
                                                </label>
                                            </div>
                                            <div class="custom-control custom-checkbox custom-control-inline">
                                                <input type="checkbox" class="custom-control-input"
                                                       id="filtro_produto_alterado" name="filtro_produto_alterado"
                                                       <?= $filtros['produto_alterado'] ? 'checked' : '' ?>>
                                                <label class="custom-control-label" for="filtro_produto_alterado">
                                                    <small>Produto Alterado</small>
                                                </label>
                                            </div>
                                            <div class="custom-control custom-checkbox custom-control-inline">
                                                <input type="checkbox" class="custom-control-input"
                                                       id="apenas_duplicadas" name="apenas_duplicadas"
                                                       <?= $filtros['apenas_duplicadas'] ? 'checked' : '' ?>>
                                                <label class="custom-control-label" for="apenas_duplicadas">
                                                    <small>Apenas Duplicadas</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 text-right">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Filtrar
                                    </button>
                                    <a href="?page=gestao_vendas" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Limpar Filtros
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabela de Vendas -->
                <div class="table-responsive">
                    <table class="table table-hover table-sm table-bordered" id="tabelaGestaoVendas">
                        <thead class="thead-dark">
                            <tr>
                                <th>ID</th>
                                <th>Data Cadastro</th>
                                <th>Data Venda</th>
                                <th>Titular</th>
                                <th>CPF</th>
                                <th>Produto Atual</th>
                                <th>Produto Original</th>
                                <th>Consultor</th>
                                <th>Status</th>
                                <th>Forma Pgto</th>
                                <th class="text-right">Valor Pago</th>
                                <th class="text-right">Valor Total</th>
                                <th class="text-center">Duplicidade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($vendas_data)): ?>
                                <tr>
                                    <td colspan="13" class="text-center">
                                        Nenhuma venda encontrada com os filtros aplicados.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($vendas_data as $venda):
                                    $cpf_limpo = preg_replace('/[^0-9]/', '', $venda['cpf'] ?? '');
                                    $e_duplicada = isset($duplicidades[$cpf_limpo]);
                                    $e_principal = false;

                                    if ($e_duplicada) {
                                        foreach ($duplicidades[$cpf_limpo] as $v) {
                                            if ($v['id'] === $venda['id'] && isset($v['e_principal'])) {
                                                $e_principal = true;
                                                break;
                                            }
                                        }
                                    }

                                    $produto_alterado = !empty($venda['produto_alterado']);

                                    $classe_linha = '';
                                    if ($e_principal) {
                                        $classe_linha = 'table-success';
                                    } elseif ($e_duplicada) {
                                        $classe_linha = 'table-warning';
                                    }
                                ?>
                                <tr class="<?= $classe_linha ?>">
                                    <td><small><?= htmlspecialchars($venda['id'] ?? $venda['titulo_id']) ?></small></td>
                                    <td><small><?= date('d/m/Y', strtotime($venda['data_cadastro'])) ?></small></td>
                                    <td><small><?= date('d/m/Y', strtotime($venda['data_venda'] ?? $venda['data_cadastro'])) ?></small></td>
                                    <td><small><?= htmlspecialchars($venda['titular']) ?></small></td>
                                    <td><small><?= htmlspecialchars($venda['cpf']) ?></small></td>
                                    <td>
                                        <small><?= htmlspecialchars($venda['produto_atual']) ?></small>
                                        <?php if ($produto_alterado): ?>
                                            <i class="fas fa-exclamation-triangle text-warning"
                                               title="Produto foi alterado"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?= htmlspecialchars($venda['produto_original']) ?></small></td>
                                    <td><small><?= htmlspecialchars($venda['consultor']) ?></small></td>
                                    <td>
                                        <span class="badge badge-<?= $venda['status'] === 'Ativo' ? 'success' : 'danger' ?> badge-sm">
                                            <?= $venda['status'] ?>
                                        </span>
                                    </td>
                                    <td><small><?= htmlspecialchars($venda['forma_pagamento'] ?? '-') ?></small></td>
                                    <td class="text-right">
                                        <small>R$ <?= number_format($venda['valor_pago'], 2, ',', '.') ?></small>
                                        <?php if (!empty($venda['primeira_parcela_paga']) || $venda['valor_pago'] > 0): ?>
                                            <i class="fas fa-check-circle text-success" title="1ª Parcela Paga"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right">
                                        <small>R$ <?= number_format($venda['valor_total'], 2, ',', '.') ?></small>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($e_principal): ?>
                                            <span class="badge badge-success">
                                                <i class="fas fa-star"></i> Principal
                                            </span>
                                        <?php elseif ($e_duplicada): ?>
                                            <span class="badge badge-warning">
                                                <i class="fas fa-copy"></i> Duplicada
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">
                                                <i class="fas fa-check"></i> Única
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Legenda -->
                <div class="alert alert-info mt-3">
                    <h6><i class="fas fa-info-circle"></i> Legenda</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <span class="badge badge-success">★ Principal</span> - Venda prioritária (1ª parcela paga ou mais recente)
                        </div>
                        <div class="col-md-3">
                            <span class="badge badge-warning">⧉ Duplicada</span> - CPF com múltiplas vendas
                        </div>
                        <div class="col-md-3">
                            <span class="badge badge-secondary">✓ Única</span> - CPF sem duplicidade
                        </div>
                        <div class="col-md-3">
                            <i class="fas fa-exclamation-triangle text-warning"></i> - Produto foi alterado
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#tabelaGestaoVendas').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json'
        },
        order: [[1, 'desc']], // Ordena por data cadastro decrescente
        pageLength: 50,
        responsive: true,
        dom: 'Bfrtip',
        buttons: []
    });
});
</script>

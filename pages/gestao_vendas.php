<?php
// pages/gestao_vendas.php - Gestão Administrativa de Vendas com Filtros Avançados

// ===== AUTENTICAÇÃO ESPECIAL PARA GESTÃO DE VENDAS =====
session_start();

// Processa login especial
if (isset($_POST['login_gestao'])) {
    if ($_POST['senha_gestao'] === 'Aqua@2021') {
        $_SESSION['acesso_gestao_vendas'] = true;
        header('Location: ?page=gestao_vendas');
        exit;
    } else {
        $erro_login = "Senha incorreta!";
    }
}

// Verifica autenticação (admin OU senha especial)
$tem_acesso = verificarAdmin() || (!empty($_SESSION['acesso_gestao_vendas']));

if (!$tem_acesso):
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Acesso - Gestão de Vendas</title>
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">🔐 Acesso - Gestão de Vendas</h5>
                        </div>
                        <div class="card-body">
                            <?php if (isset($erro_login)): ?>
                                <div class="alert alert-danger"><?= $erro_login ?></div>
                            <?php endif; ?>
                            <form method="post">
                                <div class="form-group">
                                    <label>Senha de Acesso:</label>
                                    <input type="password" name="senha_gestao" class="form-control" required autofocus>
                                </div>
                                <button type="submit" name="login_gestao" class="btn btn-warning btn-block">
                                    Acessar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    return;
endif;

// ===== PROCESSAMENTO DE EXPORTAÇÃO =====
if (isset($_GET['exportar'])) {
    $formato = $_GET['exportar'];

    // Reaplica filtros
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

    // Busca TODOS os arquivos CSV
    $arquivos = listarCSVs('vendas');
    $todas_vendas = [];

    foreach ($arquivos as $arquivo) {
        $resultado = processarVendasCSV($arquivo['caminho']);
        $todas_vendas = array_merge($todas_vendas, $resultado['vendas']);
    }

    $vendas_export = aplicarFiltrosAvancados($todas_vendas, $filtros_export);
    exportarVendas($vendas_export, $formato);
    exit;
}

// ===== CARREGA TODAS AS VENDAS DE TODOS OS CSVs =====
$arquivos_vendas = listarCSVs('vendas');
$todas_vendas = [];
$total_sem_filtro = 0;

if (!empty($arquivos_vendas)) {
    foreach ($arquivos_vendas as $arquivo) {
        $vendas_processadas = processarVendasCSV($arquivo['caminho']);
        $todas_vendas = array_merge($todas_vendas, $vendas_processadas['vendas']);
    }
}

$total_sem_filtro = count($todas_vendas);

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

$vendas_filtradas = aplicarFiltrosAvancados($todas_vendas, $filtros);

// Detecta duplicidades
$duplicidades = detectarDuplicidadesPorCPF($vendas_filtradas);

// Ordena por CPF
usort($vendas_filtradas, function($a, $b) use ($duplicidades) {
    $cpf_a = preg_replace('/[^0-9]/', '', $a['cpf'] ?? '');
    $cpf_b = preg_replace('/[^0-9]/', '', $b['cpf'] ?? '');

    $cmp_cpf = strcmp($cpf_a, $cpf_b);
    if ($cmp_cpf !== 0) return $cmp_cpf;

    return strtotime($b['data_cadastro']) - strtotime($a['data_cadastro']);
});

// ===== FUNÇÕES AUXILIARES =====

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
            return strtotime($v['data_cadastro']) >= $data_inicio;
        });
    }

    // Filtro Data Fim
    if (!empty($filtros['data_fim'])) {
        $data_fim = strtotime($filtros['data_fim'] . ' 23:59:59');
        $resultado = array_filter($resultado, function($v) use ($data_fim) {
            return strtotime($v['data_cadastro']) <= $data_fim;
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

    $duplicados = array_filter($por_cpf, function($vendas) {
        return count($vendas) > 1;
    });

    foreach ($duplicados as $cpf => &$grupo) {
        usort($grupo, function($a, $b) {
            $a_pago = !empty($a['valor_pago']) && $a['valor_pago'] > 0;
            $b_pago = !empty($b['valor_pago']) && $b['valor_pago'] > 0;

            if ($a_pago != $b_pago) {
                return $b_pago - $a_pago;
            }

            return strtotime($b['data_cadastro']) - strtotime($a['data_cadastro']);
        });

        $grupo[0]['e_principal'] = true;
    }

    return $duplicados;
}

function exportarVendas($vendas, $formato) {
    $nome_arquivo = 'vendas_' . date('Y-m-d_His');

    switch ($formato) {
        case 'csv':
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $nome_arquivo . '.csv"');

            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

            fputcsv($output, [
                'ID', 'Data Cadastro', 'Titular', 'CPF', 'Produto Original', 'Produto Atual',
                'Produto Alterado', 'Consultor', 'Status', 'Forma Pagamento',
                'Valor Total', 'Valor Pago', 'Valor Restante', 'Primeira Parcela Paga'
            ], ';');

            foreach ($vendas as $v) {
                fputcsv($output, [
                    $v['id'],
                    date('d/m/Y H:i', strtotime($v['data_cadastro'])),
                    $v['titular'],
                    $v['cpf'],
                    $v['produto_original'],
                    $v['produto_atual'],
                    ($v['produto_alterado'] ?? false) ? 'Sim' : 'Não',
                    $v['consultor'],
                    $v['status'],
                    $v['forma_pagamento'] ?? '',
                    number_format($v['valor_total'], 2, ',', '.'),
                    number_format($v['valor_pago'], 2, ',', '.'),
                    number_format($v['valor_restante'], 2, ',', '.'),
                    ($v['primeira_parcela_paga'] ?? false) ? 'Sim' : 'Não'
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
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $nome_arquivo . '.xls"');

            echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
            echo '<head><meta charset="UTF-8"></head><body>';
            echo '<table border="1"><thead><tr>';
            echo '<th>ID</th><th>Data Cadastro</th><th>Titular</th><th>CPF</th>';
            echo '<th>Produto Original</th><th>Produto Atual</th><th>Produto Alterado</th>';
            echo '<th>Consultor</th><th>Status</th><th>Forma Pagamento</th>';
            echo '<th>Valor Total</th><th>Valor Pago</th><th>Primeira Parcela</th>';
            echo '</tr></thead><tbody>';

            foreach ($vendas as $v) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($v['id']) . '</td>';
                echo '<td>' . date('d/m/Y H:i', strtotime($v['data_cadastro'])) . '</td>';
                echo '<td>' . htmlspecialchars($v['titular']) . '</td>';
                echo '<td>' . htmlspecialchars($v['cpf']) . '</td>';
                echo '<td>' . htmlspecialchars($v['produto_original']) . '</td>';
                echo '<td>' . htmlspecialchars($v['produto_atual']) . '</td>';
                echo '<td>' . (($v['produto_alterado'] ?? false) ? 'Sim' : 'Não') . '</td>';
                echo '<td>' . htmlspecialchars($v['consultor']) . '</td>';
                echo '<td>' . htmlspecialchars($v['status']) . '</td>';
                echo '<td>' . htmlspecialchars($v['forma_pagamento'] ?? '') . '</td>';
                echo '<td>' . number_format($v['valor_total'], 2, ',', '.') . '</td>';
                echo '<td>' . number_format($v['valor_pago'], 2, ',', '.') . '</td>';
                echo '<td>' . (($v['primeira_parcela_paga'] ?? false) ? 'Sim' : 'Não') . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table></body></html>';
            break;
    }
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gestão de Vendas - Aquabeat</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
</head>
<body>

<div class="container-fluid mt-4">
    <div class="card">
        <div class="card-header bg-warning text-dark">
            <h4 class="mb-0">
                <i class="fas fa-tasks"></i> Gestão Avançada de Vendas
                <span class="badge badge-secondary">📄 Todos os CSVs</span>
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
                            <h3><?= count($vendas_filtradas) ?></h3>
                            <p class="mb-0">Resultados Filtrados</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botões de Exportação -->
            <div class="mb-3 text-right">
                <div class="btn-group">
                    <a href="?page=gestao_vendas&exportar=csv&<?= http_build_query(array_filter($filtros)) ?>"
                       class="btn btn-success">
                        <i class="fas fa-file-csv"></i> CSV
                    </a>
                    <a href="?page=gestao_vendas&exportar=excel&<?= http_build_query(array_filter($filtros)) ?>"
                       class="btn btn-primary">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="?page=gestao_vendas&exportar=json&<?= http_build_query(array_filter($filtros)) ?>"
                       class="btn btn-secondary">
                        <i class="fas fa-file-code"></i> JSON
                    </a>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-filter"></i> Filtros Avançados</h5>
                </div>
                <div class="card-body">
                    <form method="get">
                        <input type="hidden" name="page" value="gestao_vendas">

                        <div class="row">
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
                                    <label><i class="fas fa-ticket-alt"></i> ID Cota</label>
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
                                    <label><i class="fas fa-credit-card"></i> Forma Pgto</label>
                                    <input type="text" class="form-control form-control-sm" name="filtro_forma_pagamento"
                                           placeholder="PIX, Boleto, etc"
                                           value="<?= htmlspecialchars($filtros['forma_pagamento']) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
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
                                    <label><i class="fas fa-money-bill"></i> Valor Pago Min</label>
                                    <input type="number" class="form-control form-control-sm" name="valor_pago_min"
                                           step="0.01" placeholder="0.00"
                                           value="<?= htmlspecialchars($filtros['valor_pago_min']) ?>">
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label><i class="fas fa-money-bill"></i> Valor Pago Max</label>
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
                                                <small>1ª Parcela</small>
                                            </label>
                                        </div>
                                        <div class="custom-control custom-checkbox custom-control-inline">
                                            <input type="checkbox" class="custom-control-input"
                                                   id="filtro_produto_alterado" name="filtro_produto_alterado"
                                                   <?= $filtros['produto_alterado'] ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="filtro_produto_alterado">
                                                <small>Alterado</small>
                                            </label>
                                        </div>
                                        <div class="custom-control custom-checkbox custom-control-inline">
                                            <input type="checkbox" class="custom-control-input"
                                                   id="apenas_duplicadas" name="apenas_duplicadas"
                                                   <?= $filtros['apenas_duplicadas'] ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="apenas_duplicadas">
                                                <small>Duplicadas</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-right">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filtrar
                            </button>
                            <a href="?page=gestao_vendas" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Limpar
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabela -->
            <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered" id="tabelaGestaoVendas">
                    <thead class="thead-dark">
                        <tr>
                            <th>ID</th>
                            <th>Data</th>
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
                        <?php if (empty($vendas_filtradas)): ?>
                            <tr>
                                <td colspan="12" class="text-center">Nenhuma venda encontrada.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($vendas_filtradas as $venda):
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
                                $classe_linha = $e_principal ? 'table-success' : ($e_duplicada ? 'table-warning' : '');
                            ?>
                            <tr class="<?= $classe_linha ?>">
                                <td><small><?= htmlspecialchars($venda['id']) ?></small></td>
                                <td><small><?= date('d/m/Y', strtotime($venda['data_cadastro'])) ?></small></td>
                                <td><small><?= htmlspecialchars($venda['titular']) ?></small></td>
                                <td><small><?= htmlspecialchars($venda['cpf']) ?></small></td>
                                <td>
                                    <small><?= htmlspecialchars($venda['produto_atual']) ?></small>
                                    <?php if ($produto_alterado): ?>
                                        <i class="fas fa-exclamation-triangle text-warning" title="Alterado"></i>
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
                                        <span class="badge badge-success"><i class="fas fa-star"></i> Principal</span>
                                    <?php elseif ($e_duplicada): ?>
                                        <span class="badge badge-warning"><i class="fas fa-copy"></i> Duplicada</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary"><i class="fas fa-check"></i> Única</span>
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
                    <div class="col-md-4">
                        <span class="badge badge-success">★ Principal</span> - Venda prioritária
                    </div>
                    <div class="col-md-4">
                        <span class="badge badge-warning">⧉ Duplicada</span> - CPF com múltiplas vendas
                    </div>
                    <div class="col-md-4">
                        <i class="fas fa-exclamation-triangle text-warning"></i> - Produto alterado
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    $('#tabelaGestaoVendas').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json'
        },
        order: [[1, 'desc']],
        pageLength: 50
    });
});
</script>

</body>
</html>

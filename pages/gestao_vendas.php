<?php
// pages/gestao_vendas.php - Gestão Administrativa de Vendas com Detecção de Duplicidades

// Verifica autenticação admin
if (!verificarAdmin()):
    echo '<div class="alert alert-danger">Acesso negado! Apenas administradores podem acessar esta página.</div>';
    return;
endif;

// Carrega arquivo de vendas
$arquivos_vendas = listarCSVs('vendas');
$vendas_data = [];
$duplicidades = [];

if (!empty($arquivos_vendas)) {
    $arquivo_selecionado = $_GET['arquivo'] ?? $arquivos_vendas[0]['caminho'];

    // Processa vendas
    $vendas_processadas = processarVendasCSV($arquivo_selecionado);
    $vendas_data = $vendas_processadas['vendas'];

    // Detecta duplicidades por CPF
    $duplicidades = detectarDuplicidadesPorCPF($vendas_data);
}

// Aplica filtros
$filtros = [
    'cpf' => $_GET['filtro_cpf'] ?? '',
    'status' => $_GET['filtro_status'] ?? '',
    'data_inicio' => $_GET['data_inicio'] ?? '',
    'data_fim' => $_GET['data_fim'] ?? '',
    'apenas_duplicadas' => isset($_GET['apenas_duplicadas'])
];

$vendas_filtradas = aplicarFiltrosGestao($vendas_data, $filtros, $duplicidades);

// Ordena por CPF e prioriza vendas principais
usort($vendas_filtradas, function($a, $b) use ($duplicidades) {
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
    return strtotime($b['data_venda']) - strtotime($a['data_venda']);
});

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

            return strtotime($b['data_venda']) - strtotime($a['data_venda']); // Mais recente primeiro
        });

        // Marca a primeira como principal
        $grupo[0]['e_principal'] = true;
    }

    return $duplicados;
}

/**
 * Aplica filtros nas vendas
 */
function aplicarFiltrosGestao($vendas, $filtros, $duplicidades) {
    $resultado = $vendas;

    // Filtro por CPF
    if (!empty($filtros['cpf'])) {
        $cpf_busca = preg_replace('/[^0-9]/', '', $filtros['cpf']);
        $resultado = array_filter($resultado, function($v) use ($cpf_busca) {
            $cpf = preg_replace('/[^0-9]/', '', $v['cpf'] ?? '');
            return strpos($cpf, $cpf_busca) !== false;
        });
    }

    // Filtro por status
    if (!empty($filtros['status'])) {
        $resultado = array_filter($resultado, function($v) use ($filtros) {
            return strcasecmp($v['status'] ?? '', $filtros['status']) === 0;
        });
    }

    // Filtro por data
    if (!empty($filtros['data_inicio'])) {
        $data_inicio = strtotime($filtros['data_inicio']);
        $resultado = array_filter($resultado, function($v) use ($data_inicio) {
            return strtotime($v['data_venda']) >= $data_inicio;
        });
    }

    if (!empty($filtros['data_fim'])) {
        $data_fim = strtotime($filtros['data_fim'] . ' 23:59:59');
        $resultado = array_filter($resultado, function($v) use ($data_fim) {
            return strtotime($v['data_venda']) <= $data_fim;
        });
    }

    // Filtro: apenas duplicadas
    if ($filtros['apenas_duplicadas']) {
        $cpfs_duplicados = array_keys($duplicidades);
        $resultado = array_filter($resultado, function($v) use ($cpfs_duplicados) {
            $cpf = preg_replace('/[^0-9]/', '', $v['cpf'] ?? '');
            return in_array($cpf, $cpfs_duplicados);
        });
    }

    return array_values($resultado);
}

?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h4 class="mb-0">
                    <i class="fas fa-tasks"></i> Gestão de Vendas - Controle de Duplicidades
                </h4>
            </div>
            <div class="card-body">

                <!-- Estatísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h3><?= count($vendas_data) ?></h3>
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

                <!-- Filtros -->
                <form method="get" class="mb-4">
                    <input type="hidden" name="page" value="gestao_vendas">

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><i class="fas fa-id-card"></i> CPF do Titular</label>
                                <input type="text" class="form-control" name="filtro_cpf"
                                       placeholder="000.000.000-00"
                                       value="<?= htmlspecialchars($filtros['cpf']) ?>">
                            </div>
                        </div>

                        <div class="col-md-2">
                            <div class="form-group">
                                <label><i class="fas fa-flag"></i> Status</label>
                                <select class="form-control" name="filtro_status">
                                    <option value="">Todos</option>
                                    <option value="Ativo" <?= $filtros['status'] === 'Ativo' ? 'selected' : '' ?>>Ativo</option>
                                    <option value="Desativado" <?= $filtros['status'] === 'Desativado' ? 'selected' : '' ?>>Desativado</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <div class="form-group">
                                <label><i class="fas fa-calendar"></i> Data Início</label>
                                <input type="date" class="form-control" name="data_inicio"
                                       value="<?= htmlspecialchars($filtros['data_inicio']) ?>">
                            </div>
                        </div>

                        <div class="col-md-2">
                            <div class="form-group">
                                <label><i class="fas fa-calendar"></i> Data Fim</label>
                                <input type="date" class="form-control" name="data_fim"
                                       value="<?= htmlspecialchars($filtros['data_fim']) ?>">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input"
                                           id="apenas_duplicadas" name="apenas_duplicadas"
                                           <?= $filtros['apenas_duplicadas'] ? 'checked' : '' ?>>
                                    <label class="custom-control-label" for="apenas_duplicadas">
                                        <i class="fas fa-copy"></i> Apenas Duplicadas
                                    </label>
                                </div>
                                <button type="submit" class="btn btn-primary btn-block mt-2">
                                    <i class="fas fa-search"></i> Filtrar
                                </button>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($filtros['cpf']) || !empty($filtros['status']) || !empty($filtros['data_inicio']) || $filtros['apenas_duplicadas']): ?>
                    <div class="text-right">
                        <a href="?page=gestao_vendas" class="btn btn-secondary btn-sm">
                            <i class="fas fa-times"></i> Limpar Filtros
                        </a>
                    </div>
                    <?php endif; ?>
                </form>

                <!-- Tabela de Vendas -->
                <div class="table-responsive">
                    <table class="table table-hover table-sm" id="tabelaGestaoVendas">
                        <thead class="thead-dark">
                            <tr>
                                <th>ID</th>
                                <th>Data Venda</th>
                                <th>Titular</th>
                                <th>CPF</th>
                                <th>Produto</th>
                                <th>Consultor</th>
                                <th>Status</th>
                                <th class="text-center">1ª Parcela</th>
                                <th class="text-center">Valor Total</th>
                                <th class="text-center">Duplicidade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($vendas_filtradas)): ?>
                                <tr>
                                    <td colspan="10" class="text-center">
                                        Nenhuma venda encontrada com os filtros aplicados.
                                    </td>
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

                                    $tem_primeira_parcela = !empty($venda['valor_pago']) && $venda['valor_pago'] > 0;

                                    $classe_linha = '';
                                    if ($e_principal) {
                                        $classe_linha = 'table-success';
                                    } elseif ($e_duplicada) {
                                        $classe_linha = 'table-warning';
                                    }
                                ?>
                                <tr class="<?= $classe_linha ?>">
                                    <td><small><?= htmlspecialchars($venda['id']) ?></small></td>
                                    <td><small><?= date('d/m/Y', strtotime($venda['data_venda'])) ?></small></td>
                                    <td><?= htmlspecialchars($venda['titular']) ?></td>
                                    <td><small><?= htmlspecialchars($venda['cpf']) ?></small></td>
                                    <td><small><?= htmlspecialchars($venda['produto_atual']) ?></small></td>
                                    <td><small><?= htmlspecialchars($venda['consultor']) ?></small></td>
                                    <td>
                                        <span class="badge badge-<?= $venda['status'] === 'Ativo' ? 'success' : 'danger' ?> badge-sm">
                                            <?= $venda['status'] ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($tem_primeira_parcela): ?>
                                            <i class="fas fa-check-circle text-success" title="Pago"></i>
                                            <small>R$ <?= number_format($venda['valor_pago'], 2, ',', '.') ?></small>
                                        <?php else: ?>
                                            <i class="fas fa-times-circle text-danger" title="Não pago"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
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
                                            <br>
                                            <small class="text-muted">
                                                <?= count($duplicidades[$cpf_limpo]) ?> vendas
                                            </small>
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
                        <div class="col-md-4">
                            <span class="badge badge-success">★ Principal</span> - Venda prioritária (1ª parcela paga ou mais recente)
                        </div>
                        <div class="col-md-4">
                            <span class="badge badge-warning">⧉ Duplicada</span> - CPF com múltiplas vendas
                        </div>
                        <div class="col-md-4">
                            <span class="badge badge-secondary">✓ Única</span> - CPF sem duplicidade
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
        order: [[1, 'desc']], // Ordena por data decrescente
        pageLength: 25,
        responsive: true
    });
});
</script>

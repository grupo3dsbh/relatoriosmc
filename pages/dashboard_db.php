<?php
// pages/dashboard_db.php - Dashboard de Estatísticas do Banco de Dados

// Verifica se banco está disponível
$banco_disponivel = false;
$mensagem_erro = null;

if (file_exists(BASE_DIR . '/database/queries.php')) {
    require_once BASE_DIR . '/database/queries.php';
    $banco_disponivel = bancoDadosDisponivel();
}

if (!$banco_disponivel) {
    $mensagem_erro = "Banco de dados não está configurado ou não está acessível!";
}

// Busca dados do banco
$meses_disponiveis = [];
$estatisticas_tabelas = [];
$logs_recentes = [];

if ($banco_disponivel) {
    global $aqdb;

    // Lista meses disponíveis
    $meses_disponiveis = listarMesesDisponiveis();

    // Estatísticas das tabelas
    $tabelas = [
        'vendas' => TABLE_VENDAS,
        'promotores' => TABLE_PROMOTORES,
        'pins' => TABLE_PINS,
        'usuarios' => TABLE_USUARIOS,
        'arquivos_csv' => TABLE_ARQUIVOS_CSV,
        'ranges' => TABLE_RANGES,
        'logs' => TABLE_LOG_IMPORTS
    ];

    foreach ($tabelas as $nome => $tabela) {
        $count = $aqdb->get_var("SELECT COUNT(*) FROM `$tabela`");
        $estatisticas_tabelas[$nome] = [
            'nome' => $nome,
            'tabela' => $tabela,
            'total' => (int) $count
        ];
    }

    // Logs de importação recentes
    $logs_recentes = $aqdb->get_results("
        SELECT l.*, a.nome_amigavel, a.tipo, a.mes_referencia
        FROM " . TABLE_LOG_IMPORTS . " l
        LEFT JOIN " . TABLE_ARQUIVOS_CSV . " a ON l.arquivo_csv_id = a.id
        ORDER BY l.created_at DESC
        LIMIT 10
    ", ARRAY_A);
}

// Verifica se é admin
$is_admin = isAdmin() || isGodMode();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Dashboard do Banco de Dados - Aquabeat</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .mes-card {
            border-left: 4px solid #007bff;
        }
        .mes-card:hover {
            background-color: #f8f9fa;
            cursor: pointer;
        }
    </style>
</head>
<body class="bg-light">

<div class="container mt-4">

    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-primary">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">
                        <i class="fas fa-database"></i> Dashboard do Banco de Dados
                    </h3>
                </div>
                <div class="card-body">
                    <?php if ($mensagem_erro): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?= $mensagem_erro ?>
                            <hr>
                            <p class="mb-0">
                                <strong>Para configurar o banco de dados:</strong><br>
                                1. Configure as credenciais em <code>database/config.php</code><br>
                                2. Crie o banco de dados MySQL<br>
                                3. Execute as migrations em <a href="database/migrations.php" class="alert-link">database/migrations.php</a>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> Banco de dados está <strong>ATIVO</strong> e funcionando corretamente!
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($banco_disponivel): ?>

    <!-- Ações Rápidas -->
    <?php if ($is_admin): ?>
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-tools"></i> Ações Rápidas</h5>
                </div>
                <div class="card-body text-center">
                    <a href="?page=admin#database" class="btn btn-primary btn-lg mr-2">
                        <i class="fas fa-upload"></i> Importar CSV
                    </a>
                    <a href="database/migrations.php?action=verificar" class="btn btn-info btn-lg mr-2">
                        <i class="fas fa-check"></i> Verificar Tabelas
                    </a>
                    <a href="database/migrations.php?action=exportar" class="btn btn-success btn-lg mr-2">
                        <i class="fas fa-download"></i> Exportar Dados (JSON)
                    </a>
                    <a href="database/migrations.php" class="btn btn-secondary btn-lg">
                        <i class="fas fa-cog"></i> Gerenciar Banco
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Estatísticas das Tabelas -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-table"></i> Estatísticas das Tabelas</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($estatisticas_tabelas as $stat): ?>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card border-info">
                                <div class="card-body text-center">
                                    <h6 class="text-muted"><?= strtoupper($stat['nome']) ?></h6>
                                    <h2 class="text-info"><?= number_format($stat['total'], 0, ',', '.') ?></h2>
                                    <small class="text-muted">registros</small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Meses Disponíveis -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> Meses Disponíveis no Banco</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($meses_disponiveis)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle"></i> Nenhum mês encontrado no banco de dados.
                            <?php if ($is_admin): ?>
                                <a href="?page=admin#database">Importe um arquivo CSV</a> para começar.
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($meses_disponiveis as $mes): ?>
                            <?php
                                $stats_mes = obterEstatisticasMes($mes['mes_referencia']);
                                $mes_formatado = date('F/Y', strtotime($mes['mes_referencia'] . '-01'));
                                $mes_pt = [
                                    'January' => 'Janeiro', 'February' => 'Fevereiro', 'March' => 'Março',
                                    'April' => 'Abril', 'May' => 'Maio', 'June' => 'Junho',
                                    'July' => 'Julho', 'August' => 'Agosto', 'September' => 'Setembro',
                                    'October' => 'Outubro', 'November' => 'Novembro', 'December' => 'Dezembro'
                                ];
                                $mes_formatado = str_replace(array_keys($mes_pt), array_values($mes_pt), $mes_formatado);
                            ?>
                            <div class="col-md-6 mb-3">
                                <div class="card mes-card">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="fas fa-calendar"></i> <?= $mes_formatado ?>
                                        </h5>
                                        <hr>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <strong>Vendas:</strong><br>
                                                <span class="badge badge-primary"><?= number_format($stats_mes['total_vendas']) ?></span>
                                            </div>
                                            <div class="col-md-4">
                                                <strong>Consultores:</strong><br>
                                                <span class="badge badge-info"><?= number_format($stats_mes['total_consultores']) ?></span>
                                            </div>
                                            <div class="col-md-4">
                                                <strong>Vagas:</strong><br>
                                                <span class="badge badge-success"><?= number_format($stats_mes['total_vagas']) ?></span>
                                            </div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-md-6">
                                                <strong>Valor Total:</strong><br>
                                                R$ <?= number_format($stats_mes['valor_total'], 2, ',', '.') ?>
                                            </div>
                                            <div class="col-md-6">
                                                <strong>Valor Pago:</strong><br>
                                                R$ <?= number_format($stats_mes['valor_pago'], 2, ',', '.') ?>
                                            </div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-md-12">
                                                <small class="text-muted">
                                                    <i class="fas fa-check-circle text-success"></i> Ativas: <?= $stats_mes['vendas_ativas'] ?> |
                                                    <i class="fas fa-money-bill-wave text-info"></i> À Vista: <?= $stats_mes['vendas_vista'] ?> |
                                                    <i class="fas fa-receipt text-warning"></i> 1ª Parcela: <?= $stats_mes['com_primeira_parcela'] ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Logs de Importação -->
    <?php if (!empty($logs_recentes)): ?>
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-history"></i> Logs de Importação Recentes</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Arquivo</th>
                                    <th>Tipo</th>
                                    <th>Mês Ref.</th>
                                    <th class="text-center">Processados</th>
                                    <th class="text-center">Sucesso</th>
                                    <th class="text-center">Erros</th>
                                    <th class="text-right">Tempo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs_recentes as $log): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
                                    <td>
                                        <small>
                                            <?= htmlspecialchars($log['nome_amigavel'] ?? 'N/A') ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $log['tipo'] === 'vendas' ? 'primary' : 'info' ?>">
                                            <?= $log['tipo'] ?>
                                        </span>
                                    </td>
                                    <td><?= $log['mes_referencia'] ?? '-' ?></td>
                                    <td class="text-center"><?= $log['total_processados'] ?></td>
                                    <td class="text-center">
                                        <span class="text-success"><?= $log['total_sucesso'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($log['total_erros'] > 0): ?>
                                            <span class="text-danger"><?= $log['total_erros'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right">
                                        <small><?= number_format($log['tempo_execucao'], 2) ?>s</small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; // fim if banco_disponivel ?>

    <!-- Botões de Navegação -->
    <div class="row mb-4">
        <div class="col-md-12 text-center">
            <a href="?page=home" class="btn btn-secondary btn-lg">
                <i class="fas fa-arrow-left"></i> Voltar ao Início
            </a>
            <?php if ($is_admin): ?>
                <a href="?page=admin" class="btn btn-primary btn-lg">
                    <i class="fas fa-cog"></i> Ir para Admin
                </a>
            <?php endif; ?>
        </div>
    </div>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

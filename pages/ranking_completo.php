<?php
// pages/ranking_completo.php - Ranking Completo de Consultores (sem filtros manuais)

require_once 'functions/vendas.php';

// Carrega configurações
$config = $_SESSION['config_sistema'] ?? carregarConfiguracoes();
$periodo_config = $config['periodo_relatorio'] ?? obterPeriodoRelatorio();
$campos_visiveis = $config['campos_visiveis_consultores'] ?? $_SESSION['campos_visiveis_consultores'];

// Inicializa variáveis
$vendas_processadas = null;
$mensagem_erro = null;

// Lista arquivos disponíveis
$arquivos_vendas = listarCSVs('vendas');

// Processa automaticamente com período configurado
if (!empty($arquivos_vendas)) {
    // Pega o arquivo mais recente
    $arquivo_selecionado = $arquivos_vendas[0]['caminho'];

    // Aplica filtros do admin
    $filtros = [
        'data_inicial' => $periodo_config['data_inicial'],
        'data_final' => $periodo_config['data_final'],
        'primeira_parcela_paga' => $periodo_config['apenas_primeira_parcela'] ?? false,
        'apenas_vista' => $periodo_config['apenas_vista'] ?? false,
        'status' => $periodo_config['filtro_status'] ?? 'Ativo'
    ];

    if (file_exists($arquivo_selecionado)) {
        $vendas_processadas = processarVendasComRanges($arquivo_selecionado, $filtros);

        // Ordena por pontos (maior para menor)
        usort($vendas_processadas['por_consultor'], function($a, $b) {
            if ($b['pontos'] == $a['pontos']) {
                return $b['quantidade'] <=> $a['quantidade'];
            }
            return $b['pontos'] <=> $a['pontos'];
        });
    } else {
        $mensagem_erro = "Arquivo não encontrado!";
    }
} else {
    $mensagem_erro = "Nenhum arquivo de vendas disponível!";
}

// Determina se DIP está ativo
$dip_ativo = false;
if (isset($_SESSION['config_sistema']['tipos_premiacao'])) {
    foreach ($_SESSION['config_sistema']['tipos_premiacao'] as $tipo) {
        if ($tipo['nome'] === 'DIP' && $tipo['ativo']) {
            $dip_ativo = true;
            break;
        }
    }
} else {
    $dip_ativo = ($_SESSION['config_premiacoes']['vendas_para_dip'] > 0 &&
                  $_SESSION['config_premiacoes']['vendas_para_dip'] < 200);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Ranking Completo - Aquabeat</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .card { box-shadow: none !important; border: 1px solid #ddd !important; }
        }

        .trophy-gold { color: #FFD700; }
        .trophy-silver { color: #C0C0C0; }
        .trophy-bronze { color: #CD7F32; }

        .container.mt-4 {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
        }

        .search-highlight {
            background-color: #fff3cd !important;
            border-left: 4px solid #ffc107 !important;
        }

        #searchInput {
            font-size: 1.1em;
            padding: 12px;
        }
    </style>
</head>
<body>

<div class="container mt-4">

    <?php if ($mensagem_erro): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?= $mensagem_erro ?>
        </div>
    <?php endif; ?>

    <?php if ($vendas_processadas): ?>

    <!-- Cabeçalho com Logo -->
    <div class="text-center mb-4">
        <?php if (temLogo()): ?>
            <img src="<?= getLogoURL() ?>" alt="Aquabeat" style="max-height: 100px;">
        <?php endif; ?>
        <h2 class="mt-3">Ranking Completo de Consultores</h2>
        <p class="text-muted">
            Período: <?= date('d/m/Y', strtotime($periodo_config['data_inicial'])) ?>
            até <?= date('d/m/Y', strtotime($periodo_config['data_final'])) ?>
        </p>
        <p class="text-info">
            <small>
                <i class="fas fa-info-circle"></i>
                Filtros aplicados: Status <?= $filtros['status'] ?>
                <?php if ($filtros['primeira_parcela_paga']): ?>
                    | Apenas 1ª Parcela Paga
                <?php endif; ?>
                <?php if ($filtros['apenas_vista']): ?>
                    | Apenas à Vista
                <?php endif; ?>
            </small>
        </p>
    </div>

    <!-- Barra de Busca -->
    <div class="card mb-4 no-print">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">
                <i class="fas fa-search"></i> Buscar Consultor no Ranking
            </h5>
        </div>
        <div class="card-body">
            <div class="input-group input-group-lg">
                <div class="input-group-prepend">
                    <span class="input-group-text">
                        <i class="fas fa-user"></i>
                    </span>
                </div>
                <input type="text" class="form-control" id="searchInput"
                       placeholder="Digite o nome do consultor para encontrar sua posição...">
                <div class="input-group-append">
                    <button class="btn btn-secondary" type="button" id="clearSearch">
                        <i class="fas fa-times"></i> Limpar
                    </button>
                </div>
            </div>
            <small class="text-muted mt-2 d-block">
                <i class="fas fa-lightbulb"></i>
                Digite qualquer parte do nome para filtrar e destacar a posição no ranking
            </small>
            <div id="searchResult" class="mt-2"></div>
        </div>
    </div>

    <!-- Botões de Ação -->
    <div class="text-center mb-4 no-print">
        <button onclick="window.print()" class="btn btn-success btn-lg">
            <i class="fas fa-print"></i> Imprimir
        </button>
        <a href="?page=home" class="btn btn-secondary btn-lg">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </div>

    <!-- Tabela Ranking Completo -->
    <div class="table-responsive">
        <table class="table table-bordered table-hover" id="rankingTable">
            <thead class="thead-dark">
                <tr>
                    <th class="text-center">Pos.</th>
                    <th>Consultor</th>
                    <?php if ($campos_visiveis['pontos']): ?>
                        <th class="text-center">Pontos</th>
                    <?php endif; ?>
                    <?php if ($campos_visiveis['vendas']): ?>
                        <th class="text-center">Vendas</th>
                    <?php endif; ?>
                    <?php if ($campos_visiveis['valor_total']): ?>
                        <th class="text-right">Valor Total</th>
                    <?php endif; ?>
                    <?php if ($campos_visiveis['valor_pago']): ?>
                        <th class="text-right">Valor Pago</th>
                    <?php endif; ?>
                    <?php if ($campos_visiveis['cotas_sap']): ?>
                        <th class="text-center">SAPs</th>
                        <?php if ($dip_ativo): ?>
                            <th class="text-center">DIPs</th>
                        <?php endif; ?>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vendas_processadas['por_consultor'] as $index => $consultor): ?>
                <tr data-consultor="<?= strtolower(htmlspecialchars($consultor['consultor'])) ?>">
                    <td class="text-center">
                        <strong><?= $index + 1 ?>º</strong>
                        <?php if ($index < 3): ?>
                            <i class="fas fa-trophy <?= ['trophy-gold', 'trophy-silver', 'trophy-bronze'][$index] ?>"></i>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= htmlspecialchars($consultor['consultor']) ?></strong></td>

                    <?php if ($campos_visiveis['pontos']): ?>
                        <td class="text-center">
                            <span class="badge badge-primary"><?= $consultor['pontos'] ?> pts</span>
                        </td>
                    <?php endif; ?>

                    <?php if ($campos_visiveis['vendas']): ?>
                        <td class="text-center"><?= $consultor['quantidade'] ?></td>
                    <?php endif; ?>

                    <?php if ($campos_visiveis['valor_total']): ?>
                        <td class="text-right">R$ <?= number_format($consultor['venda'], 2, ',', '.') ?></td>
                    <?php endif; ?>

                    <?php if ($campos_visiveis['valor_pago']): ?>
                        <td class="text-right">R$ <?= number_format($consultor['pago'], 2, ',', '.') ?></td>
                    <?php endif; ?>

                    <?php if ($campos_visiveis['cotas_sap']): ?>
                        <td class="text-center">
                            <strong><?= $consultor['saps'] ?? 0 ?></strong>
                        </td>
                        <?php if ($dip_ativo): ?>
                        <td class="text-center">
                            <strong><?= $consultor['dips'] ?? 0 ?></strong>
                        </td>
                        <?php endif; ?>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Resumo -->
    <div class="alert alert-info mt-4">
        <strong>Total de Consultores:</strong> <?= count($vendas_processadas['por_consultor']) ?><br>
        <strong>Total de Vendas:</strong> <?= count($vendas_processadas['vendas']) ?>
    </div>

    <?php endif; ?>

</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script de Busca -->
<script>
$(document).ready(function() {
    const $searchInput = $('#searchInput');
    const $clearBtn = $('#clearSearch');
    const $searchResult = $('#searchResult');
    const $tableRows = $('#rankingTable tbody tr');

    // Função de busca
    function performSearch() {
        const searchTerm = $searchInput.val().toLowerCase().trim();

        // Remove destaques anteriores
        $tableRows.removeClass('search-highlight');

        if (searchTerm === '') {
            // Mostra todas as linhas
            $tableRows.show();
            $searchResult.html('');
            return;
        }

        let foundCount = 0;
        let firstMatch = null;

        $tableRows.each(function() {
            const $row = $(this);
            const consultorName = $row.data('consultor');

            if (consultorName.indexOf(searchTerm) !== -1) {
                $row.show();
                $row.addClass('search-highlight');
                foundCount++;

                if (!firstMatch) {
                    firstMatch = $row;
                }
            } else {
                $row.hide();
            }
        });

        // Atualiza mensagem de resultado
        if (foundCount > 0) {
            $searchResult.html(
                `<div class="alert alert-success mb-0">
                    <i class="fas fa-check-circle"></i>
                    <strong>${foundCount}</strong> consultor(es) encontrado(s)
                </div>`
            );

            // Scroll até o primeiro resultado
            if (firstMatch) {
                $('html, body').animate({
                    scrollTop: firstMatch.offset().top - 150
                }, 500);
            }
        } else {
            $searchResult.html(
                `<div class="alert alert-warning mb-0">
                    <i class="fas fa-exclamation-triangle"></i>
                    Nenhum consultor encontrado com "<strong>${$searchInput.val()}</strong>"
                </div>`
            );
        }
    }

    // Eventos
    $searchInput.on('keyup', performSearch);

    $clearBtn.on('click', function() {
        $searchInput.val('');
        performSearch();
        $searchInput.focus();
    });

    // Enter para buscar
    $searchInput.on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            performSearch();
        }
    });
});
</script>

</body>
</html>

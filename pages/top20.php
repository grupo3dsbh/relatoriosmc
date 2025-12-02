<?php
// pages/top20.php - Top 20 Consultores com Impressão

require_once 'functions/vendas.php';

// Inicializa variáveis
$vendas_processadas = null;
$arquivo_selecionado = null;
$mensagem_erro = null;

// Lista arquivos disponíveis
$arquivos_vendas = listarCSVs('vendas');

// Processa relatório
if (isset($_POST['processar_top20']) || isset($_GET['arquivo'])) {
    
    if (isset($_GET['arquivo'])) {
        $nome_arquivo = $_GET['arquivo'];
        $arquivo_selecionado = VENDAS_DIR . '/' . $nome_arquivo;
    } elseif (isset($_POST['arquivo_vendas'])) {
        $arquivo_selecionado = $_POST['arquivo_vendas'];
    }
    
    if ($arquivo_selecionado && file_exists($arquivo_selecionado)) {
        
        $filtros = [
            'data_inicial' => $_POST['data_inicial'] ?? '',
            'data_final' => $_POST['data_final'] ?? '',
            'primeira_parcela_paga' => isset($_POST['primeira_parcela_paga']),
            'apenas_vista' => isset($_POST['apenas_vista']),
            'status' => $_POST['filtro_status'] ?? ''
        ];
        
        $vendas_processadas = processarVendasComRanges($arquivo_selecionado, $filtros);
        
        // Ordena e pega top 20
        usort($vendas_processadas['por_consultor'], function($a, $b) {
            if ($b['pontos'] == $a['pontos']) {
                return $b['quantidade'] <=> $a['quantidade'];
            }
            return $b['pontos'] <=> $a['pontos'];
        });
        
        $vendas_processadas['por_consultor'] = array_slice($vendas_processadas['por_consultor'], 0, 20);
    }
}

$campos_visiveis = $_SESSION['campos_visiveis_consultores'];

// Determina se DIP está ativo
$dip_ativo = ($_SESSION['config_premiacoes']['vendas_para_dip'] > 0 && 
              $_SESSION['config_premiacoes']['vendas_para_dip'] < 200);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Top 20 Consultores - Aquabeat</title>
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
            background-color: ghostwhite;
            padding: 10px;
            border-radius: 10px;
        }
    </style>
</head>
<body>

<div class="container mt-4">
    
    <?php if (!$vendas_processadas): ?>
    <!-- Formulário de Seleção -->
    <div class="card no-print">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">
                <i class="fas fa-trophy"></i> Top 20 Consultores
            </h4>
        </div>
        <div class="card-body">
            <form method="post">
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label>Arquivo de Vendas</label>
                        <select class="form-control" name="arquivo_vendas" required>
                            <option value="">-- Selecione --</option>
                            <?php foreach ($arquivos_vendas as $arquivo): ?>
                                <option value="<?= htmlspecialchars($arquivo['caminho']) ?>">
                                    <?= htmlspecialchars($arquivo['nome']) ?> (<?= $arquivo['data'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <label>Data Inicial</label>
                        <input type="date" class="form-control" name="data_inicial">
                    </div>
                    <div class="col-md-6">
                        <label>Data Final</label>
                        <input type="date" class="form-control" name="data_final">
                    </div>
                </div>
                
                <hr>
                
                <button type="submit" name="processar_top20" class="btn btn-primary btn-block">
                    <i class="fas fa-chart-line"></i> Gerar Top 20
                </button>
            </form>
        </div>
    </div>
    <?php else: ?>
    
    <!-- Cabeçalho com Logo -->
    <div class="text-center mb-4">
        <?php if (temLogo()): ?>
            <img src="<?= getLogoURL() ?>" alt="Aquabeat" style="max-height: 100px;">
        <?php endif; ?>
        <h2 class="mt-3">Top 20 Consultores - Ranking</h2>
        <p class="text-muted">
            <?php 
            $periodo = extrairPeriodoVendas($vendas_processadas['vendas']);
            echo "Período: {$periodo['inicio']} até {$periodo['fim']}";
            ?>
        </p>
    </div>
    
    <!-- Botes de Ação -->
    <div class="text-center mt-4 no-print">
        <button onclick="window.print()" class="btn btn-success btn-lg">
            <i class="fas fa-print"></i> Imprimir
        </button>
        <a href="?page=relatorio" class="btn btn-secondary btn-lg">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </div>
    
    <!-- Tabela Top 20 -->
    <table class="table table-bordered table-hover">
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
            <tr>
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
                        <strong><?= $consultor['saps'] ?></strong>
                    </td>
                    <?php if ($dip_ativo): ?>
                    <td class="text-center">
                        <strong><?= $consultor['dips'] ?></strong>
                    </td>
                    <?php endif; ?>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    
    
    <?php endif; ?>
    
</div>

</body>
</html>
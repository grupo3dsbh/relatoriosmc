<?php
// pages/relatorio.php - Relatórios de Vendas com Ranking e Ranges de Pontuação

require_once 'functions/vendas.php';

// Carrega configurações para obter período padrão
$config = $_SESSION['config_sistema'] ?? carregarConfiguracoes();
$periodo_padrao = $config['periodo_relatorio'] ?? [
    'data_inicial' => date('Y-m-01', strtotime('first day of last month')),
    'data_final' => date('Y-m-t', strtotime('last day of last month'))
];

// Inicializa variáveis
$vendas_processadas = null;
$arquivo_selecionado = null;
$mensagem_erro = null;
$mensagem_sucesso = null;

// Lista arquivos disponíveis
$arquivos_vendas = listarCSVs('vendas');

if (isset($_POST['vendas_do_dia'])) {
    $_POST['data_inicial'] = date('Y-m-d');
    $_POST['data_final'] = date('Y-m-d');
    $_POST['processar_relatorio'] = true;
}

// Garante que configurações existam
if (!isset($_SESSION['campos_visiveis_consultores'])) {
    $_SESSION['campos_visiveis_consultores'] = [
        'pontos' => true,
        'vendas' => true,
        'valor_total' => true,
        'valor_pago' => true,
        'detalhamento' => true,
        'cotas_sap' => true
    ];
}

$campos_visiveis = $_SESSION['campos_visiveis_consultores'];

// Processa relatório
if (isset($_POST['processar_relatorio']) || isset($_GET['arquivo'])) {
    
    // Determina qual arquivo usar
    if (isset($_GET['arquivo'])) {
        $nome_arquivo = $_GET['arquivo'];
        $arquivo_selecionado = VENDAS_DIR . '/' . $nome_arquivo;
    } elseif (isset($_POST['arquivo_vendas'])) {
        $arquivo_selecionado = $_POST['arquivo_vendas'];
    }
    
    if ($arquivo_selecionado && file_exists($arquivo_selecionado)) {
        
        // Monta filtros
        $filtros = [
            'data_inicial' => $_POST['data_inicial'] ?? '',
            'data_final' => $_POST['data_final'] ?? '',
            'primeira_parcela_paga' => isset($_POST['primeira_parcela_paga']),
            'apenas_vista' => isset($_POST['apenas_vista']),
            'status' => $_POST['filtro_status'] ?? ''
        ];
        
        // ===== DEBUG DETALHADO - INÍCIO =====
        if (isGodMode()) {
            echo "<div class='alert alert-info mb-4'>";
            echo "<strong>🔍 DEBUG - ANÁLISE COMPLETA DO PROCESSAMENTO:</strong><br><br>";
            
            // 1. Informações do arquivo
            echo "<strong>📄 Arquivo:</strong><br>";
            echo "Caminho: " . htmlspecialchars($arquivo_selecionado) . "<br>";
            echo "Tamanho: " . number_format(filesize($arquivo_selecionado) / 1024, 2) . " KB<br>";
            echo "Timezone PHP: <strong>" . date_default_timezone_get() . "</strong><br>";
            echo "Data/Hora atual: " . date('d/m/Y H:i:s') . "<br><br>";
            
            // 2. Conta linhas RAW do arquivo
            $handle = fopen($arquivo_selecionado, "r");
            $total_linhas_arquivo = 0;
            while (fgets($handle) !== false) {
                $total_linhas_arquivo++;
            }
            fclose($handle);
            
            echo "<strong>📊 Análise do Arquivo:</strong><br>";
            echo "Total de linhas (incluindo header): <strong>" . $total_linhas_arquivo . "</strong><br>";
            echo "Vendas esperadas: <strong class='text-primary'>" . ($total_linhas_arquivo - 1) . "</strong><br><br>";
            
            // 3. Detecta separador
            $handle = fopen($arquivo_selecionado, "r");
            $primeira_linha = fgets($handle);
            fclose($handle);
            
            $tabs = substr_count($primeira_linha, "\t");
            $pontovirgulas = substr_count($primeira_linha, ";");
            
            echo "<strong> Detecção de Separador:</strong><br>";
            echo "TABs encontrados: " . $tabs . "<br>";
            echo "Ponto-vírgulas encontrados: " . $pontovirgulas . "<br>";
            echo "Separador que será usado: <strong>" . ($tabs > $pontovirgulas ? 'TAB (\\t)' : 'PONTO-VÍRGULA (;)') . "</strong><br><br>";
            
            // 4. Mostra filtros aplicados
            echo "<strong>🎯 Filtros Aplicados:</strong><br>";
            if (!empty($filtros['data_inicial']) || !empty($filtros['data_final']) || 
                $filtros['primeira_parcela_paga'] || $filtros['apenas_vista'] || !empty($filtros['status'])) {
                
                echo "<ul class='mb-0'>";
                if (!empty($filtros['data_inicial'])) {
                    echo "<li>Data Inicial: " . date('d/m/Y', strtotime($filtros['data_inicial'])) . "</li>";
                }
                if (!empty($filtros['data_final'])) {
                    echo "<li>Data Final: " . date('d/m/Y', strtotime($filtros['data_final'])) . "</li>";
                }
                if ($filtros['primeira_parcela_paga']) {
                    echo "<li>Apenas 1ª Parcela Paga: <strong>SIM</strong></li>";
                }
                if ($filtros['apenas_vista']) {
                    echo "<li>Apenas à Vista: <strong>SIM</strong></li>";
                }
                if (!empty($filtros['status'])) {
                    echo "<li>Status: " . htmlspecialchars($filtros['status']) . "</li>";
                }
                echo "</ul>";
            } else {
                echo "<span class='text-muted'>Nenhum filtro aplicado</span>";
            }
            
            echo "<br>";
        }
        // ===== DEBUG DETALHADO - FIM =====
        
        // Processa vendas COM RANGES DE PONTUAÇÃO
        $vendas_processadas = processarVendasComRanges($arquivo_selecionado, $filtros);
        
        // ===== DEBUG: RESULTADO DO PROCESSAMENTO =====
        if (isGodMode()) {
            echo "<strong>✅ Resultado do Processamento:</strong><br>";
            echo "Vendas processadas: <strong class='text-success'>" . count($vendas_processadas['vendas']) . "</strong><br>";
            echo "Consultores encontrados: <strong>" . count($vendas_processadas['por_consultor']) . "</strong><br>";
            
            // Verifica se tem duplicados
            if (isset($vendas_processadas['duplicados']) && $vendas_processadas['duplicados']['total'] > 0) {
                echo "⚠️ CPFs duplicados: <strong class='text-warning'>" . $vendas_processadas['duplicados']['total'] . "</strong><br>";
            }
            
            // Mostra vendas ignoradas com detalhamento
            if (isset($vendas_processadas['log_ignorados']) && !empty($vendas_processadas['log_ignorados'])) {
                $total_ignorados = count($vendas_processadas['log_ignorados']);
                echo "<br><span class='text-danger'><strong>❌ Vendas Ignoradas: " . $total_ignorados . "</strong></span><br>";
                
                // Agrupa por motivo
                $por_motivo = [];
                foreach ($vendas_processadas['log_ignorados'] as $ignore) {
                    $motivo = $ignore['motivo'];
                    if (!isset($por_motivo[$motivo])) {
                        $por_motivo[$motivo] = [];
                    }
                    $por_motivo[$motivo][] = $ignore;
                }
                
                echo "<button type='button' class='btn btn-sm btn-warning mt-2' data-toggle='collapse' data-target='#collapseIgnorados'>";
                echo "<i class='fas fa-eye'></i> Ver Detalhes das Vendas Ignoradas";
                echo "</button>";
                
                echo "<div id='collapseIgnorados' class='collapse mt-3'>";
                echo "<div class='table-responsive'>";
                echo "<table class='table table-sm table-bordered'>";
                echo "<thead class='thead-light'>";
                echo "<tr><th>Motivo</th><th>Qtd</th><th>IDs (primeiros 10)</th></tr>";
                echo "</thead>";
                echo "<tbody>";
                
                foreach ($por_motivo as $motivo => $registros) {
                    $ids = array_slice(array_column($registros, 'id'), 0, 10);
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($motivo) . "</td>";
                    echo "<td><strong>" . count($registros) . "</strong></td>";
                    echo "<td><small>" . implode(', ', array_map('htmlspecialchars', $ids)) . "</small></td>";
                    echo "</tr>";
                }
                
                echo "</tbody>";
                echo "</table>";
                echo "</div>";
                echo "</div>";
            } else {
                echo "<br><span class='text-success'><strong>✅ Nenhuma venda ignorada!</strong></span><br>";
            }
            
            // Verificação matemática
            $diferenca = ($total_linhas_arquivo - 1) - count($vendas_processadas['vendas']);
            echo "<br>";
            if ($diferenca > 0) {
                echo "<div class='alert alert-danger mt-3'>";
                echo "<strong>⚠️ ATENÇÃO: Faltam " . $diferenca . " vendas!</strong><br>";
                echo "Esperado: <strong>" . ($total_linhas_arquivo - 1) . "</strong> | ";
                echo "Processado: <strong>" . count($vendas_processadas['vendas']) . "</strong><br>";
                echo "<small>Verifique os detalhes das vendas ignoradas acima para identificar o problema.</small>";
                echo "</div>";
            } else if ($diferenca < 0) {
                echo "<div class='alert alert-warning mt-3'>";
                echo "<strong>⚠️ ATENÇO: " . abs($diferenca) . " vendas a mais que o esperado!</strong><br>";
                echo "Pode haver duplicação no processamento.";
                echo "</div>";
            } else {
                echo "<div class='alert alert-success mt-3'>";
                echo "<strong>✅ PERFEITO! Todas as vendas foram processadas corretamente!</strong><br>";
                echo "Esperado = Processado = <strong>" . count($vendas_processadas['vendas']) . "</strong> vendas";
                echo "</div>";
            }
            
            echo "</div>"; // Fecha alert-info
        }
        // ===== FIM DEBUG =====
        
        // Ordena por pontos (maior para menor)
        usort($vendas_processadas['por_consultor'], function($a, $b) {
            if ($b['pontos'] == $a['pontos']) {
                return $b['quantidade'] <=> $a['quantidade'];
            }
            return $b['pontos'] <=> $a['pontos'];
        });
        
        $mensagem_sucesso = "Relatório processado com sucesso! " . 
                          count($vendas_processadas['vendas']) . " vendas encontradas.";
    } else {
        $mensagem_erro = "Arquivo não encontrado!";
    }
}

// Determina se DIP está ativo
$dip_ativo = ($_SESSION['config_premiacoes']['vendas_para_dip'] > 0 && 
              $_SESSION['config_premiacoes']['vendas_para_dip'] < 200);
?>

<!-- jquery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fas fa-chart-line"></i> Relatórios de Vendas e Rankings
                </h4>
            </div>
            <div class="card-body">
                
                <?php if ($mensagem_sucesso): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?= $mensagem_sucesso ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                
                <?php if ($mensagem_erro): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?= $mensagem_erro ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($arquivos_vendas)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Nenhum arquivo de vendas encontrado!</strong>
                        <?php if (isGodMode()): ?>
                            <a href="?page=admin" class="btn btn-warning btn-sm ml-2">
                                Ir para Admin e enviar CSV
                            </a>
                        <?php else: ?>
                            Entre em contato com o administrador para carregar os dados.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                
                <!-- Formulário de Filtros -->
                <form method="post" id="formRelatorio">
                    <!-- Card: Filtros e Configurações (COLAPSÁVEL) -->
                    <div class="card mb-4">
                        <div class="card-header bg-secondary text-white" style="cursor: pointer;" 
                             data-toggle="collapse" data-target="#collapseFiltros">
                            <h5 class="mb-0">
                                <i class="fas fa-filter"></i> Filtros e Configurações
                                <i class="fas fa-chevron-down float-right" id="iconFiltros"></i>
                            </h5>
                        </div>
                        
                        <div id="collapseFiltros" class="collapse">
                            <div class="card-body">
                                <form method="post" enctype="multipart/form-data">
                                    
                                    <!-- Seleção de Arquivo -->
                                    <div class="form-group">
                                        <label>
                                            <i class="fas fa-file-csv"></i> Arquivo de Vendas
                                        </label>
                                        <select class="form-control" name="arquivo_vendas" required>
                                            <option value="">-- Selecione o arquivo --</option>
                                            <?php foreach ($arquivos_vendas as $arquivo): ?>
                                                <option value="<?= htmlspecialchars($arquivo['caminho']) ?>"
                                                        <?= $arquivo_selecionado === $arquivo['caminho'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($arquivo['nome']) ?> (<?= $arquivo['data'] ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Filtros de Data -->
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>
                                                    <i class="fas fa-calendar-alt"></i> Data Inicial
                                                </label>
                                                <input type="date" class="form-control" name="data_inicial"
                                                       value="<?= $_POST['data_inicial'] ?? $periodo_padrao['data_inicial'] ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>
                                                    <i class="fas fa-calendar-check"></i> Data Final
                                                </label>
                                                <input type="date" class="form-control" name="data_final"
                                                       value="<?= $_POST['data_final'] ?? $periodo_padrao['data_final'] ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Filtros de Status -->
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>
                                                    <i class="fas fa-check-circle"></i> Status
                                                </label>
                                                <select class="form-control" name="filtro_status">
                                                    <option value="">Todos os Status</option>
                                                    <option value="Ativo" <?= ($_POST['filtro_status'] ?? '') === 'Ativo' ? 'selected' : '' ?>>
                                                        Ativo
                                                    </option>
                                                    <option value="Inativo" <?= ($_POST['filtro_status'] ?? '') === 'Inativo' ? 'selected' : '' ?>>
                                                        Inativo
                                                    </option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <div class="form-check mt-4 pt-2">
                                                <input type="checkbox" class="form-check-input" 
                                                       name="primeira_parcela_paga" id="primeira_parcela_paga"
                                                       <?= isset($_POST['primeira_parcela_paga']) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="primeira_parcela_paga">
                                                    <i class="fas fa-money-bill-wave"></i> Apenas com 1ª Parcela Paga
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <div class="form-check mt-4 pt-2">
                                                <input type="checkbox" class="form-check-input" 
                                                       name="apenas_vista" id="apenas_vista"
                                                       <?= isset($_POST['apenas_vista']) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="apenas_vista">
                                                    <i class="fas fa-bolt"></i> Apenas Vendas à Vista
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <hr>
                                    
                                    <!-- Botões de Ação -->
                                    <div class="row">
                                        <div class="col-md-6">
                                            <button type="submit" name="processar_relatorio" class="btn btn-primary btn-block">
                                                <i class="fas fa-chart-line"></i> Processar Relatório
                                            </button>
                                        </div>
                                        <div class="col-md-6">
                                            <button type="submit" name="vendas_do_dia" class="btn btn-info btn-block">
                                                <i class="fas fa-calendar-day"></i> Ver Vendas de Hoje
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <script>
                    $(document).ready(function() {
                        // Controla ícone do collapse
                        $('#collapseFiltros').on('show.bs.collapse', function() {
                            $('#iconFiltros').removeClass('fa-chevron-down').addClass('fa-chevron-up');
                        }).on('hide.bs.collapse', function() {
                            $('#iconFiltros').removeClass('fa-chevron-up').addClass('fa-chevron-down');
                        });
                    });
                    </script>

                </form>
                
                <?php endif; ?>
                
            </div>
        </div>
    </div>
</div>

<?php if ($vendas_processadas): ?>

<!-- RANKING TOP 3 -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h4 class="mb-0">
                    <i class="fas fa-trophy"></i> Ranking - Top 3 Consultores
                </h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php 
                    $top3 = array_slice($vendas_processadas['por_consultor'], 0, 3);
                    $medals = [
                        1 => ['class' => 'trophy-gold', 'icon' => 'fa-trophy', 'bg' => 'warning', 'label' => '1º LUGAR'],
                        2 => ['class' => 'trophy-silver', 'icon' => 'fa-medal', 'bg' => 'secondary', 'label' => '2º LUGAR'],
                        3 => ['class' => 'trophy-bronze', 'icon' => 'fa-award', 'bg' => 'info', 'label' => '3º LUGAR']
                    ];
                    
                    foreach ($top3 as $index => $consultor):
                        $posicao = $index + 1;
                        $medal = $medals[$posicao];
                    ?>
                    <div class="col-md-4">
                        <div class="card bg-<?= $medal['bg'] ?> text-white">
                            <div class="card-body text-center">
                                <i class="fas <?= $medal['icon'] ?> fa-3x <?= $medal['class'] ?> mb-3"></i>
                                <h5 class="card-title"><?= $medal['label'] ?></h5>
                                <h3><?= htmlspecialchars($consultor['consultor']) ?></h3>
                                <hr class="bg-white">
                                <div class="row">
                                    <div class="col-6">
                                        <h4><?= $consultor['pontos'] ?></h4>
                                        <small>Pontos</small>
                                    </div>
                                    <div class="col-6">
                                        <h4><?= $consultor['quantidade'] ?></h4>
                                        <small>Vendas</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TABELA COMPLETA DE RANKING -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h4 class="mb-0">
                    <i class="fas fa-list-ol"></i> Ranking Completo de Consultores
                </h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="thead-dark">
                            <tr>
                                <th class="text-center">Posião</th>
                                <th>Consultor</th>
                                
                                <?php if ($campos_visiveis['pontos']): ?>
                                    <th class="text-center">Pontos</th>
                                <?php endif; ?>
                                
                                <?php if ($campos_visiveis['vendas']): ?>
                                    <th class="text-center">Vendas</th>
                                <?php endif; ?>
                                
                                <?php if ($campos_visiveis['valor_total'] && isGodMode()): ?>
                                    <th class="text-right">Valor Total</th>
                                <?php endif; ?>
                                
                                <?php if ($campos_visiveis['valor_pago'] && isGodMode()): ?>
                                    <th class="text-right">Valor Pago</th>
                                <?php endif; ?>
                                
                                <?php if ($campos_visiveis['cotas_sap']): ?>
                                    <th class="text-center">SAPs</th>
                                    <?php if ($dip_ativo): ?>
                                        <th class="text-center">DIPs</th>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php if ($campos_visiveis['detalhamento']): ?>
                                    <th class="text-center">Detalhes</th>
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
                                <td>
                                    <strong><?= htmlspecialchars($consultor['consultor']) ?></strong>
                                </td>
                                
                                <?php if ($campos_visiveis['pontos']): ?>
                                    <td class="text-center">
                                        <span class="badge badge-primary badge-lg" style="font-size: 1.1em;">
                                            <?= $consultor['pontos'] ?> pts
                                        </span>
                                    </td>
                                <?php endif; ?>
                                
                                <?php if ($campos_visiveis['vendas']): ?>
                                    <td class="text-center">
                                        <strong><?= $consultor['quantidade'] ?></strong>
                                    </td>
                                <?php endif; ?>
                                
                                <?php if ($campos_visiveis['valor_total'] && isGodMode()): ?>
                                    <td class="text-right">
                                        R$ <?= number_format($consultor['venda'], 2, ',', '.') ?>
                                    </td>
                                <?php endif; ?>
                                
                                <?php if ($campos_visiveis['valor_pago'] && isGodMode()): ?>
                                    <td class="text-right">
                                        R$ <?= number_format($consultor['pago'], 2, ',', '.') ?>
                                    </td>
                                <?php endif; ?>
                                
                                <!-- E no corpo da tabela: -->
                                <?php if ($campos_visiveis['cotas_sap']): ?>
                                    <td class="text-center">
                                        <span class="badge badge-warning" style="font-size: 1em;">
                                            <i class="fas fa-trophy"></i> <?= $consultor['saps'] ?? 0 ?>
                                        </span>
                                    </td>
                                    <?php if ($dip_ativo): ?>
                                        <td class="text-center">
                                            <span class="badge badge-danger" style="font-size: 1em;">
                                                <i class="fas fa-medal"></i> <?= $consultor['dips'] ?? 0 ?>
                                            </span>
                                        </td>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php if ($campos_visiveis['detalhamento']): ?>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-info btn-ver-detalhes" 
                                                data-consultor-json='<?= htmlspecialchars(json_encode($consultor), ENT_QUOTES, 'UTF-8') ?>'>
                                            <i class="fas fa-eye"></i> Ver
                                        </button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Validação de Consultor -->
<div class="modal fade" id="modalValidarConsultor" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">
                    <i class="fas fa-lock"></i> Validação Necessária
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p><strong id="nomeConsultorValidacao"></strong></p>

                <!-- Modo PIN -->
                <div id="modoPin">
                    <p>Digite seu PIN de 4 dígitos:</p>
                    <div class="form-group">
                        <label>PIN</label>
                        <input type="password" class="form-control" id="inputPIN"
                               placeholder="••••"
                               pattern="[0-9]{4}" maxlength="4" inputmode="numeric">
                        <small class="text-muted">Digite o PIN de 4 dígitos que você cadastrou</small>
                    </div>
                    <div class="text-center mt-2">
                        <button type="button" class="btn btn-link btn-sm" id="btnUsarCPF">
                            <i class="fas fa-id-card"></i> Usar CPF ou Telefone
                        </button>
                    </div>
                </div>

                <!-- Modo CPF/Telefone -->
                <div id="modoCPF" style="display: none;">
                    <p>Informe sua identificação:</p>
                    <div class="form-group">
                        <label>CPF ou Telefone</label>
                        <input type="text" class="form-control" id="inputValidacaoConsultor"
                               placeholder="4 dígitos do CPF ou telefone completo"
                               pattern="[0-9]{4,11}" maxlength="11" inputmode="numeric">
                        <small class="text-muted">Digite 4 dígitos do CPF (início ou fim) ou telefone completo</small>
                    </div>
                    <div class="text-center mt-2">
                        <button type="button" class="btn btn-link btn-sm" id="btnUsarPIN">
                            <i class="fas fa-key"></i> Usar PIN
                        </button>
                    </div>
                </div>

                <div id="erroValidacao" class="alert alert-danger" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="btnValidarAcesso">
                    <i class="fas fa-check"></i> Validar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Criação de PIN -->
<div class="modal fade" id="modalCriarPIN" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-key"></i> Criar PIN de Acesso
                </h5>
            </div>
            <div class="modal-body">
                <p><strong id="nomeConsultorPIN"></strong></p>
                <p>Crie um PIN de 4 dígitos para facilitar seus próximos acessos:</p>

                <div class="form-group">
                    <label>Novo PIN</label>
                    <input type="password" class="form-control" id="inputNovoPIN"
                           placeholder="••••"
                           pattern="[0-9]{4}" maxlength="4" inputmode="numeric">
                </div>

                <div class="form-group">
                    <label>Confirmar PIN</label>
                    <input type="password" class="form-control" id="inputConfirmarPIN"
                           placeholder="••••"
                           pattern="[0-9]{4}" maxlength="4" inputmode="numeric">
                </div>

                <div id="erroCriarPIN" class="alert alert-danger" style="display: none;"></div>
                <div id="sucessoCriarPIN" class="alert alert-success" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="btnPularPIN">Pular (usar CPF/Tel nas próximas vezes)</button>
                <button type="button" class="btn btn-success" id="btnSalvarPIN">
                    <i class="fas fa-save"></i> Criar PIN
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Detalhamento - FUNDO FIXO -->
<div class="modal fade" id="modalDetalhamentoConsultor" tabindex="-1" role="dialog" 
     data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-xl" role="document" 
         style="max-width: 90%; margin: 1.75rem auto;">
        <div class="modal-content" 
             style="max-height: 90vh; display: flex; flex-direction: column;">
            <!-- Header fixo -->
            <div class="modal-header bg-info text-white" style="flex-shrink: 0;">
                <h5 class="modal-title">
                    <i class="fas fa-chart-bar"></i> Detalhamento de Pontos
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            
            <!-- Body com scroll -->
            <div class="modal-body" id="modalDetalhamentoBody" 
                 style="overflow-y: auto; flex: 1; padding: 1.5rem;">
                <!-- Conteúdo -->
            </div>
            
            <!-- Footer fixo -->
            <div class="modal-footer" style="flex-shrink: 0;">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times"></i> Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<?php if (isGodMode() && isset($vendas_processadas['vendas']) && count($vendas_processadas['log_ignorados'] ?? []) > 0): ?>

<div class="alert alert-warning">
    <h5>
        <i class="fas fa-exclamation-triangle"></i> 
        Vendas Ignoradas: <?= count($vendas_processadas['log_ignorados']) ?>
    </h5>
    
    <button type="button" class="btn btn-sm btn-warning" data-toggle="collapse" data-target="#collapseIgnorados">
        <i class="fas fa-eye"></i> Ver Detalhes
    </button>
    
    <div id="collapseIgnorados" class="collapse mt-3">
        <table class="table table-sm table-bordered">
            <thead class="thead-light">
                <tr>
                    <th>Linha</th>
                    <th>ID</th>
                    <th>Motivo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vendas_processadas['log_ignorados'] as $ignore): ?>
                <tr>
                    <td><?= $ignore['linha'] ?></td>
                    <td><?= $ignore['id'] ?></td>
                    <td><?= $ignore['motivo'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php if (isGodMode() && isset($vendas_processadas['duplicados']) && $vendas_processadas['duplicados']['total'] > 0): ?>

<!-- CARD DE DUPLICADOS -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="alert alert-warning">
            <h5>
                <i class="fas fa-exclamation-triangle"></i> 
                Atenção: <?= $vendas_processadas['duplicados']['total'] ?> CPF(s) com vendas duplicadas!
            </h5>
            <button type="button" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#modalDuplicados">
                <i class="fas fa-search"></i> Ver Relatório de Duplicados
            </button>
        </div>
    </div>
</div>

<!-- Modal de Duplicados -->
<div class="modal fade" id="modalDuplicados" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle"></i> Relatório de Vendas Duplicadas
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <strong>Total de CPFs duplicados:</strong> <?= $vendas_processadas['duplicados']['total'] ?>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-sm" id="tabelaDuplicados">
                        <thead class="thead-warning">
                            <tr>
                                <th>CPF</th>
                                <th>Titular</th>
                                <th>ID Título</th>
                                <th>Consultor</th>
                                <th>Data Venda</th>
                                <th>Produto</th>
                                <th class="text-right">Valor</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vendas_processadas['duplicados']['detalhes'] as $cpf => $registros): ?>
                                <?php foreach ($registros as $idx => $reg): ?>
                                <tr class="<?= $idx > 0 ? 'table-danger' : '' ?>">
                                    <?php if ($idx === 0): ?>
                                    <td rowspan="<?= count($registros) ?>" class="align-middle">
                                        <strong><?= formatarCPF($cpf) ?></strong>
                                        <br>
                                        <span class="badge badge-danger"><?= count($registros) ?> vendas</span>
                                    </td>
                                    <?php endif; ?>
                                    <td><?= htmlspecialchars($reg['titular']) ?></td>
                                    <td><?= $reg['id'] ?></td>
                                    <td><?= htmlspecialchars($reg['consultor']) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($reg['data_venda'])) ?></td>
                                    <td><small><?= htmlspecialchars($reg['produto']) ?></small></td>
                                    <td class="text-right">R$ <?= number_format($reg['valor_total'], 2, ',', '.') ?></td>
                                    <td>
                                        <span class="badge badge-<?= $reg['status'] === 'Ativo' ? 'success' : 'danger' ?>">
                                            <?= $reg['status'] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-warning" onclick="window.print()">
                    <i class="fas fa-print"></i> Imprimir
                </button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<style>
/* Previne scroll do body quando modal está aberto */
body.modal-open {
    overflow: hidden !important;
    position: fixed;
    width: 100%;
}

/* Garante que o backdrop no permita scroll */
.modal-backdrop {
    position: fixed;
}

/* Garante scroll apenas no body do modal */
#modalDetalhamentoConsultor .modal-body {
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch;
}
</style>
<!-- Inicializar DataTables -->
<script type="text/javascript">
jQuery(document).ready(function($) {
    // Inicializa DataTables na tabela de ranking
    if ($('#tabelaRanking').length) {
        $('#tabelaRanking').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json"
            },
            "order": [[<?php 
                // Determina qual coluna tem os pontos baseado nos campos visíveis
                $col_pontos = 2; // Posição padro
                echo $col_pontos; 
            ?>, "desc"]], // Ordena por pontos decrescente
            "pageLength": 20,
            "lengthMenu": [[10, 20, 50, 100, -1], [10, 20, 50, 100, "Todos"]],
            "columnDefs": [
                { "orderable": false, "targets": -1 } // Última coluna (Detalhes) não ordenável
            ],
            "dom": 'lfrtip' // Layout padrão
        });
    }
});
</script>

<!-- Script para Modal de Validaço e Detalhamento -->
<script type="text/javascript">
jQuery(document).ready(function($) {
    console.log('Script de detalhamento carregado!');
    
    let consultorParaValidar = null;
    
    // ========================================
    // EVENTO: Clicar no botão VER
    // ========================================
    $(document).on('click', '.btn-ver-detalhes', function(e) {
        e.preventDefault();
        console.log('Botão VER clicado!');

        const consultorJson = $(this).attr('data-consultor-json');

        try {
            consultorParaValidar = JSON.parse(consultorJson);
            console.log('Consultor:', consultorParaValidar.consultor);

            // Preenche modal de validação
            $('#nomeConsultorValidacao').text(consultorParaValidar.consultor);
            $('#inputPIN').val('');
            $('#inputValidacaoConsultor').val('');
            $('#erroValidacao').hide();

            // Reseta para modo PIN
            $('#modoPin').show();
            $('#modoCPF').hide();

            // Verifica se é GODMODE - libera direto
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('godmode') === 'on') {
                console.log('GODMODE ativo - liberando acesso direto');
                mostrarDetalhamento(consultorParaValidar);
            } else {
                // Abre modal de validação
                $('#modalValidarConsultor').modal('show');
                // Foca no campo PIN
                setTimeout(function() { $('#inputPIN').focus(); }, 500);
            }

        } catch(error) {
            console.error('Erro ao processar consultor:', error);
            alert('Erro ao carregar dados do consultor');
        }
    });

    // ========================================
    // EVENTO: Toggle entre PIN e CPF
    // ========================================
    $('#btnUsarCPF').click(function() {
        $('#modoPin').hide();
        $('#modoCPF').show();
        $('#erroValidacao').hide();
        setTimeout(function() { $('#inputValidacaoConsultor').focus(); }, 100);
    });

    $('#btnUsarPIN').click(function() {
        $('#modoCPF').hide();
        $('#modoPin').show();
        $('#erroValidacao').hide();
        setTimeout(function() { $('#inputPIN').focus(); }, 100);
    });

    // ========================================
    // EVENTO: Enter nos campos
    // ========================================
    $('#inputPIN, #inputValidacaoConsultor').keypress(function(e) {
        if (e.which === 13) { // Enter
            $('#btnValidarAcesso').click();
        }
    });

    // ========================================
    // EVENTO: Validar acesso do consultor
    // ========================================
    $('#btnValidarAcesso').click(function() {
        $('#erroValidacao').hide();

        // Verifica qual modo está ativo
        if ($('#modoPin').is(':visible')) {
            // Validação com PIN
            const pin = $('#inputPIN').val().replace(/\D/g, '');

            if (pin.length !== 4) {
                $('#erroValidacao').text('Digite 4 dígitos').show();
                return;
            }

            console.log('Validando PIN...');

            $.ajax({
                url: 'ajax/validar_pin.php',
                method: 'POST',
                data: {
                    consultor: consultorParaValidar.consultor,
                    pin: pin
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Resposta PIN:', response);

                    if (response.success && response.valido) {
                        $('#modalValidarConsultor').modal('hide');
                        mostrarDetalhamento(consultorParaValidar);
                    } else if (response.sem_pin || response.bloqueado) {
                        // Não tem PIN ou está bloqueado - força CPF
                        $('#modoPin').hide();
                        $('#modoCPF').show();
                        $('#erroValidacao').text(response.mensagem).show();
                        setTimeout(function() { $('#inputValidacaoConsultor').focus(); }, 100);
                    } else {
                        $('#erroValidacao').text(response.mensagem || 'PIN incorreto!').show();
                        $('#inputPIN').val('').focus();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro na validação PIN:', error);
                    $('#erroValidacao').text('Erro ao validar. Tente novamente.').show();
                }
            });

        } else {
            // Validação com CPF/Telefone
            const identificacao = $('#inputValidacaoConsultor').val().replace(/\D/g, '');

            if (identificacao.length < 4) {
                $('#erroValidacao').text('Digite pelo menos 4 dígitos').show();
                return;
            }

            console.log('Validando CPF/Telefone...');

            $.ajax({
                url: 'ajax/validar_consultor.php',
                method: 'POST',
                data: {
                    consultor: consultorParaValidar.consultor,
                    identificacao: identificacao
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Resposta CPF:', response);

                    if (response.success && response.valido) {
                        $('#modalValidarConsultor').modal('hide');
                        // Abre modal para criar PIN
                        abrirModalCriarPIN();
                    } else {
                        $('#erroValidacao').text(response.mensagem || 'Identificação inválida!').show();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro na validação CPF:', error);
                    $('#erroValidacao').text('Erro ao validar. Tente novamente.').show();
                }
            });
        }
    });

    // ========================================
    // FUNÇÃO: Abrir modal de criar PIN
    // ========================================
    function abrirModalCriarPIN() {
        $('#nomeConsultorPIN').text(consultorParaValidar.consultor);
        $('#inputNovoPIN').val('');
        $('#inputConfirmarPIN').val('');
        $('#erroCriarPIN').hide();
        $('#sucessoCriarPIN').hide();
        $('#modalCriarPIN').modal('show');
        setTimeout(function() { $('#inputNovoPIN').focus(); }, 500);
    }

    // ========================================
    // EVENTO: Salvar PIN
    // ========================================
    $('#btnSalvarPIN').click(function() {
        const pin = $('#inputNovoPIN').val().replace(/\D/g, '');
        const pinConf = $('#inputConfirmarPIN').val().replace(/\D/g, '');

        $('#erroCriarPIN').hide();
        $('#sucessoCriarPIN').hide();

        if (pin.length !== 4) {
            $('#erroCriarPIN').text('O PIN deve ter 4 dígitos').show();
            return;
        }

        if (pin !== pinConf) {
            $('#erroCriarPIN').text('Os PINs não conferem').show();
            return;
        }

        $.ajax({
            url: 'ajax/criar_pin.php',
            method: 'POST',
            data: {
                consultor: consultorParaValidar.consultor,
                pin: pin,
                pin_confirmacao: pinConf
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#sucessoCriarPIN').text(response.mensagem).show();
                    setTimeout(function() {
                        $('#modalCriarPIN').modal('hide');
                        mostrarDetalhamento(consultorParaValidar);
                    }, 1500);
                } else {
                    $('#erroCriarPIN').text(response.mensagem).show();
                }
            },
            error: function() {
                $('#erroCriarPIN').text('Erro ao criar PIN').show();
            }
        });
    });

    // ========================================
    // EVENTO: Pular criação de PIN
    // ========================================
    $('#btnPularPIN').click(function() {
        $('#modalCriarPIN').modal('hide');
        mostrarDetalhamento(consultorParaValidar);
    });

    // Enter nos campos do PIN
    $('#inputNovoPIN, #inputConfirmarPIN').keypress(function(e) {
        if (e.which === 13) {
            $('#btnSalvarPIN').click();
        }
    });
    
    // ========================================
    // FUNÇÃO: Mostrar detalhamento completo
    // ========================================
    function mostrarDetalhamento(consultor) {
        console.log('Montando detalhamento para:', consultor.consultor);
        
        let html = `
            <h5 class="mb-4">${consultor.consultor}</h5>
            
            <!-- Cards de Resumo -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <div class="card bg-primary text-white text-center">
                        <div class="card-body py-2">
                            <h3 class="mb-0">${consultor.pontos || 0}</h3>
                            <small>Total de Pontos</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white text-center">
                        <div class="card-body py-2">
                            <h3 class="mb-0">${consultor.quantidade || 0}</h3>
                            <small>Total de Vendas</small>
                        </div>
                    </div>
                </div>
                <?php if (isGodMode()): ?>
                <div class="col-md-3">
                    <div class="card bg-info text-white text-center">
                        <div class="card-body py-2">
                            <h4 class="mb-0">R$ ${parseFloat(consultor.venda || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</h4>
                            <small>Valor Total</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white text-center">
                        <div class="card-body py-2">
                            <h4 class="mb-0">R$ ${parseFloat(consultor.pago || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</h4>
                            <small>Valor Pago</small>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        `;
        
        // Cards de Premiação (SAP e DIP se habilitado)
        <?php if ($campos_visiveis['cotas_sap']): ?>
        const dipAtivo = <?= json_encode($dip_ativo ?? false) ?>;
        const colMd = dipAtivo ? '6' : '12';
        
        html += `
            <div class="row mb-3">
                <div class="col-md-${colMd}">
                    <div class="card bg-warning text-white text-center">
                        <div class="card-body py-2">
                            <h2 class="mb-1"><i class="fas fa-trophy"></i> ${consultor.saps || 0}</h2>
                            <small>Títulos SAP Recebidos</small>
                            <hr class="bg-white my-1">
                            <small>A cada <?= $_SESSION['config_premiacoes']['pontos_por_sap'] ?? 21 ?> pontos = 1 SAP</small>
                        </div>
                    </div>
                </div>
        `;
        
        <?php if ($dip_ativo ?? false): ?>
        html += `
                <div class="col-md-6">
                    <div class="card bg-danger text-white text-center">
                        <div class="card-body py-2">
                            <h2 class="mb-1"><i class="fas fa-medal"></i> ${consultor.dips || 0}</h2>
                            <small>DIPs Recebidos</small>
                            <hr class="bg-white my-1">
                            <small>
                                ${consultor.dips > 0 ? 
                                    (consultor.criterio_dip === 'vendas_total' ? 
                                        '<?= $_SESSION['config_premiacoes']['vendas_para_dip'] ?>+ vendas totais' : 
                                        '<?= $_SESSION['config_premiacoes']['vendas_acima_2vagas_para_dip'] ?>+ vendas >2 vagas'
                                    ) : 
                                    'Meta não atingida'
                                }
                            </small>
                        </div>
                    </div>
                </div>
        `;
        <?php endif; ?>
        
        html += `
            </div>
        `;
        <?php endif; ?>
        
        // Detalhamento de Pontuação por Range (COLAPSÁVEL)
        if (consultor.detalhamento_por_range && consultor.detalhamento_por_range.length > 0) {
            html += `
                <div class="card mb-3">
                    <div class="card-header bg-light" style="cursor: pointer;" id="headerDetalhamento">
                        <h6 class="mb-0">
                            <i class="fas fa-calendar-alt"></i> Detalhamento de Pontuação por Período
                            <i class="fas fa-chevron-down float-right" id="iconCollapse"></i>
                        </h6>
                    </div>
                    <div id="collapseDetalhamento" class="collapse">
                        <div class="card-body p-2">
            `;
            
            consultor.detalhamento_por_range.forEach(function(range, index) {
                const cores = ['#e3f2fd', '#fff3e0', '#f3e5f5', '#e8f5e9'];
                const corRange = cores[index % cores.length];
                
                html += `
                    <div class="card mb-2" style="background-color: ${corRange}; border-left: 4px solid #007bff;">
                        <div class="card-header py-2">
                            <strong><i class="fas fa-calendar-check"></i> ${range.nome}</strong>
                            <span class="badge badge-primary float-right">${range.total_pontos} pontos</span>
                        </div>
                        <div class="card-body p-2">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0 bg-white">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Categoria</th>
                                            <th class="text-center">Qtd</th>
                                            <th class="text-center">Pts/Un</th>
                                            <th class="text-center">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                `;
                
                Object.values(range.categorias).forEach(function(cat) {
                    html += `
                        <tr>
                            <td><small>${cat.categoria}</small></td>
                            <td class="text-center"><small>${cat.quantidade}</small></td>
                            <td class="text-center"><small>${cat.pontos_unitario}</small></td>
                            <td class="text-center"><strong><small>${cat.pontos_total}</small></strong></td>
                        </tr>
                    `;
                });
                
                html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += `
                        </div>
                    </div>
                </div>
            `;
            
            // Total Consolidado (SEMPRE VISÍVEL)
            html += `
                <div class="card bg-success text-white mb-3">
                    <div class="card-body text-center py-2">
                        <h5 class="mb-0">
                            <i class="fas fa-trophy"></i> TOTAL: ${consultor.pontos} pontos
                        </h5>
                    </div>
                </div>
            `;
            
        } else if (consultor.detalhamento_pontos && consultor.detalhamento_pontos.length > 0) {
            // Detalhamento Simples (sem ranges)
            html += '<h6 class="mb-3">Detalhamento de Pontuaão</h6>';
            html += `
                <table class="table table-bordered table-sm">
                    <thead class="thead-light">
                        <tr>
                            <th>Categoria</th>
                            <th class="text-center">Quantidade</th>
                            <th class="text-center">Pontos/Unidade</th>
                            <th class="text-center">Total de Pontos</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            consultor.detalhamento_pontos.forEach(function(det) {
                html += `
                    <tr>
                        <td><small>${det.categoria}</small></td>
                        <td class="text-center"><small>${det.quantidade}</small></td>
                        <td class="text-center"><small>${det.pontos_unitario}</small></td>
                        <td class="text-center"><strong><small>${det.pontos_total}</small></strong></td>
                    </tr>
                `;
            });
            
            html += `
                    </tbody>
                </table>
            `;
        }
        
        // Tabela de Vendas Detalhadas
        // Tabela de Vendas Detalhadas (COM FILTROS)
        if (consultor.vendas_ids && consultor.vendas_ids.length > 0) {
            html += `
                <hr>
                <h6 class="mb-3 mt-3">
                    <i class="fas fa-list"></i> Vendas Realizadas (${consultor.vendas_ids.length} títulos)
                </h6>
                
                <!-- Filtros -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i>
                                </span>
                            </div>
                            <input type="text" class="form-control" id="buscaTitulo" 
                                   placeholder="ID, produto, titular...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-control form-control-sm" id="filtroTipoPagamento">
                            <option value="">Tipo Pagamento</option>
                            <option value="Vista">À Vista</option>
                            <option value="Parc.">Parcelado</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-control form-control-sm" id="filtroStatus">
                            <option value="">Status</option>
                            <option value="Ativo">Ativo</option>
                            <option value="Inativo">Inativo</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-control form-control-sm" id="filtroPrimeiraParcela">
                            <option value="">1ª Parcela</option>
                            <option value="sim">Paga</option>
                            <option value="nao">Não Paga</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-sm btn-secondary btn-block" id="btnLimparFiltrosModal">
                            <i class="fas fa-eraser"></i> Limpar
                        </button>
                    </div>
                </div>
                
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-sm table-bordered table-hover" id="tabelaVendasDetalhadas">
                        <thead class="thead-dark" style="position: sticky; top: 0; z-index: 10;">
                            <tr>
                                <th>ID</th>
                                <th>Produto</th>
                                <th>Data</th>
                                <th>Status</th>
                                <th>Titular</th>
                                <th>Tipo Pag.</th>
                                <th class="text-center">Parc.</th>
                                <th class="text-center">1ª?</th>
                                <th class="text-right">Vlr. Pago</th>
                            </tr>
                        </thead>
                        <tbody id="corpoTabelaVendas">
                            <tr>
                                <td colspan="9" class="text-center">
                                    <i class="fas fa-spinner fa-spin"></i> Carregando vendas...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div id="contadorVendasModal" class="text-muted mt-2"></div>
            `;
        }
        
        // Atualiza modal e abre
        $('#modalDetalhamentoBody').html(html);
        $('#modalDetalhamentoConsultor').modal('show');
        
        // Configura evento de collapse DEPOIS de inserir o HTML
        setTimeout(function() {
            // Evento de clique no header
            $('#headerDetalhamento').off('click').on('click', function() {
                $('#collapseDetalhamento').collapse('toggle');
            });
            
            // Eventos de show/hide para trocar ícone
            $('#collapseDetalhamento').off('show.bs.collapse hide.bs.collapse')
                .on('show.bs.collapse', function() {
                    $('#iconCollapse').removeClass('fa-chevron-down').addClass('fa-chevron-up');
                })
                .on('hide.bs.collapse', function() {
                    $('#iconCollapse').removeClass('fa-chevron-up').addClass('fa-chevron-down');
                });
        }, 100);
        
        // Carrega vendas detalhadas via AJAX
        if (consultor.vendas_ids && consultor.vendas_ids.length > 0) {
            carregarVendasDetalhadas(consultor.vendas_ids, consultor.consultor);
        }
        
        // Configurar busca em tempo real
        setTimeout(function() {
            $('#buscaTitulo').off('keyup').on('keyup', function() {
                const valor = $(this).val().toUpperCase();
                $('#tabelaVendasDetalhadas tbody tr').each(function() {
                    const texto = $(this).text().toUpperCase();
                    $(this).toggle(texto.indexOf(valor) > -1);
                });
            });
        }, 500);
    }
    
    // ========================================
    // FUNÇO: Carregar vendas detalhadas via AJAX
    // ========================================
    function carregarVendasDetalhadas(vendas_ids, consultor_nome) {
        console.log('Carregando', vendas_ids.length, 'vendas para', consultor_nome);
        
        $.ajax({
            url: 'ajax/carregar_vendas.php',
            method: 'POST',
            data: {
                vendas_ids: JSON.stringify(vendas_ids),
                consultor: consultor_nome
            },
            dataType: 'json',
            success: function(response) {
                console.log('Vendas carregadas:', response);
                
                if (response.success && response.vendas) {
                    let html = '';
                    
                    response.vendas.forEach(function(venda) {
                        const tipoPagSimples = venda.tipo_pagamento === 'À Vista' ? 'Vista' : 'Parc.';
                        const primeiraParcela = venda.primeira_parcela_paga ? 'sim' : 'nao';
                        const buscaTexto = `${venda.id} ${venda.produto_atual} ${venda.titular_mascarado}`.toLowerCase();
                        
                        html += `
                            <tr data-tipo-pagamento="${tipoPagSimples}"
                                data-status="${venda.status}"
                                data-primeira-parcela="${primeiraParcela}"
                                data-busca="${buscaTexto}">
                                <td><small class="text-muted">${venda.id}</small></td>
                                <td>
                                    <small>${venda.produto_atual}</small>
                                    ${venda.produto_alterado ? 
                                        `<br><span class="badge badge-warning badge-sm">Alterado de ${venda.produto_original} </span>` : 
                                        ''}
                                </td>
                                <td><small>${venda.data_venda_formatada}</small></td>
                                <td>
                                    <span class="badge badge-${venda.status === 'Ativo' ? 'success' : 'danger'} badge-sm">
                                        ${venda.status}
                                    </span>
                                </td>
                                <td><small>${venda.titular_mascarado}</small></td>
                                <td>
                                    <span class="badge badge-${tipoPagSimples === 'Vista' ? 'success' : 'info'} badge-sm">
                                        ${tipoPagSimples}
                                    </span>
                                </td>
                                <td class="text-center"><small>${venda.num_parcelas}x</small></td>
                                <td class="text-center">
                                    ${venda.primeira_parcela_paga ? 
                                        '<i class="fas fa-check text-success"></i>' : 
                                        '<i class="fas fa-times text-danger"></i>'}
                                </td>
                                <td class="text-right">
                                    <small><strong>R$ ${parseFloat(venda.valor_pago).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</strong></small>
                                </td>
                            </tr>
                        `;
                    });
                    
                    $('#corpoTabelaVendas').html(html);
                    
                    console.log('HTML das vendas inserido, configurando filtros...');
                    
                    // Chama configuração de filtros após inserir HTML
                    configurarFiltrosModal();
                } else {
                    $('#corpoTabelaVendas').html(
                        '<tr><td colspan="9" class="text-center text-warning">Nenhuma venda encontrada</td></tr>'
                    );
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro ao carregar vendas:', error);
                console.error('Response:', xhr.responseText);
                $('#corpoTabelaVendas').html(
                    '<tr><td colspan="9" class="text-center text-danger">Erro ao carregar vendas. Tente novamente.</td></tr>'
                );
            }
        });
    }
    
    // Função separada para configurar filtros
    function configurarFiltrosModal() {
        console.log('Configurando filtros do modal...');
        
        function aplicarFiltrosModal() {
            const busca = $('#buscaTitulo').val().toLowerCase().trim();
            const tipoPag = $('#filtroTipoPagamento').val().trim();
            const status = $('#filtroStatus').val().trim();
            const primeira = $('#filtroPrimeiraParcela').val().trim();
            
            console.log('Aplicando filtros:', {busca, tipoPag, status, primeira});
            
            let totalVisivel = 0;
            let somaValorPago = 0;
            
            $('#tabelaVendasDetalhadas tbody tr').each(function() {
                const $row = $(this);
                const dataBusca = ($row.attr('data-busca') || '').trim();
                const dataTipoPag = ($row.attr('data-tipo-pagamento') || '').trim();
                const dataStatus = ($row.attr('data-status') || '').trim();
                const dataPrimeira = ($row.attr('data-primeira-parcela') || '').trim();
                
                let mostrar = true;
                
                if (busca && dataBusca.indexOf(busca) === -1) mostrar = false;
                if (tipoPag && dataTipoPag !== tipoPag) mostrar = false;
                if (status && dataStatus !== status) mostrar = false;
                if (primeira && dataPrimeira !== primeira) mostrar = false;
                
                if (mostrar) {
                    $row.show();
                    totalVisivel++;
                    
                    // Soma valor pago
                    const valorTexto = $row.find('td:last strong').text()
                        .replace('R$', '').replace(/\s/g, '')
                        .replace(/\./g, '').replace(',', '.');
                    somaValorPago += parseFloat(valorTexto) || 0;
                } else {
                    $row.hide();
                }
            });
            
            // Atualiza contador
            const totalLinhas = $('#tabelaVendasDetalhadas tbody tr').length;
            $('#contadorVendasModal').html(
                `<i class="fas fa-filter"></i> Exibindo <strong>${totalVisivel}</strong> de <strong>${totalLinhas}</strong> vendas | ` +
                `Total: <strong>R$ ${somaValorPago.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</strong>`
            );
        }
        
        // Remove eventos anteriores
        $('#buscaTitulo, #filtroTipoPagamento, #filtroStatus, #filtroPrimeiraParcela').off();
        $('#btnLimparFiltrosModal').off();
        
        // Adiciona eventos
        $('#buscaTitulo').on('keyup input', aplicarFiltrosModal);
        $('#filtroTipoPagamento, #filtroStatus, #filtroPrimeiraParcela').on('change', aplicarFiltrosModal);
        
        $('#btnLimparFiltrosModal').on('click', function() {
            $('#buscaTitulo').val('');
            $('#filtroTipoPagamento').val('');
            $('#filtroStatus').val('');
            $('#filtroPrimeiraParcela').val('');
            aplicarFiltrosModal();
        });
        
        // Aplica filtro inicial
        aplicarFiltrosModal();
        
        console.log('Filtros configurados!');
    }
    
    // Função para aplicar filtros no modal
    function aplicarFiltrosModal() {
        const busca = $('#buscaTitulo').val().toLowerCase();
        const tipoPag = $('#filtroTipoPagamento').val();
        const status = $('#filtroStatus').val();
        const primeira = $('#filtroPrimeiraParcela').val();
        
        $('#tabelaVendasDetalhadas tbody tr').each(function() {
            const $row = $(this);
            const dataBusca = $row.attr('data-busca') || '';
            const dataTipoPag = $row.attr('data-tipo-pagamento') || '';
            const dataStatus = $row.attr('data-status') || '';
            const dataPrimeira = $row.attr('data-primeira-parcela') || '';
            
            let mostrar = true;
            
            if (busca && dataBusca.indexOf(busca) === -1) mostrar = false;
            if (tipoPag && dataTipoPag !== tipoPag) mostrar = false;
            if (status && dataStatus !== status) mostrar = false;
            if (primeira && dataPrimeira !== primeira) mostrar = false;
            
            $row.toggle(mostrar);
        });
        
        atualizarContadorModal();
    }
    
    function atualizarContadorModal() {
        const total = $('#tabelaVendasDetalhadas tbody tr').length;
        const visivel = $('#tabelaVendasDetalhadas tbody tr:visible').length;
        $('#contadorVendasModal').text(`Exibindo ${visivel} de ${total} vendas`);
    }
    
    // Eventos de filtro no modal
    setTimeout(function() {
        // Funço para aplicar filtros
        function aplicarFiltrosModal() {
            const busca = $('#buscaTitulo').val().toLowerCase();
            const tipoPag = $('#filtroTipoPagamento').val();
            const status = $('#filtroStatus').val();
            const primeira = $('#filtroPrimeiraParcela').val();
            
            let totalVisivel = 0;
            let somaValorPago = 0;
            
            $('#tabelaVendasDetalhadas tbody tr').each(function() {
                const $row = $(this);
                const dataBusca = $row.attr('data-busca') || '';
                const dataTipoPag = $row.attr('data-tipo-pagamento') || '';
                const dataStatus = $row.attr('data-status') || '';
                const dataPrimeira = $row.attr('data-primeira-parcela') || '';
                
                let mostrar = true;
                
                if (busca && dataBusca.indexOf(busca) === -1) mostrar = false;
                if (tipoPag && !dataTipoPag.includes(tipoPag)) mostrar = false;
                if (status && dataStatus !== status) mostrar = false;
                if (primeira && dataPrimeira !== primeira) mostrar = false;
                
                if (mostrar) {
                    $row.show();
                    totalVisivel++;
                    
                    // Soma valor pago das linhas visíveis
                    const valorTexto = $row.find('td:last strong').text()
                        .replace('R$', '').replace(/\s/g, '')
                        .replace(/\./g, '').replace(',', '.');
                    somaValorPago += parseFloat(valorTexto) || 0;
                } else {
                    $row.hide();
                }
            });
            
            // Atualiza contador
            const totalLinhas = $('#tabelaVendasDetalhadas tbody tr').length;
            $('#contadorVendasModal').html(
                `<i class="fas fa-filter"></i> Exibindo <strong>${totalVisivel}</strong> de <strong>${totalLinhas}</strong> vendas | ` +
                `Total filtrado: <strong>R$ ${somaValorPago.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</strong>`
            );
        }
        
        // Remove eventos anteriores e adiciona novos
        $('#buscaTitulo, #filtroTipoPagamento, #filtroStatus, #filtroPrimeiraParcela')
            .off('change keyup input')
            .on('change keyup input', function() {
                console.log('Filtro acionado:', $(this).attr('id'), '=', $(this).val());
                aplicarFiltrosModal();
            });
        
        // Boto limpar filtros
        $('#btnLimparFiltrosModal').off('click').on('click', function() {
            console.log('Limpando filtros...');
            $('#buscaTitulo').val('');
            $('#filtroTipoPagamento').val('');
            $('#filtroStatus').val('');
            $('#filtroPrimeiraParcela').val('');
            aplicarFiltrosModal();
        });
        
        // Aplica filtros inicial para mostrar contador
        aplicarFiltrosModal();
        
        console.log('Filtros do modal configurados!');
    }, 1500);
});
</script>



<?php endif; ?>



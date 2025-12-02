<?php
// pages/configuracoes.php - Configurações do Sistema

require_once 'functions/vendas.php';
require_once 'functions/configuracoes.php';

$mensagem_sucesso = null;
$mensagem_erro = null;

// ===== CARREGA CONFIGURAÇÕES DO ARQUIVO =====
$config = carregarConfiguracoes();

// ===== PROCESSAR SALVAMENTO =====
if (isset($_POST['salvar_configuracoes'])) {
    
    // Atualiza configurações de premiação
    $config['premiacoes'] = [
        'pontos_por_vaga' => intval($_POST['pontos_por_vaga']),
        'pontos_venda_vista' => intval($_POST['pontos_venda_vista']),
        'vendas_para_sap' => intval($_POST['vendas_para_sap']),
        'vendas_para_dip' => intval($_POST['vendas_para_dip'])
    ];
    
    // Atualiza campos visíveis para consultores
    $config['campos_visiveis_consultores'] = [
        'pontos' => isset($_POST['campo_pontos']),
        'vendas' => isset($_POST['campo_vendas']),
        'valor_total' => isset($_POST['campo_valor_total']),
        'valor_pago' => isset($_POST['campo_valor_pago']),
        'detalhamento' => isset($_POST['campo_detalhamento']),
        'cotas_sap' => isset($_POST['campo_cotas_sap'])
    ];
    
    // Atualiza configurações de acesso
    $config['acesso'] = [
        'relatorio_padrao' => $_POST['relatorio_padrao'] ?? 'top20',
        'senha_filtro' => trim($_POST['senha_filtro'] ?? ''),
        'senha_godmode' => trim($_POST['senha_godmode'] ?? 'admin123')
    ];
    
    // Atualiza configurações de premiação
    $config['premiacao'] = [
        'mensagem' => trim($_POST['mensagem_premiacao'] ?? ''),
        'dia_limite_primeira_parcela' => intval($_POST['dia_limite_primeira_parcela'] ?? 7),
        'exibir_aviso' => isset($_POST['exibir_aviso_premiacao'])
    ];
    
    // Atualiza período padrão do relatório
    $config['periodo_relatorio'] = [
        'data_inicial' => $_POST['periodo_data_inicial'] ?? date('Y-m-01'),
        'data_final' => $_POST['periodo_data_final'] ?? date('Y-m-t'),
        'filtro_status' => $_POST['periodo_status'] ?? 'Ativo',
        'apenas_primeira_parcela' => isset($_POST['periodo_primeira_parcela']),
        'apenas_vista' => isset($_POST['periodo_apenas_vista'])
    ];
    
    // Salva configurações
    $resultado = salvarConfiguracoes($config);
    
    if ($resultado['sucesso']) {
        $mensagem_sucesso = $resultado['mensagem'];
        // Recarrega configurações
        $config = carregarConfiguracoes();
    } else {
        $mensagem_erro = $resultado['mensagem'];
    }
}

// ===== PROCESSAR RANGES =====
if (isset($_POST['adicionar_range'])) {
    $novo_range = [
        'nome' => $_POST['range_nome'],
        'data_inicio' => $_POST['range_data_inicio'],
        'data_fim' => $_POST['range_data_fim'],
        'multiplicador' => floatval($_POST['range_multiplicador']),
        'ativo' => isset($_POST['range_ativo'])
    ];
    
    $config['ranges'][] = $novo_range;
    
    $resultado = salvarConfiguracoes($config);
    if ($resultado['sucesso']) {
        $mensagem_sucesso = "Range adicionado com sucesso!";
        $config = carregarConfiguracoes();
    }
}

if (isset($_POST['remover_range'])) {
    $index = intval($_POST['range_index']);
    
    if (isset($config['ranges'][$index])) {
        array_splice($config['ranges'], $index, 1);
        
        $resultado = salvarConfiguracoes($config);
        if ($resultado['sucesso']) {
            $mensagem_sucesso = "Range removido com sucesso!";
            $config = carregarConfiguracoes();
        }
    }
}

// ===== EXPORTAR CONFIGURAÇÕES =====
if (isset($_GET['exportar'])) {
    exportarConfiguracoes();
}

// ===== IMPORTAR CONFIGURAÇÕES =====
if (isset($_POST['importar_config']) && isset($_FILES['arquivo_config'])) {
    $arquivo_temp = $_FILES['arquivo_config']['tmp_name'];
    
    $resultado = importarConfiguracoes($arquivo_temp);
    
    if ($resultado['sucesso']) {
        $mensagem_sucesso = $resultado['mensagem'];
        $config = carregarConfiguracoes();
    } else {
        $mensagem_erro = $resultado['mensagem'];
    }
}

// ===== RESETAR CONFIGURAÇÕES =====
if (isset($_POST['resetar_config'])) {
    $resultado = resetarConfiguracoes();
    
    if ($resultado['sucesso']) {
        $mensagem_sucesso = "Configurações resetadas para padrão!";
        $config = carregarConfiguracoes();
    }
}
?>

<div class="row">
    <div class="col-md-12">
        
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
        
        <!-- Card de Informações -->
        <div class="card mb-3">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle"></i> Informações do Sistema
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Arquivo de Configuração:</strong> <?= CONFIG_FILE ?></p>
                        <p><strong>Última Atualização:</strong> <?= $config['ultima_atualizacao'] ?? 'Nunca' ?></p>
                    </div>
                    <div class="col-md-6 text-right">
                        <a href="?page=configuracoes&exportar=1" class="btn btn-success">
                            <i class="fas fa-download"></i> Exportar Configurações
                        </a>
                        
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modalImportar">
                            <i class="fas fa-upload"></i> Importar Configurações
                        </button>
                        
                        <button type="button" class="btn btn-warning" data-toggle="modal" data-target="#modalResetar">
                            <i class="fas fa-undo"></i> Resetar para Padrão
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <form method="post">
            
            <!-- Card: Configurações de Acesso -->
            <div class="card mb-3">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-lock"></i> Configurações de Acesso
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-chart-bar"></i> Relatório Padrão para Consultores
                                </label>
                                <select class="form-control" name="relatorio_padrao">
                                    <option value="top20" <?= ($config['acesso']['relatorio_padrao'] ?? '') === 'top20' ? 'selected' : '' ?>>
                                        Top 20
                                    </option>
                                    <option value="ranking_completo" <?= ($config['acesso']['relatorio_padrao'] ?? '') === 'ranking_completo' ? 'selected' : '' ?>>
                                        Ranking Completo
                                    </option>
                                </select>
                                <small class="form-text text-muted">
                                    Consultores serão redirecionados para este relatório automaticamente
                                </small>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-key"></i> Senha GodMode
                                </label>
                                <input type="text" class="form-control" name="senha_godmode" 
                                       value="<?= htmlspecialchars($config['acesso']['senha_godmode'] ?? 'admin123') ?>"
                                       placeholder="admin123">
                                <small class="form-text text-muted">
                                    Use: <code>?godmode=SENHA</code> para acesso admin
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-filter"></i> Senha para Liberar Filtros
                                </label>
                                <input type="text" class="form-control" name="senha_filtro" 
                                       value="<?= htmlspecialchars($config['acesso']['senha_filtro'] ?? '') ?>"
                                       placeholder="Deixe vazio para desativar">
                                <small class="form-text text-muted">
                                    <?php if (!empty($config['acesso']['senha_filtro'])): ?>
                                        URL com filtros liberados: 
                                        <code><?= gerarUrlComFiltro('ranking_completo') ?></code>
                                    <?php else: ?>
                                        Configure uma senha para gerar URL com filtros liberados
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Card: Período Padrão do Relatório -->
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar"></i> Período Padrão do Relatório
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Data Inicial</label>
                                <input type="date" class="form-control" name="periodo_data_inicial" 
                                       value="<?= $config['periodo_relatorio']['data_inicial'] ?? date('Y-m-01') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Data Final</label>
                                <input type="date" class="form-control" name="periodo_data_final" 
                                       value="<?= $config['periodo_relatorio']['data_final'] ?? date('Y-m-t') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Status Padrão</label>
                                <select class="form-control" name="periodo_status">
                                    <option value="" <?= empty($config['periodo_relatorio']['filtro_status']) ? 'selected' : '' ?>>
                                        Todos
                                    </option>
                                    <option value="Ativo" <?= ($config['periodo_relatorio']['filtro_status'] ?? '') === 'Ativo' ? 'selected' : '' ?>>
                                        Ativo
                                    </option>
                                    <option value="Inativo" <?= ($config['periodo_relatorio']['filtro_status'] ?? '') === 'Inativo' ? 'selected' : '' ?>>
                                        Inativo
                                    </option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-check mt-4">
                                <input type="checkbox" class="form-check-input" name="periodo_primeira_parcela" 
                                       id="periodo_primeira_parcela"
                                       <?= !empty($config['periodo_relatorio']['apenas_primeira_parcela']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="periodo_primeira_parcela">
                                    Apenas com 1ª Parcela Paga
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-check mt-4">
                                <input type="checkbox" class="form-check-input" name="periodo_apenas_vista" 
                                       id="periodo_apenas_vista"
                                       <?= !empty($config['periodo_relatorio']['apenas_vista']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="periodo_apenas_vista">
                                    Apenas Vendas à Vista
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Card: Configurações de Premiação -->
            <div class="card mb-3">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-trophy"></i> Configurações de Premiação
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Pontos por Vaga</label>
                                <input type="number" class="form-control" name="pontos_por_vaga"
                                       value="<?= $config['premiacoes']['pontos_por_vaga'] ?? 3 ?>" min="1">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Pontos Venda à Vista</label>
                                <input type="number" class="form-control" name="pontos_venda_vista"
                                       value="<?= $config['premiacoes']['pontos_venda_vista'] ?? 2 ?>" min="1">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Vendas Necessárias para SAP</label>
                                <input type="number" class="form-control" name="vendas_para_sap"
                                       value="<?= $config['premiacoes']['vendas_para_sap'] ?? 21 ?>" min="1">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Vendas Necessárias para DIP</label>
                                <input type="number" class="form-control" name="vendas_para_dip"
                                       value="<?= $config['premiacoes']['vendas_para_dip'] ?? 500 ?>" min="1">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card: Mensagem de Premiação -->
            <div class="card mb-3">
                <div class="card-header bg-warning">
                    <h5 class="mb-0">
                        <i class="fas fa-bell"></i> Aviso de Premiação
                    </h5>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Mensagem de Aviso Global</label>
                        <textarea class="form-control" name="mensagem_premiacao" rows="3"><?= htmlspecialchars($config['premiacao']['mensagem'] ?? '') ?></textarea>
                        <small class="form-text text-muted">Esta mensagem será exibida no topo de todas as páginas</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Dia Limite para 1ª Parcela</label>
                                <input type="number" class="form-control" name="dia_limite_primeira_parcela"
                                       value="<?= $config['premiacao']['dia_limite_primeira_parcela'] ?? 7 ?>"
                                       min="1" max="31">
                                <small class="form-text text-muted">Ex: 7 = até dia 07 do mês seguinte</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check mt-4">
                                <input type="checkbox" class="form-check-input" name="exibir_aviso_premiacao"
                                       id="exibir_aviso_premiacao"
                                       <?= !empty($config['premiacao']['exibir_aviso']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="exibir_aviso_premiacao">
                                    Exibir aviso nas páginas
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card: Campos Visíveis para Consultores -->
            <div class="card mb-3">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-eye"></i> Campos Visíveis para Consultores
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="campo_pontos"
                                       id="campo_pontos"
                                       <?= !empty($config['campos_visiveis_consultores']['pontos']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="campo_pontos">Pontos</label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="campo_vendas"
                                       id="campo_vendas"
                                       <?= !empty($config['campos_visiveis_consultores']['vendas']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="campo_vendas">Vendas</label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="campo_valor_total"
                                       id="campo_valor_total"
                                       <?= !empty($config['campos_visiveis_consultores']['valor_total']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="campo_valor_total">Valor Total</label>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-2">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="campo_valor_pago"
                                       id="campo_valor_pago"
                                       <?= !empty($config['campos_visiveis_consultores']['valor_pago']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="campo_valor_pago">Valor Pago</label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="campo_detalhamento"
                                       id="campo_detalhamento"
                                       <?= !empty($config['campos_visiveis_consultores']['detalhamento']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="campo_detalhamento">Detalhamento</label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="campo_cotas_sap"
                                       id="campo_cotas_sap"
                                       <?= !empty($config['campos_visiveis_consultores']['cotas_sap']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="campo_cotas_sap">DIPs / Cotas SAP</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card: Ranges de Pontuação -->
            <div class="card mb-3">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-alt"></i> Ranges de Pontuação Especial
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($config['ranges'])): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Período</th>
                                        <th>Multiplicador</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($config['ranges'] as $index => $range): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($range['nome']) ?></td>
                                            <td>
                                                <?= date('d/m/Y', strtotime($range['data_inicio'])) ?>
                                                até
                                                <?= date('d/m/Y', strtotime($range['data_fim'])) ?>
                                            </td>
                                            <td><?= $range['multiplicador'] ?>x</td>
                                            <td>
                                                <?php if ($range['ativo']): ?>
                                                    <span class="badge badge-success">Ativo</span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">Inativo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="range_index" value="<?= $index ?>">
                                                    <button type="submit" name="remover_range" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Nenhum range configurado</p>
                    <?php endif; ?>

                    <hr>

                    <h6>Adicionar Novo Range</h6>
                    <form method="post">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Nome</label>
                                    <input type="text" class="form-control" name="range_nome" required>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Data Início</label>
                                    <input type="date" class="form-control" name="range_data_inicio" required>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Data Fim</label>
                                    <input type="date" class="form-control" name="range_data_fim" required>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Multiplicador</label>
                                    <input type="number" step="0.1" class="form-control" name="range_multiplicador"
                                           value="1.5" min="0.1" required>
                                </div>
                            </div>

                            <div class="col-md-1">
                                <div class="form-check mt-4">
                                    <input type="checkbox" class="form-check-input" name="range_ativo"
                                           id="range_ativo" checked>
                                    <label class="form-check-label" for="range_ativo">Ativo</label>
                                </div>
                            </div>
                        </div>

                        <button type="submit" name="adicionar_range" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Adicionar Range
                        </button>
                    </form>
                </div>
            </div>

            <!-- Botão Salvar -->
            <div class="card">
                <div class="card-body text-center">
                    <button type="submit" name="salvar_configuracoes" class="btn btn-success btn-lg">
                        <i class="fas fa-save"></i> Salvar Todas as Configurações
                    </button>
                </div>
            </div>
            
        </form>
    </div>
</div>

<!-- Modal: Importar Configurações -->
<div class="modal fade" id="modalImportar">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Importar Configurações</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Selecione o arquivo JSON:</label>
                        <input type="file" class="form-control-file" name="arquivo_config" accept=".json" required>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Atenção:</strong> Isso substituirá TODAS as configurações atuais!
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" name="importar_config" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Importar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Resetar Configurações -->
<div class="modal fade" id="modalResetar">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">Resetar Configurações</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Atenção:</strong> Isso irá restaurar TODAS as configurações para os valores padrão!
                        Esta ação não pode ser desfeita.
                    </div>
                    <p>Tem certeza que deseja continuar?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" name="resetar_config" class="btn btn-warning">
                        <i class="fas fa-undo"></i> Sim, Resetar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
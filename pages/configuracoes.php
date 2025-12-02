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

    // Atualiza pontos padrão
    $config['pontos_padrao'] = [
        '1vaga' => intval($_POST['pontos_1vaga'] ?? 1),
        '2vagas' => intval($_POST['pontos_2vagas'] ?? 2),
        '3vagas' => intval($_POST['pontos_3vagas'] ?? 2),
        '4vagas' => intval($_POST['pontos_4vagas'] ?? 3),
        '5vagas' => intval($_POST['pontos_5vagas'] ?? 3),
        '6vagas' => intval($_POST['pontos_6vagas'] ?? 3),
        '7vagas' => intval($_POST['pontos_7vagas'] ?? 3),
        '8vagas' => intval($_POST['pontos_8vagas'] ?? 4),
        '9vagas' => intval($_POST['pontos_9vagas'] ?? 4),
        '10vagas' => intval($_POST['pontos_10vagas'] ?? 4),
        'acima_10' => intval($_POST['pontos_acima_10'] ?? 4),
        'vista_acima_5' => intval($_POST['pontos_vista_acima_5'] ?? 5)
    ];

    // Atualiza tipos de premiação
    if (isset($_POST['tipos_premiacao']) && is_array($_POST['tipos_premiacao'])) {
        $config['tipos_premiacao'] = [];
        foreach ($_POST['tipos_premiacao'] as $index => $tipo) {
            $config['tipos_premiacao'][] = [
                'nome' => trim($tipo['nome']),
                'pontos_necessarios' => intval($tipo['pontos_necessarios']),
                'ativo' => isset($tipo['ativo']),
                'descricao' => trim($tipo['descricao'] ?? '')
            ];
        }
    }

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
        'ativo' => isset($_POST['range_ativo']),
        'pontos' => [
            '1vaga' => intval($_POST['range_pontos_1vaga'] ?? 1),
            '2vagas' => intval($_POST['range_pontos_2vagas'] ?? 2),
            '3vagas' => intval($_POST['range_pontos_3vagas'] ?? 2),
            '4vagas' => intval($_POST['range_pontos_4vagas'] ?? 3),
            '5vagas' => intval($_POST['range_pontos_5vagas'] ?? 3),
            '6vagas' => intval($_POST['range_pontos_6vagas'] ?? 3),
            '7vagas' => intval($_POST['range_pontos_7vagas'] ?? 3),
            '8vagas' => intval($_POST['range_pontos_8vagas'] ?? 4),
            '9vagas' => intval($_POST['range_pontos_9vagas'] ?? 4),
            '10vagas' => intval($_POST['range_pontos_10vagas'] ?? 4),
            'acima_10' => intval($_POST['range_pontos_acima_10'] ?? 4),
            'vista_acima_5' => intval($_POST['range_pontos_vista_acima_5'] ?? 5)
        ]
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

// ===== PROCESSAR TIPOS DE PREMIAÇÃO =====
if (isset($_POST['adicionar_tipo_premiacao'])) {
    $novo_tipo = [
        'nome' => trim($_POST['tipo_nome']),
        'pontos_necessarios' => intval($_POST['tipo_pontos']),
        'ativo' => isset($_POST['tipo_ativo']),
        'descricao' => trim($_POST['tipo_descricao'] ?? '')
    ];

    if (!isset($config['tipos_premiacao'])) {
        $config['tipos_premiacao'] = [];
    }

    $config['tipos_premiacao'][] = $novo_tipo;

    $resultado = salvarConfiguracoes($config);
    if ($resultado['sucesso']) {
        $mensagem_sucesso = "Tipo de premiação adicionado com sucesso!";
        $config = carregarConfiguracoes();
    }
}

if (isset($_POST['remover_tipo_premiacao'])) {
    $index = intval($_POST['tipo_index']);

    if (isset($config['tipos_premiacao'][$index])) {
        array_splice($config['tipos_premiacao'], $index, 1);

        $resultado = salvarConfiguracoes($config);
        if ($resultado['sucesso']) {
            $mensagem_sucesso = "Tipo de premiação removido com sucesso!";
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
            
            <!-- Card: Pontos Padrão -->
            <div class="card mb-3">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-star"></i> Pontos Padrão por Faixa de Vagas
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Configure quantos pontos cada venda vale de acordo com o número de vagas</p>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>1 Vaga</label>
                                <input type="number" class="form-control" name="pontos_1vaga"
                                       value="<?= $config['pontos_padrao']['1vaga'] ?? 1 ?>" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>2 Vagas</label>
                                <input type="number" class="form-control" name="pontos_2vagas"
                                       value="<?= $config['pontos_padrao']['2vagas'] ?? 2 ?>" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>3 Vagas</label>
                                <input type="number" class="form-control" name="pontos_3vagas"
                                       value="<?= $config['pontos_padrao']['3vagas'] ?? 2 ?>" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>4 Vagas</label>
                                <input type="number" class="form-control" name="pontos_4vagas"
                                       value="<?= $config['pontos_padrao']['4vagas'] ?? 3 ?>" min="0">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>5 Vagas</label>
                                <input type="number" class="form-control" name="pontos_5vagas"
                                       value="<?= $config['pontos_padrao']['5vagas'] ?? 3 ?>" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>6 Vagas</label>
                                <input type="number" class="form-control" name="pontos_6vagas"
                                       value="<?= $config['pontos_padrao']['6vagas'] ?? 3 ?>" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>7 Vagas</label>
                                <input type="number" class="form-control" name="pontos_7vagas"
                                       value="<?= $config['pontos_padrao']['7vagas'] ?? 3 ?>" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>8 Vagas</label>
                                <input type="number" class="form-control" name="pontos_8vagas"
                                       value="<?= $config['pontos_padrao']['8vagas'] ?? 4 ?>" min="0">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>9 Vagas</label>
                                <input type="number" class="form-control" name="pontos_9vagas"
                                       value="<?= $config['pontos_padrao']['9vagas'] ?? 4 ?>" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>10 Vagas</label>
                                <input type="number" class="form-control" name="pontos_10vagas"
                                       value="<?= $config['pontos_padrao']['10vagas'] ?? 4 ?>" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Acima de 10 Vagas</label>
                                <input type="number" class="form-control" name="pontos_acima_10"
                                       value="<?= $config['pontos_padrao']['acima_10'] ?? 4 ?>" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Vista (acima 5 vagas)</label>
                                <input type="number" class="form-control" name="pontos_vista_acima_5"
                                       value="<?= $config['pontos_padrao']['vista_acima_5'] ?? 5 ?>" min="0">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card: Tipos de Premiação -->
            <div class="card mb-3">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-trophy"></i> Tipos de Premiação
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Configure os tipos de premiação disponíveis (SAP, DIP, CONVITES, etc)</p>

                    <?php if (!empty($config['tipos_premiacao'])): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Pontos Necessários</th>
                                        <th>Descrição</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($config['tipos_premiacao'] as $index => $tipo): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($tipo['nome']) ?></strong></td>
                                            <td><?= $tipo['pontos_necessarios'] ?> pontos</td>
                                            <td><?= htmlspecialchars($tipo['descricao'] ?? '') ?></td>
                                            <td>
                                                <?php if ($tipo['ativo']): ?>
                                                    <span class="badge badge-success">Ativo</span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">Inativo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="tipo_index" value="<?= $index ?>">
                                                    <button type="submit" name="remover_tipo_premiacao" class="btn btn-sm btn-danger">
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
                        <p class="text-muted">Nenhum tipo de premiação configurado</p>
                    <?php endif; ?>

                    <hr>

                    <h6>Adicionar Novo Tipo de Premiação</h6>
                    <form method="post">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Nome *</label>
                                    <input type="text" class="form-control" name="tipo_nome"
                                           placeholder="Ex: SAP, DIP, CONVITES" required>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Pontos Necessários *</label>
                                    <input type="number" class="form-control" name="tipo_pontos"
                                           value="21" min="1" required>
                                </div>
                            </div>

                            <div class="col-md-5">
                                <div class="form-group">
                                    <label>Descrição</label>
                                    <input type="text" class="form-control" name="tipo_descricao"
                                           placeholder="Descrição da premiação">
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-check mt-4">
                                    <input type="checkbox" class="form-check-input" name="tipo_ativo"
                                           id="tipo_ativo" checked>
                                    <label class="form-check-label" for="tipo_ativo">Ativo</label>
                                </div>
                            </div>
                        </div>

                        <button type="submit" name="adicionar_tipo_premiacao" class="btn btn-success">
                            <i class="fas fa-plus"></i> Adicionar Tipo de Premiação
                        </button>
                    </form>
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
                        <i class="fas fa-calendar-alt"></i> Ranges de Pontuação Especial por Período
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Configure pontuações especiais para períodos específicos (ex: Black Friday, Natal, etc)</p>

                    <?php if (!empty($config['ranges'])): ?>
                        <div class="table-responsive mb-3">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Período</th>
                                        <th>Pontuação Customizada</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($config['ranges'] as $index => $range): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($range['nome']) ?></strong></td>
                                            <td>
                                                <?= date('d/m/Y', strtotime($range['data_inicio'])) ?>
                                                até
                                                <?= date('d/m/Y', strtotime($range['data_fim'])) ?>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php if (isset($range['pontos'])): ?>
                                                        1v: <?= $range['pontos']['1vaga'] ?? 0 ?>,
                                                        2-3v: <?= $range['pontos']['2vagas'] ?? 0 ?>-<?= $range['pontos']['3vagas'] ?? 0 ?>,
                                                        4-7v: <?= $range['pontos']['4vagas'] ?? 0 ?>-<?= $range['pontos']['7vagas'] ?? 0 ?>,
                                                        8-10v: <?= $range['pontos']['8vagas'] ?? 0 ?>-<?= $range['pontos']['10vagas'] ?? 0 ?>,
                                                        >10v: <?= $range['pontos']['acima_10'] ?? 0 ?>,
                                                        Vista >5v: <?= $range['pontos']['vista_acima_5'] ?? 0 ?>
                                                    <?php else: ?>
                                                        <span class="text-danger">Sem pontuação configurada</span>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
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

                    <h6><i class="fas fa-plus-circle"></i> Adicionar Novo Range</h6>
                    <form method="post">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Nome do Range *</label>
                                    <input type="text" class="form-control" name="range_nome"
                                           placeholder="Ex: Black Friday 2025" required>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Data Início *</label>
                                    <input type="date" class="form-control" name="range_data_inicio" required>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Data Fim *</label>
                                    <input type="date" class="form-control" name="range_data_fim" required>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-check mt-4">
                                    <input type="checkbox" class="form-check-input" name="range_ativo"
                                           id="range_ativo" checked>
                                    <label class="form-check-label" for="range_ativo">Ativo</label>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Pontuação para este range:</strong> Configure quantos pontos cada venda vale durante este período
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>1 Vaga</label>
                                    <input type="number" class="form-control" name="range_pontos_1vaga" value="1" min="0">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>2 Vagas</label>
                                    <input type="number" class="form-control" name="range_pontos_2vagas" value="2" min="0">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>3 Vagas</label>
                                    <input type="number" class="form-control" name="range_pontos_3vagas" value="2" min="0">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>4 Vagas</label>
                                    <input type="number" class="form-control" name="range_pontos_4vagas" value="3" min="0">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>5 Vagas</label>
                                    <input type="number" class="form-control" name="range_pontos_5vagas" value="3" min="0">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>6 Vagas</label>
                                    <input type="number" class="form-control" name="range_pontos_6vagas" value="3" min="0">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>7 Vagas</label>
                                    <input type="number" class="form-control" name="range_pontos_7vagas" value="3" min="0">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>8 Vagas</label>
                                    <input type="number" class="form-control" name="range_pontos_8vagas" value="4" min="0">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>9 Vagas</label>
                                    <input type="number" class="form-control" name="range_pontos_9vagas" value="4" min="0">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>10 Vagas</label>
                                    <input type="number" class="form-control" name="range_pontos_10vagas" value="4" min="0">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Acima de 10 Vagas</label>
                                    <input type="number" class="form-control" name="range_pontos_acima_10" value="4" min="0">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Vista (acima 5 vagas)</label>
                                    <input type="number" class="form-control" name="range_pontos_vista_acima_5" value="5" min="0">
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
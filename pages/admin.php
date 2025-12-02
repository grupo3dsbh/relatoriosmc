<?php
// pages/admin.php - Painel administrativo (COMPLETO)

/**
 * Garante que todos os ranges tenham a chave 'ativo'
 * Preserva se já existir, adiciona true por padrão se não existir
 */
function garantirChaveAtivoRanges(&$ranges) {
    if (!is_array($ranges)) {
        return;
    }

    foreach ($ranges as &$range) {
        if (!isset($range['ativo'])) {
            $range['ativo'] = true; // Ativo por padrão
        }
    }
}

// Processa login
if (isset($_POST['login_admin'])) {
    $senha = $_POST['senha'] ?? '';
    if (loginAdmin($senha)) {
        header('Location: ?page=admin');
        exit;
    } else {
        $erro_login = "Senha incorreta!";
    }
}

// Processa upload de logo
if (isset($_POST['upload_logo']) && isset($_FILES['logo'])) {
    if ($_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $resultado = salvarLogo($_FILES['logo']['tmp_name']);
        if ($resultado['sucesso']) {
            $mensagem_sucesso = "Logo atualizado com sucesso!";
        } else {
            $erro_upload = $resultado['erro'];
        }
    }
}

// Processa remoção de logo
if (isset($_POST['remover_logo'])) {
    if (file_exists(LOGO_PATH)) {
        unlink(LOGO_PATH);
        $mensagem_sucesso = "Logo removido com sucesso!";
    }
}

// Processa alteração de senha de consultores
if (isset($_POST['alterar_senha_consultores'])) {
    $_SESSION['senha_consultores'] = $_POST['nova_senha'];
    $mensagem_sucesso = "Senha de consultores alterada com sucesso!";
}

// Processa upload de CSV de vendas
if (isset($_POST['upload_vendas']) && isset($_FILES['csv_vendas'])) {
    if ($_FILES['csv_vendas']['error'] === UPLOAD_ERR_OK) {
        $resultado = salvarCSV($_FILES['csv_vendas']['tmp_name'], 'vendas');
        if ($resultado['sucesso']) {
            $mensagem_sucesso = "CSV de vendas enviado com sucesso! Arquivo: {$resultado['nome']}";
        } else {
            $erro_upload = $resultado['erro'];
        }
    }
}

// Processa upload de CSV de promotores
if (isset($_POST['upload_promotores']) && isset($_FILES['csv_promotores'])) {
    if ($_FILES['csv_promotores']['error'] === UPLOAD_ERR_OK) {
        $resultado = salvarCSV($_FILES['csv_promotores']['tmp_name'], 'promotores');
        if ($resultado['sucesso']) {
            require_once 'functions/promotores.php';
            $_SESSION['promotores'] = processarPromotoresCSV($resultado['caminho']);
            $mensagem_sucesso = "CSV de promotores enviado com sucesso! {$_SESSION['promotores']['total']} promotores carregados.";
        } else {
            $erro_upload = $resultado['erro'];
        }
    }
}

// Processa exclusão de arquivo
if (isset($_POST['excluir_arquivo'])) {
    $arquivo = $_POST['arquivo_path'];
    if (file_exists($arquivo) && unlink($arquivo)) {
        $mensagem_sucesso = "Arquivo excluído com sucesso!";
    } else {
        $erro_upload = "Erro ao excluir arquivo!";
    }
}

// Gerenciamento de Ranges de Pontuação
if (!isset($_SESSION['ranges_pontuacao'])) {
    $_SESSION['ranges_pontuacao'] = [];
}

// Adicionar novo range
if (isset($_POST['adicionar_range'])) {
    $novo_range = [
        'id' => uniqid(),
        'nome' => $_POST['nome_range'],
        'data_inicio' => $_POST['data_inicio_range'],
        'data_fim' => $_POST['data_fim_range'],
        'ativo' => true, // IMPORTANTE: Range ativo por padrão
        'pontos' => [
            '1vaga' => intval($_POST['range_1vaga'] ?? 1),
            '2vagas' => intval($_POST['range_2vagas'] ?? 2),
            '3vagas' => intval($_POST['range_3vagas'] ?? 2),
            '4vagas' => intval($_POST['range_4vagas'] ?? 3),
            '5vagas' => intval($_POST['range_5vagas'] ?? 3),
            '6vagas' => intval($_POST['range_6vagas'] ?? 3),
            '7vagas' => intval($_POST['range_7vagas'] ?? 3),
            '8vagas' => intval($_POST['range_8vagas'] ?? 4),
            '9vagas' => intval($_POST['range_9vagas'] ?? 4),
            '10vagas' => intval($_POST['range_10vagas'] ?? 4),
            'acima_10' => intval($_POST['range_acima_10'] ?? 4),
            'vista_acima_5' => intval($_POST['range_vista_acima_5'] ?? 5)
        ]
    ];
    
    $_SESSION['ranges_pontuacao'][] = $novo_range;

    // Salva no config.json
    garantirChaveAtivoRanges($_SESSION['ranges_pontuacao']);
    $_SESSION['config_sistema']['ranges'] = $_SESSION['ranges_pontuacao'];
    salvarConfiguracoes($_SESSION['config_sistema']);

    $mensagem_sucesso = "Range de pontuação '{$novo_range['nome']}' adicionado com sucesso!";
}

// Remover range
if (isset($_POST['remover_range'])) {
    $id_remover = $_POST['range_id'];
    $_SESSION['ranges_pontuacao'] = array_filter($_SESSION['ranges_pontuacao'], function($r) use ($id_remover) {
        return $r['id'] !== $id_remover;
    });
    $_SESSION['ranges_pontuacao'] = array_values($_SESSION['ranges_pontuacao']);

    // Salva no config.json
    garantirChaveAtivoRanges($_SESSION['ranges_pontuacao']);
    $_SESSION['config_sistema']['ranges'] = $_SESSION['ranges_pontuacao'];
    salvarConfiguracoes($_SESSION['config_sistema']);

    $mensagem_sucesso = "Range de pontuação removido com sucesso!";
}

// Processar configuração de campos visíveis
if (isset($_POST['salvar_campos_visiveis'])) {
    $_SESSION['campos_visiveis_consultores'] = [
        'pontos' => isset($_POST['campo_pontos']),
        'vendas' => isset($_POST['campo_vendas']),
        'valor_total' => isset($_POST['campo_valor_total']),
        'valor_pago' => isset($_POST['campo_valor_pago']),
        'detalhamento' => isset($_POST['campo_detalhamento']),
        'cotas_sap' => isset($_POST['campo_cotas_sap'])
    ];
    $mensagem_sucesso = "Campos visíveis atualizados com sucesso!";
}

// Processar configuração de premiações
if (isset($_POST['salvar_config_premiacoes'])) {
    $_SESSION['config_premiacoes'] = [
        'pontos_por_sap' => intval($_POST['pontos_por_sap']),
        'vendas_para_dip' => intval($_POST['vendas_para_dip']),
        'vendas_acima_2vagas_para_dip' => intval($_POST['vendas_acima_2vagas_para_dip'])
    ];

    // IMPORTANTE: Salva no config.json para persistir entre sessões
    $_SESSION['config_sistema']['premiacoes'] = $_SESSION['config_premiacoes'];

    // IMPORTANTE: Preserva ranges com chave 'ativo' antes de salvar
    if (isset($_SESSION['ranges_pontuacao'])) {
        garantirChaveAtivoRanges($_SESSION['ranges_pontuacao']);
        $_SESSION['config_sistema']['ranges'] = $_SESSION['ranges_pontuacao'];
    }

    salvarConfiguracoes($_SESSION['config_sistema']);

    $mensagem_sucesso = "Configurações de premiação atualizadas com sucesso!";
}

// Se não estiver autenticado, mostra tela de login
if (!verificarAdmin()):
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-danger text-white text-center">
                <h4><i class="fas fa-lock"></i> Área Administrativa</h4>
            </div>
            <div class="card-body">
                <?php if (isset($erro_login)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?= $erro_login ?>
                    </div>
                <?php endif; ?>
                
                <form method="post">
                    <div class="form-group">
                        <label for="senha">
                            <i class="fas fa-key"></i> Senha de Administrador
                        </label>
                        <input type="password" class="form-control" id="senha" name="senha" 
                               placeholder="Digite a senha" required autofocus>
                    </div>
                    
                    <button type="submit" name="login_admin" class="btn btn-danger btn-block">
                        <i class="fas fa-sign-in-alt"></i> Entrar
                    </button>
                </form>
                
                <hr>
                
                <div class="alert alert-info mb-0">
                    <small>
                        <i class="fas fa-info-circle"></i>
                        <strong>Acesso restrito:</strong> Esta área é acessível apenas com a senha de administrador.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php else: ?>

<!-- ============================================ -->
<!-- PAINEL ADMIN AUTENTICADO -->
<!-- ============================================ -->

<div class="row">
    <div class="col-md-12">
        <div class="alert alert-success d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-check-circle"></i>
                <strong>Bem-vindo ao Painel Administrativo!</strong> Você está autenticado como administrador.
            </div>
            <a href="?page=configuracoes" class="btn btn-primary btn-sm">
                <i class="fas fa-cog"></i> Configurações Detalhadas
            </a>
        </div>
    </div>
</div>

<?php if (isset($mensagem_sucesso)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?= $mensagem_sucesso ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<?php if (isset($erro_upload)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle"></i> <?= $erro_upload ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<!-- Upload de Logo -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">
                    <i class="fas fa-image"></i> Gerenciamento de Logo
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="text-center">
                            <?php if (temLogo()): ?>
                                <div class="mb-3">
                                    <img src="<?= getLogoURL() ?>" alt="Logo Atual" class="img-fluid" style="max-height: 150px;">
                                </div>
                                <form method="post" style="display: inline;">
                                    <button type="submit" name="remover_logo" class="btn btn-danger btn-sm"
                                            onclick="return confirm('Deseja remover o logo atual?')">
                                        <i class="fas fa-trash"></i> Remover Logo
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Nenhum logo configurado
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <form method="post" enctype="multipart/form-data">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-upload"></i> 
                                    <?= temLogo() ? 'Substituir Logo' : 'Enviar Logo' ?>
                                </label>
                                <input type="file" class="form-control-file" name="logo" 
                                       accept="image/png,image/jpeg,image/jpg,image/gif" required>
                                <small class="form-text text-muted">
                                    Formatos aceitos: PNG, JPG, GIF. Tamanho máximo: 2MB
                                </small>
                            </div>
                            
                            <button type="submit" name="upload_logo" class="btn btn-primary">
                                <i class="fas fa-cloud-upload-alt"></i> Enviar Logo
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Gerenciamento de Ranges de Pontuação -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-star"></i> Ranges de Pontuação por Perodo
                </h5>
            </div>
            <div class="card-body">
                
                <!-- Lista de Ranges Existentes -->
                <?php if (!empty($_SESSION['ranges_pontuacao'])): ?>
                <div class="table-responsive mb-4">
                    <table class="table table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>Nome</th>
                                <th>Período</th>
                                <th>Pontuação</th>
                                <th class="text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($_SESSION['ranges_pontuacao'] as $range): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($range['nome']) ?></strong></td>
                                <td>
                                    <?= date('d/m/Y', strtotime($range['data_inicio'])) ?> até 
                                    <?= date('d/m/Y', strtotime($range['data_fim'])) ?>
                                </td>
                                <td>
                                    <small>
                                        1v=<?= $range['pontos']['1vaga'] ?? 1 ?>,
                                        2v=<?= $range['pontos']['2vagas'] ?? 2 ?>,
                                        3v=<?= $range['pontos']['3vagas'] ?? 2 ?>,
                                        4v=<?= $range['pontos']['4vagas'] ?? 3 ?>,
                                        5v=<?= $range['pontos']['5vagas'] ?? 3 ?>,
                                        6v=<?= $range['pontos']['6vagas'] ?? 3 ?>,
                                        7v=<?= $range['pontos']['7vagas'] ?? 3 ?>,
                                        8v=<?= $range['pontos']['8vagas'] ?? 4 ?>,
                                        9v=<?= $range['pontos']['9vagas'] ?? 4 ?>,
                                        10v=<?= $range['pontos']['10vagas'] ?? 4 ?>,
                                        >10=<?= $range['pontos']['acima_10'] ?? 4 ?>,
                                        >5(vista)=<?= $range['pontos']['vista_acima_5'] ?? 5 ?>
                                    </small>
                                </td>
                                <td class="text-center">
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="range_id" value="<?= $range['id'] ?>">
                                        <button type="submit" name="remover_range" class="btn btn-sm btn-danger"
                                                onclick="return confirm('Deseja remover este range?')">
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
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    Nenhum range de pontuação personalizado configurado. 
                    O sistema usará a pontuação padro para todas as datas.
                </div>
                <?php endif; ?>
                
                <!-- Formulrio para Adicionar Novo Range -->
                <div class="card bg-light">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-plus"></i> Adicionar Novo Range de Pontuação
                            <button type="button" class="btn btn-sm btn-info float-right" 
                                    data-toggle="collapse" data-target="#formNovoRange">
                                <i class="fas fa-caret-down"></i>
                            </button>
                        </h6>
                    </div>
                    <div class="collapse" id="formNovoRange">
                        <div class="card-body">
                            <form method="post">
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label><strong>Nome do Range</strong></label>
                                        <input type="text" class="form-control" name="nome_range" 
                                               placeholder="Ex: Black Friday 2025" required>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label><strong>Data Início</strong></label>
                                        <input type="date" class="form-control" name="data_inicio_range" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label><strong>Data Fim</strong></label>
                                        <input type="date" class="form-control" name="data_fim_range" required>
                                    </div>
                                </div>
                                
                                <h6 class="mb-2"><strong>Pontuação por Quantidade de Vagas:</strong></h6>
                                <div class="row">
                                    <div class="col-md-2 col-6 mb-2">
                                        <label class="small"><strong>1 vaga</strong></label>
                                        <input type="number" class="form-control form-control-sm" name="range_1vaga"
                                               value="1" min="0" required>
                                    </div>
                                    <div class="col-md-2 col-6 mb-2">
                                        <label class="small"><strong>2 vagas</strong></label>
                                        <input type="number" class="form-control form-control-sm" name="range_2vagas"
                                               value="2" min="0" required>
                                    </div>
                                    <div class="col-md-2 col-6 mb-2">
                                        <label class="small"><strong>3 vagas</strong></label>
                                        <input type="number" class="form-control form-control-sm" name="range_3vagas"
                                               value="2" min="0" required>
                                    </div>
                                    <div class="col-md-2 col-6 mb-2">
                                        <label class="small"><strong>4 vagas</strong></label>
                                        <input type="number" class="form-control form-control-sm" name="range_4vagas"
                                               value="3" min="0" required>
                                    </div>
                                    <div class="col-md-2 col-6 mb-2">
                                        <label class="small"><strong>5 vagas</strong></label>
                                        <input type="number" class="form-control form-control-sm" name="range_5vagas"
                                               value="3" min="0" required>
                                    </div>
                                    <div class="col-md-2 col-6 mb-2">
                                        <label class="small"><strong>6 vagas</strong></label>
                                        <input type="number" class="form-control form-control-sm" name="range_6vagas"
                                               value="3" min="0" required>
                                    </div>
                                    <div class="col-md-2 col-6 mb-2">
                                        <label class="small"><strong>7 vagas</strong></label>
                                        <input type="number" class="form-control form-control-sm" name="range_7vagas"
                                               value="3" min="0" required>
                                    </div>
                                    <div class="col-md-2 col-6 mb-2">
                                        <label class="small"><strong>8 vagas</strong></label>
                                        <input type="number" class="form-control form-control-sm" name="range_8vagas"
                                               value="4" min="0" required>
                                    </div>
                                    <div class="col-md-2 col-6 mb-2">
                                        <label class="small"><strong>9 vagas</strong></label>
                                        <input type="number" class="form-control form-control-sm" name="range_9vagas"
                                               value="4" min="0" required>
                                    </div>
                                    <div class="col-md-2 col-6 mb-2">
                                        <label class="small"><strong>10 vagas</strong></label>
                                        <input type="number" class="form-control form-control-sm" name="range_10vagas"
                                               value="4" min="0" required>
                                    </div>
                                    <div class="col-md-2 col-6 mb-2">
                                        <label class="small"><strong>Acima de 10</strong></label>
                                        <input type="number" class="form-control form-control-sm" name="range_acima_10"
                                               value="4" min="0" required>
                                    </div>
                                    <div class="col-md-2 col-6 mb-2">
                                        <label class="small"><strong>5+ vagas (à vista)</strong></label>
                                        <input type="number" class="form-control form-control-sm" name="range_vista_acima_5"
                                               value="5" min="0" required>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <button type="submit" name="adicionar_range" class="btn btn-success btn-block">
                                    <i class="fas fa-plus"></i> Adicionar Range
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Exemplo Visual de Uso -->
                <div class="alert alert-info mt-3">
                    <h6><i class="fas fa-lightbulb"></i> Como Funciona o Sistema de Ranges:</h6>
                    <ol class="mb-0">
                        <li><strong>Pontuação Padrão:</strong> Vendas fora dos ranges configurados usam a pontuação padrão do sistema.</li>
                        <li><strong>Ranges Personalizados:</strong> Você pode criar ranges para períodos específicos (ex: Black Friday, Natal).</li>
                        <li><strong>Exemplo:</strong>
                            <ul>
                                <li>01/11/2025 - 15/11/2025: <span class="badge badge-secondary">Padrão</span></li>
                                <li>16/11/2025 - 30/11/2025: <span class="badge badge-warning">Black Friday (Customizado)</span></li>
                                <li>01/12/2025 em diante: <span class="badge badge-secondary">Padrão</span></li>
                            </ul>
                        </li>
                        <li><strong>No Relatório:</strong> O sistema automaticamente identifica a data de cada venda e aplica a pontuação correta!</li>
                    </ol>
                </div>
                
            </div>
        </div>
    </div>
</div>

<!-- Configuração de Campos Visíveis para Consultores -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-eye"></i> Campos Visíveis para Consultores
                </h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <p class="text-muted">
                        <i class="fas fa-info-circle"></i> 
                        Selecione quais campos os consultores poderão ver na página de relatórios:
                    </p>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" 
                                       id="campo_pontos" name="campo_pontos"
                                       <?= $_SESSION['campos_visiveis_consultores']['pontos'] ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="campo_pontos">
                                    <strong>Pontos</strong>
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" 
                                       id="campo_vendas" name="campo_vendas"
                                       <?= $_SESSION['campos_visiveis_consultores']['vendas'] ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="campo_vendas">
                                    <strong>Quantidade de Vendas</strong>
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" 
                                       id="campo_valor_total" name="campo_valor_total"
                                       <?= $_SESSION['campos_visiveis_consultores']['valor_total'] ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="campo_valor_total">
                                    <strong>Valor Total</strong>
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" 
                                       id="campo_valor_pago" name="campo_valor_pago"
                                       <?= $_SESSION['campos_visiveis_consultores']['valor_pago'] ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="campo_valor_pago">
                                    <strong>Valor Pago</strong>
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" 
                                       id="campo_detalhamento" name="campo_detalhamento"
                                       <?= $_SESSION['campos_visiveis_consultores']['detalhamento'] ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="campo_detalhamento">
                                    <strong>Detalhamento de Pontos</strong>
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" 
                                       id="campo_cotas_sap" name="campo_cotas_sap"
                                       <?= $_SESSION['campos_visiveis_consultores']['cotas_sap'] ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="campo_cotas_sap">
                                    <strong>Quantidade de Cotas SAP</strong>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <button type="submit" name="salvar_campos_visiveis" class="btn btn-info btn-block">
                        <i class="fas fa-save"></i> Salvar Configurações
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Configuração de Premiações -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="fas fa-trophy"></i> Configuração de Premiações
                </h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <div class="row">
                        <div class="col-md-4">
                            <label><strong>Pontos necessários por SAP</strong></label>
                            <input type="number" class="form-control" name="pontos_por_sap" 
                                   value="<?= $_SESSION['config_premiacoes']['pontos_por_sap'] ?>" 
                                   min="1" required>
                            <small class="text-muted">A cada X pontos = 1 título SAP</small>
                        </div>
                        
                        <div class="col-md-4">
                            <label><strong>Vendas totais para DIP</strong></label>
                            <input type="number" class="form-control" name="vendas_para_dip"
                                   value="<?= $_SESSION['config_premiacoes']['vendas_para_dip'] ?>"
                                   min="0" required>
                            <small class="text-muted">Total de vendas no mês (0 para desativar)</small>
                        </div>

                        <div class="col-md-4">
                            <label><strong>Vendas acima de 2 vagas para DIP</strong></label>
                            <input type="number" class="form-control" name="vendas_acima_2vagas_para_dip"
                                   value="<?= $_SESSION['config_premiacoes']['vendas_acima_2vagas_para_dip'] ?>"
                                   min="0" required>
                            <small class="text-muted">Vendas com mais de 2 vagas (0 para desativar)</small>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle"></i>
                        <strong>Como funciona:</strong> O consultor ganha 1 DIP se atingir o total de vendas 
                        <strong>OU</strong> o número de vendas acima de 2 vagas (o que acontecer primeiro).
                    </div>
                    
                    <hr>
                    
                    <button type="submit" name="salvar_config_premiacoes" class="btn btn-warning btn-block">
                        <i class="fas fa-save"></i> Salvar Configurações
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Configurações de Senha -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-warning">
                <h5 class="mb-0">
                    <i class="fas fa-cog"></i> Configurações de Acesso
                </h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <div class="form-row">
                        <div class="col-md-6">
                            <label>
                                <i class="fas fa-key"></i> Senha Atual de Consultores
                            </label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($_SESSION['senha_consultores']) ?>" disabled>
                        </div>
                        <div class="col-md-4">
                            <label>
                                <i class="fas fa-edit"></i> Nova Senha
                            </label>
                            <input type="text" class="form-control" name="nova_senha" 
                                   placeholder="Digite a nova senha" required>
                        </div>
                        <div class="col-md-2">
                            <label>&nbsp;</label>
                            <button type="submit" name="alterar_senha_consultores" class="btn btn-warning btn-block">
                                <i class="fas fa-save"></i> Alterar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Upload de Arquivos -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-upload"></i> Upload CSV de Vendas
                </h5>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>
                            <i class="fas fa-file-csv"></i> Arquivo CSV
                        </label>
                        <input type="file" class="form-control-file" name="csv_vendas" 
                               accept=".csv" required>
                        <small class="form-text text-muted">
                            Formato esperado: Exportaço de vendas do sistema
                        </small>
                    </div>
                    
                    <button type="submit" name="upload_vendas" class="btn btn-primary btn-block">
                        <i class="fas fa-cloud-upload-alt"></i> Enviar CSV de Vendas
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-upload"></i> Upload CSV de Promotores
                </h5>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>
                            <i class="fas fa-file-csv"></i> Arquivo CSV
                        </label>
                        <input type="file" class="form-control-file" name="csv_promotores" 
                               accept=".csv" required>
                        <small class="form-text text-muted">
                            Lista de promotores/consultores
                        </small>
                    </div>
                    
                    <button type="submit" name="upload_promotores" class="btn btn-info btn-block">
                        <i class="fas fa-cloud-upload-alt"></i> Enviar CSV de Promotores
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Arquivos de Vendas Salvos -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-database"></i> Arquivos de Vendas Salvos
                </h5>
            </div>
            <div class="card-body">
                <?php 
                $arquivos_vendas = listarCSVs('vendas');
                if (empty($arquivos_vendas)): 
                ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Nenhum arquivo de vendas salvo ainda.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th><i class="fas fa-file"></i> Arquivo</th>
                                    <th><i class="fas fa-calendar"></i> Data</th>
                                    <th><i class="fas fa-hdd"></i> Tamanho</th>
                                    <th class="text-center"><i class="fas fa-tools"></i> Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($arquivos_vendas as $arquivo): ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-file-csv text-success"></i>
                                        <?= htmlspecialchars($arquivo['nome']) ?>
                                    </td>
                                    <td><?= $arquivo['data'] ?></td>
                                    <td><?= formatarBytes($arquivo['tamanho']) ?></td>
                                    <td class="text-center">
                                        <a href="?page=relatorio&arquivo=<?= urlencode($arquivo['nome']) ?>" 
                                           class="btn btn-sm btn-primary" title="Processar">
                                            <i class="fas fa-chart-line"></i>
                                        </a>
                                        <form method="post" style="display: inline;" 
                                              onsubmit="return confirm('Deseja realmente excluir este arquivo?');">
                                            <input type="hidden" name="arquivo_path" value="<?= htmlspecialchars($arquivo['caminho']) ?>">
                                            <button type="submit" name="excluir_arquivo" class="btn btn-sm btn-danger" title="Excluir">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Arquivos de Promotores Salvos -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-users"></i> Arquivos de Promotores Salvos
                </h5>
            </div>
            <div class="card-body">
                <?php 
                $arquivos_promotores = listarCSVs('promotores');
                if (empty($arquivos_promotores)): 
                ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Nenhum arquivo de promotores salvo ainda.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th><i class="fas fa-file"></i> Arquivo</th>
                                    <th><i class="fas fa-calendar"></i> Data</th>
                                    <th><i class="fas fa-hdd"></i> Tamanho</th>
                                    <th><i class="fas fa-users"></i> Promotores</th>
                                    <th class="text-center"><i class="fas fa-tools"></i> Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($arquivos_promotores as $arquivo): ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-file-csv text-info"></i>
                                        <?= htmlspecialchars($arquivo['nome']) ?>
                                    </td>
                                    <td><?= $arquivo['data'] ?></td>
                                    <td><?= formatarBytes($arquivo['tamanho']) ?></td>
                                    <td>
                                        <?php
                                        $total = 0;
                                        if (($handle = fopen($arquivo['caminho'], "r")) !== false) {
                                            while (fgetcsv($handle, 0, ";") !== false) {
                                                $total++;
                                            }
                                            fclose($handle);
                                        }
                                        echo $total . ' promotores';
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <form method="post" style="display: inline;" 
                                              onsubmit="return confirm('Deseja realmente excluir este arquivo?');">
                                            <input type="hidden" name="arquivo_path" value="<?= htmlspecialchars($arquivo['caminho']) ?>">
                                            <button type="submit" name="excluir_arquivo" class="btn btn-sm btn-danger" title="Excluir">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>
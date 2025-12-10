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

/**
 * Garante que todos os ranges tenham um ID único
 * Gera ID se não existir
 */
function garantirIDRanges(&$ranges) {
    if (!is_array($ranges)) {
        return;
    }

    foreach ($ranges as &$range) {
        if (!isset($range['id']) || empty($range['id'])) {
            $range['id'] = uniqid('range_', true);
        }
    }
}

/**
 * Extrai valor de pontos de um range, suportando ambos formatos:
 * - Formato antigo: $range['pontos']['1vaga']
 * - Formato novo: $range['1vaga']
 */
function getPontoRange($range, $chave, $padrao = 0) {
    // Verifica formato novo (direto)
    if (isset($range[$chave])) {
        return $range[$chave];
    }

    // Verifica formato antigo (nested)
    if (isset($range['pontos']) && isset($range['pontos'][$chave])) {
        return $range['pontos'][$chave];
    }

    // Retorna padrão
    return $padrao;
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

    // Salva no config.json
    $_SESSION['config_sistema']['acesso']['senha_admin_setores'] = $_POST['nova_senha'];
    salvarConfiguracoes($_SESSION['config_sistema']);

    $mensagem_sucesso = "Senha de consultores alterada com sucesso!";
}

// Processa upload de CSV de vendas
if (isset($_POST['upload_vendas']) && isset($_FILES['csv_vendas'])) {
    if ($_FILES['csv_vendas']['error'] === UPLOAD_ERR_OK) {
        $substituir = isset($_POST['substituir_vendas']) && $_POST['substituir_vendas'] == '1';
        $arquivo_alvo = $substituir && !empty($_POST['arquivo_substituir_vendas']) ? $_POST['arquivo_substituir_vendas'] : null;
        $resultado = salvarCSV($_FILES['csv_vendas']['tmp_name'], 'vendas', $substituir, $arquivo_alvo);
        if ($resultado['sucesso']) {
            $acao = $resultado['substituiu'] ? 'substituído' : 'criado';
            $mensagem_sucesso = "CSV de vendas {$acao} com sucesso! Arquivo: {$resultado['nome']}";
        } else {
            $erro_upload = $resultado['erro'];
        }
    }
}

// Processa upload de CSV de promotores
if (isset($_POST['upload_promotores']) && isset($_FILES['csv_promotores'])) {
    if ($_FILES['csv_promotores']['error'] === UPLOAD_ERR_OK) {
        $substituir = isset($_POST['substituir_promotores']) && $_POST['substituir_promotores'] == '1';
        $arquivo_alvo = $substituir && !empty($_POST['arquivo_substituir_promotores']) ? $_POST['arquivo_substituir_promotores'] : null;
        $resultado = salvarCSV($_FILES['csv_promotores']['tmp_name'], 'promotores', $substituir, $arquivo_alvo);
        if ($resultado['sucesso']) {
            require_once 'functions/promotores.php';
            $_SESSION['promotores'] = processarPromotoresCSV($resultado['caminho']);
            $acao = $resultado['substituiu'] ? 'substituído' : 'criado';
            $mensagem_sucesso = "CSV de promotores {$acao} com sucesso! {$_SESSION['promotores']['total']} promotores carregados.";
        } else{
            $erro_upload = $resultado['erro'];
        }
    }
}

// Processa gerenciamento de apelidos de consultores
if (isset($_POST['adicionar_apelido'])) {
    $nome_original = trim($_POST['nome_original'] ?? '');
    $apelido = trim($_POST['apelido'] ?? '');

    if (!empty($nome_original) && !empty($apelido)) {
        adicionarApelidoConsultor($nome_original, $apelido);
        $mensagem_sucesso = "Apelido adicionado com sucesso!";
    } else {
        $mensagem_erro = "Nome original e apelido são obrigatórios!";
    }
}

if (isset($_POST['remover_apelido'])) {
    $nome_original = $_POST['nome_original'] ?? '';
    if (!empty($nome_original)) {
        removerApelidoConsultor($nome_original);
        $mensagem_sucesso = "Apelido removido com sucesso!";
    }
}

// Processa gerenciamento de nomes amigáveis de CSVs
if (isset($_POST['definir_nome_amigavel'])) {
    $nome_arquivo = trim($_POST['nome_arquivo_csv'] ?? '');
    $nome_amigavel = trim($_POST['nome_amigavel_csv'] ?? '');

    if (!empty($nome_arquivo) && !empty($nome_amigavel)) {
        // Valida que nome amigável não contém caracteres especiais
        if (preg_match('/^[a-z0-9\-]+$/', $nome_amigavel)) {
            definirNomeAmigavel($nome_arquivo, $nome_amigavel);
            $mensagem_sucesso = "Nome amigável definido com sucesso!";
        } else {
            $mensagem_erro = "Nome amigável deve conter apenas letras minúsculas, números e hífens!";
        }
    } else {
        $mensagem_erro = "Nome do arquivo e nome amigável são obrigatórios!";
    }
}

if (isset($_POST['remover_nome_amigavel'])) {
    $nome_arquivo = $_POST['nome_arquivo_remover'] ?? '';
    if (!empty($nome_arquivo)) {
        $mapeamento = carregarMapeamentoNomes();
        if (isset($mapeamento[$nome_arquivo])) {
            unset($mapeamento[$nome_arquivo]);
            salvarMapeamentoNomes($mapeamento);
            $mensagem_sucesso = "Nome amigável removido com sucesso!";
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

// Garantir que todos os ranges tenham ID
garantirIDRanges($_SESSION['ranges_pontuacao']);

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
    garantirIDRanges($_SESSION['ranges_pontuacao']);
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

// Processar configurações do Top20 / Período de Relatório
if (isset($_POST['salvar_config_top20'])) {
    $_SESSION['config_sistema']['periodo_relatorio'] = [
        'arquivo_csv' => $_POST['periodo_arquivo_csv'] ?? '',
        'data_inicial' => $_POST['periodo_data_inicial'],
        'data_final' => $_POST['periodo_data_final'],
        'apenas_primeira_parcela' => isset($_POST['periodo_apenas_primeira_parcela']),
        'apenas_vista' => isset($_POST['periodo_apenas_vista']),
        'filtro_status' => $_POST['periodo_filtro_status'] ?? 'Ativo'
    ];

    salvarConfiguracoes($_SESSION['config_sistema']);

    $mensagem_sucesso = "Configurações do Top20 / Período de Relatório atualizadas com sucesso!";
}

// Processar gerenciamento de mensagens de parabéns
if (isset($_POST['adicionar_mensagem_parabens'])) {
    $titulo = trim($_POST['titulo_mensagem'] ?? '');
    $mensagem = trim($_POST['texto_mensagem'] ?? '');

    if (!empty($titulo) && !empty($mensagem)) {
        adicionarMensagemParabens($titulo, $mensagem);
        $mensagem_sucesso = "Mensagem de parabéns adicionada com sucesso!";
    } else {
        $mensagem_erro = "Título e mensagem são obrigatórios!";
    }
}

if (isset($_POST['remover_mensagem_parabens'])) {
    $id = $_POST['mensagem_id'] ?? '';
    if (!empty($id)) {
        removerMensagemParabens($id);
        $mensagem_sucesso = "Mensagem removida com sucesso!";
    }
}

if (isset($_POST['alternar_status_mensagem'])) {
    $id = $_POST['mensagem_id'] ?? '';
    if (!empty($id)) {
        alternarStatusMensagem($id);
        $mensagem_sucesso = "Status da mensagem alterado com sucesso!";
    }
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
                                        1v=<?= getPontoRange($range, '1vaga', 1) ?>,
                                        2v=<?= getPontoRange($range, '2vagas', 2) ?>,
                                        3v=<?= getPontoRange($range, '3vagas', 2) ?>,
                                        4v=<?= getPontoRange($range, '4vagas', 3) ?>,
                                        5v=<?= getPontoRange($range, '5vagas', 3) ?>,
                                        6v=<?= getPontoRange($range, '6vagas', 3) ?>,
                                        7v=<?= getPontoRange($range, '7vagas', 3) ?>,
                                        8v=<?= getPontoRange($range, '8vagas', 4) ?>,
                                        9v=<?= getPontoRange($range, '9vagas', 4) ?>,
                                        10v=<?= getPontoRange($range, '10vagas', 4) ?>,
                                        >10=<?= getPontoRange($range, 'acima_10', 4) ?>,
                                        >5(vista)=<?= getPontoRange($range, 'vista_acima_5', 5) ?>
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

<!-- Configurações do Top20 / Período de Relatório -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-alt"></i> Configurações do Top20 / Período de Relatório
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    <i class="fas fa-info-circle"></i>
                    Configure o período padrão e filtros que serão aplicados automaticamente no Top20.
                    Essas configurações também são usadas como padrão nos relatórios.
                </p>

                <form method="post">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="form-group">
                                <label><strong><i class="fas fa-file-csv"></i> Arquivo CSV de Vendas para o Top20</strong></label>
                                <select class="form-control" name="periodo_arquivo_csv">
                                    <option value="">Usar o mais recente (padrão)</option>
                                    <?php
                                    $arquivos_disponiveis = listarCSVs('vendas');
                                    $arquivo_selecionado_config = $_SESSION['config_sistema']['periodo_relatorio']['arquivo_csv'] ?? '';
                                    foreach ($arquivos_disponiveis as $arq):
                                    ?>
                                        <option value="<?= htmlspecialchars($arq['nome']) ?>"
                                                <?= $arquivo_selecionado_config === $arq['nome'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($arq['nome']) ?> (<?= htmlspecialchars($arq['data']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Escolha qual arquivo CSV usar no Top20 e relatórios. Se não selecionar, usará o mais recente.</small>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><strong>Data Inicial do Período</strong></label>
                                <input type="date" class="form-control" name="periodo_data_inicial"
                                       value="<?= $_SESSION['config_sistema']['periodo_relatorio']['data_inicial'] ?? date('Y-m-01') ?>"
                                       required>
                                <small class="text-muted">Data de início para contagem de vendas</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label><strong>Data Final do Período</strong></label>
                                <input type="date" class="form-control" name="periodo_data_final"
                                       value="<?= $_SESSION['config_sistema']['periodo_relatorio']['data_final'] ?? date('Y-m-t') ?>"
                                       required>
                                <small class="text-muted">Data final para contagem de vendas</small>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><strong>Filtro de Status</strong></label>
                                <select class="form-control" name="periodo_filtro_status">
                                    <?php
                                    $status_atual = $_SESSION['config_sistema']['periodo_relatorio']['filtro_status'] ?? 'Ativo';
                                    ?>
                                    <option value="Ativo" <?= $status_atual === 'Ativo' ? 'selected' : '' ?>>Apenas Ativo</option>
                                    <option value="" <?= $status_atual === '' ? 'selected' : '' ?>>Todos os Status</option>
                                    <option value="Cancelado" <?= $status_atual === 'Cancelado' ? 'selected' : '' ?>>Apenas Cancelado</option>
                                </select>
                                <small class="text-muted">Filtrar vendas por status</small>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <div class="custom-control custom-checkbox mt-4">
                                    <input type="checkbox" class="custom-control-input"
                                           id="periodo_apenas_primeira_parcela"
                                           name="periodo_apenas_primeira_parcela"
                                           <?= ($_SESSION['config_sistema']['periodo_relatorio']['apenas_primeira_parcela'] ?? false) ? 'checked' : '' ?>>
                                    <label class="custom-control-label" for="periodo_apenas_primeira_parcela">
                                        <strong>Apenas com 1ª Parcela Paga</strong>
                                    </label>
                                </div>
                                <small class="text-muted d-block">Considerar apenas vendas que já receberam a primeira parcela</small>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <div class="custom-control custom-checkbox mt-4">
                                    <input type="checkbox" class="custom-control-input"
                                           id="periodo_apenas_vista"
                                           name="periodo_apenas_vista"
                                           <?= ($_SESSION['config_sistema']['periodo_relatorio']['apenas_vista'] ?? false) ? 'checked' : '' ?>>
                                    <label class="custom-control-label" for="periodo_apenas_vista">
                                        <strong>Apenas Vendas à Vista</strong>
                                    </label>
                                </div>
                                <small class="text-muted d-block">Considerar apenas vendas à vista</small>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Importante:</strong> Após o dia 08 do mês seguinte ao período, o sistema
                        automaticamente remove vendas canceladas e sem primeira parcela paga do ranking,
                        independentemente dos filtros configurados aqui (Regra do Dia 08).
                    </div>

                    <hr>

                    <button type="submit" name="salvar_config_top20" class="btn btn-info btn-block">
                        <i class="fas fa-save"></i> Salvar Configurações do Top20
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Mensagens de Parabéns para Relatórios Finais -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-trophy"></i> Mensagens de Parabéns (Relatórios Finais)
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    <i class="fas fa-info-circle"></i>
                    Configure mensagens que serão exibidas aleatoriamente nos relatórios FINAIS (após dia 08).
                    As mensagens ativas são selecionadas randomicamente ao carregar a página.
                </p>

                <!-- Formulário para adicionar mensagem -->
                <form method="post" class="mb-4">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><strong>Título da Mensagem</strong></label>
                                <input type="text" class="form-control" name="titulo_mensagem"
                                       placeholder="Ex: Parabéns aos Campeões!" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><strong>Mensagem</strong></label>
                                <textarea class="form-control" name="texto_mensagem" rows="2"
                                          placeholder="Digite a mensagem de parabéns..." required></textarea>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="d-block">&nbsp;</label>
                            <button type="submit" name="adicionar_mensagem_parabens" class="btn btn-success btn-block">
                                <i class="fas fa-plus"></i> Adicionar
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Lista de mensagens -->
                <?php
                $mensagens_parabens = carregarMensagensParabens();
                ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead class="thead-light">
                            <tr>
                                <th width="25%">Título</th>
                                <th width="45%">Mensagem</th>
                                <th width="10%" class="text-center">Status</th>
                                <th width="12%">Data Criação</th>
                                <th width="8%" class="text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($mensagens_parabens)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">
                                    <i class="fas fa-info-circle"></i> Nenhuma mensagem cadastrada ainda.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($mensagens_parabens as $msg): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($msg['titulo']) ?></strong></td>
                                    <td><?= htmlspecialchars($msg['mensagem']) ?></td>
                                    <td class="text-center">
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="mensagem_id" value="<?= htmlspecialchars($msg['id']) ?>">
                                            <button type="submit" name="alternar_status_mensagem"
                                                    class="btn btn-sm btn-<?= $msg['ativo'] ? 'success' : 'secondary' ?>"
                                                    title="Clique para <?= $msg['ativo'] ? 'desativar' : 'ativar' ?>">
                                                <?= $msg['ativo'] ? 'Ativa' : 'Inativa' ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= date('d/m/Y', strtotime($msg['data_criacao'])) ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="mensagem_id" value="<?= htmlspecialchars($msg['id']) ?>">
                                            <button type="submit" name="remover_mensagem_parabens"
                                                    class="btn btn-danger btn-sm"
                                                    onclick="return confirm('Remover esta mensagem?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-info mb-0 mt-3">
                    <i class="fas fa-lightbulb"></i>
                    <strong>Dica:</strong> Mantenha pelo menos 3 mensagens ativas para maior variedade.
                    As mensagens inativas não serão exibidas, mas ficam salvas para reativação futura.
                </div>
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
                    <?php
                    $arquivos_vendas_existentes = listarCSVs('vendas');
                    if (!empty($arquivos_vendas_existentes)):
                    ?>
                    <div class="alert alert-info">
                        <small>
                            <strong><i class="fas fa-info-circle"></i> Arquivos CSV de Vendas Existentes:</strong><br>
                            <?php foreach ($arquivos_vendas_existentes as $idx => $arq): ?>
                                <?= $idx + 1 ?>. <strong><?= htmlspecialchars($arq['nome']) ?></strong>
                                (<?= htmlspecialchars($arq['data']) ?>)<br>
                            <?php endforeach; ?>
                        </small>
                    </div>
                    <?php endif; ?>

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

                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input"
                                   id="substituir_vendas" name="substituir_vendas" value="1"
                                   onchange="toggleArquivoSubstituir('vendas')">
                            <label class="custom-control-label" for="substituir_vendas">
                                <i class="fas fa-sync-alt"></i> Substituir arquivo existente
                            </label>
                        </div>
                        <small class="form-text text-muted">
                            Se desmarcado, será criado um novo arquivo com timestamp
                        </small>
                    </div>

                    <!-- Dropdown de arquivos (oculto por padrão) -->
                    <div class="form-group" id="select_arquivo_vendas" style="display: none;">
                        <label><strong>Escolha qual arquivo substituir:</strong></label>
                        <select class="form-control" name="arquivo_substituir_vendas">
                            <option value="">Selecione o arquivo...</option>
                            <?php
                            $arquivos_vendas_list = listarCSVs('vendas');
                            foreach ($arquivos_vendas_list as $arq):
                            ?>
                                <option value="<?= htmlspecialchars($arq['nome']) ?>">
                                    <?= htmlspecialchars($arq['nome']) ?>
                                    (<?= htmlspecialchars($arq['data']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
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
                    <?php
                    $arquivos_promotores_existentes = listarCSVs('promotores');
                    if (!empty($arquivos_promotores_existentes)):
                    ?>
                    <div class="alert alert-info">
                        <small>
                            <strong><i class="fas fa-info-circle"></i> Arquivos CSV de Promotores Existentes:</strong><br>
                            <?php foreach ($arquivos_promotores_existentes as $idx => $arq): ?>
                                <?= $idx + 1 ?>. <strong><?= htmlspecialchars($arq['nome']) ?></strong>
                                (<?= htmlspecialchars($arq['data']) ?>)<br>
                            <?php endforeach; ?>
                        </small>
                    </div>
                    <?php endif; ?>

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

                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input"
                                   id="substituir_promotores" name="substituir_promotores" value="1"
                                   onchange="toggleArquivoSubstituir('promotores')">
                            <label class="custom-control-label" for="substituir_promotores">
                                <i class="fas fa-sync-alt"></i> Substituir arquivo existente
                            </label>
                        </div>
                        <small class="form-text text-muted">
                            Se desmarcado, será criado um novo arquivo com timestamp
                        </small>
                    </div>

                    <!-- Dropdown de arquivos (oculto por padrão) -->
                    <div class="form-group" id="select_arquivo_promotores" style="display: none;">
                        <label><strong>Escolha qual arquivo substituir:</strong></label>
                        <select class="form-control" name="arquivo_substituir_promotores">
                            <option value="">Selecione o arquivo...</option>
                            <?php
                            $arquivos_promotores_list = listarCSVs('promotores');
                            foreach ($arquivos_promotores_list as $arq):
                            ?>
                                <option value="<?= htmlspecialchars($arq['nome']) ?>">
                                    <?= htmlspecialchars($arq['nome']) ?>
                                    (<?= htmlspecialchars($arq['data']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" name="upload_promotores" class="btn btn-info btn-block">
                        <i class="fas fa-cloud-upload-alt"></i> Enviar CSV de Promotores
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Gerenciamento de Apelidos de Consultores -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="fas fa-user-tag"></i> Apelidos de Consultores
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    <i class="fas fa-info-circle"></i>
                    Configure apelidos para consultores que querem aparecer com outro nome no ranking.
                    O nome original ficará visível apenas para você ao passar o mouse.
                </p>

                <!-- Formulário para adicionar apelido -->
                <form method="post" class="mb-4">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="form-group">
                                <label><strong>Nome Original do Consultor</strong></label>
                                <input type="text" class="form-control" name="nome_original"
                                       placeholder="Ex: João Silva" required
                                       list="consultores-list">
                                <datalist id="consultores-list">
                                    <?php
                                    // Lista consultores existentes (dos arquivos CSV)
                                    $arquivos_vendas = listarCSVs('vendas');
                                    $consultores_unicos = [];

                                    foreach ($arquivos_vendas as $arquivo) {
                                        $vendas_data = processarVendasCSV($arquivo['caminho']);
                                        foreach ($vendas_data['por_consultor'] as $cons) {
                                            $consultores_unicos[$cons['consultor']] = true;
                                        }
                                    }

                                    foreach (array_keys($consultores_unicos) as $consultor):
                                    ?>
                                        <option value="<?= htmlspecialchars($consultor) ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-group">
                                <label><strong>Apelido (Nome para Exibir)</strong></label>
                                <input type="text" class="form-control" name="apelido"
                                       placeholder="Ex: J. Silva" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="d-block">&nbsp;</label>
                            <button type="submit" name="adicionar_apelido" class="btn btn-warning btn-block">
                                <i class="fas fa-plus"></i> Adicionar
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Lista de apelidos existentes -->
                <?php
                $apelidos = carregarApelidosConsultores();
                if (empty($apelidos)):
                ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Nenhum apelido configurado ainda.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead class="thead-light">
                                <tr>
                                    <th width="35%">Nome Original</th>
                                    <th width="35%">Apelido (Exibição)</th>
                                    <th width="20%">Data Alteração</th>
                                    <th width="10%" class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($apelidos as $nome_orig => $dados): ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-user text-muted"></i>
                                        <strong><?= htmlspecialchars($nome_orig) ?></strong>
                                    </td>
                                    <td>
                                        <i class="fas fa-tag text-warning"></i>
                                        <?= htmlspecialchars($dados['apelido']) ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= date('d/m/Y H:i', strtotime($dados['data_alteracao'])) ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="nome_original" value="<?= htmlspecialchars($nome_orig) ?>">
                                            <button type="submit" name="remover_apelido"
                                                    class="btn btn-danger btn-sm"
                                                    onclick="return confirm('Remover apelido de <?= htmlspecialchars($nome_orig) ?>?')">
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

<!-- Gerenciamento de Nomes Amigáveis de Relatórios -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-link"></i> Nomes Amigáveis de Relatórios (URLs)
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    <i class="fas fa-info-circle"></i>
                    Configure nomes amigáveis para os arquivos CSV de vendas, facilitando o compartilhamento de links.<br>
                    <strong>Exemplo:</strong> <code>vendas-novembro25</code> em vez de <code>2025-12-01_034017_vendas.csv</code>
                </p>

                <!-- Formulário para definir nome amigável -->
                <form method="post" class="mb-4">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="form-group">
                                <label><strong>Arquivo CSV</strong></label>
                                <select class="form-control" name="nome_arquivo_csv" required>
                                    <option value="">Selecione um arquivo...</option>
                                    <?php
                                    $arquivos_vendas = listarCSVs('vendas');
                                    foreach ($arquivos_vendas as $arquivo):
                                        $nome_arquivo = $arquivo['nome'];
                                        $nome_amigavel_atual = gerarNomeAmigavel($nome_arquivo);
                                    ?>
                                        <option value="<?= htmlspecialchars($nome_arquivo) ?>">
                                            <?= htmlspecialchars($nome_arquivo) ?>
                                            (atual: <?= htmlspecialchars($nome_amigavel_atual) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-group">
                                <label><strong>Nome Amigável (para URL)</strong></label>
                                <input type="text" class="form-control" name="nome_amigavel_csv"
                                       placeholder="Ex: vendas-novembro25" required
                                       pattern="[a-z0-9\-]+"
                                       title="Apenas letras minúsculas, números e hífens">
                                <small class="form-text text-muted">
                                    Apenas letras minúsculas, números e hífens (-)
                                </small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="d-block">&nbsp;</label>
                            <button type="submit" name="definir_nome_amigavel" class="btn btn-primary btn-block">
                                <i class="fas fa-save"></i> Definir
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Lista de nomes amigáveis personalizados -->
                <?php
                $mapeamento_nomes = carregarMapeamentoNomes();
                if (empty($mapeamento_nomes)):
                ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Nenhum nome amigável personalizado configurado.
                        Os nomes são gerados automaticamente a partir da data do arquivo.
                    </div>
                <?php else: ?>
                    <h6 class="mb-3"><strong>Nomes Personalizados:</strong></h6>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead class="thead-light">
                                <tr>
                                    <th width="40%">Arquivo CSV</th>
                                    <th width="30%">Nome Amigável</th>
                                    <th width="20%">Data Definição</th>
                                    <th width="10%" class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mapeamento_nomes as $nome_arquivo => $dados): ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-file-csv text-muted"></i>
                                        <code><?= htmlspecialchars($nome_arquivo) ?></code>
                                    </td>
                                    <td>
                                        <i class="fas fa-link text-primary"></i>
                                        <strong><?= htmlspecialchars($dados['nome_amigavel']) ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-external-link-alt"></i>
                                            <code>?page=relatorio&arquivo=<?= htmlspecialchars($dados['nome_amigavel']) ?></code>
                                        </small>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= date('d/m/Y H:i', strtotime($dados['data_definicao'])) ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="nome_arquivo_remover" value="<?= htmlspecialchars($nome_arquivo) ?>">
                                            <button type="submit" name="remover_nome_amigavel"
                                                    class="btn btn-danger btn-sm"
                                                    onclick="return confirm('Remover nome amigável?\nO arquivo voltará a usar o nome automático.')">
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

                <!-- Tabela de nomes automáticos (referência) -->
                <hr class="my-4">
                <h6 class="mb-3"><strong>Todos os Arquivos e Seus Nomes Amigáveis:</strong></h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th width="50%">Arquivo CSV</th>
                                <th width="30%">Nome Amigável Atual</th>
                                <th width="20%">Tipo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $arquivos_vendas = listarCSVs('vendas');
                            foreach ($arquivos_vendas as $arquivo):
                                $nome_arquivo = $arquivo['nome'];
                                $nome_amigavel = gerarNomeAmigavel($nome_arquivo);
                                $e_personalizado = isset($mapeamento_nomes[$nome_arquivo]);
                            ?>
                            <tr class="<?= $e_personalizado ? 'table-primary' : '' ?>">
                                <td>
                                    <i class="fas fa-file-csv text-muted"></i>
                                    <code><?= htmlspecialchars($nome_arquivo) ?></code>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($nome_amigavel) ?></strong>
                                </td>
                                <td>
                                    <?php if ($e_personalizado): ?>
                                        <span class="badge badge-primary">Personalizado</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Automático</span>
                                    <?php endif; ?>
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

<script>
// Função para mostrar/ocultar dropdown de arquivos quando checkbox for marcado
function toggleArquivoSubstituir(tipo) {
    console.log('toggleArquivoSubstituir chamado para:', tipo);

    const checkbox = document.getElementById('substituir_' + tipo);
    const selectDiv = document.getElementById('select_arquivo_' + tipo);

    if (!checkbox) {
        console.error('Checkbox não encontrado:', 'substituir_' + tipo);
        return;
    }

    if (!selectDiv) {
        console.error('Div de seleção não encontrada:', 'select_arquivo_' + tipo);
        return;
    }

    const select = selectDiv.querySelector('select');

    if (!select) {
        console.error('Select não encontrado dentro de:', 'select_arquivo_' + tipo);
        return;
    }

    console.log('Checkbox checked:', checkbox.checked);

    if (checkbox.checked) {
        selectDiv.style.display = 'block';
        select.required = true;
        console.log('Dropdown mostrado para:', tipo);
    } else {
        selectDiv.style.display = 'none';
        select.required = false;
        select.value = '';
        console.log('Dropdown ocultado para:', tipo);
    }
}
</script>

<?php endif; ?>
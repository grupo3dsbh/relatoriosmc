<?php
// pages/consultores.php - Acesso Administrativo (Setores)

require_once 'functions/promotores.php';

// Processa login administrativo
if (isset($_POST['login_consultores'])) {
    $senha = $_POST['senha'] ?? '';

    // Senha administrativa configurável
    $senha_admin = $_SESSION['config_sistema']['acesso']['senha_admin_setores'] ?? 'aquabeat';

    if ($senha === $senha_admin) {
        $_SESSION['consultores_autenticado'] = true;

        // Redireciona se houver página pendente
        if (isset($_SESSION['redirect_after_login'])) {
            $redirect = $_SESSION['redirect_after_login'];
            unset($_SESSION['redirect_after_login']);
            header("Location: ?page=$redirect");
            exit;
        }
        header('Location: ?page=consultores');
        exit;
    } else {
        $erro_login = "Senha incorreta!";
    }
}

// Se não estiver autenticado, mostra tela de login
if (!verificarConsultores()):
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-info text-white text-center">
                <h4><i class="fas fa-users"></i> Área de Consultores</h4>
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
                            <i class="fas fa-key"></i> Senha Administrativa
                        </label>
                        <input type="password" class="form-control form-control-lg"
                               id="senha" name="senha"
                               placeholder="Digite a senha administrativa"
                               required autofocus>
                    </div>

                    <button type="submit" name="login_consultores" class="btn btn-info btn-block btn-lg">
                        <i class="fas fa-sign-in-alt"></i> Entrar
                    </button>
                </form>
                
                <hr>
                
                <div class="alert alert-info mb-0">
                    <small>
                        <i class="fas fa-info-circle"></i>
                        <strong>Acesso Administrativo:</strong> Esta área é restrita aos setores autorizados.
                        Entre em contato com o administrador para obter a senha de acesso.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php else: ?>

<!-- Área Autenticada de Consultores -->
<?php
// Carrega promotores se houver arquivo
$promotores_data = ['promotores' => [], 'total' => 0];
$arquivos_promotores = listarCSVs('promotores');

if (!empty($arquivos_promotores)) {
    // Pega o arquivo mais recente
    $arquivo_recente = $arquivos_promotores[0];
    $promotores_data = processarPromotoresCSV($arquivo_recente['caminho']);
}

// Aplica filtros
$filtros = [
    'status' => $_GET['filtro_status'] ?? '',
    'busca' => $_GET['busca'] ?? '',
    'cidade' => $_GET['filtro_cidade'] ?? ''
];

$promotores_filtrados = filtrarPromotores($promotores_data['promotores'], $filtros);

// Obtém listas para filtros
$status_list = obterStatusPromotores($promotores_data['promotores']);
$cidades_list = obterCidadesPromotores($promotores_data['promotores']);
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h4 class="mb-0">
                    <i class="fas fa-users"></i> Gerenciamento de Promotores/Consultores
                    <span class="badge badge-light float-right">
                        <?= count($promotores_filtrados) ?> de <?= $promotores_data['total'] ?>
                    </span>
                </h4>
            </div>
            <div class="card-body">
                
                <?php if (empty($promotores_data['promotores'])): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Nenhum promotor cadastrado!</strong> 
                        <?php if (isGodMode()): ?>
                            <a href="?page=admin" class="btn btn-warning btn-sm ml-2">
                                Ir para Admin e enviar CSV
                            </a>
                        <?php else: ?>
                            Entre em contato com o administrador para carregar a lista de promotores.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                
                <!-- Filtros -->
                <form method="get" class="mb-4">
                    <input type="hidden" name="page" value="consultores">
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-search"></i> Buscar
                                </label>
                                <input type="text" class="form-control" name="busca" 
                                       placeholder="Nome, CPF ou Código" 
                                       value="<?= htmlspecialchars($filtros['busca']) ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-filter"></i> Status
                                </label>
                                <select class="form-control" name="filtro_status">
                                    <option value="">Todos</option>
                                    <?php foreach ($status_list as $status): ?>
                                        <option value="<?= htmlspecialchars($status) ?>"
                                                <?= $filtros['status'] === $status ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($status) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-map-marker-alt"></i> Cidade
                                </label>
                                <select class="form-control" name="filtro_cidade">
                                    <option value="">Todas</option>
                                    <?php foreach ($cidades_list as $cidade): ?>
                                        <option value="<?= htmlspecialchars($cidade) ?>"
                                                <?= $filtros['cidade'] === $cidade ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cidade) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-search"></i> Filtrar
                            </button>
                        </div>
                    </div>
                    
                    <?php if (!empty($filtros['status']) || !empty($filtros['busca']) || !empty($filtros['cidade'])): ?>
                        <div class="text-right">
                            <a href="?page=consultores" class="btn btn-sm btn-secondary">
                                <i class="fas fa-times"></i> Limpar Filtros
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
                
                <!-- Tabela de Promotores -->
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="tabelaPromotores">
                        <thead class="thead-dark">
                            <tr>
                                <th>Código</th>
                                <th>Nome</th>
                                <th>CPF</th>
                                <th>Status</th>
                                <th>Telefone</th>
                                <th>Cidade/UF</th>
                                <th class="text-center">Comissão</th>
                                <th class="text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($promotores_filtrados)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <i class="fas fa-info-circle"></i>
                                        Nenhum promotor encontrado com os filtros aplicados.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($promotores_filtrados as $promotor): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($promotor['codigo']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($promotor['nome']) ?></td>
                                    <td>
                                        <small><?= formatarCPF($promotor['cpf']) ?></small>
                                    </td>
                                    <td>
                                        <?php if (strcasecmp($promotor['status'], 'Ativo') === 0): ?>
                                            <span class="badge badge-success">
                                                <i class="fas fa-check-circle"></i> Ativo
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">
                                                <i class="fas fa-times-circle"></i> Desativado
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small>
                                            <i class="fas fa-phone"></i>
                                            <?= formatarTelefone($promotor['telefone']) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small>
                                            <?= htmlspecialchars($promotor['cidade']) ?>/<?= htmlspecialchars($promotor['estado']) ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <strong><?= number_format($promotor['comissao'], 2, ',', '.') ?>%</strong>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-info" 
                                                data-toggle="modal" 
                                                data-target="#modalDetalhes"
                                                data-promotor='<?= htmlspecialchars(json_encode($promotor)) ?>'>
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Estatísticas -->
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h3>
                                    <?php
                                    $ativos = array_filter($promotores_data['promotores'], function($p) {
                                        return strcasecmp($p['status'], 'Ativo') === 0;
                                    });
                                    echo count($ativos);
                                    ?>
                                </h3>
                                <p class="mb-0">Promotores Ativos</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card bg-secondary text-white">
                            <div class="card-body text-center">
                                <h3>
                                    <?php
                                    $desativados = array_filter($promotores_data['promotores'], function($p) {
                                        return strcasecmp($p['status'], 'Desativado') === 0;
                                    });
                                    echo count($desativados);
                                    ?>
                                </h3>
                                <p class="mb-0">Promotores Desativados</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h3><?= count($cidades_list) ?></h3>
                                <p class="mb-0">Cidades Atendidas</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Detalhes do Promotor -->
<div class="modal fade" id="modalDetalhes" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-user"></i> Detalhes do Promotor
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="modalDetalhesBody">
                <!-- Preenchido via JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times"></i> Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Preenche modal com detalhes do promotor
    $('#modalDetalhes').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        const promotor = button.data('promotor');
        
        const html = `
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="fas fa-id-card"></i> Informações Pessoais</h6>
                    <table class="table table-sm">
                        <tr>
                            <th>Código:</th>
                            <td>${promotor.codigo}</td>
                        </tr>
                        <tr>
                            <th>Nome:</th>
                            <td>${promotor.nome}</td>
                        </tr>
                        <tr>
                            <th>CPF:</th>
                            <td>${promotor.cpf}</td>
                        </tr>
                        <tr>
                            <th>RG:</th>
                            <td>${promotor.rg || 'Não informado'}</td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td>
                                ${promotor.status === 'Ativo' 
                                    ? '<span class="badge badge-success">Ativo</span>' 
                                    : '<span class="badge badge-secondary">Desativado</span>'}
                            </td>
                        </tr>
                        <tr>
                            <th>Comissão:</th>
                            <td><strong>${promotor.comissao}%</strong></td>
                        </tr>
                    </table>
                </div>
                
                <div class="col-md-6">
                    <h6><i class="fas fa-map-marker-alt"></i> Contato e Endereço</h6>
                    <table class="table table-sm">
                        <tr>
                            <th>Telefone:</th>
                            <td>${promotor.telefone || 'Não informado'}</td>
                        </tr>
                        <tr>
                            <th>Endereço:</th>
                            <td>${promotor.endereco_completo}</td>
                        </tr>
                    </table>
                </div>
            </div>
        `;
        
        $('#modalDetalhesBody').html(html);
    });
});
</script>

<?php endif; ?>
<?php
// pages/consultores.php - Gerenciamento de Consultores/Promotores

require_once 'functions/promotores.php';

// Inicializa controle de tentativas
if (!isset($_SESSION['pin_attempts'])) {
    $_SESSION['pin_attempts'] = 0;
    $_SESSION['pin_locked_until'] = null;
}

// Verifica se está bloqueado
$is_locked = false;
$lockout_time_remaining = 0;

if ($_SESSION['pin_locked_until'] !== null && time() < $_SESSION['pin_locked_until']) {
    $is_locked = true;
    $lockout_time_remaining = $_SESSION['pin_locked_until'] - time();
} elseif ($_SESSION['pin_locked_until'] !== null && time() >= $_SESSION['pin_locked_until']) {
    // Desbloqueio automático após timeout
    $_SESSION['pin_attempts'] = 0;
    $_SESSION['pin_locked_until'] = null;
}

// Processa login
if (isset($_POST['login_consultores'])) {
    if ($is_locked) {
        $erro_login = "Acesso bloqueado! Aguarde " . ceil($lockout_time_remaining / 60) . " minutos para tentar novamente.";
    } else {
        $pin = preg_replace('/\D/', '', $_POST['pin'] ?? '');

        // Valida PIN (4 dígitos)
        if (strlen($pin) !== 4) {
            $erro_login = "O PIN deve ter exatamente 4 dígitos!";
        } else {
            // Tenta fazer login
            $senha_configurada = $_SESSION['config_sistema']['acesso']['senha_consultores'] ?? 'aquabeat';

            if ($pin === $senha_configurada) {
                // Login bem-sucedido - reseta tentativas
                $_SESSION['pin_attempts'] = 0;
                $_SESSION['pin_locked_until'] = null;
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
                // Login falhou - incrementa tentativas
                $_SESSION['pin_attempts']++;

                if ($_SESSION['pin_attempts'] >= 3) {
                    // Bloqueia por 15 minutos
                    $_SESSION['pin_locked_until'] = time() + (15 * 60);
                    $is_locked = true;
                    $erro_login = "Acesso bloqueado por 15 minutos após 3 tentativas incorretas!";
                } else {
                    $tentativas_restantes = 3 - $_SESSION['pin_attempts'];
                    $erro_login = "PIN incorreto! Você tem mais $tentativas_restantes tentativa(s).";
                }
            }
        }
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
                
                <form method="post" id="formPIN">
                    <div class="form-group">
                        <label for="pin">
                            <i class="fas fa-lock"></i> PIN de Acesso (4 dígitos)
                        </label>
                        <input type="text"
                               class="form-control form-control-lg text-center"
                               id="pin"
                               name="pin"
                               placeholder="****"
                               maxlength="4"
                               pattern="[0-9]{4}"
                               inputmode="numeric"
                               autocomplete="off"
                               required
                               autofocus
                               <?= $is_locked ? 'disabled' : '' ?>
                               style="font-size: 2em; letter-spacing: 0.5em;">
                        <small class="form-text text-muted">
                            Digite seu PIN de 4 dígitos
                        </small>
                    </div>

                    <?php if ($is_locked): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-lock"></i>
                            <strong>Acesso bloqueado!</strong><br>
                            Aguarde <strong id="lockoutTimer"><?= ceil($lockout_time_remaining / 60) ?></strong> minutos para tentar novamente.
                        </div>
                        <button type="button" class="btn btn-secondary btn-block" disabled>
                            <i class="fas fa-lock"></i> Bloqueado
                        </button>
                    <?php else: ?>
                        <button type="submit" name="login_consultores" class="btn btn-info btn-block btn-lg">
                            <i class="fas fa-sign-in-alt"></i> Entrar
                        </button>

                        <?php if (isset($_SESSION['pin_attempts']) && $_SESSION['pin_attempts'] > 0): ?>
                            <div class="alert alert-warning mt-3 mb-0">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong><?= $_SESSION['pin_attempts'] ?></strong> tentativa(s) incorreta(s).
                                Restam <strong><?= 3 - $_SESSION['pin_attempts'] ?></strong> tentativa(s).
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </form>

                <script>
                // Permite apenas números no PIN
                document.getElementById('pin').addEventListener('input', function(e) {
                    this.value = this.value.replace(/\D/g, '');
                });

                // Auto-submit quando completar 4 dígitos
                document.getElementById('pin').addEventListener('input', function(e) {
                    if (this.value.length === 4) {
                        // Aguarda 300ms para melhor UX
                        setTimeout(() => {
                            if (this.value.length === 4) {
                                document.getElementById('formPIN').submit();
                            }
                        }, 300);
                    }
                });

                <?php if ($is_locked): ?>
                // Atualiza timer de bloqueio a cada segundo
                let timeRemaining = <?= $lockout_time_remaining ?>;
                const timerElement = document.getElementById('lockoutTimer');

                const interval = setInterval(function() {
                    timeRemaining--;
                    const minutesLeft = Math.ceil(timeRemaining / 60);
                    timerElement.textContent = minutesLeft;

                    if (timeRemaining <= 0) {
                        clearInterval(interval);
                        location.reload(); // Recarrega página para desbloquear
                    }
                }, 1000);
                <?php endif; ?>
                </script>
                
                <hr>
                
                <div class="alert alert-info mb-0">
                    <small>
                        <i class="fas fa-info-circle"></i>
                        <strong>Acesso restrito:</strong> Esta área é acessível apenas com PIN de 4 dígitos.
                        Entre em contato com o administrador para obter seu PIN de acesso.
                    </small>
                </div>

                <div class="alert alert-warning mb-0 mt-3">
                    <small>
                        <i class="fas fa-shield-alt"></i>
                        <strong>Segurança:</strong> Após 3 tentativas incorretas, o acesso será bloqueado por 15 minutos.
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
<?php
// pages/detalhes_vendas.php - Sistema de Consulta de Vendas por Consultor

require_once 'functions/vendas.php';
require_once 'functions/promotores.php';
require_once 'functions/pin_manager.php';

// Inicializa variáveis de sessão
if (!isset($_SESSION['detalhes_vendas'])) {
    $_SESSION['detalhes_vendas'] = [
        'passo' => 1,
        'arquivo' => null,
        'vendas' => null,
        'consultor_selecionado' => null,
        'vendas_consultor' => null
    ];
}

$passo_atual = $_SESSION['detalhes_vendas']['passo'];
$mensagem_erro = null;
$mensagem_sucesso = null;

// Processar ações
if (isset($_POST['selecionar_arquivo'])) {
    $arquivo = $_POST['arquivo_vendas'];
    
    if (file_exists($arquivo)) {
        // Processa CSV completo
        $resultado = processarVendasCSV($arquivo);
        
        $_SESSION['detalhes_vendas']['arquivo'] = $arquivo;
        $_SESSION['detalhes_vendas']['vendas'] = $resultado['vendas'];
        $_SESSION['detalhes_vendas']['passo'] = 2;
        
        $passo_atual = 2;
        $mensagem_sucesso = "Arquivo carregado com sucesso! " . count($resultado['vendas']) . " vendas encontradas.";
    } else {
        $mensagem_erro = "Arquivo não encontrado!";
    }
}

// Validação com PIN
if (isset($_POST['validar_com_pin'])) {
    $consultor_nome = $_POST['consultor_nome'];
    $pin = preg_replace('/\D/', '', $_POST['pin'] ?? '');

    // Verifica se PIN está bloqueado
    if (consultorPINBloqueado($consultor_nome)) {
        revogarPINConsultor($consultor_nome);
        $mensagem_erro = "PIN bloqueado após 3 tentativas erradas. Você precisará validar novamente com CPF/Telefone e criar um novo PIN.";
        $_SESSION['detalhes_vendas']['modo_validacao'] = 'cpf';
    } elseif (strlen($pin) !== 4) {
        $mensagem_erro = "O PIN deve ter exatamente 4 dígitos!";
    } elseif (verificarPINConsultor($consultor_nome, $pin)) {
        // PIN correto
        resetarTentativasPIN($consultor_nome);

        // Filtra vendas do consultor
        $vendas_consultor = array_filter($_SESSION['detalhes_vendas']['vendas'], function($v) use ($consultor_nome) {
            return $v['consultor'] === $consultor_nome;
        });

        $_SESSION['detalhes_vendas']['consultor_selecionado'] = $consultor_nome;
        $_SESSION['detalhes_vendas']['vendas_consultor'] = array_values($vendas_consultor);
        $_SESSION['detalhes_vendas']['passo'] = 3;

        $passo_atual = 3;
        $mensagem_sucesso = "PIN validado com sucesso! " . count($vendas_consultor) . " vendas encontradas.";
    } else {
        // PIN incorreto
        registrarTentativaErrada($consultor_nome);
        $tentativas = obterTentativasPIN($consultor_nome);

        if ($tentativas >= 3) {
            $mensagem_erro = "PIN bloqueado após 3 tentativas erradas! Você precisará validar com CPF/Telefone novamente.";
            $_SESSION['detalhes_vendas']['modo_validacao'] = 'cpf';
        } else {
            $restantes = 3 - $tentativas;
            $mensagem_erro = "PIN incorreto! Você tem mais $restantes tentativa(s).";
        }
    }
}

// Definição de novo PIN
if (isset($_POST['definir_pin'])) {
    $consultor_nome = $_SESSION['detalhes_vendas']['consultor_validado'];
    $pin = preg_replace('/\D/', '', $_POST['novo_pin'] ?? '');
    $pin_confirmacao = preg_replace('/\D/', '', $_POST['confirmar_pin'] ?? '');

    if (strlen($pin) !== 4) {
        $mensagem_erro = "O PIN deve ter exatamente 4 dígitos!";
    } elseif ($pin !== $pin_confirmacao) {
        $mensagem_erro = "Os PINs não conferem. Digite novamente.";
    } else {
        // Salva PIN
        $dados_promotor = $_SESSION['detalhes_vendas']['dados_promotor'] ?? [];
        definirPINConsultor($consultor_nome, $pin, $dados_promotor['cpf'] ?? null, $dados_promotor['telefone'] ?? null);

        // Avança para vendas
        $_SESSION['detalhes_vendas']['consultor_selecionado'] = $consultor_nome;
        $_SESSION['detalhes_vendas']['passo'] = 3;
        $passo_atual = 3;
        $mensagem_sucesso = "PIN criado com sucesso! Nas próximas vezes use seu PIN de 4 dígitos.";
    }
}

// Validação com CPF/Telefone
if (isset($_POST['validar_consultor'])) {
    $consultor_nome = $_POST['consultor_nome'];
    $identificacao = preg_replace('/[^0-9]/', '', $_POST['identificacao']); // Limpa identificação

    // Filtra vendas do consultor
    $vendas_consultor = array_filter($_SESSION['detalhes_vendas']['vendas'], function($v) use ($consultor_nome) {
        return $v['consultor'] === $consultor_nome;
    });

    $vendas_consultor = array_values($vendas_consultor);

    // Verifica se encontrou vendas
    if (empty($vendas_consultor)) {
        $mensagem_erro = "Nenhuma venda encontrada para este consultor no período selecionado.";
    } else {
        // Validação com arquivo de promotores
        $validacao_resultado = validarConsultorComPromotores($consultor_nome, $identificacao);

        if ($validacao_resultado['valido']) {
            // Salva consultor validado e vendas
            $_SESSION['detalhes_vendas']['consultor_validado'] = $consultor_nome;
            $_SESSION['detalhes_vendas']['vendas_consultor'] = $vendas_consultor;
            $_SESSION['detalhes_vendas']['dados_promotor'] = $validacao_resultado['promotor'] ?? [];

            // Redireciona para criação de PIN (passo 2.5)
            $_SESSION['detalhes_vendas']['passo'] = 2.5;
            $passo_atual = 2.5;
            $mensagem_sucesso = "Identificação validada! Agora crie um PIN de 4 dígitos para acessos futuros.";
        } else {
            $mensagem_erro = $validacao_resultado['mensagem'];

            // Debug para admin/godmode
            if (isGodMode() || isset($_GET['debug'])) {
                $mensagem_erro .= "<br><br><strong>🔍 DEBUG MODE:</strong><br>";
                $mensagem_erro .= "Consultor: " . htmlspecialchars($consultor_nome) . "<br>";
                $mensagem_erro .= "Identificação digitada: " . $identificacao . "<br>";

                if (isset($validacao_resultado['debug'])) {
                    $mensagem_erro .= "CPF encontrado: " . $validacao_resultado['debug']['cpf'] . "<br>";
                    $mensagem_erro .= "Telefone encontrado: " . $validacao_resultado['debug']['telefone'] . "<br>";
                    $mensagem_erro .= "Primeiros 4 CPF: " . $validacao_resultado['debug']['primeiros_4_cpf'] . "<br>";
                    $mensagem_erro .= "Últimos 4 CPF: " . $validacao_resultado['debug']['ultimos_4_cpf'] . "<br>";
                }
            }
        }
    }
}

if (isset($_POST['voltar_passo1'])) {
    $_SESSION['detalhes_vendas']['passo'] = 1;
    $passo_atual = 1;
}

if (isset($_POST['voltar_passo2'])) {
    $_SESSION['detalhes_vendas']['passo'] = 2;
    $_SESSION['detalhes_vendas']['consultor_selecionado'] = null;
    $_SESSION['detalhes_vendas']['vendas_consultor'] = null;
    $passo_atual = 2;
}

if (isset($_POST['nova_consulta'])) {
    $_SESSION['detalhes_vendas'] = [
        'passo' => 1,
        'arquivo' => null,
        'vendas' => null,
        'consultor_selecionado' => null,
        'vendas_consultor' => null
    ];
    $passo_atual = 1;
}

// Extrai lista de consultores únicos
$consultores_disponiveis = [];
if ($passo_atual >= 2 && $_SESSION['detalhes_vendas']['vendas']) {
    $consultores_temp = array_unique(array_column($_SESSION['detalhes_vendas']['vendas'], 'consultor'));
    sort($consultores_temp);
    $consultores_disponiveis = $consultores_temp;
}
?>

<!-- jquery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css" rel="stylesheet" />

<!-- Select2 JS (depois do jQuery) -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/pt-BR.js"></script>

<script>
$(document).ready(function() {
    // Inicializa Select2 no dropdown de consultores
    $('#consultor_select').select2({
        theme: 'bootstrap4',
        language: 'pt-BR',
        placeholder: '-- Digite para buscar seu nome --',
        allowClear: true,
        width: '100%'
    });
});
</script>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h4 class="mb-0">
                    <i class="fas fa-search"></i> Consultar Minhas Vendas
                </h4>
            </div>
            <div class="card-body">
                
                <!-- Indicador de Passos -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="progress" style="height: 30px;">
                            <div class="progress-bar <?= $passo_atual >= 1 ? 'bg-success' : 'bg-secondary' ?>" 
                                 style="width: 33.33%">
                                Passo 1: Arquivo
                            </div>
                            <div class="progress-bar <?= $passo_atual >= 2 ? 'bg-success' : 'bg-secondary' ?>" 
                                 style="width: 33.33%">
                                Passo 2: Identificação
                            </div>
                            <div class="progress-bar <?= $passo_atual >= 3 ? 'bg-success' : 'bg-secondary' ?>" 
                                 style="width: 33.34%">
                                Passo 3: Resultados
                            </div>
                        </div>
                    </div>
                </div>
                
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
                
                <!-- PASSO 1: Seleção de Arquivo -->
                <?php if ($passo_atual == 1): ?>
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-file-csv"></i> Passo 1: Selecione o Período
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-calendar"></i> Arquivo de Vendas
                                </label>
                                <select class="form-control form-control-lg" name="arquivo_vendas" required>
                                    <option value="">-- Selecione o período --</option>
                                    <?php 
                                    $arquivos_vendas = listarCSVs('vendas');
                                    foreach ($arquivos_vendas as $arquivo): 
                                    ?>
                                        <option value="<?= htmlspecialchars($arquivo['caminho']) ?>">
                                            <?= htmlspecialchars($arquivo['nome']) ?> 
                                            (<?= $arquivo['data'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <button type="submit" name="selecionar_arquivo" class="btn btn-primary btn-lg btn-block">
                                <i class="fas fa-arrow-right"></i> Próximo
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- PASSO 2: Identificação do Consultor -->
                <?php if ($passo_atual == 2):
                    // Verifica modo de validação (PIN ou CPF)
                    $consultor_selecionado_temp = $_POST['consultor_nome'] ?? $_SESSION['detalhes_vendas']['consultor_temp'] ?? null;
                    $modo_validacao = $_SESSION['detalhes_vendas']['modo_validacao'] ?? null;

                    // Se selecionou consultor, verifica se tem PIN
                    if ($consultor_selecionado_temp && !$modo_validacao) {
                        if (consultorTemPIN($consultor_selecionado_temp) && !consultorPINBloqueado($consultor_selecionado_temp)) {
                            $modo_validacao = 'pin';
                        } else {
                            $modo_validacao = 'cpf';
                        }
                        $_SESSION['detalhes_vendas']['consultor_temp'] = $consultor_selecionado_temp;
                        $_SESSION['detalhes_vendas']['modo_validacao'] = $modo_validacao;
                    }
                ?>
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="fas fa-user-check"></i> Passo 2: Identificação
                        </h5>
                    </div>
                    <div class="card-body">

                        <?php if (!$modo_validacao): ?>
                        <!-- Seleção de Consultor -->
                        <form method="post">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-user"></i> Selecione seu Nome
                                </label>
                                <select class="form-control form-control-lg" name="consultor_nome"
                                        id="consultor_select" required>
                                    <option value="">-- Selecione seu nome --</option>
                                    <?php foreach ($consultores_disponiveis as $consultor): ?>
                                        <option value="<?= htmlspecialchars($consultor) ?>">
                                            <?= htmlspecialchars($consultor) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <button type="submit" name="voltar_passo1" class="btn btn-secondary btn-lg btn-block">
                                        <i class="fas fa-arrow-left"></i> Voltar
                                    </button>
                                </div>
                                <div class="col-md-6">
                                    <button type="submit" class="btn btn-warning btn-lg btn-block">
                                        <i class="fas fa-arrow-right"></i> Próximo
                                    </button>
                                </div>
                            </div>
                        </form>

                        <?php elseif ($modo_validacao === 'pin'): ?>
                        <!-- Validação com PIN -->
                        <div class="alert alert-success">
                            <i class="fas fa-star"></i>
                            <strong>Bem-vindo de volta, <?= htmlspecialchars($consultor_selecionado_temp) ?>!</strong><br>
                            Digite seu PIN de 4 dígitos para acessar suas vendas.
                        </div>

                        <form method="post">
                            <input type="hidden" name="consultor_nome" value="<?= htmlspecialchars($consultor_selecionado_temp) ?>">

                            <div class="form-group">
                                <label>
                                    <i class="fas fa-lock"></i> Digite seu PIN
                                </label>
                                <input type="text"
                                       class="form-control form-control-lg text-center"
                                       name="pin"
                                       id="pin_input"
                                       placeholder="****"
                                       maxlength="4"
                                       pattern="[0-9]{4}"
                                       inputmode="numeric"
                                       autocomplete="off"
                                       required
                                       autofocus
                                       style="font-size: 2.5em; letter-spacing: 0.5em;">
                                <small class="form-text text-muted">
                                    <?php
                                    $tentativas = obterTentativasPIN($consultor_selecionado_temp);
                                    if ($tentativas > 0) {
                                        echo "<span class='text-danger'><i class='fas fa-exclamation-triangle'></i> " . (3 - $tentativas) . " tentativa(s) restante(s)</span>";
                                    } else {
                                        echo "Digite os 4 dígitos do seu PIN";
                                    }
                                    ?>
                                </small>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <button type="button" class="btn btn-secondary btn-lg btn-block" onclick="resetarModoValidacao()">
                                        <i class="fas fa-arrow-left"></i> Trocar Consultor
                                    </button>
                                </div>
                                <div class="col-md-6">
                                    <button type="submit" name="validar_com_pin" class="btn btn-success btn-lg btn-block">
                                        <i class="fas fa-check"></i> Entrar
                                    </button>
                                </div>
                            </div>

                            <hr>

                            <div class="text-center">
                                <small class="text-muted">
                                    <a href="#" onclick="event.preventDefault(); mudarParaCPF();">
                                        Esqueceu o PIN? Clique aqui para validar com CPF/Telefone
                                    </a>
                                </small>
                            </div>
                        </form>

                        <script>
                        function mudarParaCPF() {
                            <?php $_SESSION['detalhes_vendas']['modo_validacao'] = 'cpf'; ?>
                            location.reload();
                        }

                        // Auto-submit ao completar 4 dígitos (com delay maior)
                        let autoSubmitTimeout = null;
                        const pinInput = document.getElementById('pin_input');

                        pinInput.addEventListener('input', function(e) {
                            this.value = this.value.replace(/\D/g, '');

                            // Cancela timeout anterior se existir
                            if (autoSubmitTimeout) {
                                clearTimeout(autoSubmitTimeout);
                            }

                            if (this.value.length === 4) {
                                // Aguarda 3 segundos antes de submeter
                                autoSubmitTimeout = setTimeout(() => {
                                    if (this.value.length === 4) {
                                        this.form.submit();
                                    }
                                }, 3000);
                            }
                        });

                        // Submit imediato ao pressionar Enter
                        pinInput.addEventListener('keypress', function(e) {
                            if (e.key === 'Enter' && this.value.length === 4) {
                                // Cancela o auto-submit
                                if (autoSubmitTimeout) {
                                    clearTimeout(autoSubmitTimeout);
                                }
                                this.form.submit();
                            }
                        });
                        </script>

                        <?php else: ?>
                        <!-- Validação com CPF/Telefone -->
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Primeiro acesso ou PIN bloqueado</strong><br>
                            Valide sua identidade com CPF ou Telefone. Você criará um PIN depois.
                        </div>

                        <form method="post">
                            <input type="hidden" name="consultor_nome" value="<?= htmlspecialchars($consultor_selecionado_temp) ?>">

                            <div class="alert alert-warning">
                                <strong>Como me identificar?</strong>
                                <ul class="mb-0 mt-2">
                                    <li>4 primeiros dígitos do seu CPF (ex: 1234)</li>
                                    <li>4 últimos dígitos do seu CPF (ex: 5678)</li>
                                    <li>Seu telefone completo (ex: 11987654321)</li>
                                </ul>
                            </div>

                            <div class="form-group">
                                <label>
                                    <i class="fas fa-key"></i> Identificação (CPF ou Telefone)
                                </label>
                                <input type="text" class="form-control form-control-lg"
                                       name="identificacao" id="identificacao_input"
                                       placeholder="Digite 4 dígitos do CPF ou telefone completo"
                                       pattern="[0-9]{4,11}" maxlength="11" required autofocus>
                                <small class="form-text text-muted">
                                    Apenas números (4 a 11 dígitos)
                                </small>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <button type="button" class="btn btn-secondary btn-lg btn-block" onclick="resetarModoValidacao()">
                                        <i class="fas fa-arrow-left"></i> Trocar Consultor
                                    </button>
                                </div>
                                <div class="col-md-6">
                                    <button type="submit" name="validar_consultor"
                                            id="btn_validar" class="btn btn-warning btn-lg btn-block" disabled>
                                        <i class="fas fa-check"></i> Validar
                                    </button>
                                </div>
                            </div>
                        </form>

                        <script>
                        // Habilita botão validar apenas com 4+ dígitos
                        $('#identificacao_input').on('input', function() {
                            var valor = $(this).val();
                            if (valor.length >= 4) {
                                $('#btn_validar').prop('disabled', false);
                            } else {
                                $('#btn_validar').prop('disabled', true);
                            }
                        });
                        </script>
                        <?php endif; ?>

                        <script>
                        function resetarModoValidacao() {
                            <?php
                            $_SESSION['detalhes_vendas']['modo_validacao'] = null;
                            $_SESSION['detalhes_vendas']['consultor_temp'] = null;
                            ?>
                            location.reload();
                        }
                        </script>
                    </div>
                </div>
                <?php endif; ?>

                <!-- PASSO 2.5: Criação de PIN -->
                <?php if ($passo_atual == 2.5): ?>
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-shield-alt"></i> Crie seu PIN de Segurança
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <strong>Identificação confirmada!</strong><br>
                            Agora crie um PIN de 4 dígitos para acessar suas vendas rapidamente nas próximas vezes.
                        </div>

                        <form method="post">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-lock"></i> Crie um PIN de 4 dígitos
                                </label>
                                <input type="text"
                                       class="form-control form-control-lg text-center"
                                       name="novo_pin"
                                       id="novo_pin"
                                       placeholder="****"
                                       maxlength="4"
                                       pattern="[0-9]{4}"
                                       inputmode="numeric"
                                       autocomplete="off"
                                       required
                                       autofocus
                                       style="font-size: 2.5em; letter-spacing: 0.5em;">
                                <small class="form-text text-muted">
                                    Escolha 4 números que você não vai esquecer
                                </small>
                            </div>

                            <div class="form-group">
                                <label>
                                    <i class="fas fa-lock"></i> Confirme seu PIN
                                </label>
                                <input type="text"
                                       class="form-control form-control-lg text-center"
                                       name="confirmar_pin"
                                       id="confirmar_pin"
                                       placeholder="****"
                                       maxlength="4"
                                       pattern="[0-9]{4}"
                                       inputmode="numeric"
                                       autocomplete="off"
                                       required
                                       style="font-size: 2.5em; letter-spacing: 0.5em;">
                                <small class="form-text text-muted" id="pin_match_status"></small>
                            </div>

                            <button type="submit" name="definir_pin" class="btn btn-success btn-lg btn-block" id="btn_criar_pin" disabled>
                                <i class="fas fa-check"></i> Criar PIN e Ver Minhas Vendas
                            </button>
                        </form>

                        <script>
                        // Permite apenas números
                        $('#novo_pin, #confirmar_pin').on('input', function(e) {
                            $(this).val($(this).val().replace(/\D/g, ''));
                            verificarPINs();
                        });

                        function verificarPINs() {
                            var pin1 = $('#novo_pin').val();
                            var pin2 = $('#confirmar_pin').val();

                            if (pin1.length === 4 && pin2.length === 4) {
                                if (pin1 === pin2) {
                                    $('#pin_match_status').html('<span class="text-success"><i class="fas fa-check-circle"></i> PINs conferem!</span>');
                                    $('#btn_criar_pin').prop('disabled', false);
                                } else {
                                    $('#pin_match_status').html('<span class="text-danger"><i class="fas fa-times-circle"></i> PINs não conferem</span>');
                                    $('#btn_criar_pin').prop('disabled', true);
                                }
                            } else {
                                $('#pin_match_status').html('');
                                $('#btn_criar_pin').prop('disabled', true);
                            }
                        }
                        </script>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- PASSO 3: Resultados -->
                <?php if ($passo_atual == 3): 
                    $vendas_consultor = $_SESSION['detalhes_vendas']['vendas_consultor'];
                    $consultor_nome = $_SESSION['detalhes_vendas']['consultor_selecionado'];
                    
                    // Calcula totais
                    $total_vendas = count($vendas_consultor);
                    $valor_total = array_sum(array_column($vendas_consultor, 'valor_total'));
                    $valor_pago = array_sum(array_column($vendas_consultor, 'valor_pago'));
                    $vendas_vista = count(array_filter($vendas_consultor, function($v) { 
                        return $v['e_vista']; 
                    }));
                ?>
                
                <div class="alert alert-success">
                    <h5>
                        <i class="fas fa-user-check"></i> 
                        Bem-vindo(a), <strong><?= htmlspecialchars($consultor_nome) ?></strong>!
                    </h5>
                    <p class="mb-0">Suas vendas foram carregadas com sucesso.</p>
                </div>
                
                <!-- Cards de Resumo -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h3><?= $total_vendas ?></h3>
                                <small>Total de Vendas</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h3>R$ <?= number_format($valor_total, 2, ',', '.') ?></h3>
                                <small>Valor Total</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h3>R$ <?= number_format($valor_pago, 2, ',', '.') ?></h3>
                                <small>Valor Pago</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h3><?= $vendas_vista ?></h3>
                                <small>Vendas à Vista</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtros -->
                <div class="card mb-3">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0">
                            <i class="fas fa-filter"></i> Filtros
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label><i class="fas fa-search"></i> Buscar</label>
                                    <input type="text" class="form-control" id="filtro_busca" 
                                           placeholder="ID, produto, titular...">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label><i class="fas fa-credit-card"></i> Tipo Pagamento</label>
                                    <select class="form-control" id="filtro_tipo_pagamento">
                                        <option value="">Todos</option>
                                        <option value="À Vista">À Vista</option>
                                        <option value="Recorrente">Recorrente</option>
                                        <option value="Parcelado">Parcelado</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label><i class="fas fa-check-circle"></i> Status</label>
                                    <select class="form-control" id="filtro_status">
                                        <option value="">Todos</option>
                                        <option value="Ativo">Ativo</option>
                                        <option value="Inativo">Inativo</option>
                                        <option value="Cancelado">Cancelado</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label><i class="fas fa-money-bill"></i> 1ª Parcela Paga?</label>
                                    <select class="form-control" id="filtro_primeira_parcela">
                                        <option value="">Todos</option>
                                        <option value="sim">Sim</option>
                                        <option value="nao">Não</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <button type="button" class="btn btn-sm btn-secondary" id="btn_limpar_filtros">
                                    <i class="fas fa-eraser"></i> Limpar Filtros
                                </button>
                                <span class="ml-3" id="contador_resultados"></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabela de Vendas -->
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-list"></i> Detalhamento de Vendas
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="tabelaVendas">
                                <thead class="thead-light">
                                    <tr>
                                        <th>ID Título</th>
                                        <th>Produto</th>
                                        <th>Data Venda</th>
                                        <th>Status</th>
                                        <th>Titular</th>
                                        <th>Tipo Pagamento</th>
                                        <th class="text-center">Parcelas</th>
                                        <th class="text-center">1ª Paga?</th>
                                        <th class="text-right">Valor Pago</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vendas_consultor as $venda): 
                                        $data = DateTime::createFromFormat('Y-m-d H:i:s.u', $venda['data_venda']);
                                        if (!$data) {
                                            $data = DateTime::createFromFormat('Y-m-d H:i:s', $venda['data_venda']);
                                        }
                                    ?>
                                    <tr data-tipo-pagamento="<?= htmlspecialchars($venda['tipo_pagamento']) ?>"
                                        data-status="<?= htmlspecialchars($venda['status']) ?>"
                                        data-primeira-parcela="<?= $venda['primeira_parcela_paga'] ? 'sim' : 'nao' ?>"
                                        data-busca="<?= strtolower(htmlspecialchars($venda['id'] . ' ' . $venda['produto_atual'] . ' ' . $venda['titular'])) ?>">
                                        <td>
                                            <small class="text-muted"><?= htmlspecialchars($venda['id']) ?></small>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($venda['produto_atual']) ?>
                                            <?php if ($venda['produto_alterado']): ?>
                                                <span class="badge badge-warning badge-sm">Alterado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= $data ? $data->format('d/m/Y H:i') : $venda['data_venda'] ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $venda['status'] === 'Ativo' ? 'success' : 'danger' ?>">
                                                <?= htmlspecialchars($venda['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= mascararNome($venda['titular']) ?></td>
                                        <td>
                                            <span class="badge badge-<?= $venda['tipo_pagamento'] === 'À Vista' ? 'success' : 'info' ?>">
                                                <?= htmlspecialchars($venda['tipo_pagamento']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?= $venda['quantidade_parcelas_venda'] ?>x
                                        </td>
                                        <td class="text-center">
                                            <?php if ($venda['primeira_parcela_paga']): ?>
                                                <i class="fas fa-check text-success"></i>
                                            <?php else: ?>
                                                <i class="fas fa-times text-danger"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-right">
                                            <strong>R$ <?= number_format($venda['valor_pago'], 2, ',', '.') ?></strong>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="bg-light">
                                    <tr>
                                        <th colspan="8" class="text-right">TOTAL:</th>
                                        <th class="text-right" id="total_valor_pago">
                                            R$ <?= number_format($valor_pago, 2, ',', '.') ?>
                                        </th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- JavaScript de Filtros -->
                <script>
                $(document).ready(function() {
                    function aplicarFiltros() {
                        const busca = $('#filtro_busca').val().toLowerCase().trim();
                        const tipoPagamento = $('#filtro_tipo_pagamento').val().trim();
                        const status = $('#filtro_status').val().trim();
                        const primeiraParcela = $('#filtro_primeira_parcela').val().trim();
                        
                        let totalVisivel = 0;
                        let somaValorPago = 0;
                        
                        $('#tabelaVendas tbody tr').each(function() {
                            const $row = $(this);
                            const dataBusca = ($row.attr('data-busca') || '').trim();
                            const dataTipoPagamento = ($row.attr('data-tipo-pagamento') || '').trim();
                            const dataStatus = ($row.attr('data-status') || '').trim();
                            const dataPrimeiraParcela = ($row.attr('data-primeira-parcela') || '').trim();
                            
                            let mostrar = true;
                            
                            // Filtro de busca
                            if (busca && dataBusca.indexOf(busca) === -1) {
                                mostrar = false;
                            }
                            
                            // Filtro tipo pagamento
                            if (tipoPagamento && dataTipoPagamento !== tipoPagamento) {
                                mostrar = false;
                            }
                            
                            // Filtro status
                            if (status && dataStatus !== status) {
                                mostrar = false;
                            }
                            
                            // Filtro primeira parcela
                            if (primeiraParcela && dataPrimeiraParcela !== primeiraParcela) {
                                mostrar = false;
                            }
                            
                            if (mostrar) {
                                $row.show();
                                totalVisivel++;
                                
                                // Soma valor pago
                                const valorPagoText = $row.find('td:last').text()
                                    .replace('R$', '').replace(/\./g, '').replace(',', '.').trim();
                                somaValorPago += parseFloat(valorPagoText) || 0;
                            } else {
                                $row.hide();
                            }
                        });
                        
                        // Atualiza contador
                        $('#contador_resultados').html(
                            '<strong>' + totalVisivel + '</strong> de <?= $total_vendas ?> vendas'
                        );
                        
                        // Atualiza total
                        $('#total_valor_pago').text('R$ ' + somaValorPago.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
                    }
                    
                    // Eventos de filtro
                    $('#filtro_busca, #filtro_tipo_pagamento, #filtro_status, #filtro_primeira_parcela').on('change keyup', aplicarFiltros);
                    
                    // Limpar filtros
                    $('#btn_limpar_filtros').click(function() {
                        $('#filtro_busca').val('');
                        $('#filtro_tipo_pagamento').val('');
                        $('#filtro_status').val('');
                        $('#filtro_primeira_parcela').val('');
                        aplicarFiltros();
                    });
                    
                    // Aplica filtros inicial
                    aplicarFiltros();
                });
                </script>

                
                <!-- Botão Nova Consulta -->
                <form method="post" class="mt-4">
                    <button type="submit" name="nova_consulta" class="btn btn-primary btn-lg btn-block">
                        <i class="fas fa-redo"></i> Nova Consulta
                    </button>
                </form>
                
                <?php endif; ?>
                
            </div>
        </div>
    </div>
</div>
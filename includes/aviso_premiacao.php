<?php
// includes/aviso_premiacao.php - Aviso global sobre regras de premiação

$config_premiacao = obterConfigPremiacao();

// Só exibe se estiver configurado para exibir
if (empty($config_premiacao['exibir_aviso']) || empty($config_premiacao['mensagem'])) {
    return;
}

// Não exibe no admin
if (isset($_GET['page']) && $_GET['page'] === 'admin') {
    return;
}

// Não exibe se for godmode
if (isGodMode()) {
    return;
}
?>

<div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
    <div class="d-flex align-items-center">
        <div class="mr-3">
            <i class="fas fa-trophy fa-2x"></i>
        </div>
        <div class="flex-grow-1">
            <h5 class="alert-heading mb-2">
                <i class="fas fa-exclamation-circle"></i> Atenção: Regras de Premiação
            </h5>
            <p class="mb-0">
                <?= nl2br(htmlspecialchars($config_premiacao['mensagem'])) ?>
            </p>
        </div>
    </div>
    <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
        <span aria-hidden="true">&times;</span>
    </button>
</div>

<style>
.alert-warning {
    border-left: 5px solid #ffc107;
}
</style>
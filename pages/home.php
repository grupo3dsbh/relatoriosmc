<?php
// pages/home.php - Página inicial
?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body text-center p-5">
                <i class="fas fa-water fa-5x text-primary mb-4"></i>
                <h1 class="display-4">Sistema de Vendas Aquabeat</h1>
                <p class="lead">Gerencie vendas, consultores e relatórios de forma simples e eficiente</p>
                
                <hr class="my-4">
                
                <div class="row mt-5">
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body">
                                <i class="fas fa-chart-line fa-3x text-success mb-3"></i>
                                <h5>Relatórios</h5>
                                <p class="small">Visualize rankings e estatísticas de vendas</p>
                                <a href="?page=relatorio" class="btn btn-success btn-sm">Acessar</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body">
                                <i class="fas fa-users fa-3x text-info mb-3"></i>
                                <h5>Consultores</h5>
                                <p class="small">Gerencie promotores e consultores</p>
                                <a href="?page=consultores" class="btn btn-info btn-sm">Acessar</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body">
                                <i class="fas fa-search fa-3x text-warning mb-3"></i>
                                <h5>Consultar Vendas</h5>
                                <p class="small">Busque detalhes de vendas específicas</p>
                                <a href="?page=detalhes_vendas" class="btn btn-warning btn-sm">Acessar</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Seção Admin/Database -->
                <?php if (isAdmin() || isGodMode()): ?>
                <div class="row mt-4">
                    <div class="col-md-6 mx-auto">
                        <div class="card bg-light">
                            <div class="card-body">
                                <i class="fas fa-database fa-3x text-primary mb-3"></i>
                                <h5>Dashboard do Banco de Dados</h5>
                                <p class="small">Visualize estatísticas e gerencie o banco de dados</p>
                                <a href="?page=dashboard_db" class="btn btn-primary btn-sm">Acessar Dashboard</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isGodMode()): ?>
                <div class="alert alert-danger mt-4">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Modo God Mode Ativado!</strong> Você tem acesso ao painel administrativo.
                    <a href="?page=admin" class="btn btn-danger btn-sm ml-2">Ir para Admin</a>
                </div>
                <?php endif; ?>

                <!-- Botão para limpar cache/sessão -->
                <div class="mt-4 pt-4 border-top">
                    <button onclick="limparCache()" class="btn btn-outline-secondary btn-sm"
                            data-toggle="tooltip"
                            title="Limpa o cache da sessão e recarrega as configurações do config.json">
                        <i class="fas fa-sync-alt"></i> Limpar Cache/Sessão
                    </button>
                    <small class="text-muted d-block mt-2">
                        <i class="fas fa-info-circle"></i> Use para aplicar mudanças no config.json
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mensagem de sucesso se cache foi limpo -->
<?php if (isset($_SESSION['mensagem_sucesso'])): ?>
<script>
$(document).ready(function() {
    Swal.fire({
        icon: 'success',
        title: 'Sucesso!',
        text: '<?= $_SESSION['mensagem_sucesso'] ?>',
        timer: 3000,
        showConfirmButton: false
    });
});
</script>
<?php
unset($_SESSION['mensagem_sucesso']);
endif;
?>

<script>
// Função para limpar cache com confirmação
function limparCache() {
    Swal.fire({
        title: 'Limpar Cache?',
        text: 'Isso vai limpar a sessão atual e recarregar as configurações do config.json.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sim, limpar!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '?limpar_cache=1';
        }
    });
}

// Inicializa tooltips
$(function () {
    $('[data-toggle="tooltip"]').tooltip();
});
</script>
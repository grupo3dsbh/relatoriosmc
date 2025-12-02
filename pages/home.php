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
                
                <?php if (isGodMode()): ?>
                <div class="alert alert-danger mt-4">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Modo God Mode Ativado!</strong> Você tem acesso ao painel administrativo.
                    <a href="?page=admin" class="btn btn-danger btn-sm ml-2">Ir para Admin</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
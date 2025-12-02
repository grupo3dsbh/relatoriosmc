<?php

/*require_once 'config.php';


// ===== CRIA DIRETÓRIO DATA SE NO EXISTIR =====
if (!file_exists(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// ===== CRIA config.json se não existir =====
if (!file_exists(CONFIG_FILE)) {
    $config_padrao = obterConfigPadrao();
    file_put_contents(CONFIG_FILE, json_encode($config_padrao, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
*/

// index.php - Router principal do sistema (ATUALIZADO)
require_once 'config.php';

// Detecta qual página deve ser carregada
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Define páginas disponíveis
$paginas_disponiveis = [
    'home' => 'pages/home.php',
    'admin' => 'pages/admin.php',
    'consultores' => 'pages/consultores.php',
    'relatorio' => 'pages/relatorio.php',
    'top20' => 'pages/top20.php',  // ADICIONAR ESTA LINHA
    'detalhes_vendas' => 'pages/detalhes_vendas.php',
    'logout' => 'pages/logout.php'
];

// Proteção de rotas
$rotas_protegidas = [
    'admin' => 'admin',
    'consultores' => 'consultores'
];

// Verifica se a rota existe
if (!isset($paginas_disponiveis[$page])) {
    $page = 'home';
}

// Verifica autenticação se necessário
if (isset($rotas_protegidas[$page])) {
    $tipo_auth = $rotas_protegidas[$page];
    
    if ($tipo_auth === 'admin' && !verificarAdmin()) {
        $page = 'admin';
    } elseif ($tipo_auth === 'consultores' && !verificarConsultores()) {
        $_SESSION['redirect_after_login'] = $page;
        $page = 'consultores';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Vendas Aquabeat</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    <!-- CSS Customizado -->
    <link rel="stylesheet" href="assets/css/custom.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        
        .navbar-custom {
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .main-container {
            margin-top: 20px;
        }
        
        .navbar-brand img {
            max-height: 40px;
            width: auto;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light navbar-custom">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="?page=home">
                <?php if (temLogo()): ?>
                    <img src="<?= getLogoURL() ?>" alt="Aquabeat" class="mr-2">
                <?php else: ?>
                    <i class="fas fa-water mr-2"></i>
                <?php endif; ?>
                <strong>Aquabeat Vendas</strong>
            </a>
            
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="?page=home">
                            <i class="fas fa-home"></i> Incio
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="?page=relatorio">
                            <i class="fas fa-chart-line"></i> Relatórios
                        </a>
                    </li>
                    
                    <?php if (isGodMode()): ?>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="?page=admin">
                            <i class="fas fa-user-shield"></i> Admin
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="?page=consultores">
                            <i class="fas fa-users"></i> Consultores
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="?page=detalhes_vendas">
                            <i class="fas fa-search"></i> Consultar Vendas
                        </a>
                    </li>
                    
                    <!-- Adicionar no navbar, após o link de Relatórios -->
                    <li class="nav-item">
                        <a class="nav-link" href="?page=top20">
                            <i class="fas fa-trophy"></i> Top 20
                        </a>
                    </li>
                    
                    <?php if (verificarAdmin() || verificarConsultores()): ?>
                    <li class="nav-item">
                        <a class="nav-link text-warning" href="?page=logout">
                            <i class="fas fa-sign-out-alt"></i> Sair
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid mt-4">
        
        <?php /*
        // ===== AVISO GLOBAL DE PREMIAÇÃO =====
        if (file_exists('includes/aviso_premiacao.php')) {
            include 'includes/aviso_premiacao.php';
        }
        ?>
        
        <?php
        // Carrega a página solicitada
        $arquivo_pagina = "pages/{$page}.php";
        
        if (file_exists($arquivo_pagina)) {
            include $arquivo_pagina;
        } else {
            echo '<div class="alert alert-danger">';
            echo '<i class="fas fa-exclamation-triangle"></i> ';
            echo 'Página não encontrada!';
            echo '</div>';
        }
      */  ?>
        
    </div>
    
    <!-- Conteúdo Principal -->
    <div class="container main-container">
        <?php
        if (file_exists($paginas_disponiveis[$page])) {
            include $paginas_disponiveis[$page];
        } else {
            echo '<div class="alert alert-danger">Página não encontrada!</div>';
        }
        ?>
    </div>
    
    <!-- Footer -->
    <footer class="text-center text-white mt-5 no-print">
        <p>&copy; <?= date('Y') ?> Aquabeat - Sistema de Vendas v1.0</p>
        <small>Desenvolvido com <i class="fas fa-heart text-danger"></i></small>
    </footer>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
</body>
</html>
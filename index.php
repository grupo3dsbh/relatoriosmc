<?php
// index.php - Router principal do sistema (ATUALIZADO)
ob_start(); // Inicia buffer de saída para permitir header() redirects
require_once 'config.php';

// ===== CRIA DIRETÓRIO DATA SE NO EXISTIR =====
if (!file_exists(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// ===== CRIA config.json se não existir =====
if (!file_exists(CONFIG_FILE)) {
    $config_padrao = obterConfigPadrao();
    file_put_contents(CONFIG_FILE, json_encode($config_padrao, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ===== LIMPAR CACHE/SESSÃO =====
if (isset($_GET['limpar_cache'])) {
    // Limpa toda a sessão
    session_unset();
    session_destroy();

    // Inicia nova sessão
    session_start();

    // Recarrega configurações do arquivo
    inicializarConfiguracoes();

    // Redireciona para home com mensagem
    $_SESSION['mensagem_sucesso'] = 'Cache e sessão limpos com sucesso! Configurações recarregadas.';
    header('Location: index.php');
    exit;
}

// Detecta qual página deve ser carregada
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Define páginas disponíveis
$paginas_disponiveis = [
    'home' => 'pages/home.php',
    'admin' => 'pages/admin.php',
    'gestao_vendas' => 'pages/gestao_vendas.php',
    'consultores' => 'pages/consultores.php',
    'relatorio' => 'pages/relatorio.php',
    'top20' => 'pages/top20.php',
    'ranking_completo' => 'pages/ranking_completo.php',
    'detalhes_vendas' => 'pages/detalhes_vendas.php',
    'configuracoes' => 'pages/configuracoes.php',
    'dashboard_db' => 'pages/dashboard_db.php',
    'logout' => 'pages/logout.php'
];

// Proteção de rotas
$rotas_protegidas = [
    'admin' => 'admin',
    'gestao_vendas' => 'admin',
    'dashboard_db' => 'admin',
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

// ===== REDIRECIONAMENTO DE CONSULTORES PARA RELATÓRIO PADRÃO =====
// Consultores logados são redirecionados para o relatório padrão,
// EXCETO se tiverem acesso godmode ou filtro especial
if (verificarConsultores() && !verificarAdmin()) {
    // Verifica se tem acesso especial (godmode ou filtro)
    $tem_acesso_especial = isGodMode() || temAcessoFiltros();

    // Lista de páginas que consultores PODEM acessar sem bypass
    $paginas_liberadas = ['consultores', 'logout', 'detalhes_vendas'];

    // Se a página atual não é liberada E não tem acesso especial
    if (!in_array($page, $paginas_liberadas) && !$tem_acesso_especial) {
        // Carrega configurações para pegar relatório padrão
        $config = $_SESSION['config_sistema'] ?? carregarConfiguracoes();
        $relatorio_padrao = $config['acesso']['relatorio_padrao'] ?? 'top20';

        // Redireciona para o relatório padrão
        if ($page !== $relatorio_padrao && $page !== 'home') {
            header("Location: ?page=" . $relatorio_padrao);
            exit;
        }

        // Se está na home, redireciona também
        if ($page === 'home') {
            header("Location: ?page=" . $relatorio_padrao);
            exit;
        }
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
    <!-- CSS Customizado com cache busting -->
    <link rel="stylesheet" href="assets/css/custom.css?v=<?= filemtime('assets/css/custom.css') ?>">
    
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
                    <?php
                    // Verifica nível de acesso
                    $tem_acesso_especial = isGodMode() || temAcessoFiltros();
                    $e_consultor = verificarConsultores() && !verificarAdmin();
                    ?>

                    <?php if (!$e_consultor || $tem_acesso_especial || verificarAdmin()): ?>
                        <!-- Menu principal -->
                        <li class="nav-item">
                            <a class="nav-link" href="?page=home">
                                <i class="fas fa-home"></i> Início
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="?page=relatorio">
                                <i class="fas fa-chart-line"></i> Relatórios
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="?page=top20">
                                <i class="fas fa-trophy"></i> Top 20
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="?page=consultores">
                                <i class="fas fa-users"></i> Consultores
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- Consultar Vendas - sempre visível -->
                    <li class="nav-item">
                        <a class="nav-link" href="?page=detalhes_vendas">
                            <i class="fas fa-search"></i> Consultar Vendas
                        </a>
                    </li>

                    <?php if (verificarAdmin()): ?>
                        <!-- Separador visual -->
                        <li class="nav-item">
                            <span class="nav-link text-muted px-2">|</span>
                        </li>

                        <!-- Menu administrativo -->
                        <li class="nav-item">
                            <a class="nav-link text-warning" href="?page=gestao_vendas">
                                <i class="fas fa-tasks"></i> Gestão
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-info" href="?page=configuracoes">
                                <i class="fas fa-cog"></i> Config
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="?page=admin">
                                <i class="fas fa-user-shield"></i> Admin
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (verificarAdmin() || verificarConsultores()): ?>
                    <li class="nav-item">
                        <a class="nav-link text-secondary" href="?page=logout">
                            <i class="fas fa-sign-out-alt"></i> Sair
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if ($tem_acesso_especial && $e_consultor): ?>
                        <li class="nav-item">
                            <a class="nav-link text-success" href="#" title="Acesso especial ativado">
                                <i class="fas fa-star"></i> VIP
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid mt-4">

        <?php
        // ===== AVISO GLOBAL DE PREMIAÇÃO =====
        if (file_exists('includes/aviso_premiacao.php')) {
            include 'includes/aviso_premiacao.php';
        }
        ?>
        
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
    <footer class="bg-dark text-white mt-5 py-4 no-print">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-center text-md-left">
                    <h5 class="mb-2">MCAQUABEAT CONSULTORIA LTDA</h5>
                    <p class="mb-1">
                        <small>CNPJ: 01.453.236/0001-60</small>
                    </p>
                    <p class="mb-0">
                        <small>&copy; <?= date('Y') ?> Todos os direitos reservados.</small>
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-right">
                    <p class="mb-1">
                        <i class="fas fa-trophy"></i> <strong>Sistema de Gestão de Vendas Aquabeat</strong>
                    </p>
                    <p class="mb-0">
                        <small class="text-muted">Versão 1.0 | Desenvolvido com <i class="fas fa-heart text-danger"></i></small>
                    </p>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Botão Flutuante para Limpar Cache do Navegador -->
    <button id="btnLimparCache" class="btn-limpar-cache"
            onclick="limparCacheNavegador()"
            title="Limpar cache do navegador e recarregar página">
        <i class="fas fa-sync-alt"></i>
        <span class="btn-text">Limpar Cache</span>
    </button>

    <style>
        .btn-limpar-cache {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            cursor: pointer;
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-limpar-cache:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }

        .btn-limpar-cache:active {
            transform: translateY(0);
        }

        .btn-limpar-cache i {
            font-size: 16px;
        }

        @media (max-width: 768px) {
            .btn-limpar-cache {
                bottom: 10px;
                right: 10px;
                padding: 10px 16px;
                font-size: 12px;
            }

            .btn-limpar-cache .btn-text {
                display: none;
            }

            .btn-limpar-cache {
                width: 45px;
                height: 45px;
                border-radius: 50%;
                justify-content: center;
                padding: 0;
            }
        }

        /* Animação de rotação */
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .btn-limpar-cache.loading i {
            animation: rotate 1s linear infinite;
        }
    </style>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
    <!-- SweetAlert2 para alertas bonitos -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    /**
     * Limpa COMPLETAMENTE o cache do navegador e força reload
     */
    function limparCacheNavegador() {
        Swal.fire({
            title: 'Limpar Cache do Navegador?',
            html: `
                <p>Isso vai:</p>
                <ul style="text-align: left; display: inline-block;">
                    <li>Limpar localStorage e sessionStorage</li>
                    <li>Limpar Service Workers</li>
                    <li>Forçar recarregamento completo da página</li>
                    <li>Aplicar todas as atualizações do sistema</li>
                </ul>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#667eea',
            cancelButtonColor: '#d33',
            confirmButtonText: '<i class="fas fa-sync-alt"></i> Sim, limpar!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                executarLimpezaCache();
            }
        });
    }

    function executarLimpezaCache() {
        // Adiciona classe loading ao botão
        const btn = document.getElementById('btnLimparCache');
        btn.classList.add('loading');
        btn.disabled = true;

        // 1. Limpa localStorage
        try {
            localStorage.clear();
            console.log('✓ localStorage limpo');
        } catch (e) {
            console.warn('Erro ao limpar localStorage:', e);
        }

        // 2. Limpa sessionStorage
        try {
            sessionStorage.clear();
            console.log('✓ sessionStorage limpo');
        } catch (e) {
            console.warn('Erro ao limpar sessionStorage:', e);
        }

        // 3. Limpa Service Workers
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then(function(registrations) {
                for(let registration of registrations) {
                    registration.unregister();
                    console.log('✓ Service Worker removido');
                }
            });
        }

        // 4. Limpa Cache API
        if ('caches' in window) {
            caches.keys().then(function(names) {
                for (let name of names) {
                    caches.delete(name);
                    console.log('✓ Cache removido:', name);
                }
            });
        }

        // 5. Limpa cookies e dados do site
        try {
            // Limpa todos os cookies do domínio atual
            document.cookie.split(";").forEach(function(c) {
                document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/");
            });
            console.log('✓ Cookies limpos');
        } catch (e) {
            console.warn('Erro ao limpar cookies:', e);
        }

        // 6. Chama servidor para limpar sessão PHP
        fetch('?limpar_cache=1', {
            method: 'GET',
            cache: 'no-store',
            headers: {
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0'
            }
        }).then(() => {
            console.log('✓ Sessão PHP limpa no servidor');
        }).catch(e => {
            console.warn('Erro ao limpar sessão PHP:', e);
        }).finally(() => {
            // 7. Mostra mensagem e recarrega
            Swal.fire({
                title: 'Cache Limpo!',
                text: 'Recarregando página sem cache...',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                // Force hard reload (equivalente a Ctrl+F5)
                // Adiciona timestamp para forçar reload de todos os recursos
                const url = new URL(window.location.href);
                url.searchParams.set('_nocache', Date.now());
                url.searchParams.delete('limpar_cache'); // Remove o parâmetro de limpar cache

                // Recarrega SEM usar cache
                window.location.replace(url.toString());

                // Fallback: força reload hard
                setTimeout(() => {
                    window.location.reload(true);
                }, 100);
            });
        });
    }

    // Atalho de teclado: Ctrl+Shift+R para limpar cache
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.shiftKey && e.key === 'R') {
            e.preventDefault();
            limparCacheNavegador();
        }
    });

    console.log('%c💡 Dica: Pressione Ctrl+Shift+R para limpar o cache rapidamente!',
                'background: #667eea; color: white; padding: 5px 10px; border-radius: 3px;');
    </script>
</body>
</html>
<?php ob_end_flush(); // Flush buffer de saída ?>
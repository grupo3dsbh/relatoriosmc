<?php
// config.php - Configurações centralizadas do sistema (ATUALIZADO)
session_start();

// ===== TIMEZONE =====
date_default_timezone_set('America/Sao_Paulo');
ini_set('date.timezone', 'America/Sao_Paulo');

// Configurações de erro
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Caminhos
define('BASE_DIR', __DIR__);
define('DATA_DIR', BASE_DIR . '/data');
define('UPLOADS_DIR', DATA_DIR . '/uploads');
define('VENDAS_DIR', UPLOADS_DIR . '/vendas');
define('PROMOTORES_DIR', UPLOADS_DIR . '/promotores');
define('TEMP_DIR', BASE_DIR . '/temp');
define('ASSETS_DIR', BASE_DIR . '/assets');
define('CSS_DIR', ASSETS_DIR . '/css');
define('IMG_DIR', ASSETS_DIR . '/img');
define('LOGO_PATH', IMG_DIR . '/logo-aquabeat.png');

// Senhas
define('SENHA_ADMIN', 'sign@3DS');
define('SENHA_CONSULTORES_DEFAULT', 'consultores123');

// Configuraes do sistema
define('TEMP_LIFETIME', 300); // 5 minutos
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('MAX_LOGO_SIZE', 2 * 1024 * 1024); // 2MB para logo

// Configuraão de pontuação padrão
define('PONTOS_PADRAO', [
    1 => 1,
    2 => 2,
    3 => 2,
    4 => 3,
    5 => 3,
    6 => 3,
    7 => 3,
    8 => 4,
    9 => 4,
    10 => 4,
    'acima_10' => 4,
    'acima_5_vista' => 5
]);

// Cria diretrios necessários
$diretorios = [
    DATA_DIR, 
    UPLOADS_DIR, 
    VENDAS_DIR, 
    PROMOTORES_DIR, 
    TEMP_DIR,
    ASSETS_DIR,
    CSS_DIR,
    IMG_DIR
];

foreach ($diretorios as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
        // Cria arquivo .htaccess para proteger pastas sensíveis
        if (in_array($dir, [DATA_DIR, UPLOADS_DIR, VENDAS_DIR, PROMOTORES_DIR])) {
            file_put_contents($dir . '/.htaccess', "Order deny,allow\nDeny from all");
        }
    }
}

// Cria CSS customizado se não existir
$css_custom_path = CSS_DIR . '/custom.css';
if (!file_exists($css_custom_path)) {
    $css_content = <<<CSS
/* custom.css - Estilos personalizados do sistema */

/* Variáveis de cores */
:root {
    --primary-color: #667eea;
    --secondary-color: #764ba2;
    --success-color: #28a745;
    --warning-color: #ffc107;
    --danger-color: #dc3545;
    --info-color: #17a2b8;
    --dark-color: #343a40;
}

/* Animações suaves */
.fade-in {
    animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Cards com hover effect */
.card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}

/* Botões personalizados */
.btn {
    transition: all 0.3s ease;
    font-weight: 500;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

/* Troféus com brilho */
.trophy-gold {
    color: #FFD700;
    text-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
    animation: pulse-gold 2s infinite;
}

.trophy-silver {
    color: #C0C0C0;
    text-shadow: 0 0 10px rgba(192, 192, 192, 0.5);
    animation: pulse-silver 2s infinite;
}

.trophy-bronze {
    color: #CD7F32;
    text-shadow: 0 0 10px rgba(205, 127, 50, 0.5);
    animation: pulse-bronze 2s infinite;
}

@keyframes pulse-gold {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

@keyframes pulse-silver {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

@keyframes pulse-bronze {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

/* Tabelas responsivas melhoradas */
.table-responsive {
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.table thead th {
    position: sticky;
    top: 0;
    z-index: 10;
    background-color: #343a40;
}

/* Loading overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.9);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.loading-spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid var(--primary-color);
    border-radius: 50%;
    width: 50px;
    height: 50px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Badge personalizado */
.badge-lg {
    font-size: 1em;
    padding: 0.5em 0.8em;
}

/* Modal melhorado */
.modal-content {
    border-radius: 15px;
    border: none;
}

.modal-header {
    border-radius: 15px 15px 0 0;
}

/* Alertas animados */
.alert {
    animation: slideInDown 0.5s ease;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Footer fixo */
footer {
    margin-top: 50px;
    padding: 20px 0;
}

/* Logo responsivo */
.logo-container {
    max-width: 200px;
    margin: 0 auto;
}

.logo-container img {
    max-width: 100%;
    height: auto;
}

/* Navbar customizada */
.navbar-custom {
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95) !important;
}

/* Responsividade */
@media (max-width: 768px) {
    .card {
        margin-bottom: 1rem;
    }
    
    .table {
        font-size: 0.85rem;
    }
    
    .btn {
        font-size: 0.9rem;
    }
}

/* Print styles */
@media print {
    .no-print {
        display: none !important;
    }
    
    .card {
        box-shadow: none;
        border: 1px solid #ddd;
    }
}

/* Scrollbar customizada */
::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background: var(--primary-color);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--secondary-color);
}
CSS;
    file_put_contents($css_custom_path, $css_content);
}

// Inicializa sessões
if (!isset($_SESSION['senha_consultores'])) {
    $_SESSION['senha_consultores'] = SENHA_CONSULTORES_DEFAULT;
}

if (!isset($_SESSION['relatorios'])) {
    $_SESSION['relatorios'] = [];
}

if (!isset($_SESSION['promotores'])) {
    $_SESSION['promotores'] = [];
}

// Configuração de campos visveis para consultores
if (!isset($_SESSION['campos_visiveis_consultores'])) {
    $_SESSION['campos_visiveis_consultores'] = [
        'pontos' => true,
        'vendas' => true,
        'valor_total' => true,
        'valor_pago' => true,
        'detalhamento' => true,
        'cotas_sap' => true
    ];
}

// Configuração de pontos necessários para premiaço
if (!isset($_SESSION['config_premiacoes'])) {
    $_SESSION['config_premiacoes'] = [
        'pontos_por_sap' => 21,  // A cada 21 pontos = 1 SAP
        'vendas_para_dip' => 30, // 30 vendas OU
        'vendas_acima_2vagas_para_dip' => 20 // 20 vendas acima de 2 vagas
    ];
}

// Função para verificar autenticação admin
function verificarAdmin() {
    return isset($_SESSION['admin_autenticado']) && $_SESSION['admin_autenticado'] === true;
}

// Funão para verificar autenticaço consultores
function verificarConsultores() {
    return isset($_SESSION['consultores_autenticado']) && $_SESSION['consultores_autenticado'] === true;
}

// Função para fazer login admin
function loginAdmin($senha) {
    if ($senha === SENHA_ADMIN) {
        $_SESSION['admin_autenticado'] = true;
        return true;
    }
    return false;
}

// Função para fazer login consultores
function loginConsultores($senha) {
    if ($senha === $_SESSION['senha_consultores']) {
        $_SESSION['consultores_autenticado'] = true;
        return true;
    }
    return false;
}

// Função para fazer logout
function logout() {
    unset($_SESSION['admin_autenticado']);
    unset($_SESSION['consultores_autenticado']);
}

// Função para salvar arquivo CSV
function salvarCSV($arquivo_temporario, $tipo = 'vendas') {
    $diretorio = $tipo === 'vendas' ? VENDAS_DIR : PROMOTORES_DIR;
    $nome_arquivo = date('Y-m-d_His') . '_' . $tipo . '.csv';
    $caminho_destino = $diretorio . '/' . $nome_arquivo;
    
    if (move_uploaded_file($arquivo_temporario, $caminho_destino)) {
        return [
            'sucesso' => true,
            'caminho' => $caminho_destino,
            'nome' => $nome_arquivo
        ];
    }
    
    return ['sucesso' => false, 'erro' => 'Erro ao mover arquivo'];
}

// Função para salvar logo
function salvarLogo($arquivo_temporario) {
    // Valida tipo de arquivo
    $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
    $info = getimagesize($arquivo_temporario);
    
    if ($info === false) {
        return ['sucesso' => false, 'erro' => 'Arquivo não é uma imagem vlida'];
    }
    
    $extensao = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extensao, $extensoes_permitidas)) {
        return ['sucesso' => false, 'erro' => 'Formato não permitido. Use: JPG, PNG ou GIF'];
    }
    
    // Verifica tamanho
    if (filesize($arquivo_temporario) > MAX_LOGO_SIZE) {
        return ['sucesso' => false, 'erro' => 'Arquivo muito grande. Máximo: 2MB'];
    }
    
    // Remove logo antigo se existir
    if (file_exists(LOGO_PATH)) {
        unlink(LOGO_PATH);
    }
    
    // Move arquivo
    if (move_uploaded_file($arquivo_temporario, LOGO_PATH)) {
        return [
            'sucesso' => true,
            'caminho' => LOGO_PATH,
            'url' => 'assets/img/logo-aquabeat.png'
        ];
    }
    
    return ['sucesso' => false, 'erro' => 'Erro ao salvar logo'];
}

// Função para verificar se logo existe
function temLogo() {
    return file_exists(LOGO_PATH);
}

// Função para obter URL do logo
function getLogoURL() {
    if (temLogo()) {
        return 'assets/img/logo-aquabeat.png?v=' . filemtime(LOGO_PATH);
    }
    return null;
}

// Função para listar CSVs salvos
function listarCSVs($tipo = 'vendas') {
    $diretorio = $tipo === 'vendas' ? VENDAS_DIR : PROMOTORES_DIR;
    $arquivos = glob($diretorio . '/*.csv');
    
    $lista = [];
    foreach ($arquivos as $arquivo) {
        $lista[] = [
            'caminho' => $arquivo,
            'nome' => basename($arquivo),
            'data' => date('d/m/Y H:i:s', filemtime($arquivo)),
            'tamanho' => filesize($arquivo)
        ];
    }
    
    // Ordena por data (mais recente primeiro)
    usort($lista, function($a, $b) {
        return filemtime($b['caminho']) - filemtime($a['caminho']);
    });
    
    return $lista;
}

// Função para formatar bytes
function formatarBytes($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}

// Carrega funções
require_once BASE_DIR . '/functions/vendas.php';
require_once BASE_DIR . '/functions/promotores.php';

// ===== CARREGA FUNÇÕES DE CONFIGURAÇÃO =====
require_once __DIR__ . '/functions/configuracoes.php';

// ===== INICIALIZA CONFIGURAÇÕES DO SISTEMA =====
inicializarConfiguracoes();

// ===== DEBUG: Mostra se godmode est ativo =====
if (isGodMode()) {
    echo "<!-- GODMODE ATIVO -->";
}

if (temAcessoFiltros()) {
    echo "<!-- ACESSO AOS FILTROS LIBERADO -->";
}
?>
<?php
session_start();
require_once '../config.php';
require_once '../functions/vendas.php';
require_once '../functions/promotores.php';

header('Content-Type: application/json');

if (!isset($_POST['consultor']) || !isset($_POST['identificacao'])) {
    echo json_encode([
        'success' => false,
        'valido' => false,
        'mensagem' => 'Dados incompletos'
    ]);
    exit;
}

$consultor_nome = $_POST['consultor'];
$identificacao = preg_replace('/[^0-9]/', '', $_POST['identificacao']);

// Usa a função centralizada
$resultado = validarConsultorComPromotores($consultor_nome, $identificacao);

echo json_encode([
    'success' => true,
    'valido' => $resultado['valido'],
    'mensagem' => $resultado['mensagem']
]);
?>

<?php /*
session_start();
require_once '../config.php';
require_once '../functions/vendas.php';
require_once '../functions/promotores.php';

header('Content-Type: application/json');

if (!isset($_POST['consultor']) || !isset($_POST['identificacao'])) {
    echo json_encode([
        'success' => false,
        'valido' => false,
        'mensagem' => 'Dados incompletos'
    ]);
    exit;
}

$consultor_nome = $_POST['consultor'];
$identificacao = preg_replace('/[^0-9]/', '', $_POST['identificacao']);

// Carrega promotores mais recente
$arquivos_promotores = listarCSVs('promotores');
if (empty($arquivos_promotores)) {
    echo json_encode([
        'success' => false,
        'valido' => false,
        'mensagem' => 'Nenhum arquivo de promotores disponível'
    ]);
    exit;
}

$arquivo_promotores = $arquivos_promotores[0]['caminho'];
$dados_promotores = processarPromotoresCSV($arquivo_promotores);

// Busca o promotor pelo nome
$promotor_encontrado = null;
foreach ($dados_promotores['promotores'] as $promotor) {
    if ($promotor['nome'] === $consultor_nome) {
        $promotor_encontrado = $promotor;
        break;
    }
}

if (!$promotor_encontrado) {
    echo json_encode([
        'success' => false,
        'valido' => false,
        'mensagem' => 'Consultor não encontrado'
    ]);
    exit;
}

// Valida CPF ou Telefone
$cpf = preg_replace('/[^0-9]/', '', $promotor_encontrado['cpf']);
$telefone = preg_replace('/[^0-9]/', '', $promotor_encontrado['telefone']);

$valido = false;

// Validação por CPF (4 primeiros ou 4 últimos dígitos)
if (strlen($identificacao) == 4) {
    $primeiros_4_cpf = substr($cpf, 0, 4);
    $ultimos_4_cpf = substr($cpf, -4);
    
    if ($identificacao === $primeiros_4_cpf || $identificacao === $ultimos_4_cpf) {
        $valido = true;
    }
}

// Validação por telefone completo
if (!$valido && strlen($identificacao) == 11) {
    if ($identificacao === $telefone) {
        $valido = true;
    }
}

// Validação por CPF completo
if (!$valido && strlen($identificacao) == 11) {
    if ($identificacao === $cpf) {
        $valido = true;
    }
}

echo json_encode([
    'success' => true,
    'valido' => $valido,
    'mensagem' => $valido ? 'Acesso liberado' : 'CPF ou telefone inválido'
]);
*/?>
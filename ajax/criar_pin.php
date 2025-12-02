<?php
session_start();
require_once '../config.php';
require_once '../functions/pin_manager.php';
require_once '../functions/promotores.php';

header('Content-Type: application/json');

if (!isset($_POST['consultor']) || !isset($_POST['pin']) || !isset($_POST['pin_confirmacao'])) {
    echo json_encode([
        'success' => false,
        'mensagem' => 'Dados incompletos'
    ]);
    exit;
}

$consultor_nome = $_POST['consultor'];
$pin = preg_replace('/\D/', '', $_POST['pin']);
$pin_confirmacao = preg_replace('/\D/', '', $_POST['pin_confirmacao']);

// Valida PIN
if (strlen($pin) !== 4) {
    echo json_encode([
        'success' => false,
        'mensagem' => 'O PIN deve ter exatamente 4 dígitos'
    ]);
    exit;
}

if ($pin !== $pin_confirmacao) {
    echo json_encode([
        'success' => false,
        'mensagem' => 'Os PINs não conferem'
    ]);
    exit;
}

// Busca dados do promotor
$cpf = null;
$telefone = null;

$arquivos_promotores = listarCSVs('promotores');
if (!empty($arquivos_promotores)) {
    $arquivo_promotores = $arquivos_promotores[0]['caminho'];
    $dados_promotores = processarPromotoresCSV($arquivo_promotores);

    foreach ($dados_promotores['promotores'] as $promotor) {
        if ($promotor['nome'] === $consultor_nome) {
            $cpf = $promotor['cpf'];
            $telefone = $promotor['telefone'];
            break;
        }
    }
}

// Define PIN
if (definirPINConsultor($consultor_nome, $pin, $cpf, $telefone)) {
    echo json_encode([
        'success' => true,
        'mensagem' => 'PIN criado com sucesso! Use-o nas próximas vezes.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'mensagem' => 'Erro ao criar PIN'
    ]);
}
?>

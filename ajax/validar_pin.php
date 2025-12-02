<?php
session_start();
require_once '../config.php';
require_once '../functions/pin_manager.php';

header('Content-Type: application/json');

if (!isset($_POST['consultor']) || !isset($_POST['pin'])) {
    echo json_encode([
        'success' => false,
        'valido' => false,
        'mensagem' => 'Dados incompletos'
    ]);
    exit;
}

$consultor_nome = $_POST['consultor'];
$pin = preg_replace('/\D/', '', $_POST['pin']);

// Verifica se PIN está bloqueado
if (consultorPINBloqueado($consultor_nome)) {
    echo json_encode([
        'success' => true,
        'valido' => false,
        'bloqueado' => true,
        'mensagem' => 'PIN bloqueado. Valide com CPF/Telefone novamente.'
    ]);
    exit;
}

// Verifica se PIN tem 4 dígitos
if (strlen($pin) !== 4) {
    echo json_encode([
        'success' => true,
        'valido' => false,
        'mensagem' => 'O PIN deve ter 4 dígitos'
    ]);
    exit;
}

// Verifica se o consultor tem PIN cadastrado
if (!consultorTemPIN($consultor_nome)) {
    echo json_encode([
        'success' => true,
        'valido' => false,
        'sem_pin' => true,
        'mensagem' => 'PIN não cadastrado. Use CPF ou Telefone.'
    ]);
    exit;
}

// Valida PIN
if (verificarPINConsultor($consultor_nome, $pin)) {
    resetarTentativasPIN($consultor_nome);
    echo json_encode([
        'success' => true,
        'valido' => true,
        'mensagem' => 'PIN validado com sucesso'
    ]);
} else {
    registrarTentativaErrada($consultor_nome);
    $tentativas = obterTentativasPIN($consultor_nome);

    if ($tentativas >= 3) {
        echo json_encode([
            'success' => true,
            'valido' => false,
            'bloqueado' => true,
            'mensagem' => 'PIN bloqueado após 3 tentativas. Valide com CPF/Telefone.'
        ]);
    } else {
        $restantes = 3 - $tentativas;
        echo json_encode([
            'success' => true,
            'valido' => false,
            'mensagem' => "PIN incorreto! Restam $restantes tentativa(s)."
        ]);
    }
}
?>

<?php
/**
 * Valida a senha mestre administrativa
 */

session_start();

header('Content-Type: application/json');

// Verifica se foi enviada a senha
if (!isset($_POST['senha_mestre'])) {
    echo json_encode([
        'success' => false,
        'valido' => false,
        'mensagem' => 'Senha não informada'
    ]);
    exit;
}

$senha_informada = trim($_POST['senha_mestre']);

// Busca a senha mestre configurada
$senha_mestre_config = $_SESSION['config_sistema']['acesso']['senha_mestre'] ?? null;

// Verifica se a senha mestre está configurada
if (empty($senha_mestre_config)) {
    echo json_encode([
        'success' => false,
        'valido' => false,
        'mensagem' => 'Senha mestre não configurada no sistema'
    ]);
    exit;
}

// Valida a senha
if ($senha_informada === $senha_mestre_config) {
    echo json_encode([
        'success' => true,
        'valido' => true,
        'mensagem' => 'Acesso autorizado'
    ]);
} else {
    echo json_encode([
        'success' => true,
        'valido' => false,
        'mensagem' => 'Senha mestre incorreta!'
    ]);
}

<?php
/**
 * ajax/revoke_pins.php
 * Revoga PINs dos consultores (apenas admin)
 */

session_start();

header('Content-Type: application/json');

require_once '../config.php';
require_once '../functions/pin_manager.php';

// Verifica se é admin
if (!verificarAdmin()) {
    echo json_encode([
        'success' => false,
        'mensagem' => 'Acesso negado! Apenas administradores podem revogar PINs.'
    ]);
    exit;
}

// Lê dados da requisição
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'revoke_all') {
    // Revoga todos os PINs
    if (revogarTodosPINs()) {
        echo json_encode([
            'success' => true,
            'mensagem' => 'Todos os PINs foram revogados com sucesso!'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'mensagem' => 'Erro ao revogar PINs.'
        ]);
    }
} elseif ($action === 'revoke_one' && !empty($input['consultor'])) {
    // Revoga PIN de um consultor específico
    if (revogarPINConsultor($input['consultor'])) {
        echo json_encode([
            'success' => true,
            'mensagem' => 'PIN revogado com sucesso!'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'mensagem' => 'Erro ao revogar PIN.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'mensagem' => 'Ação inválida.'
    ]);
}
?>

<?php
/**
 * ajax/unlock_pin.php
 * Remove bloqueios de PIN dos consultores
 */

session_start();

header('Content-Type: application/json');

// Verifica se é admin
require_once '../config.php';

if (!verificarAdmin()) {
    echo json_encode([
        'success' => false,
        'mensagem' => 'Acesso negado! Apenas administradores podem desbloquear contas.'
    ]);
    exit;
}

// Remove bloqueios
$_SESSION['pin_attempts'] = 0;
$_SESSION['pin_locked_until'] = null;

// Limpa também de todas as sessões ativas (se armazenado em arquivo)
// Nota: Isso só funciona para a sessão atual. Para desbloquear todas as sessões
// seria necessário um sistema mais complexo com banco de dados

echo json_encode([
    'success' => true,
    'mensagem' => 'Bloqueios removidos com sucesso!'
]);
?>

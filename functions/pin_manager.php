<?php
/**
 * Gerenciamento de PINs dos consultores
 * PINs são armazenados em data/pins.json
 */

define('PINS_FILE', DATA_DIR . '/pins.json');

/**
 * Carrega todos os PINs cadastrados
 */
function carregarPINs() {
    if (!file_exists(PINS_FILE)) {
        return [];
    }

    $json = file_get_contents(PINS_FILE);
    $data = json_decode($json, true);

    return $data ?? [];
}

/**
 * Salva os PINs no arquivo
 */
function salvarPINs($pins) {
    $json = json_encode($pins, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents(PINS_FILE, $json) !== false;
}

/**
 * Obtém dados do PIN de um consultor
 * @param string $consultor_nome Nome do consultor
 * @return array|null Dados do PIN ou null se não existir
 */
function obterPINConsultor($consultor_nome) {
    $pins = carregarPINs();
    $key = md5(strtolower(trim($consultor_nome)));

    return $pins[$key] ?? null;
}

/**
 * Define ou atualiza o PIN de um consultor
 */
function definirPINConsultor($consultor_nome, $pin, $cpf = null, $telefone = null) {
    $pins = carregarPINs();
    $key = md5(strtolower(trim($consultor_nome)));

    $pins[$key] = [
        'consultor' => $consultor_nome,
        'pin' => password_hash($pin, PASSWORD_DEFAULT),
        'criado_em' => date('Y-m-d H:i:s'),
        'tentativas_erradas' => 0,
        'bloqueado' => false,
        'cpf' => $cpf,
        'telefone' => $telefone
    ];

    return salvarPINs($pins);
}

/**
 * Verifica se o PIN está correto
 */
function verificarPINConsultor($consultor_nome, $pin) {
    $dados_pin = obterPINConsultor($consultor_nome);

    if (!$dados_pin) {
        return false;
    }

    // Verifica se está bloqueado
    if ($dados_pin['bloqueado']) {
        return false;
    }

    return password_verify($pin, $dados_pin['pin']);
}

/**
 * Incrementa tentativas erradas e bloqueia se necessário
 */
function registrarTentativaErrada($consultor_nome) {
    $pins = carregarPINs();
    $key = md5(strtolower(trim($consultor_nome)));

    if (!isset($pins[$key])) {
        return false;
    }

    $pins[$key]['tentativas_erradas']++;

    // Bloqueia após 3 tentativas
    if ($pins[$key]['tentativas_erradas'] >= 3) {
        $pins[$key]['bloqueado'] = true;
        $pins[$key]['bloqueado_em'] = date('Y-m-d H:i:s');
    }

    return salvarPINs($pins);
}

/**
 * Reseta tentativas erradas após login bem-sucedido
 */
function resetarTentativasPIN($consultor_nome) {
    $pins = carregarPINs();
    $key = md5(strtolower(trim($consultor_nome)));

    if (!isset($pins[$key])) {
        return false;
    }

    $pins[$key]['tentativas_erradas'] = 0;

    return salvarPINs($pins);
}

/**
 * Revoga o PIN de um consultor (força redefinição)
 */
function revogarPINConsultor($consultor_nome) {
    $pins = carregarPINs();
    $key = md5(strtolower(trim($consultor_nome)));

    if (isset($pins[$key])) {
        unset($pins[$key]);
        return salvarPINs($pins);
    }

    return true;
}

/**
 * Revoga todos os PINs
 */
function revogarTodosPINs() {
    return salvarPINs([]);
}

/**
 * Verifica se consultor tem PIN definido
 */
function consultorTemPIN($consultor_nome) {
    return obterPINConsultor($consultor_nome) !== null;
}

/**
 * Verifica se PIN está bloqueado
 */
function consultorPINBloqueado($consultor_nome) {
    $dados_pin = obterPINConsultor($consultor_nome);

    if (!$dados_pin) {
        return false;
    }

    return $dados_pin['bloqueado'] ?? false;
}

/**
 * Obtém número de tentativas erradas
 */
function obterTentativasPIN($consultor_nome) {
    $dados_pin = obterPINConsultor($consultor_nome);

    if (!$dados_pin) {
        return 0;
    }

    return $dados_pin['tentativas_erradas'] ?? 0;
}
?>

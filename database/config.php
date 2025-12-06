<?php
/**
 * Configuração do Banco de Dados
 * Compatível com WordPress para migração futura
 */

// Configurações do banco (podem ser sobrescritas por constantes)
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'aquabeat_relatorios');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}
if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', '');
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}
if (!defined('DB_COLLATE')) {
    define('DB_COLLATE', 'utf8mb4_unicode_ci');
}

// Prefixo das tabelas (compatível com WordPress)
if (!defined('TABLE_PREFIX')) {
    define('TABLE_PREFIX', 'aq_');
}

// Nomes das tabelas
define('TABLE_VENDAS', TABLE_PREFIX . 'vendas');
define('TABLE_PROMOTORES', TABLE_PREFIX . 'promotores');
define('TABLE_PINS', TABLE_PREFIX . 'pins_consultores');
define('TABLE_USUARIOS', TABLE_PREFIX . 'usuarios');
define('TABLE_ARQUIVOS_CSV', TABLE_PREFIX . 'arquivos_csv');
define('TABLE_CONFIGURACOES', TABLE_PREFIX . 'configuracoes');
define('TABLE_RANGES', TABLE_PREFIX . 'ranges_pontuacao');
define('TABLE_LOG_IMPORTS', TABLE_PREFIX . 'log_imports');

// Carrega a classe Database
require_once __DIR__ . '/Database.php';

// Inicializa conexão global
global $aqdb;
$aqdb = new AquabeatDatabase();

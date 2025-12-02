<?php
/**
 * Funções para gerenciar configurações do sistema
 * Salva em arquivo JSON para persistência entre sessões
 */

// Caminho do arquivo de configuração
define('CONFIG_FILE', DATA_DIR . '/config.json');

/**
 * Carrega configurações do arquivo
 */
function carregarConfiguracoes() {
    if (file_exists(CONFIG_FILE)) {
        $json = file_get_contents(CONFIG_FILE);
        $config = json_decode($json, true);
        
        if ($config) {
            return $config;
        }
    }
    
    // Retorna configurações padrão se arquivo não existe
    return obterConfigPadrao();
}

/**
 * Retorna configurações padrão do sistema
 */
function obterConfigPadrao() {
    return [
        'premiacoes' => [
            'pontos_por_vaga' => 3,
            'pontos_venda_vista' => 2,
            'vendas_para_sap' => 21,
            'vendas_para_dip' => 500
        ],
        'ranges' => [
            // Exemplo de range
             [
                 'nome' => 'Black Week',
                 'data_inicio' => '2025-11-22',
                 'data_fim' => '2025-11-30',
                 'ativo' => true,
                 'pontos' => [
                     '1vaga_vista' => 2, 
                     '2a3_vagas' => 3,
                     '4a7_vagas' => 4,
                     '8a10_vagas' => 5,
                     'acima_10' => 5,
                     'acima_5_vista' => 7
                 ]
             ]
        ],
        'campos_visiveis_consultores' => [
            'pontos' => true,
            'vendas' => true,
            'valor_total' => false,
            'valor_pago' => false,
            'detalhamento' => true,
            'cotas_sap' => true
        ],
        'acesso' => [
            'relatorio_padrao' => 'top20', // 'top20' ou 'ranking_completo'
            'senha_filtro' => '', // Senha encode/decode para liberar filtros
            'senha_godmode' => 'admin123' // Senha para modo admin
        ],
        'premiacao' => [
            'mensagem' => 'Para premiação Top 20, só serão contabilizadas as vendas com primeira parcela paga até dia 07 do mês posterior. Ex: vendas de Novembro até 07 de Dezembro.',
            'dia_limite_primeira_parcela' => 7,
            'exibir_aviso' => true
        ],
        'periodo_relatorio' => [
            'data_inicial' => date('Y-m-01'), // Primeiro dia do mês atual
            'data_final' => date('Y-m-t'), // Último dia do mês atual
            'filtro_status' => 'Ativo',
            'apenas_primeira_parcela' => false,
            'apenas_vista' => false
        ],
        'ultima_atualizacao' => date('Y-m-d H:i:s')
    ];
}

/**
 * Salva configurações no arquivo
 */
function salvarConfiguracoes($config) {
    // Atualiza timestamp
    $config['ultima_atualizacao'] = date('Y-m-d H:i:s');
    
    // Salva no arquivo
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $resultado = file_put_contents(CONFIG_FILE, $json);
    
    if ($resultado === false) {
        return [
            'sucesso' => false,
            'mensagem' => 'Erro ao salvar arquivo de configuração!'
        ];
    }
    
    // Atualiza sessão também
    $_SESSION['config_sistema'] = $config;
    
    return [
        'sucesso' => true,
        'mensagem' => 'Configuraçes salvas com sucesso!'
    ];
}

/**
 * Inicializa configurações na sessão
 */
function inicializarConfiguracoes() {
    if (!isset($_SESSION['config_sistema'])) {
        $_SESSION['config_sistema'] = carregarConfiguracoes();
    }
    
    // Atalhos para compatibilidade com código antigo
    if (isset($_SESSION['config_sistema']['premiacoes'])) {
        $_SESSION['config_premiacoes'] = $_SESSION['config_sistema']['premiacoes'];
    }
    
    if (isset($_SESSION['config_sistema']['ranges'])) {
        $_SESSION['ranges_pontuacao'] = $_SESSION['config_sistema']['ranges'];
    }
    
    if (isset($_SESSION['config_sistema']['campos_visiveis_consultores'])) {
        $_SESSION['campos_visiveis_consultores'] = $_SESSION['config_sistema']['campos_visiveis_consultores'];
    }
}

/**
 * Verifica se usuário tem acesso godmode
 */
function isGodMode() {
    if (isset($_GET['godmode']) && !empty($_SESSION['config_sistema']['acesso']['senha_godmode'])) {
        return $_GET['godmode'] === $_SESSION['config_sistema']['acesso']['senha_godmode'];
    }
    return false;
}

/**
 * Verifica se usuário tem acesso aos filtros
 */
function temAcessoFiltros() {
    // Admin sempre tem acesso
    if (isGodMode()) {
        return true;
    }
    
    // Verifica senha de filtro
    if (isset($_GET['filtro']) && !empty($_SESSION['config_sistema']['acesso']['senha_filtro'])) {
        $senha_fornecida = base64_decode($_GET['filtro']);
        $senha_correta = $_SESSION['config_sistema']['acesso']['senha_filtro'];
        
        return $senha_fornecida === $senha_correta;
    }
    
    return false;
}

/**
 * Gera URL com acesso aos filtros
 */
function gerarUrlComFiltro($pagina = '') {
    if (empty($_SESSION['config_sistema']['acesso']['senha_filtro'])) {
        return '?page=' . $pagina;
    }
    
    $senha_encoded = base64_encode($_SESSION['config_sistema']['acesso']['senha_filtro']);
    return '?page=' . $pagina . '&filtro=' . $senha_encoded;
}

/**
 * Obtém período do relatório configurado
 */
function obterPeriodoRelatorio() {
    if (isset($_SESSION['config_sistema']['periodo_relatorio'])) {
        return $_SESSION['config_sistema']['periodo_relatorio'];
    }
    
    return [
        'data_inicial' => date('Y-m-01'),
        'data_final' => date('Y-m-t'),
        'filtro_status' => 'Ativo',
        'apenas_primeira_parcela' => false,
        'apenas_vista' => false
    ];
}

/**
 * Obtém configuração de premiação
 */
function obterConfigPremiacao() {
    if (isset($_SESSION['config_sistema']['premiacao'])) {
        return $_SESSION['config_sistema']['premiacao'];
    }
    
    return [
        'mensagem' => 'Para premiação Top 20, só serão contabilizadas as vendas com primeira parcela paga até dia 07 do mês posterior.',
        'dia_limite_primeira_parcela' => 7,
        'exibir_aviso' => true
    ];
}

/**
 * Exporta configuraçes para download
 */
function exportarConfiguracoes() {
    $config = carregarConfiguracoes();
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="config_aquabeat_' . date('Y-m-d_His') . '.json"');
    
    echo json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Importa configuraões de arquivo
 */
function importarConfiguracoes($arquivo_json) {
    if (!file_exists($arquivo_json)) {
        return [
            'sucesso' => false,
            'mensagem' => 'Arquivo não encontrado!'
        ];
    }
    
    $json = file_get_contents($arquivo_json);
    $config = json_decode($json, true);
    
    if (!$config) {
        return [
            'sucesso' => false,
            'mensagem' => 'Arquivo JSON inválido!'
        ];
    }
    
    return salvarConfiguracoes($config);
}

/**
 * Reseta configurações para padrão
 */
function resetarConfiguracoes() {
    $config = obterConfigPadrao();
    return salvarConfiguracoes($config);
}
?>
<?php
/**
 * Funções de queries para buscar dados do banco
 * Retornam no mesmo formato que as funções de processamento de CSV
 */

require_once __DIR__ . '/config.php';

/**
 * Busca vendas do banco de dados
 * Retorna no mesmo formato que processarVendasCSV()
 */
function buscarVendasDoBanco($mes_referencia = null, $filtros = []) {
    global $aqdb;

    // Monta a query base
    $where = ["1=1"];
    $params = [];

    // Filtro por mês de referência
    if ($mes_referencia) {
        $where[] = "mes_referencia = %s";
        $params[] = $mes_referencia;
    }

    // Filtro de data inicial
    if (!empty($filtros['data_inicial'])) {
        $where[] = "data_para_filtro >= %s";
        $params[] = $filtros['data_inicial'] . ' 00:00:00';
    }

    // Filtro de data final
    if (!empty($filtros['data_final'])) {
        $where[] = "data_para_filtro <= %s";
        $params[] = $filtros['data_final'] . ' 23:59:59';
    }

    // Filtro de status
    if (!empty($filtros['status'])) {
        $where[] = "status = %s";
        $params[] = $filtros['status'];
    }

    // Filtro de primeira parcela paga
    if (isset($filtros['primeira_parcela_paga']) && $filtros['primeira_parcela_paga']) {
        $where[] = "primeira_parcela_paga = 1";
    }

    // Filtro de apenas à vista
    if (isset($filtros['apenas_vista']) && $filtros['apenas_vista']) {
        $where[] = "e_vista = 1";
    }

    $where_sql = implode(' AND ', $where);

    // Monta query
    $query = "SELECT * FROM " . TABLE_VENDAS . " WHERE $where_sql ORDER BY data_cadastro DESC";

    // Prepara query se houver parâmetros
    if (!empty($params)) {
        $query = $aqdb->prepare($query, ...$params);
    }

    $vendas_db = $aqdb->get_results($query, ARRAY_A);

    // Converte para o formato esperado
    $vendas = [];
    $por_consultor = [];
    $log_ignorados = [];

    foreach ($vendas_db as $venda_db) {
        // Converte tipos booleanos
        $venda_db['e_vista'] = (bool) $venda_db['e_vista'];
        $venda_db['primeira_parcela_paga'] = (bool) $venda_db['primeira_parcela_paga'];
        $venda_db['produto_alterado'] = (bool) $venda_db['produto_alterado'];

        // Renomeia campo id para titulo_id (compatibilidade)
        $venda_db['id'] = $venda_db['titulo_id'];

        $vendas[] = $venda_db;

        // Agrupa por consultor
        $consultor_nome = $venda_db['consultor'];

        if (!isset($por_consultor[$consultor_nome])) {
            $por_consultor[$consultor_nome] = [
                'consultor' => $consultor_nome,
                'venda' => 0,
                'devido' => 0,
                'pago' => 0,
                'quantidade' => 0,
                'vendas_ativas' => 0,
                'contagem_vagas' => [],
                'vendas_detalhes' => [],
                'vendas_ids' => [],
                'vendas_acima_2vagas' => 0
            ];
        }

        $por_consultor[$consultor_nome]['venda'] += $venda_db['valor_total'];
        $por_consultor[$consultor_nome]['devido'] += $venda_db['valor_restante'];
        $por_consultor[$consultor_nome]['pago'] += $venda_db['valor_pago'];
        $por_consultor[$consultor_nome]['quantidade']++;
        $por_consultor[$consultor_nome]['vendas_ids'][] = $venda_db['titulo_id'];

        if ($venda_db['status'] === 'Ativo') {
            $por_consultor[$consultor_nome]['vendas_ativas']++;
        }

        if ($venda_db['num_vagas'] > 2) {
            $por_consultor[$consultor_nome]['vendas_acima_2vagas']++;
        }

        $por_consultor[$consultor_nome]['vendas_detalhes'][] = [
            'num_vagas' => $venda_db['num_vagas'],
            'e_vista' => $venda_db['e_vista'],
            'data_venda' => $venda_db['data_para_pontuacao'],
            'valor_total' => $venda_db['valor_total'],
            'valor_pago' => $venda_db['valor_pago']
        ];
    }

    return [
        'vendas' => $vendas,
        'por_consultor' => array_values($por_consultor),
        'log_ignorados' => $log_ignorados,
        'duplicados' => [
            'total' => 0,
            'detalhes' => []
        ]
    ];
}

/**
 * Busca promotores do banco de dados
 */
function buscarPromotoresDoBanco($filtro_status = 'Ativo') {
    global $aqdb;

    $where = "1=1";
    if ($filtro_status) {
        $where = $aqdb->prepare("status = %s", $filtro_status);
    }

    $promotores_db = $aqdb->get_results(
        "SELECT * FROM " . TABLE_PROMOTORES . " WHERE $where ORDER BY nome ASC",
        ARRAY_A
    );

    $promotores = [];
    foreach ($promotores_db as $promotor) {
        $promotores[] = $promotor;
    }

    return [
        'promotores' => $promotores,
        'total' => count($promotores)
    ];
}

/**
 * Verifica se o banco de dados está disponível e configurado
 */
function bancoDadosDisponivel() {
    try {
        global $aqdb;
        if (!isset($aqdb)) {
            return false;
        }

        // Testa se consegue conectar e se a tabela existe
        $teste = $aqdb->get_var("SHOW TABLES LIKE '" . TABLE_VENDAS . "'");
        return !empty($teste);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Busca vendas com opção de usar banco ou CSV
 * Prioriza banco de dados se disponível
 */
function buscarVendas($mes_referencia = null, $filtros = [], $forcar_csv = false) {
    // Se forçar CSV ou banco não disponível, usa CSV
    if ($forcar_csv || !bancoDadosDisponivel()) {
        // Busca arquivo CSV do mês
        $arquivos = listarCSVs('vendas');

        if (empty($arquivos)) {
            return [
                'vendas' => [],
                'por_consultor' => [],
                'log_ignorados' => [],
                'duplicados' => ['total' => 0, 'detalhes' => []]
            ];
        }

        // Se tem mês de referência, busca arquivo específico
        if ($mes_referencia) {
            foreach ($arquivos as $arquivo) {
                if ($arquivo['mes_referencia'] === $mes_referencia) {
                    require_once BASE_DIR . '/functions/vendas.php';
                    return processarVendasCSV($arquivo['caminho'], $filtros);
                }
            }
        }

        // Usa o primeiro arquivo
        require_once BASE_DIR . '/functions/vendas.php';
        return processarVendasCSV($arquivos[0]['caminho'], $filtros);
    }

    // Usa banco de dados
    return buscarVendasDoBanco($mes_referencia, $filtros);
}

/**
 * Busca promotores com opção de usar banco ou CSV
 */
function buscarPromotores($forcar_csv = false) {
    if ($forcar_csv || !bancoDadosDisponivel()) {
        $arquivos = listarCSVs('promotores');

        if (empty($arquivos)) {
            return ['promotores' => [], 'total' => 0];
        }

        require_once BASE_DIR . '/functions/promotores.php';
        return processarPromotoresCSV($arquivos[0]['caminho']);
    }

    return buscarPromotoresDoBanco();
}

/**
 * Conta total de vendas no banco por mês
 */
function contarVendasMes($mes_referencia) {
    global $aqdb;

    $query = $aqdb->prepare(
        "SELECT COUNT(*) FROM " . TABLE_VENDAS . " WHERE mes_referencia = %s",
        $mes_referencia
    );

    return (int) $aqdb->get_var($query);
}

/**
 * Lista meses disponíveis no banco
 */
function listarMesesDisponiveis() {
    global $aqdb;

    $meses = $aqdb->get_results(
        "SELECT DISTINCT mes_referencia, COUNT(*) as total
         FROM " . TABLE_VENDAS . "
         WHERE mes_referencia IS NOT NULL
         GROUP BY mes_referencia
         ORDER BY mes_referencia DESC",
        ARRAY_A
    );

    return $meses;
}

/**
 * Busca estatísticas de um mês
 */
function obterEstatisticasMes($mes_referencia) {
    global $aqdb;

    $query = $aqdb->prepare("
        SELECT
            COUNT(*) as total_vendas,
            COUNT(DISTINCT consultor) as total_consultores,
            SUM(valor_total) as valor_total,
            SUM(valor_pago) as valor_pago,
            SUM(num_vagas) as total_vagas,
            COUNT(CASE WHEN status = 'Ativo' THEN 1 END) as vendas_ativas,
            COUNT(CASE WHEN e_vista = 1 THEN 1 END) as vendas_vista,
            COUNT(CASE WHEN primeira_parcela_paga = 1 THEN 1 END) as com_primeira_parcela
        FROM " . TABLE_VENDAS . "
        WHERE mes_referencia = %s
    ", $mes_referencia);

    return $aqdb->get_row($query, ARRAY_A);
}

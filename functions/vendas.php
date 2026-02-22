<?php
// functions/vendas.php - Funções para processar vendas

/**
 * Extrai número de vagas do nome do produto
 */
function extrairNumeroVagas($produto) {
    if (preg_match('/(\d+)\s*[Vv]aga[s]?/i', $produto, $matches)) {
        return intval($matches[1]);
    }
    return 0;
}

/**
 * Processa CSV de vendas detalhadas
 * Estrutura do CSV (30 campos, índices 0-29):
 * 0: NumeroTitulo (ID - SFA-XXXX)
 * 1: NomeProdutoOriginal
 * 2: NomeProdutoAtual
 * 3: AlterouVagas
 * 4: Categoria
 * 5: StatusTitulo
 * 6: DataCadastro
 * 7: DataPrimeiraVenda
 * 8: DataUltimaVenda
 * 9: NomeTitular
 * 10: DocumentoTitular
 * 11: TelefoneResidencial
 * 12: OrigemVenda
 * 13: Promotor
 * 14: Gerente
 * 15: NumeroCartao
 * 16: Bandeira
 * 17: TipoPagamentoCartao
 * 18: QuantidadeParcelasVenda
 * 19: QtdParcelasPagas
 * 20: ValorParcela
 * 21: TotalPago
 * 22: SaldoRestante
 * 23: ParcelasRestantes
 * 24: FormaPagamento
 * 25: TipoPagamento
 * 26: PeriodoTitulo
 * 27: DiasDesdeVenda
 * 28: ListaParcelasPagas
 * 29: ListaValoresPagos
 */
 
 /**
 * Remove BOM (Byte Order Mark) UTF-8 do início de uma string
 */
function removerBOM($str) {
    // BOM UTF-8: EF BB BF
    if (substr($str, 0, 3) === "\xEF\xBB\xBF") {
        return substr($str, 3);
    }
    return $str;
}
 
 function detectarSeparadorCSV($arquivo) {
    $handle = fopen($arquivo, "r");
    if (!$handle) return ";"; // Padrão
    
    $total_tabs = 0;
    $total_pontovirgulas = 0;
    $linhas_analisadas = 0;
    $max_linhas = 5; // Analisa primeiras 5 linhas
    
    while (($linha = fgets($handle)) !== false && $linhas_analisadas < $max_linhas) {
        $total_tabs += substr_count($linha, "\t");
        $total_pontovirgulas += substr_count($linha, ";");
        $linhas_analisadas++;
    }
    
    fclose($handle);
    
     "<!-- DETECÇÃO: $total_tabs TABs, $total_pontovirgulas ; em $linhas_analisadas linhas -->";
    
    return ($total_tabs > $total_pontovirgulas) ? "\t" : ";";
}
 
 /**
 * Detecta se a primeira linha do CSV é um cabeçalho
 */
function csvTemCabecalho($arquivo, $separador) {
    $handle = fopen($arquivo, "r");
    if (!$handle) return true;
    
    $primeira_linha = fgetcsv($handle, 0, $separador);
    fclose($handle);
    
    if (empty($primeira_linha) || count($primeira_linha) < 1) return true;
    
    $primeira_coluna = trim($primeira_linha[0]);
    
    // ===== REGRA 1: Se começa com "SFA-" seguido de número, NÃO é cabealho =====
    if (preg_match('/^SFA-\d+$/', $primeira_coluna)) {
         "<!-- CABEÇALHO: Detectado ID de venda na primeira linha ('{$primeira_coluna}') - CSV SEM CABEÇALHO -->";
        return false;
    }
    
    // ===== REGRA 2: Se contém palavras de cabeçalho, É cabeçalho =====
    $palavras_cabecalho = ['numero', 'nome', 'produto', 'titulo', 'data', 'promotor', 'documento', 'status', 'cpf'];
    $primeira_coluna_lower = strtolower($primeira_coluna);
    
    foreach ($palavras_cabecalho as $palavra) {
        if (strpos($primeira_coluna_lower, $palavra) !== false) {
             "<!-- CABEÇALHO: Detectada palavra-chave '{$palavra}' - CSV COM CABEÇALHO -->";
            return true;
        }
    }
    
    // ===== REGRA 3: Se é apenas número, provavelmente é ID sem SFA =====
    if (is_numeric($primeira_coluna)) {
         "<!-- CABEÇALHO: Primeira coluna é número puro - CSV SEM CABEÇALHO -->";
        return false;
    }
    
    // ===== REGRA 4: Verifica se segunda coluna tambm parece dados =====
    if (isset($primeira_linha[1])) {
        $segunda_coluna = trim($primeira_linha[1]);
        // Se segunda coluna tem "Sócio", "Ttulo", etc, provavelmente são DADOS, não cabeçalho
        if (stripos($segunda_coluna, 'Scio') !== false || 
            stripos($segunda_coluna, 'Título') !== false ||
            stripos($segunda_coluna, 'Vagas') !== false) {
             "<!-- CABEÇALHO: Segunda coluna parece ser produto - CSV SEM CABEÇALHO -->";
            return false;
        }
    }
    
    // ===== PADRÃO: Assume que tem cabeçalho =====
     "<!-- CABEÇALHO: Não detectou padrão definitivo - Assumindo CSV COM CABEÇALHO -->";
    return true;
}
/**
 * Processa CSV de vendas - DETECÇÃO AUTOMÁTICA DE SEPARADOR
 */
function processarVendasCSV($arquivo, $filtros = []) {
    $vendas = [];
    $por_consultor = [];
    $log_ignorados = [];
    
    if (($handle = fopen($arquivo, "r")) !== false) {
        // ===== REMOVE BOM UTF-8 DO INÍCIO DO ARQUIVO =====
        $primeira_linha = fgets($handle);
        if (substr($primeira_linha, 0, 3) === "\xEF\xBB\xBF") {
            // Tem BOM, remove
            $primeira_linha = substr($primeira_linha, 3);
             "<!-- BOM UTF-8 DETECTADO E REMOVIDO -->";
        }
        
        // Volta pro início e processa normalmente
        rewind($handle);
        if (substr(fgets($handle), 0, 3) === "\xEF\xBB\xBF") {
            // Pula os 3 bytes do BOM
            fseek($handle, 3);
        } else {
            rewind($handle);
        }
        
        // Detecta separador
        $separador = detectarSeparadorCSV($arquivo);
        
        // Detecta cabeçalho
        $tem_cabecalho = csvTemCabecalho($arquivo, $separador);
        
        if ($tem_cabecalho) {
            $header = fgetcsv($handle, 0, $separador);
             "<!-- CSV COM CABEÇALHO: Primeira linha ignorada (colunas: " . count($header) . ") -->";
        } else {
             "<!-- CSV SEM CABEÇALHO: Processando desde a primeira linha -->";
        }
        
        $linha_num = $tem_cabecalho ? 1 : 0;
        $linhas_lidas = 0;
        
        while (($dados = fgetcsv($handle, 0, $separador)) !== false) {
            $linha_num++;
            $linhas_lidas++;
            
            // Debug: mostra primeiras 3 linhas
            if ($linhas_lidas <= 3) {
                 "<!-- LINHA $linha_num: " . count($dados) . " colunas | ID: " . ($dados[0] ?? 'N/A') . " -->";
            }
            
            // LOG: Linha vazia
            if (empty($dados) || count($dados) < 10) {
                $log_ignorados[] = [
                    'linha' => $linha_num,
                    'motivo' => 'Linha vazia ou dados insuficientes (' . count($dados) . ' colunas)',
                    'id' => 'N/A'
                ];
                continue;
            }
            
            // LOG: Colunas insuficientes (aceita 25 ou 30 campos para compatibilidade)
            if (count($dados) < 25) {
                $log_ignorados[] = [
                    'linha' => $linha_num,
                    'motivo' => 'Colunas insuficientes (' . count($dados) . '/mínimo 25)',
                    'id' => $dados[0] ?? 'N/A'
                ];
                continue;
            }

            // ===== REMOVE BOM DA PRIMEIRA COLUNA (SE EXISTIR) =====
            $dados[0] = removerBOM(trim($dados[0]));

            // Detecta formato do CSV (25 campos antigo ou 30 campos novo)
            $formato_novo = count($dados) >= 30;

            if ($formato_novo) {
                // Formato NOVO com 30 campos
                $venda = [
                    'id' => trim($dados[0]),
                    'produto_original' => trim($dados[1] ?? ''),
                    'produto_atual' => trim($dados[2] ?? ''),
                    'alterou_vagas' => trim($dados[3] ?? ''),
                    'categoria' => trim($dados[4] ?? ''),
                    'status' => trim($dados[5] ?? ''),
                    'data_cadastro' => trim($dados[6] ?? ''),
                    'data_primeira_venda' => trim($dados[7] ?? ''),
                    'data_venda' => trim($dados[8] ?? ''),  // DataUltimaVenda
                    'titular' => trim($dados[9] ?? ''),
                    'cpf' => trim($dados[10] ?? ''),
                    'telefone' => trim($dados[11] ?? ''),
                    'origem_venda' => trim($dados[12] ?? ''),
                    'consultor' => trim($dados[13] ?? ''),
                    'gerente' => trim($dados[14] ?? ''),
                    'numero_cartao' => trim($dados[15] ?? ''),
                    'bandeira' => trim($dados[16] ?? ''),
                    'tipo_pagamento_cartao' => trim($dados[17] ?? ''),
                    'quantidade_parcelas_venda' => intval($dados[18] ?? 0),
                    'parcelas_pagas' => intval($dados[19] ?? 0),
                    'valor_parcela' => floatval(str_replace(',', '.', $dados[20] ?? 0)),
                    'valor_pago' => floatval(str_replace(',', '.', $dados[21] ?? 0)),
                    'valor_restante' => floatval(str_replace(',', '.', $dados[22] ?? 0)),
                    'parcelas_restantes' => intval($dados[23] ?? 0),
                    'forma_pagamento' => trim($dados[24] ?? ''),
                    'tipo_pagamento' => trim($dados[25] ?? ''),
                    'periodo_titulo' => trim($dados[26] ?? ''),
                    'dias_desde_venda' => trim($dados[27] ?? ''),
                    'parcelas' => trim($dados[28] ?? ''),  // ListaParcelasPagas
                    'valores_pagos' => trim($dados[29] ?? ''),  // ListaValoresPagos
                    'valor_total' => floatval(str_replace(',', '.', $dados[21] ?? 0)) + floatval(str_replace(',', '.', $dados[22] ?? 0))  // TotalPago + SaldoRestante
                ];
            } else {
                // Formato ANTIGO com 25 campos (retrocompatibilidade)
                $venda = [
                    'id' => trim($dados[0]),
                    'produto_original' => trim($dados[1] ?? ''),
                    'produto_atual' => trim($dados[2] ?? ''),
                    'alterou_vagas' => trim($dados[3] ?? ''),
                    'categoria' => trim($dados[4] ?? ''),
                    'data_cadastro' => trim($dados[5] ?? ''),
                    'data_venda' => trim($dados[6] ?? ''),
                    'origem_venda' => trim($dados[7] ?? ''),
                    'telefone' => trim($dados[8] ?? ''),
                    'status' => trim($dados[9] ?? ''),
                    'consultor' => trim($dados[10] ?? ''),
                    'gerente' => trim($dados[11] ?? ''),
                    'titular' => trim($dados[12] ?? ''),
                    'cpf' => trim($dados[13] ?? ''),
                    'quantidade_parcelas_venda' => intval($dados[14] ?? 0),
                    'parcelas' => trim($dados[15] ?? ''),
                    'valores_pagos' => trim($dados[16] ?? ''),
                    'forma_pagamento' => trim($dados[17] ?? ''),
                    'parcelas_pagas' => intval($dados[18] ?? 0),
                    'tipo_pagamento' => trim($dados[19] ?? ''),
                    'valor_parcela' => floatval(str_replace(',', '.', $dados[20] ?? 0)),
                    'valor_total' => floatval(str_replace(',', '.', $dados[21] ?? 0)),
                    'valor_pago' => floatval(str_replace(',', '.', $dados[22] ?? 0)),
                    'valor_restante' => floatval(str_replace(',', '.', $dados[23] ?? 0)),
                    'parcelas_restantes' => intval($dados[24] ?? 0),
                    'numero_cartao' => '',  // Não disponível no formato antigo
                    'bandeira' => '',  // Não disponível no formato antigo
                    'tipo_pagamento_cartao' => ''  // Não disponível no formato antigo
                ];
            }
            
            // Debug da primeira venda
            if ($linhas_lidas == 1) {
                 "<!-- PRIMEIRA VENDA: ID='" . $venda['id'] . "' (length: " . strlen($venda['id']) . ") -->";
                 "<!-- Bytes do ID: " . bin2hex($venda['id']) . " -->";
            }
            
            // LOG: ID vazio ou inválido
            if (empty($venda['id'])) {
                $log_ignorados[] = [
                    'linha' => $linha_num,
                    'motivo' => 'ID vazio',
                    'id' => 'VAZIO'
                ];
                continue;
            }
            
            // Validação mais flexível do ID
            if (!preg_match('/^SFA-\d+$/', $venda['id'])) {
                $log_ignorados[] = [
                    'linha' => $linha_num,
                    'motivo' => 'ID com formato inválido (esperado: SFA-####)',
                    'id' => $venda['id'] . ' [' . bin2hex($venda['id']) . ']'
                ];
                continue;
            }
            
            // LOG: Consultor vazio
            if (empty($venda['consultor'])) {
                $log_ignorados[] = [
                    'linha' => $linha_num,
                    'motivo' => 'Consultor vazio',
                    'id' => $venda['id']
                ];
                continue;
            }
            
            // Processa dados adicionais
            $venda['num_vagas'] = extrairNumeroVagas($venda['produto_atual']);
            $venda['produto_alterado'] = ($venda['produto_original'] !== $venda['produto_atual']);

            // Se produto foi alterado, extrai vagas originais para comparação de pontos
            if ($venda['produto_alterado']) {
                $venda['num_vagas_original'] = extrairNumeroVagas($venda['produto_original']);
            } else {
                $venda['num_vagas_original'] = $venda['num_vagas'];
            }

            $venda['e_vista'] = (
                $venda['tipo_pagamento'] === 'À Vista' ||
                stripos($venda['tipo_pagamento'], 'vista') !== false ||
                $venda['quantidade_parcelas_venda'] <= 1
            );
            $venda['primeira_parcela_paga'] = ($venda['parcelas_pagas'] > 0);
            $venda['cpf_limpo'] = preg_replace('/[^0-9]/', '', $venda['cpf']);

            // CRÍTICO: SEMPRE usa DataCadastro para filtros e pontuação
            // Para vendas alteradas:
            //   - DataCadastro = data original da venda (NUNCA MUDA!)
            //   - DataVenda = data da alteração (muda quando altera produto)
            // Para vendas normais:
            //   - DataCadastro = DataVenda = data da venda

            // FORÇAR uso de DataCadastro (sem fallback)
            // Se DataCadastro estiver vazia, isso é um ERRO no CSV que deve ser corrigido
            if (empty($venda['data_cadastro'])) {
                error_log("AVISO: DataCadastro vazia para venda {$venda['id']}! Usando DataVenda como fallback EMERGENCIAL.");
                $venda['data_para_filtro'] = $venda['data_venda'];
                $venda['data_para_pontuacao'] = $venda['data_venda'];
            } else {
                // SEMPRE usa DataCadastro (data original que NUNCA muda)
                $venda['data_para_filtro'] = $venda['data_cadastro'];
                $venda['data_para_pontuacao'] = $venda['data_cadastro'];
            }

            // Aplica filtros
            $resultado_filtro = aplicarFiltros($venda, $filtros);
            
            if (!$resultado_filtro['passa']) {
                $log_ignorados[] = [
                    'linha' => $linha_num,
                    'motivo' => 'FILTRADO: ' . $resultado_filtro['motivo'],
                    'id' => $venda['id']
                ];
                continue;
            }
            
            $vendas[] = $venda;
            
            // Consolida por consultor (código existente...)
            $consultor_nome = $venda['consultor'];
            
            if (!isset($por_consultor[$consultor_nome])) {
                $por_consultor[$consultor_nome] = [
                    'consultor' => $consultor_nome,
                    'venda' => 0,
                    'devido' => 0,
                    'pago' => 0,
                    'quantidade' => 0,
                    'vendas_ativas' => 0,  // Contador de vendas com status "Ativo"
                    'contagem_vagas' => [],
                    'vendas_detalhes' => [],
                    'vendas_ids' => [],
                    'vendas_acima_2vagas' => 0
                ];
            }

            $por_consultor[$consultor_nome]['venda'] += $venda['valor_total'];
            $por_consultor[$consultor_nome]['devido'] += $venda['valor_restante'];
            $por_consultor[$consultor_nome]['pago'] += $venda['valor_pago'];
            $por_consultor[$consultor_nome]['quantidade']++;
            $por_consultor[$consultor_nome]['vendas_ids'][] = $venda['id'];

            // Conta vendas com status "Ativo"
            if ($venda['status'] === 'Ativo') {
                $por_consultor[$consultor_nome]['vendas_ativas']++;
            }

            if ($venda['num_vagas'] > 2) {
                $por_consultor[$consultor_nome]['vendas_acima_2vagas']++;
            }
            
            $por_consultor[$consultor_nome]['vendas_detalhes'][] = [
                'num_vagas' => $venda['num_vagas'],
                'e_vista' => $venda['e_vista'],
                'data_venda' => $venda['data_para_pontuacao'],  // Usa data original (DataCadastro) para ranges
                'data_cadastro_original' => $venda['data_cadastro'], // DEBUG: guardar para verificação
                'data_venda_original' => $venda['data_venda'], // DEBUG: guardar para verificação
                'id_venda' => $venda['id'], // DEBUG: para rastreamento
                'valor_total' => $venda['valor_total'],
                'valor_pago' => $venda['valor_pago']
            ];
        }
        
        fclose($handle);

         "<!-- PROCESSAMENTO: " . $linhas_lidas . " linhas lidas, " .
             count($vendas) . " vendas processadas, " .
             count($log_ignorados) . " ignoradas -->";
    }

    // === FILTRO DE CARTÕES DUPLICADOS ===
    $vendas_ignoradas_cartao = [];
    if (!empty($filtros['ignorar_cartao_duplicado'])) {
        $cartoes_duplicados = detectarCartoesDuplicados($vendas);

        if ($cartoes_duplicados['total'] > 0) {
            $vendas_filtradas = [];

            foreach ($vendas as $venda) {
                $numero_cartao = trim($venda['numero_cartao'] ?? '');
                $e_duplicado = false;

                // Verifica se o cartão está na lista de duplicados
                if (!empty($numero_cartao) && in_array($numero_cartao, $cartoes_duplicados['cartoes'])) {
                    $e_duplicado = true;
                    $vendas_ignoradas_cartao[] = [
                        'id' => $venda['id'],
                        'titular' => $venda['titular'],
                        'consultor' => $venda['consultor'],
                        'produto' => $venda['produto_atual'],
                        'valor_total' => $venda['valor_total'],
                        'numero_cartao' => $numero_cartao,
                        'bandeira' => $venda['bandeira'] ?? '',
                        'data_venda' => $venda['data_venda'],
                        'motivo' => 'Cartão duplicado'
                    ];

                    $log_ignorados[] = [
                        'linha' => '-',
                        'motivo' => 'FILTRADO PÓS-PROCESSAMENTO: Cartão duplicado (' . substr($numero_cartao, -4) . ')',
                        'id' => $venda['id']
                    ];
                } else {
                    $vendas_filtradas[] = $venda;
                }
            }

            $vendas = $vendas_filtradas;

            // Reprocessa por_consultor com vendas filtradas
            $por_consultor = [];
            foreach ($vendas as $venda) {
                $consultor_nome = $venda['consultor'];

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

                $por_consultor[$consultor_nome]['venda'] += $venda['valor_total'];
                $por_consultor[$consultor_nome]['devido'] += $venda['valor_restante'];
                $por_consultor[$consultor_nome]['pago'] += $venda['valor_pago'];
                $por_consultor[$consultor_nome]['quantidade']++;
                $por_consultor[$consultor_nome]['vendas_ids'][] = $venda['id'];

                if ($venda['status'] === 'Ativo') {
                    $por_consultor[$consultor_nome]['vendas_ativas']++;
                }

                if ($venda['num_vagas'] > 2) {
                    $por_consultor[$consultor_nome]['vendas_acima_2vagas']++;
                }

                $por_consultor[$consultor_nome]['vendas_detalhes'][] = [
                    'num_vagas' => $venda['num_vagas'],
                    'e_vista' => $venda['e_vista'],
                    'data_venda' => $venda['data_para_pontuacao'],
                    'data_cadastro_original' => $venda['data_cadastro'],
                    'data_venda_original' => $venda['data_venda'],
                    'id_venda' => $venda['id'],
                    'valor_total' => $venda['valor_total'],
                    'valor_pago' => $venda['valor_pago']
                ];
            }
        }
    }

    // === FILTRO DE VENDAS PIX ===
    $vendas_ignoradas_pix = [];
    if (!empty($filtros['ignorar_vendas_pix'])) {
        $vendas_filtradas = [];

        foreach ($vendas as $venda) {
            $forma_pagamento = strtoupper(trim($venda['forma_pagamento'] ?? ''));
            $tipo_pagamento = strtoupper(trim($venda['tipo_pagamento'] ?? ''));

            // Verifica se é PIX
            $e_pix = (stripos($forma_pagamento, 'PIX') !== false) ||
                     (stripos($tipo_pagamento, 'PIX') !== false);

            if ($e_pix) {
                $vendas_ignoradas_pix[] = [
                    'id' => $venda['id'],
                    'titular' => $venda['titular'],
                    'consultor' => $venda['consultor'],
                    'produto' => $venda['produto_atual'],
                    'valor_total' => $venda['valor_total'],
                    'forma_pagamento' => $venda['forma_pagamento'],
                    'data_venda' => $venda['data_venda'],
                    'motivo' => 'Pagamento via PIX'
                ];

                $log_ignorados[] = [
                    'linha' => '-',
                    'motivo' => 'FILTRADO PÓS-PROCESSAMENTO: Venda PIX',
                    'id' => $venda['id']
                ];
            } else {
                $vendas_filtradas[] = $venda;
            }
        }

        $vendas = $vendas_filtradas;

        // Reprocessa por_consultor com vendas filtradas
        $por_consultor = [];
        foreach ($vendas as $venda) {
            $consultor_nome = $venda['consultor'];

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

            $por_consultor[$consultor_nome]['venda'] += $venda['valor_total'];
            $por_consultor[$consultor_nome]['devido'] += $venda['valor_restante'];
            $por_consultor[$consultor_nome]['pago'] += $venda['valor_pago'];
            $por_consultor[$consultor_nome]['quantidade']++;
            $por_consultor[$consultor_nome]['vendas_ids'][] = $venda['id'];

            if ($venda['status'] === 'Ativo') {
                $por_consultor[$consultor_nome]['vendas_ativas']++;
            }

            if ($venda['num_vagas'] > 2) {
                $por_consultor[$consultor_nome]['vendas_acima_2vagas']++;
            }

            $por_consultor[$consultor_nome]['vendas_detalhes'][] = [
                'num_vagas' => $venda['num_vagas'],
                'e_vista' => $venda['e_vista'],
                'data_venda' => $venda['data_para_pontuacao'],
                'data_cadastro_original' => $venda['data_cadastro'],
                'data_venda_original' => $venda['data_venda'],
                'id_venda' => $venda['id'],
                'valor_total' => $venda['valor_total'],
                'valor_pago' => $venda['valor_pago']
            ];
        }
    }

    return [
        'vendas' => $vendas,
        'por_consultor' => array_values($por_consultor),
        'log_ignorados' => $log_ignorados,
        'vendas_ignoradas_cartao' => $vendas_ignoradas_cartao,
        'vendas_ignoradas_pix' => $vendas_ignoradas_pix
    ];
}
/**
 * Detecta vendas duplicadas (mesmo CPF no período)
 */
function detectarDuplicados($vendas) {
    $cpfs = [];
    $duplicados = [];
    
    foreach ($vendas as $index => $venda) {
        $cpf_limpo = $venda['cpf_limpo'];
        
        if (empty($cpf_limpo) || strlen($cpf_limpo) != 11) {
            continue;
        }
        
        if (!isset($cpfs[$cpf_limpo])) {
            $cpfs[$cpf_limpo] = [];
        }
        
        $cpfs[$cpf_limpo][] = [
            'index' => $index,
            'id' => $venda['id'],
            'titular' => $venda['titular'],
            'consultor' => $venda['consultor'],
            'data_venda' => $venda['data_venda'],
            'produto' => $venda['produto_atual'],
            'valor_total' => $venda['valor_total'],
            'status' => $venda['status']
        ];
    }
    
    // Identifica duplicados
    foreach ($cpfs as $cpf => $registros) {
        if (count($registros) > 1) {
            $duplicados[$cpf] = $registros;
        }
    }
    
    return [
        'total' => count($duplicados),
        'cpfs' => array_keys($duplicados),
        'detalhes' => $duplicados
    ];
}

/**
 * Detecta vendas com cart\u00f5es duplicados (mesmo n\u00famero de cart\u00e3o)
 */
function detectarCartoesDuplicados($vendas) {
    $cartoes = [];
    $duplicados = [];

    foreach ($vendas as $index => $venda) {
        $numero_cartao = trim($venda['numero_cartao'] ?? '');

        // Ignora se n\u00e3o tiver n\u00famero de cart\u00e3o
        if (empty($numero_cartao) || $numero_cartao === 'NULL' || strlen($numero_cartao) < 4) {
            continue;
        }

        if (!isset($cartoes[$numero_cartao])) {
            $cartoes[$numero_cartao] = [];
        }

        $cartoes[$numero_cartao][] = [
            'index' => $index,
            'id' => $venda['id'],
            'titular' => $venda['titular'],
            'consultor' => $venda['consultor'],
            'data_venda' => $venda['data_venda'],
            'produto' => $venda['produto_atual'],
            'valor_total' => $venda['valor_total'],
            'status' => $venda['status'],
            'bandeira' => $venda['bandeira'] ?? 'N/A'
        ];
    }

    // Identifica duplicados
    foreach ($cartoes as $cartao => $registros) {
        if (count($registros) > 1) {
            $duplicados[$cartao] = $registros;
        }
    }

    return [
        'total' => count($duplicados),
        'cartoes' => array_keys($duplicados),
        'detalhes' => $duplicados
    ];
}

/**
 * Marca vendas duplicadas
 */
function marcarDuplicados(&$vendas, $duplicados) {
    foreach ($duplicados['detalhes'] as $cpf => $registros) {
        foreach ($registros as $reg) {
            if (isset($vendas[$reg['index']])) {
                $vendas[$reg['index']]['duplicado'] = true;
                $vendas[$reg['index']]['qtd_duplicados'] = count($registros);
            }
        }
    }
}
/**
 * Aplica filtros em uma venda
 * Retorna array ['passa' => bool, 'motivo' => string]
 */
function aplicarFiltros($venda, $filtros) {
    // Filtro de status
    if (!empty($filtros['status']) && $venda['status'] !== $filtros['status']) {
        return ['passa' => false, 'motivo' => 'Status não corresponde (' . $venda['status'] . ' != ' . $filtros['status'] . ')'];
    }
    
    // Filtro apenas primeira parcela paga
    if (!empty($filtros['primeira_parcela_paga']) && !$venda['primeira_parcela_paga']) {
        return ['passa' => false, 'motivo' => 'Primeira parcela não paga'];
    }
    
    // Filtro apenas à vista
    if (!empty($filtros['apenas_vista']) && !$venda['e_vista']) {
        return ['passa' => false, 'motivo' => 'Não é à vista'];
    }
    
    // Filtro de data inicial
    if (!empty($filtros['data_inicial'])) {
        // Usa data_para_filtro se disponível (DataCadastro para títulos alterados, DataVenda para normais)
        $data_comparacao = $venda['data_para_filtro'] ?? $venda['data_venda'];
        $data_venda = strtotime($data_comparacao);
        $data_inicial_filter = strtotime($filtros['data_inicial'] . ' 00:00:00');

        if ($data_venda < $data_inicial_filter) {
            return [
                'passa' => false,
                'motivo' => 'Data anterior ao filtro (' .
                           date('d/m/Y', $data_venda) . ' < ' .
                           date('d/m/Y', $data_inicial_filter) . ')'
            ];
        }
    }

    // Filtro de data final
    if (!empty($filtros['data_final'])) {
        // Usa data_para_filtro se disponível (DataCadastro para títulos alterados, DataVenda para normais)
        $data_comparacao = $venda['data_para_filtro'] ?? $venda['data_venda'];
        $data_venda = strtotime($data_comparacao);
        $data_final_filter = strtotime($filtros['data_final'] . ' 23:59:59');

        if ($data_venda > $data_final_filter) {
            return [
                'passa' => false,
                'motivo' => 'Data posterior ao filtro (' .
                           date('d/m/Y', $data_venda) . ' > ' .
                           date('d/m/Y', $data_final_filter) . ')'
            ];
        }
    }
    
    return ['passa' => true, 'motivo' => ''];
}
/**
 * Calcula premiações (SAP e DIP) baseado nas configuraões
 */
function calcularPremiacoes($consultor) {
    $config = $_SESSION['config_premiacoes'] ?? [];

    // Fallback para valores padrão se não estiver na sessão
    $pontos_por_sap = $config['pontos_por_sap'] ?? 21;
    $vendas_para_dip = $config['vendas_para_dip'] ?? 0;
    $vendas_acima_2vagas_para_dip = $config['vendas_acima_2vagas_para_dip'] ?? 0;

    $pontos = $consultor['pontos'] ?? 0;
    $vendas_total = $consultor['quantidade'] ?? 0;
    $vendas_acima_2vagas = $consultor['vendas_acima_2vagas'] ?? 0;

    // Calcula SAPs (protege contra divisão por zero)
    $saps = $pontos_por_sap > 0 ? floor($pontos / $pontos_por_sap) : 0;

    // Calcula DIPs (se atingir qualquer um dos critérios)
    // IMPORTANTE: Se o valor configurado for 0, desativa esse critério
    $dip_por_total = ($vendas_para_dip > 0 && $vendas_total >= $vendas_para_dip) ? 1 : 0;
    $dip_por_acima_2vagas = ($vendas_acima_2vagas_para_dip > 0 && $vendas_acima_2vagas >= $vendas_acima_2vagas_para_dip) ? 1 : 0;

    $dips = max($dip_por_total, $dip_por_acima_2vagas);
    
    return [
        'saps' => $saps,
        'dips' => $dips,
        'criterio_dip' => $dip_por_total ? 'vendas_total' : ($dip_por_acima_2vagas ? 'vendas_acima_2vagas' : null)
    ];
}

/**
 * Adiciona premiações aos consultores processados
 */
function adicionarPremiacoes(&$consultores) {
    foreach ($consultores as &$consultor) {
        $premiacoes = calcularPremiacoes($consultor);
        $consultor['saps'] = $premiacoes['saps'];
        $consultor['dips'] = $premiacoes['dips'];
        $consultor['criterio_dip'] = $premiacoes['criterio_dip'];
    }
}

/**
 * Calcula pontos com range de pontuação personalizada
 */
function calcularPontosPersonalizado($vendas_detalhes, $config_pontos) {
    $pontos_total = 0;
    $detalhamento = [];
    
    // Agrupa vendas por categoria
    $vendas_por_categoria = [];
    
    foreach ($vendas_detalhes as $detalhe) {
        $num_vagas = $detalhe['num_vagas'];
        $e_vista = $detalhe['e_vista'];
        
        // Determina a categoria
        $categoria = determinarCategoria($num_vagas, $e_vista);
        
        if (!isset($vendas_por_categoria[$categoria])) {
            $vendas_por_categoria[$categoria] = 0;
        }
        $vendas_por_categoria[$categoria]++;
    }
    
    // Calcula pontos por categoria
    foreach ($vendas_por_categoria as $categoria => $quantidade) {
        $pontos_categoria = obterPontosPorCategoria($categoria, $config_pontos);
        $pontos_total += ($quantidade * $pontos_categoria);
        
        $detalhamento[] = [
            'categoria' => $categoria,
            'quantidade' => $quantidade,
            'pontos_unitario' => $pontos_categoria,
            'pontos_total' => ($quantidade * $pontos_categoria)
        ];
    }
    
    return [
        'pontos_total' => $pontos_total,
        'detalhamento' => $detalhamento
    ];
}

/**
 * Calcula pontos de uma venda considerando ranges configurados
 */
function calcularPontosVenda($venda, $ranges = []) {
    $num_vagas = $venda['num_vagas'];
    $e_vista = $venda['e_vista'];
    $data_venda = $venda['data_venda'];
    
    // Verifica se a venda está dentro de algum range ativo
    $range_ativo = null;
    
    foreach ($ranges as $range) {
        if (empty($range['ativo'])) continue;
        
        $data_venda_timestamp = strtotime($data_venda);
        $data_inicio = strtotime($range['data_inicio'] . ' 00:00:00');
        $data_fim = strtotime($range['data_fim'] . ' 23:59:59');
        
        if ($data_venda_timestamp >= $data_inicio && $data_venda_timestamp <= $data_fim) {
            $range_ativo = $range;
            break;
        }
    }
    
    // Se tem range ativo, usa a pontuação do range
    if ($range_ativo && isset($range_ativo['pontos'])) {
        $pontos_config = $range_ativo['pontos'];
        
        // Regra: 5+ vagas à vista
        if ($num_vagas >= 5 && $e_vista) {
            return $pontos_config['acima_5_vista'] ?? 5;
        }
        
        // Regra: 1 vaga à vista
        if ($num_vagas == 1 && $e_vista) {
            return $pontos_config['1vaga_vista'] ?? 1;
        }
        
        // Regras por faixa de vagas (parcelado ou à vista)
        if ($num_vagas >= 11) {
            return $pontos_config['acima_10'] ?? 4;
        }
        if ($num_vagas >= 8) {
            return $pontos_config['8a10_vagas'] ?? 4;
        }
        if ($num_vagas >= 4) {
            return $pontos_config['4a7_vagas'] ?? 3;
        }
        if ($num_vagas >= 2) {
            return $pontos_config['2a3_vagas'] ?? 2;
        }
        
        // 1 vaga parcelado
        return 0;
    }
    
    // Sem range ativo, usa pontuação padrão do sistema
    $pontos_por_vaga = $_SESSION['config_premiacoes']['pontos_por_vaga'] ?? 3;
    $pontos_vista = $_SESSION['config_premiacoes']['pontos_venda_vista'] ?? 2;
    
    $pontos = $num_vagas * $pontos_por_vaga;
    
    if ($e_vista) {
        $pontos += $pontos_vista;
    }
    
    return $pontos;
}

/**
 * Determina categoria de uma venda
 */
function determinarCategoria($num_vagas, $e_vista) {
    // Vendas à vista de 5+ vagas têm categoria especial
    if ($num_vagas >= 5 && $e_vista) {
        return 'acima_5_vista';
    }
    
    // 1 vaga parcelado no pontua
    if ($num_vagas == 1 && !$e_vista) {
        return '1vaga_parcelado';
    }
    
    // 1 vaga  vista
    if ($num_vagas == 1 && $e_vista) {
        return '1vaga_vista';
    }
    
    // 2-3 vagas
    if ($num_vagas >= 2 && $num_vagas <= 3) {
        return '2a3_vagas';
    }
    
    // 4-7 vagas
    if ($num_vagas >= 4 && $num_vagas <= 7) {
        return '4a7_vagas';
    }
    
    // 8-10 vagas
    if ($num_vagas >= 8 && $num_vagas <= 10) {
        return '8a10_vagas';
    }
    
    // Acima de 10 vagas
    if ($num_vagas > 10) {
        return 'acima_10';
    }
    
    return 'outros';
}


/**
 * Obtm pontos por categoria (CORRIGIDO)
 */
function obterPontosPorCategoria($categoria, $config_pontos) {
    $mapa = [
        '1vaga_parcelado' => 0, // NÃO PONTUA
        '1vaga_vista' => $config_pontos['1vaga_vista'] ?? 1,
        '2a3_vagas' => $config_pontos['2a3_vagas'] ?? 2,
        '4a7_vagas' => $config_pontos['4a7_vagas'] ?? 3,
        '8a10_vagas' => $config_pontos['8a10_vagas'] ?? 4,
        'acima_10' => $config_pontos['acima_10'] ?? 4,
        'acima_5_vista' => $config_pontos['acima_5_vista'] ?? 5,
        'outros' => 0
    ];
    
    return $mapa[$categoria] ?? 0;
}

/**
 * Extrai período do CSV de vendas
 */
function extrairPeriodoVendas($vendas) {
    if (empty($vendas)) {
        return [
            'inicio' => date('d/m/Y'),
            'fim' => date('d/m/Y')
        ];
    }
    
    $datas = [];
    foreach ($vendas as $venda) {
        $data = DateTime::createFromFormat('Y-m-d H:i:s.u', $venda['data_venda']);
        if (!$data) {
            $data = DateTime::createFromFormat('Y-m-d H:i:s', $venda['data_venda']);
        }
        if ($data) {
            $datas[] = $data;
        }
    }
    
    if (empty($datas)) {
        return [
            'inicio' => date('d/m/Y'),
            'fim' => date('d/m/Y')
        ];
    }
    
    sort($datas);
    
    return [
        'inicio' => $datas[0]->format('d/m/Y'),
        'fim' => end($datas)->format('d/m/Y')
    ];
}

/**
 * Mascara nome do titular (primeiro e último nome visíveis)
 */
function mascararNome($nome_completo) {
    $partes = explode(' ', trim($nome_completo));
    
    if (count($partes) <= 2) {
        return $nome_completo;
    }
    
    $primeiro = $partes[0];
    $ultimo = $partes[count($partes) - 1];
    $meio_count = count($partes) - 2;
    
    $meio = str_repeat('*** ', $meio_count);
    
    return $primeiro . ' ' . trim($meio) . ' ' . $ultimo;
}

/**
 * Mascara CPF parcialmente
 */
function mascararCPF($cpf) {
    $cpf = preg_replace('/\D/', '', $cpf);
    
    if (strlen($cpf) != 11) {
        return $cpf;
    }
    
    return '***.' . substr($cpf, 3, 3) . '.***-' . substr($cpf, 9, 2);
}

/**
 * Mascara telefone parcialmente
 */
function mascararTelefone($telefone) {
    $telefone = preg_replace('/\D/', '', $telefone);
    
    if (empty($telefone)) {
        return 'No informado';
    }
    
    if (strlen($telefone) == 11) {
        return '(' . substr($telefone, 0, 2) . ') ****-' . substr($telefone, 7, 4);
    } elseif (strlen($telefone) == 10) {
        return '(' . substr($telefone, 0, 2) . ') ****-' . substr($telefone, 6, 4);
    }
    
    return '****-' . substr($telefone, -4);
}

/**
 * Valida identificaão do consultor usando CPF ou Telefone
 */
function validarIdentificacaoConsultor($consultor_nome, $identificacao, $vendas) {
    // Remove caracteres no numéricos
    $identificacao = preg_replace('/[^0-9]/', '', $identificacao);
    
    if (empty($vendas)) {
        return false;
    }
    
    // Para cada venda do consultor, verifica CPF e telefone
    foreach ($vendas as $venda) {
        $cpf = preg_replace('/[^0-9]/', '', $venda['cpf']);
        $telefone = preg_replace('/[^0-9]/', '', $venda['telefone']);
        
        // Se identificao tem 11 dígitos, pode ser telefone completo ou CPF completo
        if (strlen($identificacao) == 11) {
            if ($identificacao === $cpf || $identificacao === $telefone) {
                return true;
            }
        }
        
        // Se identificao tem 4 dígitos
        if (strlen($identificacao) == 4) {
            // Verifica 4 primeiros dgitos do CPF
            $primeiros_4_cpf = substr($cpf, 0, 4);
            if ($identificacao === $primeiros_4_cpf) {
                return true;
            }
            
            // Verifica 4 últimos dgitos do CPF
            $ultimos_4_cpf = substr($cpf, -4);
            if ($identificacao === $ultimos_4_cpf) {
                return true;
            }
            
            // Verifica 4 primeiros dígitos do telefone
            if (strlen($telefone) >= 4) {
                $primeiros_4_tel = substr($telefone, 0, 4);
                if ($identificacao === $primeiros_4_tel) {
                    return true;
                }
            }
            
            // Verifica 4 últimos dígitos do telefone
            if (strlen($telefone) >= 4) {
                $ultimos_4_tel = substr($telefone, -4);
                if ($identificacao === $ultimos_4_tel) {
                    return true;
                }
            }
        }
        
        // Se identificaão tem entre 5 e 10 dígitos, pode ser parte do CPF ou telefone
        if (strlen($identificacao) >= 5 && strlen($identificacao) <= 10) {
            // Verifica se est contido no CPF
            if (strpos($cpf, $identificacao) !== false) {
                return true;
            }
            
            // Verifica se est contido no telefone
            if (strpos($telefone, $identificacao) !== false) {
                return true;
            }
        }
    }
    
    return false;
}
// Adicionar no arquivo functions/vendas.php

/**
 * Obtém pontos padrão do config.json (não hardcoded)
 */
function obterPontosPadrao() {
    $config = $_SESSION['config_sistema'] ?? carregarConfiguracoes();

    // Tenta obter do config.json primeiro
    if (isset($config['pontos_padrao']) && !empty($config['pontos_padrao'])) {
        return $config['pontos_padrao'];
    }

    // Fallback para constante se não houver no config.json
    return PONTOS_PADRAO;
}

/**
 * Determina qual configuração de pontos usar baseado na data da venda
 */
function obterConfiguracaoPontosPorData($data_venda) {
    // IMPORTANTE: Carrega ranges do config.json se não estiver na sessão
    if (!isset($_SESSION['ranges_pontuacao']) || empty($_SESSION['ranges_pontuacao'])) {
        $config = $_SESSION['config_sistema'] ?? carregarConfiguracoes();
        if (isset($config['ranges']) && !empty($config['ranges'])) {
            $_SESSION['ranges_pontuacao'] = $config['ranges'];
        }
    }

    // Converte data da venda para DateTime
    $data = DateTime::createFromFormat('Y-m-d H:i:s.u', $data_venda);
    if (!$data) {
        $data = DateTime::createFromFormat('Y-m-d H:i:s', $data_venda);
    }
    if (!$data) {
        $data = DateTime::createFromFormat('d/m/Y', $data_venda);
    }

    if (!$data) {
        return obterPontosPadrao(); // Retorna padrão se não conseguir parsear
    }

    // Verifica se há ranges configurados
    if (!isset($_SESSION['ranges_pontuacao']) || empty($_SESSION['ranges_pontuacao'])) {
        return obterPontosPadrao();
    }

    // Procura range que contenha esta data E que esteja ativo
    foreach ($_SESSION['ranges_pontuacao'] as $range) {
        // IMPORTANTE: Verifica se o range está ativo
        if (!isset($range['ativo']) || $range['ativo'] !== true) {
            continue; // Pula ranges inativos
        }

        $data_inicio = DateTime::createFromFormat('Y-m-d', $range['data_inicio']);
        $data_inicio->setTime(0, 0, 0); // Início do dia
        $data_fim = DateTime::createFromFormat('Y-m-d', $range['data_fim']);
        $data_fim->setTime(23, 59, 59); // Fim do dia

        if ($data >= $data_inicio && $data <= $data_fim) {
            // DEBUG: Log do range encontrado
            error_log("DEBUG obterConfiguracaoPontosPorData: Data " . $data_venda . " está no range: " . ($range['nome'] ?? 'sem nome') . " (" . $range['data_inicio'] . " a " . $range['data_fim'] . ")");

            // Verifica se os pontos estão em um objeto "pontos" ou diretamente no range
            if (isset($range['pontos']) && is_array($range['pontos'])) {
                return $range['pontos'];
            } else {
                // Pontos estão diretamente no range - remove metadados
                $pontos = $range;
                unset($pontos['nome'], $pontos['data_inicio'], $pontos['data_fim'], $pontos['ativo'], $pontos['id']);
                return $pontos;
            }
        }
    }

    // Se não encontrou range especfico, usa padrão
    return obterPontosPadrao();
}

/**
 * Converte pontos do formato config.json (1vaga, 2vagas, etc) para formato esperado
 */
function converterFormatoRange($pontos_range) {
    // Se já está no formato correto (com chaves como '2a3_vagas'), retorna direto
    if (isset($pontos_range['2a3_vagas']) || isset($pontos_range['1vaga_vista'])) {
        return $pontos_range;
    }

    // Converte do formato config.json (1vaga, 2vagas, etc) para formato esperado
    return [
        '1vaga_vista' => $pontos_range['1vaga'] ?? $pontos_range['1vagas'] ?? 1,
        '2a3_vagas' => max($pontos_range['2vagas'] ?? 2, $pontos_range['3vagas'] ?? 2),
        '4a7_vagas' => max(
            $pontos_range['4vagas'] ?? 3,
            $pontos_range['5vagas'] ?? 3,
            $pontos_range['6vagas'] ?? 3,
            $pontos_range['7vagas'] ?? 3
        ),
        '8a10_vagas' => max(
            $pontos_range['8vagas'] ?? 4,
            $pontos_range['9vagas'] ?? 4,
            $pontos_range['10vagas'] ?? 4
        ),
        'acima_10' => $pontos_range['acima_10'] ?? 4,
        'acima_5_vista' => $pontos_range['vista_acima_5'] ?? $pontos_range['acima_5_vista'] ?? 5
    ];
}

/**
 * Converte configurao de pontos padro para formato compatvel
 */
function converterPontosPadrao($pontos_padrao) {
    return [
        '1vaga_vista' => $pontos_padrao[1] ?? 1,
        '2a3_vagas' => $pontos_padrao[2] ?? 2,
        '4a7_vagas' => $pontos_padrao[4] ?? 3,
        '8a10_vagas' => $pontos_padrao[8] ?? 4,
        'acima_10' => $pontos_padrao['acima_10'] ?? 4,
        'acima_5_vista' => $pontos_padrao['acima_5_vista'] ?? 5
    ];
}

/**
 * Calcula pontos com ranges de pontuação por data (CORRIGIDO)
 */
function calcularPontosComRanges($vendas_detalhes, $nome_consultor = '') {
    $pontos_total = 0;
    $detalhamento_por_range = [];
    $pontos_por_venda = []; // NOVO: Rastreia pontos por ID de venda

    // DEBUG: Armazena info para exibir na tela com godmode
    $debug_info = [];
    $debug_todas_vendas = []; // Para mostrar TODAS as vendas de um consultor

    foreach ($vendas_detalhes as $detalhe) {
        // DEBUG: Captura TODAS as vendas se for consultor específico
        if (isGodMode() && !empty($nome_consultor) && stripos($nome_consultor, 'LUCIANA') !== false) {
            // Adiciona info básica de CADA venda
            $debug_todas_vendas[] = [
                'id' => $detalhe['id_venda'] ?? 'N/A',
                'vagas' => $detalhe['num_vagas'] ?? 0,
                'data_usada' => substr($detalhe['data_venda'] ?? '', 0, 10),
            ];
        }

        // DEBUG: Captura info detalhada para venda específica SFA-9340
        if (isset($detalhe['id_venda']) && $detalhe['id_venda'] === 'SFA-9340') {
            $debug_info['SFA-9340'] = [
                'data_venda_usada' => $detalhe['data_venda'] ?? 'NULL',
                'data_cadastro_original' => $detalhe['data_cadastro_original'] ?? 'NULL',
                'data_venda_original' => $detalhe['data_venda_original'] ?? 'NULL',
                'num_vagas' => $detalhe['num_vagas'] ?? 'NULL'
            ];
        }

        // Obtém configuração de pontos baseada na data da venda (que deveria ser DataCadastro!)
        $config_pontos_raw = obterConfiguracaoPontosPorData($detalhe['data_venda']);
        
        // Converte para formato compatível se necessário
        // Detecta se é formato antigo com índices numéricos (1, 2, 3...) ou novo com strings
        $tem_indices_numericos = isset($config_pontos_raw[1]) && is_numeric(key($config_pontos_raw));

        if ($tem_indices_numericos) {
            // É formato PONTOS_PADRAO antigo (índices numéricos: 1=>1, 2=>2, etc)
            $config_pontos = converterPontosPadrao($config_pontos_raw);
        } else {
            // É formato config.json/range (strings: "1vaga", "2vagas", etc)
            $config_pontos = converterFormatoRange($config_pontos_raw);
        }
        
        // Determina categoria
        $categoria = determinarCategoria($detalhe['num_vagas'], $detalhe['e_vista']);
        
        // Obtém pontos para esta categoria
        $pontos = obterPontosPorCategoria($categoria, $config_pontos);

        // IMPORTANTE: Adiciona os pontos apenas UMA VEZ
        $pontos_total += $pontos;

        // NOVO: Armazena pontos por ID de venda
        if (isset($detalhe['id_venda'])) {
            $pontos_por_venda[$detalhe['id_venda']] = $pontos;
        }

        // Identifica qual range foi usado
        $range_usado = identificarRange($detalhe['data_venda']);

        // DEBUG: Adiciona pontos de TODAS as vendas da LUCIANA
        if (isGodMode() && !empty($nome_consultor) && stripos($nome_consultor, 'LUCIANA') !== false) {
            $ultimo_indice = count($debug_todas_vendas) - 1;
            if ($ultimo_indice >= 0) {
                $debug_todas_vendas[$ultimo_indice]['range'] = $range_usado;
                $debug_todas_vendas[$ultimo_indice]['pontos'] = $pontos;
            }
        }

        // DEBUG: Adiciona info do range usado para SFA-9340
        if (isset($detalhe['id_venda']) && $detalhe['id_venda'] === 'SFA-9340') {
            $debug_info['SFA-9340']['range_usado'] = $range_usado;
            $debug_info['SFA-9340']['pontos_calculados'] = $pontos;
            $debug_info['SFA-9340']['categoria'] = $categoria;
        }

        // Agrupa por range
        if (!isset($detalhamento_por_range[$range_usado])) {
            $detalhamento_por_range[$range_usado] = [
                'nome' => $range_usado,
                'categorias' => [],
                'total_pontos' => 0
            ];
        }
        
        // Agrupa por categoria dentro do range
        if (!isset($detalhamento_por_range[$range_usado]['categorias'][$categoria])) {
            $detalhamento_por_range[$range_usado]['categorias'][$categoria] = [
                'categoria' => formatarNomeCategoria($categoria),
                'quantidade' => 0,
                'pontos_unitario' => $pontos,
                'pontos_total' => 0
            ];
        }
        
        // Incrementa quantidade e pontos
        $detalhamento_por_range[$range_usado]['categorias'][$categoria]['quantidade']++;
        $detalhamento_por_range[$range_usado]['categorias'][$categoria]['pontos_total'] += $pontos;
        $detalhamento_por_range[$range_usado]['total_pontos'] += $pontos;
    }

    // DEBUG: Exibe na tela se godmode ativo
    if (isGodMode()) {
        // DEBUG da venda SFA-9340
        if (!empty($debug_info)) {
            echo '<div class="alert alert-warning mt-3"><strong>🔍 DEBUG PONTUAÇÃO - SFA-9340 (GodMode):</strong><br>';
            foreach ($debug_info as $id => $info) {
                echo "<strong>Venda {$id}:</strong><br>";
                echo "• Data usada para pontuação: <strong>{$info['data_venda_usada']}</strong><br>";
                echo "• DataCadastro (original): {$info['data_cadastro_original']}<br>";
                echo "• DataVenda (alteração): {$info['data_venda_original']}<br>";
                echo "• Número de vagas: {$info['num_vagas']}<br>";
                echo "• Range aplicado: <strong class='text-danger'>{$info['range_usado']}</strong><br>";
                echo "• Categoria: {$info['categoria']}<br>";
                echo "• Pontos calculados: <strong>{$info['pontos_calculados']}</strong><br>";
            }
            echo '</div>';
        }

        // DEBUG de TODAS as vendas da LUCIANA
        if (!empty($debug_todas_vendas)) {
            $total_pts = array_sum(array_column($debug_todas_vendas, 'pontos'));
            echo '<div class="alert alert-info mt-3">';
            echo '<strong>🔍 DEBUG TODAS AS VENDAS - ' . htmlspecialchars($nome_consultor) . ':</strong><br>';
            echo '<strong>TOTAL CALCULADO: ' . $total_pts . ' pontos</strong><br><br>';
            echo '<table class="table table-sm table-bordered mt-2">';
            echo '<thead><tr><th>ID</th><th>Vagas</th><th>Data Usada</th><th>Range</th><th>Pontos</th></tr></thead><tbody>';
            foreach ($debug_todas_vendas as $v) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($v['id']) . '</td>';
                echo '<td>' . $v['vagas'] . '</td>';
                echo '<td>' . $v['data_usada'] . '</td>';
                echo '<td>' . htmlspecialchars($v['range']) . '</td>';
                echo '<td><strong>' . $v['pontos'] . '</strong></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</div>';
        }
    }

    // Monta detalhamento final
    $detalhamento = [];
    foreach ($detalhamento_por_range as $range_nome => $range_data) {
        foreach ($range_data['categorias'] as $cat_data) {
            $detalhamento[] = array_merge($cat_data, ['range' => $range_nome]);
        }
    }

    return [
        'pontos_total' => $pontos_total,
        'detalhamento' => $detalhamento,
        'detalhamento_por_range' => array_values($detalhamento_por_range),
        'pontos_por_venda' => $pontos_por_venda // NOVO: Array de pontos por ID
    ];
}

/**
 * Identifica qual range foi usado para uma data específica
 */
function identificarRange($data_venda) {
    // IMPORTANTE: Carrega ranges do config.json se não estiver na sessão
    if (!isset($_SESSION['ranges_pontuacao']) || empty($_SESSION['ranges_pontuacao'])) {
        $config = $_SESSION['config_sistema'] ?? carregarConfiguracoes();
        if (isset($config['ranges']) && !empty($config['ranges'])) {
            $_SESSION['ranges_pontuacao'] = $config['ranges'];
        }
    }

    $data = DateTime::createFromFormat('Y-m-d H:i:s.u', $data_venda);
    if (!$data) {
        $data = DateTime::createFromFormat('Y-m-d H:i:s', $data_venda);
    }

    if (!$data) {
        return 'Padrão';
    }

    if (!isset($_SESSION['ranges_pontuacao']) || empty($_SESSION['ranges_pontuacao'])) {
        return 'Padrão';
    }

    foreach ($_SESSION['ranges_pontuacao'] as $range) {
        // IMPORTANTE: Verifica se o range está ativo
        if (!isset($range['ativo']) || $range['ativo'] !== true) {
            continue; // Pula ranges inativos
        }

        $data_inicio = DateTime::createFromFormat('Y-m-d', $range['data_inicio']);
        $data_inicio->setTime(0, 0, 0); // Início do dia
        $data_fim = DateTime::createFromFormat('Y-m-d', $range['data_fim']);
        $data_fim->setTime(23, 59, 59); // Fim do dia

        if ($data >= $data_inicio && $data <= $data_fim) {
            return $range['nome'];
        }
    }

    return 'Padrão';
}

/**
 * Processa múltiplos arquivos CSV e consolida os dados (GODMODE)
 *
 * @param array $arquivos Lista de caminhos dos arquivos CSV
 * @param array $filtros Filtros a aplicar
 * @return array Resultado consolidado com todas as vendas unificadas
 */
function processarMultiplosArquivosCSV($arquivos, $filtros = []) {
    $vendas_consolidadas = [];
    $nomes_arquivos = [];

    // Processa cada arquivo
    foreach ($arquivos as $arquivo) {
        if (!file_exists($arquivo)) {
            continue;
        }

        // Processa o arquivo individual
        $resultado = processarVendasComRanges($arquivo, $filtros);

        // Adiciona nome do arquivo à lista
        $nomes_arquivos[] = basename($arquivo);

        // Unifica as vendas
        foreach ($resultado['vendas'] as $venda) {
            $venda['arquivo_origem'] = basename($arquivo); // Marca de qual arquivo veio
            $vendas_consolidadas[] = $venda;
        }
    }

    // Agora recalcula tudo com as vendas consolidadas
    // Agrupa por consultor
    $por_consultor = [];

    foreach ($vendas_consolidadas as $venda) {
        $nome = $venda['consultor'];

        if (!isset($por_consultor[$nome])) {
            $por_consultor[$nome] = [
                'consultor' => $nome,
                'vendas_detalhes' => [],
                'quantidade' => 0,
                'venda' => 0,
                'pago' => 0,
                'vendas_vista' => 0,
                'vendas_acima_2vagas' => 0,
                'vendas_ativas' => 0
            ];
        }

        $por_consultor[$nome]['vendas_detalhes'][] = [
            'num_vagas' => $venda['num_vagas'],
            'e_vista' => $venda['e_vista'],
            'data_venda' => $venda['data_para_pontuacao'],
            'id_venda' => $venda['id'],
            'valor_total' => $venda['valor_total'],
            'valor_pago' => $venda['valor_pago']
        ];

        $por_consultor[$nome]['quantidade']++;
        $por_consultor[$nome]['venda'] += $venda['valor_total'];
        $por_consultor[$nome]['pago'] += $venda['valor_pago'];

        if ($venda['e_vista']) {
            $por_consultor[$nome]['vendas_vista']++;
        }

        if ($venda['num_vagas'] > 2) {
            $por_consultor[$nome]['vendas_acima_2vagas']++;
        }

        // Conta vendas ativas
        if (strcasecmp($venda['status'], 'Ativo') === 0) {
            $por_consultor[$nome]['vendas_ativas']++;
        }
    }

    // Calcula pontos para cada consultor
    foreach ($por_consultor as &$consultor) {
        $calculo_pontos = calcularPontosComRanges($consultor['vendas_detalhes'], $consultor['consultor']);
        $consultor['pontos'] = $calculo_pontos['pontos_total'];

        // Calcula SAPs
        $pontos_por_sap = $_SESSION['config_premiacoes']['pontos_por_sap'] ?? 21;
        $consultor['saps'] = floor($consultor['pontos'] / $pontos_por_sap);

        // Calcula DIPs
        $vendas_para_dip = $_SESSION['config_premiacoes']['vendas_para_dip'] ?? 200;
        $vendas_acima_2vagas_para_dip = $_SESSION['config_premiacoes']['vendas_acima_2vagas_para_dip'] ?? 200;
        $consultor['dips'] = 0;

        if ($consultor['quantidade'] >= $vendas_para_dip) {
            $consultor['dips'] = 1;
            $consultor['criterio_dip'] = 'vendas_total';
        } elseif ($consultor['vendas_acima_2vagas'] >= $vendas_acima_2vagas_para_dip) {
            $consultor['dips'] = 1;
            $consultor['criterio_dip'] = 'vendas_acima_2vagas';
        }
    }
    unset($consultor);

    return [
        'vendas' => $vendas_consolidadas,
        'por_consultor' => array_values($por_consultor),
        'multifile' => true,
        'arquivos' => $nomes_arquivos,
        'total_arquivos' => count($arquivos)
    ];
}

/**
 * Processa vendas e calcula pontos com ranges (NOVA FUNÃO)
 */
/**
 * Processa vendas com ranges e detecta duplicados
 */
function processarVendasComRanges($arquivo, $filtros = []) {
    // Processa vendas normalmente
    $resultado = processarVendasCSV($arquivo, $filtros);

    // ===== PROCESSA COTAS DESCONSIDERADAS =====
    $cotas_desconsideradas_dados = carregarCotasDesconsideradas();
    $vendas_desconsideradas = [];
    $vendas_desconsideradas_por_consultor = [];

    // Extrai apenas os IDs das cotas desconsideradas
    $cotas_desconsideradas_ids = [];
    foreach ($cotas_desconsideradas_dados as $item) {
        $cotas_desconsideradas_ids[] = $item['cota'];
    }

    if (!empty($cotas_desconsideradas_ids)) {
        // Separa vendas desconsideradas
        foreach ($resultado['vendas'] as $key => $venda) {
            if (in_array($venda['id'], $cotas_desconsideradas_ids)) {
                $vendas_desconsideradas[] = $venda;

                // Agrupa por consultor
                $nome_consultor = $venda['consultor'];
                if (!isset($vendas_desconsideradas_por_consultor[$nome_consultor])) {
                    $vendas_desconsideradas_por_consultor[$nome_consultor] = [
                        'quantidade' => 0,
                        'cotas' => [],
                        'pontos' => 0
                    ];
                }

                $vendas_desconsideradas_por_consultor[$nome_consultor]['quantidade']++;
                $vendas_desconsideradas_por_consultor[$nome_consultor]['cotas'][] = $venda['id'];

                // Remove da lista de vendas processadas
                unset($resultado['vendas'][$key]);
            }
        }

        // Reindexar array de vendas
        $resultado['vendas'] = array_values($resultado['vendas']);

        // Recalcula por_consultor sem as cotas desconsideradas
        if (!empty($vendas_desconsideradas)) {
            // Precisa recalcular todos os consultores
            $resultado = processarVendasCSV($arquivo, $filtros);

            // Remove vendas desconsideradas novamente
            foreach ($resultado['vendas'] as $key => $venda) {
                if (in_array($venda['id'], $cotas_desconsideradas_ids)) {
                    unset($resultado['vendas'][$key]);
                }
            }
            $resultado['vendas'] = array_values($resultado['vendas']);

            // Reagrupa por consultor
            $resultado = reagruparVendasPorConsultor($resultado['vendas']);
        }
    }

    // Detecta duplicados
    $duplicados = detectarDuplicados($resultado['vendas']);

    // Marca duplicados nas vendas
    marcarDuplicados($resultado['vendas'], $duplicados);

    // Adiciona informação de duplicados aos consultores
    $por_consultor = [];
    
    foreach ($resultado['por_consultor'] as $consultor) {
        $consultor_nome = $consultor['consultor'];

        // Calcula pontos com ranges (passa nome do consultor para debug)
        $calculo_pontos = calcularPontosComRanges($consultor['vendas_detalhes'], $consultor_nome);

        $consultor['pontos'] = $calculo_pontos['pontos_total'];
        $consultor['detalhamento_pontos'] = $calculo_pontos['detalhamento'];
        $consultor['detalhamento_por_range'] = $calculo_pontos['detalhamento_por_range'];

        // DEBUG: Verifica se pontos estão sendo preservados
        if (isGodMode() && stripos($consultor_nome, 'LUCIANA') !== false) {
            echo '<div class="alert alert-danger mt-2">';
            echo '<strong>⚠️ DEBUG VERIFICAÇÃO FINAL:</strong><br>';
            echo "Pontos calculados pela função: <strong>{$calculo_pontos['pontos_total']}</strong><br>";
            echo "Pontos atribuídos ao consultor: <strong>{$consultor['pontos']}</strong><br>";
            echo '</div>';
        }

        $por_consultor[] = $consultor;
    }
    
    // Ordena por pontos
    usort($por_consultor, function($a, $b) {
        return $b['pontos'] - $a['pontos'];
    });
    
    // Adiciona premiações
    adicionarPremiacoes($por_consultor);

    // Calcula pontos perdidos por cotas desconsideradas
    foreach ($vendas_desconsideradas as $venda_desc) {
        $nome_consultor = $venda_desc['consultor'];
        if (isset($vendas_desconsideradas_por_consultor[$nome_consultor])) {
            // Calcula pontos que essa venda teria gerado
            $pontos_venda = calcularPontosComRanges([
                [
                    'num_vagas' => $venda_desc['num_vagas'],
                    'e_vista' => $venda_desc['e_vista'],
                    'data_venda' => $venda_desc['data_para_pontuacao'],
                    'id_venda' => $venda_desc['id'],
                    'valor_total' => $venda_desc['valor_total'],
                    'valor_pago' => $venda_desc['valor_pago']
                ]
            ], $nome_consultor);

            $vendas_desconsideradas_por_consultor[$nome_consultor]['pontos'] += $pontos_venda['pontos_total'];
        }
    }

    return [
        'vendas' => $resultado['vendas'],
        'por_consultor' => $por_consultor,
        'duplicados' => $duplicados,
        'vendas_ignoradas_cartao' => $resultado['vendas_ignoradas_cartao'] ?? [],
        'vendas_ignoradas_pix' => $resultado['vendas_ignoradas_pix'] ?? [],
        'cotas_desconsideradas' => $vendas_desconsideradas,
        'cotas_desconsideradas_por_consultor' => $vendas_desconsideradas_por_consultor
    ];
}

/**
 * Reagrupa vendas por consultor
 */
function reagruparVendasPorConsultor($vendas) {
    $por_consultor = [];

    foreach ($vendas as $venda) {
        $nome = $venda['consultor'];

        if (!isset($por_consultor[$nome])) {
            $por_consultor[$nome] = [
                'consultor' => $nome,
                'quantidade' => 0,
                'venda' => 0,
                'pago' => 0,
                'vendas_detalhes' => [],
                'vendas_acima_2vagas' => 0
            ];
        }

        $por_consultor[$nome]['quantidade']++;
        $por_consultor[$nome]['venda'] += $venda['valor_total'];
        $por_consultor[$nome]['pago'] += $venda['valor_pago'];
        $por_consultor[$nome]['vendas_detalhes'][] = [
            'num_vagas' => $venda['num_vagas'],
            'e_vista' => $venda['e_vista'],
            'data_venda' => $venda['data_para_pontuacao'],
            'id_venda' => $venda['id'],
            'valor_total' => $venda['valor_total'],
            'valor_pago' => $venda['valor_pago']
        ];

        if ($venda['num_vagas'] > 2) {
            $por_consultor[$nome]['vendas_acima_2vagas']++;
        }
    }

    return [
        'vendas' => $vendas,
        'por_consultor' => array_values($por_consultor)
    ];
}

/**
 * Formata nome de categoria para exibiço
 */
function formatarNomeCategoria($categoria) {
    $mapa = [
        '1vaga_parcelado' => '1 vaga (parcelado)',
        '1vaga_vista' => '1 vaga (à vista)',
        '2a3_vagas' => '2-3 vagas',
        '4a7_vagas' => '4-7 vagas',
        '8a10_vagas' => '8-10 vagas',
        'acima_10' => 'Acima de 10 vagas',
        'acima_5_vista' => '5+ vagas ( vista)',
        'outros' => 'Outros'
    ];
    
    return $mapa[$categoria] ?? $categoria;
}

/**
 * Carrega apelidos/nomes alternativos de consultores
 */
function carregarApelidosConsultores() {
    $arquivo = DATA_DIR . '/consultores_apelidos.json';

    if (!file_exists($arquivo)) {
        return [];
    }

    $conteudo = file_get_contents($arquivo);
    $apelidos = json_decode($conteudo, true);

    return $apelidos ?: [];
}

/**
 * Salva apelidos/nomes alternativos de consultores
 */
function salvarApelidosConsultores($apelidos) {
    $arquivo = DATA_DIR . '/consultores_apelidos.json';

    $json = json_encode($apelidos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($arquivo, $json);
}

/**
 * Obtém nome de exibição do consultor (apelido se existir, senão nome original)
 */
function obterNomeExibicaoConsultor($nome_original) {
    static $apelidos = null;

    if ($apelidos === null) {
        $apelidos = carregarApelidosConsultores();
    }

    return $apelidos[$nome_original]['apelido'] ?? $nome_original;
}

/**
 * Adiciona ou atualiza apelido de um consultor
 */
function adicionarApelidoConsultor($nome_original, $apelido) {
    $apelidos = carregarApelidosConsultores();

    $apelidos[$nome_original] = [
        'nome_original' => $nome_original,
        'apelido' => $apelido,
        'data_alteracao' => date('Y-m-d H:i:s')
    ];

    salvarApelidosConsultores($apelidos);
}

/**
 * Remove apelido de um consultor
 */
function removerApelidoConsultor($nome_original) {
    $apelidos = carregarApelidosConsultores();

    if (isset($apelidos[$nome_original])) {
        unset($apelidos[$nome_original]);
        salvarApelidosConsultores($apelidos);
    }
}

/**
 * Carrega mapeamento de nomes amigáveis personalizados
 */
function carregarMapeamentoNomes() {
    $arquivo = DATA_DIR . '/nomes_amigaveis.json';

    if (!file_exists($arquivo)) {
        return [];
    }

    $conteudo = file_get_contents($arquivo);
    $mapeamento = json_decode($conteudo, true);

    return $mapeamento ?: [];
}

/**
 * Salva mapeamento de nomes amigáveis personalizados
 */
function salvarMapeamentoNomes($mapeamento) {
    $arquivo = DATA_DIR . '/nomes_amigaveis.json';

    $json = json_encode($mapeamento, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($arquivo, $json);
}

/**
 * Define um nome amigável personalizado para um arquivo CSV
 */
function definirNomeAmigavel($nome_arquivo, $nome_amigavel) {
    $mapeamento = carregarMapeamentoNomes();

    // Remove a extensão .csv se foi passada
    $nome_arquivo = basename($nome_arquivo);

    $mapeamento[$nome_arquivo] = [
        'nome_amigavel' => $nome_amigavel,
        'nome_arquivo' => $nome_arquivo,
        'data_definicao' => date('Y-m-d H:i:s')
    ];

    salvarMapeamentoNomes($mapeamento);
}

/**
 * Gera nome amigável para arquivo CSV baseado na data
 * Ex: 2025-12-01_034017_vendas.csv → vendas-novembro25
 * Verifica primeiro se há mapeamento personalizado
 */
function gerarNomeAmigavel($nome_arquivo) {
    // Remove caminho se tiver
    $nome_arquivo = basename($nome_arquivo);

    // Verifica se há mapeamento personalizado
    $mapeamento = carregarMapeamentoNomes();
    if (isset($mapeamento[$nome_arquivo])) {
        return $mapeamento[$nome_arquivo]['nome_amigavel'];
    }

    // Tenta extrair a data do nome do arquivo (formato: YYYY-MM-DD_HHMMSS_vendas.csv)
    if (preg_match('/(\d{4})-(\d{2})-(\d{2})_/', $nome_arquivo, $matches)) {
        $ano = $matches[1];
        $mes = intval($matches[2]);

        // Meses em português
        $meses = [
            1 => 'janeiro', 2 => 'fevereiro', 3 => 'marco', 4 => 'abril',
            5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
            9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro'
        ];

        $nome_mes = $meses[$mes] ?? 'desconhecido';
        $ano_curto = substr($ano, 2, 2);

        return "vendas-{$nome_mes}{$ano_curto}";
    }

    // Fallback: retorna o nome do arquivo sem extensão
    return pathinfo($nome_arquivo, PATHINFO_FILENAME);
}

/**
 * Converte nome amigável de volta para arquivo CSV real
 * Ex: vendas-novembro25 → 2025-12-01_034017_vendas.csv (procura no diretório)
 * Verifica primeiro se há mapeamento personalizado
 */
function obterArquivoRealPorNomeAmigavel($nome_amigavel) {
    // Verifica primeiro se há mapeamento personalizado
    $mapeamento = carregarMapeamentoNomes();
    foreach ($mapeamento as $nome_arquivo => $dados) {
        if ($dados['nome_amigavel'] === $nome_amigavel) {
            // Encontrou mapeamento personalizado, retorna o caminho completo
            return VENDAS_DIR . '/' . $nome_arquivo;
        }
    }

    // Se não encontrou mapeamento personalizado, tenta extrair pelo padrão de nome
    // Extrai mês e ano do nome amigável (ex: vendas-novembro25)
    if (preg_match('/vendas-(\w+)(\d{2})/', $nome_amigavel, $matches)) {
        $nome_mes = $matches[1];
        $ano_curto = $matches[2];

        // Mapeia nome do mês para número
        $meses = [
            'janeiro' => '01', 'fevereiro' => '02', 'marco' => '03', 'abril' => '04',
            'maio' => '05', 'junho' => '06', 'julho' => '07', 'agosto' => '08',
            'setembro' => '09', 'outubro' => '10', 'novembro' => '11', 'dezembro' => '12'
        ];

        if (!isset($meses[$nome_mes])) {
            return null;
        }

        $mes = $meses[$nome_mes];
        $ano = '20' . $ano_curto;

        // Lista todos os CSVs e procura por um que corresponda ao mês/ano
        $arquivos = listarCSVs('vendas');
        foreach ($arquivos as $arquivo) {
            if (preg_match("/{$ano}-{$mes}-/", $arquivo['nome'])) {
                return $arquivo['caminho'];
            }
        }
    }

    return null;
}

/**
 * Carrega mensagens de parabéns para relatórios finais
 */
function carregarMensagensParabens() {
    $arquivo = DATA_DIR . '/mensagens_parabens.json';

    if (!file_exists($arquivo)) {
        // Cria arquivo com 3 mensagens padrão
        $mensagens_padrao = [
            [
                'id' => uniqid('msg_', true),
                'titulo' => 'Parabéns aos Campeões!',
                'mensagem' => 'Parabéns a todos os consultores que alcançaram o TOP 20! Seu esforço e dedicação são inspiradores! 🏆',
                'ativo' => true,
                'data_criacao' => date('Y-m-d H:i:s')
            ],
            [
                'id' => uniqid('msg_', true),
                'titulo' => 'Celebrando o Sucesso!',
                'mensagem' => 'Este é o ranking oficial final! Parabéns aos vencedores e a todos que se esforçaram para chegar até aqui! 🎉',
                'ativo' => true,
                'data_criacao' => date('Y-m-d H:i:s')
            ],
            [
                'id' => uniqid('msg_', true),
                'titulo' => 'Você é Incrível!',
                'mensagem' => 'Resultado oficial disponível! Parabéns aos TOP 20 e a todos que fizeram parte desta jornada incrível! 🌟',
                'ativo' => true,
                'data_criacao' => date('Y-m-d H:i:s')
            ]
        ];
        salvarMensagensParabens($mensagens_padrao);
        return $mensagens_padrao;
    }

    $conteudo = file_get_contents($arquivo);
    $mensagens = json_decode($conteudo, true);

    return $mensagens ?: [];
}

/**
 * Salva mensagens de parabéns
 */
function salvarMensagensParabens($mensagens) {
    $arquivo = DATA_DIR . '/mensagens_parabens.json';

    $json = json_encode($mensagens, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($arquivo, $json);
}

/**
 * Adiciona nova mensagem de parabéns
 */
function adicionarMensagemParabens($titulo, $mensagem) {
    $mensagens = carregarMensagensParabens();

    $mensagens[] = [
        'id' => uniqid('msg_', true),
        'titulo' => $titulo,
        'mensagem' => $mensagem,
        'ativo' => true,
        'data_criacao' => date('Y-m-d H:i:s')
    ];

    salvarMensagensParabens($mensagens);
}

/**
 * Remove mensagem de parabéns
 */
function removerMensagemParabens($id) {
    $mensagens = carregarMensagensParabens();

    $mensagens = array_filter($mensagens, function($msg) use ($id) {
        return $msg['id'] !== $id;
    });

    // Reindexar array
    $mensagens = array_values($mensagens);

    salvarMensagensParabens($mensagens);
}

/**
 * Alterna status ativo/inativo de uma mensagem
 */
function alternarStatusMensagem($id) {
    $mensagens = carregarMensagensParabens();

    foreach ($mensagens as &$msg) {
        if ($msg['id'] === $id) {
            $msg['ativo'] = !$msg['ativo'];
            break;
        }
    }

    salvarMensagensParabens($mensagens);
}

/**
 * Obtém uma mensagem aleatória de parabéns (apenas ativas)
 */
function obterMensagemAleatoriaParabens() {
    $mensagens = carregarMensagensParabens();

    // Filtra apenas mensagens ativas
    $mensagens_ativas = array_filter($mensagens, function($msg) {
        return $msg['ativo'] ?? true;
    });

    if (empty($mensagens_ativas)) {
        return [
            'titulo' => 'Parabéns!',
            'mensagem' => 'Ranking oficial disponível! Parabéns a todos! 🎉'
        ];
    }

    // Seleciona uma mensagem aleatória
    $mensagem = $mensagens_ativas[array_rand($mensagens_ativas)];

    return $mensagem;
}

/**
 * Verifica se é dia 08 ou posterior do mês seguinte ao período do relatório
 * E filtra/conta vendas canceladas e sem primeira parcela paga
 */
function aplicarRegraDia08(&$vendas, $data_inicio_periodo, $data_fim_periodo) {
    // Verifica se é dia 08 ou posterior do mês seguinte
    $hoje = new DateTime();
    $fim_periodo = new DateTime($data_fim_periodo);

    // Calcula o dia 08 do mês seguinte ao período
    $mes_seguinte = clone $fim_periodo;
    $mes_seguinte->modify('first day of next month');
    $mes_seguinte->setDate(
        (int)$mes_seguinte->format('Y'),
        (int)$mes_seguinte->format('m'),
        8
    );

    // Se ainda não chegou no dia 08, não aplica filtro
    if ($hoje < $mes_seguinte) {
        return [
            'aplicar_filtro' => false,
            'removidas_canceladas' => 0,
            'removidas_sem_pagamento' => 0
        ];
    }

    // É dia 08 ou posterior, aplica filtro
    $canceladas = 0;
    $sem_pagamento = 0;
    $vendas_filtradas = [];

    foreach ($vendas as $venda) {
        $remover = false;

        // Remove vendas com status Cancelado
        if (strcasecmp($venda['status'], 'Cancelado') === 0 ||
            strcasecmp($venda['status'], 'Cancelada') === 0) {
            $canceladas++;
            $remover = true;
        }

        // Remove vendas sem primeira parcela paga
        if (!$remover && !($venda['primeira_parcela_paga'] ?? false)) {
            $sem_pagamento++;
            $remover = true;
        }

        if (!$remover) {
            $vendas_filtradas[] = $venda;
        }
    }

    $vendas = $vendas_filtradas;

    return [
        'aplicar_filtro' => true,
        'removidas_canceladas' => $canceladas,
        'removidas_sem_pagamento' => $sem_pagamento
    ];
}

?>
<?php
// functions/promotores.php - Funções para processar promotores

/**
 * Processa CSV de promotores
 * Estrutura do CSV:
 * 0: Nome
 * 1: Código
 * 2: Comissão (%)
 * 3: Status
 * 4: CPF
 * 5: RG
 * 6: Endereço
 * 7: Número
 * 8: Complemento
 * 9: Bairro
 * 10: Cidade
 * 11: Estado
 * 12: CEP
 * 13: Telefone
 */

/**
 * Remove BOM (Byte Order Mark) UTF-8 do início de uma string

function removerBOM($str) {
    // BOM UTF-8: EF BB BF
    if (substr($str, 0, 3) === "\xEF\xBB\xBF") {
        return substr($str, 3);
    }
    return $str;
}
 */
/**
 * Processa arquivo CSV de promotores
 */
function processarPromotoresCSV($arquivo) {
    $promotores = [];
    
    if (($handle = fopen($arquivo, "r")) !== false) {
        // ===== REMOVE BOM UTF-8 DO INÍCIO DO ARQUIVO =====
        $primeira_linha_pos = ftell($handle);
        $primeira_linha = fgets($handle);
        
        if (substr($primeira_linha, 0, 3) === "\xEF\xBB\xBF") {
            // Tem BOM, posiciona após ele
            fseek($handle, 3);
             "<!-- PROMOTORES: BOM UTF-8 DETECTADO E REMOVIDO -->";
        } else {
            // Não tem BOM, volta pro início
            fseek($handle, $primeira_linha_pos);
        }
        
        // Detecta separador
        $primeira_linha_teste = fgets($handle);
        rewind($handle);
        
        // Pula BOM novamente se existir
        if (substr(fgets($handle), 0, 3) === "\xEF\xBB\xBF") {
            fseek($handle, 3);
        } else {
            rewind($handle);
        }
        
        $tabs = substr_count($primeira_linha_teste, "\t");
        $pontovirgulas = substr_count($primeira_linha_teste, ";");
        $separador = ($tabs > $pontovirgulas) ? "\t" : ";";
        
         "<!-- PROMOTORES: Separador detectado: " . ($separador === "\t" ? "TAB" : "PONTO-VÍRGULA") . " -->";
        
        // ===== DETECTA SE TEM CABEÇALHO =====
        $primeira_linha_dados = fgetcsv($handle, 0, $separador);
        
        $tem_cabecalho = false;
        if (!empty($primeira_linha_dados)) {
            // Remove BOM da primeira coluna se existir
            $primeira_linha_dados[0] = removerBOM(trim($primeira_linha_dados[0]));
            
            $primeira_coluna = strtolower($primeira_linha_dados[0]);
            
            // Se contém palavras de cabeçalho
            $palavras_cabecalho = ['nome', 'promotor', 'titulo', 'documento', 'cpf', 'status'];
            foreach ($palavras_cabecalho as $palavra) {
                if (strpos($primeira_coluna, $palavra) !== false) {
                    $tem_cabecalho = true;
                     "<!-- PROMOTORES: Cabeçalho detectado (palavra-chave: '{$palavra}') -->";
                    break;
                }
            }
            
            // Se não é cabeçalho, processa essa primeira linha
            if (!$tem_cabecalho) {
                 "<!-- PROMOTORES: CSV SEM CABEÇALHO - processando desde primeira linha -->";

                // Processa a primeira linha como dado
                // MAPEAMENTO PROVISÓRIO - ajustar com linha exemplo real do CSV
                if (count($primeira_linha_dados) >= 14) {
                    $promotores[] = [
                        'nome' => trim($primeira_linha_dados[0]),
                        'codigo' => trim($primeira_linha_dados[1] ?? ''),
                        'comissao' => floatval(trim($primeira_linha_dados[2] ?? 0)),
                        'status' => trim($primeira_linha_dados[3] ?? ''),
                        'cpf' => trim($primeira_linha_dados[4] ?? ''),
                        'rg' => trim($primeira_linha_dados[5] ?? ''),
                        'rua' => trim($primeira_linha_dados[6] ?? ''),
                        'numero' => trim($primeira_linha_dados[7] ?? ''),
                        'complemento' => trim($primeira_linha_dados[8] ?? ''),
                        'bairro' => trim($primeira_linha_dados[9] ?? ''),
                        'cidade' => trim($primeira_linha_dados[10] ?? ''),
                        'estado' => trim($primeira_linha_dados[11] ?? ''),
                        'cep' => trim($primeira_linha_dados[12] ?? ''),
                        'telefone' => trim($primeira_linha_dados[13] ?? ''),
                        'endereco_completo' => trim($primeira_linha_dados[6] ?? '') . ', ' . trim($primeira_linha_dados[7] ?? '') . ' - ' . trim($primeira_linha_dados[9] ?? '') . ', ' . trim($primeira_linha_dados[10] ?? '') . '/' . trim($primeira_linha_dados[11] ?? '')
                    ];
                }
            } else {
                 "<!-- PROMOTORES: Cabeçalho ignorado, processando dados -->";
            }
        }
        
        // Processa resto do arquivo
        $linha_num = 1;
        while (($dados = fgetcsv($handle, 0, $separador)) !== false) {
            $linha_num++;
            
            // Pula linhas vazias ou com poucos dados
            if (empty($dados) || count($dados) < 14) {
                continue;
            }
            
            // Remove BOM da primeira coluna (por segurança)
            $dados[0] = removerBOM(trim($dados[0]));
            
            // Pula se primeira coluna estiver vazia
            if (empty($dados[0])) {
                continue;
            }
            
            // MAPEAMENTO PROVISÓRIO - ajustar com linha exemplo real do CSV
            $promotores[] = [
                'nome' => trim($dados[0]),
                'codigo' => trim($dados[1] ?? ''),
                'comissao' => floatval(trim($dados[2] ?? 0)),
                'status' => trim($dados[3] ?? ''),
                'cpf' => trim($dados[4] ?? ''),
                'rg' => trim($dados[5] ?? ''),
                'rua' => trim($dados[6] ?? ''),
                'numero' => trim($dados[7] ?? ''),
                'complemento' => trim($dados[8] ?? ''),
                'bairro' => trim($dados[9] ?? ''),
                'cidade' => trim($dados[10] ?? ''),
                'estado' => trim($dados[11] ?? ''),
                'cep' => trim($dados[12] ?? ''),
                'telefone' => trim($dados[13] ?? ''),
                'endereco_completo' => trim($dados[6] ?? '') . ', ' . trim($dados[7] ?? '') . ' - ' . trim($dados[9] ?? '') . ', ' . trim($dados[10] ?? '') . '/' . trim($dados[11] ?? '')
            ];
        }
        
        fclose($handle);

         "<!-- PROMOTORES: " . count($promotores) . " promotores processados -->";
    }

    return [
        'promotores' => $promotores,
        'total' => count($promotores)
    ];
}

/**
 * Valida consultor usando arquivo de promotores mais recente
 */
function validarConsultorComPromotores($consultor_nome, $identificacao) {
    // Carrega arquivo de promotores mais recente
    $arquivos_promotores = listarCSVs('promotores');
    
    if (empty($arquivos_promotores)) {
        return [
            'valido' => false,
            'mensagem' => 'Nenhum arquivo de promotores disponível para validação.'
        ];
    }
    
    $arquivo_promotores = $arquivos_promotores[0]['caminho'];
    $dados_promotores = processarPromotoresCSV($arquivo_promotores);
    
    // Busca o promotor pelo nome
    $promotor_encontrado = null;
    foreach ($dados_promotores['promotores'] as $promotor) {
        if ($promotor['nome'] === $consultor_nome) {
            $promotor_encontrado = $promotor;
            break;
        }
    }
    
    if (!$promotor_encontrado) {
        return [
            'valido' => false,
            'mensagem' => 'Consultor não encontrado no cadastro de promotores.'
        ];
    }
    
    // Limpa CPF e Telefone
    $cpf = preg_replace('/[^0-9]/', '', $promotor_encontrado['cpf']);
    $telefone = preg_replace('/[^0-9]/', '', $promotor_encontrado['telefone']);
    
    // Limpa identificação digitada
    $identificacao = preg_replace('/[^0-9]/', '', $identificacao);
    
    $valido = false;
    
    // Validação por CPF completo (11 dígitos)
    if (strlen($identificacao) == 11 && $identificacao === $cpf) {
        $valido = true;
    }
    
    // Validação por telefone completo (11 dígitos)
    if (!$valido && strlen($identificacao) == 11 && $identificacao === $telefone) {
        $valido = true;
    }
    
    // Validação por 4 dígitos (início ou fim do CPF)
    if (!$valido && strlen($identificacao) == 4) {
        $primeiros_4_cpf = substr($cpf, 0, 4);
        $ultimos_4_cpf = substr($cpf, -4);
        
        if ($identificacao === $primeiros_4_cpf || $identificacao === $ultimos_4_cpf) {
            $valido = true;
        }
    }
    
    // Validação por 4 dígitos (início ou fim do telefone)
    if (!$valido && strlen($identificacao) == 4) {
        $primeiros_4_tel = substr($telefone, 0, 4);
        $ultimos_4_tel = substr($telefone, -4);
        
        if ($identificacao === $primeiros_4_tel || $identificacao === $ultimos_4_tel) {
            $valido = true;
        }
    }
    
    // Validaão por sequência contida (5-10 dígitos)
    if (!$valido && strlen($identificacao) >= 5 && strlen($identificacao) <= 10) {
        if (strpos($cpf, $identificacao) !== false || strpos($telefone, $identificacao) !== false) {
            $valido = true;
        }
    }
    
    if ($valido) {
        return [
            'valido' => true,
            'mensagem' => 'Identificação válida!'
        ];
    } else {
        return [
            'valido' => false,
            'mensagem' => 'Identificação inválida! Verifique os dados informados.<br>' .
                         '<small>Dica: Use 4 dígitos do CPF (incio ou fim) ou telefone completo (11 dígitos).</small>',
            'debug' => [
                'cpf' => $cpf,
                'telefone' => $telefone,
                'primeiros_4_cpf' => substr($cpf, 0, 4),
                'ultimos_4_cpf' => substr($cpf, -4)
            ]
        ];
    }
}

/**
 * Filtra promotores por status
 */
function filtrarPromotores($promotores, $filtros = []) {
    $resultado = $promotores;
    
    // Filtro de status
    if (isset($filtros['status']) && !empty($filtros['status'])) {
        $resultado = array_filter($resultado, function($p) use ($filtros) {
            return strcasecmp($p['status'], $filtros['status']) === 0;
        });
    }
    
    // Filtro de busca por nome
    if (isset($filtros['busca']) && !empty($filtros['busca'])) {
        $busca = strtolower($filtros['busca']);
        $resultado = array_filter($resultado, function($p) use ($busca) {
            return stripos($p['nome'], $busca) !== false ||
                   stripos($p['cpf'], $busca) !== false ||
                   stripos($p['codigo'], $busca) !== false;
        });
    }
    
    // Filtro de cidade
    if (isset($filtros['cidade']) && !empty($filtros['cidade'])) {
        $resultado = array_filter($resultado, function($p) use ($filtros) {
            return strcasecmp($p['cidade'], $filtros['cidade']) === 0;
        });
    }
    
    return array_values($resultado);
}

/**
 * Obtém lista de status nicos
 */
function obterStatusPromotores($promotores) {
    $status_list = array_unique(array_map(function($p) {
        return $p['status'];
    }, $promotores));
    
    sort($status_list);
    return $status_list;
}

/**
 * Obtém lista de cidades únicas
 */
function obterCidadesPromotores($promotores) {
    $cidades = array_unique(array_map(function($p) {
        return $p['cidade'];
    }, $promotores));
    
    sort($cidades);
    return array_filter($cidades); // Remove vazios
}

/**
 * Formata telefone para exibiço
 */
function formatarTelefone($telefone) {
    $telefone = preg_replace('/\D/', '', $telefone);
    
    if (empty($telefone)) {
        return 'No informado';
    }
    
    if (strlen($telefone) == 11) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7, 4);
    } elseif (strlen($telefone) == 10) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6, 4);
    }
    
    return $telefone;
}

/**
 * Formata CPF para exibição
 */
function formatarCPF($cpf) {
    $cpf = preg_replace('/\D/', '', $cpf);
    
    if (strlen($cpf) == 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    
    return $cpf;
}

/**
 * Formata CEP para exibição
 */
function formatarCEP($cep) {
    $cep = preg_replace('/\D/', '', $cep);
    
    if (strlen($cep) == 8) {
        return substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
    }
    
    return $cep;
}
?>
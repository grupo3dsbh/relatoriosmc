<?php
// ajax/carregar_vendas.php - COMPLETO

session_start();
require_once '../config.php';
require_once '../functions/vendas.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

try {
    if (!isset($_POST['vendas_ids']) || !isset($_POST['consultor'])) {
        throw new Exception('Parâmetros faltando');
    }

    $vendas_ids = json_decode($_POST['vendas_ids'], true);
    $consultor_nome = $_POST['consultor'];

    $arquivos_vendas = listarCSVs('vendas');
    if (empty($arquivos_vendas)) {
        throw new Exception('Nenhum arquivo disponível');
    }

    $arquivo_vendas = $arquivos_vendas[0]['caminho'];
    $resultado = processarVendasCSV($arquivo_vendas);
    
    $vendas_consultor = array_filter($resultado['vendas'], function($v) use ($consultor_nome, $vendas_ids) {
        return $v['consultor'] === $consultor_nome && in_array($v['id'], $vendas_ids);
    });

    $vendas_retorno = [];
    foreach ($vendas_consultor as $venda) {
        // Usa data_cadastro para exibição (data_para_filtro já é usada internamente)
        $data = DateTime::createFromFormat('Y-m-d H:i:s.u', $venda['data_cadastro']);
        if (!$data) {
            $data = DateTime::createFromFormat('Y-m-d H:i:s', $venda['data_cadastro']);
        }

        $vendas_retorno[] = [
            'id' => $venda['id'],
            'produto_atual' => $venda['produto_atual'],
            'produto_original' => $venda['produto_original'],
            'produto_alterado' => $venda['produto_alterado'],
            'data_cadastro_formatada' => $data ? $data->format('d/m/Y H:i') : $venda['data_cadastro'],
            'status' => $venda['status'],
            'titular_mascarado' => mascararNome($venda['titular']),
            'tipo_pagamento' => $venda['tipo_pagamento'],
            'forma_pagamento' => $venda['forma_pagamento'] ?? '',
            'num_parcelas' => $venda['quantidade_parcelas_venda'], // CORRETO
            'primeira_parcela_paga' => $venda['primeira_parcela_paga'],
            'valor_pago' => $venda['valor_pago']
        ];
    }

    usort($vendas_retorno, function($a, $b) {
        return strcmp($a['id'], $b['id']);
    });

    echo json_encode([
        'success' => true,
        'vendas' => $vendas_retorno,
        'total' => count($vendas_retorno)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
exit;
?>
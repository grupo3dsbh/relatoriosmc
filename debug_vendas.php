<?php
// debug_vendas.php - Script para debugar vendas perdidas

session_start();
require_once 'config.php';
require_once 'functions/vendas.php';

// Inicializa configurações se necessário
if (!isset($_SESSION['config_sistema'])) {
    inicializarConfiguracoes();
}

echo "=== DEBUG: Análise de Vendas ===\n\n";

// Lista arquivos CSV
$arquivos = listarCSVs('vendas');

if (empty($arquivos)) {
    echo "Nenhum arquivo CSV encontrado!\n";
    exit;
}

$arquivo = $arquivos[0]['caminho'];
echo "Processando: {$arquivo}\n\n";

// Abre o CSV
$handle = fopen($arquivo, 'r');
if (!$handle) {
    echo "Erro ao abrir arquivo!\n";
    exit;
}

// Pula header
fgetcsv($handle, 0, ';');

$total_linhas = 0;
$vendas_validas = 0;
$vendas_problematicas = [];

while (($data = fgetcsv($handle, 0, ';')) !== false) {
    $total_linhas++;

    // Mapeia campos conforme o sistema
    $venda = [
        'id' => trim($data[0] ?? ''),
        'produto_original' => trim($data[1] ?? ''),
        'produto_atual' => trim($data[2] ?? ''),
        'alterou_vagas' => trim($data[3] ?? ''),
        'categoria' => trim($data[4] ?? ''),
        'data_cadastro' => trim($data[5] ?? ''),
        'data_venda' => trim($data[6] ?? ''),
        'origem_venda' => trim($data[7] ?? ''),
        'telefone' => trim($data[8] ?? ''),
        'status' => trim($data[9] ?? ''),
        'consultor' => trim($data[10] ?? ''),
        'gerente' => trim($data[11] ?? ''),
        'titular' => trim($data[12] ?? ''),
        'cpf' => trim($data[13] ?? ''),
    ];

    // Verifica se seria filtrada
    $problemas = [];

    // Validação 1: ID válido
    if (!preg_match('/^SFA-\d+$/', $venda['id'])) {
        $problemas[] = "ID inválido: " . $venda['id'];
    }

    // Validação 2: Consultor não vazio
    if (empty($venda['consultor'])) {
        $problemas[] = "Consultor vazio";
    }

    // Validação 3: Datas vazias ou inválidas
    if (empty($venda['data_cadastro'])) {
        $problemas[] = "DataCadastro vazia";
    } else {
        $timestamp_cadastro = strtotime($venda['data_cadastro']);
        if ($timestamp_cadastro === false) {
            $problemas[] = "DataCadastro inválida: " . $venda['data_cadastro'];
        }
    }

    if (empty($venda['data_venda'])) {
        $problemas[] = "DataVenda vazia";
    } else {
        $timestamp_venda = strtotime($venda['data_venda']);
        if ($timestamp_venda === false) {
            $problemas[] = "DataVenda inválida: " . $venda['data_venda'];
        }
    }

    // Validação 4: Produto alterado
    $produto_alterado = ($venda['produto_original'] !== $venda['produto_atual']);

    if ($produto_alterado && empty($venda['data_cadastro'])) {
        $problemas[] = "Produto alterado MAS DataCadastro vazia!";
    }

    if (count($problemas) > 0) {
        $vendas_problematicas[] = [
            'linha' => $total_linhas + 1, // +1 por causa do header
            'id' => $venda['id'],
            'consultor' => $venda['consultor'],
            'data_cadastro' => $venda['data_cadastro'],
            'data_venda' => $venda['data_venda'],
            'produto_original' => $venda['produto_original'],
            'produto_atual' => $venda['produto_atual'],
            'produto_alterado' => $produto_alterado ? 'SIM' : 'NÃO',
            'problemas' => implode(' | ', $problemas)
        ];
    } else {
        $vendas_validas++;
    }
}

fclose($handle);

echo "Total de linhas (sem header): $total_linhas\n";
echo "Vendas válidas: $vendas_validas\n";
echo "Vendas problemáticas: " . count($vendas_problematicas) . "\n\n";

if (count($vendas_problematicas) > 0) {
    echo "=== VENDAS PROBLEMÁTICAS ===\n\n";
    foreach ($vendas_problematicas as $vp) {
        echo "Linha {$vp['linha']}: {$vp['id']} - {$vp['consultor']}\n";
        echo "  DataCadastro: {$vp['data_cadastro']}\n";
        echo "  DataVenda: {$vp['data_venda']}\n";
        echo "  Produto Original: {$vp['produto_original']}\n";
        echo "  Produto Atual: {$vp['produto_atual']}\n";
        echo "  Produto Alterado: {$vp['produto_alterado']}\n";
        echo "  Problemas: {$vp['problemas']}\n\n";
    }
}

echo "\n=== Testando processarVendasCSV() ===\n";
$resultado = processarVendasCSV($arquivo);
echo "Vendas processadas: " . count($resultado['vendas']) . "\n";
echo "Consultores: " . count($resultado['por_consultor']) . "\n";

if (count($resultado['vendas']) < $vendas_validas) {
    $diferenca = $vendas_validas - count($resultado['vendas']);
    echo "\n⚠️  ATENÇÃO: $diferenca vendas foram filtradas pela função processarVendasCSV()!\n";
    echo "Verifique os filtros aplicados.\n";
}

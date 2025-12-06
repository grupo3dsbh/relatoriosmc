<?php
// debug_vendas_simples.php - Debug simples sem dependências

echo "=== DEBUG: Análise de Vendas (Simples) ===\n\n";

// Lista arquivos CSV manualmente
$vendas_dir = __DIR__ . '/data/vendas';
$arquivos = glob($vendas_dir . '/*.csv');

if (empty($arquivos)) {
    echo "Nenhum arquivo CSV encontrado em $vendas_dir!\n";
    exit;
}

// Pega o primeiro arquivo
$arquivo = $arquivos[0];
echo "Arquivo: " . basename($arquivo) . "\n\n";

// Abre o CSV
$handle = fopen($arquivo, 'r');
if (!$handle) {
    echo "Erro ao abrir arquivo!\n";
    exit;
}

// Pula header
fgetcsv($handle, 0, ';');

$total_linhas = 0;
$vendas_problematicas = [];
$datas_vazias_cadastro = 0;
$datas_vazias_venda = 0;
$datas_invalidas_cadastro = 0;
$datas_invalidas_venda = 0;
$ids_invalidos = 0;
$consultores_vazios = 0;

while (($data = fgetcsv($handle, 0, ';')) !== false) {
    $total_linhas++;

    $id = trim($data[0] ?? '');
    $produto_original = trim($data[1] ?? '');
    $produto_atual = trim($data[2] ?? '');
    $data_cadastro = trim($data[5] ?? '');
    $data_venda = trim($data[6] ?? '');
    $consultor = trim($data[10] ?? '');

    // Verifica problemas
    if (!preg_match('/^SFA-\d+$/', $id)) {
        $ids_invalidos++;
        $vendas_problematicas[] = [
            'linha' => $total_linhas + 1,
            'id' => $id,
            'problema' => 'ID inválido'
        ];
        continue;
    }

    if (empty($consultor)) {
        $consultores_vazios++;
        $vendas_problematicas[] = [
            'linha' => $total_linhas + 1,
            'id' => $id,
            'problema' => 'Consultor vazio'
        ];
        continue;
    }

    // Verifica datas
    if (empty($data_cadastro)) {
        $datas_vazias_cadastro++;
        $vendas_problematicas[] = [
            'linha' => $total_linhas + 1,
            'id' => $id,
            'problema' => 'DataCadastro vazia'
        ];
    } else {
        $timestamp = strtotime($data_cadastro);
        if ($timestamp === false) {
            $datas_invalidas_cadastro++;
            $vendas_problematicas[] = [
                'linha' => $total_linhas + 1,
                'id' => $id,
                'problema' => "DataCadastro inválida: $data_cadastro"
            ];
        }
    }

    if (empty($data_venda)) {
        $datas_vazias_venda++;
        if (!in_array($id, array_column($vendas_problematicas, 'id'))) {
            $vendas_problematicas[] = [
                'linha' => $total_linhas + 1,
                'id' => $id,
                'problema' => 'DataVenda vazia'
            ];
        }
    } else {
        $timestamp = strtotime($data_venda);
        if ($timestamp === false) {
            $datas_invalidas_venda++;
            if (!in_array($id, array_column($vendas_problematicas, 'id'))) {
                $vendas_problematicas[] = [
                    'linha' => $total_linhas + 1,
                    'id' => $id,
                    'problema' => "DataVenda inválida: $data_venda"
                ];
            }
        }
    }
}

fclose($handle);

echo "=== RESUMO ===\n";
echo "Total de linhas (sem header): $total_linhas\n";
echo "IDs inválidos: $ids_invalidos\n";
echo "Consultores vazios: $consultores_vazios\n";
echo "DataCadastro vazia: $datas_vazias_cadastro\n";
echo "DataCadastro inválida: $datas_invalidas_cadastro\n";
echo "DataVenda vazia: $datas_vazias_venda\n";
echo "DataVenda inválida: $datas_invalidas_venda\n";
echo "\nTotal de vendas problemáticas: " . count($vendas_problematicas) . "\n";

$vendas_esperadas = $total_linhas - $ids_invalidos - $consultores_vazios;
echo "\nVendas que DEVERIAM passar: $vendas_esperadas\n";

if (count($vendas_problematicas) > 0) {
    echo "\n=== PRIMEIRAS 20 VENDAS PROBLEMÁTICAS ===\n";
    foreach (array_slice($vendas_problematicas, 0, 20) as $vp) {
        echo "Linha {$vp['linha']}: {$vp['id']} - {$vp['problema']}\n";
    }
}

<?php
session_start();
require_once 'config.php';
require_once 'functions/vendas.php';
require_once 'functions/configuracoes.php';

// Processa o CSV mais recente
$arquivos_vendas = listarCSVs('vendas');
if (empty($arquivos_vendas)) {
    die("Nenhum arquivo de vendas encontrado!");
}

$arquivo = $arquivos_vendas[0]['caminho'];

echo "<h2>DEBUG DETALHADO - Análise de Vendas</h2>";
echo "<p><strong>Arquivo:</strong> " . basename($arquivo) . "</p>";

// Abre o arquivo e analisa cada linha
$handle = fopen($arquivo, 'r');
if (!$handle) {
    die("Erro ao abrir arquivo!");
}

// Remove BOM e detecta separador
$primeira_linha = fgets($handle);
if (substr($primeira_linha, 0, 3) === "\xEF\xBB\xBF") {
    fseek($handle, 3);
} else {
    rewind($handle);
}

$separador = detectarSeparadorCSV($arquivo);
$tem_cabecalho = csvTemCabecalho($arquivo, $separador);

if ($tem_cabecalho) {
    fgetcsv($handle, 0, $separador); // Pula cabeçalho
}

$total_linhas = 0;
$linhas_validas = 0;
$linhas_invalidas = [];
$problemas_por_tipo = [];

while (($dados = fgetcsv($handle, 0, $separador)) !== false) {
    $total_linhas++;
    $num_colunas = count($dados);

    // Verifica problemas
    $problemas = [];

    if ($num_colunas < 10) {
        $problemas[] = "Menos de 10 colunas ($num_colunas)";
    } elseif ($num_colunas < 24) {
        $problemas[] = "Menos de 24 colunas ($num_colunas)";
    }

    if (empty(trim($dados[0]))) {
        $problemas[] = "ID vazio";
    } elseif (!preg_match('/^SFA-\d+$/', trim($dados[0]))) {
        $problemas[] = "ID inválido: " . trim($dados[0]);
    }

    if ($num_colunas >= 10 && empty(trim($dados[10]))) {
        $problemas[] = "Consultor vazio";
    }

    if (!empty($problemas)) {
        $linhas_invalidas[] = [
            'linha' => $total_linhas + ($tem_cabecalho ? 1 : 0),
            'id' => trim($dados[0] ?? 'N/A'),
            'colunas' => $num_colunas,
            'problemas' => implode(", ", $problemas)
        ];

        foreach ($problemas as $prob) {
            if (!isset($problemas_por_tipo[$prob])) {
                $problemas_por_tipo[$prob] = 0;
            }
            $problemas_por_tipo[$prob]++;
        }
    } else {
        $linhas_validas++;
    }
}

fclose($handle);

echo "<h3>Resumo:</h3>";
echo "<ul>";
echo "<li>Total de linhas de dados: <strong>$total_linhas</strong></li>";
echo "<li>Linhas válidas: <strong style='color:green'>$linhas_validas</strong></li>";
echo "<li>Linhas inválidas: <strong style='color:red'>" . count($linhas_invalidas) . "</strong></li>";
echo "<li>Header detectado: <strong>" . ($tem_cabecalho ? "Sim" : "Não") . "</strong></li>";
echo "</ul>";

if (!empty($problemas_por_tipo)) {
    echo "<h3>Problemas por Tipo:</h3>";
    echo "<ul>";
    foreach ($problemas_por_tipo as $tipo => $count) {
        echo "<li><strong>$tipo:</strong> $count linha(s)</li>";
    }
    echo "</ul>";
}

if (!empty($linhas_invalidas)) {
    echo "<h3 style='color:red;'>Linhas Inválidas (" . count($linhas_invalidas) . "):</h3>";
    echo "<div style='max-height:400px; overflow-y:auto;'>";
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
    echo "<tr><th>Linha</th><th>ID</th><th>Colunas</th><th>Problemas</th></tr>";
    foreach ($linhas_invalidas as $inv) {
        echo "<tr>";
        echo "<td>{$inv['linha']}</td>";
        echo "<td>" . htmlspecialchars($inv['id']) . "</td>";
        echo "<td>{$inv['colunas']}</td>";
        echo "<td>" . htmlspecialchars($inv['problemas']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
}

// Agora processa com e sem filtros
echo "<hr><h3>Teste com Filtros:</h3>";

$resultado_sem_filtro = processarVendasCSV($arquivo, []);
echo "<p><strong>SEM FILTROS:</strong> " . count($resultado_sem_filtro['vendas']) . " vendas processadas, " .
     count($resultado_sem_filtro['log_ignorados']) . " ignoradas</p>";

// Testa com filtro de status
$resultado_com_status = processarVendasCSV($arquivo, ['status' => 'Ativo']);
echo "<p><strong>COM FILTRO STATUS='Ativo':</strong> " . count($resultado_com_status['vendas']) . " vendas processadas, " .
     count($resultado_com_status['log_ignorados']) . " ignoradas</p>";

if (count($resultado_com_status['log_ignorados']) > 0) {
    echo "<details><summary>Ver vendas filtradas por status (" . count($resultado_com_status['log_ignorados']) . ")</summary>";
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
    echo "<tr><th>Linha</th><th>ID</th><th>Motivo</th></tr>";
    foreach ($resultado_com_status['log_ignorados'] as $ignorado) {
        if (strpos($ignorado['motivo'], 'Status') !== false) {
            echo "<tr>";
            echo "<td>{$ignorado['linha']}</td>";
            echo "<td>" . htmlspecialchars($ignorado['id']) . "</td>";
            echo "<td>" . htmlspecialchars($ignorado['motivo']) . "</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
    echo "</details>";
}

// Testa com filtros de data
$config = carregarConfiguracoes();
$periodo = $config['periodo_relatorio'] ?? [];
if (!empty($periodo['data_inicial']) && !empty($periodo['data_final'])) {
    $filtros_completos = [
        'status' => $periodo['filtro_status'] ?? null,
        'data_inicial' => $periodo['data_inicial'],
        'data_final' => $periodo['data_final']
    ];

    $resultado_completo = processarVendasCSV($arquivo, $filtros_completos);
    echo "<p><strong>COM FILTROS DO CONFIG (Status='" . ($periodo['filtro_status'] ?? 'N/A') . "', " .
         "Data={$periodo['data_inicial']} até {$periodo['data_final']}):</strong><br>";
    echo count($resultado_completo['vendas']) . " vendas processadas, " .
         count($resultado_completo['log_ignorados']) . " ignoradas</p>";

    if (count($resultado_completo['log_ignorados']) > 0) {
        echo "<details><summary>Ver vendas filtradas (" . count($resultado_completo['log_ignorados']) . ")</summary>";
        echo "<div style='max-height:400px; overflow-y:auto;'>";
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
        echo "<tr><th>Linha</th><th>ID</th><th>Motivo</th></tr>";
        foreach ($resultado_completo['log_ignorados'] as $ignorado) {
            echo "<tr>";
            echo "<td>{$ignorado['linha']}</td>";
            echo "<td>" . htmlspecialchars($ignorado['id']) . "</td>";
            echo "<td>" . htmlspecialchars($ignorado['motivo']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";
        echo "</details>";
    }
}

echo "<p style='margin-top:20px;'><strong>CONCLUSÃO:</strong> ";
$diff = $total_linhas - $linhas_validas;
if ($diff == count($linhas_invalidas)) {
    echo "As $diff vendas faltando são linhas com problemas de validação.";
} else {
    echo "Diferença não explicada: $total_linhas linhas - $linhas_validas válidas = $diff faltando, " .
         "mas " . count($linhas_invalidas) . " inválidas.";
}
echo "</p>";
?>

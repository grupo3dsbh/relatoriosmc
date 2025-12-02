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

echo "<h2>DEBUG - Processamento de Vendas</h2>";
echo "<p><strong>Arquivo:</strong> " . basename($arquivo) . "</p>";

// Conta linhas do arquivo
$total_linhas_arquivo = count(file($arquivo));
echo "<p><strong>Total de linhas no arquivo:</strong> $total_linhas_arquivo</p>";

// Processa vendas
$resultado = processarVendasCSV($arquivo);

$total_vendas_processadas = count($resultado['vendas']);
$total_ignoradas = count($resultado['log_ignorados']);

echo "<p><strong>Vendas processadas:</strong> $total_vendas_processadas</p>";
echo "<p><strong>Vendas ignoradas:</strong> $total_ignoradas</p>";

echo "<h3>Cálculo:</h3>";
echo "<ul>";
echo "<li>Linhas no arquivo: $total_linhas_arquivo</li>";
echo "<li>Header: 1 linha</li>";
echo "<li>Vendas esperadas: " . ($total_linhas_arquivo - 1) . "</li>";
echo "<li>Vendas processadas: $total_vendas_processadas</li>";
echo "<li>Diferença: <strong style='color:" . (($total_linhas_arquivo - 1 - $total_vendas_processadas) == 0 ? 'green' : 'red') . "'>" .
     ($total_linhas_arquivo - 1 - $total_vendas_processadas) . "</strong></li>";
echo "</ul>";

if ($total_ignoradas > 0) {
    echo "<h3 style='color:orange;'>Vendas Ignoradas ({$total_ignoradas}):</h3>";
    echo "<div style='max-height:400px; overflow-y:auto;'>";
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
    echo "<tr><th>Linha</th><th>ID</th><th>Motivo</th></tr>";
    foreach ($resultado['log_ignorados'] as $ignorado) {
        echo "<tr>";
        echo "<td>{$ignorado['linha']}</td>";
        echo "<td>" . htmlspecialchars($ignorado['id']) . "</td>";
        echo "<td>" . htmlspecialchars($ignorado['motivo']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
}

// Verifica se a última linha é vazia
$handle = fopen($arquivo, 'r');
$ultima_linha = '';
$linhas_vazias = 0;
while (($linha = fgets($handle)) !== false) {
    if (trim($linha) === '') {
        $linhas_vazias++;
    }
    $ultima_linha = $linha;
}
fclose($handle);

echo "<h3>Verificação de Linhas Vazias:</h3>";
echo "<p>Linhas vazias encontradas: <strong>$linhas_vazias</strong></p>";
echo "<p>Última linha do arquivo:</p>";
echo "<pre style='background:#f0f0f0; padding:10px; overflow-x:auto;'>" . htmlspecialchars(substr($ultima_linha, 0, 200)) . (strlen($ultima_linha) > 200 ? '...' : '') . "</pre>";
?>

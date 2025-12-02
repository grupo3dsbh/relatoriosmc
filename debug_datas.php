<?php
session_start();
require_once 'config.php';
require_once 'functions/configuracoes.php';

echo "<h2>DEBUG - Comparação de Datas</h2>";

$config = carregarConfiguracoes();
$range = $config['ranges'][0];

echo "<h3>Range Black Week:</h3>";
echo "Data início: " . $range['data_inicio'] . "<br>";
echo "Data fim: " . $range['data_fim'] . "<br>";
echo "Ativo: " . ($range['ativo'] ? 'SIM' : 'NÃO') . "<br>";

$data_inicio = DateTime::createFromFormat('Y-m-d', $range['data_inicio']);
$data_fim = DateTime::createFromFormat('Y-m-d', $range['data_fim']);
$data_fim->setTime(23, 59, 59);

echo "<h4>DateTime objetos:</h4>";
echo "Início: " . $data_inicio->format('Y-m-d H:i:s') . "<br>";
echo "Fim: " . $data_fim->format('Y-m-d H:i:s') . "<br>";

echo "<h3>Teste de vendas:</h3>";

$vendas_teste = [
    '2025-11-21 23:59:59',
    '2025-11-22 00:00:00',
    '2025-11-22 09:00:00',
    '2025-11-22 12:00:00',
    '2025-11-30 23:59:59',
    '2025-12-01 00:00:00',
];

foreach ($vendas_teste as $data_str) {
    $data = DateTime::createFromFormat('Y-m-d H:i:s', $data_str);

    $dentro_range = ($data >= $data_inicio && $data <= $data_fim);
    $cor = $dentro_range ? 'green' : 'red';

    echo "<div style='padding:5px; margin:5px 0; border:1px solid #ccc;'>";
    echo "<strong>$data_str</strong><br>";
    echo "DateTime: " . $data->format('Y-m-d H:i:s') . "<br>";
    echo "Timestamp: " . $data->getTimestamp() . "<br>";
    echo "<span style='color:$cor; font-weight:bold;'>";
    echo $dentro_range ? "✓ DENTRO do range Black Week" : "✗ FORA do range Black Week";
    echo "</span><br>";

    // Comparações detalhadas
    echo "<small>";
    echo "Início <= Data? " . ($data >= $data_inicio ? 'SIM' : 'NÃO') . " | ";
    echo "Data <= Fim? " . ($data <= $data_fim ? 'SIM' : 'NÃO');
    echo "</small>";
    echo "</div>";
}
?>
